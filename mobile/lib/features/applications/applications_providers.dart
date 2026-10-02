import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import '../../core/error/retry_policy.dart';
import 'data/applications_repository.dart';
import 'domain/application_entities.dart';

/// Applications DI (B14).
final applicationsRepositoryProvider = Provider<ApplicationsRepository>(
  (ref) => ApplicationsRepository(ref.watch(apiClientProvider)),
);

/// The caller's own applications + account type (GET /v1/applications/mine).
final myApplicationsProvider = FutureProvider.autoDispose<MyApplications>((
  ref,
) async {
  final result = await ref.watch(applicationsRepositoryProvider).mine();
  return result.fold((f) => throw f, (v) => v);
}, retry: retryTransientFailures);
