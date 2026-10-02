import 'package:flutter/foundation.dart';

/// Resident complex + invitation read models — B6 (`/v1/invites/*`), B7 (`/v1/complexes/*`) and the
/// caller's pending subscriptions (`/v1/subscriptions?status=pending_payment`). Display models only: every
/// rule (who may join, subscribe or pay) is enforced by the server (IMPLEMENTATION_PLAN §9, §13, §14 / B16).

DateTime? _date(Object? v) => v is String ? DateTime.tryParse(v) : null;
int _int(Object? v) => (v as num?)?.toInt() ?? 0;

enum InviteKind { complexResident, familyMember, unknown }

InviteKind inviteKindFrom(Object? raw) => switch (raw) {
  'complex_resident' => InviteKind.complexResident,
  'family_member' => InviteKind.familyMember,
  _ => InviteKind.unknown,
};

/// GET /v1/invites/{token} — what the invitee may see before signing in (no ids, the email masked).
@immutable
class InvitePreview {
  const InvitePreview({
    required this.kind,
    this.complexName,
    this.inviterName,
    this.firstName,
    this.lastName,
    this.emailMasked,
    this.expiresAt,
  });

  final InviteKind kind;
  final String? complexName;
  final String? inviterName;
  final String? firstName;
  final String? lastName;
  final String? emailMasked;
  final DateTime? expiresAt;

  String get inviteeName => [
    firstName,
    lastName,
  ].whereType<String>().where((s) => s.trim().isNotEmpty).join(' ');

  factory InvitePreview.fromJson(Map<String, dynamic> j) => InvitePreview(
    kind: inviteKindFrom(j['kind']),
    complexName: j['complex_name'] as String?,
    inviterName: j['inviter_name'] as String?,
    firstName: j['first_name'] as String?,
    lastName: j['last_name'] as String?,
    emailMasked: j['email_masked'] as String?,
    expiresAt: _date(j['expires_at']),
  );
}

/// POST /v1/invites/{token}/accept — complex: `{kind, complex:{id,name}}`;
/// family: `{kind, family_link_id, device:{id,label}, subscription_id}` (the member's own pending one).
@immutable
class InviteAcceptance {
  const InviteAcceptance({
    required this.kind,
    this.complexId,
    this.complexName,
    this.deviceId,
    this.deviceLabel,
    this.subscriptionId,
  });

  final InviteKind kind;
  final int? complexId;
  final String? complexName;
  final int? deviceId;
  final String? deviceLabel;
  final int? subscriptionId;

  factory InviteAcceptance.fromJson(Map<String, dynamic> j) {
    final complex = j['complex'] is Map
        ? Map<String, dynamic>.from(j['complex'] as Map)
        : null;
    final device = j['device'] is Map
        ? Map<String, dynamic>.from(j['device'] as Map)
        : null;
    return InviteAcceptance(
      kind: inviteKindFrom(j['kind']),
      complexId: (complex?['id'] as num?)?.toInt(),
      complexName: complex?['name'] as String?,
      deviceId: (device?['id'] as num?)?.toInt(),
      deviceLabel: device?['label'] as String?,
      subscriptionId: (j['subscription_id'] as num?)?.toInt(),
    );
  }
}

@immutable
class ResidentComplex {
  const ResidentComplex({required this.id, required this.name, this.address});

  final int id;
  final String name;
  final String? address;

  factory ResidentComplex.fromJson(Map<String, dynamic> j) => ResidentComplex(
    id: _int(j['id']),
    name: (j['name'] as String?) ?? '',
    address: j['address'] as String?,
  );
}

/// The caller's own subscription state on a complex device (never other residents').
enum MySubscriptionStatus { none, pendingPayment, active, expired }

MySubscriptionStatus mySubscriptionStatusFrom(Object? raw) => switch (raw) {
  'pending_payment' => MySubscriptionStatus.pendingPayment,
  'active' => MySubscriptionStatus.active,
  'expired' => MySubscriptionStatus.expired,
  _ => MySubscriptionStatus.none,
};

@immutable
class ComplexDevice {
  const ComplexDevice({
    required this.id,
    required this.label,
    this.address,
    this.imageUrl,
    this.subscriptionPriceMinor,
    this.subscriptionTermDays,
    this.currency,
    this.myStatus = MySubscriptionStatus.none,
  });

  final int id;
  final String label;
  final String? address;
  final String? imageUrl;
  final int? subscriptionPriceMinor;
  final int? subscriptionTermDays;
  final String? currency;
  final MySubscriptionStatus myStatus;

  /// Subscribe / finish paying / renew all go through the same server action (idempotent per pending row).
  bool get canStartSubscription => myStatus != MySubscriptionStatus.active;

  factory ComplexDevice.fromJson(Map<String, dynamic> j) => ComplexDevice(
    id: _int(j['id']),
    label: (j['label'] as String?) ?? '',
    address: j['address'] as String?,
    imageUrl: j['image_url'] as String?,
    subscriptionPriceMinor: (j['subscription_price_minor'] as num?)?.toInt(),
    subscriptionTermDays: (j['subscription_term_days'] as num?)?.toInt(),
    currency: j['currency'] as String?,
    myStatus: mySubscriptionStatusFrom(j['my_subscription_status']),
  );
}

/// One of the caller's own `pending_payment` subscriptions (resident `main` or family member `additional`).
@immutable
class PendingSubscription {
  const PendingSubscription({
    required this.id,
    required this.tier,
    this.deviceId,
    this.priceMinor = 0,
    this.currency,
    this.termDays,
  });

  final int id;
  final String tier;
  final int? deviceId;
  final int priceMinor;
  final String? currency;
  final int? termDays;

  bool get isAdditional => tier == 'additional';

  factory PendingSubscription.fromJson(Map<String, dynamic> j) =>
      PendingSubscription(
        id: _int(j['id']),
        tier: (j['tier'] as String?) ?? 'main',
        deviceId: (j['device_id'] as num?)?.toInt(),
        priceMinor: _int(j['price_minor']),
        currency: j['currency'] as String?,
        termDays: (j['term_days'] as num?)?.toInt(),
      );
}

/// `12.00 AZN` from minor units (display only — the server prices every order).
String formatMinor(int minor, String? currency) =>
    '${(minor / 100).toStringAsFixed(2)} ${currency ?? 'AZN'}';
