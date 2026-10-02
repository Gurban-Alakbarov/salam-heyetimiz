import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'failure.dart';

/// Retry policy for providers that surface a typed [Failure] (opt-in per provider, never global).
///
/// Riverpod 3 retries every failed provider by default (10 attempts, 200 ms → 6.4 s back-off), which
/// keeps a screen on its spinner for ~40 s even when the server has answered definitively — 401/403/404,
/// a 409 business refusal, a 422, a 429. Those now surface at once so the screen shows its error state;
/// transient failures (network, timeout, 5xx, device offline, unknown) and non-[Failure] errors keep
/// Riverpod's default behaviour unchanged.
Duration? retryTransientFailures(int retryCount, Object error) {
  if (isDefinitiveFailure(error)) return null;
  return ProviderContainer.defaultRetry(retryCount, error);
}

/// A server answer that retrying cannot change.
bool isDefinitiveFailure(Object error) => switch (error) {
  UnauthorizedFailure() ||
  ForbiddenFailure() ||
  NotFoundFailure() ||
  ConflictFailure() ||
  ValidationFailure() ||
  RateLimitedFailure() ||
  OtpFailure() ||
  LocationFailure() => true,
  _ => false,
};
