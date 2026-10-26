import '../../core/network/api_client.dart';
import '../../core/storage/app_storage.dart';
import '../../core/storage/storage_keys.dart';

/// The home-screen widget's own credential (backend `POST /v1/me/widget-token`): open-only, bound to this
/// install, long-lived. The widget's background isolate uses it instead of the app's 15-minute access token,
/// so a driver tapping "Aç" at the gate is not told "session expired" just because the app was not opened
/// recently — and the widget still never refreshes the app session (no refresh-token race, W3 Q2).
///
/// The app (re)asks for it at start-up and when a widget is set up; it is re-issued when older than
/// [reissueAfter], so an install that keeps using the app keeps a valid widget token. Logout clears it
/// (SessionManager.clear) and the server revokes it.
class WidgetTokenSync {
  WidgetTokenSync(this._api, this._store, {DateTime Function()? now}) : _now = now ?? DateTime.now;

  static const Duration reissueAfter = Duration(days: 7);

  final ApiClient _api;
  final SecureStore _store;
  final DateTime Function() _now;

  /// Best-effort: never throws (a failure just leaves the previous token, if any, in place).
  Future<void> ensure({bool force = false}) async {
    try {
      final issuedAtMs = int.tryParse(await _store.read(StorageKeys.widgetTokenIssuedAt) ?? '');
      final token = await _store.read(StorageKeys.widgetToken);
      final fresh = token != null &&
          issuedAtMs != null &&
          _now().difference(DateTime.fromMillisecondsSinceEpoch(issuedAtMs)) < reissueAfter;
      if (fresh && !force) return;

      final res = await _api.post('/v1/me/widget-token');
      final body = res.data;
      final data = body is Map ? body['data'] : null;
      final newToken = data is Map ? data['token'] : null;
      if (newToken is! String || newToken.isEmpty) return;

      await _store.write(StorageKeys.widgetToken, newToken);
      await _store.write(StorageKeys.widgetTokenIssuedAt, _now().millisecondsSinceEpoch.toString());
    } catch (_) {
      // offline / not signed in — the widget keeps the token it has
    }
  }
}
