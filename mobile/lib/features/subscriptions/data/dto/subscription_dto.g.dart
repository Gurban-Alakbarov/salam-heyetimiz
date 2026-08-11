// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'subscription_dto.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_SubscriptionDto _$SubscriptionDtoFromJson(Map<String, dynamic> json) =>
    _SubscriptionDto(
      id: (json['id'] as num).toInt(),
      tier: json['tier'] as String?,
      status: json['status'] as String?,
      startsAt: json['starts_at'] as String?,
      endsAt: json['ends_at'] as String?,
      daysRemaining: (json['days_remaining'] as num?)?.toInt(),
      autoRenew: json['auto_renew'] as bool?,
      deviceId: (json['device_id'] as num?)?.toInt(),
      userId: (json['user_id'] as num?)?.toInt(),
      termDays: (json['term_days'] as num?)?.toInt(),
      priceMinor: (json['price_minor'] as num?)?.toInt(),
      currency: json['currency'] as String?,
    );

Map<String, dynamic> _$SubscriptionDtoToJson(_SubscriptionDto instance) =>
    <String, dynamic>{
      'id': instance.id,
      'tier': instance.tier,
      'status': instance.status,
      'starts_at': instance.startsAt,
      'ends_at': instance.endsAt,
      'days_remaining': instance.daysRemaining,
      'auto_renew': instance.autoRenew,
      'device_id': instance.deviceId,
      'user_id': instance.userId,
      'term_days': instance.termDays,
      'price_minor': instance.priceMinor,
      'currency': instance.currency,
    };
