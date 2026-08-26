// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'invitation_dto.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_InvitationDto _$InvitationDtoFromJson(Map<String, dynamic> json) =>
    _InvitationDto(
      id: (json['id'] as num).toInt(),
      visitorName: json['visitor_name'] as String?,
      purpose: json['purpose'] as String?,
      accessType: json['access_type'] as String?,
      status: json['status'] as String?,
      expiresAt: json['expires_at'] as String?,
      maxUsage: (json['max_usage'] as num?)?.toInt(),
      usageCount: (json['usage_count'] as num?)?.toInt(),
      firstUsedAt: json['first_used_at'] as String?,
      lastUsedAt: json['last_used_at'] as String?,
      revokedAt: json['revoked_at'] as String?,
      createdAt: json['created_at'] as String?,
    );

Map<String, dynamic> _$InvitationDtoToJson(_InvitationDto instance) =>
    <String, dynamic>{
      'id': instance.id,
      'visitor_name': instance.visitorName,
      'purpose': instance.purpose,
      'access_type': instance.accessType,
      'status': instance.status,
      'expires_at': instance.expiresAt,
      'max_usage': instance.maxUsage,
      'usage_count': instance.usageCount,
      'first_used_at': instance.firstUsedAt,
      'last_used_at': instance.lastUsedAt,
      'revoked_at': instance.revokedAt,
      'created_at': instance.createdAt,
    };
