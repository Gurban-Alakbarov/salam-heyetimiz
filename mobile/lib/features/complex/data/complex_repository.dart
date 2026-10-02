import 'package:dio/dio.dart';
import 'package:uuid/uuid.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/envelope.dart';
import '../../payments/domain/payment_entities.dart';
import '../domain/complex_entities.dart';

/// B7 contracts: GET /v1/complexes, /v1/complexes/{id}, /v1/complexes/{id}/devices,
/// POST /v1/complexes/{c}/devices/{d}/subscribe (Idempotency-Key) → the same order resource as createOrder;
/// plus the caller's own pending subscriptions (GET /v1/subscriptions?status=pending_payment, batch 06).
/// Bodies are `{data: …}`. Non-members get 404 from the server — never another complex's data.
class ComplexRepository {
  ComplexRepository(this._api, {Uuid? uuid}) : _uuid = uuid ?? const Uuid();

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

  List<Map<String, dynamic>> _list(Object? body) {
    final data = body is Map ? body['data'] : null;
    return data is List
        ? data
              .whereType<Map>()
              .map((m) => Map<String, dynamic>.from(m))
              .toList()
        : const [];
  }

  Future<Result<List<ResidentComplex>>> myComplexes() => _guard(
    () async => _list(
      (await _api.get('/v1/complexes')).data,
    ).map(ResidentComplex.fromJson).toList(),
  );

  Future<Result<ResidentComplex>> complex(int id) => _guard(
    () async => ResidentComplex.fromJson(
      Envelope.data((await _api.get('/v1/complexes/$id')).data),
    ),
  );

  Future<Result<List<ComplexDevice>>> devices(int complexId) => _guard(
    () async => _list(
      (await _api.get('/v1/complexes/$complexId/devices')).data,
    ).map(ComplexDevice.fromJson).toList(),
  );

  /// Starts (or resumes) the caller's own `main` subscription on a complex device → order for checkout.
  Future<Result<PaymentOrder>> subscribe(int complexId, int deviceId) =>
      _guard(() async {
        final res = await _api.post(
          '/v1/complexes/$complexId/devices/$deviceId/subscribe',
          data: const <String, dynamic>{},
          headers: {
            'Idempotency-Key': 'subscribe-$complexId-$deviceId-${_uuid.v4()}',
          },
        );
        return PaymentOrder.fromJson(Envelope.data(res.data, unified: false));
      });

  Future<Result<List<PendingSubscription>>> pendingSubscriptions() => _guard(
    () async => _list(
      (await _api.get(
        '/v1/subscriptions',
        query: {'status': 'pending_payment'},
      )).data,
    ).map(PendingSubscription.fromJson).toList(),
  );
}
