// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'user_dto.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_UserDto _$UserDtoFromJson(Map<String, dynamic> json) => _UserDto(
  id: (json['id'] as num).toInt(),
  phone: json['phone'] as String,
  fullName: json['full_name'] as String?,
  email: json['email'] as String?,
  emailVerifiedAt: json['email_verified_at'] as String?,
  preferredLanguage: json['preferred_language'] as String?,
  status: json['status'] as String?,
  createdAt: json['created_at'] as String?,
  lastLoginAt: json['last_login_at'] as String?,
  hasActiveSubscription: json['has_active_subscription'] as bool?,
);

Map<String, dynamic> _$UserDtoToJson(_UserDto instance) => <String, dynamic>{
  'id': instance.id,
  'phone': instance.phone,
  'full_name': instance.fullName,
  'email': instance.email,
  'email_verified_at': instance.emailVerifiedAt,
  'preferred_language': instance.preferredLanguage,
  'status': instance.status,
  'created_at': instance.createdAt,
  'last_login_at': instance.lastLoginAt,
  'has_active_subscription': instance.hasActiveSubscription,
};
