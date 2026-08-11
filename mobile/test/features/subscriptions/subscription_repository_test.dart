import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/subscriptions/data/repository_impl.dart';
import 'package:salam_mobile/features/subscriptions/domain/entity/subscription_entities.dart';

import '../../helpers/mocks.dart';

void main() {
  late MockSubscriptionRemoteDataSource remote;
  late SubscriptionRepositoryImpl repo;

  setUp(() {
    remote = MockSubscriptionRemoteDataSource();
    repo = SubscriptionRepositoryImpl(remote);
  });

  test('listActive parses {data, page} into Subscriptions (snake_case wire)', () async {
    when(
      () => remote.list(
        status: any(named: 'status'),
        limit: any(named: 'limit'),
        cursor: any(named: 'cursor'),
      ),
    ).thenAnswer(
      (_) async => {
        'data': [
          {
            'id': 10,
            'tier': 'main',
            'status': 'active',
            'starts_at': '2026-01-01T00:00:00Z',
            'ends_at': '2026-12-31T00:00:00Z',
            'days_remaining': 142,
            'auto_renew': true,
            'device_id': 5,
            'term_days': 365,
          },
          {
            'id': 11,
            'tier': 'additional',
            'status': 'active',
            'ends_at': '2026-09-01T00:00:00Z',
            'days_remaining': 20,
            'device_id': 6,
          },
        ],
        'page': {'next_cursor': null, 'has_more': false, 'limit': 100},
      },
    );

    final result = await repo.listActive();

    expect(result.isSuccess, isTrue);
    final subs = (result as Success).value;
    // Multiple active subscriptions render as separate rows.
    expect(subs.length, 2);

    final first = subs.first;
    expect(first.id, 10);
    expect(first.tier, 'main');
    expect(first.isActive, isTrue);
    expect(first.deviceId, 5); // device_id → resolved against the device list
    expect(first.daysRemaining, 142); // server value, taken as-is
    expect(first.termDays, 365);
    expect(first.autoRenew, isTrue);
    expect(first.startsAt, isNotNull);

    // Second row: no starts_at/term_days, but still parses.
    expect(subs[1].deviceId, 6);
    expect(subs[1].daysRemaining, 20);
    expect(subs[1].startsAt, isNull);
  });

  test('listActive returns an empty list when the resident has none', () async {
    when(
      () => remote.list(
        status: any(named: 'status'),
        limit: any(named: 'limit'),
        cursor: any(named: 'cursor'),
      ),
    ).thenAnswer((_) async => {'data': const [], 'page': const {}});

    final result = await repo.listActive();

    expect(result.isSuccess, isTrue);
    expect((result as Success).value, isEmpty);
  });

  test('listActive maps 401 → UnauthorizedFailure', () async {
    when(
      () => remote.list(
        status: any(named: 'status'),
        limit: any(named: 'limit'),
        cursor: any(named: 'cursor'),
      ),
    ).thenThrow(dioError(status: 401));

    final result = await repo.listActive();

    expect((result as Err).failure, isA<UnauthorizedFailure>());
  });

  test('listActive maps connectionError → NetworkFailure', () async {
    when(
      () => remote.list(
        status: any(named: 'status'),
        limit: any(named: 'limit'),
        cursor: any(named: 'cursor'),
      ),
    ).thenThrow(dioOffline());

    final result = await repo.listActive();

    expect((result as Err).failure, isA<NetworkFailure>());
  });

  group('Subscription.progressAt (client-side term progress)', () {
    final start = DateTime.utc(2026, 1, 1);
    final end = DateTime.utc(2026, 1, 11); // 10-day term

    test('is 0.5 at the midpoint', () {
      const sub = Subscription(id: 1, deviceId: 1);
      final withDates = sub.copyWith(startsAt: start, endsAt: end);
      expect(withDates.progressAt(DateTime.utc(2026, 1, 6)), closeTo(0.5, 0.001));
    });

    test('clamps to 1.0 past the end date', () {
      const sub = Subscription(id: 1);
      final withDates = sub.copyWith(startsAt: start, endsAt: end);
      expect(withDates.progressAt(DateTime.utc(2026, 2, 1)), 1.0);
    });

    test('is null when the dates are missing', () {
      const sub = Subscription(id: 1);
      expect(sub.progressAt(DateTime.utc(2026, 1, 6)), isNull);
    });
  });
}
