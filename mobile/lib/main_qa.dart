import 'package:salam_mobile/bootstrap.dart';
import 'package:salam_mobile/core/config/app_config.dart';

/// QA flavor entrypoint: flutter run -t lib/main_qa.dart
Future<void> main() => bootstrap(AppConfig.qa);
