import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/shared/validators/auth_validators.dart';

void main() {
  group('AuthValidators.isEmail', () {
    test('accepts valid', () {
      expect(AuthValidators.isEmail('aysel@example.com'), isTrue);
    });
    test('rejects invalid', () {
      expect(AuthValidators.isEmail('not-an-email'), isFalse);
      expect(AuthValidators.isEmail('a@b'), isFalse);
      expect(AuthValidators.isEmail(''), isFalse);
    });
  });

  group('AuthValidators.isAzPhone', () {
    test('accepts +994 + 9 digits', () {
      expect(AuthValidators.isAzPhone('+994501234567'), isTrue);
    });
    test('rejects wrong format', () {
      expect(AuthValidators.isAzPhone('0501234567'), isFalse);
      expect(AuthValidators.isAzPhone('+9945012345'), isFalse); // too short
      expect(AuthValidators.isAzPhone('+994501234567890'), isFalse); // too long
      expect(AuthValidators.isAzPhone('+1234567890'), isFalse);
    });
  });

  test('isNotBlank', () {
    expect(AuthValidators.isNotBlank(' a '), isTrue);
    expect(AuthValidators.isNotBlank('   '), isFalse);
  });
}
