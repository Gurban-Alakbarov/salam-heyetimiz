import 'package:freezed_annotation/freezed_annotation.dart';

part 'auth_entities.freezed.dart';

/// Domain user — what the UI consumes. No envelope/wire knowledge.
@freezed
abstract class UserEntity with _$UserEntity {
  const factory UserEntity({
    required int id,
    required String phone,
    String? fullName,
    String? email,
    @Default(false) bool emailVerified,
    @Default('az') String preferredLanguage,
    @Default('active') String status,
    @Default(false) bool hasActiveSubscription,
  }) = _UserEntity;
}

/// App gate status (maintenance + version enforcement).
@freezed
abstract class AppStatusEntity with _$AppStatusEntity {
  const factory AppStatusEntity({
    @Default(false) bool maintenanceMode,
    String? minVersion,
    String? latestVersion,
    @Default(false) bool forceUpdate,
  }) = _AppStatusEntity;
}

/// Guest bootstrap result that drives the splash gate + welcome screen.
@freezed
abstract class BootstrapEntity with _$BootstrapEntity {
  const factory BootstrapEntity({
    required AppStatusEntity app,
    @Default(6) int otpLength,
    @Default(120) int otpTtlSeconds,
    @Default(30) int otpResendSeconds,
    @Default(true) bool emailOtpEnabled,
    @Default(true) bool registrationEnabled,
    String? brand,
    String? supportEmail,
    String? supportPhone,
  }) = _BootstrapEntity;
}

/// OTP dispatch acknowledgement (register / login / resend `meta`).
@freezed
abstract class OtpDispatch with _$OtpDispatch {
  const factory OtpDispatch({
    @Default(120) int expiresInSeconds,
    @Default(30) int resendAvailableInSeconds,
  }) = _OtpDispatch;
}

/// Authenticated bootstrap (GET /v1/me) — user + current app gate status.
@freezed
abstract class MeEntity with _$MeEntity {
  const factory MeEntity({
    required UserEntity user,
    required AppStatusEntity app,
  }) = _MeEntity;
}
