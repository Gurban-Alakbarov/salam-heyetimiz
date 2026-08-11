// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'bootstrap_dto.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_BootstrapDto _$BootstrapDtoFromJson(Map<String, dynamic> json) =>
    _BootstrapDto(
      app: json['app'] == null
          ? const AppConfigDto()
          : AppConfigDto.fromJson(json['app'] as Map<String, dynamic>),
      otp: json['otp'] == null
          ? const OtpConfigDto()
          : OtpConfigDto.fromJson(json['otp'] as Map<String, dynamic>),
      featureFlags: json['feature_flags'] == null
          ? const FeatureFlagsDto()
          : FeatureFlagsDto.fromJson(
              json['feature_flags'] as Map<String, dynamic>,
            ),
      publicSettings: json['public_settings'] == null
          ? null
          : PublicSettingsDto.fromJson(
              json['public_settings'] as Map<String, dynamic>,
            ),
      support: json['support'] == null
          ? null
          : SupportDto.fromJson(json['support'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$BootstrapDtoToJson(_BootstrapDto instance) =>
    <String, dynamic>{
      'app': instance.app.toJson(),
      'otp': instance.otp.toJson(),
      'feature_flags': instance.featureFlags.toJson(),
      'public_settings': instance.publicSettings?.toJson(),
      'support': instance.support?.toJson(),
    };
