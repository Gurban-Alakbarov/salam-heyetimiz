import 'package:freezed_annotation/freezed_annotation.dart';

part 'notification_dto.freezed.dart';
part 'notification_dto.g.dart';

/// Wire shape for an in-app notification (GET /v1/notifications → NotificationResource). camelCase maps
/// to snake_case JSON via the project-wide json_serializable `field_rename: snake`. `payload` carries the
/// rendered title/body + deep-link type/ids. DTOs stay in the data layer.
@freezed
abstract class NotificationDto with _$NotificationDto {
  const factory NotificationDto({
    required int id,
    String? templateKey,
    String? channel,
    Map<String, dynamic>? payload,
    String? status,
    String? sentAt,
    String? readAt,
    String? createdAt,
  }) = _NotificationDto;

  factory NotificationDto.fromJson(Map<String, dynamic> json) =>
      _$NotificationDtoFromJson(json);
}
