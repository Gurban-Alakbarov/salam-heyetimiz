import '../../../../core/network/api_client.dart';

/// Device endpoints. These use the direct (non-envelope) shapes:
/// list = `{data:[...], page:{...}}`, detail = a bare device object.
class DeviceRemoteDataSource {
  DeviceRemoteDataSource(this._api);

  final ApiClient _api;

  Future<Map<String, dynamic>> list({
    String? filter,
    String? cursor,
    int limit = 25,
  }) async {
    final query = <String, dynamic>{'limit': limit};
    if (filter != null) query['filter'] = filter;
    if (cursor != null) query['cursor'] = cursor;
    final res = await _api.get('/v1/devices', query: query);
    return Map<String, dynamic>.from(res.data as Map);
  }

  Future<Map<String, dynamic>> detail(int id) async {
    final res = await _api.get('/v1/devices/$id');
    return Map<String, dynamic>.from(res.data as Map);
  }
}
