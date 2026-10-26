import '../../../core/error/failure.dart';
import 'entity/auth_entities.dart';

/// Auth domain contract (Constitution §1.5/§3.1). Implemented in the data layer;
/// every method returns a [Result] — exceptions never cross this boundary.
abstract class AuthRepository {
  Future<Result<BootstrapEntity>> bootstrap();

  Future<Result<OtpDispatch>> register({
    required String firstName,
    required String lastName,
    required String phone,
    required String email,
    String? accountType,
    String? invitationToken,
  });

  Future<Result<OtpDispatch>> login({required String email});

  Future<Result<OtpDispatch>> resendOtp({required String email});

  /// Verifies the OTP and, on success, stores the session (tokens) internally.
  Future<Result<UserEntity>> verifyEmail({
    required String email,
    required String code,
  });

  Future<Result<MeEntity>> me();

  /// Best-effort server revoke + always clears the local session.
  Future<Result<void>> logout();

  /// Y7 — deletes the account on the server; clears the local session ONLY on
  /// success (a refusal — e.g. 409 for a device owner — keeps the user signed in).
  Future<Result<void>> deleteAccount();
}
