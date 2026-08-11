import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/app_inputs.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/auth/presentation/failure_l10n.dart';
import 'package:salam_mobile/features/auth/presentation/providers/auth_controllers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/shared/validators/auth_validators.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _phone = TextEditingController(text: '+994');
  final _email = TextEditingController();

  String? _firstNameError;
  String? _lastNameError;
  String? _phoneError;
  String? _emailError;

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _phone.dispose();
    _email.dispose();
    super.dispose();
  }

  bool _validate(AppLocalizations l) {
    setState(() {
      _firstNameError = AuthValidators.isNotBlank(_firstName.text)
          ? null
          : l.vRequired;
      _lastNameError = AuthValidators.isNotBlank(_lastName.text)
          ? null
          : l.vRequired;
      _phoneError = AuthValidators.isAzPhone(_phone.text) ? null : l.vPhone;
      _emailError = AuthValidators.isEmail(_email.text) ? null : l.vEmail;
    });
    return _firstNameError == null &&
        _lastNameError == null &&
        _phoneError == null &&
        _emailError == null;
  }

  Future<void> _submit() async {
    final l = AppLocalizations.of(context);
    if (!_validate(l)) return;
    final email = _email.text.trim();

    final otp = await ref
        .read(registerControllerProvider.notifier)
        .submit(
          firstName: _firstName.text.trim(),
          lastName: _lastName.text.trim(),
          phone: _phone.text.trim(),
          email: email,
        );
    if (!mounted) return;

    if (otp != null) {
      context.go(
        '/auth/verify?email=${Uri.encodeComponent(email)}&flow=register',
      );
      return;
    }

    final failure = ref.read(registerControllerProvider).error;
    if (failure is ValidationFailure) {
      setState(() {
        _firstNameError = failure.firstFor('first_name') ?? _firstNameError;
        _lastNameError = failure.firstFor('last_name') ?? _lastNameError;
        _phoneError = failure.firstFor('phone') ?? _phoneError;
        _emailError = failure.firstFor('email') ?? _emailError;
      });
    } else if (failure is Failure) {
      AppSnackBar.show(context, failureMessage(l, failure), isError: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final loading = ref.watch(registerControllerProvider).isLoading;

    return AppScaffold(
      title: l.registerTitle,
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: AutofillGroup(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AppTextField(
                label: l.firstName,
                controller: _firstName,
                errorText: _firstNameError,
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.givenName],
                prefixIcon: Icons.person_outline,
              ),
              AppTextField(
                label: l.lastName,
                controller: _lastName,
                errorText: _lastNameError,
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.familyName],
                prefixIcon: Icons.person_outline,
              ),
              AppTextField(
                label: l.phoneNumber,
                controller: _phone,
                errorText: _phoneError,
                hintText: l.phoneHint,
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.next,
                autofillHints: const [AutofillHints.telephoneNumber],
                inputFormatters: [
                  FilteringTextInputFormatter.allow(RegExp(r'[0-9+]')),
                  LengthLimitingTextInputFormatter(13),
                ],
                prefixIcon: Icons.phone_outlined,
              ),
              AppTextField(
                label: l.emailAddress,
                controller: _email,
                errorText: _emailError,
                hintText: l.emailHint,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.done,
                autofillHints: const [AutofillHints.email],
                prefixIcon: Icons.email_outlined,
              ),
              const SizedBox(height: AppSpacing.lg),
              AppButton(
                label: l.registerSubmit,
                loading: loading,
                onPressed: _submit,
              ),
              const SizedBox(height: AppSpacing.sm),
              AppTextButton(
                label: l.alreadyHaveAccount,
                onPressed: () => context.go('/auth/login'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
