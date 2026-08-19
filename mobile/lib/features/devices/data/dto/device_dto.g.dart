// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'device_dto.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_DeviceDto _$DeviceDtoFromJson(Map<String, dynamic> json) => _DeviceDto(
  id: (json['id'] as num).toInt(),
  label: json['label'] as String?,
  serial: json['serial'] as String?,
  imageUrl: json['image_url'] as String?,
  address: json['address'] as String?,
  status: json['status'] as String?,
  role: json['role'] as String?,
  canOpen: json['can_open'] as bool?,
  suspensionReason: json['suspension_reason'] as String?,
  latitude: (json['latitude'] as num?)?.toDouble(),
  longitude: (json['longitude'] as num?)?.toDouble(),
  geofenceEnabled: json['geofence_enabled'] as bool?,
  geofenceRadiusM: (json['geofence_radius_m'] as num?)?.toInt(),
  lastOnlineAt: json['last_online_at'] as String?,
  deviceModel: json['device_model'] == null
      ? null
      : DeviceModelDto.fromJson(json['device_model'] as Map<String, dynamic>),
  cooldownSecondsRemaining: (json['cooldown_seconds_remaining'] as num?)
      ?.toInt(),
  subscription: json['subscription'] == null
      ? null
      : SubscriptionBriefDto.fromJson(
          json['subscription'] as Map<String, dynamic>,
        ),
);

Map<String, dynamic> _$DeviceDtoToJson(_DeviceDto instance) =>
    <String, dynamic>{
      'id': instance.id,
      'label': instance.label,
      'serial': instance.serial,
      'image_url': instance.imageUrl,
      'address': instance.address,
      'status': instance.status,
      'role': instance.role,
      'can_open': instance.canOpen,
      'suspension_reason': instance.suspensionReason,
      'latitude': instance.latitude,
      'longitude': instance.longitude,
      'geofence_enabled': instance.geofenceEnabled,
      'geofence_radius_m': instance.geofenceRadiusM,
      'last_online_at': instance.lastOnlineAt,
      'device_model': instance.deviceModel?.toJson(),
      'cooldown_seconds_remaining': instance.cooldownSecondsRemaining,
      'subscription': instance.subscription?.toJson(),
    };

_DeviceModelDto _$DeviceModelDtoFromJson(Map<String, dynamic> json) =>
    _DeviceModelDto(
      vendor: json['vendor'] as String?,
      modelCode: json['model_code'] as String?,
    );

Map<String, dynamic> _$DeviceModelDtoToJson(_DeviceModelDto instance) =>
    <String, dynamic>{
      'vendor': instance.vendor,
      'model_code': instance.modelCode,
    };

_SubscriptionBriefDto _$SubscriptionBriefDtoFromJson(
  Map<String, dynamic> json,
) => _SubscriptionBriefDto(
  id: (json['id'] as num?)?.toInt(),
  tier: json['tier'] as String?,
  status: json['status'] as String?,
  endsAt: json['ends_at'] as String?,
  daysRemaining: (json['days_remaining'] as num?)?.toInt(),
  autoRenew: json['auto_renew'] as bool?,
);

Map<String, dynamic> _$SubscriptionBriefDtoToJson(
  _SubscriptionBriefDto instance,
) => <String, dynamic>{
  'id': instance.id,
  'tier': instance.tier,
  'status': instance.status,
  'ends_at': instance.endsAt,
  'days_remaining': instance.daysRemaining,
  'auto_renew': instance.autoRenew,
};
