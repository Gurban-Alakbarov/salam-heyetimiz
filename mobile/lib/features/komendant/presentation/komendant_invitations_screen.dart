import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/error/failure.dart';
import '../../../design_system/components/app_components.dart';
import '../../../design_system/components/app_inputs.dart';
import '../../../design_system/components/data_components.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/komendant_entities.dart';
import '../komendant_providers.dart';
import 'komendant_failure.dart';

/// KomendantInvitationsScreen (BR-10 / B15): the complex's resident invitations in status tabs, with
/// resend (pending / expired, rate-limited server-side) and revoke (pending only, confirmed first).
class KomendantInvitationsScreen extends ConsumerWidget {
  const KomendantInvitationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final async = ref.watch(komendantInvitationsProvider);
    final tabs = [
      (InvitationTab.pending, l.kmTabPending),
      (InvitationTab.accepted, l.kmTabAccepted),
      (InvitationTab.expired, l.kmTabExpired),
      (InvitationTab.closed, l.kmTabClosed),
    ];

    return DefaultTabController(
      length: tabs.length,
      child: AppScaffold(
        title: l.kmInvitations,
        body: Column(
          children: [
            TabBar(
              isScrollable: true,
              tabAlignment: TabAlignment.start,
              tabs: [for (final t in tabs) Tab(text: t.$2)],
            ),
            Expanded(
              child: async.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (e, _) => Center(
                  child: ErrorStateView(
                    message: e is Failure
                        ? komendantFailureMessage(l, e)
                        : l.errUnknown,
                    onRetry: () => ref.invalidate(komendantInvitationsProvider),
                  ),
                ),
                data: (all) => TabBarView(
                  children: [
                    for (final t in tabs)
                      RefreshIndicator(
                        onRefresh: () =>
                            ref.refresh(komendantInvitationsProvider.future),
                        child: _TabList(
                          items: all.where((i) => i.tab == t.$1).toList(),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TabList extends StatelessWidget {
  const _TabList({required this.items});

  final List<KomendantInvitation> items;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    if (items.isEmpty) {
      return ListView(
        children: [
          const SizedBox(height: 120),
          EmptyState(message: l.kmNoInvitations, icon: Icons.mail_outline),
        ],
      );
    }
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [for (final i in items) _InvitationTile(invitation: i)],
    );
  }
}

class _InvitationTile extends ConsumerStatefulWidget {
  const _InvitationTile({required this.invitation});

  final KomendantInvitation invitation;

  @override
  ConsumerState<_InvitationTile> createState() => _InvitationTileState();
}

class _InvitationTileState extends ConsumerState<_InvitationTile> {
  bool _busy = false;

  static String _fmt(DateTime d) =>
      DateFormat('dd.MM.yyyy HH:mm').format(d.toLocal());

  Future<void> _run(
    Future<Result<KomendantInvitation>> Function() call,
    String ok,
  ) async {
    final l = AppLocalizations.of(context);
    setState(() => _busy = true);
    final result = await call();
    if (!mounted) return;
    setState(() => _busy = false);
    result.fold(
      (f) {
        AppSnackBar.show(context, komendantFailureMessage(l, f), isError: true);
        // A stale row (e.g. revoked elsewhere) → reload so the tabs show the server truth.
        if (f is NotFoundFailure || f is ConflictFailure) {
          ref.invalidate(komendantInvitationsProvider);
        }
      },
      (_) {
        AppSnackBar.show(context, ok);
        ref.invalidate(komendantInvitationsProvider);
        ref.invalidate(komendantComplexProvider);
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
            key: const Key('km-revoke-confirm'),
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
      () => ref.read(komendantRepositoryProvider).revoke(widget.invitation.id),
      l.kmRevoked,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final i = widget.invitation;
    final (label, tone) = switch (i.status) {
      'pending' => (l.kmTabPending, BadgeTone.warning),
      'accepted' => (l.kmTabAccepted, BadgeTone.success),
      'expired' => (l.kmTabExpired, BadgeTone.neutral),
      'declined' => (l.kmStatusDeclined, BadgeTone.danger),
      _ => (l.kmTabClosed, BadgeTone.danger),
    };
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
            if (i.email != null) ...[
              const SizedBox(height: 2),
              Text(i.email!, style: text.bodySmall),
            ],
            const SizedBox(height: AppSpacing.xs),
            if (i.status == 'accepted' && i.acceptedAt != null)
              Text(l.kmAcceptedAt(_fmt(i.acceptedAt!)), style: text.bodySmall)
            else if (i.expiresAt != null && i.tab != InvitationTab.closed)
              Text(l.kmExpiresAt(_fmt(i.expiresAt!)), style: text.bodySmall),
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
                        key: Key('km-resend-${i.id}'),
                        onPressed: () => _run(
                          () => ref
                              .read(komendantRepositoryProvider)
                              .resend(i.id),
                          l.kmResent,
                        ),
                        icon: const Icon(Icons.send_outlined, size: 18),
                        label: Text(l.kmResend),
                      ),
                    if (i.canRevoke)
                      TextButton.icon(
                        key: Key('km-revoke-${i.id}'),
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
