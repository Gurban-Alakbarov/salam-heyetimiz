import 'package:freezed_annotation/freezed_annotation.dart';

part 'command_dto.freezed.dart';
part 'command_dto.g.dart';

/// 202 response of POST /v1/devices/{id}/open.
@freezed
abstract class OpenAckDto with _$OpenAckDto {
  const factory OpenAckDto({
    required int commandId,
    String? state,
    int? expectedCompletionMs,
    bool? driverConfirmsActuation,
    String? websocketChannel,
  }) = _OpenAckDto;

  factory OpenAckDto.fromJson(Map<String, dynamic> json) =>
      _$OpenAckDtoFromJson(json);
}

/// GET /v1/commands/{id} — the polled command status.
@freezed
abstract class CommandStatusDto with _$CommandStatusDto {
  const factory CommandStatusDto({
    required int id,
    int? deviceId,
    String? state,
    String? failureReason,
    String? driver,
    String? requestedAt,
    String? dispatchedAt,
    String? completedAt,
    int? latencyMs,
    int? attempts,
  }) = _CommandStatusDto;

  factory CommandStatusDto.fromJson(Map<String, dynamic> json) =>
      _$CommandStatusDtoFromJson(json);
}
