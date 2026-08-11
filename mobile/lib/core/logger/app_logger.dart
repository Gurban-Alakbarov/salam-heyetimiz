import 'package:logger/logger.dart';

/// Leveled, redacted logger (Constitution §2/§16 — never log tokens/OTP/PII).
class AppLogger {
  AppLogger({required bool enabled})
    : _logger = Logger(
        filter: enabled ? DevelopmentFilter() : ProductionFilter(),
        printer: PrettyPrinter(methodCount: 0),
      );

  final Logger _logger;

  void d(String message) => _logger.d(message);
  void i(String message) => _logger.i(message);
  void w(String message) => _logger.w(message);
  void e(String message, [Object? error, StackTrace? stack]) =>
      _logger.e(message, error: error, stackTrace: stack);
}
