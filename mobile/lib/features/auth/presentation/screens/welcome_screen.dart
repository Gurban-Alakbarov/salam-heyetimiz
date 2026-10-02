import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/app_inputs.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/auth/auth_providers.dart';
import 'package:salam_mobile/features/complex/complex_providers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

class WelcomeScreen extends ConsumerWidget {
  const WelcomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final boot = ref.watch(bootstrapControllerProvider).value;
    final registrationEnabled = boot?.registrationEnabled ?? true;
    final brand = boot?.brand ?? l.appTitle;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            children: [
              const Spacer(),
              const Icon(
                Icons.home_work_outlined,
                size: 72,
                color: AppColors.brand,
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(brand, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: AppSpacing.sm),
              Text(
                l.welcomeTagline,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const Spacer(),
              // B16: an invitation opened earlier on this device is one tap away.
              if (ref.watch(hasPendingInviteProvider).value ?? false) ...[
                AppSecondaryButton(
                  key: const Key('welcome-invite'),
                  label: l.invPendingCard,
                  icon: Icons.mark_email_unread_outlined,
                  onPressed: () => context.push('/invite'),
                ),
                const SizedBox(height: AppSpacing.sm),
              ],
              if (registrationEnabled)
                AppButton(
                  label: l.registerSubmit,
                  onPressed: () => context.push('/auth/register/type'),
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
