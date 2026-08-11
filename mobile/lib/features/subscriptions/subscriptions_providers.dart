import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import 'data/datasource/subscription_remote_datasource.dart';
import 'data/repository_impl.dart';
import 'domain/entity/subscription_entities.dart';
import 'domain/repository.dart';

/// Subscriptions DI — granular providers mirroring the devices feature
/// (Constitution §5; no god-provider).

final subscriptionRepositoryProvider = Provider<SubscriptionRepository>(
  (ref) => SubscriptionRepositoryImpl(
    SubscriptionRemoteDataSource(ref.watch(apiClientProvider)),
  ),
);

/// The resident's active subscriptions (GET /v1/subscriptions?status=active).
/// Pull-to-refresh via `ref.refresh(activeSubscriptionsProvider.future)`.
final activeSubscriptionsProvider =
    FutureProvider.autoDispose<List<Subscription>>((ref) async {
      final result = await ref.watch(subscriptionRepositoryProvider).listActive();
      return result.fold((failure) => throw failure, (list) => list);
    });
