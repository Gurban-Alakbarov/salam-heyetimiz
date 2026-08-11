// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'config_dto.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_AppConfigDto _$AppConfigDtoFromJson(Map<String, dynamic> json) =>
    _AppConfigDto(
      maintenanceMode: json['maintenance_mode'] as bool? ?? false,
      minVersion: json['min_version'] as String?,
      latestVersion: json['latest_version'] as String?,
      forceUpdate: json['force_update'] as bool? ?? false,
    );

Map<String, dynamic> _$AppConfigDtoToJson(_AppConfigDto instance) =>
    <String, dynamic>{
      'maintenance_mode': instance.maintenanceMode,
      'min_version': instance.minVersion,
      'latest_version': instance.latestVersion,
      'force_update': instance.forceUpdate,
    };

_OtpConfigDto _$OtpConfigDtoFromJson(Map<String, dynamic> json) =>
    _OtpConfigDto(
      length: (json['length'] as num?)?.toInt() ?? 6,
      ttlSeconds: (json['ttl_seconds'] as num?)?.toInt() ?? 120,
      resendSeconds: (json['resend_seconds'] as num?)?.toInt() ?? 30,
    );

Map<String, dynamic> _$OtpConfigDtoToJson(_OtpConfigDto instance) =>
    <String, dynamic>{
      'length': instance.length,
      'ttl_seconds': instance.ttlSeconds,
      'resend_seconds': instance.resendSeconds,
    };

_FeatureFlagsDto _$FeatureFlagsDtoFromJson(Map<String, dynamic> json) =>
    _FeatureFlagsDto(
      emailOtp: json['email_otp'] as bool? ?? true,
      smsLogin: json['sms_login'] as bool? ?? false,
      registration: json['registration'] as bool? ?? true,
    );

Map<String, dynamic> _$FeatureFlagsDtoToJson(_FeatureFlagsDto instance) =>
    <String, dynamic>{
      'email_otp': instance.emailOtp,
      'sms_login': instance.smsLogin,
      'registration': instance.registration,
    };

_PublicSettingsDto _$PublicSettingsDtoFromJson(Map<String, dynamic> json) =>
    _PublicSettingsDto(
      brand: json['brand'] as String?,
      defaultLocale: json['default_locale'] as String?,
      defaultTimezone: json['default_timezone'] as String?,
      currency: json['currency'] as String?,
      logoUrl: json['logo_url'] as String?,
    );

Map<String, dynamic> _$PublicSettingsDtoToJson(_PublicSettingsDto instance) =>
    <String, dynamic>{
      'brand': instance.brand,
      'default_locale': instance.defaultLocale,
      'default_timezone': instance.defaultTimezone,
      'currency': instance.currency,
      'logo_url': instance.logoUrl,
    };

_SupportDto _$SupportDtoFromJson(Map<String, dynamic> json) => _SupportDto(
  email: json['email'] as String?,
  phone: json['phone'] as String?,
);

Map<String, dynamic> _$SupportDtoToJson(_SupportDto instance) =>
    <String, dynamic>{'email': instance.email, 'phone': instance.phone};
