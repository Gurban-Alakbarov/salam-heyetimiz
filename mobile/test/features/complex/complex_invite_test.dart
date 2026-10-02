import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/deeplinks/deep_link_service.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/core/network/api_client.dart';
import 'package:salam_mobile/core/services/device_info_service.dart';
import 'package:salam_mobile/core/storage/app_storage.dart';
import 'package:salam_mobile/features/auth/auth_providers.dart';
import 'package:salam_mobile/features/auth/data/datasource/auth_remote_datasource.dart';
import 'package:salam_mobile/features/auth/session_roles_provider.dart';
import 'package:salam_mobile/features/complex/complex_providers.dart';
import 'package:salam_mobile/features/complex/data/complex_repository.dart';
import 'package:salam_mobile/features/complex/data/invite_repository.dart';
import 'package:salam_mobile/features/complex/domain/complex_entities.dart';
import 'package:salam_mobile/features/complex/presentation/complex_failure.dart';
import 'package:salam_mobile/features/complex/presentation/invite_landing_screen.dart';
import 'package:salam_mobile/features/payments/data/payments_repository.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/l10n/app_localizations_az.dart';

import '../../helpers/mocks.dart';

const _token = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-abcd';

class _MemStore implements SecureStore {
  final Map<String, String> data = {};
  @override
  Future<String?> read(String key) async => data[key];
  @override
  Future<void> write(String key, String value) async => data[key] = value;
  @override
  Future<void> delete(String key) async => data.remove(key);
  @override
  Future<void> clear() async => data.clear();
}

class _MockInviteRepo extends Mock implements InviteRepository {}

class _MockDeviceInfo extends Mock implements DeviceInfoService {}

class _FixedAuth extends AuthStateNotifier {
  _FixedAuth(this.fixed);
  final AuthState fixed;
  @override
  AuthState build() => fixed;
}

Response<dynamic> _res(Object? data, [int status = 200]) => Response<dynamic>(
  data: data,
  statusCode: status,
  requestOptions: RequestOptions(path: '/'),
);

DioException _http(int status, Map<String, dynamic> body) => DioException(
  requestOptions: RequestOptions(path: '/'),
  type: DioExceptionType.badResponse,
  response: Response<dynamic>(
    data: body,
    statusCode: status,
    requestOptions: RequestOptions(path: '/'),
  ),
);

Map<String, dynamic> _envelope(Object? data) => {
  'success': true,
  'message': 'ok',
  'data': data,
  'meta': null,
  'errors': null,
};

Map<String, dynamic> _fail(String code) => {
  'success': false,
  'message': 'x',
  'data': null,
  'meta': null,
  'errors': {'code': code},
};

const _complexPreview = InvitePreview(
  kind: InviteKind.complexResident,
  complexName: 'Gənclik Park',
  firstName: 'Aysel',
  lastName: 'M',
  emailMasked: 'a****@x.az',
);

void main() {
  setUpAll(() => registerFallbackValue(<String, dynamic>{}));
  final l = AppLocalizationsAz();

  group('InviteRepository (B6 contracts)', () {
    late MockApiClient api;
    late InviteRepository repo;
    setUp(() {
      api = MockApiClient();
      repo = InviteRepository(api);
    });

    test('preview parses the public view (masked email, no ids)', () async {
      when(() => api.get('/v1/invites/$_token')).thenAnswer(
        (_) async => _res(
          _envelope({
            'kind': 'complex_resident',
            'complex_name': 'Park',
            'inviter_name': 'Park',
            'first_name': 'Aysel',
            'last_name': 'M',
            'email_masked': 'a****@x.az',
            'status': 'pending',
            'expires_at': '2026-10-09T10:00:00+04:00',
          }),
        ),
      );
      (await repo.preview(_token)).fold((_) => fail('preview'), (p) {
        expect(p.kind, InviteKind.complexResident);
        expect(p.inviteeName, 'Aysel M');
        expect(p.emailMasked, 'a****@x.az');
      });
    });

    test(
      'every dead token (410) is a definitive invitation_invalid — never an UnknownFailure',
      () async {
        when(
          () => api.get('/v1/invites/$_token'),
        ).thenThrow(_http(410, _fail('invitation_invalid')));
        (await repo.preview(_token)).fold((f) {
          expect(f, isA<ConflictFailure>());
          expect(f.code, InviteRepository.goneCode);
          expect(isTerminalInviteFailure(f), isTrue);
        }, (_) => fail('expected 410'));
      },
    );

    test(
      'accept: complex → membership only; family → device + the member\'s own pending subscription',
      () async {
        when(() => api.post('/v1/invites/$_token/accept')).thenAnswer(
          (_) async => _res(
            _envelope({
              'kind': 'complex_resident',
              'complex': {'id': 3, 'name': 'Park'},
            }),
          ),
        );
        (await repo.accept(_token)).fold((_) => fail('accept'), (a) {
          expect(
            (a.kind, a.complexId, a.subscriptionId),
            (InviteKind.complexResident, 3, null),
          );
        });

        when(() => api.post('/v1/invites/$_token/accept')).thenAnswer(
          (_) async => _res(
            _envelope({
              'kind': 'family_member',
              'family_link_id': 9,
              'device': {'id': 5, 'label': 'Darvaza'},
              'subscription_id': 44,
            }),
          ),
        );
        (await repo.accept(_token)).fold((_) => fail('accept'), (a) {
          expect(
            (a.kind, a.deviceId, a.deviceLabel, a.subscriptionId),
            (InviteKind.familyMember, 5, 'Darvaza', 44),
          );
        });
      },
    );

    test(
      'refusal codes map to messages; an email mismatch keeps the token, the rest drop it',
      () async {
        when(
          () => api.post('/v1/invites/$_token/accept'),
        ).thenThrow(_http(403, _fail('invitation_email_mismatch')));
        (await repo.accept(_token)).fold((f) {
          expect(complexFailureMessage(l, f), l.invErrEmailMismatch);
          expect(isTerminalInviteFailure(f), isFalse);
        }, (_) => fail('expected 403'));

        when(
          () => api.post('/v1/invites/$_token/accept'),
        ).thenThrow(_http(409, _fail('invitation_already_has_access')));
        (await repo.accept(_token)).fold((f) {
          expect(complexFailureMessage(l, f), l.invErrAlreadyHasAccess);
          expect(isTerminalInviteFailure(f), isTrue);
        }, (_) => fail('expected 409'));
      },
    );
  });

  test('request logging never prints an invitation token', () {
    expect(
      LoggingInterceptor.redactPath('/v1/invites/$_token'),
      '/v1/invites/<redacted>',
    );
    expect(
      LoggingInterceptor.redactPath('/v1/invites/$_token/accept'),
      '/v1/invites/<redacted>/accept',
    );
    expect(
      LoggingInterceptor.redactPath('/v1/complexes/3/devices'),
      '/v1/complexes/3/devices',
    );
  });

  group('ComplexRepository / payments (B7, B1 contracts)', () {
    late MockApiClient api;
    setUp(() => api = MockApiClient());

    test(
      'devices carry only the monthly price and the caller\'s own status',
      () async {
        when(() => api.get('/v1/complexes/3/devices')).thenAnswer(
          (_) async => _res({
            'data': [
              {
                'id': 7,
                'label': 'Giriş',
                'subscription_price_minor': 1200,
                'subscription_term_days': 30,
                'currency': 'AZN',
                'my_subscription_status': 'pending_payment',
              },
              {'id': 8, 'label': 'Park', 'my_subscription_status': 'active'},
            ],
          }),
        );
        (await ComplexRepository(api).devices(3)).fold((_) => fail('devices'), (
          list,
        ) {
          expect(list.first.myStatus, MySubscriptionStatus.pendingPayment);
          expect(list.first.canStartSubscription, isTrue);
          expect(list.last.canStartSubscription, isFalse);
          expect(
            formatMinor(list.first.subscriptionPriceMinor!, 'AZN'),
            '12.00 AZN',
          );
        });
      },
    );

    test(
      'subscribe sends an Idempotency-Key and returns the order for checkout',
      () async {
        when(
          () => api.post(
            '/v1/complexes/3/devices/7/subscribe',
            data: any(named: 'data'),
            headers: any(named: 'headers'),
          ),
        ).thenAnswer(
          (_) async => _res({
            'id': 91,
            'reference': 'r',
            'status': 'pending',
            'amount_minor': 1200,
          }, 201),
        );
        (await ComplexRepository(api).subscribe(
          3,
          7,
        )).fold((_) => fail('subscribe'), (o) => expect(o.id, 91));
        final headers =
            verify(
                  () => api.post(
                    '/v1/complexes/3/devices/7/subscribe',
                    data: any(named: 'data'),
                    headers: captureAny(named: 'headers'),
                  ),
                ).captured.single
                as Map;
        expect(
          (headers['Idempotency-Key'] as String).startsWith('subscribe-3-7-'),
          isTrue,
        );
      },
    );

    test(
      'pending list = the caller\'s own pending_payment subscriptions',
      () async {
        when(
          () => api.get(
            '/v1/subscriptions',
            query: {'status': 'pending_payment'},
          ),
        ).thenAnswer(
          (_) async => _res({
            'data': [
              {
                'id': 44,
                'tier': 'additional',
                'device_id': 5,
                'price_minor': 1200,
                'currency': 'AZN',
                'term_days': 30,
              },
            ],
          }),
        );
        (await ComplexRepository(api).pendingSubscriptions()).fold(
          (_) => fail('pending'),
          (list) {
            expect(list.single.isAdditional, isTrue);
            expect(list.single.deviceId, 5);
          },
        );
      },
    );

    test(
      'paying a pending subscription orders its own tier (sub_additional / sub_main)',
      () async {
        when(
          () => api.post(
            '/v1/orders',
            data: any(named: 'data'),
            headers: any(named: 'headers'),
          ),
        ).thenAnswer(
          (_) async => _res({
            'id': 92,
            'reference': 'r',
            'status': 'pending',
            'amount_minor': 1200,
          }, 201),
        );
        final repo = PaymentsRepository(api);
        await repo.payPendingSubscription(44, 'additional');
        await repo.payPendingSubscription(45, 'main');
        final bodies = verify(
          () => api.post(
            '/v1/orders',
            data: captureAny(named: 'data'),
            headers: any(named: 'headers'),
          ),
        ).captured;
        expect((bodies[0] as Map)['purpose'], 'sub_additional');
        expect(((bodies[0] as Map)['items'] as List).single, {
          'item_type': 'sub_additional',
          'referenced_id': 44,
          'quantity': 1,
        });
        expect((bodies[1] as Map)['purpose'], 'sub_main');
      },
    );
  });

  test(
    'register sends invitation_token only in the invitation flow (pre-flight; legacy path unchanged)',
    () async {
      final api = MockApiClient();
      final ds = AuthRemoteDataSource(api, _MockDeviceInfo());
      when(
        () => api.post('/v1/auth/register', data: any(named: 'data')),
      ).thenAnswer(
        (_) async => _res({
          'success': true,
          'data': null,
          'meta': {'expires_in_seconds': 600},
        }, 202),
      );
      await ds.register(
        firstName: 'A',
        lastName: 'B',
        phone: '+994500000001',
        email: 'a@x.az',
      );
      await ds.register(
        firstName: 'A',
        lastName: 'B',
        phone: '+994500000001',
        email: 'a@x.az',
        invitationToken: _token,
      );
      final calls = verify(
        () => api.post('/v1/auth/register', data: captureAny(named: 'data')),
      ).captured;
      expect((calls[0] as Map).containsKey('invitation_token'), isFalse);
      expect((calls[1] as Map)['invitation_token'], _token);
    },
  );

  group('InviteLandingScreen', () {
    late _MemStore mem;
    late PendingInviteStore store;
    late _MockInviteRepo repo;
    late List<String> visited;

    setUp(() async {
      mem = _MemStore();
      store = PendingInviteStore(mem);
      repo = _MockInviteRepo();
      visited = [];
    });

    Future<void> pump(
      WidgetTester tester,
      AuthState auth, {
      bool autoAccept = false,
    }) async {
      GoRoute stub(String path) => GoRoute(
        path: path,
        builder: (_, s) {
          visited.add(s.uri.toString());
          return Scaffold(body: Text('at:${s.uri}'));
        },
      );
      final router = GoRouter(
        initialLocation: '/invite',
        routes: [
          GoRoute(
            path: '/invite',
            builder: (_, _) => InviteLandingScreen(autoAccept: autoAccept),
          ),
          stub('/home'),
          stub('/welcome'),
          stub('/auth/login'),
          stub('/auth/register'),
          stub('/complex/:id'),
          stub('/payments/pending'),
        ],
      );
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            pendingInviteStoreProvider.overrideWithValue(store),
            inviteRepositoryProvider.overrideWithValue(repo),
            authStateProvider.overrideWith(() => _FixedAuth(auth)),
            sessionRolesProvider.overrideWith(
              (ref) async => throw UnimplementedError(),
            ),
          ],
          child: MaterialApp.router(
            routerConfig: router,
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            supportedLocales: AppLocalizations.supportedLocales,
            locale: const Locale('az'),
          ),
        ),
      );
      await tester.pumpAndSettle();
    }

    testWidgets(
      'no pending token → "invalid" state (the token is never in the route)',
      (tester) async {
        await pump(tester, AuthState.guest);
        expect(find.byKey(const Key('inv-gone')), findsOneWidget);
        verifyNever(() => repo.preview(any()));
      },
    );

    testWidgets(
      'a dead link (410) shows the invalid state and drops the stored token',
      (tester) async {
        await store.save(_token);
        when(() => repo.preview(_token)).thenAnswer(
          (_) async => const Err(ConflictFailure('invitation_invalid', '')),
        );
        await pump(tester, AuthState.guest);
        expect(find.byKey(const Key('inv-gone')), findsOneWidget);
        expect(await store.read(), isNull);
      },
    );

    testWidgets(
      'guest sees the invitation with register / log in (no accept)',
      (tester) async {
        await store.save(_token);
        when(
          () => repo.preview(_token),
        ).thenAnswer((_) async => const Success(_complexPreview));
        await pump(tester, AuthState.guest);
        expect(find.text('Gənclik Park'), findsOneWidget);
        expect(find.text(l.invSentTo('a****@x.az')), findsOneWidget);
        expect(find.byKey(const Key('inv-accept')), findsNothing);
        await tester.tap(find.byKey(const Key('inv-register')));
        await tester.pumpAndSettle();
        expect(visited.last, '/auth/register?invite=1');
        expect(visited.last.contains(_token), isFalse);
      },
    );

    testWidgets(
      'signed in: accept (complex) clears the token and opens the complex',
      (tester) async {
        await store.save(_token);
        when(
          () => repo.preview(_token),
        ).thenAnswer((_) async => const Success(_complexPreview));
        when(() => repo.accept(_token)).thenAnswer(
          (_) async => const Success(
            InviteAcceptance(
              kind: InviteKind.complexResident,
              complexId: 3,
              complexName: 'Gənclik Park',
            ),
          ),
        );
        await pump(tester, AuthState.authenticated);
        await tester.tap(find.byKey(const Key('inv-accept')));
        await tester.pumpAndSettle();
        verify(() => repo.accept(_token)).called(1);
        expect(
          visited,
          containsAll(['/home', '/complex/3']),
        ); // Home underneath, the complex on top
        expect(find.text('at:/complex/3'), findsOneWidget);
        expect(await store.read(), isNull);
      },
    );

    testWidgets(
      'after an invitation registration the claim runs automatically (family → pending payment)',
      (tester) async {
        await store.save(_token);
        when(() => repo.preview(_token)).thenAnswer(
          (_) async => const Success(
            InvitePreview(kind: InviteKind.familyMember, inviterName: 'Rəşad'),
          ),
        );
        when(() => repo.accept(_token)).thenAnswer(
          (_) async => const Success(
            InviteAcceptance(
              kind: InviteKind.familyMember,
              deviceId: 5,
              subscriptionId: 44,
            ),
          ),
        );
        await pump(tester, AuthState.authenticated, autoAccept: true);
        verify(() => repo.accept(_token)).called(1);
        expect(visited, containsAll(['/home', '/payments/pending']));
        expect(find.text('at:/payments/pending'), findsOneWidget);
      },
    );

    testWidgets(
      'email mismatch: message + log out offered, token kept for the right account',
      (tester) async {
        await store.save(_token);
        when(
          () => repo.preview(_token),
        ).thenAnswer((_) async => const Success(_complexPreview));
        when(() => repo.accept(_token)).thenAnswer(
          (_) async =>
              const Err(ForbiddenFailure('x', 'invitation_email_mismatch')),
        );
        await pump(tester, AuthState.authenticated);
        await tester.tap(find.byKey(const Key('inv-accept')));
        await tester.pumpAndSettle();
        expect(find.text(l.invErrEmailMismatch), findsOneWidget);
        expect(find.text(l.logout), findsOneWidget);
        expect(await store.read(), _token);
      },
    );
  });
}
