import 'package:flutter/foundation.dart';

/// Komendant (complex manager) read models — B5 contracts under /v1/komendant/* (IMPLEMENTATION_PLAN §12 /
/// B15). The server scopes everything to the linked manager's complex; these are display models only.

DateTime? _date(Object? v) => v is String ? DateTime.tryParse(v) : null;
int _int(Object? v) => (v as num?)?.toInt() ?? 0;

@immutable
class KomendantComplex {
  const KomendantComplex({
    required this.id,
    required this.name,
    this.address,
    this.devices = 0,
    this.residents = 0,
    this.pendingInvitations = 0,
  });

  final int id;
  final String name;
  final String? address;
  final int devices;
  final int residents;
  final int pendingInvitations;

  factory KomendantComplex.fromJson(Map<String, dynamic> j) {
    final stats = j['stats'] is Map
        ? Map<String, dynamic>.from(j['stats'] as Map)
        : const <String, dynamic>{};
    return KomendantComplex(
      id: _int(j['id']),
      name: (j['name'] as String?) ?? '',
      address: j['address'] as String?,
      devices: _int(stats['devices']),
      residents: _int(stats['residents']),
      pendingInvitations: _int(stats['pending_invitations']),
    );
  }
}

/// A shared complex device. Only the monthly subscription price is exposed — never the sale price.
@immutable
class KomendantDevice {
  const KomendantDevice({
    required this.id,
    required this.label,
    this.address,
    this.status = '',
    this.online = false,
    this.subscriptionPriceMinor,
    this.subscriptionTermDays,
    this.currency,
  });

  final int id;
  final String label;
  final String? address;
  final String status;
  final bool online;
  final int? subscriptionPriceMinor;
  final int? subscriptionTermDays;
  final String? currency;

  factory KomendantDevice.fromJson(Map<String, dynamic> j) => KomendantDevice(
    id: _int(j['id']),
    label: (j['label'] as String?) ?? '',
    address: j['address'] as String?,
    status: (j['status'] as String?) ?? '',
    online: j['online'] == true,
    subscriptionPriceMinor: (j['subscription_price_minor'] as num?)?.toInt(),
    subscriptionTermDays: (j['subscription_term_days'] as num?)?.toInt(),
    currency: j['currency'] as String?,
  );
}

@immutable
class KomendantResident {
  const KomendantResident({
    required this.userId,
    this.fullName,
    this.email,
    this.phoneMasked,
    this.joinedAt,
    this.activeSubscriptions = 0,
  });

  final int userId;
  final String? fullName;
  final String? email;
  final String? phoneMasked;
  final DateTime? joinedAt;
  final int activeSubscriptions;

  factory KomendantResident.fromJson(Map<String, dynamic> j) =>
      KomendantResident(
        userId: _int(j['user_id']),
        fullName: j['full_name'] as String?,
        email: j['email'] as String?,
        phoneMasked: j['phone_masked'] as String?,
        joinedAt: _date(j['joined_at']),
        activeSubscriptions: _int(j['active_subscriptions']),
      );
}

/// Invitation tabs. `closed` groups `cancelled` (revoked) and `declined`.
enum InvitationTab { pending, accepted, expired, closed }

@immutable
class KomendantInvitation {
  const KomendantInvitation({
    required this.id,
    required this.status,
    this.firstName,
    this.lastName,
    this.email,
    this.expiresAt,
    this.sendCount = 0,
    this.lastSentAt,
    this.acceptedAt,
    this.revokedAt,
    this.createdAt,
  });

  final int id;

  /// Server-effective status (a pending invitation past its expiry is already reported as `expired`).
  final String status;
  final String? firstName;
  final String? lastName;
  final String? email;
  final DateTime? expiresAt;
  final int sendCount;
  final DateTime? lastSentAt;
  final DateTime? acceptedAt;
  final DateTime? revokedAt;
  final DateTime? createdAt;

  String get fullName => [
    firstName,
    lastName,
  ].whereType<String>().where((s) => s.trim().isNotEmpty).join(' ');

  /// Mirrors InvitationService: resend for pending/expired, revoke only while still pending.
  bool get canResend => status == 'pending' || status == 'expired';
  bool get canRevoke => status == 'pending';

  InvitationTab get tab => switch (status) {
    'pending' => InvitationTab.pending,
    'accepted' => InvitationTab.accepted,
    'expired' => InvitationTab.expired,
    _ => InvitationTab.closed,
  };

  factory KomendantInvitation.fromJson(Map<String, dynamic> j) =>
      KomendantInvitation(
        id: _int(j['id']),
        status: (j['status'] as String?) ?? '',
        firstName: j['first_name'] as String?,
        lastName: j['last_name'] as String?,
        email: j['email'] as String?,
        expiresAt: _date(j['expires_at']),
        sendCount: _int(j['send_count']),
        lastSentAt: _date(j['last_sent_at']),
        acceptedAt: _date(j['accepted_at']),
        revokedAt: _date(j['revoked_at']),
        createdAt: _date(j['created_at']),
      );
}

/// Result of DELETE /v1/komendant/residents/{userId}.
@immutable
class ResidentRemoval {
  const ResidentRemoval({
    this.revokedRows = 0,
    this.cancelledSubscriptions = 0,
  });

  final int revokedRows;
  final int cancelledSubscriptions;

  factory ResidentRemoval.fromJson(Map<String, dynamic> j) => ResidentRemoval(
    revokedRows: _int(j['revoked_rows']),
    cancelledSubscriptions: _int(j['cancelled_subscriptions']),
  );
}
