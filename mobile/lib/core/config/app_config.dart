/// Flavor / environment configuration (Constitution §17.3). Injected at app start
/// via a ProviderScope override from `main_<flavor>.dart`.
enum AppEnv { dev, qa, prod }

class AppConfig {
  const AppConfig({
    required this.env,
    required this.apiBaseUrl,
    required this.appName,
  });

  final AppEnv env;
  final String apiBaseUrl;
  final String appName;

  bool get isProd => env == AppEnv.prod;
  bool get loggingEnabled => env != AppEnv.prod;

  /// B13: dev / qa builds may point at a local or test backend with
  /// `--dart-define=API_BASE_URL=http://10.0.2.2:8010`. The prod flavor never reads it.
  static const String _nonProdApiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://api.salamheyetimiz.com',
  );

  /// Host of invitation universal / app links (`https://<host>/invite/{token}`, B6).
  static const String inviteLinkHost = 'salamheyetimiz.com';

  static const AppConfig prod = AppConfig(
    env: AppEnv.prod,
    apiBaseUrl: 'https://api.salamheyetimiz.com',
    appName: 'Salam Həyətimiz',
  );

  static const AppConfig qa = AppConfig(
    env: AppEnv.qa,
    apiBaseUrl: _nonProdApiBaseUrl,
    appName: 'Salam QA',
  );

  static const AppConfig dev = AppConfig(
    env: AppEnv.dev,
    apiBaseUrl: _nonProdApiBaseUrl,
    appName: 'Salam Dev',
  );
}
