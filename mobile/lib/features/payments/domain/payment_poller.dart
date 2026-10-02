import 'payment_entities.dart';

/// Result-confirmation state machine (IMPLEMENTATION_PLAN §16.5): the server is the only source of truth —
/// a return deep link only tells the app *when* to look. Polls GET /v1/orders/{id} every [interval] up to
/// [maxAttempts] (2 s × 30); if still pending, asks the server to re-check with the bank once, then stops
/// with `pending` (the user can re-check manually). Pure apart from the injected callbacks → unit tested.
class PaymentPoller {
  PaymentPoller({
    required this.fetch,
    required this.recheck,
    this.interval = const Duration(seconds: 2),
    this.maxAttempts = 30,
    Future<void> Function(Duration)? delay,
  }) : _delay = delay ?? Future<void>.delayed;

  final Future<PaymentOrder> Function() fetch;
  final Future<PaymentOrder> Function() recheck;
  final Duration interval;
  final int maxAttempts;
  final Future<void> Function(Duration) _delay;

  bool _cancelled = false;
  void cancel() => _cancelled = true;

  /// Emits every observed order; completes on a final outcome, after the
  /// recheck, or when cancelled.
  Stream<PaymentOrder> run() async* {
    for (var attempt = 0; attempt < maxAttempts && !_cancelled; attempt++) {
      final order = await fetch();
      yield order;
      if (order.outcome.isFinal) return;
      if (attempt < maxAttempts - 1) await _delay(interval);
    }
    if (_cancelled) return;
    yield await recheck();
  }
}
