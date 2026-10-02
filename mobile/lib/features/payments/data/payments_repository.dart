import 'package:dio/dio.dart';
import 'package:uuid/uuid.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/envelope.dart';
import '../domain/payment_entities.dart';

/// Order / payment calls for the hosted-checkout flow (B13). Reuses the existing backend contracts only:
/// GET /v1/orders/{id}, POST /v1/orders/{id}/recheck, GET /v1/orders (reference lookup) and
/// POST /v1/subscriptions/{id}/renew (Idempotency-Key). Order bodies are bare resources (no envelope).
class PaymentsRepository {
  PaymentsRepository(this._api, {Uuid? uuid}) : _uuid = uuid ?? const Uuid();

  final ApiClient _api;
  final Uuid _uuid;

  Future<Result<T>> _guard<T>(Future<T> Function() run) async {
    try {
      return Success(await run());
    } on DioException catch (e) {
      return Err(mapDioError(e));
    } catch (_) {
      return const Err(UnknownFailure());
    }
  }

  Map<String, dynamic> _map(Object? body) => Envelope.data(body, unified: false);

  Future<Result<PaymentOrder>> getOrder(int id) =>
      _guard(() async => PaymentOrder.fromJson(_map((await _api.get('/v1/orders/$id')).data)));

  Future<Result<PaymentOrder>> recheck(int id) =>
      _guard(() async => PaymentOrder.fromJson(_map((await _api.post('/v1/orders/$id/recheck')).data)));

  /// Resolves a return deep link's order reference to the caller's own order (server-scoped list).
  Future<Result<PaymentOrder?>> findByReference(String reference) => _guard(() async {
        final body = _map((await _api.get('/v1/orders', query: {'limit': 50})).data);
        final rows = (body['data'] as List?) ?? const [];
        for (final r in rows) {
          final o = PaymentOrder.fromJson(Map<String, dynamic>.from(r as Map));
          if (o.reference == reference) return o;
        }
        return null;
      });

  /// B16: pays a `pending_payment` subscription (POST /v1/orders, B1 contract). [tier] is the
  /// subscription's own tier (`main` | `additional`) → `sub_main` | `sub_additional`. The server decides
  /// who may pay (beneficiary or the member's family head — SubscriptionPaymentAuthorizer) and the price.
  Future<Result<PaymentOrder>> payPendingSubscription(int subscriptionId, String tier) => _guard(() async {
        final itemType = tier == 'additional' ? 'sub_additional' : 'sub_main';
        final res = await _api.post(
          '/v1/orders',
          data: {
            'purpose': itemType,
            'items': [
              {'item_type': itemType, 'referenced_id': subscriptionId, 'quantity': 1},
            ],
          },
          headers: {'Idempotency-Key': 'pay-$subscriptionId-${_uuid.v4()}'},
        );
        return PaymentOrder.fromJson(_map(res.data));
      });

  /// Starts (or idempotently replays) a renewal order for the caller's own subscription.
  Future<Result<PaymentOrder>> renewSubscription(int subscriptionId) => _guard(() async {
        final res = await _api.post(
          '/v1/subscriptions/$subscriptionId/renew',
          data: const <String, dynamic>{},
          headers: {'Idempotency-Key': 'renew-$subscriptionId-${_uuid.v4()}'},
        );
        return PaymentOrder.fromJson(_map(res.data));
      });
}
