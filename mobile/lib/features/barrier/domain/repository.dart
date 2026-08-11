import '../../../core/error/failure.dart';
import 'entity/barrier_entities.dart';

/// Barrier domain contract (open → poll → optional feedback).
abstract class BarrierRepository {
  Future<Result<OpenAck>> open(int deviceId);

  /// Close command through the same pipeline (Phase 5) — polled + confirmed identically to open.
  Future<Result<OpenAck>> close(int deviceId);

  Future<Result<CommandStatus>> status(int commandId);

  Future<Result<void>> feedback(
    int commandId, {
    required bool gateMoved,
    String? comment,
  });
}
