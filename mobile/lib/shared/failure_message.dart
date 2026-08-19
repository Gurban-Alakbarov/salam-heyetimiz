import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// Maps a [Failure] to a localized message for the Devices/Barrier/Home surfaces
/// (Constitution §7 — no raw exceptions/codes shown). Reuses the shared Failure
/// taxonomy; covers the device/barrier-specific codes.
String deviceFailureMessage(AppLocalizations l, Failure failure) {
  switch (failure) {
    case DeviceOfflineFailure():
      return l.errDeviceOffline;
    case ForbiddenFailure(:final code):
      return switch (code) {
        'subscription_required' => l.errSubscriptionRequired,
        'device_disabled' => l.errDeviceDisabled,
        // GEOFENCE-3 — distance-gate codes (403) must not fall to the generic message.
        'location_required' => l.errLocationRequired,
        'outside_geofence' => l.errOutsideGeofence,
        'location_imprecise' => l.errLocationImprecise,
        _ => l.errAccessDenied,
      };
    case LocationFailure(:final code):
      return switch (code) {
        'permission_denied' => l.errLocationPermissionDenied,
        'permanently_denied' => l.errLocationPermissionPermanent,
        'service_disabled' => l.errLocationServiceDisabled,
        'timeout' => l.errLocationTimeout,
        _ => l.errLocationRequired,
      };
    case RateLimitedFailure(:final retryAfterSeconds):
      return l.errRateLimited(retryAfterSeconds);
    case NetworkFailure():
      return l.errNetwork;
    case TimeoutFailure():
      return l.errTimeout;
    case UnauthorizedFailure():
      return l.sessionExpired;
    case NotFoundFailure():
      return l.errNotFound;
    case ServerFailure():
      return l.errServer;
    case ValidationFailure():
      return l.errValidation;
    case ConflictFailure():
      return failure.message;
    case OtpFailure():
      return l.errUnknown;
    case UnknownFailure():
      return l.errUnknown;
  }
}
