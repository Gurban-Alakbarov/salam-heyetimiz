import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/error/failure.dart';
import '../../auth_providers.dart';
import '../../domain/entity/auth_entities.dart';
import '../../domain/usecase/auth_use_cases.dart';

/// Screen view-models (Constitution §1.7, STATE_MANAGEMENT.md §2). State is an
/// [AsyncValue]: idle (`AsyncData(null)`) → loading → data/error. Widgets watch
/// it for spinners + inline/field errors; submit returns the value for routing.

class RegisterController extends Notifier<AsyncValue<OtpDispatch?>> {
  @override
  AsyncValue<OtpDispatch?> build() => const AsyncData(null);

  Future<OtpDispatch?> submit({
    required String firstName,
    required String lastName,
    required String phone,
    required String email,
  }) async {
    state = const AsyncLoading();
    final result = await ref
        .read(registerUseCaseProvider)
        .call(
          firstName: firstName,
          lastName: lastName,
          phone: phone,
          email: email,
        );
    return result.fold(
      (failure) {
        state = AsyncError(failure, StackTrace.current);
        return null;
      },
      (otp) {
        state = AsyncData(otp);
        return otp;
      },
    );
  }
}

final registerControllerProvider =
    NotifierProvider.autoDispose<RegisterController, AsyncValue<OtpDispatch?>>(
      RegisterController.new,
    );

class LoginController extends Notifier<AsyncValue<OtpDispatch?>> {
  @override
  AsyncValue<OtpDispatch?> build() => const AsyncData(null);

  Future<OtpDispatch?> submit({required String email}) async {
    state = const AsyncLoading();
    final result = await ref.read(loginUseCaseProvider).call(email: email);
    return result.fold(
      (failure) {
        state = AsyncError(failure, StackTrace.current);
        return null;
      },
      (otp) {
        state = AsyncData(otp);
        return otp;
      },
    );
  }
}

final loginControllerProvider =
    NotifierProvider.autoDispose<LoginController, AsyncValue<OtpDispatch?>>(
      LoginController.new,
    );

class VerifyController extends Notifier<AsyncValue<UserEntity?>> {
  @override
  AsyncValue<UserEntity?> build() => const AsyncData(null);

  Future<UserEntity?> submit({
    required String email,
    required String code,
    required AuthFlow flow,
  }) async {
    state = const AsyncLoading();
    final result = await ref
        .read(verifyOtpUseCaseProvider)
        .call(email: email, code: code, flow: flow);
    return result.fold(
      (failure) {
        state = AsyncError(failure, StackTrace.current);
        return null;
      },
      (user) {
        state = AsyncData(user);
        return user;
      },
    );
  }

  /// Re-dispatch the OTP. Returns the new dispatch (for the resend countdown);
  /// does not change the verify state. Returns null + leaves a Failure for the
  /// caller to surface on error.
  Future<OtpDispatch?> resend({required String email}) async {
    final result = await ref.read(resendOtpUseCaseProvider).call(email: email);
    return result.fold(
      (failure) {
        _lastResendFailure = failure;
        return null;
      },
      (otp) {
        _lastResendFailure = null;
        return otp;
      },
    );
  }

  Failure? _lastResendFailure;
  Failure? get lastResendFailure => _lastResendFailure;
}

final verifyControllerProvider =
    NotifierProvider.autoDispose<VerifyController, AsyncValue<UserEntity?>>(
      VerifyController.new,
    );
