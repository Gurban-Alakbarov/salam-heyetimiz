import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/features/devices/domain/entity/device_entities.dart';

/// A2 — the app shows the server's online flag (one window with the admin panel); the 15-minute local
/// fallback is only for a server that does not send it.
void main() {
  final now = DateTime(2026, 10, 27, 12);

  test('the server flag wins over the local window', () {
    final d = Device(id: 1, label: 'G', online: false, lastOnlineAt: now.subtract(const Duration(minutes: 1)));
    expect(d.isOnlineAt(now), isFalse);
  });

  test('without the flag the fallback window is 15 minutes', () {
    expect(Device(id: 1, label: 'G', lastOnlineAt: now.subtract(const Duration(minutes: 10))).isOnlineAt(now), isTrue);
    expect(Device(id: 1, label: 'G', lastOnlineAt: now.subtract(const Duration(minutes: 20))).isOnlineAt(now), isFalse);
  });
}
