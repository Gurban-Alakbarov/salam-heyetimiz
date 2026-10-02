import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure.dart';
import '../../../design_system/components/app_components.dart';
import '../../../design_system/components/app_inputs.dart';
import '../../../design_system/components/data_components.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../devices/devices_providers.dart';
import '../../home/home_providers.dart';
import '../../payments/payments_providers.dart';
import '../complex_providers.dart';
import '../domain/complex_entities.dart';
import 'complex_failure.dart';

/// `/payments/pending` — the caller's OWN `pending_payment` subscriptions (B16): a resident's `main` one left
/// unpaid, or a family member's `additional` one created on accepting the head's invitation (B8). "Ödə"
/// starts the B1 order for that subscription and hands off to the B13 hosted checkout; the server decides
/// who may pay (SubscriptionPaymentAuthorizer) — a refusal is shown, never retried silently.
class PendingPaymentsScreen extends ConsumerWidget {
  const PendingPaymentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final async = ref.watch(pendingSubscriptionsProvider);
    final devices = ref.watch(deviceListProvider).value?.devices ?? const [];

    String deviceName(int? id) {
      for (final d in devices) {
        if (d.id == id) return d.label;
      }
      return id == null ? '' : l.payDeviceFallback(id);
    }

    return AppScaffold(
      title: l.payPendingTitle,
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(pendingSubscriptionsProvider.future),
        child: async.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => ListView(
            children: [
              const SizedBox(height: 160),
              ErrorStateView(
                message: e is Failure
                    ? complexFailureMessage(l, e)
                    : l.errUnknown,
                onRetry: () => ref.invalidate(pendingSubscriptionsProvider),
              ),
            ],
          ),
          data: (list) => list.isEmpty
              ? ListView(
                  children: [
                    const SizedBox(height: 120),
                    EmptyState(message: l.payPendingNone, icon: Icons.task_alt),
                  ],
                )
              : ListView(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  children: [
                    for (final s in list)
                      _PendingTile(
                        subscription: s,
                        deviceName: deviceName(s.deviceId),
                      ),
                  ],
                ),
        ),
      ),
    );
  }
}

class _PendingTile extends ConsumerStatefulWidget {
  const _PendingTile({required this.subscription, required this.deviceName});

  final PendingSubscription subscription;
  final String deviceName;

  @override
  ConsumerState<_PendingTile> createState() => _PendingTileState();
}

class _PendingTileState extends ConsumerState<_PendingTile> {
  bool _busy = false;

  Future<void> _pay() async {
    final l = AppLocalizations.of(context);
    final s = widget.subscription;
    setState(() => _busy = true);
    final result = await ref
        .read(paymentsRepositoryProvider)
        .payPendingSubscription(s.id, s.tier);
    if (!mounted) return;
    setState(() => _busy = false);
    await result.fold(
      (f) async {
        AppSnackBar.show(context, complexFailureMessage(l, f), isError: true);
      },
      (order) async {
        await context.push('/checkout/${order.id}');
        if (!mounted) return;
        ref
          ..invalidate(pendingSubscriptionsProvider)
          ..invalidate(deviceListProvider)
          ..invalidate(homeProvider);
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final s = widget.subscription;
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
                    widget.deviceName,
                    style: text.titleSmall,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                StatusBadge(label: l.cxStatusPending, tone: BadgeTone.warning),
              ],
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              s.isAdditional ? l.payPendingAdditional : l.payPendingMain,
              style: text.bodySmall,
            ),
            if (s.termDays != null)
              Text(
                l.cxPrice(formatMinor(s.priceMinor, s.currency), s.termDays!),
                style: text.bodySmall,
              ),
            const SizedBox(height: AppSpacing.sm),
            AppButton(
              key: Key('pay-${s.id}'),
              label: l.payNow,
              icon: Icons.payment,
              loading: _busy,
              onPressed: _busy ? null : _pay,
            ),
          ],
        ),
      ),
    );
  }
}
