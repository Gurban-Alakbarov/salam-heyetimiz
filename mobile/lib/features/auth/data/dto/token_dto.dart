import 'package:freezed_annotation/freezed_annotation.dart';

import 'user_dto.dart';

part 'token_dto.freezed.dart';
part 'token_dto.g.dart';

/// Token bundle returned by verify-email (inside the unified envelope `data`)
/// and by refresh (legacy — top-level, no envelope). Same shape both times.
@freezed
abstract class TokenDto with _$TokenDto {
  const factory TokenDto({
    required String accessToken,
    required String refreshToken,
    String? tokenType,
    int? expiresIn,
    int? refreshExpiresIn,
    UserDto? user,
  }) = _TokenDto;

  factory TokenDto.fromJson(Map<String, dynamic> json) =>
      _$TokenDtoFromJson(json);
}
