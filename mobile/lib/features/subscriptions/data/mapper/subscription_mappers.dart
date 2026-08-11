import '../../domain/entity/subscription_entities.dart';
import '../dto/subscription_dto.dart';

DateTime? _date(String? value) =>
    value == null ? null : DateTime.tryParse(value);

Subscription subscriptionDtoToEntity(SubscriptionDto d) => Subscription(
  id: d.id,
  tier: d.tier ?? 'main',
  status: d.status ?? 'active',
  startsAt: _date(d.startsAt),
  endsAt: _date(d.endsAt),
  daysRemaining: d.daysRemaining,
  autoRenew: d.autoRenew ?? false,
  deviceId: d.deviceId,
  termDays: d.termDays,
);
