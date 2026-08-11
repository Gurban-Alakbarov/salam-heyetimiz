import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:uuid/uuid.dart';

import '../storage/app_storage.dart';
import '../storage/storage_keys.dart';

/// Device fingerprint sent with verify-email (token is bound to the install).
/// Maps 1:1 to the backend `device` object.
@immutable
class DeviceInfo {
  const DeviceInfo({
    required this.installUuid,
    required this.platform,
    this.osVersion,
    this.appVersion,
    this.deviceModel,
    this.pushToken,
  });

  final String installUuid;
  final String platform; // 'ios' | 'android'
  final String? osVersion;
  final String? appVersion;
  final String? deviceModel;
  final String? pushToken; // FCM/APNs — wired in a later phase.
}

class DeviceInfoService {
  DeviceInfoService(this._secureStore);

  final SecureStore _secureStore;

  Future<DeviceInfo> get() async {
    final installUuid = await _installUuid();
    final platform = defaultTargetPlatform == TargetPlatform.iOS
        ? 'ios'
        : 'android';

    String? osVersion;
    String? deviceModel;
    try {
      final plugin = DeviceInfoPlugin();
      if (defaultTargetPlatform == TargetPlatform.android) {
        final info = await plugin.androidInfo;
        osVersion = info.version.release;
        deviceModel = info.model;
      } else if (defaultTargetPlatform == TargetPlatform.iOS) {
        final info = await plugin.iosInfo;
        osVersion = info.systemVersion;
        deviceModel = info.utsname.machine;
      }
    } catch (_) {
      // Best-effort; nullable fields stay null.
    }

    String? appVersion;
    try {
      appVersion = (await PackageInfo.fromPlatform()).version;
    } catch (_) {
      // ignore
    }

    return DeviceInfo(
      installUuid: installUuid,
      platform: platform,
      osVersion: osVersion,
      appVersion: appVersion,
      deviceModel: deviceModel,
    );
  }

  /// One stable UUID per install, persisted in secure storage.
  Future<String> _installUuid() async {
    final existing = await _secureStore.read(StorageKeys.installUuid);
    if (existing != null) return existing;
    final id = const Uuid().v4();
    await _secureStore.write(StorageKeys.installUuid, id);
    return id;
  }
}
