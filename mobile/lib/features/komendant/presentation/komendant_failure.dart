import '../../../core/error/failure.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/presentation/failure_l10n.dart';

/// B5 Komendant refusal codes → localized messages; everything else falls back to the shared mapper.
String komendantFailureMessage(AppLocalizations l, Failure failure) {
  switch (failure) {
    case ForbiddenFailure(:final code):
      return code == 'not_komendant' ? l.kmErrNotKomendant : l.kmErrForbidden;
    case ConflictFailure(:final code):
      return switch (code) {
        'already_resident' => l.kmErrAlreadyResident,
        'invitation_already_pending' => l.kmErrAlreadyPending,
        'invitation_not_resendable' => l.kmErrNotResendable,
        'invitation_not_revocable' => l.kmErrNotRevocable,
        _ => failureMessage(l, failure),
      };
    case RateLimitedFailure():
      return l.kmErrRateLimited;
    case NotFoundFailure():
      return l.kmErrNotFound;
    default:
      return failureMessage(l, failure);
  }
}
