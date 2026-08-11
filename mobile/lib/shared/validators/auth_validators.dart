/// Pure, UI-agnostic input validators (mirror the backend rules). Screens use
/// these for inline validation before hitting the API.
class AuthValidators {
  AuthValidators._();

  static final RegExp _email = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');
  static final RegExp _azPhone = RegExp(r'^\+994\d{9}$');

  static bool isEmail(String value) => _email.hasMatch(value.trim());

  /// E.164 Azerbaijan: +994 followed by 9 digits.
  static bool isAzPhone(String value) => _azPhone.hasMatch(value.trim());

  static bool isNotBlank(String value) => value.trim().isNotEmpty;
}
