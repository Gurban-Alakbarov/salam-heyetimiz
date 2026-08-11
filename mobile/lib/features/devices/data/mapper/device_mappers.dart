import '../../domain/entity/device_entities.dart';
import '../dto/device_dto.dart';

DateTime? _date(String? value) =>
    value == null ? null : DateTime.tryParse(value);

Device deviceDtoToEntity(DeviceDto d) => Device(
  id: d.id,
  label: d.label ?? d.serial ?? 'Device #${d.id}',
  serial: d.serial,
  imageUrl: d.imageUrl,
  address: d.address,
  status: d.status ?? 'unknown',
  role: d.role ?? 'user',
  canOpen: d.canOpen ?? false,
  suspensionReason: d.suspensionReason ?? 'none',
  latitude: d.latitude,
  longitude: d.longitude,
  lastOnlineAt: _date(d.lastOnlineAt),
  cooldownSecondsRemaining: d.cooldownSecondsRemaining,
  model: d.deviceModel == null
      ? null
      : DeviceModel(
          vendor: d.deviceModel!.vendor,
          modelCode: d.deviceModel!.modelCode,
        ),
  subscription: d.subscription == null
      ? null
      : DeviceSubscription(
          id: d.subscription!.id,
          tier: d.subscription!.tier,
          status: d.subscription!.status,
          endsAt: _date(d.subscription!.endsAt),
          daysRemaining: d.subscription!.daysRemaining,
          autoRenew: d.subscription!.autoRenew ?? false,
        ),
);
