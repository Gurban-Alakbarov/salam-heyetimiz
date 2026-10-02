import 'package:dio/dio.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/envelope.dart';
import '../domain/application_entities.dart';

/// B9 contracts only: GET /v1/applications/mine, POST /v1/applications/individual | legal. Bodies are
/// `{data: …}` (not the unified envelope); 422 field errors / 409 codes surface as typed [Failure]s.
class ApplicationsRepository {
  ApplicationsRepository(this._api);

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

  Map<String, dynamic> _data(Object? body) {
    if (body is Map && body['data'] is Map) return Map<String, dynamic>.from(body['data'] as Map);
    return const {};
  }

  Future<Result<MyApplications>> mine() =>
      _guard(() async => MyApplications.fromJson(_data((await _api.get('/v1/applications/mine')).data)));

  Future<Result<IndividualApplication>> submitIndividual({
    required String fullName,
    required String phone,
    required String email,
    required String address,
    required GeoPoint location,
    String? note,
  }) =>
      _guard(() async {
        final res = await _api.post('/v1/applications/individual', data: {
          'full_name': fullName,
          'phone': phone,
          'email': email,
          'address': address,
          'latitude': location.latitude,
          'longitude': location.longitude,
          if (note != null && note.isNotEmpty) 'note': note,
        });
        return IndividualApplication.fromJson(_data(res.data));
      });

  Future<Result<LegalApplication>> submitLegal({
    required String complexName,
    required String legalName,
    required String voen,
    required String legalAddress,
    required String contactPersonName,
    required String contactPhone,
    required String contactEmail,
    required String address,
    required GeoPoint location,
    int? apartmentsCount,
    String? note,
  }) =>
      _guard(() async {
        final res = await _api.post('/v1/applications/legal', data: {
          'complex_name': complexName,
          'legal_name': legalName,
          'voen': voen,
          'legal_address': legalAddress,
          'contact_person_name': contactPersonName,
          'contact_phone': contactPhone,
          'contact_email': contactEmail,
          'address': address,
          'latitude': location.latitude,
          'longitude': location.longitude,
          'apartments_count': ?apartmentsCount,
          if (note != null && note.isNotEmpty) 'note': note,
        });
        return LegalApplication.fromJson(_data(res.data));
      });
}
