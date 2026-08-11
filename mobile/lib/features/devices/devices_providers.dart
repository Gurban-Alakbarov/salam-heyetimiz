import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import 'data/datasource/device_remote_datasource.dart';
import 'data/repository_impl.dart';
import 'domain/entity/device_entities.dart';
import 'domain/repository.dart';

/// Devices DI — separate, granular providers (no god-provider; Constitution §5).

class DeviceEvents {
  DeviceEvents._();
  static const listOpened = 'device_list_opened';
}

final deviceRepositoryProvider = Provider<DeviceRepository>(
  (ref) => DeviceRepositoryImpl(
    DeviceRemoteDataSource(ref.watch(apiClientProvider)),
  ),
);

/// First page of the user's devices. Pull-to-refresh via `ref.refresh(...future)`.
final deviceListProvider = FutureProvider.autoDispose<DevicePage>((ref) async {
  final result = await ref.watch(deviceRepositoryProvider).list(limit: 25);
  return result.fold((failure) => throw failure, (page) => page);
});
