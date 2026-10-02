import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure.dart';
import '../../../design_system/components/app_components.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../complex/complex_providers.dart';
import '../../devices/devices_providers.dart';
import '../../family/family_providers.dart';
import '../../home/home_providers.dart';
import '../../subscriptions/subscriptions_providers.dart';
import '../domain/payment_entities.dart';
import '../domain/payment_poller.dart';
import '../payments_providers.dart';
import 'test_payment_banner.dart';

/// Payment result (IMPLEMENTATION_PLAN §16.5 / B13). Reached from the checkout WebView (by [orderId]) or
/// from an external `salam://payment/return` deep link (by [orderReference]). The outcome is always read
/// from the server (GET /v1/orders/{id} polling + one recheck) — the deep link is only a trigger.
class PaymentResultScreen extends ConsumerStatefulWidget {
  const PaymentResultScreen({this.orderId, this.orderReference, super.key});

  final int? orderId;
  final String? orderReference;

  @override
  ConsumerState<PaymentResultScreen> createState() =>
      _PaymentResultScreenState();
}

class _PaymentResultScreenState extends ConsumerState<PaymentResultScreen> {
  PaymentOrder? _order;
  Failure? _failure;
  bool _polling = false;
  bool _notFound = false;
  PaymentPoller? _poller;
  StreamSubscription<PaymentOrder>? _sub;

  @override
  void initState() {
    super.initState();
    _start();
  }

  @override
  void dispose() {
    _poller?.cancel();
    _sub?.cancel();
    super.dispose();
  }

  Future<void> _start() async {
    final repo = ref.read(paymentsRepositoryProvider);
    var id = widget.orderId;
    if (id == null && widget.orderReference != null) {
      final found = await repo.findByReference(widget.orderReference!);
      if (!mounted) return;
      found.fold((f) => setState(() => _failure = f), (o) => id = o?.id);
      if (id == null) {
        if (_failure == null) setState(() => _notFound = true);
        return;
      }
    }
    if (id == null) {
      setState(() => _notFound = true);
      return;
    }
    _poll(id!);
  }

  void _poll(int id) {
    final repo = ref.read(paymentsRepositoryProvider);
    Future<PaymentOrder> unwrap(Future<Result<PaymentOrder>> call) async =>
        (await call).fold((f) => throw f, (o) => o);

    _sub?.cancel();
    _poller = PaymentPoller(
      fetch: () => unwrap(repo.getOrder(id)),
      recheck: () => unwrap(repo.recheck(id)),
    );
    setState(() {
      _polling = true;
      _failure = null;
    });
    _sub = _poller!.run().listen(
      (o) => setState(() => _order = o),
      onError: (Object e) => setState(() {
        _failure = e is Failure ? e : const UnknownFailure();
        _polling = false;
      }),
      onDone: () {
        if (!mounted) return;
        setState(() => _polling = false);
        // A paid order changes access everywhere (B18): refresh it here, not in the caller — "Ana səhifəyə
        // qayıt" uses go(), which disposes the screen that pushed the checkout before it could refresh.
        if (_order?.outcome == PaymentOutcome.success) {
          ref
            ..invalidate(activeSubscriptionsProvider)
            ..invalidate(deviceListProvider)
            ..invalidate(pendingSubscriptionsProvider)
            ..invalidate(familyMembersProvider)
            ..invalidate(homeProvider);
        }
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final order = _order;
    final outcome = order?.outcome ?? PaymentOutcome.pending;

    final (IconData icon, Color color, String title) = switch (outcome) {
      PaymentOutcome.success => (
        Icons.check_circle,
        AppColors.success,
        l.paymentSuccess,
      ),
      PaymentOutcome.failed => (
        Icons.cancel,
        AppColors.danger,
        l.paymentFailed,
      ),
      PaymentOutcome.cancelled => (
        Icons.remove_circle,
        AppColors.warning,
        l.paymentCancelled,
      ),
      PaymentOutcome.expired => (
        Icons.timer_off,
        AppColors.warning,
        l.paymentExpired,
      ),
      PaymentOutcome.pending => (
        Icons.hourglass_top,
        AppColors.brand,
        l.paymentPending,
      ),
    };

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) context.go('/home');
      },
      child: AppScaffold(
        title: l.paymentResultTitle,
        body: Column(
          children: [
            if (order?.isTest ?? false)
              TestPaymentBanner(label: l.paymentTestBanner),
            Expanded(
              child: Center(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  child: _notFound
                      ? EmptyState(
                          message: l.paymentOrderNotFound,
                          icon: Icons.receipt_long_outlined,
                        )
                      : _failure != null && order == null
                      ? ErrorStateView(message: l.errUnknown, onRetry: _start)
                      : Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(icon, size: 72, color: color),
                            const SizedBox(height: AppSpacing.md),
                            Text(
                              title,
                              style: Theme.of(context).textTheme.titleLarge,
                              textAlign: TextAlign.center,
                            ),
                            if (order != null) ...[
                              const SizedBox(height: AppSpacing.xs),
                              Text(
                                order.reference,
                                style: Theme.of(context).textTheme.bodyMedium,
                              ),
                            ],
                            if (_polling) ...[
                              const SizedBox(height: AppSpacing.md),
                              Text(
                                l.paymentChecking,
                                style: Theme.of(context).textTheme.bodyMedium,
                              ),
                              const SizedBox(height: AppSpacing.sm),
                              const SizedBox(
                                width: 160,
                                child: LinearProgressIndicator(),
                              ),
                            ],
                            const SizedBox(height: AppSpacing.lg),
                            if (!_polling &&
                                outcome == PaymentOutcome.pending &&
                                order != null)
                              AppButton(
                                label: l.paymentRecheck,
                                onPressed: () => _poll(order.id),
                              ),
                            const SizedBox(height: AppSpacing.sm),
                            TextButton(
                              onPressed: () => context.go('/home'),
                              child: Text(l.paymentDone),
                            ),
                          ],
                        ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
