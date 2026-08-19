import '../../../core/error/failure.dart';
import '../../../core/location/location_service.dart';
import 'entity/barrier_entities.dart';

/// Barrier domain contract (open → poll → optional feedback).
abstract class BarrierRepository {
  /// GEOFENCE-3 — [fix] is attached to the request only for geofenced devices;
  /// null keeps the historical (non-geofence) body unchanged.
  Future<Result<OpenAck>> open(int deviceId, {GeoFix? fix});

  /// Close command through the same pipeline (Phase 5) — polled + confirmed identically to open.
  Future<Result<OpenAck>> close(int deviceId, {GeoFix? fix});

  Future<Result<CommandStatus>> status(int commandId);

  Future<Result<void>> feedback(
    int commandId, {
    required bool gateMoved,
    String? comment,
  });
}
