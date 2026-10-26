import 'package:freezed_annotation/freezed_annotation.dart';

part 'device_entities.freezed.dart';

/// Display-only online threshold. Command gating is ALWAYS server-side
/// (`canOpen`); `lastOnlineAt` only drives a presentational badge.
/// A2 — only a fallback for a server that sends no `online` flag; it matches the
/// server's shared window (domain.devices.offline_threshold_minutes = 15).
const Duration kOnlineThreshold = Duration(minutes: 15);

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
    // A4 — a family member may open but never share access as a visitor link (BR-19); the server says
    // whether this caller may create visitor links on this device.
    @Default(false) bool viaFamily,
    @Default(false) bool canShareVisitorLink,
    double? latitude,
    double? longitude,
    // GEOFENCE-3 — when true the app must attach the caller's GPS to /open (the
    // backend rejects with location_required otherwise). Radius is informational;
    // the backend holds + enforces it.
    @Default(false) bool geofenceEnabled,
    int? geofenceRadiusM,
    DateTime? lastOnlineAt,
    // A2 — the server's online flag; null when an older server did not send it.
    bool? online,
    DeviceModel? model,
    int? cooldownSecondsRemaining,
    DeviceSubscription? subscription,
  }) = _Device;

  bool get isActive => status == 'active';
  bool get isOwner => role == 'owner';

  /// Whether the barrier has coordinates — gates the resident "Directions" action.
  bool get hasLocation => latitude != null && longitude != null;

  /// Presentational online flag (display only — not for gating). A2: the server's
  /// flag when present, so the app and the admin panel always agree.
  bool isOnlineAt(DateTime now) =>
      online ??
      (lastOnlineAt != null && now.difference(lastOnlineAt!) <= kOnlineThreshold);
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
