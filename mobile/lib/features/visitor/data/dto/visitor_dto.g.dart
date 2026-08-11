// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'visitor_dto.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_CreateVisitorLinkResponseDto _$CreateVisitorLinkResponseDtoFromJson(
  Map<String, dynamic> json,
) => _CreateVisitorLinkResponseDto(
  link: json['link'] == null
      ? null
      : VisitorLinkDto.fromJson(json['link'] as Map<String, dynamic>),
  token: json['token'] as String?,
  url: json['url'] as String?,
);

Map<String, dynamic> _$CreateVisitorLinkResponseDtoToJson(
  _CreateVisitorLinkResponseDto instance,
) => <String, dynamic>{
  'link': instance.link?.toJson(),
  'token': instance.token,
  'url': instance.url,
};

_VisitorLinkDto _$VisitorLinkDtoFromJson(Map<String, dynamic> json) =>
    _VisitorLinkDto(
      id: (json['id'] as num?)?.toInt(),
      visitorName: json['visitor_name'] as String?,
      purpose: json['purpose'] as String?,
      accessType: json['access_type'] as String?,
      status: json['status'] as String?,
      expiresAt: json['expires_at'] as String?,
    );

Map<String, dynamic> _$VisitorLinkDtoToJson(_VisitorLinkDto instance) =>
    <String, dynamic>{
      'id': instance.id,
      'visitor_name': instance.visitorName,
      'purpose': instance.purpose,
      'access_type': instance.accessType,
      'status': instance.status,
      'expires_at': instance.expiresAt,
    };
