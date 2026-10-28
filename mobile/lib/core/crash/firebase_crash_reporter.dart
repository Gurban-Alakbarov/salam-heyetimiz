import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:salam_mobile/core/crash/crash_reporter.dart';

/// Firebase Crashlytics adapter (1.0.1). The user id is the internal numeric id only — never a name,
/// email or phone number. Every call is fire-and-forget so reporting can never crash the app itself.
class FirebaseCrashReporter implements CrashReporter {
  FirebaseCrashReporter(this._crashlytics);

  final FirebaseCrashlytics _crashlytics;

  @override
  void recordError(Object error, StackTrace? stack, {bool fatal = false}) =>
      _crashlytics.recordError(error, stack, fatal: fatal).ignore();

  @override
  void log(String breadcrumb) => _crashlytics.log(breadcrumb).ignore();

  @override
  void setUserId(String? id) =>
      _crashlytics.setUserIdentifier(id ?? '').ignore();
}
