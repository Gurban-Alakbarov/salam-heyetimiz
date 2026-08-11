import 'package:freezed_annotation/freezed_annotation.dart';

part 'user_dto.freezed.dart';
part 'user_dto.g.dart';

/// Wire shape of the backend `UserSelf` object. DTOs never leave the data layer
/// (Constitution §1.6); the mapper converts to a domain entity.
@freezed
abstract class UserDto with _$UserDto {
  const factory UserDto({
    required int id,
    required String phone,
    String? fullName,
    String? email,
    String? emailVerifiedAt,
    String? preferredLanguage,
    String? status,
    String? createdAt,
    String? lastLoginAt,
    bool? hasActiveSubscription,
  }) = _UserDto;

  factory UserDto.fromJson(Map<String, dynamic> json) =>
      _$UserDtoFromJson(json);
}
