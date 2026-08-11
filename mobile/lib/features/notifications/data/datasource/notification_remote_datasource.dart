import '../../../../core/network/api_client.dart';

/// In-app inbox endpoints (openapi listNotifications / markNotificationRead / markAllNotificationsRead).
/// GET returns the direct `{data:[...], page:{...}, unread_count}` shape (same as devices/subscriptions).
class NotificationRemoteDataSource {
  NotificationRemoteDataSource(this._api);

  final ApiClient _api;

  Future<Map<String, dynamic>> list({int limit = 25, String? cursor}) async {
    final query = <String, dynamic>{'limit': limit};
    if (cursor != null) query['cursor'] = cursor;
    final res = await _api.get('/v1/notifications', query: query);
    return Map<String, dynamic>.from(res.data as Map);
  }

  Future<void> markRead(int id) => _api.post('/v1/notifications/$id/read');

  Future<void> markAllRead() => _api.post('/v1/notifications/read-all');
}
