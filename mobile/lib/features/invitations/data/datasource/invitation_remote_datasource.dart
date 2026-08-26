import '../../../../core/network/api_client.dart';

/// Visitor-link ("invitations") endpoints — the caller's OWN links across every device.
/// GET /v1/visitor-links uses the direct list shape `{data:[...], page:{...}}` (the same
/// shape as the devices/subscriptions lists). Ownership + status filtering are server-side.
class InvitationRemoteDataSource {
  InvitationRemoteDataSource(this._api);

  final ApiClient _api;

  Future<Map<String, dynamic>> list({
    String? status,
    int limit = 50,
    String? cursor,
  }) async {
    final query = <String, dynamic>{'limit': limit};
    if (status != null) query['status'] = status;
    if (cursor != null) query['cursor'] = cursor;
    final res = await _api.get('/v1/visitor-links', query: query);
    return Map<String, dynamic>.from(res.data as Map);
  }
}
