import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/data_components.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/home/domain/home_data.dart';
import 'package:salam_mobile/features/home/home_providers.dart';
import 'package:salam_mobile/features/invitations/presentation/screens/invitations_screen.dart';
import 'package:salam_mobile/features/subscriptions/presentation/screens/active_subscriptions_screen.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/shared/failure_message.dart';

/// Home tab body — real /v1/me-backed dashboard with skeleton loading.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final async = ref.watch(homeProvider);

    return RefreshIndicator(
      onRefresh: () => ref.refresh(homeProvider.future),
      child: async.when(
        loading: () => ListView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          children: const [
            AppSkeleton(width: 220, height: 28),
            SizedBox(height: AppSpacing.lg),
            AppSkeletonCard(),
            SizedBox(height: AppSpacing.md),
            Row(
              children: [
                Expanded(child: AppSkeletonCard()),
                SizedBox(width: AppSpacing.md),
                Expanded(child: AppSkeletonCard()),
              ],
            ),
          ],
        ),
        error: (error, _) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            const SizedBox(height: 160),
            ErrorStateView(
              message: error is Failure
                  ? deviceFailureMessage(l, error)
                  : l.errUnknown,
              onRetry: () => ref.refresh(homeProvider),
            ),
          ],
        ),
        data: (home) => _HomeView(
          home: home,
          onDevicesTap: () => ref.read(homeTabProvider.notifier).select(1),
          onSubscriptionsTap: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => const ActiveSubscriptionsScreen(),
            ),
          ),
          onInvitationsTap: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => const InvitationsScreen(),
            ),
          ),
        ),
      ),
    );
  }
}

class _HomeView extends StatelessWidget {
  const _HomeView({
    required this.home,
    required this.onDevicesTap,
    required this.onSubscriptionsTap,
    required this.onInvitationsTap,
  });

  final HomeData home;
  final VoidCallback onDevicesTap;
  final VoidCallback onSubscriptionsTap;
  final VoidCallback onInvitationsTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        Text(
          l.homeGreeting(home.fullName ?? l.homeUser),
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: AppSpacing.lg),
        AppCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (home.email != null)
                _ContactRow(icon: Icons.email_outlined, value: home.email!),
              if (home.phone != null)
                _ContactRow(icon: Icons.phone_outlined, value: home.phone!),
              if (home.lastActivityAt != null)
                _ContactRow(
                  icon: Icons.access_time,
                  value:
                      '${l.homeLastActivity}: ${DateFormat('dd.MM.yyyy HH:mm').format(home.lastActivityAt!.toLocal())}',
                ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        Row(
          children: [
            Expanded(
              child: _StatCard(
                count: home.deviceCount,
                label: l.homeActiveDevices,
                icon: Icons.meeting_room_outlined,
                onTap: onDevicesTap,
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: _StatCard(
                count: home.subscriptionCount,
                label: l.homeActiveSubscriptions,
                icon: Icons.card_membership_outlined,
                onTap: onSubscriptionsTap,
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: _StatCard(
                count: home.invitationCount,
                label: l.homeInvitations,
                icon: Icons.mail_outline,
                onTap: onInvitationsTap,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({
    required this.count,
    required this.label,
    required this.icon,
    this.onTap,
  });

  final int count;
  final String label;
  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return AppCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(AppSpacing.sm),
            decoration: BoxDecoration(
              color: AppColors.brandContainer,
              borderRadius: AppRadius.brMd,
            ),
            child: Icon(icon, color: AppColors.brand),
          ),
          const SizedBox(height: AppSpacing.md),
          Text('$count', style: Theme.of(context).textTheme.titleLarge),
          Text(label, style: Theme.of(context).textTheme.bodyMedium),
        ],
      ),
    );
  }
}

class _ContactRow extends StatelessWidget {
  const _ContactRow({required this.icon, required this.value});

  final IconData icon;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Row(
        children: [
          Icon(icon, size: 18, color: AppColors.brand),
          const SizedBox(width: AppSpacing.sm),
          Flexible(
            child: Text(value, style: Theme.of(context).textTheme.bodyLarge),
          ),
        ],
      ),
    );
  }
}
