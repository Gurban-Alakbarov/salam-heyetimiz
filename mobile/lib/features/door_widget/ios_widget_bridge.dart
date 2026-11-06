import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:home_widget/home_widget.dart';

import '../devices/domain/entity/device_entities.dart';

/// Hands the iOS home-screen widget what it needs: the doors, the API address, the language and the widget's own
/// open-only token. They go through a shared Keychain group (ios/SalamDoorWidget/DoorStore.swift reads them). The
/// app's session tokens stay out of that group. Does nothing on other platforms, and a failure never reaches the app.
class IosWidgetBridge {
  const IosWidgetBridge();

  static const String _kind = 'SalamDoorWidget';
  static const String _keyToken = 'widget_token';
  static const String _keyBaseUrl = 'widget_base_url';
  static const String _keyLocale = 'widget_locale';
  static const String _keyDoors = 'widget_doors';

  static const FlutterSecureStorage _shared = FlutterSecureStorage(
    iOptions: IOSOptions(
      groupId: '2654395QYQ.az.salam.sezam.shared',
      accessibility: KeychainAccessibility.first_unlock, // the widget also runs on a locked phone
    ),
  );

  bool get _enabled => defaultTargetPlatform == TargetPlatform.iOS;

  Future<void> setConfig({required String baseUrl, required String locale}) => _run(() async {
    await _shared.write(key: _keyBaseUrl, value: baseUrl);
    await _shared.write(key: _keyLocale, value: locale);
  });

  Future<void> setLocale(String locale) => _run(() => _shared.write(key: _keyLocale, value: locale));

  Future<void> setToken(String token) => _run(() => _shared.write(key: _keyToken, value: token));

  /// Only the doors this person can open now.
  Future<void> setDoors(List<Device> devices) => _run(() {
    final doors = [
      for (final device in devices)
        if (device.canOpen) {'id': device.id, 'label': device.label},
    ];
    return _shared.write(key: _keyDoors, value: jsonEncode(doors));
  });

  /// Logout: the widget forgets the person's doors and token. The address and language are not personal.
  Future<void> clear() => _run(() async {
    await _shared.delete(key: _keyToken);
    await _shared.delete(key: _keyDoors);
  });

  Future<void> _run(Future<void> Function() write) async {
    if (!_enabled) return;
    try {
      await write();
      await HomeWidget.updateWidget(iOSName: _kind);
    } catch (_) {
      // Best-effort: the widget keeps what it has.
    }
  }
}
