import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/features/notifications/data/notification_settings_datasource.dart';
import 'package:salam_mobile/features/notifications/notifications_providers.dart';

/// O13 — the Settings switches: loads the server's list, saves a toggle, and puts it back when the save fails.
class _FakeSource implements NotificationSettingsDataSource {
  _FakeSource({this.failSave = false});

  final bool failSave;
  final List<NotificationSetting> _rows = [
    const NotificationSetting(templateKey: 'device.opened', pushEnabled: true),
    const NotificationSetting(templateKey: 'system.admin_campaign', pushEnabled: true),
  ];

  @override
  Future<List<NotificationSetting>> load() async => List.of(_rows);

  @override
  Future<List<NotificationSetting>> setPush(String templateKey, bool enabled) async {
    if (failSave) throw Exception('422');
    for (var i = 0; i < _rows.length; i++) {
      if (_rows[i].templateKey == templateKey) _rows[i] = _rows[i].copyWith(pushEnabled: enabled);
    }
    return List.of(_rows);
  }
}

ProviderContainer _container(_FakeSource source) => ProviderContainer(
  overrides: [notificationSettingsDataSourceProvider.overrideWithValue(source)],
);

void main() {
  test('parses the server list', () {
    final s = NotificationSetting.fromJson({'template_key': 'device.opened', 'push_enabled': false});
    expect(s.templateKey, 'device.opened');
    expect(s.pushEnabled, isFalse);
  });

  test('saves a switch', () async {
    final c = _container(_FakeSource());
    addTearDown(c.dispose);
    final sub = c.listen(notificationSettingsProvider, (_, _) {});
    addTearDown(sub.close);
    await c.read(notificationSettingsProvider.future);

    final ok = await c.read(notificationSettingsProvider.notifier).setPush('device.opened', false);

    expect(ok, isTrue);
    expect(c.read(notificationSettingsProvider).value!.first.pushEnabled, isFalse);
  });

  test('puts the switch back when the server refuses', () async {
    final c = _container(_FakeSource(failSave: true));
    addTearDown(c.dispose);
    final sub = c.listen(notificationSettingsProvider, (_, _) {});
    addTearDown(sub.close);
    await c.read(notificationSettingsProvider.future);

    final ok = await c.read(notificationSettingsProvider.notifier).setPush('device.opened', false);

    expect(ok, isFalse);
    expect(c.read(notificationSettingsProvider).value!.first.pushEnabled, isTrue);
  });
}
