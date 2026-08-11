import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:salam_mobile/app.dart';
import 'package:salam_mobile/core/config/app_config.dart';
import 'package:salam_mobile/core/crash/crash_reporter.dart';
import 'package:salam_mobile/core/di/providers.dart';
import 'package:salam_mobile/core/logger/app_logger.dart';
import 'package:salam_mobile/core/session/session_manager.dart';
import 'package:salam_mobile/core/storage/app_storage.dart';
import 'package:salam_mobile/features/notifications/data/push_messaging_service.dart';

/// Single app entrypoint used by every flavor (`main_<flavor>.dart`). Initialises
/// storage + session + global error handlers (→ CrashReporter, Noop for now)
/// before running the app inside a guarded zone.
Future<void> bootstrap(AppConfig config) async {
  const CrashReporter crash = NoopCrashReporter();

  await runZonedGuarded(() async {
    WidgetsFlutterBinding.ensureInitialized();
    await Hive.initFlutter();

    // Firebase / FCM — Android only for now (iOS Firebase config is deferred until Apple credentials
    // land). The background handler must be registered before runApp. A failure here must never block
    // app start, so it is reported non-fatally and the app continues without push.
    if (defaultTargetPlatform == TargetPlatform.android) {
      try {
        await Firebase.initializeApp();
        FirebaseMessaging.onBackgroundMessage(fcmBackgroundHandler);
      } catch (error, stack) {
        crash.recordError(error, stack, fatal: false);
      }
    }

    // Hydrate the session before the first frame so the splash gate can decide
    // immediately whether to restore the session or show Welcome.
    const secureStore = SecureStore();
    final logger = AppLogger(enabled: config.loggingEnabled);
    final sessionManager = SessionManager(
      store: secureStore,
      baseUrl: config.apiBaseUrl,
      logger: logger,
    );
    await sessionManager.load();

    String? appVersion;
    try {
      appVersion = (await PackageInfo.fromPlatform()).version;
    } catch (_) {
      // Version unknown → rely on the server force-update flag only.
    }

    FlutterError.onError = (details) {
      crash.recordError(details.exception, details.stack, fatal: true);
    };
    WidgetsBinding.instance.platformDispatcher.onError = (error, stack) {
      crash.recordError(error, stack, fatal: true);
      return true;
    };

    runApp(
      ProviderScope(
        overrides: [
          appConfigProvider.overrideWithValue(config),
          sessionManagerProvider.overrideWithValue(sessionManager),
          appVersionProvider.overrideWithValue(appVersion),
        ],
        child: const SalamApp(),
      ),
    );
  }, (error, stack) => crash.recordError(error, stack, fatal: true));
}
