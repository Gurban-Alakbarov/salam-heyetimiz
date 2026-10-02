import 'package:dio/dio.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/envelope.dart';
import '../domain/complex_entities.dart';

/// B6 contracts: GET /v1/invites/{token} (public), POST /v1/invites/{token}/accept | decline (signed-in
/// invitee). Unified envelope. The token is a credential: it is only ever placed in the request path
/// (redacted by the logging interceptor) and never returned, stored or logged here.
///
/// Every non-live token (unknown, expired, revoked, already used) is ONE uniform 410 — there is no typed
/// [Failure] for 410, so it is folded into a [ConflictFailure] carrying the server code
/// (`invitation_invalid`) instead of an UnknownFailure: definitive (never retried) and keyed by code.
class InviteRepository {
  InviteRepository(this._api);

  final ApiClient _api;

  static const String goneCode = 'invitation_invalid';

  Future<Result<T>> _guard<T>(Future<T> Function() run) async {
    try {
      return Success(await run());
    } on DioException catch (e) {
      if (e.response?.statusCode == 410) {
        return Err(
          ConflictFailure(
            _code(e.response?.data) ?? goneCode,
            _message(e.response?.data) ?? '',
          ),
        );
      }
      return Err(mapDioError(e));
    } catch (_) {
      return const Err(UnknownFailure());
    }
  }

  static String? _code(Object? body) {
    final errors = body is Map ? body['errors'] : null;
    return errors is Map && errors['code'] is String
        ? errors['code'] as String
        : null;
  }

  static String? _message(Object? body) =>
      body is Map && body['message'] is String
      ? body['message'] as String
      : null;

  Future<Result<InvitePreview>> preview(String token) => _guard(
    () async => InvitePreview.fromJson(
      Envelope.data((await _api.get('/v1/invites/$token')).data),
    ),
  );

  Future<Result<InviteAcceptance>> accept(String token) => _guard(
    () async => InviteAcceptance.fromJson(
      Envelope.data((await _api.post('/v1/invites/$token/accept')).data),
    ),
  );

  Future<Result<void>> decline(String token) => _guard(() async {
    await _api.post('/v1/invites/$token/decline');
  });
}
