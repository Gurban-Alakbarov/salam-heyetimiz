import 'dart:async';

import 'package:firebase_analytics/firebase_analytics.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:home_widget/home_widget.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:salam_mobile/app.dart';
import 'package:salam_mobile/core/analytics/analytics_service.dart';
import 'package:salam_mobile/core/analytics/firebase_analytics_service.dart';
import 'package:salam_mobile/core/config/app_config.dart';
import 'package:salam_mobile/core/crash/crash_reporter.dart';
import 'package:salam_mobile/core/crash/firebase_crash_reporter.dart';
import 'package:salam_mobile/core/di/providers.dart';
import 'package:salam_mobile/core/logger/app_logger.dart';
import 'package:salam_mobile/core/session/session_manager.dart';
import 'package:salam_mobile/core/storage/app_storage.dart';
import 'package:salam_mobile/core/storage/storage_keys.dart';
import 'package:salam_mobile/features/door_widget/door_widget_open.dart';
import 'package:salam_mobile/features/door_widget/door_widget_service.dart';
import 'package:salam_mobile/features/notifications/data/push_messaging_service.dart';

/// Crash reports + usage analytics leave the device only from a prod release build (1.0.1), so dev / QA /
/// debug builds never pollute the store app's numbers. `--dart-define=TELEMETRY_DEBUG=true` turns them on
/// in a debug build to verify the wiring. Native collection is off in AndroidManifest / Info.plist until
/// this decides, so nothing is sent before the user's analytics choice is known.
bool _telemetryOn(AppConfig config) =>
    const bool.fromEnvironment('TELEMETRY_DEBUG') ||
    (kReleaseMode && config.env == AppEnv.prod);

/// Single app entrypoint used by every flavor (`main_<flavor>.dart`). Initialises
/// storage + session + global error handlers (→ CrashReporter: Crashlytics, or Noop
/// when telemetry is off) before running the app inside a guarded zone.
Future<void> bootstrap(AppConfig config) async {
  CrashReporter crash = const NoopCrashReporter();
  AnalyticsService analytics = const NoopAnalytics();

  await runZonedGuarded(() async {
    WidgetsFlutterBinding.ensureInitialized();
    await Hive.initFlutter();

    // Firebase / FCM — Android (google-services.json) and iOS (GoogleService-Info.plist + APNs key in
    // Firebase). The background handler must be registered before runApp. A failure here must never
    // block app start, so it is reported non-fatally and the app continues without push.
    if (defaultTargetPlatform == TargetPlatform.android || defaultTargetPlatform == TargetPlatform.iOS) {
      try {
        await Firebase.initializeApp();
        FirebaseMessaging.onBackgroundMessage(fcmBackgroundHandler);
        final telemetry = _telemetryOn(config);
        await FirebaseCrashlytics.instance.setCrashlyticsCollectionEnabled(
          telemetry,
        );
        if (telemetry) {
          crash = FirebaseCrashReporter(FirebaseCrashlytics.instance);
          analytics = FirebaseAnalyticsService(FirebaseAnalytics.instance);
        } else {
          await FirebaseAnalytics.instance.setAnalyticsCollectionEnabled(false);
        }
      } catch (error, stack) {
        crash.recordError(error, stack, fatal: false);
      }
    }

    // Persisted app locale (W5 D2) — hydrate so a chosen language survives restart.
    // + the "send usage statistics" choice (1.0.1, default on), applied before any event is sent.
    Locale? savedLocale;
    var widgetLocaleCode = deviceLanguageCode();
    var analyticsEnabled = true;
    try {
      final prefs = await SharedPreferences.getInstance();
      final code = prefs.getString(StorageKeys.localeCode);
      if (code != null && code.isNotEmpty) {
        savedLocale = Locale(code);
        widgetLocaleCode = code;
      }
      analyticsEnabled = prefs.getBool(StorageKeys.analyticsEnabled) ?? true;
    } catch (_) {
      // Hydration is best-effort — fall back to the app defaults (az, analytics on).
    }
    try {
      await analytics.setEnabled(analyticsEnabled);
    } catch (error, stack) {
      crash.recordError(error, stack, fatal: false);
    }

    // Home-screen door widget (W3/W5): register the background open callback + persist the
    // active flavor base URL (Q3, non-secret) so the widget's background isolate can reach
    // the existing open endpoint, and mirror the app locale (W5 D2) so native renders in the
    // right language. Android-only, best-effort — a failure here must never block app start.
    if (defaultTargetPlatform == TargetPlatform.android) {
      try {
        await HomeWidget.registerInteractivityCallback(doorWidgetOpenCallback);
        const service = DoorWidgetService();
        await service.setBaseUrl(config.apiBaseUrl);
        await service.setLocale(widgetLocaleCode);
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
          initialLocaleProvider.overrideWithValue(savedLocale),
          crashReporterProvider.overrideWithValue(crash),
          analyticsProvider.overrideWithValue(analytics),
          initialAnalyticsEnabledProvider.overrideWithValue(analyticsEnabled),
        ],
        child: const SalamApp(),
      ),
    );
  }, (error, stack) => crash.recordError(error, stack, fatal: true));
}
