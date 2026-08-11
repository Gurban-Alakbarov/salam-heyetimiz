/// Typed failures (Constitution §7). Repositories return [Result]; the UI never
/// sees a raw exception. Mapping from transport/HTTP lives in
/// `core/network/error_mapper.dart`.
sealed class Failure {
  const Failure(this.message, {this.code});

  /// Human-facing fallback message (often the backend's localized text). The UI
  /// prefers an l10n string keyed by [code] where one exists, else shows this.
  final String message;

  /// Stable machine code where the backend supplies one (e.g. `wrong_code`,
  /// `email_already_registered`). Null for generic transport failures.
  final String? code;
}

class NetworkFailure extends Failure {
  const NetworkFailure([super.message = 'Şəbəkə xətası']);
}

class TimeoutFailure extends Failure {
  const TimeoutFailure([super.message = 'Bağlantı vaxtı bitdi']);
}

/// 401 on an authenticated call (token invalid/expired and refresh failed).
class UnauthorizedFailure extends Failure {
  const UnauthorizedFailure([super.message = 'Sessiya bitdi']);
}

/// 401 with an OTP-specific code: `wrong_code` | `otp_expired` | `otp_max_attempts`.
class OtpFailure extends Failure {
  const OtpFailure(String code, [super.message = 'Təsdiq kodu xətası'])
    : super(code: code);
}

/// 403 — optionally carries the backend code (`subscription_required`,
/// `device_disabled`, `forbidden`) so the UI can show the right message.
class ForbiddenFailure extends Failure {
  const ForbiddenFailure([super.message = 'İcazə yoxdur', String? code])
    : super(code: code);
}

/// 502 / code `device_offline` — the driver could not reach the device.
class DeviceOfflineFailure extends Failure {
  const DeviceOfflineFailure([super.message = 'Cihaz offline'])
    : super(code: 'device_offline');
}

/// 404.
class NotFoundFailure extends Failure {
  const NotFoundFailure([super.message = 'Tapılmadı']);
}

/// 409 (e.g. `email_already_registered`, `registration_conflict`).
class ConflictFailure extends Failure {
  const ConflictFailure(String code, [super.message = 'Konflikt'])
    : super(code: code);
}

/// 422 — field-level validation errors.
class ValidationFailure extends Failure {
  const ValidationFailure(this.fields, [super.message = 'Doğrulama xətası']);
  final Map<String, List<String>> fields;

  /// First error for [field], if any — convenient for inline form errors.
  String? firstFor(String field) {
    final list = fields[field];
    return (list != null && list.isNotEmpty) ? list.first : null;
  }
}

/// 429 — includes the server's Retry-After (seconds).
class RateLimitedFailure extends Failure {
  const RateLimitedFailure(
    this.retryAfterSeconds, [
    super.message = 'Çox sayda sorğu',
  ]);
  final int retryAfterSeconds;
}

/// 5xx.
class ServerFailure extends Failure {
  const ServerFailure([super.message = 'Server xətası']);
}

class UnknownFailure extends Failure {
  const UnknownFailure([super.message = 'Naməlum xəta']);
}

/// Minimal Result type (Success | Err) used across the data/domain boundary.
sealed class Result<T> {
  const Result();

  /// Pattern-collapse helper. `onErr` for [Err], `onOk` for [Success].
  R fold<R>(R Function(Failure failure) onErr, R Function(T value) onOk) {
    final self = this;
    return switch (self) {
      Err<T>() => onErr(self.failure),
      Success<T>() => onOk(self.value),
    };
  }

  bool get isSuccess => this is Success<T>;
}

class Success<T> extends Result<T> {
  const Success(this.value);
  final T value;
}

class Err<T> extends Result<T> {
  const Err(this.failure);
  final Failure failure;
}
