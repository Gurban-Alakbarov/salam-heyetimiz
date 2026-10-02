import 'package:flutter/foundation.dart';

/// Family head read models — B8 contracts under /v1/family/* and /v1/devices/{id}/invitations
/// (IMPLEMENTATION_PLAN §14 / B17). Three independent layers: the relation (`family_links`), the access
/// (`device_users.family_link_id`, one per granted device) and billing (the member's OWN `additional`
/// subscription on that device). Display models only — the server authorises every action.

DateTime? _date(Object? v) => v is String ? DateTime.tryParse(v) : null;
int _int(Object? v) => (v as num?)?.toInt() ?? 0;

@immutable
class FamilySubscription {
  const FamilySubscription({
    required this.id,
    required this.status,
    required this.tier,
    this.priceMinor = 0,
    this.termDays,
    this.currency,
    this.endsAt,
  });

  final int id;
  final String status;
  final String tier;
  final int priceMinor;
  final int? termDays;
  final String? currency;
  final DateTime? endsAt;

  bool get isActive => status == 'active';
  bool get isPendingPayment => status == 'pending_payment';

  factory FamilySubscription.fromJson(Map<String, dynamic> j) =>
      FamilySubscription(
        id: _int(j['id']),
        status: (j['status'] as String?) ?? '',
        tier: (j['tier'] as String?) ?? 'additional',
        priceMinor: _int(j['price_minor']),
        termDays: (j['term_days'] as num?)?.toInt(),
        currency: j['currency'] as String?,
        endsAt: _date(j['ends_at']),
      );
}

/// One device the head granted to a member (access layer) + the member's own subscription on it.
@immutable
class FamilyDeviceGrant {
  const FamilyDeviceGrant({
    required this.deviceId,
    this.label,
    this.subscription,
  });

  final int deviceId;
  final String? label;
  final FamilySubscription? subscription;

  factory FamilyDeviceGrant.fromJson(Map<String, dynamic> j) =>
      FamilyDeviceGrant(
        deviceId: _int(j['device_id']),
        label: j['label'] as String?,
        subscription: j['subscription'] is Map
            ? FamilySubscription.fromJson(
                Map<String, dynamic>.from(j['subscription'] as Map),
              )
            : null,
      );
}

/// An active family link (relation layer) with the devices granted through it.
@immutable
class FamilyMember {
  const FamilyMember({
    required this.familyLinkId,
    required this.userId,
    this.fullName,
    this.email,
    this.phoneMasked,
    this.linkedAt,
    this.devices = const [],
  });

  final int familyLinkId;
  final int userId;
  final String? fullName;
  final String? email;
  final String? phoneMasked;
  final DateTime? linkedAt;
  final List<FamilyDeviceGrant> devices;

  String get displayName {
    final n = fullName?.trim() ?? '';
    return n.isNotEmpty ? n : (email ?? '#$userId');
  }

  factory FamilyMember.fromJson(Map<String, dynamic> j) => FamilyMember(
    familyLinkId: _int(j['family_link_id']),
    userId: _int(j['user_id']),
    fullName: j['full_name'] as String?,
    email: j['email'] as String?,
    phoneMasked: j['phone_masked'] as String?,
    linkedAt: _date(j['linked_at']),
    devices: (j['devices'] is List ? j['devices'] as List : const [])
        .whereType<Map>()
        .map((m) => FamilyDeviceGrant.fromJson(Map<String, dynamic>.from(m)))
        .toList(),
  );
}

enum FamilyInvitationTab { pending, accepted, expired, closed }

@immutable
class FamilyInvitation {
  const FamilyInvitation({
    required this.id,
    required this.status,
    this.deviceId,
    this.firstName,
    this.lastName,
    this.email,
    this.expiresAt,
    this.sendCount = 0,
  });

  final int id;

  /// Server-effective status (a pending invitation past its expiry is reported as `expired`).
  final String status;
  final int? deviceId;
  final String? firstName;
  final String? lastName;
  final String? email;
  final DateTime? expiresAt;
  final int sendCount;

  String get fullName => [
    firstName,
    lastName,
  ].whereType<String>().where((s) => s.trim().isNotEmpty).join(' ');

  /// Mirrors InvitationService: resend for pending / expired, revoke only while pending.
  bool get canResend => status == 'pending' || status == 'expired';
  bool get canRevoke => status == 'pending';

  FamilyInvitationTab get tab => switch (status) {
    'pending' => FamilyInvitationTab.pending,
    'accepted' => FamilyInvitationTab.accepted,
    'expired' => FamilyInvitationTab.expired,
    _ => FamilyInvitationTab.closed, // cancelled (revoked) / declined
  };

  factory FamilyInvitation.fromJson(Map<String, dynamic> j) => FamilyInvitation(
    id: _int(j['id']),
    status: (j['status'] as String?) ?? '',
    deviceId: (j['device_id'] as num?)?.toInt(),
    firstName: j['first_name'] as String?,
    lastName: j['last_name'] as String?,
    email: j['email'] as String?,
    expiresAt: _date(j['expires_at']),
    sendCount: _int(j['send_count']),
  );
}

/// A device on which the caller may run a family (server: DevicePolicy::manageFamily) + its invitations.
@immutable
class ManagedDevice {
  const ManagedDevice({
    required this.deviceId,
    required this.label,
    this.invitations = const [],
  });

  final int deviceId;
  final String label;
  final List<FamilyInvitation> invitations;
}

/// POST /v1/devices/{id}/invitations — an already-active member gets the device at once (`granted`);
/// anyone else receives an emailed invitation.
@immutable
class FamilyInviteResult {
  const FamilyInviteResult({required this.granted, this.invitation});

  final bool granted;
  final FamilyInvitation? invitation;

  factory FamilyInviteResult.fromJson(Map<String, dynamic> j) =>
      FamilyInviteResult(
        granted: j['granted'] == true,
        invitation: j['invitation'] is Map
            ? FamilyInvitation.fromJson(
                Map<String, dynamic>.from(j['invitation'] as Map),
              )
            : null,
      );
}

/// DELETE /v1/family/members/{userId} — what the removal did (no refund, history kept).
@immutable
class FamilyRemoval {
  const FamilyRemoval({this.revokedRows = 0, this.cancelledSubscriptions = 0});

  final int revokedRows;
  final int cancelledSubscriptions;

  factory FamilyRemoval.fromJson(Map<String, dynamic> j) => FamilyRemoval(
    revokedRows: _int(j['revoked_rows']),
    cancelledSubscriptions: _int(j['cancelled_subscriptions']),
  );
}
