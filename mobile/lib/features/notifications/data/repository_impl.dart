import 'package:dio/dio.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/envelope.dart';
import '../domain/entity/notification_entities.dart';
import '../domain/repository.dart';
import 'datasource/notification_remote_datasource.dart';
import 'dto/notification_dto.dart';
import 'mapper/notification_mappers.dart';

class NotificationRepositoryImpl implements NotificationRepository {
  NotificationRepositoryImpl(this._remote);

  final NotificationRemoteDataSource _remote;

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
  Future<Result<NotificationPage>> load({String? cursor}) => _guard(() async {
    final body = await _remote.list(cursor: cursor);
    final raw = (body['data'] as List?) ?? const [];
    final items = raw
        .map(
          (e) => notificationDtoToEntity(
            NotificationDto.fromJson(Map<String, dynamic>.from(e as Map)),
          ),
        )
        .toList();
    final page = body['page'] is Map
        ? Map<String, dynamic>.from(body['page'] as Map)
        : const <String, dynamic>{};
    return NotificationPage(
      items: items,
      unreadCount: (body['unread_count'] as num?)?.toInt() ?? 0,
      nextCursor: page['next_cursor'] as String?,
      hasMore: (page['has_more'] as bool?) ?? false,
    );
  });

  @override
  Future<Result<void>> markRead(int id) => _guard(() => _remote.markRead(id));

  @override
  Future<Result<void>> markAllRead() => _guard(() => _remote.markAllRead());
}
