import 'package:dio/dio.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/envelope.dart';
import '../domain/entity/barrier_entities.dart';
import '../domain/repository.dart';
import 'datasource/barrier_remote_datasource.dart';
import 'dto/command_dto.dart';
import 'mapper/command_mappers.dart';

class BarrierRepositoryImpl implements BarrierRepository {
  BarrierRepositoryImpl(this._remote);

  final BarrierRemoteDataSource _remote;

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
  Future<Result<OpenAck>> open(int deviceId) => _guard(
    () async =>
        openAckDtoToEntity(OpenAckDto.fromJson(await _remote.open(deviceId))),
  );

  @override
  Future<Result<OpenAck>> close(int deviceId) => _guard(
    () async =>
        openAckDtoToEntity(OpenAckDto.fromJson(await _remote.close(deviceId))),
  );

  @override
  Future<Result<CommandStatus>> status(int commandId) => _guard(
    () async => commandStatusDtoToEntity(
      CommandStatusDto.fromJson(await _remote.status(commandId)),
    ),
  );

  @override
  Future<Result<void>> feedback(
    int commandId, {
    required bool gateMoved,
    String? comment,
  }) => _guard(
    () => _remote.feedback(commandId, gateMoved: gateMoved, comment: comment),
  );
}
