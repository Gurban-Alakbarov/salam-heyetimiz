import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:home_widget/home_widget.dart';
import 'package:salam_mobile/core/di/providers.dart';
import 'package:salam_mobile/design_system/theme/app_theme.dart';
import 'package:salam_mobile/features/auth/auth_providers.dart';
import 'package:salam_mobile/features/notifications/notifications_providers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/routing/app_router.dart';

/// Root app: MaterialApp.router wired to the token-based themes, l10n (az/en/ru),
/// and the go_router graph. Theme mode + locale are reactive (Settings toggles).
///
/// It also wires the FCM message handlers once and registers the push token when
/// the session is (or becomes) authenticated — every push call is inert off
/// Android / without an initialised Firebase app (Phase 4A), so this is safe in
/// tests and on iOS until its Firebase config lands.
class SalamApp extends ConsumerStatefulWidget {
  const SalamApp({super.key});

  @override
  ConsumerState<SalamApp> createState() => _SalamAppState();
}

class _SalamAppState extends ConsumerState<SalamApp> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final push = ref.read(pushMessagingServiceProvider);
      push.initMessaging();
      if (ref.read(authStateProvider) == AuthState.authenticated) {
        push.registerToken();
      }
    });
  }

  /// True once the Android "add widget" configure launch has been routed, so it is
  /// handled exactly once (W5, Model A).
  bool _configureHandled = false;

  /// If this launch came from the home-screen widget's Android configure flow, route
  /// to the per-instance barrier picker (needs an authenticated session for the device
  /// list). A normal launch returns null here and nothing happens.
  Future<void> _maybeHandleWidgetConfigure() async {
    if (_configureHandled || !mounted) return;
    try {
      final raw = await HomeWidget.initiallyLaunchedFromHomeWidgetConfigure();
      final widgetId = int.tryParse(raw ?? '');
      if (widgetId == null || !mounted) return;
      _configureHandled = true;
      ref.read(routerProvider).push('/widget/configure?widgetId=$widgetId');
    } catch (_) {
      // Configure routing is best-effort — never break app start.
    }
  }

  @override
  Widget build(BuildContext context) {
    // Register the FCM token the moment the session becomes authenticated (login,
    // or a restored session resolving unknown → authenticated).
    ref.listen<AuthState>(authStateProvider, (previous, next) {
      if (next == AuthState.authenticated) {
        ref.read(pushMessagingServiceProvider).registerToken();
        // Session is ready → if we were launched to configure a widget, route there.
        _maybeHandleWidgetConfigure();
      }
    });

    final config = ref.watch(appConfigProvider);
    return MaterialApp.router(
      title: config.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: ref.watch(themeModeProvider),
      // Default to Azerbaijani on first launch (no explicit choice yet). Once the
      // user picks a language in Settings the provider holds it and wins here.
      locale: ref.watch(localeProvider) ?? const Locale('az'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      routerConfig: ref.watch(routerProvider),
    );
  }
}
