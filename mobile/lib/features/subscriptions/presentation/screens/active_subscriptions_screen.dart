import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/data_components.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/devices/devices_providers.dart';
import 'package:salam_mobile/features/devices/domain/entity/device_entities.dart';
import 'package:salam_mobile/features/subscriptions/domain/entity/subscription_entities.dart';
import 'package:salam_mobile/features/subscriptions/subscriptions_providers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/shared/failure_message.dart';

/// "Aktiv abunəliklər" — pushed from the Home "Active subscriptions" card.
///
/// Lists the resident's active subscriptions (GET /v1/subscriptions?status=active)
/// and resolves each `device_id` against the already-cached device list for the
/// barrier name/address (there is no residential-complex entity in the backend, so
/// none is invented). Back returns to Home.
class ActiveSubscriptionsScreen extends ConsumerWidget {
  const ActiveSubscriptionsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final subsAsync = ref.watch(activeSubscriptionsProvider);
    // The device list is already cached by the (always-mounted) Devices tab; we
    // reuse it to resolve device_id → label/address. `.value` is null only while
    // that list is still loading, in which case the name falls back gracefully.
    final devicesById = <int, Device>{
      for (final d in ref.watch(deviceListProvider).value?.devices ?? const [])
        d.id: d,
    };

    return AppScaffold(
      title: l.activeSubscriptionsTitle,
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(activeSubscriptionsProvider.future),
        child: subsAsync.when(
          loading: () => ListView(
            padding: const EdgeInsets.all(AppSpacing.md),
            children: List.generate(
              3,
              (_) => const Padding(
                padding: EdgeInsets.symmetric(vertical: AppSpacing.xs),
                child: AppSkeletonCard(),
              ),
            ),
          ),
          error: (error, _) => ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              const SizedBox(height: 160),
              ErrorStateView(
                message: error is Failure
                    ? deviceFailureMessage(l, error)
                    : l.errUnknown,
                onRetry: () => ref.refresh(activeSubscriptionsProvider),
              ),
            ],
          ),
          data: (subs) {
            if (subs.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  const SizedBox(height: 160),
                  EmptyState(
                    message: l.subscriptionsEmpty,
                    icon: Icons.card_membership_outlined,
                  ),
                ],
              );
            }
            return ListView.builder(
              padding: const EdgeInsets.all(AppSpacing.md),
              itemCount: subs.length,
              itemBuilder: (context, index) {
                final sub = subs[index];
                return AppAppear(
                  child: _SubscriptionCard(
                    subscription: sub,
                    device: sub.deviceId == null
                        ? null
                        : devicesById[sub.deviceId],
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}

class _SubscriptionCard extends StatelessWidget {
  const _SubscriptionCard({required this.subscription, this.device});

  final Subscription subscription;
  final Device? device;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final s = subscription;
    final tierLabel = _tierLabel(l, s.tier);
    final name = device?.label ?? tierLabel;
    final address = device?.address;
    final df = DateFormat('dd.MM.yyyy');
    final progress = s.progressAt(DateTime.now());
    final days = s.daysRemaining;
    final soon = days != null && days <= 15;
    final accent = soon ? AppColors.warning : AppColors.brand;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header — barrier name + address + active status pill.
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: const BoxDecoration(
                  color: AppColors.brandContainer,
                  borderRadius: AppRadius.brMd,
                ),
                child: const Icon(
                  Icons.card_membership_outlined,
                  color: AppColors.brand,
                  size: 22,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: Theme.of(context).textTheme.titleMedium,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (address != null && address.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        address,
                        style: Theme.of(context).textTheme.bodyMedium,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              StatusBadge(
                label: l.deviceSubscriptionActive,
                tone: BadgeTone.success,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Text(tierLabel, style: Theme.of(context).textTheme.labelLarge),
          const SizedBox(height: AppSpacing.sm),
          // Client-side progress (starts→ends). Does NOT override days_remaining.
          if (progress != null)
            ClipRRect(
              borderRadius: BorderRadius.circular(AppRadius.pill),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 8,
                backgroundColor: AppColors.n200,
                color: accent,
              ),
            ),
          const SizedBox(height: AppSpacing.md),
          Row(
            children: [
              if (s.startsAt != null)
                Expanded(
                  child: _DateBlock(
                    label: l.subscriptionStart,
                    value: df.format(s.startsAt!.toLocal()),
                  ),
                ),
              if (s.endsAt != null)
                Expanded(
                  child: _DateBlock(
                    label: l.subscriptionEnd,
                    value: df.format(s.endsAt!.toLocal()),
                  ),
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          // Remaining days — the authoritative server value.
          Row(
            children: [
              Icon(Icons.schedule, size: 16, color: accent),
              const SizedBox(width: AppSpacing.xs),
              Text(
                _remainingLabel(l, days),
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  color: soon ? AppColors.warning : null,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _tierLabel(AppLocalizations l, String tier) => switch (tier) {
    'main' => l.subscriptionTierMain,
    'additional' => l.subscriptionTierAdditional,
    _ => tier,
  };

  String _remainingLabel(AppLocalizations l, int? days) {
    if (days == null) return '—';
    if (days <= 0) return l.subscriptionExpiresToday;
    return l.subscriptionDaysLeft(days);
  }
}

class _DateBlock extends StatelessWidget {
  const _DateBlock({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: Theme.of(context).textTheme.bodyMedium),
        const SizedBox(height: 2),
        Text(value, style: Theme.of(context).textTheme.bodyLarge),
      ],
    );
  }
}
