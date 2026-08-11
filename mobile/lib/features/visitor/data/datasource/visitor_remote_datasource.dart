import '../../../../core/network/api_client.dart';

/// Visitor-link endpoints (resident side). Creation returns the shareable
/// `{ link, token, url }` exactly once — the plaintext token is never retrievable
/// again (the backend stores only its hash).
class VisitorRemoteDataSource {
  VisitorRemoteDataSource(this._api);

  final ApiClient _api;

  Future<Map<String, dynamic>> create(
    int deviceId, {
    required String accessType,
    int? durationMinutes,
    String? visitorName,
    String? purpose,
  }) async {
    final body = <String, dynamic>{'access_type': accessType};
    if (durationMinutes != null) body['duration_minutes'] = durationMinutes;
    if (visitorName != null && visitorName.trim().isNotEmpty) {
      body['visitor_name'] = visitorName.trim();
    }
    if (purpose != null) body['purpose'] = purpose;

    final res = await _api.post(
      '/v1/devices/$deviceId/visitor-links',
      data: body,
    );
    return Map<String, dynamic>.from(res.data as Map);
  }
}
