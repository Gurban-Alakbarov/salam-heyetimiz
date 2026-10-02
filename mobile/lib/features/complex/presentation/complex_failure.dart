import '../../../core/error/failure.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/presentation/failure_l10n.dart';

/// B6 invitation / B7 subscribe / B1 order refusal codes → localized messages; everything else falls back
/// to the shared mapper.
String complexFailureMessage(AppLocalizations l, Failure failure) {
  final code = failure.code;
  switch (code) {
    case 'invitation_invalid':
      return l.invGoneBody;
    case 'invitation_email_mismatch':
      return l.invErrEmailMismatch;
    case 'invitation_already_has_access':
      return l.invErrAlreadyHasAccess;
    case 'invitation_invalid_target':
      return l.invErrInvalidTarget;
    case 'invitation_kind_unsupported':
      return l.invErrUnsupported;
    case 'subscription_already_active':
      return l.cxErrAlreadyActive;
  }
  if (failure is ForbiddenFailure) return l.payErrForbidden;
  return failureMessage(l, failure);
}

/// Codes after which the stored invitation can never succeed → drop the local token. An email mismatch is
/// NOT terminal: the invitee may sign out and continue with the invited email.
bool isTerminalInviteFailure(Failure failure) => const {
  'invitation_invalid',
  'invitation_already_has_access',
  'invitation_invalid_target',
  'invitation_kind_unsupported',
}.contains(failure.code);
