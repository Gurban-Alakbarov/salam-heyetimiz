// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'command_dto.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_OpenAckDto _$OpenAckDtoFromJson(Map<String, dynamic> json) => _OpenAckDto(
  commandId: (json['command_id'] as num).toInt(),
  state: json['state'] as String?,
  expectedCompletionMs: (json['expected_completion_ms'] as num?)?.toInt(),
  driverConfirmsActuation: json['driver_confirms_actuation'] as bool?,
  websocketChannel: json['websocket_channel'] as String?,
);

Map<String, dynamic> _$OpenAckDtoToJson(_OpenAckDto instance) =>
    <String, dynamic>{
      'command_id': instance.commandId,
      'state': instance.state,
      'expected_completion_ms': instance.expectedCompletionMs,
      'driver_confirms_actuation': instance.driverConfirmsActuation,
      'websocket_channel': instance.websocketChannel,
    };

_CommandStatusDto _$CommandStatusDtoFromJson(Map<String, dynamic> json) =>
    _CommandStatusDto(
      id: (json['id'] as num).toInt(),
      deviceId: (json['device_id'] as num?)?.toInt(),
      state: json['state'] as String?,
      failureReason: json['failure_reason'] as String?,
      driver: json['driver'] as String?,
      requestedAt: json['requested_at'] as String?,
      dispatchedAt: json['dispatched_at'] as String?,
      completedAt: json['completed_at'] as String?,
      latencyMs: (json['latency_ms'] as num?)?.toInt(),
      attempts: (json['attempts'] as num?)?.toInt(),
    );

Map<String, dynamic> _$CommandStatusDtoToJson(_CommandStatusDto instance) =>
    <String, dynamic>{
      'id': instance.id,
      'device_id': instance.deviceId,
      'state': instance.state,
      'failure_reason': instance.failureReason,
      'driver': instance.driver,
      'requested_at': instance.requestedAt,
      'dispatched_at': instance.dispatchedAt,
      'completed_at': instance.completedAt,
      'latency_ms': instance.latencyMs,
      'attempts': instance.attempts,
    };
