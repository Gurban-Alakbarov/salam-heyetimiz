import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/core/services/app_version.dart';

void main() {
  group('AppVersion.isBelow (force-update gate)', () {
    test('lower version is below minimum', () {
      expect(AppVersion.isBelow('1.0.0', '1.2.0'), isTrue);
      expect(AppVersion.isBelow('1.1.9', '1.2.0'), isTrue);
      expect(AppVersion.isBelow('0.9.0', '1.0.0'), isTrue);
    });
    test('equal or higher is not below', () {
      expect(AppVersion.isBelow('1.2.0', '1.2.0'), isFalse);
      expect(AppVersion.isBelow('2.0.0', '1.2.0'), isFalse);
      expect(AppVersion.isBelow('1.2.1', '1.2.0'), isFalse);
    });
    test('ignores build metadata', () {
      expect(AppVersion.isBelow('1.2.0+5', '1.2.0'), isFalse);
    });
  });
}
