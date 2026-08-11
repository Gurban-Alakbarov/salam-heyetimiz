import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/app_inputs.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// Terminal gate shown when the installed version is below `min_version` or the
/// server sets `force_update`. The store deep-link is wired at release time
/// (the app is not yet published); for now this surfaces the requirement.
class ForceUpdateScreen extends ConsumerWidget {
  const ForceUpdateScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.system_update, size: 72, color: AppColors.brand),
              const SizedBox(height: AppSpacing.lg),
              Text(
                l.forceUpdateTitle,
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                l.forceUpdateMessage,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: AppSpacing.xl),
              AppButton(
                label: l.updateNow,
                // Store links land at publish time (RELEASE_PLAN.md). Until then
                // the action just acknowledges the requirement.
                onPressed: () =>
                    AppSnackBar.show(context, l.forceUpdateMessage),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
