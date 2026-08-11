import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../analytics/analytics_service.dart';
import '../config/app_config.dart';
import '../connectivity/connectivity_service.dart';
import '../crash/crash_reporter.dart';
import '../feature_flags/feature_flag_service.dart';
import '../logger/app_logger.dart';
import '../network/api_client.dart';
import '../services/device_info_service.dart';
import '../session/session_manager.dart';
import '../storage/app_storage.dart';

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
    localeCode: () => ref.read(localeProvider)?.languageCode ?? 'az',
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

/// App locale (null = system). Sent as Accept-Language to the API (Phase 2).
class LocaleNotifier extends Notifier<Locale?> {
  @override
  Locale? build() => null;

  void set(Locale? locale) => state = locale;
}

final localeProvider = NotifierProvider<LocaleNotifier, Locale?>(
  LocaleNotifier.new,
);
