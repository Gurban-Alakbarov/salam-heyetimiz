import 'package:freezed_annotation/freezed_annotation.dart';

part 'visitor_dto.freezed.dart';
part 'visitor_dto.g.dart';

/// Wire shape of POST /v1/devices/{id}/visitor-links — `{ link, token, url }`.
/// The token + url are the shareable secret, returned exactly once at creation.
@freezed
abstract class CreateVisitorLinkResponseDto with _$CreateVisitorLinkResponseDto {
  const factory CreateVisitorLinkResponseDto({
    VisitorLinkDto? link,
    String? token,
    String? url,
  }) = _CreateVisitorLinkResponseDto;

  factory CreateVisitorLinkResponseDto.fromJson(Map<String, dynamic> json) =>
      _$CreateVisitorLinkResponseDtoFromJson(json);
}

@freezed
abstract class VisitorLinkDto with _$VisitorLinkDto {
  const factory VisitorLinkDto({
    int? id,
    String? visitorName,
    String? purpose,
    String? accessType,
    String? status,
    String? expiresAt,
  }) = _VisitorLinkDto;

  factory VisitorLinkDto.fromJson(Map<String, dynamic> json) =>
      _$VisitorLinkDtoFromJson(json);
}
