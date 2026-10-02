import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import '../../core/error/failure.dart';
import '../../core/error/retry_policy.dart';
import 'data/komendant_repository.dart';
import 'domain/komendant_entities.dart';

/// Komendant DI (B15). Read providers throw the typed [Failure] so screens render it via `when(error:)`.
final komendantRepositoryProvider = Provider<KomendantRepository>(
  (ref) => KomendantRepository(ref.watch(apiClientProvider)),
);

Future<T> _unwrap<T>(Future<Result<T>> call) async =>
    (await call).fold((f) => throw f, (v) => v);

final komendantComplexProvider = FutureProvider.autoDispose<KomendantComplex>(
  (ref) => _unwrap(ref.watch(komendantRepositoryProvider).complex()),
  retry: retryTransientFailures,
);

final komendantDevicesProvider =
    FutureProvider.autoDispose<List<KomendantDevice>>(
      (ref) => _unwrap(ref.watch(komendantRepositoryProvider).devices()),
      retry: retryTransientFailures,
    );

final komendantResidentsProvider =
    FutureProvider.autoDispose<List<KomendantResident>>(
      (ref) => _unwrap(ref.watch(komendantRepositoryProvider).residents()),
      retry: retryTransientFailures,
    );

final komendantInvitationsProvider =
    FutureProvider.autoDispose<List<KomendantInvitation>>(
      (ref) => _unwrap(ref.watch(komendantRepositoryProvider).invitations()),
      retry: retryTransientFailures,
    );
