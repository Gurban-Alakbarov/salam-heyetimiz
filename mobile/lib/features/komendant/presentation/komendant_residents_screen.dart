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

  String get _name {
    final r = widget.resident;
    final n = r.fullName?.trim() ?? '';
    return n.isNotEmpty ? n : (r.email ?? r.phoneMasked ?? '#${r.userId}');
  }

  Future<void> _remove() async {
    final l = AppLocalizations.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l.kmRemoveTitle),
        content: Text(l.kmRemoveBody(_name)),
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
    if (confirmed != true || !mounted) return;

    setState(() => _busy = true);
    final result = await ref
        .read(komendantRepositoryProvider)
        .removeResident(widget.resident.userId);
    if (!mounted) return;
    setState(() => _busy = false);
    result.fold(
      (f) {
        AppSnackBar.show(context, komendantFailureMessage(l, f), isError: true);
        if (f is NotFoundFailure) ref.invalidate(komendantResidentsProvider);
      },
      (_) {
        AppSnackBar.show(context, l.kmRemoved);
        ref.invalidate(komendantResidentsProvider);
        ref.invalidate(komendantComplexProvider);
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final r = widget.resident;
    final text = Theme.of(context).textTheme;
    final subs = r.activeSubscriptions;

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: AppCard(
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _name,
                    style: text.titleSmall,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (r.email != null && r.email != _name)
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
              TextButton(
                key: Key('km-remove-${r.userId}'),
                onPressed: _remove,
                child: Text(
                  l.kmRemove,
                  style: const TextStyle(color: AppColors.danger),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
