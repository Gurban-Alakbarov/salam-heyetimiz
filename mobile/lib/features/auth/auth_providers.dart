import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import '../../core/error/failure.dart';
import '../../core/services/app_version.dart';
import '../door_widget/door_widget_providers.dart';
import '../notifications/notifications_providers.dart';
import 'data/datasource/auth_remote_datasource.dart';
import 'data/repository_impl.dart';
import 'domain/entity/auth_entities.dart';
import 'domain/repository.dart';
import 'domain/usecase/auth_use_cases.dart';

/// Auth feature DI (Constitution §5). Wires the data → domain → use-case graph
/// and the session-scoped auth state + splash bootstrap orchestration.

final authRemoteDataSourceProvider = Provider<AuthRemoteDataSource>(
  (ref) => AuthRemoteDataSource(
    ref.watch(apiClientProvider),
    ref.watch(deviceInfoServiceProvider),
  ),
);

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepositoryImpl(
    ref.watch(authRemoteDataSourceProvider),
    ref.watch(sessionManagerProvider),
  ),
);

final registerUseCaseProvider = Provider<RegisterUseCase>(
  (ref) => RegisterUseCase(
    ref.watch(authRepositoryProvider),
    ref.watch(analyticsProvider),
    ref.watch(crashReporterProvider),
  ),
);

final loginUseCaseProvider = Provider<LoginUseCase>(
  (ref) => LoginUseCase(
    ref.watch(authRepositoryProvider),
    ref.watch(analyticsProvider),
    ref.watch(crashReporterProvider),
  ),
);

final resendOtpUseCaseProvider = Provider<ResendOtpUseCase>(
  (ref) => ResendOtpUseCase(
    ref.watch(authRepositoryProvider),
    ref.watch(analyticsProvider),
  ),
);

final verifyOtpUseCaseProvider = Provider<VerifyOtpUseCase>(
  (ref) => VerifyOtpUseCase(
    ref.watch(authRepositoryProvider),
    ref.watch(analyticsProvider),
    ref.watch(crashReporterProvider),
  ),
);

final logoutUseCaseProvider = Provider<LogoutUseCase>(
  (ref) => LogoutUseCase(
    ref.watch(authRepositoryProvider),
    ref.watch(analyticsProvider),
    // Pre-logout cleanup, run while the session token is still valid. Best-effort:
    // de-register this install's FCM push token (DELETE /v1/notifications/push-token,
    // inert off Android) AND drop the home-screen widget's saved barrier so a
    // logged-out (or the next) user never sees the previous resident's door. Each
    // step is guarded independently so one failure can't skip the other.
    () async {
      try {
        await ref.read(pushMessagingServiceProvider).deregisterToken();
      } catch (_) {
        // ignore — a missed de-registration must not block logout
      }
      try {
        await ref.read(doorWidgetServiceProvider).clearAll();
      } catch (_) {
        // ignore — widget cleanup is non-critical and non-sensitive
      }
    },
  ),
);

/// The auth gate. `unknown` until the splash bootstrap resolves it; thereafter
/// derived from the [SessionManager] (login/logout/refresh-fail update it live).
enum AuthState { unknown, guest, authenticated }

class AuthStateNotifier extends Notifier<AuthState> {
  bool _resolved = false;

  @override
  AuthState build() {
    final sm = ref.watch(sessionManagerProvider);
    void sync() {
      if (_resolved) {
        state = sm.isAuthenticated ? AuthState.authenticated : AuthState.guest;
      }
    }

    sm.addListener(sync);
    ref.onDispose(() => sm.removeListener(sync));
    return _resolved
        ? (sm.isAuthenticated ? AuthState.authenticated : AuthState.guest)
        : AuthState.unknown;
  }

  /// Called once by the splash bootstrap when the initial state is known.
  void resolve() {
    _resolved = true;
    final sm = ref.read(sessionManagerProvider);
    state = sm.isAuthenticated ? AuthState.authenticated : AuthState.guest;
  }
}

final authStateProvider = NotifierProvider<AuthStateNotifier, AuthState>(
  AuthStateNotifier.new,
);

/// Splash bootstrap: fetch guest config → apply force-update check → restore the
/// session (GET /v1/me, with transparent refresh) → resolve [authStateProvider].
/// Throws a [Failure] on bootstrap fetch error (splash shows retry).
class BootstrapController extends AsyncNotifier<BootstrapEntity> {
  @override
  Future<BootstrapEntity> build() async {
    final repo = ref.read(authRepositoryProvider);
    final sm = ref.read(sessionManagerProvider);

    final res = await repo.bootstrap();
    final boot = switch (res) {
      Success(:final value) => value,
      Err(:final failure) => throw failure,
    };

    var app = boot.app;

    // Client-side min-version enforcement (in addition to the server flag).
    final currentVersion = ref.read(appVersionProvider);
    var forceUpdate = app.forceUpdate;
    final minVersion = app.minVersion;
    if (currentVersion != null &&
        minVersion != null &&
        AppVersion.isBelow(currentVersion, minVersion)) {
      forceUpdate = true;
    }
    app = app.copyWith(forceUpdate: forceUpdate);

    // Restore the session unless the app is gated.
    if (sm.isAuthenticated && !app.maintenanceMode && !forceUpdate) {
      final meRes = await repo.me();
      meRes.fold(
        (_) {}, // session invalid → refresh already cleared it → guest
        (me) => app = me.app.copyWith(
          forceUpdate: forceUpdate || me.app.forceUpdate,
        ),
      );
    }

    ref.read(authStateProvider.notifier).resolve();
    return boot.copyWith(app: app);
  }

  Future<void> reload() async {
    state = const AsyncValue.loading();
    ref.invalidateSelf();
    await future;
  }
}

final bootstrapControllerProvider =
    AsyncNotifierProvider<BootstrapController, BootstrapEntity>(
      BootstrapController.new,
    );

/// The authenticated user for profile UI. Reuses the existing GET /v1/me call
/// (no new endpoint); returns null when the session can't be resolved so the
/// profile can degrade gracefully instead of throwing.
final currentUserProvider = FutureProvider.autoDispose<MeEntity?>((ref) async {
  final res = await ref.read(authRepositoryProvider).me();
  return res.fold((_) => null, (me) => me);
});
