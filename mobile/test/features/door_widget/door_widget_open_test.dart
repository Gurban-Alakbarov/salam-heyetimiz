import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/barrier/domain/entity/barrier_entities.dart';
import 'package:salam_mobile/features/barrier/domain/repository.dart';
import 'package:salam_mobile/features/door_widget/door_widget_open.dart';
import 'package:salam_mobile/features/door_widget/door_widget_service.dart';

/// Fires a canned open result, then returns a scripted sequence of status polls
/// (falling back to `queued` past the end, to simulate a command that never settles).
class _FakeBarrierRepository implements BarrierRepository {
  _FakeBarrierRepository({
    this.openResult = const Success(OpenAck(commandId: 7)),
    this.statuses = const [],
  });

  final Result<OpenAck> openResult;
  final List<Result<CommandStatus>> statuses;

  int openCalls = 0;
  int statusCalls = 0;
  int? openedDeviceId;

  @override
  Future<Result<OpenAck>> open(int deviceId) async {
    openCalls++;
    openedDeviceId = deviceId;
    return openResult;
  }

  @override
  Future<Result<CommandStatus>> status(int commandId) async {
    final i = statusCalls++;
    if (i < statuses.length) return statuses[i];
    return const Success(CommandStatus(id: 7, state: CommandState.queued));
  }

  @override
  Future<Result<OpenAck>> close(int deviceId) => throw UnimplementedError();
  @override
  Future<Result<void>> feedback(
    int commandId, {
    required bool gateMoved,
    String? comment,
  }) => throw UnimplementedError();
}

Result<CommandStatus> _ok(CommandState state) =>
    Success(CommandStatus(id: 7, state: state));
Result<CommandStatus> _err(Failure f) => Err(f);

void main() {
  const config = DoorWidgetConfig(deviceId: 37, deviceLabel: 'Bina 5');
  const baseUrl = 'https://api.example.test';
  const token = 'SECRET_TOKEN_XYZ';

  Future<void> noSleep(Duration _) async {}

  Future<DoorWidgetOpenOutcome> runWith(List<Result<CommandStatus>> statuses) =>
      performDoorWidgetOpen(
        config: config,
        accessToken: token,
        baseUrl: baseUrl,
        repositoryFactory: (b, t) =>
            _FakeBarrierRepository(statuses: statuses),
        sleep: noSleep,
      );

  group('preconditions', () {
    test('no configured device → noDevice, never builds a client', () async {
      var built = false;
      final outcome = await performDoorWidgetOpen(
        config: null,
        accessToken: token,
        baseUrl: baseUrl,
        sleep: noSleep,
        repositoryFactory: (b, t) {
          built = true;
          return _FakeBarrierRepository();
        },
      );
      expect(outcome, DoorWidgetOpenOutcome.noDevice);
      expect(built, isFalse);
    });

    test('null token → sessionExpired (no background refresh)', () async {
      final outcome = await performDoorWidgetOpen(
        config: config,
        accessToken: null,
        baseUrl: baseUrl,
        sleep: noSleep,
        repositoryFactory: (b, t) => _FakeBarrierRepository(),
      );
      expect(outcome, DoorWidgetOpenOutcome.sessionExpired);
    });

    test('missing base URL → error', () async {
      final outcome = await performDoorWidgetOpen(
        config: config,
        accessToken: token,
        baseUrl: null,
        sleep: noSleep,
        repositoryFactory: (b, t) => _FakeBarrierRepository(),
      );
      expect(outcome, DoorWidgetOpenOutcome.error);
    });
  });

  group('open + poll → real command result', () {
    test('202 → polling starts on the CONFIGURED device', () async {
      final repo = _FakeBarrierRepository(statuses: [_ok(CommandState.opened)]);
      await performDoorWidgetOpen(
        config: config,
        accessToken: token,
        baseUrl: baseUrl,
        repositoryFactory: (b, t) => repo,
        sleep: noSleep,
      );
      expect(repo.openCalls, 1);
      expect(repo.openedDeviceId, 37);
      expect(repo.statusCalls, greaterThanOrEqualTo(1));
    });

    test('opened → opened (the only "Qapı açıldı ✓")', () async {
      expect(await runWith([_ok(CommandState.opened)]), DoorWidgetOpenOutcome.opened);
    });

    test('queued then opened → opened', () async {
      expect(
        await runWith([_ok(CommandState.queued), _ok(CommandState.opened)]),
        DoorWidgetOpenOutcome.opened,
      );
    });

    test('dispatching then opened → opened', () async {
      expect(
        await runWith([_ok(CommandState.dispatching), _ok(CommandState.opened)]),
        DoorWidgetOpenOutcome.opened,
      );
    });

    test('dispatched → sent (delivered, no actuation feedback)', () async {
      expect(await runWith([_ok(CommandState.dispatched)]), DoorWidgetOpenOutcome.sent);
    });

    test('failed → failed', () async {
      expect(await runWith([_ok(CommandState.failed)]), DoorWidgetOpenOutcome.failed);
    });

    test('expired → noResponse', () async {
      expect(await runWith([_ok(CommandState.expired)]), DoorWidgetOpenOutcome.noResponse);
    });
  });

  group('poll resilience — transient network errors are tolerated', () {
    test('202 → transient network error → retry → opened', () async {
      expect(
        await runWith([
          _ok(CommandState.queued),
          _err(const NetworkFailure()),
          _ok(CommandState.opened),
        ]),
        DoorWidgetOpenOutcome.opened,
      );
    });

    test('202 → several consecutive transient errors → opened', () async {
      expect(
        await runWith([
          _err(const NetworkFailure()),
          _err(const TimeoutFailure()),
          _err(const NetworkFailure()),
          _ok(CommandState.opened),
        ]),
        DoorWidgetOpenOutcome.opened,
      );
    });

    // Reproduces production cmd 494: 3 non-terminal polls, then the background poll
    // drops, then the command reaches `opened` — the widget MUST end on "Qapı açıldı ✓".
    test('AUDIT 494: queued×3 → dropped poll → opened', () async {
      expect(
        await runWith([
          _ok(CommandState.queued),
          _ok(CommandState.queued),
          _ok(CommandState.queued),
          _err(const NetworkFailure()),
          _ok(CommandState.opened),
        ]),
        DoorWidgetOpenOutcome.opened,
      );
    });

    test('202 → transient errors exhaust → notConfirmed (NOT failed/network)', () async {
      final repo = _FakeBarrierRepository(
        statuses: List.filled(10, _err(const NetworkFailure())),
      );
      final outcome = await performDoorWidgetOpen(
        config: config,
        accessToken: token,
        baseUrl: baseUrl,
        repositoryFactory: (b, t) => repo,
        sleep: noSleep,
      );
      expect(outcome, DoorWidgetOpenOutcome.notConfirmed);
      expect(outcome, isNot(DoorWidgetOpenOutcome.failed));
      expect(outcome, isNot(DoorWidgetOpenOutcome.networkError));
      // Bounded: stops at the transient-error tolerance, well within the poll cap.
      expect(repo.statusCalls, kDoorWidgetMaxTransientPollErrors + 1);
    });

    test('a poll network error NEVER yields "failed"', () async {
      final outcome = await runWith([_err(const NetworkFailure())]);
      // (falls back to queued, then exhausts → notConfirmed) — never a false failure.
      expect(outcome, isNot(DoorWidgetOpenOutcome.failed));
      expect(outcome, DoorWidgetOpenOutcome.notConfirmed);
    });

    test('non-transient poll error (5xx) → notConfirmed, not failure', () async {
      expect(
        await runWith([_err(const ServerFailure())]),
        DoorWidgetOpenOutcome.notConfirmed,
      );
    });

    test('never settles → bounded poll → notConfirmed', () async {
      final repo = _FakeBarrierRepository(); // always queued (Success, non-terminal)
      final outcome = await performDoorWidgetOpen(
        config: config,
        accessToken: token,
        baseUrl: baseUrl,
        repositoryFactory: (b, t) => repo,
        sleep: noSleep,
      );
      expect(outcome, DoorWidgetOpenOutcome.notConfirmed);
      expect(repo.statusCalls, doorWidgetMaxPolls(5000)); // exactly the cap, never more
    });
  });

  group('open failure mapping (Stage 1, no poll)', () {
    test('401 → sessionExpired, never polls (no background refresh)', () async {
      final repo = _FakeBarrierRepository(
        openResult: const Err(UnauthorizedFailure()),
      );
      final outcome = await performDoorWidgetOpen(
        config: config,
        accessToken: token,
        baseUrl: baseUrl,
        repositoryFactory: (b, t) => repo,
        sleep: noSleep,
      );
      expect(outcome, DoorWidgetOpenOutcome.sessionExpired);
      expect(repo.statusCalls, 0);
    });

    test('403 → unauthorized (server authorization is canonical)', () async {
      final outcome = await performDoorWidgetOpen(
        config: config,
        accessToken: token,
        baseUrl: baseUrl,
        repositoryFactory: (b, t) =>
            _FakeBarrierRepository(openResult: const Err(ForbiddenFailure())),
        sleep: noSleep,
      );
      expect(outcome, DoorWidgetOpenOutcome.unauthorized);
    });

    test('network failure on the OPEN → networkError (request never reached server)', () async {
      final outcome = await performDoorWidgetOpen(
        config: config,
        accessToken: token,
        baseUrl: baseUrl,
        repositoryFactory: (b, t) =>
            _FakeBarrierRepository(openResult: const Err(NetworkFailure())),
        sleep: noSleep,
      );
      expect(outcome, DoorWidgetOpenOutcome.networkError);
    });
  });

  group('doorWidgetMaxPolls — bounded', () {
    test('never exceeds the hard cap', () {
      expect(doorWidgetMaxPolls(5000), kDoorWidgetMaxPolls);
      expect(doorWidgetMaxPolls(999999), kDoorWidgetMaxPolls);
    });

    test('small expected completion → few polls', () {
      expect(doorWidgetMaxPolls(1000), lessThanOrEqualTo(kDoorWidgetMaxPolls));
      expect(doorWidgetMaxPolls(1000), greaterThan(0));
    });
  });

  group('doorWidgetShouldFire — rapid-tap guard', () {
    test('fires when there is no prior attempt', () {
      expect(doorWidgetShouldFire(1000, null), isTrue);
    });
    test('blocks a second tap inside the cooldown window', () {
      const now = 1000000;
      expect(doorWidgetShouldFire(now, now - 500), isFalse);
    });
    test('fires again once the cooldown has elapsed', () {
      const now = 1000000;
      expect(
        doorWidgetShouldFire(now, now - (kDoorWidgetCooldown.inMilliseconds + 1)),
        isTrue,
      );
    });
  });

  group('doorWidgetOutcomeCode — non-localized state codes (W5 D1)', () {
    test('opened maps to "opened"; sent to "sent" (never "opened" from a 202)', () {
      expect(doorWidgetOutcomeCode(DoorWidgetOpenOutcome.opened), 'opened');
      expect(doorWidgetOutcomeCode(DoorWidgetOpenOutcome.sent), 'sent');
    });

    test('notConfirmed has its own code, not failed/network', () {
      final code = doorWidgetOutcomeCode(DoorWidgetOpenOutcome.notConfirmed);
      expect(code, 'not_confirmed');
      expect(code, isNot('failed'));
      expect(code, isNot('network_error'));
    });

    test('every outcome is a unique plain-ascii slug (never localized text)', () {
      final codes = <String>{};
      for (final outcome in DoorWidgetOpenOutcome.values) {
        final code = doorWidgetOutcomeCode(outcome);
        expect(code.isNotEmpty, isTrue);
        // A CODE is an ascii slug the native layer localizes — never user-facing text,
        // so a token can never leak through it either.
        expect(RegExp(r'^[a-z_]+$').hasMatch(code), isTrue, reason: code);
        expect(code.contains(token), isFalse);
        expect(codes.add(code), isTrue); // unique per outcome
      }
      expect(codes.length, DoorWidgetOpenOutcome.values.length);
    });
  });
}
