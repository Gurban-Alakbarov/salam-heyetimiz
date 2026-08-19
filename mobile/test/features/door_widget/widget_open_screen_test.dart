import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/core/location/location_service.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/features/barrier/barrier_providers.dart';
import 'package:salam_mobile/features/barrier/domain/entity/barrier_entities.dart';
import 'package:salam_mobile/features/barrier/domain/repository.dart';
import 'package:salam_mobile/features/devices/devices_providers.dart';
import 'package:salam_mobile/features/devices/domain/entity/device_entities.dart';
import 'package:salam_mobile/features/devices/presentation/widgets/device_card.dart';
import 'package:salam_mobile/features/door_widget/presentation/widget_open_screen.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// GEOFENCE-4 — the screen a `location_required` widget tap lands on. It must show ONLY
/// the widget's own barrier (never another), and NEVER expose an open action for a device
/// that is not in the user's roster (the server stays the sole open authority).
Widget _app(int deviceId, List<dynamic> overrides) => ProviderScope(
  overrides: overrides.cast(),
  child: MaterialApp(
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: AppLocalizations.supportedLocales,
    locale: const Locale('az'),
    home: WidgetOpenScreen(deviceId: deviceId),
  ),
);

void main() {
  DevicePage roster(List<Device> devices) => DevicePage(devices: devices);

  Device gate(int id, String label) => Device(
    id: id,
    label: label,
    status: 'active',
    canOpen: true,
    lastOnlineAt: DateTime.now(),
  );

  testWidgets('device in the roster → shows that barrier + the manual open button', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(7, [
        deviceListProvider.overrideWith(
          (ref) async => roster([gate(7, 'Blok A'), gate(9, 'Blok B')]),
        ),
        barrierRepositoryProvider.overrideWithValue(_FakeBarrierRepo()),
      ]),
    );
    await tester.pumpAndSettle();

    // Exactly one card, and it is the WIDGET's barrier — never the other one.
    expect(find.byType(DeviceCard), findsOneWidget);
    expect(find.text('Blok A'), findsWidgets); // app bar title + card header
    expect(find.text('Blok B'), findsNothing);
    // Manual open button present; nothing auto-fires (D4).
    expect(find.text('Qapını Aç'), findsOneWidget);
  });

  testWidgets('device NOT in the roster → safe notice, NO card / open action', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(7, [
        deviceListProvider.overrideWith((ref) async => roster([gate(9, 'Blok B')])),
        barrierRepositoryProvider.overrideWithValue(_FakeBarrierRepo()),
      ]),
    );
    await tester.pumpAndSettle();

    // No barrier card at all → the widget can never force-open a device the server
    // would not authorize. A safe access notice is shown instead.
    expect(find.byType(DeviceCard), findsNothing);
    expect(find.byType(EmptyState), findsOneWidget);
    expect(find.text('Bu cihaza icazəniz yoxdur'), findsOneWidget);
  });
}

/// Every open/close acks immediately and polls to `opened`; never actually hit here
/// because the tests only render the idle screen.
class _FakeBarrierRepo implements BarrierRepository {
  @override
  Future<Result<OpenAck>> open(int deviceId, {GeoFix? fix}) async =>
      const Success(OpenAck(commandId: 1, expectedCompletionMs: 600));

  @override
  Future<Result<OpenAck>> close(int deviceId, {GeoFix? fix}) async =>
      const Success(OpenAck(commandId: 2, expectedCompletionMs: 600));

  @override
  Future<Result<CommandStatus>> status(int commandId) async =>
      const Success(CommandStatus(id: 1, state: CommandState.opened));

  @override
  Future<Result<void>> feedback(
    int commandId, {
    required bool gateMoved,
    String? comment,
  }) => throw UnimplementedError();
}
