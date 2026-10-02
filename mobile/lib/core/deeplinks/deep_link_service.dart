import 'package:app_links/app_links.dart';

import '../storage/app_storage.dart';
import 'deep_link.dart';

/// Single entry point for OS-delivered links (B13): Android App Links / intent-filters and the iOS custom
/// scheme, via `app_links` (Flutter's built-in deep linking is disabled in the platform configs so links
/// are handled exactly once, here). Parsing is delegated to [DeepLinkParser]; nothing here logs a link.
class DeepLinkService {
  DeepLinkService([AppLinks? appLinks]) : _appLinks = appLinks ?? AppLinks();

  final AppLinks _appLinks;

  /// The link that cold-started the app, if any.
  Future<DeepLink?> initial() async {
    final uri = await _appLinks.getInitialLink();
    return uri == null ? null : DeepLinkParser.parse(uri);
  }

  /// Links delivered while the app is running (warm start / foreground).
  Stream<DeepLink> get links => _appLinks.uriLinkStream.map(DeepLinkParser.parse);
}

/// Holds an invitation token opened via link until the user is ready to claim it (register → verify-email
/// with `invitation_token`, or accept — consumed by the invite flow, B16). The token is a credential: it
/// lives in [SecureStore] only, expires client-side after the server's 7-day window, and is never logged.
class PendingInviteStore {
  PendingInviteStore(this._store, {DateTime Function()? now}) : _now = now ?? DateTime.now;

  static const String _tokenKey = 'pending_invite_token';
  static const String _savedAtKey = 'pending_invite_saved_at';
  static const Duration ttl = Duration(days: 7);

  final SecureStore _store;
  final DateTime Function() _now;

  Future<void> save(String token) async {
    await _store.write(_tokenKey, token);
    await _store.write(_savedAtKey, _now().toUtc().toIso8601String());
  }

  Future<String?> read() async {
    final token = await _store.read(_tokenKey);
    final savedAt = DateTime.tryParse(await _store.read(_savedAtKey) ?? '');
    if (token == null || savedAt == null) return null;
    if (_now().toUtc().difference(savedAt) > ttl) {
      await clear();
      return null;
    }
    return token;
  }

  Future<void> clear() async {
    await _store.delete(_tokenKey);
    await _store.delete(_savedAtKey);
  }
}
