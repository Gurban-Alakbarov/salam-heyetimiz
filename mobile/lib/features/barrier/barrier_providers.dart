import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import '../../core/error/failure.dart';
import '../../core/location/location_service.dart';
import 'data/datasource/barrier_remote_datasource.dart';
import 'data/repository_impl.dart';
import 'domain/entity/barrier_entities.dart';
import 'domain/repository.dart';

/// Open (Aç) vs Close (Bağla) — both flow through the same DeviceComm pipeline
/// (POST /v1/devices/{id}/open with an optional `direction`); only the command
/// text the device model resolves differs (VL110C RELAY,1# / RELAY,0#).
enum BarrierDirection { open, close }

class BarrierEvents {
  BarrierEvents._();
  static const openStarted = 'barrier_open_started';
  static const openSuccess = 'barrier_open_success';
  static const openFailed = 'barrier_open_failed';
  static const openTimeout = 'barrier_open_timeout';
  static const closeStarted = 'barrier_close_started';
  static const closeSuccess = 'barrier_close_success';
  static const closeFailed = 'barrier_close_failed';
  static const closeTimeout = 'barrier_close_timeout';
}

final barrierRepositoryProvider = Provider<BarrierRepository>(
  (ref) => BarrierRepositoryImpl(
    BarrierRemoteDataSource(
      ref.watch(apiClientProvider),
      appVersion: ref.watch(appVersionProvider),
    ),
  ),
);

/// Barrier-command view state (idle → sending → pending → success|failed|timeout|cooldown).
/// Shared by both the open and close notifiers.
sealed class BarrierOpenState {
  const BarrierOpenState();
}

class BarrierIdle extends BarrierOpenState {
  const BarrierIdle();
}

class BarrierSending extends BarrierOpenState {
  const BarrierSending();
}

/// GEOFENCE-3 — acquiring the caller's GPS fix before the request (geofenced device
/// only). Treated as in-flight by the UI so the button stays locked.
class BarrierLocating extends BarrierOpenState {
  const BarrierLocating();
}

class BarrierPending extends BarrierOpenState {
  const BarrierPending({
    required this.commandId,
    required this.expectedMs,
    required this.elapsedMs,
  });
  final int commandId;
  final int expectedMs;
  final int elapsedMs;

  double get progress =>
      expectedMs <= 0 ? 0 : (elapsedMs / expectedMs).clamp(0.0, 0.99);
}

class BarrierSuccess extends BarrierOpenState {
  const BarrierSuccess({required this.commandId, required this.actuated});
  final int commandId;

  /// true = device confirmed actuation (`opened`); false = `dispatched` (sent,
  /// no feedback — UI may ask the user whether the gate moved).
  final bool actuated;
}

class BarrierFailed extends BarrierOpenState {
  const BarrierFailed(this.failure, [this.reason]);
  final Failure failure;
  final String? reason;
}

class BarrierTimeout extends BarrierOpenState {
  const BarrierTimeout();
}

class BarrierCooldown extends BarrierOpenState {
  const BarrierCooldown(this.retryAfterSeconds);
  final int retryAfterSeconds;
}

/// The command state machine + polling loop (Constitution §5, §14.5,
/// API_INTEGRATION.md §8). Exactly one poller; stops on terminal state; cancels
/// on dispose; a single in-flight lock prevents duplicate commands. Both the
/// open and close notifiers mix this in; only [sendCommand] (open vs close on
/// the repository) and the analytics event names differ.
mixin BarrierCommandMachine on Notifier<BarrierOpenState> {
  static const _pollInterval = Duration(milliseconds: 1200);

  Timer? _pollTimer;
  DateTime? _start;
  bool _inFlight = false;
  int _polls = 0;
  int _maxPolls = 0;
  int _expectedMs = 5000;

  /// Issue the command (repository.open or repository.close). [fix] is attached
  /// only for geofenced devices (GEOFENCE-3); null keeps the historical body.
  Future<Result<OpenAck>> sendCommand(int deviceId, {GeoFix? fix});

  String get attemptLog;
  String get eventStarted;
  String get eventSuccess;
  String get eventFailed;
  String get eventTimeout;

  void attachDispose() => ref.onDispose(_stopPolling);

  /// User pressed the action. Ignored if a command is already in flight.
  ///
  /// GEOFENCE-3 — when [geofenceEnabled] is true, a fresh foreground GPS fix is
  /// acquired FIRST and attached to the request. Any acquisition failure ends in
  /// [BarrierFailed] with a [LocationFailure] and NO request is sent. When false
  /// the flow is byte-identical to before (no GPS call).
  Future<void> dispatch(int deviceId, {bool geofenceEnabled = false}) async {
    if (_inFlight) return;
    _inFlight = true;
    _start = DateTime.now();
    ref.read(crashReporterProvider).log(attemptLog);

    GeoFix? fix;
    if (geofenceEnabled) {
      state = const BarrierLocating();
      final located = await ref.read(locationServiceProvider).getCurrent();
      switch (located) {
        case LocationOk(fix: final acquired):
          fix = acquired;
        case LocationServiceDisabled():
          _failLocation('service_disabled');
          return;
        case LocationPermissionDenied():
          _failLocation('permission_denied');
          return;
        case LocationPermissionPermanentlyDenied():
          _failLocation('permanently_denied');
          return;
        case LocationTimeout():
          _failLocation('timeout');
          return;
        case LocationError():
          _failLocation('error');
          return;
      }
    }

    state = const BarrierSending();
    final result = await sendCommand(deviceId, fix: fix);
    result.fold(
      (failure) {
        _inFlight = false;
        state = switch (failure) {
          RateLimitedFailure(:final retryAfterSeconds) => BarrierCooldown(
            retryAfterSeconds,
          ),
          _ => BarrierFailed(failure, _reason(failure)),
        };
        _logFailed(_reason(failure));
      },
      (ack) {
        _expectedMs = ack.expectedCompletionMs;
        _maxPolls =
            (ack.expectedCompletionMs / _pollInterval.inMilliseconds).ceil() +
            3;
        _polls = 0;
        state = BarrierPending(
          commandId: ack.commandId,
          expectedMs: _expectedMs,
          elapsedMs: 0,
        );
        ref.read(analyticsProvider).logEvent(eventStarted);
        _startPolling(ack.commandId);
      },
    );
  }

  void _startPolling(int commandId) {
    _stopPolling();
    _pollTimer = Timer.periodic(_pollInterval, (_) {
      _polls++;
      if (_polls > _maxPolls) {
        _terminate(const BarrierTimeout());
        _logTimeout();
        return;
      }
      _poll(commandId);
    });
  }

  Future<void> _poll(int commandId) async {
    final result = await ref.read(barrierRepositoryProvider).status(commandId);
    if (_pollTimer == null) return; // already terminated
    result.fold(
      (failure) {
        _terminate(BarrierFailed(failure, _reason(failure)));
        _logFailed(_reason(failure));
      },
      (status) {
        final elapsed = DateTime.now().difference(_start!).inMilliseconds;
        if (status.state.isSuccess) {
          _terminate(
            BarrierSuccess(
              commandId: commandId,
              actuated: status.state == CommandState.opened,
            ),
          );
          ref
              .read(analyticsProvider)
              .logEvent(
                eventSuccess,
                params: {'latency_ms': status.latencyMs ?? elapsed},
              );
        } else if (status.state.isFailure) {
          if (status.state == CommandState.expired) {
            _terminate(const BarrierTimeout());
            _logTimeout();
          } else {
            final reason = status.failureReason ?? 'device_error';
            _terminate(BarrierFailed(const UnknownFailure(), reason));
            _logFailed(reason);
          }
        } else {
          // queued / dispatching → keep polling, update elapsed for progress.
          state = BarrierPending(
            commandId: commandId,
            expectedMs: _expectedMs,
            elapsedMs: elapsed,
          );
        }
      },
    );
  }

  /// After a `dispatched` (no actuation feedback) result, the user can confirm.
  Future<void> sendFeedback(int commandId, {required bool gateMoved}) async {
    await ref
        .read(barrierRepositoryProvider)
        .feedback(commandId, gateMoved: gateMoved);
  }

  /// Return to idle (e.g. after showing a result) so the button is usable again.
  void reset() {
    _stopPolling();
    _inFlight = false;
    state = const BarrierIdle();
  }

  void _terminate(BarrierOpenState terminal) {
    _stopPolling();
    _inFlight = false;
    state = terminal;
  }

  /// GEOFENCE-3 — GPS acquisition failed; end in [BarrierFailed] without sending a
  /// request (no cooldown reserved, no command issued).
  void _failLocation(String code) {
    _inFlight = false;
    state = BarrierFailed(LocationFailure(code), 'location_$code');
    _logFailed('location_$code');
  }

  void _stopPolling() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  void _logFailed(String reason) {
    final elapsed = _start == null
        ? 0
        : DateTime.now().difference(_start!).inMilliseconds;
    ref
        .read(analyticsProvider)
        .logEvent(
          eventFailed,
          params: {'reason': reason, 'latency_ms': elapsed},
        );
  }

  void _logTimeout() {
    final elapsed = _start == null
        ? 0
        : DateTime.now().difference(_start!).inMilliseconds;
    ref
        .read(analyticsProvider)
        .logEvent(eventTimeout, params: {'latency_ms': elapsed});
  }

  String _reason(Failure failure) => switch (failure) {
    DeviceOfflineFailure() => 'device_offline',
    ForbiddenFailure() => 'access_denied',
    RateLimitedFailure() => 'cooldown',
    NetworkFailure() || TimeoutFailure() => 'offline',
    _ => 'unknown',
  };
}

/// Open (Aç) — barrier open command state machine.
class BarrierOpenNotifier extends Notifier<BarrierOpenState>
    with BarrierCommandMachine {
  @override
  BarrierOpenState build() {
    attachDispose();
    return const BarrierIdle();
  }

  @override
  Future<Result<OpenAck>> sendCommand(int deviceId, {GeoFix? fix}) =>
      ref.read(barrierRepositoryProvider).open(deviceId, fix: fix);

  @override
  String get attemptLog => 'barrier_open_attempt';
  @override
  String get eventStarted => BarrierEvents.openStarted;
  @override
  String get eventSuccess => BarrierEvents.openSuccess;
  @override
  String get eventFailed => BarrierEvents.openFailed;
  @override
  String get eventTimeout => BarrierEvents.openTimeout;

  /// User pressed "Aç" (Open). GEOFENCE-3 — [geofenceEnabled] gates the GPS fix.
  Future<void> open(int deviceId, {bool geofenceEnabled = false}) =>
      dispatch(deviceId, geofenceEnabled: geofenceEnabled);
}

final barrierOpenProvider =
    NotifierProvider.autoDispose<BarrierOpenNotifier, BarrierOpenState>(
      BarrierOpenNotifier.new,
    );

/// Close (Bağla) — barrier close command state machine (same pipeline, direction=close).
class BarrierCloseNotifier extends Notifier<BarrierOpenState>
    with BarrierCommandMachine {
  @override
  BarrierOpenState build() {
    attachDispose();
    return const BarrierIdle();
  }

  @override
  Future<Result<OpenAck>> sendCommand(int deviceId, {GeoFix? fix}) =>
      ref.read(barrierRepositoryProvider).close(deviceId, fix: fix);

  @override
  String get attemptLog => 'barrier_close_attempt';
  @override
  String get eventStarted => BarrierEvents.closeStarted;
  @override
  String get eventSuccess => BarrierEvents.closeSuccess;
  @override
  String get eventFailed => BarrierEvents.closeFailed;
  @override
  String get eventTimeout => BarrierEvents.closeTimeout;

  /// User pressed "Bağla" (Close). GEOFENCE-3 — [geofenceEnabled] gates the GPS fix.
  Future<void> close(int deviceId, {bool geofenceEnabled = false}) =>
      dispatch(deviceId, geofenceEnabled: geofenceEnabled);
}

final barrierCloseProvider =
    NotifierProvider.autoDispose<BarrierCloseNotifier, BarrierOpenState>(
      BarrierCloseNotifier.new,
    );
