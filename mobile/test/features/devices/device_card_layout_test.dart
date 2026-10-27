import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/theme/app_theme.dart';
import 'package:salam_mobile/features/barrier/barrier_providers.dart';
import 'package:salam_mobile/features/devices/device_access_action.dart';
import 'package:salam_mobile/features/devices/presentation/widgets/device_card.dart';
import 'package:salam_mobile/features/devices/devices_providers.dart';
import 'package:salam_mobile/features/devices/domain/entity/device_entities.dart';
import 'package:salam_mobile/features/devices/presentation/screens/device_list_screen.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// B19: the 2-column Cihazlar grid must never overflow (debug "OVERFLOWED BY … PIXELS") — real AppTheme,
/// real list screen, narrow → wide phones, larger accessibility text, long + short names, active + inactive
/// cards side by side.
const _widths = <double>[320, 360, 375, 393, 411];
const _textScales = <double>[1.0, 1.3];

Device _device(
  int id,
  String label, {
  bool canOpen = true,
  String reason = 'none',
}) => Device(
  id: id,
  label: label,
  status: 'active',
  canOpen: canOpen,
  suspensionReason: reason,
  lastOnlineAt: DateTime.now(),
  latitude: 40.4,
  longitude: 49.8,
);

final _devices = <Device>[
  _device(1, 'Əsas Giriş'),
  _device(
    2,
    'Gənclik Park Yaşayış Kompleksi — Şimal Darvazası Parkinq Girişi',
    canOpen: false,
    reason: 'subscription_expired',
  ),
  _device(3, 'A', canOpen: false, reason: 'subscription_expired'),
  _device(4, 'Çox uzun cihaz adı ilə ikinci sütun üçün yoxlama mətni Bakı'),
  _device(5, 'Ofis', canOpen: false, reason: 'device_disabled'),
  _device(
    6,
    'Gənclik Park Yaşayış Kompleksi Cənub Qapısı',
    canOpen: false,
    reason: 'subscription_expired',
  ),
];

/// Every card that cannot open shows its real action label (the longest ones are the overflow risk).
DeviceAccessAction _actionFor(int id) => switch (id) {
  2 => const PayPendingAction(12, 'main'),
  3 => const RenewAction(13),
  6 => const SubscribeAction(4),
  _ => const NoDeviceAccessAction(),
};

class _FixedOpen extends BarrierOpenNotifier {
  _FixedOpen(this.fixed);
  final BarrierOpenState fixed;
  @override
  BarrierOpenState build() => fixed;
}

Future<List<String>> _pumpGrid(
  WidgetTester tester,
  double width,
  double textScale,
) async {
  tester.view.devicePixelRatio = 3;
  tester.view.physicalSize = Size(width * 3, 2400);
  addTearDown(tester.view.reset);

  final overflows = <String>[];
  final previous = FlutterError.onError;
  FlutterError.onError = (details) {
    final text = details.exceptionAsString();
    if (text.contains('overflowed')) {
      overflows.add(text.split('\n').first);
    } else {
      previous?.call(details);
    }
  };
  addTearDown(() => FlutterError.onError = previous);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        deviceListProvider.overrideWith(
          (ref) async => DevicePage(devices: _devices),
        ),
        deviceAccessActionProvider.overrideWith(
          (ref, id) async => _actionFor(id),
        ),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        locale: const Locale('az'),
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(
            context,
          ).copyWith(textScaler: TextScaler.linear(textScale)),
          child: child!,
        ),
        home: const Scaffold(body: DeviceListScreen()),
      ),
    ),
  );
  await tester.pumpAndSettle();
  FlutterError.onError = previous;
  return overflows;
}

Future<List<String>> _pumpActivePair(
  WidgetTester tester,
  double width,
  BarrierOpenState state,
) async {
  tester.view.devicePixelRatio = 3;
  tester.view.physicalSize = Size(width * 3, 2400);
  addTearDown(tester.view.reset);
  final overflows = <String>[];
  final previous = FlutterError.onError;
  FlutterError.onError = (details) {
    final text = details.exceptionAsString();
    if (text.contains('overflowed')) {
      overflows.add(text.split('\n').first);
    } else {
      previous?.call(details);
    }
  };
  addTearDown(() => FlutterError.onError = previous);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [barrierOpenProvider.overrideWith(() => _FixedOpen(state))],
      child: MaterialApp(
        theme: AppTheme.light,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        locale: const Locale('az'),
        home: Scaffold(
          body: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: DeviceCard(device: _devices[0], isActive: true),
                ),
                const SizedBox(width: 16),
                Expanded(child: DeviceCard(device: _devices[3])),
              ],
            ),
          ),
        ),
      ),
    ),
  );
  await tester.pump(const Duration(milliseconds: 300));
  FlutterError.onError = previous;
  return overflows;
}

void main() {
  for (final width in const <double>[320, 360]) {
    for (final state in <BarrierOpenState>[
      const BarrierFailed(UnknownFailure()),
      const BarrierSuccess(commandId: 1, actuated: false),
    ]) {
      testWidgets(
        'active card live status has no overflow at ${width.toInt()}dp (${state.runtimeType})',
        (tester) async {
          final overflows = await _pumpActivePair(tester, width, state);
          expect(overflows, isEmpty, reason: overflows.join('\n'));
        },
      );
    }
  }

  for (final width in _widths) {
    for (final scale in _textScales) {
      testWidgets(
        '2-column device grid has no overflow at ${width.toInt()}dp, text ×$scale',
        (tester) async {
          final overflows = await _pumpGrid(tester, width, scale);
          expect(overflows, isEmpty, reason: overflows.join('\n'));
          expect(find.text('Əsas Giriş'), findsOneWidget);
          // active + inactive side by side: only cards that cannot open carry the central notice and the
          // real action (longest labels — the overflow risk); the active card keeps "Qapını Aç".
          expect(find.byKey(const Key('device-inactive-1')), findsNothing);
          expect(find.byKey(const Key('device-open-1')), findsOneWidget);
          for (final id in const [2, 3, 5, 6]) {
            expect(find.byKey(Key('device-inactive-$id')), findsOneWidget);
          }
          expect(find.text('Ödənişi tamamla'), findsOneWidget);
          expect(find.text('Abunəliyi yenilə'), findsOneWidget);
          expect(find.text('Abunə ol'), findsOneWidget);
          expect(find.byKey(const Key('device-open-5')), findsOneWidget);
        },
      );
    }
  }
}
