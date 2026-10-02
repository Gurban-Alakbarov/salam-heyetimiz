import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/komendant/data/komendant_repository.dart';
import 'package:salam_mobile/features/komendant/domain/komendant_entities.dart';
import 'package:salam_mobile/features/komendant/komendant_providers.dart';
import 'package:salam_mobile/features/komendant/presentation/komendant_failure.dart';
import 'package:salam_mobile/features/komendant/presentation/komendant_invitations_screen.dart';
import 'package:salam_mobile/features/komendant/presentation/komendant_residents_screen.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/l10n/app_localizations_az.dart';

import '../../helpers/mocks.dart';

class _MockRepo extends Mock implements KomendantRepository {}

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

Map<String, dynamic> _inv(int id, String status) => {
  'id': id,
  'first_name': 'Aysel',
  'last_name': 'M$id',
  'email': 'a$id@x.az',
  'status': status,
  'expires_at': '2026-10-09T10:00:00+04:00',
  'send_count': 1,
};

Widget _app(Widget child, List overrides) => ProviderScope(
  overrides: [...overrides],
  child: MaterialApp(
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: AppLocalizations.supportedLocales,
    locale: const Locale('az'),
    home: child,
  ),
);

void main() {
  setUpAll(() => registerFallbackValue(<String, dynamic>{}));
  final l = AppLocalizationsAz();

  group('B5 read models', () {
    test('complex stats + device subscription price (no sale price)', () {
      final c = KomendantComplex.fromJson({
        'id': 3,
        'name': 'Park',
        'address': 'Bakı',
        'stats': {'devices': 2, 'residents': 5, 'pending_invitations': 1},
      });
      expect((c.devices, c.residents, c.pendingInvitations), (2, 5, 1));
      final d = KomendantDevice.fromJson({
        'id': 9,
        'label': 'Giriş',
        'status': 'active',
        'online': true,
        'subscription_price_minor': 1200,
        'subscription_term_days': 30,
        'currency': 'AZN',
      });
      expect(d.subscriptionPriceMinor, 1200);
      expect(d.subscriptionTermDays, 30);
      expect(d.online, isTrue);
    });

    test(
      'invitation actions mirror InvitationService; tabs group cancelled + declined',
      () {
        final pending = KomendantInvitation.fromJson(_inv(1, 'pending'));
        final expired = KomendantInvitation.fromJson(_inv(2, 'expired'));
        final accepted = KomendantInvitation.fromJson(_inv(3, 'accepted'));
        final cancelled = KomendantInvitation.fromJson(_inv(4, 'cancelled'));
        final declined = KomendantInvitation.fromJson(_inv(5, 'declined'));
        expect((pending.canResend, pending.canRevoke), (true, true));
        expect((expired.canResend, expired.canRevoke), (true, false));
        expect((accepted.canResend, accepted.canRevoke), (false, false));
        expect(cancelled.canResend, isFalse);
        expect(
          [pending, expired, accepted, cancelled, declined].map((i) => i.tab),
          [
            InvitationTab.pending,
            InvitationTab.expired,
            InvitationTab.accepted,
            InvitationTab.closed,
            InvitationTab.closed,
          ],
        );
        expect(pending.fullName, 'Aysel M1');
      },
    );

    test('resident row', () {
      final r = KomendantResident.fromJson({
        'user_id': 7,
        'full_name': 'Rauf',
        'phone_masked': '+994 50 *** ** 01',
        'active_subscriptions': 2,
      });
      expect((r.userId, r.activeSubscriptions), (7, 2));
    });
  });

  group('KomendantRepository (B5 contracts)', () {
    late MockApiClient api;
    late KomendantRepository repo;
    setUp(() {
      api = MockApiClient();
      repo = KomendantRepository(api);
    });

    test('GETs complex / devices / residents / invitations', () async {
      when(() => api.get('/v1/komendant/complex')).thenAnswer(
        (_) async => _res({
          'data': {'id': 1, 'name': 'P', 'stats': {}},
        }),
      );
      when(() => api.get('/v1/komendant/devices')).thenAnswer(
        (_) async => _res({
          'data': [
            {'id': 1, 'label': 'A'},
          ],
        }),
      );
      when(
        () => api.get('/v1/komendant/residents'),
      ).thenAnswer((_) async => _res({'data': []}));
      when(() => api.get('/v1/komendant/invitations')).thenAnswer(
        (_) async => _res({
          'data': [_inv(1, 'pending')],
        }),
      );
      expect((await repo.complex()).isSuccess, isTrue);
      (await repo.devices()).fold(
        (_) => fail('devices'),
        (v) => expect(v.single.label, 'A'),
      );
      (await repo.residents()).fold(
        (_) => fail('residents'),
        (v) => expect(v, isEmpty),
      );
      (await repo.invitations()).fold(
        (_) => fail('invitations'),
        (v) => expect(v.single.id, 1),
      );
    });

    test('invite sends only name + email (complex is server-scoped)', () async {
      when(
        () => api.post('/v1/komendant/invitations', data: any(named: 'data')),
      ).thenAnswer((_) async => _res({'data': _inv(5, 'pending')}, 201));
      final r = await repo.invite(
        firstName: 'Aysel',
        lastName: 'M',
        email: 'a@x.az',
      );
      expect(r.isSuccess, isTrue);
      final sent =
          verify(
                () => api.post(
                  '/v1/komendant/invitations',
                  data: captureAny(named: 'data'),
                ),
              ).captured.single
              as Map;
      expect(sent, {
        'first_name': 'Aysel',
        'last_name': 'M',
        'email': 'a@x.az',
      });
    });

    test('resend / revoke / remove hit their endpoints', () async {
      when(
        () => api.post('/v1/komendant/invitations/5/resend'),
      ).thenAnswer((_) async => _res({'data': _inv(5, 'pending')}));
      when(
        () => api.post('/v1/komendant/invitations/5/revoke'),
      ).thenAnswer((_) async => _res({'data': _inv(5, 'cancelled')}));
      when(() => api.delete('/v1/komendant/residents/7')).thenAnswer(
        (_) async => _res({
          'data': {'revoked_rows': 2, 'cancelled_subscriptions': 1},
        }),
      );
      (await repo.resend(
        5,
      )).fold((_) => fail('resend'), (v) => expect(v.status, 'pending'));
      (await repo.revoke(
        5,
      )).fold((_) => fail('revoke'), (v) => expect(v.status, 'cancelled'));
      (await repo.removeResident(7)).fold(
        (_) => fail('remove'),
        (v) => expect(v.cancelledSubscriptions, 1),
      );
    });

    test(
      'maps 403 not_komendant, 409 codes and 429 to Komendant messages',
      () async {
        when(() => api.get('/v1/komendant/complex')).thenThrow(
          _http(403, {
            'error': {'code': 'not_komendant', 'message': 'x'},
          }),
        );
        (await repo.complex()).fold(
          (f) => expect(komendantFailureMessage(l, f), l.kmErrNotKomendant),
          (_) => fail('expected failure'),
        );

        when(
          () => api.post('/v1/komendant/invitations', data: any(named: 'data')),
        ).thenThrow(
          _http(409, {
            'error': {'code': 'already_resident', 'message': 'x'},
          }),
        );
        (await repo.invite(
          firstName: 'a',
          lastName: 'b',
          email: 'c@x.az',
        )).fold(
          (f) => expect(komendantFailureMessage(l, f), l.kmErrAlreadyResident),
          (_) => fail('expected failure'),
        );

        when(() => api.post('/v1/komendant/invitations/5/resend')).thenThrow(
          _http(429, {
            'error': {'code': 'invitation_rate_limited', 'message': 'x'},
          }),
        );
        (await repo.resend(5)).fold(
          (f) => expect(komendantFailureMessage(l, f), l.kmErrRateLimited),
          (_) => fail('expected failure'),
        );

        expect(
          komendantFailureMessage(
            l,
            const ConflictFailure('invitation_already_pending', 'x'),
          ),
          l.kmErrAlreadyPending,
        );
        expect(
          komendantFailureMessage(l, const ForbiddenFailure('x', 'forbidden')),
          l.kmErrForbidden,
        );
      },
    );
  });

  testWidgets(
    'Invitations: status tabs; revoke asks first, then calls the API',
    (tester) async {
      final repo = _MockRepo();
      when(() => repo.revoke(1)).thenAnswer(
        (_) async =>
            Success(KomendantInvitation.fromJson(_inv(1, 'cancelled'))),
      );
      final items = [
        _inv(1, 'pending'),
        _inv(2, 'accepted'),
        _inv(3, 'declined'),
      ].map(KomendantInvitation.fromJson).toList();
      await tester.pumpWidget(
        _app(const KomendantInvitationsScreen(), [
          komendantRepositoryProvider.overrideWithValue(repo),
          komendantInvitationsProvider.overrideWith((ref) async => items),
          komendantComplexProvider.overrideWith(
            (ref) async => const KomendantComplex(id: 1, name: 'P'),
          ),
        ]),
      );
      await tester.pumpAndSettle();

      expect(find.text('Aysel M1'), findsOneWidget); // pending tab
      expect(find.text('Aysel M2'), findsNothing);
      expect(find.byKey(const Key('km-resend-1')), findsOneWidget);

      await tester.tap(find.byKey(const Key('km-revoke-1')));
      await tester.pumpAndSettle();
      await tester.tap(find.text(l.cancel));
      await tester.pumpAndSettle();
      verifyNever(() => repo.revoke(any()));

      await tester.tap(find.byKey(const Key('km-revoke-1')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('km-revoke-confirm')));
      await tester.pumpAndSettle();
      verify(() => repo.revoke(1)).called(1);
      expect(find.text(l.kmRevoked), findsOneWidget);

      await tester.tap(find.text(l.kmTabClosed));
      await tester.pumpAndSettle();
      expect(find.text('Aysel M3'), findsOneWidget);
      expect(find.text(l.kmStatusDeclined), findsOneWidget);
      expect(find.byKey(const Key('km-resend-3')), findsNothing);
    },
  );

  testWidgets(
    'Residents: "Çıxar" explains the effect and removes only after confirmation',
    (tester) async {
      final repo = _MockRepo();
      when(() => repo.removeResident(7)).thenAnswer(
        (_) async => const Success(
          ResidentRemoval(revokedRows: 1, cancelledSubscriptions: 1),
        ),
      );
      await tester.pumpWidget(
        _app(const KomendantResidentsScreen(), [
          komendantRepositoryProvider.overrideWithValue(repo),
          komendantResidentsProvider.overrideWith(
            (ref) async => [
              const KomendantResident(
                userId: 7,
                fullName: 'Rauf Sakin',
                activeSubscriptions: 1,
              ),
            ],
          ),
          komendantComplexProvider.overrideWith(
            (ref) async => const KomendantComplex(id: 1, name: 'P'),
          ),
        ]),
      );
      await tester.pumpAndSettle();
      expect(find.text(l.kmActiveSubs(1)), findsOneWidget);

      await tester.tap(find.byKey(const Key('km-remove-7')));
      await tester.pumpAndSettle();
      expect(find.text(l.kmRemoveBody('Rauf Sakin')), findsOneWidget);
      await tester.tap(find.byKey(const Key('km-remove-confirm')));
      await tester.pumpAndSettle();
      verify(() => repo.removeResident(7)).called(1);
      expect(find.text(l.kmRemoved), findsOneWidget);
    },
  );
}
