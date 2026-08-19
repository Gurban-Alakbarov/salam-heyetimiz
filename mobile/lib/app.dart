import 'dart:async';

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
  /// Warm (app-already-running) widget launches — a `location_required` widget tap
  /// arriving via onNewIntent (GEOFENCE-4). Cold launches come through
  /// [_maybeHandleWidgetForegroundOpen] instead.
  StreamSubscription<Uri?>? _widgetClickSub;

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
    // GEOFENCE-4: the widget's `location_required` tap launches the app (HOME_WIDGET
    // LAUNCH action) with `salamwidget://foreground?deviceId=…&widgetId=…`. Handle the
    // warm-launch (already running) case here; best-effort so it never breaks start.
    try {
      _widgetClickSub = HomeWidget.widgetClicked.listen(_navigateFromWidgetLaunch);
    } catch (_) {
      // No home_widget channel (non-Android / tests) → nothing to listen to.
    }
  }

  @override
  void dispose() {
    _widgetClickSub?.cancel();
    super.dispose();
  }

  /// True once the Android "add widget" configure launch has been routed, so it is
  /// handled exactly once (W5, Model A).
  bool _configureHandled = false;

  /// True once a cold-start `location_required` widget launch has been routed
  /// (GEOFENCE-4), so the initial intent is consumed exactly once.
  bool _foregroundHandled = false;

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

  /// Cold-start counterpart of [_navigateFromWidgetLaunch]: if this launch came from a
  /// `location_required` widget tap (GEOFENCE-4), route to that barrier's open screen.
  /// Needs an authenticated session (device list), so it is driven off the auth
  /// listener like the configure flow, and consumed exactly once.
  Future<void> _maybeHandleWidgetForegroundOpen() async {
    if (_foregroundHandled || !mounted) return;
    try {
      final uri = await HomeWidget.initiallyLaunchedFromHomeWidget();
      if (uri == null || uri.host != 'foreground') return;
      _foregroundHandled = true;
      _navigateFromWidgetLaunch(uri);
    } catch (_) {
      // Foreground routing is best-effort — never break app start.
    }
  }

  /// Route a `salamwidget://foreground?deviceId=…&widgetId=…` widget launch to the
  /// per-barrier open screen. [deviceId] is navigation context only — the screen
  /// re-checks it against the user's own device list, and the server stays the sole
  /// open authority. Ignored unless authenticated (a protected route would bounce to
  /// Welcome anyway).
  void _navigateFromWidgetLaunch(Uri? uri) {
    if (!mounted || uri == null || uri.host != 'foreground') return;
    final deviceId = int.tryParse(uri.queryParameters['deviceId'] ?? '');
    if (deviceId == null) return;
    if (ref.read(authStateProvider) != AuthState.authenticated) return;
    final widgetId = uri.queryParameters['widgetId'];
    final suffix = widgetId != null ? '&widgetId=$widgetId' : '';
    ref.read(routerProvider).push('/widget/open?deviceId=$deviceId$suffix');
  }

  @override
  Widget build(BuildContext context) {
    // Register the FCM token the moment the session becomes authenticated (login,
    // or a restored session resolving unknown → authenticated).
    ref.listen<AuthState>(authStateProvider, (previous, next) {
      if (next == AuthState.authenticated) {
        ref.read(pushMessagingServiceProvider).registerToken();
        // Session is ready → route any pending widget launch (configure, or a
        // GEOFENCE-4 `location_required` open) now that the device list is reachable.
        _maybeHandleWidgetConfigure();
        _maybeHandleWidgetForegroundOpen();
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
