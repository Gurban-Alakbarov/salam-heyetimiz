import '../../../../core/analytics/analytics_service.dart';
import '../../../../core/crash/crash_reporter.dart';
import '../../../../core/error/failure.dart';
import '../entity/auth_entities.dart';
import '../repository.dart';

/// Which OTP flow a verification belongs to (drives the success event + routing).
enum AuthFlow { register, login }

/// Analytics event names (ANALYTICS_PLAN.md). No PII is ever attached.
class AuthEvents {
  AuthEvents._();
  static const registerStarted = 'register_started';
  static const registerSuccess = 'register_success';
  static const loginStarted = 'login_started';
  static const loginSuccess = 'login_success';
  static const otpRequested = 'otp_requested';
  static const otpVerified = 'otp_verified';
  static const logout = 'logout';
}

/// register → dispatch OTP. Multi-concern (analytics + crash breadcrumb), hence
/// a use-case (Constitution §1.10).
class RegisterUseCase {
  RegisterUseCase(this._repo, this._analytics, this._crash);
  final AuthRepository _repo;
  final AnalyticsService _analytics;
  final CrashReporter _crash;

  Future<Result<OtpDispatch>> call({
    required String firstName,
    required String lastName,
    required String phone,
    required String email,
  }) async {
    _analytics.logEvent(AuthEvents.registerStarted);
    _crash.log('auth: register_attempt');
    final result = await _repo.register(
      firstName: firstName,
      lastName: lastName,
      phone: phone,
      email: email,
    );
    if (result.isSuccess) {
      _analytics.logEvent(
        AuthEvents.otpRequested,
        params: {'flow': 'register'},
      );
    }
    return result;
  }
}

class LoginUseCase {
  LoginUseCase(this._repo, this._analytics, this._crash);
  final AuthRepository _repo;
  final AnalyticsService _analytics;
  final CrashReporter _crash;

  Future<Result<OtpDispatch>> call({required String email}) async {
    _analytics.logEvent(AuthEvents.loginStarted);
    _crash.log('auth: login_attempt');
    final result = await _repo.login(email: email);
    if (result.isSuccess) {
      _analytics.logEvent(AuthEvents.otpRequested, params: {'flow': 'login'});
    }
    return result;
  }
}

class ResendOtpUseCase {
  ResendOtpUseCase(this._repo, this._analytics);
  final AuthRepository _repo;
  final AnalyticsService _analytics;

  Future<Result<OtpDispatch>> call({required String email}) async {
    final result = await _repo.resendOtp(email: email);
    if (result.isSuccess) {
      _analytics.logEvent(AuthEvents.otpRequested, params: {'flow': 'resend'});
    }
    return result;
  }
}

class VerifyOtpUseCase {
  VerifyOtpUseCase(this._repo, this._analytics, this._crash);
  final AuthRepository _repo;
  final AnalyticsService _analytics;
  final CrashReporter _crash;

  Future<Result<UserEntity>> call({
    required String email,
    required String code,
    required AuthFlow flow,
  }) async {
    _crash.log('auth: verify_attempt');
    final result = await _repo.verifyEmail(email: email, code: code);
    if (result.isSuccess) {
      _analytics.logEvent(AuthEvents.otpVerified, params: {'flow': flow.name});
      _analytics.logEvent(
        flow == AuthFlow.register
            ? AuthEvents.registerSuccess
            : AuthEvents.loginSuccess,
      );
    }
    return result;
  }
}

class LogoutUseCase {
  LogoutUseCase(this._repo, this._analytics, [this._onBeforeLogout]);
  final AuthRepository _repo;
  final AnalyticsService _analytics;

  /// Best-effort hook run while the session token is still present (used to
  /// de-register the FCM push token before the session is cleared). Never blocks logout.
  final Future<void> Function()? _onBeforeLogout;

  Future<Result<void>> call() async {
    try {
      await _onBeforeLogout?.call();
    } catch (_) {
      // Ignore — a missed de-registration must not prevent the user logging out.
    }
    final result = await _repo.logout();
    _analytics.logEvent(AuthEvents.logout);
    return result;
  }
}
