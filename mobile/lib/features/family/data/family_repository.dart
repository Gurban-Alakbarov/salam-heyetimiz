import 'package:dio/dio.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/envelope.dart';
import '../domain/family_entities.dart';

/// B8 contracts only — no new endpoint: GET /v1/family/members, DELETE /v1/family/members/{userId},
/// GET|POST /v1/devices/{id}/invitations, POST /v1/family/invitations/{id}/resend|revoke. Bodies are
/// `{data: …}`; refusals use the `{error:{code,…}}` envelope (409 / 403 / 422 / 429). Paying for a member
/// goes through the existing PaymentsRepository (B1 orders + SubscriptionPaymentAuthorizer).
class FamilyRepository {
  FamilyRepository(this._api);

  final ApiClient _api;

  Future<Result<T>> _guard<T>(Future<T> Function() run) async {
    try {
      return Success(await run());
    } on DioException catch (e) {
      return Err(mapDioError(e));
    } catch (_) {
      return const Err(UnknownFailure());
    }
  }

  Map<String, dynamic> _map(Object? body) => Envelope.data(body);

  List<Map<String, dynamic>> _list(Object? body) {
    final data = body is Map ? body['data'] : null;
    return data is List
        ? data
              .whereType<Map>()
              .map((m) => Map<String, dynamic>.from(m))
              .toList()
        : const [];
  }

  Future<Result<List<FamilyMember>>> members() => _guard(
    () async => _list(
      (await _api.get('/v1/family/members')).data,
    ).map(FamilyMember.fromJson).toList(),
  );

  /// The caller's family invitations for one device, or `null` when the caller does not head a family on
  /// it (403 not the head / 404 not visible) — that answer is how the app learns which devices it manages
  /// (no extra contract; the server's DevicePolicy::manageFamily decides).
  Future<Result<List<FamilyInvitation>?>> deviceInvitations(
    int deviceId,
  ) async {
    try {
      final res = await _api.get('/v1/devices/$deviceId/invitations');
      return Success(_list(res.data).map(FamilyInvitation.fromJson).toList());
    } on DioException catch (e) {
      final status = e.response?.statusCode;
      if (status == 403 || status == 404) return const Success(null);
      return Err(mapDioError(e));
    } catch (_) {
      return const Err(UnknownFailure());
    }
  }

  Future<Result<FamilyInviteResult>> invite({
    required int deviceId,
    required String firstName,
    required String lastName,
    required String email,
  }) => _guard(() async {
    final res = await _api.post(
      '/v1/devices/$deviceId/invitations',
      data: {'first_name': firstName, 'last_name': lastName, 'email': email},
    );
    return FamilyInviteResult.fromJson(_map(res.data));
  });

  Future<Result<FamilyInvitation>> resend(int invitationId) => _guard(
    () async => FamilyInvitation.fromJson(
      _map(
        (await _api.post('/v1/family/invitations/$invitationId/resend')).data,
      ),
    ),
  );

  Future<Result<FamilyInvitation>> revoke(int invitationId) => _guard(
    () async => FamilyInvitation.fromJson(
      _map(
        (await _api.post('/v1/family/invitations/$invitationId/revoke')).data,
      ),
    ),
  );

  Future<Result<FamilyRemoval>> removeMember(int userId) => _guard(
    () async => FamilyRemoval.fromJson(
      _map((await _api.delete('/v1/family/members/$userId')).data),
    ),
  );
}
