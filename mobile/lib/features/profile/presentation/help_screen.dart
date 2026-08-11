import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/auth/auth_providers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// Help & support. Contact details come from the existing bootstrap payload
/// ([BootstrapEntity.supportEmail] / `supportPhone`); nothing is fabricated. When
/// the backend supplies no contacts the screen degrades to the description alone.
class HelpScreen extends ConsumerWidget {
  const HelpScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final boot = ref.watch(bootstrapControllerProvider).value;
    final email = boot?.supportEmail;
    final phone = boot?.supportPhone;

    return AppScaffold(
      title: l.profileHelp,
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          Text(l.helpDescription, style: Theme.of(context).textTheme.bodyLarge),
          const SizedBox(height: AppSpacing.lg),
          if (email != null && email.isNotEmpty)
            _ContactTile(
              icon: Icons.email_outlined,
              label: l.fieldEmail,
              value: email,
            ),
          if (phone != null && phone.isNotEmpty)
            _ContactTile(
              icon: Icons.phone_outlined,
              label: l.fieldPhone,
              value: phone,
            ),
        ],
      ),
    );
  }
}

class _ContactTile extends StatelessWidget {
  const _ContactTile({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.md),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: AppRadius.brMd,
        border: Border.all(color: AppColors.border),
      ),
      child: ListTile(
        leading: Icon(icon, color: AppColors.brand),
        title: Text(label, style: Theme.of(context).textTheme.bodyMedium),
        subtitle: Text(value, style: Theme.of(context).textTheme.bodyLarge),
        trailing: const Icon(
          Icons.copy_rounded,
          size: 18,
          color: AppColors.n400,
        ),
        onTap: () {
          Clipboard.setData(ClipboardData(text: value));
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(l.visitorCopied)),
          );
        },
      ),
    );
  }
}
