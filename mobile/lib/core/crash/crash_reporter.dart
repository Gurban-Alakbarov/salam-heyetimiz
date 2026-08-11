/// Provider-agnostic crash reporting (Constitution §12, CRASH_REPORTING.md).
/// Default = Noop; Crashlytics/Sentry plug in as adapters. No PII in reports.
abstract class CrashReporter {
  void recordError(Object error, StackTrace? stack, {bool fatal});
  void log(String breadcrumb);
  void setUserId(String? id);
}

class NoopCrashReporter implements CrashReporter {
  const NoopCrashReporter();

  @override
  void recordError(Object error, StackTrace? stack, {bool fatal = false}) {}
  @override
  void log(String breadcrumb) {}
  @override
  void setUserId(String? id) {}
}
