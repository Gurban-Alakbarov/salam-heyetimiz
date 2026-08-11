import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/notifications/data/repository_impl.dart';

import '../../helpers/mocks.dart';

void main() {
  late MockNotificationRemoteDataSource remote;
  late NotificationRepositoryImpl repo;

  setUp(() {
    remote = MockNotificationRemoteDataSource();
    repo = NotificationRepositoryImpl(remote);
  });

  Map<String, dynamic> item(
    int id, {
    String status = 'sent',
    String? readAt,
    Map<String, dynamic>? payload,
  }) => {
    'id': id,
    'template_key': 'device.opened',
    'channel': 'inapp',
    'payload':
        payload ??
        {
          'title': 'Qonaq daxil oldu',
          'body': 'Kuryer açdı',
          'type': 'visitor_link_used',
          'ids': {'visitor_link_id': 5, 'device_id': 3},
        },
    'status': status,
    'sent_at': '2026-08-11T10:00:00Z',
    'read_at': readAt,
    'created_at': '2026-08-11T10:00:00Z',
  };

  test('load parses {data, page, unread_count} and lifts payload into the entity', () async {
    when(
      () => remote.list(limit: any(named: 'limit'), cursor: any(named: 'cursor')),
    ).thenAnswer(
      (_) async => {
        'data': [
          item(20),
          item(19, status: 'read', readAt: '2026-08-11T11:00:00Z'),
        ],
        'page': {'next_cursor': 'abc', 'has_more': true, 'limit': 25},
        'unread_count': 1,
      },
    );

    final res = await repo.load();
    expect(res.isSuccess, isTrue);
    final page = (res as Success).value;

    expect(page.items.length, 2);
    expect(page.unreadCount, 1);
    expect(page.nextCursor, 'abc');
    expect(page.hasMore, isTrue);

    final first = page.items.first;
    expect(first.id, 20);
    expect(first.type, 'visitor_link_used');
    expect(first.title, 'Qonaq daxil oldu');
    expect(first.body, 'Kuryer açdı');
    expect(first.ids['device_id'], 3);
    expect(first.isRead, isFalse);
    expect(page.items[1].isRead, isTrue);
  });

  test('load degrades safely on a malformed/missing payload', () async {
    when(
      () => remote.list(limit: any(named: 'limit'), cursor: any(named: 'cursor')),
    ).thenAnswer(
      (_) async => {
        'data': [
          {'id': 7, 'channel': 'inapp', 'status': 'sent', 'created_at': '2026-08-11T10:00:00Z'},
        ],
        'page': const <String, dynamic>{},
        'unread_count': 1,
      },
    );

    final page = ((await repo.load()) as Success).value;
    expect(page.items.first.type, '');
    expect(page.items.first.title, '');
    expect(page.items.first.ids, isEmpty);
    expect(page.hasMore, isFalse);
    expect(page.nextCursor, isNull);
  });

  test('load returns an empty page for an empty inbox', () async {
    when(
      () => remote.list(limit: any(named: 'limit'), cursor: any(named: 'cursor')),
    ).thenAnswer((_) async => {'data': const [], 'page': const {}, 'unread_count': 0});

    final page = ((await repo.load()) as Success).value;
    expect(page.items, isEmpty);
    expect(page.unreadCount, 0);
  });

  test('load passes the cursor to the datasource', () async {
    when(
      () => remote.list(limit: any(named: 'limit'), cursor: any(named: 'cursor')),
    ).thenAnswer((_) async => {'data': const [], 'page': const {}, 'unread_count': 0});

    await repo.load(cursor: 'CUR');
    verify(() => remote.list(cursor: 'CUR')).called(1);
  });

  test('load maps 401 → UnauthorizedFailure', () async {
    when(
      () => remote.list(limit: any(named: 'limit'), cursor: any(named: 'cursor')),
    ).thenThrow(dioError(status: 401));

    expect(((await repo.load()) as Err).failure, isA<UnauthorizedFailure>());
  });

  test('markRead / markAllRead call the datasource', () async {
    when(() => remote.markRead(any())).thenAnswer((_) async {});
    when(() => remote.markAllRead()).thenAnswer((_) async {});

    expect((await repo.markRead(5)).isSuccess, isTrue);
    expect((await repo.markAllRead()).isSuccess, isTrue);
    verify(() => remote.markRead(5)).called(1);
    verify(() => remote.markAllRead()).called(1);
  });
}
