import 'package:freezed_annotation/freezed_annotation.dart';

part 'device_entities.freezed.dart';

/// Display-only online threshold. Command gating is ALWAYS server-side
/// (`canOpen`); `lastOnlineAt` only drives a presentational badge.
const Duration kOnlineThreshold = Duration(minutes: 5);

@freezed
abstract class Device with _$Device {
  const Device._();

  const factory Device({
    required int id,
    required String label,
    String? serial,
    String? imageUrl,
    String? address,
    @Default('unknown') String status,
    @Default('user') String role,
    @Default(false) bool canOpen,
    @Default('none') String suspensionReason,
    double? latitude,
    double? longitude,
    // GEOFENCE-3 — when true the app must attach the caller's GPS to /open (the
    // backend rejects with location_required otherwise). Radius is informational;
    // the backend holds + enforces it.
    @Default(false) bool geofenceEnabled,
    int? geofenceRadiusM,
    DateTime? lastOnlineAt,
    DeviceModel? model,
    int? cooldownSecondsRemaining,
    DeviceSubscription? subscription,
  }) = _Device;

  bool get isActive => status == 'active';
  bool get isOwner => role == 'owner';

  /// Whether the barrier has coordinates — gates the resident "Directions" action.
  bool get hasLocation => latitude != null && longitude != null;

  /// Presentational online flag (display only — not for gating).
  bool isOnlineAt(DateTime now) =>
      lastOnlineAt != null && now.difference(lastOnlineAt!) <= kOnlineThreshold;
}

@freezed
abstract class DeviceModel with _$DeviceModel {
  const factory DeviceModel({String? vendor, String? modelCode}) = _DeviceModel;
}

@freezed
abstract class DeviceSubscription with _$DeviceSubscription {
  const factory DeviceSubscription({
    int? id,
    String? tier,
    String? status,
    DateTime? endsAt,
    int? daysRemaining,
    @Default(false) bool autoRenew,
  }) = _DeviceSubscription;
}

/// A page of devices + the opaque cursor for the next page.
@freezed
abstract class DevicePage with _$DevicePage {
  const factory DevicePage({
    required List<Device> devices,
    String? nextCursor,
    @Default(false) bool hasMore,
  }) = _DevicePage;
}
