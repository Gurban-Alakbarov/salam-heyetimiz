import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/auth/auth_providers.dart';
import 'package:salam_mobile/features/auth/presentation/failure_l10n.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// Brand-asset green (#00B800) — the loading background's colour, painted behind the
/// image so there is never a white flash before it decodes (matches the native launch).
const Color _kLoadingGreen = Color(0xFF00B800);

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

    // Loading: full-bleed brand background (loading_bg) with the centred white-bubble
    // logo (loading_logo) + a spinner. Both images keep their aspect ratio — the
    // background covers (cropping, never stretching), the logo scales by width only.
    final logoWidth = (MediaQuery.sizeOf(context).width * 0.62)
        .clamp(180.0, 360.0)
        .toDouble();

    return Scaffold(
      backgroundColor: _kLoadingGreen,
      body: Stack(
        fit: StackFit.expand,
        children: [
          const ColoredBox(color: _kLoadingGreen),
          Image.asset('assets/images/loading_bg.png', fit: BoxFit.cover),
          SafeArea(
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Image.asset(
                    'assets/images/loading_logo.png',
                    width: logoWidth,
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  const CircularProgressIndicator(color: AppColors.onBrand),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
