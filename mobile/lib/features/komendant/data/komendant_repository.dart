import 'package:dio/dio.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/envelope.dart';
import '../domain/komendant_entities.dart';

/// B5 contracts only: /v1/komendant/{complex,devices,residents,invitations}. Bodies are `{data: …}`;
/// 403 `not_komendant`, 409 invitation codes and 429 resend limits surface as typed [Failure]s.
class KomendantRepository {
  KomendantRepository(this._api);

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

  Map<String, dynamic> _map(Object? body) {
    if (body is Map && body['data'] is Map) {
      return Map<String, dynamic>.from(body['data'] as Map);
    }
    return const {};
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

  Future<Result<KomendantComplex>> complex() => _guard(
    () async => KomendantComplex.fromJson(
      _map((await _api.get('/v1/komendant/complex')).data),
    ),
  );

  Future<Result<List<KomendantDevice>>> devices() => _guard(
    () async => _list(
      (await _api.get('/v1/komendant/devices')).data,
    ).map(KomendantDevice.fromJson).toList(),
  );

  Future<Result<List<KomendantResident>>> residents() => _guard(
    () async => _list(
      (await _api.get('/v1/komendant/residents')).data,
    ).map(KomendantResident.fromJson).toList(),
  );

  /// Removes a resident or a family member from the complex. O7: a family head needs [familyChoice] (and the
  /// new head for [FamilyRemovalChoice.newHead]); without one the server answers 409 family_decision_required.
  Future<Result<ResidentRemoval>> removeResident(
    int userId, {
    FamilyRemovalChoice? familyChoice,
    int? newHeadUserId,
  }) => _guard(
    () async => ResidentRemoval.fromJson(
      _map(
        (await _api.delete(
          '/v1/komendant/residents/$userId',
          data: switch (familyChoice) {
            FamilyRemovalChoice.newHead => {'family_action': 'new_head', 'new_head_user_id': newHeadUserId},
            FamilyRemovalChoice.removeFamily => {'family_action': 'remove_family'},
            null => null,
          },
        )).data,
      ),
    ),
  );

  /// O7 — [newHeadUserId] takes [headUserId]'s family over at once; the old head stays a member unless
  /// [removeOldHead].
  Future<Result<void>> changeFamilyHead(
    int headUserId, {
    required int newHeadUserId,
    bool removeOldHead = false,
  }) => _guard(() async {
    await _api.post(
      '/v1/komendant/families/$headUserId/head',
      data: {'new_head_user_id': newHeadUserId, 'old_head': removeOldHead ? 'remove' : 'member'},
    );
  });

  /// All of the complex's resident invitations (server caps at 200, newest first); tabs group client-side.
  Future<Result<List<KomendantInvitation>>> invitations() => _guard(
    () async => _list(
      (await _api.get('/v1/komendant/invitations')).data,
    ).map(KomendantInvitation.fromJson).toList(),
  );

  Future<Result<KomendantInvitation>> invite({
    required String firstName,
    required String lastName,
    required String email,
  }) => _guard(() async {
    final res = await _api.post(
      '/v1/komendant/invitations',
      data: {'first_name': firstName, 'last_name': lastName, 'email': email},
    );
    return KomendantInvitation.fromJson(_map(res.data));
  });

  Future<Result<KomendantInvitation>> resend(int id) => _guard(
    () async => KomendantInvitation.fromJson(
      _map((await _api.post('/v1/komendant/invitations/$id/resend')).data),
    ),
  );

  Future<Result<KomendantInvitation>> revoke(int id) => _guard(
    () async => KomendantInvitation.fromJson(
      _map((await _api.post('/v1/komendant/invitations/$id/revoke')).data),
    ),
  );
}
