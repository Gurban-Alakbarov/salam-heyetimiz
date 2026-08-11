// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'me_dto.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_MeDto _$MeDtoFromJson(Map<String, dynamic> json) => _MeDto(
  user: UserDto.fromJson(json['user'] as Map<String, dynamic>),
  app: json['app'] == null
      ? const AppConfigDto()
      : AppConfigDto.fromJson(json['app'] as Map<String, dynamic>),
  featureFlags: json['feature_flags'] == null
      ? const FeatureFlagsDto()
      : FeatureFlagsDto.fromJson(json['feature_flags'] as Map<String, dynamic>),
  registrationCompleted: json['registration_completed'] as bool? ?? false,
  emailVerified: json['email_verified'] as bool? ?? false,
  hasPassword: json['has_password'] as bool? ?? false,
  locale: json['locale'] as String?,
);

Map<String, dynamic> _$MeDtoToJson(_MeDto instance) => <String, dynamic>{
  'user': instance.user.toJson(),
  'app': instance.app.toJson(),
  'feature_flags': instance.featureFlags.toJson(),
  'registration_completed': instance.registrationCompleted,
  'email_verified': instance.emailVerified,
  'has_password': instance.hasPassword,
  'locale': instance.locale,
};
