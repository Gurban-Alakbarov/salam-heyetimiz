import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/error/failure.dart';
import '../../core/error/retry_policy.dart';
import '../complex/complex_providers.dart';
import '../payments/domain/payment_entities.dart';
import '../payments/payments_providers.dart';
import 'devices_providers.dart';

/// B19 — what a device card offers when the caller cannot open (`can_open=false`), derived ONLY from
/// existing contracts (no new endpoint): the caller's own subscription brief from `GET /v1/devices/{id}`
/// and, when there is none, the caller's complexes. The server still authorises every payment.
sealed class DeviceAccessAction {
  const DeviceAccessAction();
}

/// Nothing the caller can do from the card (device disabled/suspended, another person's access, …).
class NoDeviceAccessAction extends DeviceAccessAction {
  const NoDeviceAccessAction();
}

/// `pending_payment` → pay the existing subscription (`POST /v1/orders`; the server hands back an
/// in-flight order instead of opening a second one).
class PayPendingAction extends DeviceAccessAction {
  const PayPendingAction(this.subscriptionId, this.tier);
  final int subscriptionId;
  final String tier;
}

/// `expired` → the existing renewal (`POST /v1/subscriptions/{id}/renew`).
class RenewAction extends DeviceAccessAction {
  const RenewAction(this.subscriptionId);
  final int subscriptionId;
}

/// No live subscription on a device of one of the caller's complexes → the existing complex subscribe
/// (`POST /v1/complexes/{id}/devices/{deviceId}/subscribe` → `pending_payment` → checkout).
class SubscribeAction extends DeviceAccessAction {
  const SubscribeAction(this.complexId);
  final int complexId;
}

const _blockedReasons = {'device_disabled', 'device_suspended'};

final deviceAccessActionProvider = FutureProvider.autoDispose
    .family<DeviceAccessAction, int>((ref, deviceId) async {
      final detail =
          (await ref.watch(deviceRepositoryProvider).detail(deviceId)).fold(
            (f) => throw f,
            (d) => d,
          );
      if (detail.canOpen || _blockedReasons.contains(detail.suspensionReason)) {
        return const NoDeviceAccessAction();
      }
      final sub = detail.subscription;
      final id = sub?.id;
      switch (sub?.status) {
        case 'pending_payment' when id != null:
          return PayPendingAction(id, sub!.tier ?? 'main');
        case 'expired' when id != null:
          return RenewAction(id);
        case 'active':
          // Own subscription is live but access is still blocked (e.g. the private owner's expired) —
          // nothing the caller can pay for here.
          return const NoDeviceAccessAction();
      }
      // No subscription (or cancelled / refunded): only a complex device the caller is a resident of can
      // be subscribed to; family members get theirs from the head (B8) and never subscribe here.
      final complexes = await ref.watch(myComplexesProvider.future);
      for (final c in complexes) {
        final devices = await ref.watch(complexDevicesProvider(c.id).future);
        if (devices.any((d) => d.id == deviceId)) return SubscribeAction(c.id);
      }
      return const NoDeviceAccessAction();
    }, retry: retryTransientFailures);

/// Runs [action] through the existing payment entry points and returns the order to check out.
Future<Result<PaymentOrder>> startDeviceAccessPayment(
  WidgetRef ref,
  int deviceId,
  DeviceAccessAction action,
) {
  final payments = ref.read(paymentsRepositoryProvider);
  return switch (action) {
    PayPendingAction(:final subscriptionId, :final tier) =>
      payments.payPendingSubscription(subscriptionId, tier),
    RenewAction(:final subscriptionId) => payments.renewSubscription(
      subscriptionId,
    ),
    SubscribeAction(:final complexId) =>
      ref.read(complexRepositoryProvider).subscribe(complexId, deviceId),
    NoDeviceAccessAction() => Future.value(const Err(UnknownFailure())),
  };
}
