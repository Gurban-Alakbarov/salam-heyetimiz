import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/notifications/domain/entity/notification_entities.dart';
import 'package:salam_mobile/features/notifications/notifications_providers.dart';

import '../../helpers/mocks.dart';

AppNotification _n(int id, {bool read = false}) => AppNotification(
  id: id,
  type: 'visitor_link_used',
  title: 'T$id',
  body: 'B$id',
  ids: const {},
  isRead: read,
  createdAt: DateTime.utc(2026, 8, 11),
);

void main() {
  late MockNotificationRepository repo;

  setUp(() => repo = MockNotificationRepository());

  ProviderContainer container() {
    final c = ProviderContainer(
      overrides: [notificationRepositoryProvider.overrideWithValue(repo)],
    );
    addTearDown(c.dispose);
    return c;
  }

  test('build loads the first page into state', () async {
    when(() => repo.load(cursor: any(named: 'cursor'))).thenAnswer(
      (_) async => Success(
        NotificationPage(items: [_n(2), _n(1)], unreadCount: 2, nextCursor: 'c', hasMore: true),
      ),
    );

    final c = container();
    await c.read(notificationInboxProvider.future);
    final s = c.read(notificationInboxProvider).value!;

    expect(s.items.map((e) => e.id), [2, 1]);
    expect(s.unreadCount, 2);
    expect(s.hasMore, isTrue);
  });

  test('loadMore appends the next page and clears the cursor at the end', () async {
    when(() => repo.load(cursor: null)).thenAnswer(
      (_) async => Success(
        NotificationPage(items: [_n(3)], unreadCount: 1, nextCursor: 'c1', hasMore: true),
      ),
    );
    when(() => repo.load(cursor: 'c1')).thenAnswer(
      (_) async => Success(
        NotificationPage(items: [_n(2)], unreadCount: 1, nextCursor: null, hasMore: false),
      ),
    );

    final c = container();
    await c.read(notificationInboxProvider.future);
    await c.read(notificationInboxProvider.notifier).loadMore();
    final s = c.read(notificationInboxProvider).value!;

    expect(s.items.map((e) => e.id), [3, 2]);
    expect(s.hasMore, isFalse);
    expect(s.nextCursor, isNull);
  });

  test('markRead flips the item and drops unread_count optimistically', () async {
    when(() => repo.load(cursor: any(named: 'cursor'))).thenAnswer(
      (_) async => Success(
        NotificationPage(items: [_n(2), _n(1)], unreadCount: 2, nextCursor: null, hasMore: false),
      ),
    );
    when(() => repo.markRead(any())).thenAnswer((_) async => const Success<void>(null));

    final c = container();
    await c.read(notificationInboxProvider.future);
    await c.read(notificationInboxProvider.notifier).markRead(2);
    final s = c.read(notificationInboxProvider).value!;

    expect(s.items.firstWhere((e) => e.id == 2).isRead, isTrue);
    expect(s.items.firstWhere((e) => e.id == 1).isRead, isFalse);
    expect(s.unreadCount, 1);
    verify(() => repo.markRead(2)).called(1);
  });

  test('markAllRead reads every item and zeroes unread_count', () async {
    when(() => repo.load(cursor: any(named: 'cursor'))).thenAnswer(
      (_) async => Success(
        NotificationPage(items: [_n(2), _n(1)], unreadCount: 2, nextCursor: null, hasMore: false),
      ),
    );
    when(() => repo.markAllRead()).thenAnswer((_) async => const Success<void>(null));

    final c = container();
    await c.read(notificationInboxProvider.future);
    await c.read(notificationInboxProvider.notifier).markAllRead();
    final s = c.read(notificationInboxProvider).value!;

    expect(s.items.every((e) => e.isRead), isTrue);
    expect(s.unreadCount, 0);
    verify(() => repo.markAllRead()).called(1);
  });

  test('empty inbox yields no items and zero unread', () async {
    when(() => repo.load(cursor: any(named: 'cursor'))).thenAnswer(
      (_) async => const Success(
        NotificationPage(items: [], unreadCount: 0, nextCursor: null, hasMore: false),
      ),
    );

    final c = container();
    await c.read(notificationInboxProvider.future);
    final s = c.read(notificationInboxProvider).value!;

    expect(s.items, isEmpty);
    expect(s.unreadCount, 0);
    expect(s.hasMore, isFalse);
  });
}
