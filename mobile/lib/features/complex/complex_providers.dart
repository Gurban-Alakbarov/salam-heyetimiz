import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import '../../core/error/failure.dart';
import '../../core/error/retry_policy.dart';
import '../auth/session_roles_provider.dart';
import 'data/complex_repository.dart';
import 'data/invite_repository.dart';
import 'domain/complex_entities.dart';

/// Resident complex + invitation DI (B16). Read providers throw the typed [Failure] so screens render it
/// via `when(error:)`; definitive refusals are not retried (retryTransientFailures).
final inviteRepositoryProvider = Provider<InviteRepository>(
  (ref) => InviteRepository(ref.watch(apiClientProvider)),
);

final complexRepositoryProvider = Provider<ComplexRepository>(
  (ref) => ComplexRepository(ref.watch(apiClientProvider)),
);

Future<T> _unwrap<T>(Future<Result<T>> call) async =>
    (await call).fold((f) => throw f, (v) => v);

/// Whether an invitation token is waiting on this device (PendingInviteStore, B13). The token itself never
/// leaves the store through a provider value.
final hasPendingInviteProvider = FutureProvider.autoDispose<bool>((ref) async {
  try {
    return await ref.watch(pendingInviteStoreProvider).read() != null;
  } catch (_) {
    return false; // unreadable store (no keystore / tests) → no entry shown
  }
});

final myComplexesProvider = FutureProvider.autoDispose<List<ResidentComplex>>(
  (ref) => _unwrap(ref.watch(complexRepositoryProvider).myComplexes()),
  retry: retryTransientFailures,
);

final complexProvider = FutureProvider.autoDispose.family<ResidentComplex, int>(
  (ref, id) => _unwrap(ref.watch(complexRepositoryProvider).complex(id)),
  retry: retryTransientFailures,
);

final complexDevicesProvider = FutureProvider.autoDispose
    .family<List<ComplexDevice>, int>(
      (ref, id) => _unwrap(ref.watch(complexRepositoryProvider).devices(id)),
      retry: retryTransientFailures,
    );

final pendingSubscriptionsProvider =
    FutureProvider.autoDispose<List<PendingSubscription>>(
      (ref) =>
          _unwrap(ref.watch(complexRepositoryProvider).pendingSubscriptions()),
      retry: retryTransientFailures,
    );
