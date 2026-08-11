import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/barrier/data/repository_impl.dart';
import 'package:salam_mobile/features/barrier/domain/entity/barrier_entities.dart';

import '../../helpers/mocks.dart';

void main() {
  late MockBarrierRemoteDataSource remote;
  late BarrierRepositoryImpl repo;

  setUp(() {
    remote = MockBarrierRemoteDataSource();
    repo = BarrierRepositoryImpl(remote);
  });

  test('open success → OpenAck', () async {
    when(() => remote.open(any())).thenAnswer(
      (_) async => {
        'command_id': 5678,
        'state': 'queued',
        'expected_completion_ms': 3000,
        'driver_confirms_actuation': true,
      },
    );

    final result = await repo.open(42);

    expect(result.isSuccess, isTrue);
    final ack = (result as Success).value;
    expect(ack.commandId, 5678);
    expect(ack.state, CommandState.queued);
    expect(ack.expectedCompletionMs, 3000);
  });

  test('close success → OpenAck (Phase 5)', () async {
    when(() => remote.close(any())).thenAnswer(
      (_) async => {
        'command_id': 9012,
        'state': 'queued',
        'expected_completion_ms': 3000,
        'driver_confirms_actuation': true,
      },
    );

    final result = await repo.close(42);

    expect(result.isSuccess, isTrue);
    final ack = (result as Success).value;
    expect(ack.commandId, 9012);
    expect(ack.state, CommandState.queued);
  });

  test('close device_offline (502) → DeviceOfflineFailure', () async {
    when(() => remote.close(any())).thenThrow(
      dioError(
        status: 502,
        body: {
          'error': {'code': 'device_offline', 'message': 'unreachable'},
        },
      ),
    );

    final result = await repo.close(42);

    expect((result as Err).failure, isA<DeviceOfflineFailure>());
  });

  test('open device_offline (502) → DeviceOfflineFailure', () async {
    when(() => remote.open(any())).thenThrow(
      dioError(
        status: 502,
        body: {
          'error': {'code': 'device_offline', 'message': 'unreachable'},
        },
      ),
    );

    final result = await repo.open(42);

    expect((result as Err).failure, isA<DeviceOfflineFailure>());
  });

  test(
    'open subscription_required (403) → ForbiddenFailure with code',
    () async {
      when(() => remote.open(any())).thenThrow(
        dioError(
          status: 403,
          body: {
            'error': {'code': 'subscription_required'},
          },
        ),
      );

      final failure = (await repo.open(42) as Err).failure;
      expect(failure, isA<ForbiddenFailure>());
      expect(failure.code, 'subscription_required');
    },
  );

  test('status maps a terminal opened command', () async {
    when(() => remote.status(any())).thenAnswer(
      (_) async => {'id': 5678, 'state': 'opened', 'latency_ms': 3000},
    );

    final status = (await repo.status(5678) as Success).value;
    expect(status.state, CommandState.opened);
    expect(status.state.isSuccess, isTrue);
    expect(status.state.isTerminal, isTrue);
  });

  test('CommandState terminal/success/failure classification', () {
    expect(CommandState.queued.isTerminal, isFalse);
    expect(CommandState.dispatching.isTerminal, isFalse);
    expect(CommandState.opened.isSuccess, isTrue);
    expect(CommandState.dispatched.isSuccess, isTrue);
    expect(CommandState.failed.isFailure, isTrue);
    expect(CommandState.expired.isFailure, isTrue);
  });
}
