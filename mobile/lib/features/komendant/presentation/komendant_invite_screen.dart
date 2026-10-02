import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure.dart';
import '../../../design_system/components/app_components.dart';
import '../../../design_system/components/app_inputs.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/validators/auth_validators.dart';
import '../komendant_providers.dart';
import 'komendant_failure.dart';

/// KomendantInviteScreen (BR-10 / B15): first name, last name, email → POST /v1/komendant/invitations.
/// The server emails a 7-day link; the complex is always the manager's own (never sent by the client).
class KomendantInviteScreen extends ConsumerStatefulWidget {
  const KomendantInviteScreen({super.key});

  @override
  ConsumerState<KomendantInviteScreen> createState() =>
      _KomendantInviteScreenState();
}

class _KomendantInviteScreenState extends ConsumerState<KomendantInviteScreen> {
  final _first = TextEditingController();
  final _last = TextEditingController();
  final _email = TextEditingController();
  Map<String, String?> _errors = {};
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    // An edited field drops its stale error.
    for (final (key, c) in [
      ('first_name', _first),
      ('last_name', _last),
      ('email', _email),
    ]) {
      c.addListener(() {
        if (_errors[key] != null) {
          setState(() => _errors = {..._errors, key: null});
        }
      });
    }
  }

  @override
  void dispose() {
    _first.dispose();
    _last.dispose();
    _email.dispose();
    super.dispose();
  }

  bool _validate(AppLocalizations l) {
    final e = <String, String?>{
      'first_name': AuthValidators.isNotBlank(_first.text) ? null : l.vRequired,
      'last_name': AuthValidators.isNotBlank(_last.text) ? null : l.vRequired,
      'email': AuthValidators.isEmail(_email.text) ? null : l.vEmail,
    };
    setState(() => _errors = e);
    return e.values.every((v) => v == null);
  }

  Future<void> _submit() async {
    final l = AppLocalizations.of(context);
    if (!_validate(l)) return;
    setState(() => _busy = true);
    final result = await ref
        .read(komendantRepositoryProvider)
        .invite(
          firstName: _first.text.trim(),
          lastName: _last.text.trim(),
          email: _email.text.trim(),
        );
    if (!mounted) return;
    setState(() => _busy = false);
    result.fold(
      (failure) {
        if (failure is ValidationFailure) {
          setState(
            () => _errors = {
              for (final f in const ['first_name', 'last_name', 'email'])
                f: failure.firstFor(f),
            },
          );
        } else {
          AppSnackBar.show(
            context,
            komendantFailureMessage(l, failure),
            isError: true,
          );
        }
      },
      (_) {
        ref.invalidate(komendantInvitationsProvider);
        ref.invalidate(komendantComplexProvider);
        AppSnackBar.show(context, l.kmInviteSent);
        context.pop();
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return AppScaffold(
      title: l.kmInvite,
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              l.kmInviteIntro,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: AppSpacing.lg),
            AppTextField(
              key: const Key('km-first'),
              label: l.kmFirstName,
              controller: _first,
              errorText: _errors['first_name'],
              prefixIcon: Icons.person_outline,
            ),
            const SizedBox(height: AppSpacing.md),
            AppTextField(
              key: const Key('km-last'),
              label: l.kmLastName,
              controller: _last,
              errorText: _errors['last_name'],
              prefixIcon: Icons.person_outline,
            ),
            const SizedBox(height: AppSpacing.md),
            AppTextField(
              key: const Key('km-email'),
              label: l.kmEmail,
              controller: _email,
              errorText: _errors['email'],
              prefixIcon: Icons.email_outlined,
              keyboardType: TextInputType.emailAddress,
            ),
            const SizedBox(height: AppSpacing.lg),
            AppButton(
              key: const Key('km-invite-send'),
              label: l.kmInviteSend,
              loading: _busy,
              onPressed: _busy ? null : _submit,
            ),
          ],
        ),
      ),
    );
  }
}
