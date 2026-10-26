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

/// KomendantResidentsScreen (BR-11 / B15): active residents with a subscription chip and "Çıxar". Removal
/// is confirmed first and explains the server-side effect (access revoked, active subscriptions cancelled,
/// no automatic refund — RemoveComplexResident).
///
/// O7 — each head's family is listed under them: a member can be removed on its own, the family can be
/// handed to another member ("Başçını dəyiş"), and removing a head first asks what happens to the family
/// (a member takes over, or the whole family leaves).
class KomendantResidentsScreen extends ConsumerWidget {
  const KomendantResidentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final async = ref.watch(komendantResidentsProvider);

    return AppScaffold(
      title: l.kmResidentsTitle,
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(komendantResidentsProvider.future),
        child: async.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => ListView(
            children: [
              const SizedBox(height: 160),
              ErrorStateView(
                message: e is Failure
                    ? komendantFailureMessage(l, e)
                    : l.errUnknown,
                onRetry: () => ref.invalidate(komendantResidentsProvider),
              ),
            ],
          ),
          data: (list) => list.isEmpty
              ? ListView(
                  children: [
                    const SizedBox(height: 120),
                    EmptyState(
                      message: l.kmNoResidents,
                      icon: Icons.people_outline,
                    ),
                  ],
                )
              : ListView(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  children: [for (final r in list) _ResidentTile(resident: r)],
                ),
        ),
      ),
    );
  }
}

class _ResidentTile extends ConsumerStatefulWidget {
  const _ResidentTile({required this.resident});

  final KomendantResident resident;

  @override
  ConsumerState<_ResidentTile> createState() => _ResidentTileState();
}

class _ResidentTileState extends ConsumerState<_ResidentTile> {
  bool _busy = false;

  bool get _hasFamily => widget.resident.isFamilyHead && widget.resident.family.isNotEmpty;

  Future<bool?> _confirm(String title, String body) {
    final l = AppLocalizations.of(context);
    return showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: Text(body),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(l.cancel),
          ),
          TextButton(
            key: const Key('km-remove-confirm'),
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(
              l.kmRemove,
              style: const TextStyle(color: AppColors.danger),
            ),
          ),
        ],
      ),
    );
  }

  /// Runs a server action with the busy spinner, then reports and refreshes the lists.
  Future<void> _run(Future<Result<Object?>> Function() action, String doneMessage) async {
    final l = AppLocalizations.of(context);
    setState(() => _busy = true);
    final result = await action();
    if (!mounted) return;
    setState(() => _busy = false);
    result.fold(
      (f) {
        final message = switch (f) {
          ConflictFailure(code: 'family_decision_required') || ValidationFailure() => l.kmErrFamily,
          _ => komendantFailureMessage(l, f),
        };
        AppSnackBar.show(context, message, isError: true);
        ref.invalidate(komendantResidentsProvider);
      },
      (_) {
        AppSnackBar.show(context, doneMessage);
        ref.invalidate(komendantResidentsProvider);
        ref.invalidate(komendantComplexProvider);
      },
    );
  }

  Future<void> _removeResident() async {
    final l = AppLocalizations.of(context);
    final r = widget.resident;
    final repo = ref.read(komendantRepositoryProvider);

    if (!_hasFamily) {
      if (await _confirm(l.kmRemoveTitle, l.kmRemoveBody(r.displayName)) != true || !mounted) return;
      return _run(() => repo.removeResident(r.userId), l.kmRemoved);
    }

    final decision = await showDialog<_HeadDecision>(
      context: context,
      builder: (_) => _FamilyDialog(
        title: l.kmRemoveHeadTitle,
        body: l.kmRemoveHeadBody(r.displayName),
        members: r.family,
        removeMode: true,
      ),
    );
    if (decision == null || !mounted) return;
    return _run(
      () => repo.removeResident(
        r.userId,
        familyChoice: decision.newHeadUserId == null ? FamilyRemovalChoice.removeFamily : FamilyRemovalChoice.newHead,
        newHeadUserId: decision.newHeadUserId,
      ),
      l.kmRemoved,
    );
  }

  Future<void> _changeHead() async {
    final l = AppLocalizations.of(context);
    final r = widget.resident;
    final decision = await showDialog<_HeadDecision>(
      context: context,
      builder: (_) => _FamilyDialog(
        title: l.kmChangeHeadTitle,
        body: l.kmChangeHeadBody(r.displayName),
        members: r.family,
        removeMode: false,
      ),
    );
    if (decision == null || decision.newHeadUserId == null || !mounted) return;
    return _run(
      () => ref.read(komendantRepositoryProvider).changeFamilyHead(
        r.userId,
        newHeadUserId: decision.newHeadUserId!,
        removeOldHead: decision.removeOldHead,
      ),
      l.kmHeadChanged,
    );
  }

  Future<void> _removeMember(KomendantFamilyMember m) async {
    final l = AppLocalizations.of(context);
    if (await _confirm(l.kmRemoveTitle, l.kmRemoveMemberBody(m.displayName)) != true || !mounted) return;
    return _run(() => ref.read(komendantRepositoryProvider).removeResident(m.userId), l.kmRemoved);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final r = widget.resident;
    final text = Theme.of(context).textTheme;
    final subs = r.activeSubscriptions;
    final name = r.displayName;

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
                      Text(
                        name,
                        style: text.titleSmall,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      if (r.email != null && r.email != name)
                        Text(r.email!, style: text.bodySmall),
                      if (r.phoneMasked != null)
                        Text(r.phoneMasked!, style: text.bodySmall),
                      if (r.joinedAt != null)
                        Text(
                          l.kmJoinedAt(
                            DateFormat('dd.MM.yyyy').format(r.joinedAt!.toLocal()),
                          ),
                          style: text.bodySmall,
                        ),
                      const SizedBox(height: AppSpacing.xs),
                      StatusBadge(
                        label: subs > 0 ? l.kmActiveSubs(subs) : l.kmNoActiveSub,
                        tone: subs > 0 ? BadgeTone.success : BadgeTone.neutral,
                      ),
                    ],
                  ),
                ),
                if (_busy)
                  const SizedBox(
                    width: 24,
                    height: 24,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                else
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      TextButton(
                        key: Key('km-remove-${r.userId}'),
                        onPressed: _removeResident,
                        child: Text(
                          l.kmRemove,
                          style: const TextStyle(color: AppColors.danger),
                        ),
                      ),
                      if (_hasFamily)
                        TextButton(
                          key: Key('km-change-head-${r.userId}'),
                          onPressed: _changeHead,
                          child: Text(l.kmChangeHead),
                        ),
                    ],
                  ),
              ],
            ),
            if (_hasFamily) ...[
              const Divider(height: AppSpacing.lg),
              Text(l.kmFamilyLabel, style: text.labelMedium),
              for (final m in r.family)
                Row(
                  children: [
                    const Icon(Icons.subdirectory_arrow_right, size: 18),
                    const SizedBox(width: AppSpacing.xs),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(m.displayName, style: text.bodyMedium, maxLines: 1, overflow: TextOverflow.ellipsis),
                          Text(
                            m.activeSubscriptions > 0 ? l.kmActiveSubs(m.activeSubscriptions) : l.kmNoActiveSub,
                            style: text.bodySmall,
                          ),
                        ],
                      ),
                    ),
                    if (!_busy)
                      TextButton(
                        key: Key('km-remove-member-${m.userId}'),
                        onPressed: () => _removeMember(m),
                        child: Text(l.kmRemove, style: const TextStyle(color: AppColors.danger)),
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

/// What the Komendant decided: [newHeadUserId] null = the whole family leaves (remove mode only).
class _HeadDecision {
  const _HeadDecision({this.newHeadUserId, this.removeOldHead = false});

  final int? newHeadUserId;
  final bool removeOldHead;
}

/// O7 — pick the family's new head. In remove mode the alternative is removing the whole family; otherwise the
/// Komendant chooses whether the old head stays as a member (default) or leaves the complex.
class _FamilyDialog extends StatefulWidget {
  const _FamilyDialog({
    required this.title,
    required this.body,
    required this.members,
    required this.removeMode,
  });

  final String title;
  final String body;
  final List<KomendantFamilyMember> members;
  final bool removeMode;

  @override
  State<_FamilyDialog> createState() => _FamilyDialogState();
}

class _FamilyDialogState extends State<_FamilyDialog> {
  late int? _newHead = widget.members.first.userId;
  bool _wholeFamily = false;
  bool _removeOldHead = false;

  Widget _choice({required bool selected, required String label, required VoidCallback onTap, Key? key}) => ListTile(
    key: key,
    dense: true,
    contentPadding: EdgeInsets.zero,
    leading: Icon(
      selected ? Icons.radio_button_checked : Icons.radio_button_unchecked,
      color: selected ? AppColors.brand : null,
    ),
    title: Text(label),
    onTap: onTap,
  );

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;

    return AlertDialog(
      title: Text(widget.title),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.body),
            const SizedBox(height: AppSpacing.md),
            if (widget.removeMode) ...[
              _choice(
                key: const Key('km-family-new-head'),
                selected: !_wholeFamily,
                label: l.kmRemoveHeadNewHead,
                onTap: () => setState(() => _wholeFamily = false),
              ),
              _choice(
                key: const Key('km-family-remove-all'),
                selected: _wholeFamily,
                label: l.kmRemoveHeadWholeFamily,
                onTap: () => setState(() => _wholeFamily = true),
              ),
            ],
            if (!_wholeFamily) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(l.kmNewHead, style: text.labelMedium),
              for (final m in widget.members)
                _choice(
                  key: Key('km-new-head-${m.userId}'),
                  selected: _newHead == m.userId,
                  label: m.displayName,
                  onTap: () => setState(() => _newHead = m.userId),
                ),
            ],
            if (!widget.removeMode) ...[
              const Divider(height: AppSpacing.lg),
              _choice(
                selected: !_removeOldHead,
                label: l.kmOldHeadStays,
                onTap: () => setState(() => _removeOldHead = false),
              ),
              _choice(
                key: const Key('km-old-head-leaves'),
                selected: _removeOldHead,
                label: l.kmOldHeadLeaves,
                onTap: () => setState(() => _removeOldHead = true),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: Text(l.cancel),
        ),
        TextButton(
          key: const Key('km-family-confirm'),
          onPressed: () => Navigator.of(context).pop(
            _HeadDecision(
              newHeadUserId: _wholeFamily ? null : _newHead,
              removeOldHead: _removeOldHead,
            ),
          ),
          child: Text(
            widget.removeMode ? l.kmRemove : l.kmChangeHead,
            style: widget.removeMode ? const TextStyle(color: AppColors.danger) : null,
          ),
        ),
      ],
    );
  }
}
