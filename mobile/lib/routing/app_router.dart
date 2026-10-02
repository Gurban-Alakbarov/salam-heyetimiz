import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:salam_mobile/core/session/session_roles.dart';
import 'package:salam_mobile/features/auth/auth_providers.dart';
import 'package:salam_mobile/features/auth/session_roles_provider.dart';
import 'package:salam_mobile/features/auth/domain/usecase/auth_use_cases.dart';
import 'package:salam_mobile/features/auth/presentation/screens/force_update_screen.dart';
import 'package:salam_mobile/features/auth/presentation/screens/login_screen.dart';
import 'package:salam_mobile/features/auth/presentation/screens/maintenance_screen.dart';
import 'package:salam_mobile/features/auth/presentation/screens/register_screen.dart';
import 'package:salam_mobile/features/auth/presentation/screens/verify_otp_screen.dart';
import 'package:salam_mobile/features/auth/presentation/screens/welcome_screen.dart';
import 'package:salam_mobile/features/applications/presentation/applications_screen.dart';
import 'package:salam_mobile/features/applications/presentation/legal_application_screen.dart';
import 'package:salam_mobile/features/applications/presentation/physical_application_screen.dart';
import 'package:salam_mobile/features/applications/presentation/register_type_screen.dart';
import 'package:salam_mobile/features/complex/domain/complex_entities.dart';
import 'package:salam_mobile/features/complex/presentation/complex_screens.dart';
import 'package:salam_mobile/features/complex/presentation/invite_landing_screen.dart';
import 'package:salam_mobile/features/complex/presentation/pending_payments_screen.dart';
import 'package:salam_mobile/features/door_widget/presentation/door_widget_picker_screen.dart';
import 'package:salam_mobile/features/family/presentation/family_invite_screen.dart';
import 'package:salam_mobile/features/family/presentation/family_screen.dart';
import 'package:salam_mobile/features/door_widget/presentation/widget_open_screen.dart';
import 'package:salam_mobile/features/home/home_shell.dart';
import 'package:salam_mobile/features/komendant/presentation/komendant_home_screen.dart';
import 'package:salam_mobile/features/komendant/presentation/komendant_invitations_screen.dart';
import 'package:salam_mobile/features/komendant/presentation/komendant_invite_screen.dart';
import 'package:salam_mobile/features/komendant/presentation/komendant_residents_screen.dart';
import 'package:salam_mobile/features/notifications/presentation/notification_screen.dart';
import 'package:salam_mobile/features/payments/presentation/checkout_screen.dart';
import 'package:salam_mobile/features/payments/presentation/payment_result_screen.dart';
import 'package:salam_mobile/features/settings/settings_screen.dart';
import 'package:salam_mobile/features/splash/splash_screen.dart';

/// go_router graph + guards (Constitution §1, NAVIGATION.md, SCREEN_FLOW.md §0).
/// The redirect is the single navigation authority; it re-runs whenever the auth
/// state or the bootstrap result changes (refreshListenable).
final routerProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier<int>(0);
  ref.listen(authStateProvider, (_, _) => refresh.value++);
  ref.listen(bootstrapControllerProvider, (_, _) => refresh.value++);
  ref.listen(sessionRolesProvider, (_, _) => refresh.value++);
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
          loc.startsWith('/devices') ||
          loc.startsWith('/widget') ||
          loc.startsWith('/checkout') ||
          loc.startsWith('/payment') ||
          loc.startsWith('/applications') ||
          loc.startsWith('/complex') ||
          loc.startsWith('/family') ||
          loc.startsWith('/komendant');

      // Leaving the splash or a now-cleared gate → route onward.
      if (loc == '/' || loc == '/maintenance' || loc == '/force-update') {
        return authed ? '/home' : '/welcome';
      }
      if (isProtected && !authed) return '/welcome';
      if (isGuestRoute && authed) return '/home';
      // B13 role gate (server-derived roles from /v1/me; UI gating only).
      return roleRedirect(loc, ref.read(sessionRolesProvider).value);
    },
    routes: [
      GoRoute(path: '/', builder: (context, state) => const SplashScreen()),
      GoRoute(
        path: '/welcome',
        builder: (context, state) => const WelcomeScreen(),
      ),
      // B14: the standard registration path picks Physical / Legal first (BR-5).
      GoRoute(
        path: '/auth/register/type',
        builder: (context, state) => const RegisterTypeScreen(),
      ),
      GoRoute(
        path: '/auth/register',
        builder: (context, state) {
          // B16: `invite=1` = registering to claim the pending invitation (token stays in PendingInviteStore;
          // the preview, if any, travels as `extra` — never in the URL).
          if (state.uri.queryParameters['invite'] == '1') {
            return RegisterScreen(invite: true, invitePreview: state.extra is InvitePreview ? state.extra as InvitePreview : null);
          }
          return RegisterScreen(accountType: _accountType(state.uri.queryParameters['type']));
        },
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
          return VerifyOtpScreen(
            email: email,
            flow: flow,
            accountType: _accountType(state.uri.queryParameters['type']),
            invite: state.uri.queryParameters['invite'] == '1',
          );
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
      // B14 registration applications (B9): status list + the two separate forms.
      GoRoute(path: '/applications', builder: (context, state) => const ApplicationsScreen()),
      GoRoute(
        path: '/applications/new/physical',
        builder: (context, state) => PhysicalApplicationScreen(onboarding: state.uri.queryParameters['onboarding'] == '1'),
      ),
      GoRoute(
        path: '/applications/new/legal',
        builder: (context, state) => LegalApplicationScreen(onboarding: state.uri.queryParameters['onboarding'] == '1'),
      ),
      // B16 invitation landing (token read from PendingInviteStore — never in the route) + resident complex
      // browse/subscribe (B7) + the caller's own pending subscriptions (resident main / family additional).
      GoRoute(
        path: '/invite',
        builder: (context, state) => InviteLandingScreen(autoAccept: state.uri.queryParameters['accept'] == '1'),
      ),
      GoRoute(path: '/complexes', builder: (context, state) => const MyComplexesScreen()),
      GoRoute(
        path: '/complex/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '');
          return id == null ? const HomeShell() : ComplexHomeScreen(complexId: id);
        },
      ),
      GoRoute(
        path: '/complex/:id/device/:deviceId',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '');
          final deviceId = int.tryParse(state.pathParameters['deviceId'] ?? '');
          return id == null || deviceId == null ? const HomeShell() : ComplexDeviceScreen(complexId: id, deviceId: deviceId);
        },
      ),
      GoRoute(path: '/payments/pending', builder: (context, state) => const PendingPaymentsScreen()),
      // B17 family head surface (B8 API): members + invitations, and the per-device family invite form.
      GoRoute(
        path: '/family',
        builder: (context, state) => FamilyScreen(focusDeviceId: int.tryParse(state.uri.queryParameters['device'] ?? '')),
      ),
      GoRoute(
        path: '/family/invite',
        builder: (context, state) => FamilyInviteScreen(deviceId: int.tryParse(state.uri.queryParameters['device'] ?? '')),
      ),
      // B15 Komendant (B5 API): reachable only for a linked manager — roleRedirect bounces everyone else.
      GoRoute(path: '/komendant', builder: (context, state) => const KomendantHomeScreen()),
      GoRoute(path: '/komendant/invite', builder: (context, state) => const KomendantInviteScreen()),
      GoRoute(path: '/komendant/invitations', builder: (context, state) => const KomendantInvitationsScreen()),
      GoRoute(path: '/komendant/residents', builder: (context, state) => const KomendantResidentsScreen()),
      // B13 payments: hosted checkout (URL loaded from the order, never from the route) and the
      // server-confirmed result — by orderId (in-app) or by `order` reference (salam:// return link).
      GoRoute(
        path: '/checkout/:orderId',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['orderId'] ?? '');
          return id == null ? const HomeShell() : CheckoutScreen(orderId: id);
        },
      ),
      GoRoute(
        path: '/payment/return',
        builder: (context, state) => PaymentResultScreen(
          orderId: int.tryParse(state.uri.queryParameters['orderId'] ?? ''),
          orderReference: state.uri.queryParameters['order'],
        ),
      ),
      // W5: the Android "add widget" configure flow lands here (per-instance barrier
      // picker bound to the real AppWidgetId). A selection finishes the configuration.
      GoRoute(
        path: '/widget/configure',
        builder: (context, state) {
          final id = int.tryParse(
            state.uri.queryParameters['widgetId'] ?? '',
          );
          return id == null
              ? const HomeShell()
              : DoorWidgetPickerScreen(widgetId: id, fromConfigure: true);
        },
      ),
      // GEOFENCE-4: a geofenced widget open returned `location_required`; the widget
      // launched the app here for that barrier so the user can open it in-app (with
      // the foreground GPS gate). deviceId is navigation context; the screen verifies
      // it against the user's own device list.
      GoRoute(
        path: '/widget/open',
        builder: (context, state) {
          final id = int.tryParse(state.uri.queryParameters['deviceId'] ?? '');
          return id == null
              ? const HomeShell()
              : WidgetOpenScreen(deviceId: id);
        },
      ),
    ],
  );
});

/// Only the two B9 account types pass through; anything else is dropped (legacy / no type).
String? _accountType(String? raw) => raw == 'physical' || raw == 'legal' ? raw : null;
