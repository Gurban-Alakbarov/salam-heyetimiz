import 'package:dio/dio.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/envelope.dart';
import '../domain/entity/device_entities.dart';
import '../domain/repository.dart';
import 'datasource/device_remote_datasource.dart';
import 'dto/device_dto.dart';
import 'mapper/device_mappers.dart';

class DeviceRepositoryImpl implements DeviceRepository {
  DeviceRepositoryImpl(this._remote);

  final DeviceRemoteDataSource _remote;

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
  Future<Result<DevicePage>> list({
    String? filter,
    String? cursor,
    int limit = 25,
  }) => _guard(() async {
    final body = await _remote.list(
      filter: filter,
      cursor: cursor,
      limit: limit,
    );
    final rawList = (body['data'] as List?) ?? const [];
    final devices = rawList
        .map(
          (e) => deviceDtoToEntity(
            DeviceDto.fromJson(Map<String, dynamic>.from(e as Map)),
          ),
        )
        .toList();
    final page = body['page'];
    final pageMap = page is Map ? Map<String, dynamic>.from(page) : const {};
    return DevicePage(
      devices: devices,
      nextCursor: pageMap['next_cursor'] as String?,
      hasMore: pageMap['has_more'] as bool? ?? false,
    );
  });

  @override
  Future<Result<Device>> detail(int id) => _guard(
    () async => deviceDtoToEntity(DeviceDto.fromJson(await _remote.detail(id))),
  );
}
