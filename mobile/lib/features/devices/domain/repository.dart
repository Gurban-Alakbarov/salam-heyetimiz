import '../../../core/error/failure.dart';
import 'entity/device_entities.dart';

/// Device domain contract. Returns [Result]; no exceptions cross the boundary.
abstract class DeviceRepository {
  Future<Result<DevicePage>> list({String? filter, String? cursor, int limit});

  Future<Result<Device>> detail(int id);
}
