/// Centralised storage keys (Constitution §16). Tokens live in [SecureStore];
/// install id is generated once and persisted for device-binding.
class StorageKeys {
  StorageKeys._();

  // Secure (encrypted)
  static const String accessToken = 'access_token';
  static const String refreshToken = 'refresh_token';
  static const String installUuid = 'install_uuid';

  // Prefs (non-sensitive)
  static const String themeMode = 'theme_mode';
  static const String localeCode = 'locale_code';
}
