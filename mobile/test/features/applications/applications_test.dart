import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/core/services/device_info_service.dart';
import 'package:salam_mobile/features/applications/data/applications_repository.dart';
import 'package:salam_mobile/features/applications/domain/application_entities.dart';
import 'package:salam_mobile/features/applications/presentation/register_type_screen.dart';
import 'package:salam_mobile/features/auth/data/datasource/auth_remote_datasource.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

import '../../helpers/mocks.dart';

class _MockDeviceInfo extends Mock implements DeviceInfoService {}

Response<dynamic> _res(Object? data, [int status = 200]) =>
    Response<dynamic>(data: data, statusCode: status, requestOptions: RequestOptions(path: '/'));

DioException _http(int status, Map<String, dynamic> body) => DioException(
      requestOptions: RequestOptions(path: '/'),
      type: DioExceptionType.badResponse,
      response: Response<dynamic>(data: body, statusCode: status, requestOptions: RequestOptions(path: '/')),
    );

void main() {
  setUpAll(() => registerFallbackValue(<String, dynamic>{}));

  group('MyApplications (GET /v1/applications/mine)', () {
    Map<String, dynamic> mine(String? type, {List<Map<String, dynamic>> ind = const [], List<Map<String, dynamic>> legal = const []}) =>
        {'account_type': type, 'individual': ind, 'legal': legal};

    test('parses both kinds with OSM coordinates', () {
      final m = MyApplications.fromJson(mine('physical', ind: [
        {'id': 1, 'status': 'contacted', 'address': 'Bakı', 'location': {'latitude': 40.4, 'longitude': 49.8}, 'created_at': '2026-10-01T10:00:00+04:00'},
      ], legal: [
        {'id': 2, 'status': 'rejected', 'complex_name': 'Park', 'voen': '1234567891', 'address': 'A', 'rejection_reason': 'VÖEN', 'location': {'latitude': 40.1, 'longitude': 49.1}},
      ]));
      expect(m.accountType, AccountType.physical);
      expect(m.individual.single.location, const GeoPoint(40.4, 49.8));
      expect(m.individual.single.isOpen, isTrue);
      expect(m.legal.single.rejectionReason, 'VÖEN');
    });

    test('a typed account may only file its own kind; legacy NULL may file either', () {
      expect(MyApplications.fromJson(mine(null)).canFilePhysical, isTrue);
      expect(MyApplications.fromJson(mine(null)).canFileLegal, isTrue);
      expect(MyApplications.fromJson(mine('physical')).canFileLegal, isFalse);
      expect(MyApplications.fromJson(mine('legal')).canFilePhysical, isFalse);
      expect(MyApplications.fromJson(mine('legal')).canFileLegal, isTrue);
    });

    test('one open physical application at a time; a closed one allows a new one', () {
      final open = MyApplications.fromJson(mine('physical', ind: [{'id': 1, 'status': 'in_progress', 'address': 'x'}]));
      final closed = MyApplications.fromJson(mine('physical', ind: [{'id': 1, 'status': 'installed', 'address': 'x'}]));
      expect(open.canFilePhysical, isFalse);
      expect(closed.canFilePhysical, isTrue);
    });
  });

  group('client validation mirrors the backend', () {
    test('service area = Azerbaijan box', () {
      expect(const GeoPoint(40.4093, 49.8671).inServiceArea, isTrue);
      expect(const GeoPoint(0, 0).inServiceArea, isFalse);
      expect(const GeoPoint(40.4, 60.1).inServiceArea, isFalse);
      expect(const GeoPoint(42.5, 47.0).inServiceArea, isFalse);
    });

    test('VÖEN 10 digits; apartments optional 1..100000', () {
      expect(ApplicationValidators.isVoen('1234567891'), isTrue);
      expect(ApplicationValidators.isVoen('12345'), isFalse);
      expect(ApplicationValidators.isVoen('12345678ab'), isFalse);
      expect(ApplicationValidators.isApartmentsCount(''), isTrue);
      expect(ApplicationValidators.isApartmentsCount('120'), isTrue);
      expect(ApplicationValidators.isApartmentsCount('0'), isFalse);
      expect(ApplicationValidators.isApartmentsCount('abc'), isFalse);
    });
  });

  group('ApplicationsRepository (B9 contracts)', () {
    late MockApiClient api;
    late ApplicationsRepository repo;
    setUp(() {
      api = MockApiClient();
      repo = ApplicationsRepository(api);
    });

    test('POSTs the physical payload with lat/lng and omits an empty note', () async {
      when(() => api.post('/v1/applications/individual', data: any(named: 'data')))
          .thenAnswer((_) async => _res({'data': {'id': 5, 'status': 'new', 'address': 'Bakı'}}, 201));
      final r = await repo.submitIndividual(
          fullName: 'Rauf', phone: '+994501110001', email: 'r@x.az', address: 'Bakı', location: const GeoPoint(40.4, 49.8), note: '');
      expect(r.isSuccess, isTrue);
      final sent = verify(() => api.post('/v1/applications/individual', data: captureAny(named: 'data'))).captured.single as Map;
      expect(sent['latitude'], 40.4);
      expect(sent['longitude'], 49.8);
      expect(sent.containsKey('note'), isFalse);
      expect(sent.containsKey('account_type'), isFalse);
    });

    test('POSTs the legal payload; apartments only when given', () async {
      when(() => api.post('/v1/applications/legal', data: any(named: 'data')))
          .thenAnswer((_) async => _res({'data': {'id': 6, 'status': 'pending', 'complex_name': 'P', 'voen': '1234567891', 'address': 'A'}}, 201));
      await repo.submitLegal(
        complexName: 'P', legalName: 'P MMC', voen: '1234567891', legalAddress: 'L', contactPersonName: 'Leyla',
        contactPhone: '+994551112233', contactEmail: 'l@x.az', address: 'A', location: const GeoPoint(40.1, 49.1),
      );
      final sent = verify(() => api.post('/v1/applications/legal', data: captureAny(named: 'data'))).captured.single as Map;
      expect(sent['voen'], '1234567891');
      expect(sent.containsKey('apartments_count'), isFalse);
    });

    test('maps 409 refusal codes and 422 field errors', () async {
      when(() => api.post('/v1/applications/individual', data: any(named: 'data')))
          .thenThrow(_http(409, {'error': {'code': 'account_type_mismatch', 'message': 'x'}}));
      final conflict = await repo.submitIndividual(fullName: 'a', phone: 'b', email: 'c', address: 'd', location: const GeoPoint(40.4, 49.8));
      conflict.fold((f) => expect((f as ConflictFailure).code, 'account_type_mismatch'), (_) => fail('expected failure'));

      when(() => api.post('/v1/applications/legal', data: any(named: 'data')))
          .thenThrow(_http(422, {'error': {'code': 'validation_failed', 'message': 'x', 'fields': {'voen': ['VÖEN düzgün deyil']}}}));
      final invalid = await repo.submitLegal(
        complexName: 'P', legalName: 'P', voen: '1', legalAddress: 'L', contactPersonName: 'C', contactPhone: '+994551112233',
        contactEmail: 'c@x.az', address: 'A', location: const GeoPoint(40.1, 49.1),
      );
      invalid.fold((f) => expect((f as ValidationFailure).firstFor('voen'), 'VÖEN düzgün deyil'), (_) => fail('expected failure'));
    });
  });

  group('register account_type (additive, B9)', () {
    test('sends account_type only when chosen — legacy path unchanged', () async {
      final api = MockApiClient();
      final ds = AuthRemoteDataSource(api, _MockDeviceInfo());
      when(() => api.post('/v1/auth/register', data: any(named: 'data')))
          .thenAnswer((_) async => _res({'success': true, 'data': null, 'meta': {'expires_in_seconds': 600}}, 202));

      await ds.register(firstName: 'A', lastName: 'B', phone: '+994500000001', email: 'a@x.az');
      await ds.register(firstName: 'A', lastName: 'B', phone: '+994500000001', email: 'a@x.az', accountType: 'legal');

      final calls = verify(() => api.post('/v1/auth/register', data: captureAny(named: 'data'))).captured;
      expect((calls[0] as Map).containsKey('account_type'), isFalse);
      expect((calls[1] as Map)['account_type'], 'legal');
    });
  });

  testWidgets('RegisterTypeScreen routes each choice to the register form with its type', (tester) async {
    final visited = <String>[];
    final router = GoRouter(initialLocation: '/auth/register/type', routes: [
      GoRoute(path: '/auth/register/type', builder: (_, _) => const RegisterTypeScreen()),
      GoRoute(path: '/auth/register', builder: (_, s) {
        visited.add(s.uri.toString());
        return const Scaffold(body: Text('register'));
      }),
    ]);
    await tester.pumpWidget(ProviderScope(
      child: MaterialApp.router(
        routerConfig: router,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        locale: const Locale('az'),
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Fiziki şəxs'), findsOneWidget);
    expect(find.text('Hüquqi şəxs'), findsOneWidget);

    await tester.tap(find.byKey(const Key('reg-type-legal')));
    await tester.pumpAndSettle();
    expect(visited.last, '/auth/register?type=legal');

    router.go('/auth/register/type');
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('reg-type-physical')));
    await tester.pumpAndSettle();
    expect(visited.last, '/auth/register?type=physical');
  });
}
