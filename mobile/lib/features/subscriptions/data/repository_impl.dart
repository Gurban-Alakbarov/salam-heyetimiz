import 'package:dio/dio.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/envelope.dart';
import '../domain/entity/subscription_entities.dart';
import '../domain/repository.dart';
import 'datasource/subscription_remote_datasource.dart';
import 'dto/subscription_dto.dart';
import 'mapper/subscription_mappers.dart';

class SubscriptionRepositoryImpl implements SubscriptionRepository {
  SubscriptionRepositoryImpl(this._remote);

  final SubscriptionRemoteDataSource _remote;

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
  Future<Result<List<Subscription>>> listActive() => _guard(() async {
    final body = await _remote.list(status: 'active', limit: 100);
    final rawList = (body['data'] as List?) ?? const [];
    return rawList
        .map(
          (e) => subscriptionDtoToEntity(
            SubscriptionDto.fromJson(Map<String, dynamic>.from(e as Map)),
          ),
        )
        .toList();
  });
}
