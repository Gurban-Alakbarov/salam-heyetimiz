import 'package:flutter/material.dart';
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

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _email = TextEditingController();
  String? _emailError;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l = AppLocalizations.of(context);
    final email = _email.text.trim();
    setState(
      () => _emailError = AuthValidators.isEmail(email) ? null : l.vEmail,
    );
    if (_emailError != null) return;

    final otp = await ref
        .read(loginControllerProvider.notifier)
        .submit(email: email);
    if (!mounted) return;

    if (otp != null) {
      context.go('/auth/verify?email=${Uri.encodeComponent(email)}&flow=login');
      return;
    }
    final failure = ref.read(loginControllerProvider).error;
    if (failure is ValidationFailure) {
      setState(() => _emailError = failure.firstFor('email') ?? _emailError);
    } else if (failure is Failure) {
      AppSnackBar.show(context, failureMessage(l, failure), isError: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final loading = ref.watch(loginControllerProvider).isLoading;

    return AppScaffold(
      title: l.loginTitle,
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: AutofillGroup(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: AppSpacing.xl),
              Center(
                child: Container(
                  width: 76,
                  height: 76,
                  decoration: BoxDecoration(
                    color: AppColors.brandContainer,
                    borderRadius: AppRadius.brCard,
                  ),
                  child: const Icon(
                    Icons.sensor_door_rounded,
                    size: 40,
                    color: AppColors.brand,
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.xl),
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
                label: l.loginSubmit,
                loading: loading,
                onPressed: _submit,
              ),
              const SizedBox(height: AppSpacing.sm),
              AppTextButton(
                label: l.dontHaveAccount,
                onPressed: () => context.go('/auth/register'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
