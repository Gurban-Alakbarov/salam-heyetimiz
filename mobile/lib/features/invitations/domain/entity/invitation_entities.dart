import 'package:freezed_annotation/freezed_annotation.dart';

part 'invitation_entities.freezed.dart';

/// A visitor link the resident created ("Dəvətlərim"). `status` is the SERVER-derived value
/// (active | used_up | expired | revoked) — the client never re-derives it. All timing is
/// display-only. Recipient email/phone are intentionally absent: the visitor-link model stores
/// only an optional [visitorName], so nothing is invented here.
@freezed
abstract class Invitation with _$Invitation {
  const Invitation._();

  const factory Invitation({
    required int id,
    String? visitorName,
    String? purpose,
    @Default('time_limited') String accessType,
    @Default('active') String status,
    DateTime? expiresAt,
    int? maxUsage,
    @Default(0) int usageCount,
    DateTime? firstUsedAt,
    DateTime? lastUsedAt,
    DateTime? revokedAt,
    DateTime? createdAt,
  }) = _Invitation;

  bool get isActive => status == 'active';
  bool get isUsedUp => status == 'used_up';
  bool get isExpired => status == 'expired';
  bool get isRevoked => status == 'revoked';
  bool get hasBeenUsed => usageCount > 0;

  /// Remaining time until expiry, meaningful only while active. Null when there is no expiry
  /// or it has already elapsed.
  Duration? remainingAt(DateTime now) {
    final e = expiresAt;
    if (e == null) return null;
    final d = e.difference(now);
    return d.isNegative ? null : d;
  }
}
