import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/invitations/data/repository_impl.dart';
import 'package:salam_mobile/features/invitations/domain/entity/invitation_entities.dart';
import 'package:salam_mobile/features/invitations/invitations_providers.dart';

import '../../helpers/mocks.dart';

void main() {
  late MockInvitationRemoteDataSource remote;
  late InvitationRepositoryImpl repo;

  setUp(() {
    remote = MockInvitationRemoteDataSource();
    repo = InvitationRepositoryImpl(remote);
  });

  When<Future<Map<String, dynamic>>> whenList() => when(
    () => remote.list(
      status: any(named: 'status'),
      limit: any(named: 'limit'),
      cursor: any(named: 'cursor'),
    ),
  );

  test('list parses {data, page} into Invitations (snake_case wire, incl. usage info)', () async {
    whenList().thenAnswer(
      (_) async => {
        'data': [
          {
            'id': 42,
            'visitor_name': 'Anar Əliyev',
            'purpose': 'guest',
            'access_type': 'time_limited',
            'status': 'active',
            'expires_at': '2026-08-21T15:20:00Z',
            'max_usage': null,
            'usage_count': 0,
            'first_used_at': null,
            'last_used_at': null,
            'revoked_at': null,
            'created_at': '2026-08-21T14:20:00Z',
          },
          {
            'id': 41,
            'visitor_name': 'Kuryer',
            'purpose': 'courier',
            'access_type': 'one_time',
            'status': 'used_up',
            'expires_at': '2026-08-21T15:00:00Z',
            'max_usage': 1,
            'usage_count': 1,
            'first_used_at': '2026-08-21T14:35:00Z',
            'last_used_at': '2026-08-21T14:35:00Z',
            'revoked_at': null,
            'created_at': '2026-08-21T14:20:00Z',
          },
        ],
        'page': {'next_cursor': null, 'has_more': false, 'limit': 100},
      },
    );

    final result = await repo.list();

    expect(result.isSuccess, isTrue);
    final items = (result as Success).value;
    expect(items.length, 2);

    final active = items.first;
    expect(active.id, 42);
    expect(active.visitorName, 'Anar Əliyev');
    expect(active.purpose, 'guest');
    expect(active.accessType, 'time_limited');
    expect(active.isActive, isTrue);
    expect(active.usageCount, 0);
    expect(active.hasBeenUsed, isFalse);
    expect(active.expiresAt, isNotNull);
    expect(active.firstUsedAt, isNull);

    final used = items[1];
    expect(used.isUsedUp, isTrue);
    expect(used.usageCount, 1);
    expect(used.hasBeenUsed, isTrue);
    expect(used.maxUsage, 1);
    expect(used.firstUsedAt, isNotNull); // real value from the usage log, not fabricated
    expect(used.lastUsedAt, isNotNull);
  });

  test('list returns an empty list when the resident has none', () async {
    whenList().thenAnswer((_) async => {'data': const [], 'page': const {}});

    final result = await repo.list(status: 'active');

    expect(result.isSuccess, isTrue);
    expect((result as Success).value, isEmpty);
  });

  test('list maps 401 → UnauthorizedFailure', () async {
    whenList().thenThrow(dioError(status: 401));

    final result = await repo.list();

    expect((result as Err).failure, isA<UnauthorizedFailure>());
  });

  test('list maps connectionError → NetworkFailure', () async {
    whenList().thenThrow(dioOffline());

    final result = await repo.list();

    expect((result as Err).failure, isA<NetworkFailure>());
  });

  group('InvitationFilter → server query mapping', () {
    test('all → null (no filter); the others map 1:1 (used → used)', () {
      expect(InvitationFilter.all.query, isNull);
      expect(InvitationFilter.active.query, 'active');
      expect(InvitationFilter.used.query, 'used');
      expect(InvitationFilter.expired.query, 'expired');
      expect(InvitationFilter.revoked.query, 'revoked');
    });
  });

  group('Invitation.remainingAt', () {
    test('is positive while active and null once elapsed', () {
      const base = Invitation(id: 1);
      final future = base.copyWith(
        expiresAt: DateTime.now().add(const Duration(hours: 2, minutes: 15)),
      );
      final rem = future.remainingAt(DateTime.now());
      expect(rem, isNotNull);
      expect(rem!.inMinutes, greaterThan(120));

      final past = base.copyWith(
        expiresAt: DateTime.now().subtract(const Duration(minutes: 1)),
      );
      expect(past.remainingAt(DateTime.now()), isNull);
    });
  });
}
