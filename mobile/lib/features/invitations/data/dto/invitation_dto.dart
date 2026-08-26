import 'package:freezed_annotation/freezed_annotation.dart';

part 'invitation_dto.freezed.dart';
part 'invitation_dto.g.dart';

/// Wire shape for one visitor link as its owner sees it (GET /v1/visitor-links →
/// VisitorLinkResource). camelCase fields map to the snake_case JSON via the
/// project-wide json_serializable `field_rename: snake`. DTOs stay in the data layer.
///
/// `firstUsedAt` is only present when the backend eager-loads it (the "my invitations"
/// listing); it is null otherwise — never fabricated (it comes from visitor_link_usages).
@freezed
abstract class InvitationDto with _$InvitationDto {
  const factory InvitationDto({
    required int id,
    String? visitorName,
    String? purpose,
    String? accessType,
    String? status,
    String? expiresAt,
    int? maxUsage,
    int? usageCount,
    String? firstUsedAt,
    String? lastUsedAt,
    String? revokedAt,
    String? createdAt,
  }) = _InvitationDto;

  factory InvitationDto.fromJson(Map<String, dynamic> json) =>
      _$InvitationDtoFromJson(json);
}
