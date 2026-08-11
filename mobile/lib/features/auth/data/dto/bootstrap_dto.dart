import 'package:freezed_annotation/freezed_annotation.dart';

import 'config_dto.dart';

part 'bootstrap_dto.freezed.dart';
part 'bootstrap_dto.g.dart';

/// Guest bootstrap `data` (GET /v1/bootstrap). Unknown keys are ignored.
@freezed
abstract class BootstrapDto with _$BootstrapDto {
  const factory BootstrapDto({
    @Default(AppConfigDto()) AppConfigDto app,
    @Default(OtpConfigDto()) OtpConfigDto otp,
    @Default(FeatureFlagsDto()) FeatureFlagsDto featureFlags,
    PublicSettingsDto? publicSettings,
    SupportDto? support,
  }) = _BootstrapDto;

  factory BootstrapDto.fromJson(Map<String, dynamic> json) =>
      _$BootstrapDtoFromJson(json);
}
