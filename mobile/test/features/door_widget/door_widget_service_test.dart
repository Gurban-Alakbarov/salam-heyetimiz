import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/features/door_widget/door_widget_service.dart';

/// [DoorWidgetService] (W5) keeps PER-AppWidgetId config in the home_widget store and
/// refreshes the native widget. These tests drive the `home_widget` MethodChannel with
/// an in-memory fake and pin the multi-instance contract: instance isolation, the
/// legacy global read fallback, delete/logout cleanup, and non-secret storage only.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const channel = MethodChannel('home_widget');
  const service = DoorWidgetService();

  final log = <MethodCall>[];
  final store = <String, Object?>{};
  var installed = <Map<String, Object?>>[]; // getInstalledWidgets result

  setUp(() {
    log.clear();
    store.clear();
    installed = [];
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
          log.add(call);
          switch (call.method) {
            case 'saveWidgetData':
              final args = (call.arguments as Map).cast<String, dynamic>();
              final id = args['id'] as String;
              final data = args['data'];
              if (data == null) {
                store.remove(id);
              } else {
                store[id] = data;
              }
              return true;
            case 'getWidgetData':
              final args = (call.arguments as Map).cast<String, dynamic>();
              final id = args['id'] as String;
              return store.containsKey(id) ? store[id] : args['defaultValue'];
            case 'getInstalledWidgets':
              return installed;
            case 'updateWidget':
              return true;
            default:
              return null;
          }
        });
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null);
  });

  List<MethodCall> callsTo(String m) => log.where((c) => c.method == m).toList();

  test('per-instance keys are suffixed with the real AppWidgetId', () {
    expect(DoorWidgetService.deviceIdKey(123), 'door_widget_device_id_123');
    expect(DoorWidgetService.deviceLabelKey(123), 'door_widget_device_label_123');
    expect(DoorWidgetService.statusCodeKey(123), 'door_widget_status_code_123');
    expect(DoorWidgetService.lastOpenMsKey(123), 'door_widget_last_open_ms_123');
    // No slot / fabricated id scheme — only the AppWidgetId.
    expect(DoorWidgetService.resolvedStatusKey(456), 'door_widget_status_code_456');
    expect(DoorWidgetService.resolvedStatusKey(null), 'door_widget_status_code');
  });

  test('provider identity is stable', () {
    expect(
      DoorWidgetService.androidProvider,
      'com.salamheyetimiz.salam_mobile.DoorWidgetProvider',
    );
  });

  test('setDevice writes ONLY that instance\'s non-secret keys', () async {
    await service.setDevice(123, deviceId: 25, deviceLabel: 'Sinam Giris');

    expect(store['door_widget_device_id_123'], 25);
    expect(store['door_widget_device_label_123'], 'Sinam Giris');
    expect(store['door_widget_device_id_123'], isA<int>());
    // Only this instance's keys — no token/secret, no other instance.
    expect(store.keys.toSet(), {
      'door_widget_device_id_123',
      'door_widget_device_label_123',
    });
  });

  test('two instances are isolated (A does not affect B)', () async {
    await service.setDevice(123, deviceId: 25, deviceLabel: 'Sinam Giris');
    await service.setDevice(456, deviceId: 31, deviceLabel: 'Parking Gate');

    final a = await service.current(123);
    final b = await service.current(456);
    expect(a!.deviceId, 25);
    expect(a.deviceLabel, 'Sinam Giris');
    expect(b!.deviceId, 31);
    expect(b.deviceLabel, 'Parking Gate');
  });

  test('setDevice clears that instance\'s stale status + cooldown only', () async {
    store['door_widget_status_code_123'] = 'no_device';
    store['door_widget_last_open_ms_123'] = 111;
    store['door_widget_status_code_456'] = 'opened'; // other instance untouched

    await service.setDevice(123, deviceId: 5, deviceLabel: 'Q');

    expect(store.containsKey('door_widget_status_code_123'), isFalse);
    expect(store.containsKey('door_widget_last_open_ms_123'), isFalse);
    expect(store['door_widget_status_code_456'], 'opened');
  });

  test('current falls back to the legacy global config (pre-W5 widget)', () async {
    // No per-instance keys — only the old global config exists.
    store['door_widget_device_id'] = 9;
    store['door_widget_device_label'] = 'Köhnə qapı';

    final config = await service.current(777);
    expect(config!.deviceId, 9);
    expect(config.deviceLabel, 'Köhnə qapı');
  });

  test('per-instance config wins over the legacy global fallback', () async {
    store['door_widget_device_id'] = 9;
    store['door_widget_device_label'] = 'Köhnə';
    await service.setDevice(777, deviceId: 42, deviceLabel: 'Yeni');

    final config = await service.current(777);
    expect(config!.deviceId, 42);
    expect(config.deviceLabel, 'Yeni');
  });

  test('current(widgetId) is null when nothing is configured', () async {
    expect(await service.current(123), isNull);
  });

  test('clearInstance clears only that instance', () async {
    await service.setDevice(123, deviceId: 25, deviceLabel: 'A');
    await service.setDevice(456, deviceId: 31, deviceLabel: 'B');

    await service.clearInstance(123);

    expect(await service.current(123), isNull);
    expect((await service.current(456))!.deviceId, 31);
  });

  test('clearAll wipes every installed instance + legacy global + base URL', () async {
    installed = [
      {'widgetId': 123, 'androidClassName': '.DoorWidgetProvider'},
      {'widgetId': 456, 'androidClassName': '.DoorWidgetProvider'},
    ];
    await service.setDevice(123, deviceId: 25, deviceLabel: 'A');
    await service.setDevice(456, deviceId: 31, deviceLabel: 'B');
    store['door_widget_device_id'] = 9; // legacy global
    store['door_widget_device_label'] = 'legacy';
    await service.setBaseUrl('https://api.example.test');
    await service.setLocale('ru'); // locale must SURVIVE logout

    await service.clearAll();

    expect(await service.current(123), isNull);
    expect(await service.current(456), isNull);
    expect(store.containsKey('door_widget_device_id'), isFalse);
    expect(store.containsKey('door_widget_base_url'), isFalse);
    // Locale is an app-level preference, not user data → kept across logout.
    expect(store['door_widget_locale'], 'ru');
  });

  test('base URL round-trips and is global (config only, no re-render)', () async {
    await service.setBaseUrl('https://api.example.test');
    expect(store['door_widget_base_url'], 'https://api.example.test');
    expect(await service.baseUrl(), 'https://api.example.test');
    expect(callsTo('updateWidget'), isEmpty); // setBaseUrl does not refresh
  });

  test('setLocale persists the code and refreshes all widgets (D2)', () async {
    await service.setLocale('en');
    expect(store['door_widget_locale'], 'en');
    expect(callsTo('updateWidget').length, 1);
  });
}
