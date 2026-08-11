import '../../../core/network/api_client.dart';

/// Thin remote layer for the canonical push-token endpoints (openapi upsertPushToken /
/// deletePushToken). The install is resolved server-side from the JWT `fp` claim, so the body carries
/// only the token; de-registration needs no body. This is the only place these paths are spoken to.
class PushTokenDataSource {
  PushTokenDataSource(this._api);

  final ApiClient _api;

  Future<void> upsertPushToken(String token) =>
      _api.put('/v1/notifications/push-token', data: {'push_token': token});

  Future<void> deletePushToken() => _api.delete('/v1/notifications/push-token');
}
