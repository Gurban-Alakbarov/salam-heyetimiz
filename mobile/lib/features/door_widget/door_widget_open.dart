import 'package:flutter/widgets.dart';
import 'package:home_widget/home_widget.dart';

import '../../core/error/failure.dart';
import '../../core/network/api_client.dart';
import '../../core/storage/app_storage.dart';
import '../../core/storage/storage_keys.dart';
import '../barrier/data/datasource/barrier_remote_datasource.dart';
import '../barrier/data/repository_impl.dart';
import '../barrier/domain/entity/barrier_entities.dart';
import '../barrier/domain/repository.dart';
import 'door_widget_service.dart';

/// Terminal result of a background open attempt. W5: mapped to a non-localized state
/// CODE stored in widget prefs; the native provider turns the code into the localized
/// string (AZ/RU/EN). The background isolate therefore stays locale-agnostic.
enum DoorWidgetOpenOutcome {
  opened, // CommandState.opened — actuation CONFIRMED
  sent, // CommandState.dispatched — sent, driver doesn't confirm actuation
  failed, // CommandState.failed
  noResponse, // CommandState.expired
  notConfirmed, // got a 202 but couldn't confirm (transient errors / non-terminal)
  sessionExpired, // 401 / missing token on the OPEN (NO background refresh)
  unauthorized, // 403 on the OPEN — server authorization is canonical
  networkError, // network/timeout on the OPEN (Stage 1) — never reached server
  noDevice, // no configured device
  error, // generic / unknown
}

const Duration kDoorWidgetCooldown = Duration(seconds: 4);
const Duration kDoorWidgetPollInterval = Duration(milliseconds: 1200);
const int kDoorWidgetMaxPolls = 6;
const int kDoorWidgetMaxTransientPollErrors = 3;

/// Transient in-progress code written before/while polling.
const String kDoorWidgetOpeningCode = 'opening';

/// Background entry point. W5: the widget's click URI carries `?widgetId=<AppWidgetId>`,
/// so the callback reads/writes ONLY that instance's config + status + cooldown. Runs
/// in the home_widget background isolate — no ProviderScope, no SessionManager. Never
/// refreshes the session (Q2); writes NO token/secret to widget storage.
@pragma('vm:entry-point')
Future<void> doorWidgetOpenCallback(Uri? uri) async {
  WidgetsFlutterBinding.ensureInitialized();
  if (uri != null && uri.host != 'open') return;

  final widgetId = int.tryParse(uri?.queryParameters['widgetId'] ?? '');
  const service = DoorWidgetService();
  final statusKey = DoorWidgetService.resolvedStatusKey(widgetId);
  final lastOpenKey = DoorWidgetService.resolvedLastOpenKey(widgetId);

  // No configured device → native renders the localized "no device" hint from the
  // absent label; clear any stale status code so nothing contradictory lingers.
  final config = await service.current(widgetId);
  if (config == null) {
    await HomeWidget.saveWidgetData<String>(statusKey, null);
    await service.refresh();
    return;
  }

  // Debounce rapid taps (per instance).
  final nowMs = DateTime.now().millisecondsSinceEpoch;
  final lastMs = await _readIntKey(lastOpenKey);
  if (!doorWidgetShouldFire(nowMs, lastMs)) {
    await _writeCode(statusKey, kDoorWidgetOpeningCode);
    return;
  }
  await HomeWidget.saveWidgetData<int>(lastOpenKey, nowMs);
  await _writeCode(statusKey, kDoorWidgetOpeningCode);

  final outcome = await performDoorWidgetOpen(
    config: config,
    accessToken: await const SecureStore().read(StorageKeys.accessToken),
    baseUrl: await service.baseUrl(),
    repositoryFactory: backgroundBarrierRepository,
  );

  await _writeCode(statusKey, doorWidgetOutcomeCode(outcome));
}

/// Whether a fresh open should fire given the last-attempt time (pure → testable).
bool doorWidgetShouldFire(int nowMs, int? lastMs) =>
    lastMs == null || (nowMs - lastMs) >= kDoorWidgetCooldown.inMilliseconds;

/// Bounded poll count for a command whose ack promised [expectedCompletionMs].
int doorWidgetMaxPolls(int expectedCompletionMs) {
  final computed =
      (expectedCompletionMs / kDoorWidgetPollInterval.inMilliseconds).ceil() + 2;
  return computed < kDoorWidgetMaxPolls ? computed : kDoorWidgetMaxPolls;
}

/// Testable core. Fires ONE open, then BOUNDED-polls `GET /v1/commands/{id}` (reusing
/// [BarrierRepository]) until a terminal state or the cap. A 202 is in hand, so a
/// TRANSIENT poll error is retried (bounded) rather than a false failure; on
/// exhaustion → `notConfirmed`. Never refreshes (Q2). Returns an outcome only — the
/// token is never returned/persisted. [sleep] is injectable so tests run instantly.
Future<DoorWidgetOpenOutcome> performDoorWidgetOpen({
  required DoorWidgetConfig? config,
  required String? accessToken,
  required String? baseUrl,
  required BarrierRepository Function(String baseUrl, String accessToken)
  repositoryFactory,
  Future<void> Function(Duration)? sleep,
}) async {
  if (config == null) return DoorWidgetOpenOutcome.noDevice;
  if (accessToken == null || accessToken.isEmpty) {
    return DoorWidgetOpenOutcome.sessionExpired;
  }
  if (baseUrl == null || baseUrl.isEmpty) return DoorWidgetOpenOutcome.error;

  final repo = repositoryFactory(baseUrl, accessToken);

  final openResult = await repo.open(config.deviceId);
  final OpenAck ack;
  switch (openResult) {
    case Err(:final failure):
      return _outcomeForFailure(failure);
    case Success(:final value):
      ack = value; // 202 accepted — NOT yet "opened"
  }

  final wait = sleep ?? (d) => Future<void>.delayed(d);
  final maxPolls = doorWidgetMaxPolls(ack.expectedCompletionMs);
  var transientErrors = 0;
  for (var attempt = 0; attempt < maxPolls; attempt++) {
    await wait(kDoorWidgetPollInterval);
    final statusResult = await repo.status(ack.commandId);
    switch (statusResult) {
      case Err(:final failure):
        if (failure is NetworkFailure || failure is TimeoutFailure) {
          if (++transientErrors > kDoorWidgetMaxTransientPollErrors) {
            return DoorWidgetOpenOutcome.notConfirmed;
          }
          continue; // transient — retry within the bounded window
        }
        return DoorWidgetOpenOutcome.notConfirmed; // accepted, couldn't confirm
      case Success(:final value):
        transientErrors = 0;
        switch (value.state) {
          case CommandState.opened:
            return DoorWidgetOpenOutcome.opened;
          case CommandState.dispatched:
            return DoorWidgetOpenOutcome.sent;
          case CommandState.failed:
            return DoorWidgetOpenOutcome.failed;
          case CommandState.expired:
            return DoorWidgetOpenOutcome.noResponse;
          case CommandState.queued:
          case CommandState.dispatching:
          case CommandState.unknown:
            break; // non-terminal → keep polling
        }
    }
  }
  return DoorWidgetOpenOutcome.notConfirmed; // exhausted, result unconfirmed
}

BarrierRepository backgroundBarrierRepository(
  String baseUrl,
  String accessToken,
) => BarrierRepositoryImpl(
  BarrierRemoteDataSource(
    ApiClient.background(
      baseUrl: baseUrl,
      accessToken: accessToken,
      localeCode: 'az',
    ),
  ),
);

DoorWidgetOpenOutcome _outcomeForFailure(Failure failure) {
  if (failure is UnauthorizedFailure) return DoorWidgetOpenOutcome.sessionExpired;
  if (failure is ForbiddenFailure) return DoorWidgetOpenOutcome.unauthorized;
  if (failure is NetworkFailure || failure is TimeoutFailure) {
    return DoorWidgetOpenOutcome.networkError;
  }
  return DoorWidgetOpenOutcome.error;
}

/// Maps a terminal outcome to a NON-LOCALIZED state code stored in widget prefs. The
/// native provider localizes it (AZ/RU/EN string resources). `opened` is the ONLY
/// code that renders "door opened" — never produced from a bare 202.
String doorWidgetOutcomeCode(DoorWidgetOpenOutcome outcome) => switch (outcome) {
  DoorWidgetOpenOutcome.opened => 'opened',
  DoorWidgetOpenOutcome.sent => 'sent',
  DoorWidgetOpenOutcome.failed => 'failed',
  DoorWidgetOpenOutcome.noResponse => 'no_response',
  DoorWidgetOpenOutcome.notConfirmed => 'not_confirmed',
  DoorWidgetOpenOutcome.sessionExpired => 'session_expired',
  DoorWidgetOpenOutcome.unauthorized => 'unauthorized',
  DoorWidgetOpenOutcome.networkError => 'network_error',
  DoorWidgetOpenOutcome.noDevice => 'no_device',
  DoorWidgetOpenOutcome.error => 'error',
};

Future<void> _writeCode(String statusKey, String code) async {
  await HomeWidget.saveWidgetData<String>(statusKey, code);
  await HomeWidget.updateWidget(
    qualifiedAndroidName: DoorWidgetService.androidProvider,
  );
}

Future<int?> _readIntKey(String key) async {
  final raw = await HomeWidget.getWidgetData<dynamic>(key);
  return raw is int ? raw : (raw is num ? raw.toInt() : null);
}
