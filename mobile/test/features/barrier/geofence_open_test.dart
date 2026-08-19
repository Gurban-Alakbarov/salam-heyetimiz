import 'package:dio/dio.dart';
import 'package:fake_async/fake_async.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/di/providers.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/core/location/location_service.dart';
import 'package:salam_mobile/features/barrier/barrier_providers.dart';
import 'package:salam_mobile/features/barrier/data/datasource/barrier_remote_datasource.dart';
import 'package:salam_mobile/features/barrier/data/repository_impl.dart';
import 'package:salam_mobile/features/barrier/domain/entity/barrier_entities.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/shared/failure_message.dart';

import '../../helpers/mocks.dart';

Response<dynamic> _ack() => Response<dynamic>(
  requestOptions: RequestOptions(path: '/v1/devices/42/open'),
  statusCode: 202,
  data: const {
    'command_id': 1,
    'state': 'queued',
    'expected_completion_ms': 3000,
  },
);

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(registerCommonFallbacks);

  // ---- datasource: the /open request body (GEOFENCE-3 + preserved invariants) ----
  group('datasource /open body', () {
    late MockApiClient api;
    late BarrierRemoteDataSource ds;

    setUp(() {
      api = MockApiClient();
      ds = BarrierRemoteDataSource(api, appVersion: '1.2.3');
      when(
        () => api.post(
          any(),
          data: any(named: 'data'),
          headers: any(named: 'headers'),
        ),
      ).thenAnswer((_) async => _ack());
    });

    Map<String, dynamic> body() => Map<String, dynamic>.from(
      verify(
        () => api.post(
          any(),
          data: captureAny(named: 'data'),
          headers: any(named: 'headers'),
        ),
      ).captured.single as Map,
    );

    test('geofence fix → latitude/longitude/accuracy attached', () async {
      await ds.open(
        42,
        fix: const GeoFix(latitude: 40.1, longitude: 49.2, accuracy: 9.5),
      );
      final b = body();
      expect(b['latitude'], 40.1);
      expect(b['longitude'], 49.2);
      expect(b['accuracy'], 9.5);
      expect(b['client_app_version'], '1.2.3'); // preserved
    });

    test('no fix → NO location keys (geofence OFF regression)', () async {
      await ds.open(42);
      final b = body();
      expect(b.containsKey('latitude'), isFalse);
      expect(b.containsKey('longitude'), isFalse);
      expect(b.containsKey('accuracy'), isFalse);
      expect(b.containsKey('direction'), isFalse);
      expect(b['client_app_version'], '1.2.3'); // preserved
    });

    test('close preserves direction=close and attaches the fix', () async {
      await ds.close(
        42,
        fix: const GeoFix(latitude: 1, longitude: 2, accuracy: 3),
      );
      final b = body();
      expect(b['direction'], 'close');
      expect(b['latitude'], 1);
    });

    test('Idempotency-Key is a fresh UUID per call', () async {
      await ds.open(42);
      await ds.open(42);
      final keys = verify(
        () => api.post(
          any(),
          data: any(named: 'data'),
          headers: captureAny(named: 'headers'),
        ),
      ).captured.map((h) => (h as Map)['Idempotency-Key'] as String).toList();
      expect(keys.length, 2);
      expect(keys.first, isNotEmpty);
      expect(keys.first.length, 36); // UUID v4 canonical form
      expect(keys.first, isNot(keys.last)); // unique per logical open
    });
  });

  // ---- notifier: CONDITIONAL location acquisition (D6) ----
  group('notifier conditional location', () {
    late MockBarrierRepository repo;
    late MockLocationService location;

    setUp(() {
      repo = MockBarrierRepository();
      location = MockLocationService();
      when(() => repo.open(any(), fix: any(named: 'fix'))).thenAnswer(
        (_) async => const Success(
          OpenAck(
            commandId: 1,
            state: CommandState.queued,
            expectedCompletionMs: 3000,
          ),
        ),
      );
      when(() => repo.status(any())).thenAnswer(
        (_) async =>
            const Success(CommandStatus(id: 1, state: CommandState.queued)),
      );
    });

    ProviderContainer make() {
      final c = ProviderContainer(
        overrides: [
          barrierRepositoryProvider.overrideWithValue(repo),
          locationServiceProvider.overrideWithValue(location),
        ],
      );
      c.listen(barrierOpenProvider, (_, _) {});
      addTearDown(c.dispose);
      return c;
    }

    test('geofence ON → acquires GPS and sends the fix with the open', () {
      fakeAsync((fake) {
        when(() => location.getCurrent()).thenAnswer(
          (_) async => const LocationOk(
            GeoFix(latitude: 40.0, longitude: 49.0, accuracy: 11),
          ),
        );
        make().read(barrierOpenProvider.notifier).open(42, geofenceEnabled: true);
        fake.flushMicrotasks();

        verify(() => location.getCurrent()).called(1);
        final fix =
            verify(() => repo.open(any(), fix: captureAny(named: 'fix')))
                    .captured
                    .single
                as GeoFix?;
        expect(fix, isNotNull);
        expect(fix!.latitude, 40.0);
        expect(fix.accuracy, 11);
      });
    });

    test('geofence OFF → location service NOT called, no fix sent', () {
      fakeAsync((fake) {
        final c = make();
        c.read(barrierOpenProvider.notifier).open(42, geofenceEnabled: false);
        fake.flushMicrotasks();

        verifyNever(() => location.getCurrent());
        final fix =
            verify(() => repo.open(any(), fix: captureAny(named: 'fix')))
                    .captured
                    .single
                as GeoFix?;
        expect(fix, isNull);
      });
    });

    test('permission denied → BarrierFailed(LocationFailure), open NOT sent', () {
      fakeAsync((fake) {
        when(
          () => location.getCurrent(),
        ).thenAnswer((_) async => const LocationPermissionDenied());
        final c = make();
        c.read(barrierOpenProvider.notifier).open(42, geofenceEnabled: true);
        fake.flushMicrotasks();

        final state = c.read(barrierOpenProvider);
        expect(state, isA<BarrierFailed>());
        final failure = (state as BarrierFailed).failure;
        expect(failure, isA<LocationFailure>());
        expect((failure as LocationFailure).code, 'permission_denied');
        verifyNever(() => repo.open(any(), fix: any(named: 'fix')));
      });
    });

    test('location timeout → BarrierFailed, open NOT sent', () {
      fakeAsync((fake) {
        when(
          () => location.getCurrent(),
        ).thenAnswer((_) async => const LocationTimeout());
        final c = make();
        c.read(barrierOpenProvider.notifier).open(42, geofenceEnabled: true);
        fake.flushMicrotasks();

        expect(
          ((c.read(barrierOpenProvider) as BarrierFailed).failure
                  as LocationFailure)
              .code,
          'timeout',
        );
        verifyNever(() => repo.open(any(), fix: any(named: 'fix')));
      });
    });

    test('service disabled → BarrierFailed, open NOT sent', () {
      fakeAsync((fake) {
        when(
          () => location.getCurrent(),
        ).thenAnswer((_) async => const LocationServiceDisabled());
        final c = make();
        c.read(barrierOpenProvider.notifier).open(42, geofenceEnabled: true);
        fake.flushMicrotasks();

        expect(
          ((c.read(barrierOpenProvider) as BarrierFailed).failure
                  as LocationFailure)
              .code,
          'service_disabled',
        );
        verifyNever(() => repo.open(any(), fix: any(named: 'fix')));
      });
    });
  });

  // ---- transport mapping: geofence 403 codes → ForbiddenFailure(code) ----
  group('403 geofence code mapping', () {
    late MockBarrierRemoteDataSource remote;
    late BarrierRepositoryImpl repo;

    setUp(() {
      remote = MockBarrierRemoteDataSource();
      repo = BarrierRepositoryImpl(remote);
    });

    for (final code in const [
      'location_required',
      'outside_geofence',
      'location_imprecise',
    ]) {
      test('403 $code → ForbiddenFailure(code:$code)', () async {
        when(() => remote.open(any(), fix: any(named: 'fix'))).thenThrow(
          dioError(
            status: 403,
            body: {
              'error': {'code': code},
            },
          ),
        );
        final failure = (await repo.open(42) as Err).failure;
        expect(failure, isA<ForbiddenFailure>());
        expect(failure.code, code);
      });
    }

    test('regression: 429 → RateLimitedFailure; connection → NetworkFailure', () async {
      when(
        () => remote.open(any(), fix: any(named: 'fix')),
      ).thenThrow(dioError(status: 429, retryAfter: '15'));
      expect((await repo.open(42) as Err).failure, isA<RateLimitedFailure>());

      when(
        () => remote.open(any(), fix: any(named: 'fix')),
      ).thenThrow(dioOffline());
      expect((await repo.open(42) as Err).failure, isA<NetworkFailure>());
    });
  });

  // ---- localized message mapping ----
  group('message mapping (l10n az)', () {
    late AppLocalizations l;
    setUp(() async {
      l = await AppLocalizations.delegate.load(const Locale('az'));
    });

    test('geofence 403 codes → specific (non-generic) messages', () {
      expect(
        deviceFailureMessage(l, const ForbiddenFailure('m', 'location_required')),
        l.errLocationRequired,
      );
      expect(
        deviceFailureMessage(l, const ForbiddenFailure('m', 'outside_geofence')),
        l.errOutsideGeofence,
      );
      expect(
        deviceFailureMessage(
          l,
          const ForbiddenFailure('m', 'location_imprecise'),
        ),
        l.errLocationImprecise,
      );
    });

    test('subscription_required keeps its own message (D5 untouched)', () {
      expect(
        deviceFailureMessage(
          l,
          const ForbiddenFailure('m', 'subscription_required'),
        ),
        l.errSubscriptionRequired,
      );
    });

    test('client LocationFailure codes → location messages', () {
      expect(
        deviceFailureMessage(l, const LocationFailure('permission_denied')),
        l.errLocationPermissionDenied,
      );
      expect(
        deviceFailureMessage(l, const LocationFailure('permanently_denied')),
        l.errLocationPermissionPermanent,
      );
      expect(
        deviceFailureMessage(l, const LocationFailure('service_disabled')),
        l.errLocationServiceDisabled,
      );
      expect(
        deviceFailureMessage(l, const LocationFailure('timeout')),
        l.errLocationTimeout,
      );
    });
  });
}
