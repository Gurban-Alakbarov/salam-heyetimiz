import 'package:freezed_annotation/freezed_annotation.dart';

import 'config_dto.dart';
import 'user_dto.dart';

part 'me_dto.freezed.dart';
part 'me_dto.g.dart';

/// Authenticated bootstrap `data` (GET /v1/me). Intentionally extensible — only
/// the keys the auth feature consumes are typed; unknown keys are ignored.
@freezed
abstract class MeDto with _$MeDto {
  const factory MeDto({
    required UserDto user,
    @Default(AppConfigDto()) AppConfigDto app,
    @Default(FeatureFlagsDto()) FeatureFlagsDto featureFlags,
    @Default(false) bool registrationCompleted,
    @Default(false) bool emailVerified,
    @Default(false) bool hasPassword,
    String? locale,
  }) = _MeDto;

  factory MeDto.fromJson(Map<String, dynamic> json) => _$MeDtoFromJson(json);
}
