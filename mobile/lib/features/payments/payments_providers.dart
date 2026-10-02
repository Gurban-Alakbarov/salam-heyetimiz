import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import '../../core/error/retry_policy.dart';
import 'data/payments_repository.dart';
import 'domain/payment_entities.dart';

/// Payments DI (B13) — mirrors the other features' granular providers.
final paymentsRepositoryProvider = Provider<PaymentsRepository>(
  (ref) => PaymentsRepository(ref.watch(apiClientProvider)),
);

/// One order (checkout loads its hosted page URL from here — the URL never travels in a route).
final paymentOrderProvider = FutureProvider.autoDispose
    .family<PaymentOrder, int>((ref, id) async {
      final result = await ref.watch(paymentsRepositoryProvider).getOrder(id);
      return result.fold((f) => throw f, (o) => o);
    }, retry: retryTransientFailures);
