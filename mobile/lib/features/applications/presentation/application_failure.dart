import '../../../core/error/failure.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/presentation/failure_l10n.dart';

/// B9 refusal codes → localized messages; everything else falls back to the shared mapper.
String applicationFailureMessage(AppLocalizations l, Failure failure) {
  if (failure is ConflictFailure) {
    switch (failure.code) {
      case 'account_type_mismatch':
        return l.appErrTypeMismatch;
      case 'application_already_open':
        return l.appErrAlreadyOpen;
      case 'application_duplicate_voen':
        return l.appErrDuplicateVoen;
    }
  }
  return failureMessage(l, failure);
}
