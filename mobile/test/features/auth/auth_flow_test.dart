import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/di/providers.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/auth/auth_providers.dart';
import 'package:salam_mobile/features/auth/domain/entity/auth_entities.dart';

import '../../helpers/mocks.dart';

/// Boot-gate integration tests: the splash bootstrap orchestration across the
/// repository + session + authState providers (Session Restore, Maintenance,
/// Force Update, offline error) — driven through a real [ProviderContainer].
void main() {
  late MockAuthRepository repo;
  late MockSessionManager session;

  setUp(() {
    repo = MockAuthRepository();
    session = MockSessionManager();
    when(() => session.isAuthenticated).thenReturn(false);
  });

  ProviderContainer makeContainer({String? appVersion}) {
    final container = ProviderContainer(
      overrides: [
        sessionManagerProvider.overrideWithValue(session),
        authRepositoryProvider.overrideWithValue(repo),
        appVersionProvider.overrideWithValue(appVersion),
      ],
    );
    addTearDown(container.dispose);
    return container;
  }

  BootstrapEntity boot({
    bool maintenance = false,
    bool forceUpdate = false,
    String? minVersion,
  }) => BootstrapEntity(
    app: AppStatusEntity(
      maintenanceMode: maintenance,
      forceUpdate: forceUpdate,
      minVersion: minVersion,
    ),
  );

  test('guest: bootstrap resolves authState to guest', () async {
    when(() => repo.bootstrap()).thenAnswer((_) async => Success(boot()));
    final container = makeContainer();

    final result = await container.read(bootstrapControllerProvider.future);

    expect(result.app.maintenanceMode, isFalse);
    expect(container.read(authStateProvider), AuthState.guest);
    verifyNever(() => repo.me());
  });

  test(
    'stored session: restores via /me and resolves to authenticated',
    () async {
      when(() => session.isAuthenticated).thenReturn(true);
      when(() => repo.bootstrap()).thenAnswer((_) async => Success(boot()));
      when(() => repo.me()).thenAnswer(
        (_) async => const Success(
          MeEntity(
            user: UserEntity(id: 1, phone: '+994501234567'),
            app: AppStatusEntity(),
          ),
        ),
      );
      final container = makeContainer();

      await container.read(bootstrapControllerProvider.future);

      expect(container.read(authStateProvider), AuthState.authenticated);
      verify(() => repo.me()).called(1);
    },
  );

  test('maintenance_mode surfaces in the resolved bootstrap', () async {
    when(
      () => repo.bootstrap(),
    ).thenAnswer((_) async => Success(boot(maintenance: true)));
    final container = makeContainer();

    final result = await container.read(bootstrapControllerProvider.future);

    expect(result.app.maintenanceMode, isTrue);
  });

  test('force_update from the server flag', () async {
    when(
      () => repo.bootstrap(),
    ).thenAnswer((_) async => Success(boot(forceUpdate: true)));
    final container = makeContainer();

    final result = await container.read(bootstrapControllerProvider.future);

    expect(result.app.forceUpdate, isTrue);
  });

  test('force_update from a below-minimum installed version', () async {
    when(
      () => repo.bootstrap(),
    ).thenAnswer((_) async => Success(boot(minVersion: '2.0.0')));
    final container = makeContainer(appVersion: '1.0.0');

    final result = await container.read(bootstrapControllerProvider.future);

    expect(result.app.forceUpdate, isTrue);
  });

  // Note: the offline-at-launch path (bootstrap → NetworkFailure → splash retry)
  // is covered by error_mapper_test (offline → NetworkFailure) plus the splash's
  // error rendering. Asserting the thrown build error through a ProviderContainer
  // races with Riverpod's disposal, so it is intentionally not duplicated here.
}
