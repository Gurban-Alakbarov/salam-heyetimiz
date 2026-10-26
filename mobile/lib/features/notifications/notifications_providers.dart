import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import '../../core/error/retry_policy.dart';
import 'data/datasource/notification_remote_datasource.dart';
import 'data/notification_settings_datasource.dart';
import 'data/push_messaging_service.dart';
import 'data/push_token_datasource.dart';
import 'data/repository_impl.dart';
import 'domain/entity/notification_entities.dart';
import 'domain/repository.dart';

/// Notifications feature DI (Constitution §5). Push transport (registration/messaging) + the in-app
/// inbox (list/read/unread). Push providers are inert off Android; the inbox is backend-driven.

final pushTokenDataSourceProvider = Provider<PushTokenDataSource>(
  (ref) => PushTokenDataSource(ref.watch(apiClientProvider)),
);

final pushMessagingServiceProvider = Provider<PushMessagingService>(
  (ref) => PushMessagingService(
    ref,
    ref.watch(pushTokenDataSourceProvider),
    ref.watch(loggerProvider),
    ref.watch(featureFlagServiceProvider),
  ),
);

// ---- In-app inbox ---------------------------------------------------------

final notificationRepositoryProvider = Provider<NotificationRepository>(
  (ref) =>
      NotificationRepositoryImpl(NotificationRemoteDataSource(ref.watch(apiClientProvider))),
);

/// The inbox state (paginated list + live unread count). Kept alive so the app-bar bell badge and the
/// inbox screen share one source; the badge triggers the first load.
final notificationInboxProvider =
    AsyncNotifierProvider<NotificationInboxNotifier, NotificationInboxState>(
      NotificationInboxNotifier.new,
      retry: retryTransientFailures,
    );

class NotificationInboxNotifier extends AsyncNotifier<NotificationInboxState> {
  NotificationRepository get _repo => ref.read(notificationRepositoryProvider);

  @override
  Future<NotificationInboxState> build() async {
    final res = await _repo.load();
    return res.fold(
      (failure) => throw failure,
      (page) => NotificationInboxState.fromPage(page),
    );
  }

  /// Pull-to-refresh: reload the first page from scratch.
  Future<void> refresh() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() async {
      final res = await _repo.load();
      return res.fold(
        (failure) => throw failure,
        (page) => NotificationInboxState.fromPage(page),
      );
    });
  }

  /// Append the next page (no-op when there is nothing more or a load is already in flight).
  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore || current.loadingMore) return;

    state = AsyncValue.data(current.copyWith(loadingMore: true));
    final res = await _repo.load(cursor: current.nextCursor);
    res.fold(
      (_) => state = AsyncValue.data(current.copyWith(loadingMore: false)),
      (page) => state = AsyncValue.data(
        NotificationInboxState(
          items: [...current.items, ...page.items],
          unreadCount: page.unreadCount,
          nextCursor: page.nextCursor,
          hasMore: page.hasMore,
        ),
      ),
    );
  }

  /// Optimistically mark one item read (the server call is best-effort; a refresh reconciles on error).
  Future<void> markRead(int id) async {
    final current = state.value;
    if (current == null) return;
    final wasUnread = current.items.any((n) => n.id == id && !n.isRead);
    if (!wasUnread) return;

    state = AsyncValue.data(
      current.copyWith(
        items: [
          for (final n in current.items) n.id == id ? n.copyWith(isRead: true) : n,
        ],
        unreadCount: current.unreadCount > 0 ? current.unreadCount - 1 : 0,
      ),
    );
    await _repo.markRead(id);
  }

  /// Optimistically mark every item read.
  Future<void> markAllRead() async {
    final current = state.value;
    if (current == null || current.unreadCount == 0) return;

    state = AsyncValue.data(
      current.copyWith(
        items: [for (final n in current.items) n.copyWith(isRead: true)],
        unreadCount: 0,
      ),
    );
    await _repo.markAllRead();
  }
}

// ---- O13: notification switches ---------------------------------------------

final notificationSettingsDataSourceProvider = Provider<NotificationSettingsDataSource>(
  (ref) => NotificationSettingsDataSource(ref.watch(apiClientProvider)),
);

/// The user's switchable notifications (Settings › Notifications). A toggle is applied optimistically and
/// rolled back if the server refuses; the caller shows the error.
final notificationSettingsProvider =
    AsyncNotifierProvider.autoDispose<NotificationSettingsNotifier, List<NotificationSetting>>(
      NotificationSettingsNotifier.new,
    );

class NotificationSettingsNotifier extends AsyncNotifier<List<NotificationSetting>> {
  NotificationSettingsDataSource get _source => ref.read(notificationSettingsDataSourceProvider);

  @override
  Future<List<NotificationSetting>> build() => _source.load();

  /// Returns false when the server refused (the switch is put back).
  Future<bool> setPush(String templateKey, bool enabled) async {
    final previous = state.value;
    if (previous == null) return false;
    state = AsyncValue.data([
      for (final s in previous) s.templateKey == templateKey ? s.copyWith(pushEnabled: enabled) : s,
    ]);
    try {
      state = AsyncValue.data(await _source.setPush(templateKey, enabled));
      return true;
    } catch (_) {
      state = AsyncValue.data(previous);
      return false;
    }
  }
}
