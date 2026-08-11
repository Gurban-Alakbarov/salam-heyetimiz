import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/auth/auth_providers.dart';
import 'package:salam_mobile/features/auth/presentation/failure_l10n.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// Splash = launch gate. Watching [bootstrapControllerProvider] runs the boot
/// sequence (bootstrap → flags → maintenance/force-update → session restore).
/// Routing to the resolved destination is handled by the go_router redirect;
/// this screen only renders the loading / retry-on-error states.
class SplashScreen extends ConsumerWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final boot = ref.watch(bootstrapControllerProvider);
    final l = AppLocalizations.of(context);

    if (boot.hasError) {
      final error = boot.error;
      final message = error is Failure
          ? failureMessage(l, error)
          : l.errUnknown;
      return Scaffold(
        body: SafeArea(
          child: ErrorStateView(
            message: message,
            onRetry: () =>
                ref.read(bootstrapControllerProvider.notifier).reload(),
          ),
        ),
      );
    }

    return const Scaffold(
      backgroundColor: AppColors.brand,
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Salam Həyətimiz',
              style: TextStyle(
                color: AppColors.onBrand,
                fontSize: 24,
                fontWeight: FontWeight.w700,
              ),
            ),
            SizedBox(height: AppSpacing.xl),
            CircularProgressIndicator(color: AppColors.onBrand),
          ],
        ),
      ),
    );
  }
}
