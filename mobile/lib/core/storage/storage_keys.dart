/// Centralised storage keys (Constitution §16). Tokens live in [SecureStore];
/// install id is generated once and persisted for device-binding.
class StorageKeys {
  StorageKeys._();

  // Secure (encrypted)
  static const String accessToken = 'access_token';
  static const String refreshToken = 'refresh_token';
  static const String installUuid = 'install_uuid';
  // Home-screen widget's open-only token (WidgetTokenSync) + when it was issued (ms since epoch).
  static const String widgetToken = 'widget_token';
  static const String widgetTokenIssuedAt = 'widget_token_issued_at';

  // Prefs (non-sensitive)
  static const String themeMode = 'theme_mode';
  static const String localeCode = 'locale_code';
  // 1.0.1 — "send usage statistics" (Profile); absent = on.
  static const String analyticsEnabled = 'analytics_enabled';
}
