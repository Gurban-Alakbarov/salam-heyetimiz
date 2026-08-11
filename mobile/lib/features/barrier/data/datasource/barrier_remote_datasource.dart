import 'package:uuid/uuid.dart';

import '../../../../core/network/api_client.dart';

/// Barrier/command endpoints (direct shapes). The open command REQUIRES a unique
/// `Idempotency-Key` header (one per logical open) so retries can't double-fire.
class BarrierRemoteDataSource {
  BarrierRemoteDataSource(this._api, {this.appVersion});

  final ApiClient _api;
  final String? appVersion;

  Future<Map<String, dynamic>> open(int deviceId) => _command(deviceId, null);

  /// Close command through the SAME endpoint (Phase 5). `direction=close` makes the backend read the
  /// device model's close_command (VL110C RELAY,0#) — no new endpoint, same command lifecycle.
  Future<Map<String, dynamic>> close(int deviceId) => _command(deviceId, 'close');

  Future<Map<String, dynamic>> _command(int deviceId, String? direction) async {
    final body = <String, dynamic>{};
    if (appVersion != null) body['client_app_version'] = appVersion;
    if (direction != null) body['direction'] = direction;
    final res = await _api.post(
      '/v1/devices/$deviceId/open',
      data: body,
      headers: {'Idempotency-Key': const Uuid().v4()},
    );
    return Map<String, dynamic>.from(res.data as Map);
  }

  Future<Map<String, dynamic>> status(int commandId) async {
    final res = await _api.get('/v1/commands/$commandId');
    return Map<String, dynamic>.from(res.data as Map);
  }

  Future<void> feedback(
    int commandId, {
    required bool gateMoved,
    String? comment,
  }) async {
    final body = <String, dynamic>{'gate_moved': gateMoved};
    if (comment != null) body['comment'] = comment;
    await _api.post('/v1/commands/$commandId/feedback', data: body);
  }
}
