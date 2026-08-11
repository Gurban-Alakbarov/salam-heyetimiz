import 'package:dio/dio.dart';

import '../../../core/error/failure.dart';
import '../../../core/network/envelope.dart';
import '../../../core/session/session_manager.dart';
import '../domain/entity/auth_entities.dart';
import '../domain/repository.dart';
import 'datasource/auth_remote_datasource.dart';
import 'mapper/auth_mappers.dart';

class AuthRepositoryImpl implements AuthRepository {
  AuthRepositoryImpl(this._remote, this._session);

  final AuthRemoteDataSource _remote;
  final SessionManager _session;

  /// Runs [run], translating any thrown error into a typed [Failure].
  Future<Result<T>> _guard<T>(Future<T> Function() run) async {
    try {
      return Success(await run());
    } on DioException catch (e) {
      return Err(mapDioError(e));
    } catch (_) {
      return const Err(UnknownFailure());
    }
  }

  @override
  Future<Result<BootstrapEntity>> bootstrap() =>
      _guard(() async => bootstrapDtoToEntity(await _remote.bootstrap()));

  @override
  Future<Result<OtpDispatch>> register({
    required String firstName,
    required String lastName,
    required String phone,
    required String email,
  }) => _guard(
    () async => _otpFromMeta(
      await _remote.register(
        firstName: firstName,
        lastName: lastName,
        phone: phone,
        email: email,
      ),
    ),
  );

  @override
  Future<Result<OtpDispatch>> login({required String email}) =>
      _guard(() async => _otpFromMeta(await _remote.login(email: email)));

  @override
  Future<Result<OtpDispatch>> resendOtp({required String email}) =>
      _guard(() async => _otpFromMeta(await _remote.resendOtp(email: email)));

  @override
  Future<Result<UserEntity>> verifyEmail({
    required String email,
    required String code,
  }) => _guard(() async {
    final token = await _remote.verifyEmail(email: email, code: code);
    await _session.save(tokenDtoToSession(token));
    final user = token.user;
    if (user == null) throw StateError('verify-email returned no user');
    return userDtoToEntity(user);
  });

  @override
  Future<Result<MeEntity>> me() =>
      _guard(() async => meDtoToEntity(await _remote.me()));

  @override
  Future<Result<void>> logout() async {
    try {
      await _remote.logout();
    } catch (_) {
      // Best-effort server revoke; local clear below always runs.
    }
    await _session.clear();
    return const Success(null);
  }

  OtpDispatch _otpFromMeta(Map<String, dynamic> meta) => OtpDispatch(
    expiresInSeconds: (meta['expires_in_seconds'] as num?)?.toInt() ?? 120,
    resendAvailableInSeconds:
        (meta['resend_available_in_seconds'] as num?)?.toInt() ?? 30,
  );
}
