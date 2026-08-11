import 'package:salam_mobile/bootstrap.dart';
import 'package:salam_mobile/core/config/app_config.dart';

/// Default entrypoint (dev flavor). Used by `flutter run` / default builds.
/// QA + Prod entrypoints: lib/main_qa.dart, lib/main_prod.dart.
Future<void> main() => bootstrap(AppConfig.dev);
