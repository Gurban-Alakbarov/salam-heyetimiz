import 'package:dio/dio.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/envelope.dart';
import '../domain/entity/invitation_entities.dart';
import '../domain/repository.dart';
import 'datasource/invitation_remote_datasource.dart';
import 'dto/invitation_dto.dart';
import 'mapper/invitation_mappers.dart';

class InvitationRepositoryImpl implements InvitationRepository {
  InvitationRepositoryImpl(this._remote);

  final InvitationRemoteDataSource _remote;

  @override
  Future<Result<List<Invitation>>> list({String? status}) async {
    try {
      final body = await _remote.list(status: status, limit: 100);
      final rawList = (body['data'] as List?) ?? const [];
      final items = rawList
          .map(
            (e) => invitationDtoToEntity(
              InvitationDto.fromJson(Map<String, dynamic>.from(e as Map)),
            ),
          )
          .toList();
      return Success(items);
    } on DioException catch (e) {
      return Err(mapDioError(e));
    } catch (_) {
      return const Err(UnknownFailure());
    }
  }
}
