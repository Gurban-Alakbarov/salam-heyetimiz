import 'package:freezed_annotation/freezed_annotation.dart';

part 'config_dto.freezed.dart';
part 'config_dto.g.dart';

/// `app` block — maintenance + version enforcement (bootstrap and /v1/me).
@freezed
abstract class AppConfigDto with _$AppConfigDto {
  const factory AppConfigDto({
    @Default(false) bool maintenanceMode,
    String? minVersion,
    String? latestVersion,
    @Default(false) bool forceUpdate,
  }) = _AppConfigDto;

  factory AppConfigDto.fromJson(Map<String, dynamic> json) =>
      _$AppConfigDtoFromJson(json);
}

/// `otp` block — OTP length / lifetime / resend cooldown (bootstrap).
@freezed
abstract class OtpConfigDto with _$OtpConfigDto {
  const factory OtpConfigDto({
    @Default(6) int length,
    @Default(120) int ttlSeconds,
    @Default(30) int resendSeconds,
  }) = _OtpConfigDto;

  factory OtpConfigDto.fromJson(Map<String, dynamic> json) =>
      _$OtpConfigDtoFromJson(json);
}

/// `feature_flags` block (boolean map; only the keys we consume are typed).
@freezed
abstract class FeatureFlagsDto with _$FeatureFlagsDto {
  const factory FeatureFlagsDto({
    @Default(true) bool emailOtp,
    @Default(false) bool smsLogin,
    @Default(true) bool registration,
  }) = _FeatureFlagsDto;

  factory FeatureFlagsDto.fromJson(Map<String, dynamic> json) =>
      _$FeatureFlagsDtoFromJson(json);
}

/// `public_settings` block (brand etc.) — guest bootstrap.
@freezed
abstract class PublicSettingsDto with _$PublicSettingsDto {
  const factory PublicSettingsDto({
    String? brand,
    String? defaultLocale,
    String? defaultTimezone,
    String? currency,
    String? logoUrl,
  }) = _PublicSettingsDto;

  factory PublicSettingsDto.fromJson(Map<String, dynamic> json) =>
      _$PublicSettingsDtoFromJson(json);
}

/// `support` block.
@freezed
abstract class SupportDto with _$SupportDto {
  const factory SupportDto({String? email, String? phone}) = _SupportDto;

  factory SupportDto.fromJson(Map<String, dynamic> json) =>
      _$SupportDtoFromJson(json);
}
