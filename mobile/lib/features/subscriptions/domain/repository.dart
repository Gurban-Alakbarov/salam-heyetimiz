import '../../../core/error/failure.dart';
import 'entity/subscription_entities.dart';

/// Subscription domain contract. Returns [Result]; no exceptions cross the boundary.
abstract class SubscriptionRepository {
  /// The caller's active subscriptions (GET /v1/subscriptions?status=active).
  Future<Result<List<Subscription>>> listActive();
}
