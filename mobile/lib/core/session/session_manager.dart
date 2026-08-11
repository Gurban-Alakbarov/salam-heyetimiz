import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../logger/app_logger.dart';
import '../storage/app_storage.dart';
import '../storage/storage_keys.dart';

/// An access + refresh token pair. Plain infrastructure type (lives in core, not
/// the auth feature) since the network layer depends on it.
@immutable
class Session {
  const Session({required this.accessToken, required this.refreshToken});

  final String accessToken;
  final String refreshToken;
}

/// The single token authority (Constitution §6/§16, SECURITY_PLAN.md §1).
/// Holds tokens in memory + [SecureStore], performs the single-flight refresh,
/// and notifies listeners (go_router `refreshListenable`, auth state) on change.
class SessionManager extends ChangeNotifier {
  SessionManager._(this._store, this._baseUrl, this._logger);

  factory SessionManager({
    required SecureStore store,
    required String baseUrl,
    required AppLogger logger,
  }) => SessionManager._(store, baseUrl, logger);

  final SecureStore _store;
  final String _baseUrl;
  final AppLogger _logger;

  Session? _current;
  Session? get current => _current;
  bool get isAuthenticated => _current != null;
  String? get accessToken => _current?.accessToken;

  /// Hydrate from secure storage at boot. Does NOT notify (initial load).
  Future<void> load() async {
    final access = await _store.read(StorageKeys.accessToken);
    final refresh = await _store.read(StorageKeys.refreshToken);
    if (access != null && refresh != null) {
      _current = Session(accessToken: access, refreshToken: refresh);
    }
  }

  Future<void> save(Session session) async {
    _current = session;
    await _store.write(StorageKeys.accessToken, session.accessToken);
    await _store.write(StorageKeys.refreshToken, session.refreshToken);
    notifyListeners();
  }

  Future<void> clear() async {
    _current = null;
    await _store.delete(StorageKeys.accessToken);
    await _store.delete(StorageKeys.refreshToken);
    notifyListeners();
  }

  Future<bool>? _inFlight;

  /// Single-flight refresh: concurrent 401s share one `/v1/auth/refresh` call.
  /// Returns true if a fresh session was stored; on failure the session is
  /// cleared (→ listeners drive the app back to guest).
  Future<bool> refresh() {
    final existing = _inFlight;
    if (existing != null) return existing;
    final future = _doRefresh();
    _inFlight = future;
    future.whenComplete(() => _inFlight = null);
    return future;
  }

  Future<bool> _doRefresh() async {
    final token = _current?.refreshToken;
    if (token == null) return false;
    try {
      // Bare Dio — no interceptors — to avoid recursion. /v1/auth/refresh
      // returns the legacy token object directly (no unified envelope).
      final dio = Dio(
        BaseOptions(
          baseUrl: _baseUrl,
          connectTimeout: const Duration(seconds: 10),
          receiveTimeout: const Duration(seconds: 15),
          headers: const {'Accept': 'application/json'},
        ),
      );
      final res = await dio.post<dynamic>(
        '/v1/auth/refresh',
        data: {'refresh_token': token},
      );
      final body = res.data;
      if (body is! Map) {
        await clear();
        return false;
      }
      final access = body['access_token'];
      final refreshTok = body['refresh_token'];
      if (access is! String || refreshTok is! String) {
        await clear();
        return false;
      }
      await save(Session(accessToken: access, refreshToken: refreshTok));
      _logger.d('session refreshed');
      return true;
    } catch (_) {
      _logger.w('session refresh failed; clearing');
      await clear();
      return false;
    }
  }
}
