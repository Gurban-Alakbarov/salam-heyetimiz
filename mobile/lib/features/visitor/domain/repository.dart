import '../../../core/error/failure.dart';
import 'entity/visitor_entities.dart';

/// Resident visitor-link operations. Creation is gated server-side by the same
/// open-permission check as opening the barrier (you can only share access you
/// hold), so a forbidden result surfaces as a [ForbiddenFailure].
abstract class VisitorRepository {
  Future<Result<CreatedVisitorLink>> create(
    int deviceId, {
    required VisitorAccessType accessType,
    int? durationMinutes,
    String? visitorName,
    VisitorPurpose? purpose,
  });
}
