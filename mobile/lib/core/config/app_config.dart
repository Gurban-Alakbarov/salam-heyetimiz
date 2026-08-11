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

  static const AppConfig prod = AppConfig(
    env: AppEnv.prod,
    apiBaseUrl: 'https://api.salamheyetimiz.com',
    appName: 'Salam Həyətimiz',
  );

  static const AppConfig qa = AppConfig(
    env: AppEnv.qa,
    apiBaseUrl: 'https://api.salamheyetimiz.com',
    appName: 'Salam QA',
  );

  static const AppConfig dev = AppConfig(
    env: AppEnv.dev,
    apiBaseUrl: 'https://api.salamheyetimiz.com',
    appName: 'Salam Dev',
  );
}
