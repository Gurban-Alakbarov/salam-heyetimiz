import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/error/failure.dart';
import '../../../design_system/components/app_components.dart';
import '../../../design_system/components/app_inputs.dart';
import '../../../design_system/components/data_components.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../complex/complex_providers.dart';
import '../../complex/domain/complex_entities.dart';
import '../../devices/devices_providers.dart';
import '../../payments/payments_providers.dart';
import '../domain/family_entities.dart';
import '../family_providers.dart';
import 'family_failure.dart';

/// FamilyMembersScreen (IMPLEMENTATION_PLAN §14 / §21 / B17) — the family head's surface:
/// * Üzvlər: active family links, the devices granted through each, and the member's OWN `additional`
///   subscription per device ("Üzv üçün ödə" when pending — the head pays via the B13 checkout), "Çıxar".
/// * Dəvətlər: family invitations of every device the caller heads (pending / accepted / expired /
///   cancelled-declined) with resend / revoke.
/// [focusDeviceId] comes from a device card's menu: the invite form preselects it, and a device the caller
/// does not head is explained instead of offered.
class FamilyScreen extends ConsumerWidget {
  const FamilyScreen({this.focusDeviceId, super.key});

  final int? focusDeviceId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final managed = ref.watch(managedDevicesProvider);

    return DefaultTabController(
      length: 2,
      child: AppScaffold(
        title: l.famTitle,
        body: managed.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(
            child: ErrorStateView(
              message: e is Failure ? familyFailureMessage(l, e) : l.errUnknown,
              onRetry: () => ref.invalidate(managedDevicesProvider),
            ),
          ),
          data: (devices) {
            final focusIsManaged =
                focusDeviceId == null ||
                devices.any((d) => d.deviceId == focusDeviceId);
            return Column(
              children: [
                if (!focusIsManaged)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(
                      AppSpacing.lg,
                      AppSpacing.md,
                      AppSpacing.lg,
                      0,
                    ),
                    child: AppCard(
                      child: Row(
                        children: [
                          const Icon(
                            Icons.info_outline,
                            color: AppColors.warning,
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: Text(
                              l.famErrNotHead,
                              key: const Key('fam-not-head-device'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                if (devices.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(
                      AppSpacing.lg,
                      AppSpacing.md,
                      AppSpacing.lg,
                      0,
                    ),
                    child: AppButton(
                      key: const Key('fam-invite'),
                      label: l.famInvite,
                      icon: Icons.person_add_alt_1_outlined,
                      onPressed: () {
                        final pre = focusIsManaged && focusDeviceId != null
                            ? '?device=$focusDeviceId'
                            : '';
                        context.push('/family/invite$pre');
                      },
                    ),
                  ),
                if (devices.isEmpty)
                  Expanded(
                    child: ListView(
                      children: [
                        const SizedBox(height: 120),
                        EmptyState(
                          message: l.famNotHead,
                          icon: Icons.family_restroom,
                        ),
                      ],
                    ),
                  )
                else ...[
                  TabBar(
                    tabs: [
                      Tab(text: l.famTabMembers),
                      Tab(text: l.famTabInvitations),
                    ],
                  ),
                  Expanded(
                    child: TabBarView(
                      children: [
                        const _MembersTab(),
                        _InvitationsTab(devices: devices),
                      ],
                    ),
                  ),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}

String _fmt(DateTime d) => DateFormat('dd.MM.yyyy').format(d.toLocal());

class _MembersTab extends ConsumerWidget {
  const _MembersTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final async = ref.watch(familyMembersProvider);
    return RefreshIndicator(
      onRefresh: () => ref.refresh(familyMembersProvider.future),
      child: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ListView(
          children: [
            const SizedBox(height: 120),
            ErrorStateView(
              message: e is Failure ? familyFailureMessage(l, e) : l.errUnknown,
              onRetry: () => ref.invalidate(familyMembersProvider),
            ),
          ],
        ),
        data: (members) => members.isEmpty
            ? ListView(
                children: [
                  const SizedBox(height: 120),
                  EmptyState(
                    message: l.famNoMembers,
                    icon: Icons.people_outline,
                  ),
                ],
              )
            : ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [for (final m in members) _MemberCard(member: m)],
              ),
      ),
    );
  }
}

class _MemberCard extends ConsumerStatefulWidget {
  const _MemberCard({required this.member});

  final FamilyMember member;

  @override
  ConsumerState<_MemberCard> createState() => _MemberCardState();
}

class _MemberCardState extends ConsumerState<_MemberCard> {
  int? _payingSubscriptionId;
  bool _removing = false;

  void _refreshAll() {
    ref
      ..invalidate(familyMembersProvider)
      ..invalidate(managedDevicesProvider)
      ..invalidate(pendingSubscriptionsProvider)
      ..invalidate(deviceListProvider);
  }

  Future<void> _pay(FamilySubscription sub) async {
    final l = AppLocalizations.of(context);
    setState(() => _payingSubscriptionId = sub.id);
    final result = await ref
        .read(paymentsRepositoryProvider)
        .payPendingSubscription(sub.id, sub.tier);
    if (!mounted) return;
    setState(() => _payingSubscriptionId = null);
    await result.fold(
      (f) async {
        AppSnackBar.show(
          context,
          f is ForbiddenFailure
              ? l.payErrForbidden
              : familyFailureMessage(l, f),
          isError: true,
        );
      },
      (order) async {
        await context.push('/checkout/${order.id}');
        if (mounted) _refreshAll();
      },
    );
  }

  Future<void> _remove() async {
    final l = AppLocalizations.of(context);
    final m = widget.member;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l.famRemoveTitle),
        content: Text(l.famRemoveBody(m.displayName)),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(l.cancel),
          ),
          TextButton(
            key: const Key('fam-remove-confirm'),
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(
              l.famRemove,
              style: const TextStyle(color: AppColors.danger),
            ),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _removing = true);
    final result = await ref
        .read(familyRepositoryProvider)
        .removeMember(m.userId);
    if (!mounted) return;
    setState(() => _removing = false);
    result.fold(
      (f) =>
          AppSnackBar.show(context, familyFailureMessage(l, f), isError: true),
      (_) {
        AppSnackBar.show(context, l.famRemoved);
        _refreshAll();
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final m = widget.member;
    final text = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(m.displayName, style: text.titleSmall),
                      if (m.email != null && m.email != m.displayName)
                        Text(m.email!, style: text.bodySmall),
                      if (m.phoneMasked != null)
                        Text(m.phoneMasked!, style: text.bodySmall),
                    ],
                  ),
                ),
                if (_removing)
                  const SizedBox(
                    width: 24,
                    height: 24,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                else
                  TextButton(
                    key: Key('fam-remove-${m.userId}'),
                    onPressed: _remove,
                    child: Text(
                      l.famRemove,
                      style: const TextStyle(color: AppColors.danger),
                    ),
                  ),
              ],
            ),
            for (final g in m.devices) ...[
              const Divider(height: AppSpacing.lg),
              _GrantRow(
                grant: g,
                paying:
                    _payingSubscriptionId != null &&
                    _payingSubscriptionId == g.subscription?.id,
                onPay:
                    g.subscription != null && g.subscription!.isPendingPayment
                    ? () => _pay(g.subscription!)
                    : null,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _GrantRow extends StatelessWidget {
  const _GrantRow({required this.grant, required this.paying, this.onPay});

  final FamilyDeviceGrant grant;
  final bool paying;
  final VoidCallback? onPay;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;
    final sub = grant.subscription;
    final (label, tone) = switch (sub?.status) {
      'active' => (l.cxStatusActive, BadgeTone.success),
      'pending_payment' => (l.cxStatusPending, BadgeTone.warning),
      'expired' => (l.cxStatusExpired, BadgeTone.danger),
      'cancelled' => (l.kmTabClosed, BadgeTone.danger),
      null => (l.famSubNone, BadgeTone.neutral),
      _ => (sub!.status, BadgeTone.neutral),
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            const Icon(
              Icons.meeting_room_outlined,
              size: 18,
              color: AppColors.brand,
            ),
            const SizedBox(width: AppSpacing.xs),
            Expanded(
              child: Text(
                grant.label ?? l.payDeviceFallback(grant.deviceId),
                style: text.bodyMedium,
              ),
            ),
            StatusBadge(label: label, tone: tone),
          ],
        ),
        if (sub != null) ...[
          const SizedBox(height: 2),
          Text(
            sub.isActive && sub.endsAt != null
                ? l.famSubActiveUntil(_fmt(sub.endsAt!))
                : '${l.payPendingAdditional} · ${formatMinor(sub.priceMinor, sub.currency)}',
            style: text.bodySmall,
          ),
        ],
        if (onPay != null) ...[
          const SizedBox(height: AppSpacing.sm),
          AppSecondaryButton(
            key: Key('fam-pay-${sub!.id}'),
            label: l.famPayFor,
            icon: Icons.payment,
            onPressed: paying ? null : onPay,
          ),
        ],
      ],
    );
  }
}

class _InvitationsTab extends ConsumerWidget {
  const _InvitationsTab({required this.devices});

  final List<ManagedDevice> devices;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final items = [
      for (final d in devices)
        for (final i in d.invitations) (device: d, invitation: i),
    ]..sort((a, b) => b.invitation.id.compareTo(a.invitation.id));

    return RefreshIndicator(
      onRefresh: () => ref.refresh(managedDevicesProvider.future),
      child: items.isEmpty
          ? ListView(
              children: [
                const SizedBox(height: 120),
                EmptyState(
                  message: l.famNoInvitations,
                  icon: Icons.mail_outline,
                ),
              ],
            )
          : ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                for (final it in items)
                  _InvitationTile(
                    invitation: it.invitation,
                    deviceLabel: it.device.label,
                  ),
              ],
            ),
    );
  }
}

class _InvitationTile extends ConsumerStatefulWidget {
  const _InvitationTile({required this.invitation, required this.deviceLabel});

  final FamilyInvitation invitation;
  final String deviceLabel;

  @override
  ConsumerState<_InvitationTile> createState() => _InvitationTileState();
}

class _InvitationTileState extends ConsumerState<_InvitationTile> {
  bool _busy = false;

  Future<void> _run(
    Future<Result<FamilyInvitation>> Function() call,
    String ok,
  ) async {
    final l = AppLocalizations.of(context);
    setState(() => _busy = true);
    final result = await call();
    if (!mounted) return;
    setState(() => _busy = false);
    result.fold(
      (f) {
        AppSnackBar.show(context, familyFailureMessage(l, f), isError: true);
        if (f is ConflictFailure || f is NotFoundFailure) {
          ref.invalidate(managedDevicesProvider);
        }
      },
      (_) {
        AppSnackBar.show(context, ok);
        ref.invalidate(managedDevicesProvider);
      },
    );
  }

  Future<void> _revoke() async {
    final l = AppLocalizations.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l.kmRevokeTitle),
        content: Text(l.kmRevokeBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(l.cancel),
          ),
          TextButton(
            key: const Key('fam-revoke-confirm'),
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(
              l.kmRevoke,
              style: const TextStyle(color: AppColors.danger),
            ),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(
      () => ref.read(familyRepositoryProvider).revoke(widget.invitation.id),
      l.kmRevoked,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final i = widget.invitation;
    final text = Theme.of(context).textTheme;
    final (label, tone) = switch (i.status) {
      'pending' => (l.kmTabPending, BadgeTone.warning),
      'accepted' => (l.kmTabAccepted, BadgeTone.success),
      'expired' => (l.kmTabExpired, BadgeTone.neutral),
      'declined' => (l.kmStatusDeclined, BadgeTone.danger),
      _ => (l.kmTabClosed, BadgeTone.danger),
    };

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    i.fullName.isEmpty ? (i.email ?? '') : i.fullName,
                    style: text.titleSmall,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                StatusBadge(label: label, tone: tone),
              ],
            ),
            if (i.email != null) Text(i.email!, style: text.bodySmall),
            Text(widget.deviceLabel, style: text.bodySmall),
            if (i.expiresAt != null &&
                (i.status == 'pending' || i.status == 'expired'))
              Text(
                l.kmExpiresAt(
                  DateFormat('dd.MM.yyyy HH:mm').format(i.expiresAt!.toLocal()),
                ),
                style: text.bodySmall,
              ),
            if (i.sendCount > 0)
              Text(l.kmSendCount(i.sendCount), style: text.bodySmall),
            if (i.canResend || i.canRevoke) ...[
              const SizedBox(height: AppSpacing.sm),
              if (_busy)
                const LinearProgressIndicator()
              else
                Wrap(
                  spacing: AppSpacing.sm,
                  children: [
                    if (i.canResend)
                      TextButton.icon(
                        key: Key('fam-resend-${i.id}'),
                        onPressed: () => _run(
                          () => ref.read(familyRepositoryProvider).resend(i.id),
                          l.kmResent,
                        ),
                        icon: const Icon(Icons.send_outlined, size: 18),
                        label: Text(l.kmResend),
                      ),
                    if (i.canRevoke)
                      TextButton.icon(
                        key: Key('fam-revoke-${i.id}'),
                        onPressed: _revoke,
                        icon: const Icon(
                          Icons.block,
                          size: 18,
                          color: AppColors.danger,
                        ),
                        label: Text(
                          l.kmRevoke,
                          style: const TextStyle(color: AppColors.danger),
                        ),
                      ),
                  ],
                ),
            ],
          ],
        ),
      ),
    );
  }
}
