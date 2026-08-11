import '../../../../core/session/session_manager.dart';
import '../../domain/entity/auth_entities.dart';
import '../dto/bootstrap_dto.dart';
import '../dto/config_dto.dart';
import '../dto/me_dto.dart';
import '../dto/token_dto.dart';
import '../dto/user_dto.dart';

/// DTO → Entity mappers. The only bridge across the data/domain boundary; DTOs
/// stay inside `data/` (Constitution §1.6).

UserEntity userDtoToEntity(UserDto d) => UserEntity(
  id: d.id,
  phone: d.phone,
  fullName: d.fullName,
  email: d.email,
  emailVerified: d.emailVerifiedAt != null,
  preferredLanguage: d.preferredLanguage ?? 'az',
  status: d.status ?? 'active',
  hasActiveSubscription: d.hasActiveSubscription ?? false,
);

AppStatusEntity appConfigDtoToEntity(AppConfigDto d) => AppStatusEntity(
  maintenanceMode: d.maintenanceMode,
  minVersion: d.minVersion,
  latestVersion: d.latestVersion,
  forceUpdate: d.forceUpdate,
);

BootstrapEntity bootstrapDtoToEntity(BootstrapDto d) => BootstrapEntity(
  app: appConfigDtoToEntity(d.app),
  otpLength: d.otp.length,
  otpTtlSeconds: d.otp.ttlSeconds,
  otpResendSeconds: d.otp.resendSeconds,
  emailOtpEnabled: d.featureFlags.emailOtp,
  registrationEnabled: d.featureFlags.registration,
  brand: d.publicSettings?.brand,
  supportEmail: d.support?.email,
  supportPhone: d.support?.phone,
);

MeEntity meDtoToEntity(MeDto d) =>
    MeEntity(user: userDtoToEntity(d.user), app: appConfigDtoToEntity(d.app));

Session tokenDtoToSession(TokenDto d) =>
    Session(accessToken: d.accessToken, refreshToken: d.refreshToken);
