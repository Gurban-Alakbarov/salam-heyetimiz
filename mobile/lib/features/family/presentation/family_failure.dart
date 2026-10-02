import '../../../core/error/failure.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/presentation/failure_l10n.dart';

/// B8 family refusal codes → localized messages; everything else falls back to the shared mapper.
String familyFailureMessage(AppLocalizations l, Failure failure) {
  switch (failure.code) {
    case 'invitation_already_pending':
      return l.kmErrAlreadyPending;
    case 'invitation_already_has_access':
      return l.invErrAlreadyHasAccess;
    case 'invitation_not_resendable':
      return l.kmErrNotResendable;
    case 'invitation_not_revocable':
      return l.kmErrNotRevocable;
  }
  return switch (failure) {
    ForbiddenFailure() => l.famErrNotHead,
    RateLimitedFailure() => l.kmErrRateLimited,
    NotFoundFailure() => l.kmErrNotFound,
    // A 422 without field errors is the B8 `invitation_invalid_target` refusal (self / the head's own email /
    // someone already on the device) — the error mapper keeps no code for 422, so it is matched by shape.
    ValidationFailure(:final fields) when fields.isEmpty =>
      l.famErrInvalidTarget,
    _ => failureMessage(l, failure),
  };
}
