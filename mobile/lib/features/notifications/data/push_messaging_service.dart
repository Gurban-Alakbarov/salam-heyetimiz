import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/feature_flags/feature_flag_service.dart';
import '../../../core/logger/app_logger.dart';
import '../../../routing/app_router.dart';
import 'push_token_datasource.dart';

/// Top-level FCM background handler (must be a top-level/static function — it runs in its own
/// isolate). Notification-type messages are rendered by the OS tray; we keep this minimal and do NO
/// navigation/UI here (the tap is handled on the main isolate by onMessageOpenedApp / getInitialMessage).
@pragma('vm:entry-point')
Future<void> fcmBackgroundHandler(RemoteMessage message) async {}

/// Android foreground channel — its id MUST match the manifest `default_notification_channel_id`.
const AndroidNotificationChannel _androidChannel = AndroidNotificationChannel(
  'high_importance_channel',
  'Bildirişlər',
  description: 'Salam Həyətimiz push bildirişləri',
  importance: Importance.high,
);

/// Owns the FCM token lifecycle + message handling on Android — the only platform provisioned with
/// Firebase so far. iOS stays behind the same PushClient/backend seam until Apple/Firebase iOS
/// credentials land; the [_enabled] guard (Android + push flag + an initialised Firebase app) makes
/// every method a safe no-op elsewhere, including widget tests where Firebase is never initialised.
///
/// Registration/refresh use the canonical PUT /v1/notifications/push-token (Phase 3, D3); the install
/// is resolved server-side from the JWT fp, so multi-device isolation is preserved.
class PushMessagingService {
  PushMessagingService(this._ref, this._datasource, this._logger, this._flags);

  final Ref _ref;
  final PushTokenDataSource _datasource;
  final AppLogger _logger;
  final FeatureFlagService _flags;

  final FlutterLocalNotificationsPlugin _local =
      FlutterLocalNotificationsPlugin();
  bool _messagingReady = false;

  bool get _enabled =>
      defaultTargetPlatform == TargetPlatform.android &&
      _flags.isEnabled(FeatureFlag.push) &&
      Firebase.apps.isNotEmpty;

  /// Create the local-notification channel + wire the FCM handlers exactly once.
  Future<void> initMessaging() async {
    if (_messagingReady || !_enabled) return;
    _messagingReady = true;

    try {
      await _local.initialize(
        settings: const InitializationSettings(
          android: AndroidInitializationSettings('@mipmap/ic_launcher'),
        ),
        onDidReceiveNotificationResponse: (response) {
          final payload = response.payload;
          if (payload != null) _routeFromData(_decode(payload));
        },
      );
      await _local
          .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin
          >()
          ?.createNotificationChannel(_androidChannel);

      FirebaseMessaging.onMessage.listen(_showForeground);
      FirebaseMessaging.onMessageOpenedApp.listen((m) => _routeFromData(m.data));
      FirebaseMessaging.instance.onTokenRefresh.listen(_registerToken);

      // Cold start from a notification tap (terminated → launched).
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      if (initial != null) _routeFromData(initial.data);
    } catch (e) {
      _messagingReady = false;
      _logger.w('push: initMessaging failed');
    }
  }

  /// Request permission (where the platform requires it), fetch the FCM token, and register it against
  /// the current install via the canonical PUT endpoint. Safe to call repeatedly (PUT is idempotent).
  Future<void> registerToken() async {
    if (!_enabled) return;
    try {
      await FirebaseMessaging.instance.requestPermission();
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await _registerToken(token);
    } catch (e) {
      _logger.w('push: registerToken failed');
    }
  }

  /// De-register the current install's token (logout). Best-effort + idempotent — never blocks logout.
  Future<void> deregisterToken() async {
    if (!_enabled) return;
    try {
      await _datasource.deletePushToken();
    } catch (_) {
      // The server also drops the token on push_invalid; a missed DELETE is harmless.
    }
  }

  Future<void> _registerToken(String token) async {
    try {
      await _datasource.upsertPushToken(token);
    } catch (_) {
      _logger.w('push: token registration PUT failed');
    }
  }

  Future<void> _showForeground(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) return;
    await _local.show(
      id: notification.hashCode,
      title: notification.title,
      body: notification.body,
      notificationDetails: NotificationDetails(
        android: AndroidNotificationDetails(
          _androidChannel.id,
          _androidChannel.name,
          channelDescription: _androidChannel.description,
          importance: Importance.high,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
        ),
      ),
      payload: jsonEncode(message.data),
    );
  }

  /// Canonical deep-link: a tap resolves to the authenticated destination. Granular per-type routing
  /// (device / subscription / inbox) needs routes Phase 7 adds; until then every tap lands on `/home`
  /// (the authenticated shell) — the router redirect gates guests back to Welcome.
  void _routeFromData(Map<String, dynamic> data) {
    final type = data['type']?.toString() ?? '';
    _logger.d('push tap → type=$type');
    // Land on the inbox so the tapped notification is visible + read there. Granular per-type
    // destinations (e.g. a device detail screen) need routes not yet present → the inbox is the safe
    // target; /home stays underneath (the router redirect gates guests back to Welcome).
    _ref.read(routerProvider).push('/notifications');
  }

  Map<String, dynamic> _decode(String payload) {
    try {
      final decoded = jsonDecode(payload);
      return decoded is Map<String, dynamic> ? decoded : <String, dynamic>{};
    } catch (_) {
      return <String, dynamic>{};
    }
  }
}
