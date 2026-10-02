import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/devices/devices_providers.dart';
import 'package:salam_mobile/features/devices/domain/entity/device_entities.dart';
import 'package:salam_mobile/features/payments/data/payments_repository.dart';
import 'package:salam_mobile/features/payments/domain/payment_entities.dart';
import 'package:salam_mobile/features/payments/payments_providers.dart';
import 'package:salam_mobile/features/payments/presentation/payment_result_screen.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

class _MockPaymentsRepo extends Mock implements PaymentsRepository {}

/// B18 regression: after a paid order the result screen itself refreshes the access-bearing lists, so the
/// Cihazlar tab shows the new device / an enabled "Qapını Aç" even when the user leaves via go('/home').
void main() {
  late _MockPaymentsRepo payments;

  setUp(() => payments = _MockPaymentsRepo());

  Future<int> deviceListBuildsAfter(WidgetTester tester, String status) async {
    var builds = 0;
    when(() => payments.getOrder(9)).thenAnswer(
      (_) async => Success(
        PaymentOrder(
          id: 9,
          reference: 'SH-1',
          status: status,
          amountMinor: 1200,
          currency: 'AZN',
          isTest: true,
        ),
      ),
    );
    when(() => payments.recheck(9)).thenAnswer(
      (_) async => Success(
        PaymentOrder(
          id: 9,
          reference: 'SH-1',
          status: status,
          amountMinor: 1200,
          currency: 'AZN',
          isTest: true,
        ),
      ),
    );
    final container = ProviderContainer(
      overrides: [
        paymentsRepositoryProvider.overrideWithValue(payments),
        deviceListProvider.overrideWith((ref) async {
          builds++;
          return const DevicePage(devices: []);
        }),
      ],
    );
    addTearDown(container.dispose);
    // the Cihazlar tab keeps the list alive underneath the checkout stack
    container.listen(deviceListProvider, (_, _) {});
    await container.read(deviceListProvider.future);

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: const MaterialApp(
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          home: PaymentResultScreen(orderId: 9),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await container.read(deviceListProvider.future);
    return builds;
  }

  testWidgets(
    'paid order → device list is refreshed (new access visible without pull-to-refresh)',
    (tester) async {
      expect(await deviceListBuildsAfter(tester, 'paid'), 2);
    },
  );

  testWidgets('cancelled order → device list is left alone', (tester) async {
    expect(await deviceListBuildsAfter(tester, 'cancelled'), 1);
  });
}
