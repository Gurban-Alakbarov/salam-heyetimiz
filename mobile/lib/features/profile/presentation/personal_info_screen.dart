import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/auth/auth_providers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// Personal information (read-only in Phase 1 — editing lands behind
/// `FeatureFlag.profileEdit`). Every field is sourced from the existing
/// [currentUserProvider] (GET /v1/me); no new model or endpoint. The API exposes
/// a single `fullName`, so first/last name are derived from it for display.
class PersonalInfoScreen extends ConsumerWidget {
  const PersonalInfoScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final userAsync = ref.watch(currentUserProvider);

    return AppScaffold(
      title: l.profilePersonalInfo,
      body: userAsync.when(
        loading: () => const AppLoading(),
        error: (_, _) => ErrorStateView(
          message: l.errUnknown,
          onRetry: () => ref.invalidate(currentUserProvider),
        ),
        data: (me) {
          final user = me?.user;
          final full = user?.fullName?.trim() ?? '';
          final parts = full.isEmpty
              ? const <String>[]
              : full.split(RegExp(r'\s+'));
          final firstName = parts.isNotEmpty ? parts.first : '—';
          final lastName = parts.length > 1 ? parts.sublist(1).join(' ') : '—';
          final email = (user?.email?.isNotEmpty ?? false) ? user!.email! : '—';

          return ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              _InfoRow(label: l.fieldFirstName, value: firstName),
              _InfoRow(label: l.fieldLastName, value: lastName),
              _InfoRow(label: l.fieldPhone, value: user?.phone ?? '—'),
              _InfoRow(label: l.fieldEmail, value: email),
            ],
          );
        },
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.md),
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.lg,
        vertical: AppSpacing.md,
      ),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: AppRadius.brMd,
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: AppSpacing.xs),
          Text(value, style: Theme.of(context).textTheme.bodyLarge),
        ],
      ),
    );
  }
}
