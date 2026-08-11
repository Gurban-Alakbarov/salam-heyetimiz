import '../../../../core/network/api_client.dart';

/// Subscription endpoints. GET /v1/subscriptions uses the direct (non-envelope)
/// list shape `{data:[...], page:{...}}` — the same shape as the devices list.
class SubscriptionRemoteDataSource {
  SubscriptionRemoteDataSource(this._api);

  final ApiClient _api;

  Future<Map<String, dynamic>> list({
    String status = 'active',
    int limit = 100,
    String? cursor,
  }) async {
    final query = <String, dynamic>{'status': status, 'limit': limit};
    if (cursor != null) query['cursor'] = cursor;
    final res = await _api.get('/v1/subscriptions', query: query);
    return Map<String, dynamic>.from(res.data as Map);
  }
}
