import 'package:freezed_annotation/freezed_annotation.dart';

part 'subscription_entities.freezed.dart';

/// A resident's subscription for one barrier (GET /v1/subscriptions). One row per
/// (user, device) roster entry. `daysRemaining` is the AUTHORITATIVE remaining-day
/// value (server-computed); `startsAt`/`endsAt` drive only the client-side progress.
@freezed
abstract class Subscription with _$Subscription {
  const Subscription._();

  const factory Subscription({
    required int id,
    @Default('main') String tier,
    @Default('active') String status,
    DateTime? startsAt,
    DateTime? endsAt,
    int? daysRemaining,
    @Default(false) bool autoRenew,
    int? deviceId,
    int? termDays,
  }) = _Subscription;

  bool get isActive => status == 'active';

  /// Elapsed fraction of the term (0..1) from starts→ends, for a progress bar.
  /// Null when the dates are missing/degenerate. Client-side only — this never
  /// overrides the server-provided [daysRemaining].
  double? progressAt(DateTime now) {
    final start = startsAt;
    final end = endsAt;
    if (start == null || end == null) return null;
    final total = end.difference(start).inSeconds;
    if (total <= 0) return null;
    return (now.difference(start).inSeconds / total).clamp(0.0, 1.0);
  }
}
