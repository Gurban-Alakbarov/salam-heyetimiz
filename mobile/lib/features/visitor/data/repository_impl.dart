import 'package:dio/dio.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/envelope.dart';
import '../domain/entity/visitor_entities.dart';
import '../domain/repository.dart';
import 'datasource/visitor_remote_datasource.dart';
import 'dto/visitor_dto.dart';
import 'mapper/visitor_mappers.dart';

class VisitorRepositoryImpl implements VisitorRepository {
  VisitorRepositoryImpl(this._remote);

  final VisitorRemoteDataSource _remote;

  Future<Result<T>> _guard<T>(Future<T> Function() run) async {
    try {
      return Success(await run());
    } on DioException catch (e) {
      return Err(mapDioError(e));
    } catch (_) {
      return const Err(UnknownFailure());
    }
  }

  @override
  Future<Result<CreatedVisitorLink>> create(
    int deviceId, {
    required VisitorAccessType accessType,
    int? durationMinutes,
    String? visitorName,
    VisitorPurpose? purpose,
  }) => _guard(
    () async => createVisitorLinkDtoToEntity(
      CreateVisitorLinkResponseDto.fromJson(
        await _remote.create(
          deviceId,
          accessType: accessType.wire,
          durationMinutes: durationMinutes,
          visitorName: visitorName,
          purpose: purpose?.wire,
        ),
      ),
    ),
  );
}
