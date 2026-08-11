// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'notification_dto.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_NotificationDto _$NotificationDtoFromJson(Map<String, dynamic> json) =>
    _NotificationDto(
      id: (json['id'] as num).toInt(),
      templateKey: json['template_key'] as String?,
      channel: json['channel'] as String?,
      payload: json['payload'] as Map<String, dynamic>?,
      status: json['status'] as String?,
      sentAt: json['sent_at'] as String?,
      readAt: json['read_at'] as String?,
      createdAt: json['created_at'] as String?,
    );

Map<String, dynamic> _$NotificationDtoToJson(_NotificationDto instance) =>
    <String, dynamic>{
      'id': instance.id,
      'template_key': instance.templateKey,
      'channel': instance.channel,
      'payload': instance.payload,
      'status': instance.status,
      'sent_at': instance.sentAt,
      'read_at': instance.readAt,
      'created_at': instance.createdAt,
    };
