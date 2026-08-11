import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/auth/domain/entity/auth_entities.dart';
import 'package:salam_mobile/features/auth/domain/usecase/auth_use_cases.dart';

import '../../helpers/mocks.dart';

void main() {
  late MockAuthRepository repo;
  late MockAnalyticsService analytics;
  late MockCrashReporter crash;

  setUp(() {
    repo = MockAuthRepository();
    analytics = MockAnalyticsService();
    crash = MockCrashReporter();
  });

  test('RegisterUseCase: started + otpRequested on success', () async {
    when(
      () => repo.register(
        firstName: any(named: 'firstName'),
        lastName: any(named: 'lastName'),
        phone: any(named: 'phone'),
        email: any(named: 'email'),
      ),
    ).thenAnswer((_) async => const Success(OtpDispatch()));

    final result = await RegisterUseCase(repo, analytics, crash).call(
      firstName: 'A',
      lastName: 'B',
      phone: '+994501234567',
      email: 'a@b.com',
    );

    expect(result.isSuccess, isTrue);
    verify(() => analytics.logEvent(AuthEvents.registerStarted)).called(1);
    verify(
      () => analytics.logEvent(
        AuthEvents.otpRequested,
        params: any(named: 'params'),
      ),
    ).called(1);
  });

  test('RegisterUseCase: no otpRequested on failure', () async {
    when(
      () => repo.register(
        firstName: any(named: 'firstName'),
        lastName: any(named: 'lastName'),
        phone: any(named: 'phone'),
        email: any(named: 'email'),
      ),
    ).thenAnswer(
      (_) async => const Err(ConflictFailure('email_already_registered')),
    );

    await RegisterUseCase(repo, analytics, crash).call(
      firstName: 'A',
      lastName: 'B',
      phone: '+994501234567',
      email: 'a@b.com',
    );

    verify(() => analytics.logEvent(AuthEvents.registerStarted)).called(1);
    verifyNever(
      () => analytics.logEvent(
        AuthEvents.otpRequested,
        params: any(named: 'params'),
      ),
    );
  });

  test('LoginUseCase: started + otpRequested on success', () async {
    when(
      () => repo.login(email: any(named: 'email')),
    ).thenAnswer((_) async => const Success(OtpDispatch()));

    await LoginUseCase(repo, analytics, crash).call(email: 'a@b.com');

    verify(() => analytics.logEvent(AuthEvents.loginStarted)).called(1);
    verify(
      () => analytics.logEvent(
        AuthEvents.otpRequested,
        params: any(named: 'params'),
      ),
    ).called(1);
  });

  test('ResendOtpUseCase: otpRequested on success', () async {
    when(
      () => repo.resendOtp(email: any(named: 'email')),
    ).thenAnswer((_) async => const Success(OtpDispatch()));

    await ResendOtpUseCase(repo, analytics).call(email: 'a@b.com');

    verify(
      () => analytics.logEvent(
        AuthEvents.otpRequested,
        params: any(named: 'params'),
      ),
    ).called(1);
  });

  test('VerifyOtpUseCase (register): otpVerified + registerSuccess', () async {
    when(
      () => repo.verifyEmail(
        email: any(named: 'email'),
        code: any(named: 'code'),
      ),
    ).thenAnswer(
      (_) async => const Success(UserEntity(id: 1, phone: '+994501234567')),
    );

    await VerifyOtpUseCase(
      repo,
      analytics,
      crash,
    ).call(email: 'a@b.com', code: '123456', flow: AuthFlow.register);

    verify(
      () => analytics.logEvent(
        AuthEvents.otpVerified,
        params: any(named: 'params'),
      ),
    ).called(1);
    verify(() => analytics.logEvent(AuthEvents.registerSuccess)).called(1);
  });

  test('VerifyOtpUseCase (login): otpVerified + loginSuccess', () async {
    when(
      () => repo.verifyEmail(
        email: any(named: 'email'),
        code: any(named: 'code'),
      ),
    ).thenAnswer(
      (_) async => const Success(UserEntity(id: 1, phone: '+994501234567')),
    );

    await VerifyOtpUseCase(
      repo,
      analytics,
      crash,
    ).call(email: 'a@b.com', code: '123456', flow: AuthFlow.login);

    verify(() => analytics.logEvent(AuthEvents.loginSuccess)).called(1);
  });

  test('LogoutUseCase: emits logout', () async {
    when(() => repo.logout()).thenAnswer((_) async => const Success(null));

    await LogoutUseCase(repo, analytics).call();

    verify(() => analytics.logEvent(AuthEvents.logout)).called(1);
  });
}
