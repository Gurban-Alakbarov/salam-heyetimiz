import 'package:freezed_annotation/freezed_annotation.dart';

part 'device_dto.freezed.dart';
part 'device_dto.g.dart';

/// Wire shape for a device — covers both the list item and the detail superset
/// (GET /v1/devices and GET /v1/devices/{id}). DTOs stay in the data layer.
@freezed
abstract class DeviceDto with _$DeviceDto {
  const factory DeviceDto({
    required int id,
    String? label,
    String? serial,
    String? imageUrl,
    String? address,
    String? status,
    String? role,
    bool? canOpen,
    String? suspensionReason,
    double? latitude,
    double? longitude,
    String? lastOnlineAt,
    DeviceModelDto? deviceModel,
    int? cooldownSecondsRemaining,
    SubscriptionBriefDto? subscription,
  }) = _DeviceDto;

  factory DeviceDto.fromJson(Map<String, dynamic> json) =>
      _$DeviceDtoFromJson(json);
}

@freezed
abstract class DeviceModelDto with _$DeviceModelDto {
  const factory DeviceModelDto({String? vendor, String? modelCode}) =
      _DeviceModelDto;

  factory DeviceModelDto.fromJson(Map<String, dynamic> json) =>
      _$DeviceModelDtoFromJson(json);
}

@freezed
abstract class SubscriptionBriefDto with _$SubscriptionBriefDto {
  const factory SubscriptionBriefDto({
    int? id,
    String? tier,
    String? status,
    String? endsAt,
    int? daysRemaining,
    bool? autoRenew,
  }) = _SubscriptionBriefDto;

  factory SubscriptionBriefDto.fromJson(Map<String, dynamic> json) =>
      _$SubscriptionBriefDtoFromJson(json);
}
