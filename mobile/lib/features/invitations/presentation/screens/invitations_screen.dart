import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/data_components.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/invitations/domain/entity/invitation_entities.dart';
import 'package:salam_mobile/features/invitations/invitations_providers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/shared/failure_message.dart';

/// "Dəvətlərim" — pushed from the Home "Dəvətlərim" card. Lists the visitor links the resident
/// created (GET /v1/visitor-links), filtered server-side by the selected status tab. Ownership is
/// enforced by the backend (created_by_user_id); the client only chooses the status. Back → Home.
class InvitationsScreen extends ConsumerWidget {
  const InvitationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final filter = ref.watch(invitationFilterProvider);
    final async = ref.watch(invitationsProvider(filter));

    return AppScaffold(
      title: l.invitationsTitle,
      body: Column(
        children: [
          _FilterBar(selected: filter),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => ref.refresh(invitationsProvider(filter).future),
              child: async.when(
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
                      onRetry: () => ref.refresh(invitationsProvider(filter)),
                    ),
                  ],
                ),
                data: (items) {
                  if (items.isEmpty) {
                    return ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: [
                        const SizedBox(height: 160),
                        EmptyState(
                          message: l.invitationsEmpty,
                          icon: Icons.mail_outline,
                        ),
                      ],
                    );
                  }
                  return ListView.builder(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    itemCount: items.length,
                    itemBuilder: (context, index) => AppAppear(
                      child: _InvitationCard(invitation: items[index]),
                    ),
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterBar extends ConsumerWidget {
  const _FilterBar({required this.selected});

  final InvitationFilter selected;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final items = <(InvitationFilter, String)>[
      (InvitationFilter.all, l.invitationFilterAll),
      (InvitationFilter.active, l.invitationFilterActive),
      (InvitationFilter.used, l.invitationFilterUsed),
      (InvitationFilter.expired, l.invitationFilterExpired),
      (InvitationFilter.revoked, l.invitationFilterRevoked),
    ];
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      child: Row(
        children: [
          for (final (f, label) in items)
            Padding(
              padding: const EdgeInsets.only(right: AppSpacing.sm),
              child: ChoiceChip(
                label: Text(label),
                selected: selected == f,
                onSelected: (_) =>
                    ref.read(invitationFilterProvider.notifier).select(f),
              ),
            ),
        ],
      ),
    );
  }
}

class _InvitationCard extends StatelessWidget {
  const _InvitationCard({required this.invitation});

  final Invitation invitation;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final inv = invitation;
    final df = DateFormat('dd.MM.yyyy HH:mm');
    final (statusLabel, tone) = _status(l, inv.status);
    final purpose = _purposeLabel(l, inv.purpose);
    final remaining = _remainingLabel(l, inv);
    final name = (inv.visitorName != null && inv.visitorName!.trim().isNotEmpty)
        ? inv.visitorName!.trim()
        : l.invitationDefaultTitle;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
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
                  Icons.mail_outline,
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
                    if (purpose != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        purpose,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              StatusBadge(label: statusLabel, tone: tone),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          if (inv.createdAt != null)
            _InfoRow(
              label: l.invitationSentAt,
              value: df.format(inv.createdAt!.toLocal()),
            ),
          if (inv.isActive && inv.expiresAt != null)
            _InfoRow(
              label: l.invitationExpiresAt,
              value: df.format(inv.expiresAt!.toLocal()),
            ),
          if (remaining != null)
            Padding(
              padding: const EdgeInsets.only(top: AppSpacing.xs),
              child: Row(
                children: [
                  const Icon(Icons.schedule, size: 16, color: AppColors.brand),
                  const SizedBox(width: AppSpacing.xs),
                  Text(
                    remaining,
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                ],
              ),
            ),
          if (inv.hasBeenUsed) ...[
            _InfoRow(
              label: l.invitationUsageCount,
              value: _usageValue(l, inv),
            ),
            if (inv.firstUsedAt != null)
              _InfoRow(
                label: l.invitationFirstUsedAt,
                value: df.format(inv.firstUsedAt!.toLocal()),
              ),
            if (inv.lastUsedAt != null)
              _InfoRow(
                label: l.invitationLastUsedAt,
                value: df.format(inv.lastUsedAt!.toLocal()),
              ),
          ],
          if (inv.isRevoked && inv.revokedAt != null)
            _InfoRow(
              label: l.invitationStatusRevoked,
              value: df.format(inv.revokedAt!.toLocal()),
            ),
        ],
      ),
    );
  }

  (String, BadgeTone) _status(AppLocalizations l, String s) => switch (s) {
    'active' => (l.invitationStatusActive, BadgeTone.success),
    'used_up' => (l.invitationStatusUsed, BadgeTone.neutral),
    'expired' => (l.invitationStatusExpired, BadgeTone.warning),
    'revoked' => (l.invitationStatusRevoked, BadgeTone.danger),
    _ => (s, BadgeTone.neutral),
  };

  String? _purposeLabel(AppLocalizations l, String? p) => switch (p) {
    'guest' => l.visitorPurposeGuest,
    'delivery' => l.visitorPurposeDelivery,
    'courier' => l.visitorPurposeCourier,
    'service' => l.visitorPurposeService,
    'cleaning' => l.visitorPurposeCleaning,
    'taxi' => l.visitorPurposeTaxi,
    'other' => l.visitorPurposeOther,
    _ => null,
  };

  String? _remainingLabel(AppLocalizations l, Invitation inv) {
    if (!inv.isActive) return null;
    final d = inv.remainingAt(DateTime.now());
    if (d == null) return null;
    final h = d.inHours;
    final m = d.inMinutes.remainder(60);
    final parts = <String>[];
    if (h > 0) parts.add(l.visitorHoursShort(h));
    if (m > 0 || h == 0) parts.add(l.visitorMinutesShort(m));
    return l.invitationRemaining(parts.join(' '));
  }

  String _usageValue(AppLocalizations l, Invitation inv) {
    final count = l.invitationUsageValue(inv.usageCount);
    if (inv.maxUsage == null) return '$count · ${l.invitationUnlimited}';
    return '$count / ${inv.maxUsage}';
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('$label: ', style: Theme.of(context).textTheme.bodyMedium),
          Expanded(
            child: Text(
              value,
              style: Theme.of(context).textTheme.bodyLarge,
              textAlign: TextAlign.right,
            ),
          ),
        ],
      ),
    );
  }
}
