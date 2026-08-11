import 'package:salam_mobile/bootstrap.dart';
import 'package:salam_mobile/core/config/app_config.dart';

/// Prod flavor entrypoint: flutter run -t lib/main_prod.dart
Future<void> main() => bootstrap(AppConfig.prod);
