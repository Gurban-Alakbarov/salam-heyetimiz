import 'package:fake_async/fake_async.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/barrier/barrier_providers.dart';
import 'package:salam_mobile/features/barrier/domain/entity/barrier_entities.dart';

import '../../helpers/mocks.dart';

void main() {
  late MockBarrierRepository repo;

  setUp(() {
    repo = MockBarrierRepository();
    when(() => repo.open(any())).thenAnswer(
      (_) async => const Success(
        OpenAck(
          commandId: 1,
          state: CommandState.queued,
          expectedCompletionMs: 3000,
        ),
      ),
    );
  });

  ProviderContainer container() {
    final c = ProviderContainer(
      overrides: [barrierRepositoryProvider.overrideWithValue(repo)],
    );
    c.listen(barrierOpenProvider, (_, _) {}); // keep alive
    addTearDown(c.dispose);
    return c;
  }

  ProviderContainer closeContainer() {
    final c = ProviderContainer(
      overrides: [barrierRepositoryProvider.overrideWithValue(repo)],
    );
    c.listen(barrierCloseProvider, (_, _) {}); // keep alive
    addTearDown(c.dispose);
    return c;
  }

  test('queued → opened ends in BarrierSuccess and stops polling', () {
    fakeAsync((fake) {
      var statusCalls = 0;
      when(() => repo.status(any())).thenAnswer((_) async {
        statusCalls++;
        return Success(
          CommandStatus(
            id: 1,
            state: statusCalls >= 2 ? CommandState.opened : CommandState.queued,
          ),
        );
      });

      final c = container();
      c.read(barrierOpenProvider.notifier).open(42);
      fake.flushMicrotasks();
      expect(c.read(barrierOpenProvider), isA<BarrierPending>());

      fake.elapse(const Duration(milliseconds: 1300));
      fake.flushMicrotasks();
      fake.elapse(const Duration(milliseconds: 1300));
      fake.flushMicrotasks();

      final state = c.read(barrierOpenProvider);
      expect(state, isA<BarrierSuccess>());
      expect((state as BarrierSuccess).actuated, isTrue);

      // Polling stopped: advancing further does not call status again.
      final callsAfter = statusCalls;
      fake.elapse(const Duration(seconds: 5));
      fake.flushMicrotasks();
      expect(statusCalls, callsAfter);

      verify(() => repo.open(any())).called(1);
    });
  });

  test('failed command → BarrierFailed', () {
    fakeAsync((fake) {
      when(() => repo.status(any())).thenAnswer(
        (_) async => const Success(
          CommandStatus(
            id: 1,
            state: CommandState.failed,
            failureReason: 'device_error',
          ),
        ),
      );

      final c = container();
      c.read(barrierOpenProvider.notifier).open(42);
      fake.flushMicrotasks();
      fake.elapse(const Duration(milliseconds: 1300));
      fake.flushMicrotasks();

      expect(c.read(barrierOpenProvider), isA<BarrierFailed>());
    });
  });

  test('never terminal → BarrierTimeout after max polls', () {
    fakeAsync((fake) {
      when(() => repo.status(any())).thenAnswer(
        (_) async =>
            const Success(CommandStatus(id: 1, state: CommandState.queued)),
      );

      final c = container();
      c.read(barrierOpenProvider.notifier).open(42);
      fake.flushMicrotasks();

      fake.elapse(const Duration(seconds: 12));
      fake.flushMicrotasks();

      expect(c.read(barrierOpenProvider), isA<BarrierTimeout>());
    });
  });

  test('immediate failure (offline) → BarrierFailed, no polling', () {
    fakeAsync((fake) {
      when(
        () => repo.open(any()),
      ).thenAnswer((_) async => const Err(NetworkFailure()));

      final c = container();
      c.read(barrierOpenProvider.notifier).open(42);
      fake.flushMicrotasks();

      expect(c.read(barrierOpenProvider), isA<BarrierFailed>());
      verifyNever(() => repo.status(any()));
    });
  });

  test('cooldown (429) → BarrierCooldown', () {
    fakeAsync((fake) {
      when(
        () => repo.open(any()),
      ).thenAnswer((_) async => const Err(RateLimitedFailure(30)));

      final c = container();
      c.read(barrierOpenProvider.notifier).open(42);
      fake.flushMicrotasks();

      final state = c.read(barrierOpenProvider);
      expect(state, isA<BarrierCooldown>());
      expect((state as BarrierCooldown).retryAfterSeconds, 30);
    });
  });

  test('close: queued → opened ends in BarrierSuccess (Phase 5)', () {
    fakeAsync((fake) {
      when(() => repo.close(any())).thenAnswer(
        (_) async => const Success(
          OpenAck(
            commandId: 2,
            state: CommandState.queued,
            expectedCompletionMs: 3000,
          ),
        ),
      );
      var statusCalls = 0;
      when(() => repo.status(any())).thenAnswer((_) async {
        statusCalls++;
        return Success(
          CommandStatus(
            id: 2,
            state: statusCalls >= 2 ? CommandState.opened : CommandState.queued,
          ),
        );
      });

      final c = closeContainer();
      c.read(barrierCloseProvider.notifier).close(42);
      fake.flushMicrotasks();
      expect(c.read(barrierCloseProvider), isA<BarrierPending>());

      fake.elapse(const Duration(milliseconds: 1300));
      fake.flushMicrotasks();
      fake.elapse(const Duration(milliseconds: 1300));
      fake.flushMicrotasks();

      expect(c.read(barrierCloseProvider), isA<BarrierSuccess>());
      verify(() => repo.close(any())).called(1);
      verifyNever(() => repo.open(any()));
    });
  });

  test('close: double press sends exactly one command', () {
    fakeAsync((fake) {
      when(() => repo.close(any())).thenAnswer(
        (_) async => const Success(
          OpenAck(
            commandId: 2,
            state: CommandState.queued,
            expectedCompletionMs: 3000,
          ),
        ),
      );
      when(() => repo.status(any())).thenAnswer(
        (_) async =>
            const Success(CommandStatus(id: 2, state: CommandState.queued)),
      );

      final c = closeContainer();
      c.read(barrierCloseProvider.notifier)
        ..close(42)
        ..close(42);
      fake.flushMicrotasks();

      verify(() => repo.close(any())).called(1);
    });
  });

  test('double press sends exactly one command', () {
    fakeAsync((fake) {
      when(() => repo.status(any())).thenAnswer(
        (_) async =>
            const Success(CommandStatus(id: 1, state: CommandState.queued)),
      );

      final c = container();
      final notifier = c.read(barrierOpenProvider.notifier)
        ..open(42)
        ..open(42); // second press while in flight — must be ignored
      fake.flushMicrotasks();

      expect(notifier, isNotNull);
      verify(() => repo.open(any())).called(1);
    });
  });
}
