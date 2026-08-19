import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../logger/app_logger.dart';
import '../session/session_manager.dart';

/// The single HTTP seam (Constitution §6). Direct Dio use outside this layer is
/// forbidden. Interceptor chain: Auth(+Accept-Language) → Refresh(single-flight
/// 401) → Logging(dev). Error→Failure mapping (`mapDioError`) is applied by
/// repositories. Repositories depend on [ApiClient], never on Dio.
class ApiClient {
  ApiClient(this._dio);

  final Dio _dio;

  Dio get raw => _dio;

  Future<Response<dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _dio.get<dynamic>(path, queryParameters: query);

  Future<Response<dynamic>> post(
    String path, {
    Object? data,
    Map<String, dynamic>? headers,
  }) => _dio.post<dynamic>(
    path,
    data: data,
    options: headers == null ? null : Options(headers: headers),
  );

  Future<Response<dynamic>> put(String path, {Object? data}) =>
      _dio.put<dynamic>(path, data: data);

  Future<Response<dynamic>> delete(String path, {Object? data}) =>
      _dio.delete<dynamic>(path, data: data);

  static ApiClient create({
    required AppConfig config,
    required SessionManager sessionManager,
    required AppLogger logger,
    required String Function() localeCode,
  }) {
    final dio = Dio(
      BaseOptions(
        baseUrl: config.apiBaseUrl,
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 20),
        sendTimeout: const Duration(seconds: 20),
        headers: const {'Accept': 'application/json'},
      ),
    );

    dio.interceptors.add(AuthInterceptor(sessionManager, localeCode));
    dio.interceptors.add(RefreshInterceptor(sessionManager));
    if (config.loggingEnabled) {
      dio.interceptors.add(LoggingInterceptor(logger));
    }
    return ApiClient(dio);
  }

  /// A client for the home-screen widget's BACKGROUND isolate (W3), which has no
  /// [SessionManager]. It carries a fixed bearer [accessToken] read once from
  /// secure storage and — deliberately — NO [RefreshInterceptor]: the background
  /// isolate must never refresh or rotate the session (W3 Q2). A 401 therefore
  /// surfaces as an [UnauthorizedFailure] → "session expired → open the app".
  static ApiClient background({
    required String baseUrl,
    required String accessToken,
    required String localeCode,
  }) {
    final dio = Dio(
      BaseOptions(
        baseUrl: baseUrl,
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 20),
        sendTimeout: const Duration(seconds: 20),
        headers: const {'Accept': 'application/json'},
      ),
    );
    dio.interceptors.add(_StaticAuthInterceptor(accessToken, localeCode));
    return ApiClient(dio);
  }
}

/// Paths that never carry a bearer token and must not trigger a 401 refresh
/// (e.g. verify-email 401 is an OTP error, not an expired session).
const publicPaths = <String>[
  '/v1/bootstrap',
  '/v1/auth/register',
  '/v1/auth/verify-email',
  '/v1/auth/resend-otp',
  '/v1/auth/login',
  '/v1/auth/refresh',
  '/v1/health',
];

bool _isPublic(String path) => publicPaths.any(path.startsWith);

class AuthInterceptor extends Interceptor {
  AuthInterceptor(this._session, this._localeCode);

  final SessionManager _session;
  final String Function() _localeCode;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    options.headers['Accept-Language'] = _localeCode();
    if (!_isPublic(options.path)) {
      final token = _session.accessToken;
      if (token != null) {
        options.headers['Authorization'] = 'Bearer $token';
      }
    }
    handler.next(options);
  }
}

/// Adds a fixed bearer token + Accept-Language for the background isolate (which
/// has no [SessionManager]). Deliberately paired with NO refresh interceptor
/// ([ApiClient.background], W3 Q2) — a 401 propagates untouched.
class _StaticAuthInterceptor extends Interceptor {
  _StaticAuthInterceptor(this._accessToken, this._localeCode);

  final String _accessToken;
  final String _localeCode;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    options.headers['Accept-Language'] = _localeCode;
    if (!_isPublic(options.path)) {
      options.headers['Authorization'] = 'Bearer $_accessToken';
    }
    handler.next(options);
  }
}

/// Single-flight refresh on 401 for authenticated requests. Excludes public
/// paths (OTP/login errors must surface untouched). Retries the original request
/// once via a bare Dio to avoid interceptor recursion.
class RefreshInterceptor extends Interceptor {
  RefreshInterceptor(this._session);

  final SessionManager _session;
  final Dio _retryDio = Dio();

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final options = err.requestOptions;
    final status = err.response?.statusCode;
    final alreadyRetried = options.extra['__retried'] == true;

    final eligible =
        status == 401 &&
        !_isPublic(options.path) &&
        _session.isAuthenticated &&
        !alreadyRetried;

    if (!eligible) {
      handler.next(err);
      return;
    }

    final refreshed = await _session.refresh();
    if (!refreshed) {
      // Session cleared inside refresh(); listeners bounce the app to guest.
      handler.next(err);
      return;
    }

    options
      ..extra['__retried'] = true
      ..headers['Authorization'] = 'Bearer ${_session.accessToken}';
    try {
      final response = await _retryDio.fetch<dynamic>(options);
      handler.resolve(response);
    } on DioException catch (retryErr) {
      handler.next(retryErr);
    }
  }
}

class LoggingInterceptor extends Interceptor {
  LoggingInterceptor(this._logger);

  final AppLogger _logger;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    // Redacted: method + path only — never headers/body (tokens, OTP, PII).
    _logger.d('→ ${options.method} ${options.path}');
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    _logger.w('✗ ${err.requestOptions.path} [${err.response?.statusCode}]');
    handler.next(err);
  }
}
