import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:salam_mobile/features/auth/auth_providers.dart';
import 'package:salam_mobile/features/auth/domain/usecase/auth_use_cases.dart';
import 'package:salam_mobile/features/auth/presentation/screens/force_update_screen.dart';
import 'package:salam_mobile/features/auth/presentation/screens/login_screen.dart';
import 'package:salam_mobile/features/auth/presentation/screens/maintenance_screen.dart';
import 'package:salam_mobile/features/auth/presentation/screens/register_screen.dart';
import 'package:salam_mobile/features/auth/presentation/screens/verify_otp_screen.dart';
import 'package:salam_mobile/features/auth/presentation/screens/welcome_screen.dart';
import 'package:salam_mobile/features/home/home_shell.dart';
import 'package:salam_mobile/features/notifications/presentation/notification_screen.dart';
import 'package:salam_mobile/features/settings/settings_screen.dart';
import 'package:salam_mobile/features/splash/splash_screen.dart';

/// go_router graph + guards (Constitution §1, NAVIGATION.md, SCREEN_FLOW.md §0).
/// The redirect is the single navigation authority; it re-runs whenever the auth
/// state or the bootstrap result changes (refreshListenable).
final routerProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier<int>(0);
  ref.listen(authStateProvider, (_, _) => refresh.value++);
  ref.listen(bootstrapControllerProvider, (_, _) => refresh.value++);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    initialLocation: '/',
    refreshListenable: refresh,
    redirect: (context, state) {
      final auth = ref.read(authStateProvider);
      final boot = ref.read(bootstrapControllerProvider);
      final loc = state.uri.path;

      // Still resolving (or bootstrap failed) → hold on the splash.
      if (auth == AuthState.unknown || boot.isLoading || boot.hasError) {
        return loc == '/' ? null : '/';
      }

      final app = boot.value?.app;
      if (app?.maintenanceMode ?? false) {
        return loc == '/maintenance' ? null : '/maintenance';
      }
      if (app?.forceUpdate ?? false) {
        return loc == '/force-update' ? null : '/force-update';
      }

      final authed = auth == AuthState.authenticated;
      final isGuestRoute = loc == '/welcome' || loc.startsWith('/auth');
      final isProtected =
          loc == '/home' ||
          loc == '/settings' ||
          loc == '/notifications' ||
          loc.startsWith('/devices');

      // Leaving the splash or a now-cleared gate → route onward.
      if (loc == '/' || loc == '/maintenance' || loc == '/force-update') {
        return authed ? '/home' : '/welcome';
      }
      if (isProtected && !authed) return '/welcome';
      if (isGuestRoute && authed) return '/home';
      return null;
    },
    routes: [
      GoRoute(path: '/', builder: (context, state) => const SplashScreen()),
      GoRoute(
        path: '/welcome',
        builder: (context, state) => const WelcomeScreen(),
      ),
      GoRoute(
        path: '/auth/register',
        builder: (context, state) => const RegisterScreen(),
      ),
      GoRoute(
        path: '/auth/login',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/auth/verify',
        builder: (context, state) {
          final email = state.uri.queryParameters['email'] ?? '';
          final flow = state.uri.queryParameters['flow'] == 'login'
              ? AuthFlow.login
              : AuthFlow.register;
          return VerifyOtpScreen(email: email, flow: flow);
        },
      ),
      GoRoute(
        path: '/maintenance',
        builder: (context, state) => const MaintenanceScreen(),
      ),
      GoRoute(
        path: '/force-update',
        builder: (context, state) => const ForceUpdateScreen(),
      ),
      GoRoute(path: '/home', builder: (context, state) => const HomeShell()),
      GoRoute(
        path: '/settings',
        builder: (context, state) => const SettingsScreen(),
      ),
      GoRoute(
        path: '/notifications',
        builder: (context, state) => const NotificationScreen(),
      ),
    ],
  );
});
