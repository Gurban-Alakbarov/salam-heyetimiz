import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/data_components.dart';
import 'package:salam_mobile/features/barrier/barrier_providers.dart';
import 'package:salam_mobile/features/barrier/domain/entity/barrier_entities.dart';
import 'package:salam_mobile/features/barrier/domain/repository.dart';
import 'package:salam_mobile/features/devices/devices_providers.dart';
import 'package:salam_mobile/features/devices/domain/entity/device_entities.dart';
import 'package:salam_mobile/features/devices/presentation/screens/device_list_screen.dart';
import 'package:salam_mobile/features/devices/presentation/widgets/device_card.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

Widget _app(List<dynamic> override) => ProviderScope(
  overrides: override.cast(),
  child: const MaterialApp(
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: AppLocalizations.supportedLocales,
    locale: Locale('az'),
    home: Scaffold(body: DeviceListScreen()),
  ),
);

void main() {
  testWidgets('loading → skeleton cards', (tester) async {
    await tester.pumpWidget(
      _app([
        deviceListProvider.overrideWith(
          (ref) => Completer<DevicePage>().future,
        ),
      ]),
    );
    await tester.pump();

    expect(find.byType(AppSkeletonCard), findsWidgets);
  });

  testWidgets('empty → EmptyState', (tester) async {
    await tester.pumpWidget(
      _app([
        deviceListProvider.overrideWith(
          (ref) async => const DevicePage(devices: []),
        ),
      ]),
    );
    await tester.pumpAndSettle();

    expect(find.byType(EmptyState), findsOneWidget);
    expect(find.text('Cihazınız yoxdur'), findsOneWidget);
  });

  testWidgets('data → device cards with online/offline badges', (tester) async {
    final now = DateTime.now();
    final page = DevicePage(
      devices: [
        Device(
          id: 1,
          label: 'Gate Online',
          status: 'active',
          lastOnlineAt: now,
        ),
        Device(
          id: 2,
          label: 'Gate Offline',
          status: 'active',
          lastOnlineAt: now.subtract(const Duration(minutes: 30)),
        ),
      ],
    );

    await tester.pumpWidget(
      _app([deviceListProvider.overrideWith((ref) async => page)]),
    );
    await tester.pumpAndSettle();

    expect(find.byType(DeviceCard), findsNWidgets(2));
    expect(find.text('Gate Online'), findsOneWidget);
    expect(find.text('Gate Offline'), findsOneWidget);
    expect(find.text('Online'), findsOneWidget);
    expect(find.text('Offline'), findsOneWidget);
  });

  testWidgets('open is a pulse: success shows "Qapı açıldı", never a close '
      'button, then auto-returns to idle (no navigation)', (tester) async {
    final page = DevicePage(
      devices: [
        Device(
          id: 1,
          label: 'Blok A',
          serial: '863767070453873',
          status: 'active',
          canOpen: true,
          lastOnlineAt: DateTime.now(),
        ),
      ],
    );

    await tester.pumpWidget(
      _app([
        deviceListProvider.overrideWith((ref) async => page),
        barrierRepositoryProvider.overrideWithValue(_FakeBarrierRepo()),
      ]),
    );
    await tester.pumpAndSettle();

    // Default state: a single "Qapını Aç". There is NO close button in the pulse model.
    expect(find.text('Qapını Aç'), findsOneWidget);
    expect(find.text('Qapını Bağla'), findsNothing);

    // Open flows through the shared barrier pipeline (fake ack → poll → opened).
    await tester.tap(find.text('Qapını Aç'));
    await tester.pump(); // deliver tap → dispatch open
    await tester.pump(const Duration(seconds: 2)); // ack → pending → poll → opened
    await tester.pump(); // rebuild with the success state

    // Success message on the SAME card; still no close button; the user never left the list.
    expect(find.text('Qapı açıldı'), findsOneWidget);
    expect(find.text('Qapını Bağla'), findsNothing);
    expect(find.byType(DeviceCard), findsOneWidget);

    // The pulse auto-returns the operated card to its normal idle state.
    await tester.pump(const Duration(seconds: 3)); // auto-reset delay
    await tester.pump();
    expect(find.text('Qapını Aç'), findsOneWidget);
    expect(find.text('Qapını Bağla'), findsNothing);
  });
}

/// Stubs the shared barrier pipeline: every open/close acks immediately and the
/// polled status reports `opened` (actuation confirmed).
class _FakeBarrierRepo implements BarrierRepository {
  @override
  Future<Result<OpenAck>> open(int deviceId) async =>
      const Success(OpenAck(commandId: 1, expectedCompletionMs: 600));

  @override
  Future<Result<OpenAck>> close(int deviceId) async =>
      const Success(OpenAck(commandId: 2, expectedCompletionMs: 600));

  @override
  Future<Result<CommandStatus>> status(int commandId) async =>
      const Success(CommandStatus(id: 1, state: CommandState.opened));

  @override
  Future<Result<void>> feedback(
    int commandId, {
    required bool gateMoved,
    String? comment,
  }) =>
      throw UnimplementedError();
}
