import 'package:flutter/foundation.dart';

/// A mobile order as returned by GET /v1/orders/{id} (B1/B7 OrderResource). Only what the payment flow
/// needs. [redirectUrl] is the hosted (BirPay or fake) checkout page — a signed URL: never logged.
@immutable
class PaymentOrder {
  const PaymentOrder({
    required this.id,
    required this.reference,
    required this.status,
    required this.amountMinor,
    required this.currency,
    this.redirectUrl,
    this.isTest = false,
  });

  final int id;
  final String reference;
  final String status;
  final int amountMinor;
  final String currency;
  final String? redirectUrl;
  final bool isTest;

  /// Tolerates both a bare resource and a `{data: {...}}` wrapper.
  factory PaymentOrder.fromJson(Map<String, dynamic> json) {
    final d = json['data'] is Map && (json['data'] as Map).containsKey('id')
        ? Map<String, dynamic>.from(json['data'] as Map)
        : json;
    return PaymentOrder(
      id: (d['id'] as num).toInt(),
      reference: (d['reference'] ?? '').toString(),
      status: (d['status'] ?? 'pending').toString(),
      amountMinor: (d['amount_minor'] as num?)?.toInt() ?? 0,
      currency: (d['currency'] ?? 'AZN').toString(),
      redirectUrl: d['bank_redirect_url'] as String?,
      isTest: d['is_test'] == true,
    );
  }

  PaymentOutcome get outcome => PaymentOutcomeX.fromOrderStatus(status);

  @override
  String toString() => 'PaymentOrder(#$id, $status, test=$isTest)';
}

/// What the user sees after checkout (plan §16: success / failed / cancelled / pending (+ expired)).
enum PaymentOutcome { success, failed, cancelled, expired, pending }

extension PaymentOutcomeX on PaymentOutcome {
  bool get isFinal => this != PaymentOutcome.pending;

  /// Server order status → outcome. `refunded` / `partially_refunded` were paid first.
  static PaymentOutcome fromOrderStatus(String status) => switch (status) {
        'paid' || 'refunded' || 'partially_refunded' => PaymentOutcome.success,
        'failed' => PaymentOutcome.failed,
        'cancelled' => PaymentOutcome.cancelled,
        'expired' => PaymentOutcome.expired,
        _ => PaymentOutcome.pending, // pending / authorising / unknown
      };
}
