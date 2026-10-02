import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../design_system/components/app_components.dart';
import '../../../design_system/components/data_components.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';

/// RegisterTypeScreen (IMPLEMENTATION_PLAN §10–§11 / B14, BR-5): the standard registration path starts
/// by choosing Physical or Legal person; the choice travels to the existing register form as
/// `account_type` (B9). Login and existing / legacy accounts are untouched.
///
/// Back returns to the screen that opened it (Welcome / Login); when it is the flow's entry point with
/// nothing underneath, Back lands on Welcome instead of closing the app.
class RegisterTypeScreen extends StatelessWidget {
  const RegisterTypeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final canPop = context.canPop();
    return PopScope(
      canPop: canPop,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) context.go('/welcome');
      },
      child: AppScaffold(
        title: l.regTypeTitle,
        body: ListView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          children: [
            _TypeCard(
              key: const Key('reg-type-physical'),
              icon: Icons.home_outlined,
              title: l.regTypePhysical,
              body: l.regTypePhysicalBody,
              onTap: () => context.push('/auth/register?type=physical'),
            ),
            const SizedBox(height: AppSpacing.md),
            _TypeCard(
              key: const Key('reg-type-legal'),
              icon: Icons.apartment_outlined,
              title: l.regTypeLegal,
              body: l.regTypeLegalBody,
              onTap: () => context.push('/auth/register?type=legal'),
            ),
          ],
        ),
      ),
    );
  }
}

class _TypeCard extends StatelessWidget {
  const _TypeCard({
    required this.icon,
    required this.title,
    required this.body,
    required this.onTap,
    super.key,
  });

  final IconData icon;
  final String title;
  final String body;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: AppRadius.brCard,
      child: AppCard(
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: const BoxDecoration(
                color: AppColors.brandContainer,
                borderRadius: AppRadius.brMd,
              ),
              child: Icon(icon, color: AppColors.brand),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 2),
                  Text(body, style: Theme.of(context).textTheme.bodyMedium),
                ],
              ),
            ),
            const Icon(Icons.chevron_right),
          ],
        ),
      ),
    );
  }
}
