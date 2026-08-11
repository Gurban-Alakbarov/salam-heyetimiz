import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// Maps a typed [Failure] to a localized, user-facing message (Constitution §7 —
/// no raw exceptions/codes shown). Prefers an l10n string by type/code; falls
/// back to the backend message for cases without a dedicated key.
String failureMessage(AppLocalizations l, Failure failure) {
  switch (failure) {
    case OtpFailure(:final code):
      return switch (code) {
        'wrong_code' => l.errWrongCode,
        'otp_expired' => l.errOtpExpired,
        'otp_max_attempts' => l.errOtpMaxAttempts,
        _ => failure.message,
      };
    case ConflictFailure(:final code):
      return code == 'email_already_registered'
          ? l.errEmailAlreadyRegistered
          : failure.message;
    case RateLimitedFailure(:final retryAfterSeconds):
      return l.errRateLimited(retryAfterSeconds);
    case NetworkFailure():
      return l.errNetwork;
    case TimeoutFailure():
      return l.errTimeout;
    case UnauthorizedFailure():
      return l.sessionExpired;
    case ValidationFailure():
      return l.errValidation;
    case ServerFailure():
      return l.errServer;
    case ForbiddenFailure():
      return failure.message;
    case DeviceOfflineFailure():
      return failure.message;
    case NotFoundFailure():
      return failure.message;
    case UnknownFailure():
      return l.errUnknown;
  }
}
