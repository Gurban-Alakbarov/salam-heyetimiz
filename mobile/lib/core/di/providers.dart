import 'dart:ui' show PlatformDispatcher;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../analytics/analytics_service.dart';
import '../config/app_config.dart';
import '../connectivity/connectivity_service.dart';
import '../crash/crash_reporter.dart';
import '../feature_flags/feature_flag_service.dart';
import '../location/location_service.dart';
import '../logger/app_logger.dart';
import '../network/api_client.dart';
import '../services/device_info_service.dart';
import '../session/session_manager.dart';
import '../storage/app_storage.dart';
import '../storage/storage_keys.dart';

/// Dependency injection via Riverpod (Constitution §1.4/§5, STATE_MANAGEMENT.md §5).
/// No get_it. [appConfigProvider] is overridden per flavor in `main_<flavor>.dart`.

final appConfigProvider = Provider<AppConfig>((ref) => AppConfig.dev);

/// Installed app version (semver), read once in bootstrap via package_info and
/// injected as an override. Null when unknown (e.g. tests) → no client-side
/// force-update. Kept out of the splash hot path for testability.
final appVersionProvider = Provider<String?>((ref) => null);

final loggerProvider = Provider<AppLogger>(
  (ref) => AppLogger(enabled: ref.watch(appConfigProvider).loggingEnabled),
);

final secureStoreProvider = Provider<SecureStore>((ref) => const SecureStore());

final analyticsProvider = Provider<AnalyticsService>(
  (ref) => const NoopAnalytics(),
);

final crashReporterProvider = Provider<CrashReporter>(
  (ref) => const NoopCrashReporter(),
);

final featureFlagServiceProvider = Provider<FeatureFlagService>(
  (ref) => const LocalFeatureFlagService(),
);

final connectivityServiceProvider = Provider<ConnectivityService>(
  (ref) => ConnectivityService(),
);

final deviceInfoServiceProvider = Provider<DeviceInfoService>(
  (ref) => DeviceInfoService(ref.watch(secureStoreProvider)),
);

/// Foreground GPS acquisition for the geofence open gate (GEOFENCE-3). Only used
/// when a device has `geofenceEnabled` — otherwise the open flow never touches it.
final locationServiceProvider = Provider<LocationService>(
  (ref) => const LocationService(),
);

/// The token authority. Constructed + `load()`ed in bootstrap, then injected
/// here via a ProviderScope override (so tokens are ready before the first frame).
final sessionManagerProvider = Provider<SessionManager>(
  (ref) => throw UnimplementedError(
    'sessionManagerProvider must be overridden in bootstrap',
  ),
);

final apiClientProvider = Provider<ApiClient>(
  (ref) => ApiClient.create(
    config: ref.watch(appConfigProvider),
    sessionManager: ref.watch(sessionManagerProvider),
    logger: ref.watch(loggerProvider),
    localeCode: () => ref.read(effectiveLocaleProvider).languageCode,
  ),
);

/// Theme mode (system/light/dark). In-memory in Phase 1; persistence in Phase 2.
class ThemeModeNotifier extends Notifier<ThemeMode> {
  @override
  ThemeMode build() => ThemeMode.system;

  void set(ThemeMode mode) => state = mode;
  void toggleDark() =>
      state = state == ThemeMode.dark ? ThemeMode.light : ThemeMode.dark;
}

final themeModeProvider = NotifierProvider<ThemeModeNotifier, ThemeMode>(
  ThemeModeNotifier.new,
);

/// The persisted "send usage statistics" choice, hydrated in bootstrap (default on).
final initialAnalyticsEnabledProvider = Provider<bool>((ref) => true);

/// "Send usage statistics" switch (Profile, 1.0.1). Persisted; applied to the analytics SDK at once, so
/// off means nothing more leaves the device. Crash reports are not affected by this switch.
class AnalyticsEnabledNotifier extends Notifier<bool> {
  @override
  bool build() => ref.read(initialAnalyticsEnabledProvider);

  Future<void> set(bool enabled) async {
    state = enabled;
    await ref.read(analyticsProvider).setEnabled(enabled);
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(StorageKeys.analyticsEnabled, enabled);
    } catch (_) {
      // Best-effort persistence; the SDK already has the new value for this run.
    }
  }
}

final analyticsEnabledProvider =
    NotifierProvider<AnalyticsEnabledNotifier, bool>(
      AnalyticsEnabledNotifier.new,
    );

/// Initial app locale, hydrated from persisted `locale_code` in bootstrap and injected
/// via a ProviderScope override (null = no choice yet → the phone's language, see [effectiveLocaleProvider]).
final initialLocaleProvider = Provider<Locale?>((ref) => null);

/// App locale (null = system). Sent as Accept-Language to the API. Now PERSISTED
/// (W5 D2): a chosen language survives restart. The home-screen widget follows this
/// choice too — the settings screen mirrors the code to widget storage on change.
class LocaleNotifier extends Notifier<Locale?> {
  @override
  Locale? build() => ref.read(initialLocaleProvider);

  Future<void> set(Locale? locale) async {
    state = locale;
    try {
      final prefs = await SharedPreferences.getInstance();
      if (locale == null) {
        await prefs.remove(StorageKeys.localeCode);
      } else {
        await prefs.setString(StorageKeys.localeCode, locale.languageCode);
      }
    } catch (_) {
      // Persistence is best-effort — a failed write must not break switching.
    }
  }
}

final localeProvider = NotifierProvider<LocaleNotifier, Locale?>(
  LocaleNotifier.new,
);

const _appLanguages = {'az', 'en', 'ru'};

/// The phone's language when the app speaks it (az / en / ru), otherwise Azerbaijani — what the app uses
/// until the user picks a language in Settings (owner request 2026-10-30).
String deviceLanguageCode() {
  final code = PlatformDispatcher
      .instance
      .locale
      .languageCode; // safe before the binding exists
  return _appLanguages.contains(code) ? code : 'az';
}

/// The language the app actually shows: the user's choice, else the phone's language (az fallback).
final effectiveLocaleProvider = Provider<Locale>(
  (ref) => ref.watch(localeProvider) ?? Locale(deviceLanguageCode()),
);
