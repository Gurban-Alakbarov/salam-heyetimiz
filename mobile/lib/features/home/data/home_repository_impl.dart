import 'package:dio/dio.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/envelope.dart';
import '../../devices/domain/repository.dart';
import '../domain/home_data.dart';

/// Builds the Home snapshot from GET /v1/me (profile + subscriptions) and the
/// device count from the devices repository. Parses only the /me fields Home
/// needs (does not touch the auth feature).
class HomeRepositoryImpl implements HomeRepository {
  HomeRepositoryImpl(this._api, this._deviceRepository);

  final ApiClient _api;
  final DeviceRepository _deviceRepository;

  @override
  Future<Result<HomeData>> load() async {
    try {
      final meRes = await _api.get('/v1/me');
      final data = Envelope.data(meRes.data);
      final user = data['user'] is Map
          ? Map<String, dynamic>.from(data['user'] as Map)
          : <String, dynamic>{};
      final subs = data['active_subscriptions'] is List
          ? data['active_subscriptions'] as List
          : const [];

      final deviceResult = await _deviceRepository.list(limit: 100);
      // The card reads "Aktiv cihaz" → count only devices whose status is active
      // (client-side; the backend list is unchanged).
      final deviceCount = deviceResult.fold(
        (_) => 0,
        (page) => page.devices.where((d) => d.isActive).length,
      );

      return Success(
        HomeData(
          fullName: user['full_name'] as String?,
          email: user['email'] as String?,
          phone: (data['phone'] ?? user['phone']) as String?,
          deviceCount: deviceCount,
          subscriptionCount: subs.length,
          lastActivityAt: DateTime.tryParse(
            user['last_login_at']?.toString() ?? '',
          ),
        ),
      );
    } on DioException catch (e) {
      return Err(mapDioError(e));
    } catch (_) {
      return const Err(UnknownFailure());
    }
  }
}
