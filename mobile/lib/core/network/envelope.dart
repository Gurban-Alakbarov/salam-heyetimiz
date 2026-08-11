import 'package:dio/dio.dart';

import '../error/failure.dart';

/// Helpers for the backend's unified response envelope
/// `{success, message, data, meta, errors}` and the few legacy-shaped endpoints
/// (refresh returns the token object directly; logout returns 204).
/// See docs/flutter/API_INTEGRATION.md.
class Envelope {
  Envelope._();

  /// Extracts the `data` object from a unified-envelope response body.
  /// For legacy endpoints (no envelope) the whole body IS the data — pass
  /// [unified] = false.
  static Map<String, dynamic> data(Object? body, {bool unified = true}) {
    if (body is! Map) return <String, dynamic>{};
    final map = Map<String, dynamic>.from(body);
    if (!unified) return map;
    final d = map['data'];
    return d is Map ? Map<String, dynamic>.from(d) : <String, dynamic>{};
  }

  /// Extracts the `meta` object (timing info for OTP dispatch endpoints).
  static Map<String, dynamic> meta(Object? body) {
    if (body is! Map) return <String, dynamic>{};
    final m = body['meta'];
    return m is Map ? Map<String, dynamic>.from(m) : <String, dynamic>{};
  }
}

/// Maps any Dio transport/HTTP error to a typed [Failure]. This is the single
/// translation point (Constitution §7); features only ever see [Failure].
Failure mapDioError(DioException e) {
  switch (e.type) {
    case DioExceptionType.connectionTimeout:
    case DioExceptionType.receiveTimeout:
    case DioExceptionType.sendTimeout:
      return const TimeoutFailure();
    case DioExceptionType.connectionError:
      return const NetworkFailure();
    case DioExceptionType.badCertificate:
      return const NetworkFailure();
    case DioExceptionType.cancel:
      return const UnknownFailure();
    case DioExceptionType.badResponse:
    case DioExceptionType.unknown:
      break;
  }

  final response = e.response;
  final status = response?.statusCode ?? 0;
  if (status == 0) {
    // No response and not a typed timeout/connection error → treat as network.
    return const NetworkFailure();
  }

  final body = response?.data;
  String? code;
  String? message;
  final fields = <String, List<String>>{};

  if (body is Map) {
    if (body['message'] is String) message = body['message'] as String;

    // Unified envelope: errors == {code: "..."} OR a field→[messages] map.
    final errors = body['errors'];
    if (errors is Map) {
      final c = errors['code'];
      if (c is String) {
        code = c;
      } else {
        errors.forEach((k, v) {
          if (v is List) {
            fields[k.toString()] = v.map((x) => x.toString()).toList();
          }
        });
      }
    }

    // Legacy envelope: error == {code, message, fields}.
    final legacy = body['error'];
    if (legacy is Map) {
      code ??= legacy['code']?.toString();
      message ??= legacy['message']?.toString();
      final f = legacy['fields'];
      if (f is Map) {
        f.forEach((k, v) {
          if (v is List) {
            fields[k.toString()] = v.map((x) => x.toString()).toList();
          }
        });
      }
    }
  }

  final retryAfter =
      int.tryParse(response?.headers.value('retry-after') ?? '') ?? 60;

  // Device/barrier driver could not reach the device (any HTTP status).
  if (code == 'device_offline') {
    return DeviceOfflineFailure(message ?? 'Cihaz offline');
  }

  switch (status) {
    case 401:
      if (code == 'wrong_code' ||
          code == 'otp_expired' ||
          code == 'otp_max_attempts') {
        return OtpFailure(code!, message ?? 'Təsdiq kodu xətası');
      }
      return UnauthorizedFailure(message ?? 'Sessiya bitdi');
    case 403:
      return ForbiddenFailure(message ?? 'İcazə yoxdur', code);
    case 404:
      return NotFoundFailure(message ?? 'Tapılmadı');
    case 409:
      return ConflictFailure(code ?? 'conflict', message ?? 'Konflikt');
    case 422:
      return ValidationFailure(fields, message ?? 'Doğrulama xətası');
    case 429:
      return RateLimitedFailure(retryAfter, message ?? 'Çox sayda sorğu');
    default:
      if (status >= 500) return ServerFailure(message ?? 'Server xətası');
      return UnknownFailure(message ?? 'Naməlum xəta');
  }
}
