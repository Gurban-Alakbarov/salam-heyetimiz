import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/devices/devices_providers.dart';
import 'package:salam_mobile/features/devices/domain/entity/device_entities.dart';
import 'package:salam_mobile/features/devices/presentation/widgets/device_card.dart';
import 'package:salam_mobile/features/family/data/family_repository.dart';
import 'package:salam_mobile/features/family/domain/family_entities.dart';
import 'package:salam_mobile/features/family/family_providers.dart';
import 'package:salam_mobile/features/family/presentation/family_failure.dart';
import 'package:salam_mobile/features/family/presentation/family_screen.dart';
import 'package:salam_mobile/features/payments/data/payments_repository.dart';
import 'package:salam_mobile/features/payments/domain/payment_entities.dart';
import 'package:salam_mobile/features/payments/payments_providers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/l10n/app_localizations_az.dart';

import '../../helpers/mocks.dart';

class _MockFamilyRepo extends Mock implements FamilyRepository {}

class _MockPaymentsRepo extends Mock implements PaymentsRepository {}

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

Map<String, dynamic> _err(String code) => {
  'error': {'code': code, 'message': 'x'},
};

Map<String, dynamic> _memberJson({String subStatus = 'pending_payment'}) => {
  'family_link_id': 9,
  'user_id': 4,
  'full_name': 'Leyla Üzv',
  'email': 'uzv@x.az',
  'phone_masked': '+994 50 *** ** 05',
  'linked_at': '2026-10-02T10:00:00+04:00',
  'devices': [
    {
      'device_id': 1,
      'label': 'Giriş',
      'subscription': {
        'id': 44,
        'status': subStatus,
        'tier': 'additional',
        'price_minor': 1200,
        'term_days': 30,
        'currency': 'AZN',
        'ends_at': null,
      },
    },
  ],
};

Map<String, dynamic> _inv(int id, String status) => {
  'id': id,
  'device_id': 1,
  'first_name': 'Fam',
  'last_name': 'M$id',
  'email': 'f$id@x.az',
  'status': status,
  'expires_at': '2026-10-09T10:00:00+04:00',
  'send_count': 1,
};

void main() {
  setUpAll(() => registerFallbackValue(<String, dynamic>{}));
  final l = AppLocalizationsAz();

  group('B8 family read models', () {
    test(
      'a member carries the relation, the granted device (access) and its own additional subscription',
      () {
        final m = FamilyMember.fromJson(_memberJson());
        expect((m.familyLinkId, m.userId, m.displayName), (9, 4, 'Leyla Üzv'));
        final grant = m.devices.single;
        expect(grant.deviceId, 1);
        expect(grant.subscription!.tier, 'additional');
        expect(grant.subscription!.isPendingPayment, isTrue);
      },
    );

    test(
      'invitation actions mirror InvitationService; tabs group cancelled + declined',
      () {
        final s = [
          'pending',
          'accepted',
          'expired',
          'cancelled',
          'declined',
        ].map((x) => FamilyInvitation.fromJson(_inv(1, x))).toList();
        expect(s.map((i) => (i.canResend, i.canRevoke)).toList(), [
          (true, true),
          (false, false),
          (true, false),
          (false, false),
          (false, false),
        ]);
        expect(s.map((i) => i.tab).toList(), [
          FamilyInvitationTab.pending,
          FamilyInvitationTab.accepted,
          FamilyInvitationTab.expired,
          FamilyInvitationTab.closed,
          FamilyInvitationTab.closed,
        ]);
      },
    );
  });

  group('FamilyRepository (B8 contracts, no new endpoint)', () {
    late MockApiClient api;
    late FamilyRepository repo;
    setUp(() {
      api = MockApiClient();
      repo = FamilyRepository(api);
    });

    test(
      'device invitations: 200 → the head\'s list; 403 / 404 → not a head there (null)',
      () async {
        when(() => api.get('/v1/devices/1/invitations')).thenAnswer(
          (_) async => _res({
            'data': [_inv(5, 'pending')],
          }),
        );
        when(
          () => api.get('/v1/devices/2/invitations'),
        ).thenThrow(_http(403, {'message': 'Forbidden'}));
        when(
          () => api.get('/v1/devices/3/invitations'),
        ).thenThrow(_http(404, {'message': 'Not found'}));
        (await repo.deviceInvitations(
          1,
        )).fold((_) => fail('1'), (v) => expect(v!.single.id, 5));
        (await repo.deviceInvitations(
          2,
        )).fold((_) => fail('2'), (v) => expect(v, isNull));
        (await repo.deviceInvitations(
          3,
        )).fold((_) => fail('3'), (v) => expect(v, isNull));
      },
    );

    test(
      'invite posts name + email to the device; granted vs emailed invitation',
      () async {
        when(
          () => api.post('/v1/devices/1/invitations', data: any(named: 'data')),
        ).thenAnswer(
          (_) async => _res({
            'data': {'granted': false, 'invitation': _inv(6, 'pending')},
          }, 201),
        );
        (await repo.invite(
          deviceId: 1,
          firstName: 'Fam',
          lastName: 'Üzv',
          email: 'f@x.az',
        )).fold((_) => fail('invite'), (r) {
          expect(r.granted, isFalse);
          expect(r.invitation!.id, 6);
        });
        final sent =
            verify(
                  () => api.post(
                    '/v1/devices/1/invitations',
                    data: captureAny(named: 'data'),
                  ),
                ).captured.single
                as Map;
        expect(sent, {
          'first_name': 'Fam',
          'last_name': 'Üzv',
          'email': 'f@x.az',
        });

        when(
          () => api.post('/v1/devices/1/invitations', data: any(named: 'data')),
        ).thenAnswer(
          (_) async => _res({
            'data': {
              'granted': true,
              'device': {'device_id': 1},
            },
          }, 201),
        );
        (await repo.invite(
          deviceId: 1,
          firstName: 'a',
          lastName: 'b',
          email: 'c@x.az',
        )).fold((_) => fail('grant'), (r) => expect(r.granted, isTrue));
      },
    );

    test('resend / revoke / remove hit their endpoints', () async {
      when(
        () => api.post('/v1/family/invitations/6/resend'),
      ).thenAnswer((_) async => _res({'data': _inv(6, 'pending')}));
      when(
        () => api.post('/v1/family/invitations/6/revoke'),
      ).thenAnswer((_) async => _res({'data': _inv(6, 'cancelled')}));
      when(() => api.delete('/v1/family/members/4')).thenAnswer(
        (_) async => _res({
          'data': {'revoked_rows': 1, 'cancelled_subscriptions': 1},
        }),
      );
      (await repo.resend(
        6,
      )).fold((_) => fail('resend'), (i) => expect(i.status, 'pending'));
      (await repo.revoke(
        6,
      )).fold((_) => fail('revoke'), (i) => expect(i.status, 'cancelled'));
      (await repo.removeMember(4)).fold(
        (_) => fail('remove'),
        (r) => expect(r.cancelledSubscriptions, 1),
      );
    });

    test(
      'refusals map to family messages (duplicate / existing access / not head / invalid target / rate limit)',
      () async {
        Future<Failure> failing(DioException e) async {
          when(
            () =>
                api.post('/v1/devices/1/invitations', data: any(named: 'data')),
          ).thenThrow(e);
          return (await repo.invite(
            deviceId: 1,
            firstName: 'a',
            lastName: 'b',
            email: 'c@x.az',
          )).fold((f) => f, (_) => throw 'expected failure');
        }

        expect(
          familyFailureMessage(
            l,
            await failing(_http(409, _err('invitation_already_pending'))),
          ),
          l.kmErrAlreadyPending,
        );
        expect(
          familyFailureMessage(
            l,
            await failing(_http(409, _err('invitation_already_has_access'))),
          ),
          l.invErrAlreadyHasAccess,
        );
        expect(
          familyFailureMessage(
            l,
            await failing(_http(403, {'message': 'Forbidden'})),
          ),
          l.famErrNotHead,
        );
        expect(
          familyFailureMessage(
            l,
            await failing(_http(422, _err('invitation_invalid_target'))),
          ),
          l.famErrInvalidTarget,
        );
        expect(
          familyFailureMessage(
            l,
            await failing(_http(429, _err('invitation_rate_limited'))),
          ),
          l.kmErrRateLimited,
        );
      },
    );
  });

  test(
    'managed devices = the devices whose invitations endpoint answers (the server decides who heads)',
    () async {
      final repo = _MockFamilyRepo();
      when(() => repo.deviceInvitations(1)).thenAnswer(
        (_) async => Success([FamilyInvitation.fromJson(_inv(5, 'pending'))]),
      );
      when(
        () => repo.deviceInvitations(2),
      ).thenAnswer((_) async => const Success(null)); // member / not head
      final container = ProviderContainer(
        overrides: [
          familyRepositoryProvider.overrideWithValue(repo),
          deviceListProvider.overrideWith(
            (ref) async => const DevicePage(
              devices: [
                Device(id: 1, label: 'Own gate', status: 'active'),
                Device(id: 2, label: 'Granted gate', status: 'active'),
              ],
            ),
          ),
        ],
      );
      addTearDown(container.dispose);
      final managed = await container.read(managedDevicesProvider.future);
      expect(managed.map((d) => d.deviceId), [1]);
      expect(managed.single.invitations.single.id, 5);
    },
  );

  group('FamilyScreen', () {
    late _MockFamilyRepo repo;
    late _MockPaymentsRepo payments;
    late List<String> visited;

    setUp(() {
      repo = _MockFamilyRepo();
      payments = _MockPaymentsRepo();
      visited = [];
    });

    Future<void> pump(
      WidgetTester tester, {
      required List<ManagedDevice> managed,
      List<FamilyMember> members = const [],
    }) async {
      final router = GoRouter(
        initialLocation: '/family',
        routes: [
          GoRoute(path: '/family', builder: (_, _) => const FamilyScreen()),
          GoRoute(
            path: '/checkout/:id',
            builder: (_, s) {
              visited.add(s.uri.toString());
              return const Scaffold(body: Text('checkout'));
            },
          ),
          GoRoute(
            path: '/family/invite',
            builder: (_, s) {
              visited.add(s.uri.toString());
              return const Scaffold(body: Text('invite'));
            },
          ),
        ],
      );
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            familyRepositoryProvider.overrideWithValue(repo),
            paymentsRepositoryProvider.overrideWithValue(payments),
            managedDevicesProvider.overrideWith((ref) async => managed),
            familyMembersProvider.overrideWith((ref) async => members),
            deviceListProvider.overrideWith(
              (ref) async => const DevicePage(devices: []),
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

    testWidgets('no device the caller heads → explained, no invite button', (
      tester,
    ) async {
      await pump(tester, managed: const []);
      expect(find.text(l.famNotHead), findsOneWidget);
      expect(find.byKey(const Key('fam-invite')), findsNothing);
    });

    testWidgets(
      'head pays the member\'s own pending additional subscription through the B13 checkout',
      (tester) async {
        when(
          () => payments.payPendingSubscription(44, 'additional'),
        ).thenAnswer(
          (_) async => Success(
            PaymentOrder.fromJson({
              'id': 91,
              'reference': 'r',
              'status': 'pending',
              'amount_minor': 1200,
            }),
          ),
        );
        await pump(
          tester,
          managed: const [ManagedDevice(deviceId: 1, label: 'Giriş')],
          members: [FamilyMember.fromJson(_memberJson())],
        );

        expect(find.text('Leyla Üzv'), findsOneWidget);
        expect(find.text(l.cxStatusPending), findsOneWidget);
        await tester.tap(find.byKey(const Key('fam-pay-44')));
        await tester.pumpAndSettle();
        verify(
          () => payments.payPendingSubscription(44, 'additional'),
        ).called(1);
        expect(visited, ['/checkout/91']);
      },
    );

    testWidgets(
      'a refused payment (403) shows the server refusal and opens no checkout',
      (tester) async {
        when(
          () => payments.payPendingSubscription(44, 'additional'),
        ).thenAnswer(
          (_) async => const Err(ForbiddenFailure('x', 'forbidden')),
        );
        await pump(
          tester,
          managed: const [ManagedDevice(deviceId: 1, label: 'Giriş')],
          members: [FamilyMember.fromJson(_memberJson())],
        );
        await tester.tap(find.byKey(const Key('fam-pay-44')));
        await tester.pumpAndSettle();
        expect(find.text(l.payErrForbidden), findsOneWidget);
        expect(visited, isEmpty);
      },
    );

    testWidgets(
      'an active member shows no pay button; removal asks first, then calls the API',
      (tester) async {
        when(() => repo.removeMember(4)).thenAnswer(
          (_) async => const Success(
            FamilyRemoval(revokedRows: 1, cancelledSubscriptions: 1),
          ),
        );
        await pump(
          tester,
          managed: const [ManagedDevice(deviceId: 1, label: 'Giriş')],
          members: [FamilyMember.fromJson(_memberJson(subStatus: 'active'))],
        );
        expect(find.byKey(const Key('fam-pay-44')), findsNothing);
        expect(find.text(l.cxStatusActive), findsOneWidget);

        await tester.tap(find.byKey(const Key('fam-remove-4')));
        await tester.pumpAndSettle();
        expect(find.text(l.famRemoveBody('Leyla Üzv')), findsOneWidget);
        await tester.tap(find.byKey(const Key('fam-remove-confirm')));
        await tester.pumpAndSettle();
        verify(() => repo.removeMember(4)).called(1);
        expect(find.text(l.famRemoved), findsOneWidget);
      },
    );

    testWidgets(
      'invitations tab: statuses, resend for pending / expired, revoke (confirmed) only for pending',
      (tester) async {
        when(() => repo.revoke(5)).thenAnswer(
          (_) async => Success(FamilyInvitation.fromJson(_inv(5, 'cancelled'))),
        );
        final invs = [
          _inv(5, 'pending'),
          _inv(6, 'expired'),
          _inv(7, 'cancelled'),
        ].map(FamilyInvitation.fromJson).toList();
        await pump(
          tester,
          managed: [
            ManagedDevice(deviceId: 1, label: 'Giriş', invitations: invs),
          ],
        );
        await tester.tap(find.text(l.famTabInvitations));
        await tester.pumpAndSettle();

        expect(find.text(l.kmTabPending), findsOneWidget);
        expect(find.text(l.kmTabExpired), findsOneWidget);
        expect(find.text(l.kmTabClosed), findsOneWidget);
        expect(find.byKey(const Key('fam-resend-5')), findsOneWidget);
        expect(find.byKey(const Key('fam-resend-6')), findsOneWidget);
        expect(find.byKey(const Key('fam-revoke-6')), findsNothing);
        expect(find.byKey(const Key('fam-resend-7')), findsNothing);

        await tester.ensureVisible(find.byKey(const Key('fam-revoke-5')));
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const Key('fam-revoke-5')));
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const Key('fam-revoke-confirm')));
        await tester.pumpAndSettle();
        verify(() => repo.revoke(5)).called(1);
      },
    );
  });

  testWidgets(
    'device card (redesign): status, name, visitor invite + directions, open, and a ⋮ menu with info + family',
    (tester) async {
      final visited = <String>[];
      final router = GoRouter(
        initialLocation: '/',
        routes: [
          GoRoute(
            path: '/',
            builder: (_, _) => Scaffold(
              body: SingleChildScrollView(
                child: DeviceCard(
                  device: Device(
                    id: 7,
                    label: 'Sinam Giriş',
                    status: 'active',
                    canOpen: true,
                    lastOnlineAt: DateTime.now(),
                  ),
                ),
              ),
            ),
          ),
          GoRoute(
            path: '/family',
            builder: (_, s) {
              visited.add(s.uri.toString());
              return const Scaffold(body: Text('family'));
            },
          ),
        ],
      );
      await tester.pumpWidget(
        ProviderScope(
          child: MaterialApp.router(
            routerConfig: router,
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            supportedLocales: AppLocalizations.supportedLocales,
            locale: const Locale('az'),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Sinam Giriş'), findsOneWidget);
      expect(find.text('Online'), findsOneWidget);
      expect(find.byKey(const Key('device-invite-7')), findsOneWidget);
      expect(find.byKey(const Key('device-directions-7')), findsOneWidget);
      expect(find.text('Qapını Aç'), findsOneWidget);

      await tester.tap(find.byKey(const Key('device-menu-7')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('device-menu-info')), findsOneWidget);
      await tester.tap(find.byKey(const Key('device-menu-family')));
      await tester.pumpAndSettle();
      expect(visited, ['/family?device=7']);
    },
  );
}
