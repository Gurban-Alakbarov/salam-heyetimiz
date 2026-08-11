import 'package:flutter/foundation.dart';

/// A single in-app notification (domain entity). `title`/`body`/`type`/`ids` are lifted from the
/// server-rendered `payload`; `isRead` collapses the backend status/read_at into one flag for the UI.
@immutable
class AppNotification {
  const AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    required this.ids,
    required this.isRead,
    this.createdAt,
  });

  final int id;
  final String type;
  final String title;
  final String body;
  final Map<String, dynamic> ids;
  final bool isRead;
  final DateTime? createdAt;

  AppNotification copyWith({bool? isRead}) => AppNotification(
    id: id,
    type: type,
    title: title,
    body: body,
    ids: ids,
    isRead: isRead ?? this.isRead,
    createdAt: createdAt,
  );
}

/// One page of the inbox plus the live unread count and cursor (mirrors NotificationListResponse).
@immutable
class NotificationPage {
  const NotificationPage({
    required this.items,
    required this.unreadCount,
    required this.nextCursor,
    required this.hasMore,
  });

  final List<AppNotification> items;
  final int unreadCount;
  final String? nextCursor;
  final bool hasMore;
}

/// The inbox UI state held by the notifier: the accumulated (paginated) list + unread count + paging.
@immutable
class NotificationInboxState {
  const NotificationInboxState({
    required this.items,
    required this.unreadCount,
    required this.nextCursor,
    required this.hasMore,
    this.loadingMore = false,
  });

  final List<AppNotification> items;
  final int unreadCount;
  final String? nextCursor;
  final bool hasMore;
  final bool loadingMore;

  factory NotificationInboxState.fromPage(NotificationPage page) =>
      NotificationInboxState(
        items: page.items,
        unreadCount: page.unreadCount,
        nextCursor: page.nextCursor,
        hasMore: page.hasMore,
      );

  NotificationInboxState copyWith({
    List<AppNotification>? items,
    int? unreadCount,
    bool? loadingMore,
  }) => NotificationInboxState(
    items: items ?? this.items,
    unreadCount: unreadCount ?? this.unreadCount,
    nextCursor: nextCursor,
    hasMore: hasMore,
    loadingMore: loadingMore ?? this.loadingMore,
  );
}
