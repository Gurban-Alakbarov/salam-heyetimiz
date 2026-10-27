import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/theme/app_theme.dart';
import 'package:salam_mobile/features/complex/complex_providers.dart';
import 'package:salam_mobile/features/complex/data/complex_repository.dart';
import 'package:salam_mobile/features/complex/domain/complex_entities.dart';
import 'package:salam_mobile/features/devices/device_access_action.dart';
import 'package:salam_mobile/features/devices/devices_providers.dart';
import 'package:salam_mobile/features/devices/domain/entity/device_entities.dart';
import 'package:salam_mobile/features/devices/presentation/widgets/device_card.dart';
import 'package:salam_mobile/features/payments/data/payments_repository.dart';
import 'package:salam_mobile/features/payments/domain/payment_entities.dart';
import 'package:salam_mobile/features/payments/payments_providers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

import '../../helpers/mocks.dart';

class _MockPayments extends Mock implements PaymentsRepository {}

class _MockComplexRepo extends Mock implements ComplexRepository {}

/// B19 — a card that cannot open offers the caller's real next step, using ONLY existing endpoints.
void main() {
  late MockDeviceRepository devices;

  setUp(() => devices = MockDeviceRepository());

  Device detail({
    bool canOpen = false,
    String reason = 'subscription_expired',
    DeviceSubscription? sub,
  }) => Device(
    id: 7,
    label: 'Sinam Giriş',
    status: 'active',
    canOpen: canOpen,
    suspensionReason: reason,
    subscription: sub,
  );

  Future<DeviceAccessAction> resolve(
    Device d, {
    List<ResidentComplex> complexes = const [],
    Map<int, List<ComplexDevice>> complexDevices = const {},
  }) async {
    when(() => devices.detail(7)).thenAnswer((_) async => Success(d));
    final container = ProviderContainer(
      overrides: [
        deviceRepositoryProvider.overrideWithValue(devices),
        myComplexesProvider.overrideWith((ref) async => complexes),
        complexDevicesProvider.overrideWith(
          (ref, id) async => complexDevices[id] ?? const [],
        ),
      ],
    );
    addTearDown(container.dispose);
    final sub = container.listen(deviceAccessActionProvider(7), (_, _) {});
    addTearDown(sub.close);
    return container.read(deviceAccessActionProvider(7).future);
  }

  group('deviceAccessActionProvider (existing contracts only)', () {
    test(
      'pending_payment → finish the existing payment (main + additional)',
      () async {
        final main = await resolve(
          detail(
            sub: const DeviceSubscription(
              id: 5,
              tier: 'main',
              status: 'pending_payment',
            ),
          ),
        );
        expect(
          main,
          isA<PayPendingAction>()
              .having((a) => a.subscriptionId, 'id', 5)
              .having((a) => a.tier, 'tier', 'main'),
        );
        final family = await resolve(
          detail(
            sub: const DeviceSubscription(
              id: 6,
              tier: 'additional',
              status: 'pending_payment',
            ),
          ),
        );
        expect(
          family,
          isA<PayPendingAction>().having((a) => a.tier, 'tier', 'additional'),
        );
      },
    );

    test('expired → the existing renewal', () async {
      final a = await resolve(
        detail(
          sub: const DeviceSubscription(id: 9, tier: 'main', status: 'expired'),
        ),
      );
      expect(a, isA<RenewAction>().having((a) => a.subscriptionId, 'id', 9));
    });

    test(
      'no subscription on a device of my complex → subscribe through that complex',
      () async {
        final a = await resolve(
          detail(),
          complexes: const [ResidentComplex(id: 3, name: 'Gənclik')],
          complexDevices: const {
            3: [ComplexDevice(id: 7, label: 'Sinam Giriş')],
          },
        );
        expect(
          a,
          isA<SubscribeAction>().having((a) => a.complexId, 'complexId', 3),
        );
      },
    );

    test(
      'cancelled subscription on a complex device → subscribe again',
      () async {
        final a = await resolve(
          detail(
            sub: const DeviceSubscription(
              id: 4,
              tier: 'main',
              status: 'cancelled',
            ),
          ),
          complexes: const [ResidentComplex(id: 3, name: 'Gənclik')],
          complexDevices: const {
            3: [ComplexDevice(id: 7, label: 'Sinam Giriş')],
          },
        );
        expect(a, isA<SubscribeAction>());
      },
    );

    test(
      'no subscription and not my complex device (e.g. family member) → no payment action',
      () async {
        expect(await resolve(detail()), isA<NoDeviceAccessAction>());
      },
    );

    test(
      'own subscription active but access blocked, disabled device, or can open → nothing to pay',
      () async {
        expect(
          await resolve(
            detail(
              reason: 'owner_sub_expired_others_active',
              sub: const DeviceSubscription(
                id: 1,
                tier: 'main',
                status: 'active',
              ),
            ),
          ),
          isA<NoDeviceAccessAction>(),
        );
        expect(
          await resolve(detail(reason: 'device_disabled')),
          isA<NoDeviceAccessAction>(),
        );
        expect(
          await resolve(detail(canOpen: true, reason: 'none')),
          isA<NoDeviceAccessAction>(),
        );
      },
    );
  });

  group('device card action', () {
    late _MockPayments payments;
    late _MockComplexRepo complexRepo;
    late List<String> visited;

    setUp(() {
      payments = _MockPayments();
      complexRepo = _MockComplexRepo();
      visited = [];
    });

    const order = PaymentOrder(
      id: 42,
      reference: 'SH-1',
      status: 'authorising',
      amountMinor: 1200,
      currency: 'AZN',
      isTest: true,
    );

    Future<void> pumpCard(
      WidgetTester tester,
      DeviceAccessAction action,
    ) async {
      final router = GoRouter(
        initialLocation: '/',
        routes: [
          GoRoute(
            path: '/',
            builder: (_, _) => Scaffold(
              body: SingleChildScrollView(
                child: SizedBox(
                  width: 180,
                  child: DeviceCard(
                    device: detail().copyWith(lastOnlineAt: DateTime.now()),
                  ),
                ),
              ),
            ),
          ),
          GoRoute(
            path: '/checkout/:id',
            builder: (_, s) {
              visited.add(s.uri.toString());
              return const Scaffold(body: Text('checkout'));
            },
          ),
        ],
      );
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            deviceAccessActionProvider.overrideWith((ref, id) async => action),
            paymentsRepositoryProvider.overrideWithValue(payments),
            complexRepositoryProvider.overrideWithValue(complexRepo),
            pendingSubscriptionsProvider.overrideWith((ref) async => const []),
            deviceListProvider.overrideWith(
              (ref) async => const DevicePage(devices: []),
            ),
          ],
          child: MaterialApp.router(
            theme: AppTheme.light,
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
      'inactive card shows the central notice above the name and "Ödənişi tamamla" → pending order → checkout',
      (tester) async {
        when(
          () => payments.payPendingSubscription(5, 'main'),
        ).thenAnswer((_) async => const Success(order));
        await pumpCard(tester, const PayPendingAction(5, 'main'));

        expect(find.byKey(const Key('device-inactive-7')), findsOneWidget);
        expect(find.text('Abunəliyiniz aktiv deyil'), findsOneWidget);
        final notice = tester.getTopLeft(
          find.byKey(const Key('device-inactive-7')),
        );
        final name = tester.getTopLeft(find.text('Sinam Giriş'));
        expect(
          notice.dy,
          lessThan(name.dy),
          reason: 'the notice sits above the device name',
        );
        expect(find.text('Ödənişi tamamla'), findsOneWidget);
        expect(find.text('Qapını Aç'), findsNothing);

        await tester.tap(find.byKey(const Key('device-access-7')));
        await tester.pumpAndSettle();
        verify(() => payments.payPendingSubscription(5, 'main')).called(1);
        verifyNever(() => complexRepo.subscribe(any(), any()));
        expect(visited, ['/checkout/42']);
      },
    );

    testWidgets('"Abunəliyi yenilə" → existing renewal → checkout', (
      tester,
    ) async {
      when(
        () => payments.renewSubscription(9),
      ).thenAnswer((_) async => const Success(order));
      await pumpCard(tester, const RenewAction(9));
      expect(find.text('Abunəliyi yenilə'), findsOneWidget);
      await tester.tap(find.byKey(const Key('device-access-7')));
      await tester.pumpAndSettle();
      verify(() => payments.renewSubscription(9)).called(1);
      expect(visited, ['/checkout/42']);
    });

    testWidgets('"Abunə ol" → existing complex subscribe → checkout', (
      tester,
    ) async {
      when(
        () => complexRepo.subscribe(3, 7),
      ).thenAnswer((_) async => const Success(order));
      await pumpCard(tester, const SubscribeAction(3));
      expect(find.text('Abunə ol'), findsOneWidget);
      await tester.tap(find.byKey(const Key('device-access-7')));
      await tester.pumpAndSettle();
      verify(() => complexRepo.subscribe(3, 7)).called(1);
      expect(visited, ['/checkout/42']);
    });

    testWidgets('server refuses the payer (403) → error shown, no checkout', (
      tester,
    ) async {
      when(
        () => payments.payPendingSubscription(5, 'additional'),
      ).thenAnswer((_) async => const Err(ForbiddenFailure()));
      await pumpCard(tester, const PayPendingAction(5, 'additional'));
      await tester.tap(find.byKey(const Key('device-access-7')));
      await tester.pumpAndSettle();
      expect(find.text('Bu əməliyyat üçün icazəniz yoxdur.'), findsOneWidget);
      expect(visited, isEmpty);
    });

    testWidgets(
      'nothing to pay → the disabled "Qapını Aç" stays (no fake action)',
      (tester) async {
        await pumpCard(tester, const NoDeviceAccessAction());
        expect(find.byKey(const Key('device-access-7')), findsNothing);
        final button = tester.widget<FilledButton>(
          find
              .descendant(
                of: find.byKey(const Key('device-open-7')),
                matching: find.byType(FilledButton),
              )
              .first,
        );
        expect(button.onPressed, isNull);
      },
    );
  });
}
