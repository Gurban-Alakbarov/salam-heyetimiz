import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/auth/data/dto/token_dto.dart';
import 'package:salam_mobile/features/auth/data/dto/user_dto.dart';
import 'package:salam_mobile/features/auth/data/repository_impl.dart';

import '../../helpers/mocks.dart';

void main() {
  late MockAuthRemoteDataSource remote;
  late MockSessionManager session;
  late AuthRepositoryImpl repo;

  setUpAll(registerCommonFallbacks);

  setUp(() {
    remote = MockAuthRemoteDataSource();
    session = MockSessionManager();
    repo = AuthRepositoryImpl(remote, session);
  });

  test('register success → Success(OtpDispatch) from meta', () async {
    when(
      () => remote.register(
        firstName: any(named: 'firstName'),
        lastName: any(named: 'lastName'),
        phone: any(named: 'phone'),
        email: any(named: 'email'),
      ),
    ).thenAnswer(
      (_) async => {
        'expires_in_seconds': 120,
        'resend_available_in_seconds': 30,
      },
    );

    final result = await repo.register(
      firstName: 'A',
      lastName: 'B',
      phone: '+994501234567',
      email: 'a@b.com',
    );

    expect(result.isSuccess, isTrue);
    final otp = (result as Success).value;
    expect(otp.expiresInSeconds, 120);
    expect(otp.resendAvailableInSeconds, 30);
  });

  test('register duplicate email (409) → Err(ConflictFailure)', () async {
    when(
      () => remote.register(
        firstName: any(named: 'firstName'),
        lastName: any(named: 'lastName'),
        phone: any(named: 'phone'),
        email: any(named: 'email'),
      ),
    ).thenThrow(
      dioError(
        status: 409,
        body: {
          'errors': {'code': 'email_already_registered'},
        },
      ),
    );

    final result = await repo.register(
      firstName: 'A',
      lastName: 'B',
      phone: '+994501234567',
      email: 'a@b.com',
    );

    expect(result, isA<Err>());
    expect((result as Err).failure, isA<ConflictFailure>());
  });

  test('register duplicate phone (422) → Err(ValidationFailure)', () async {
    when(
      () => remote.register(
        firstName: any(named: 'firstName'),
        lastName: any(named: 'lastName'),
        phone: any(named: 'phone'),
        email: any(named: 'email'),
      ),
    ).thenThrow(
      dioError(
        status: 422,
        body: {
          'errors': {
            'phone': ['Bu telefon nömrəsi artıq istifadədədir.'],
          },
        },
      ),
    );

    final result = await repo.register(
      firstName: 'A',
      lastName: 'B',
      phone: '+994501234567',
      email: 'a@b.com',
    );

    expect((result as Err).failure, isA<ValidationFailure>());
  });

  test('verifyEmail success stores session + returns user', () async {
    when(
      () => remote.verifyEmail(
        email: any(named: 'email'),
        code: any(named: 'code'),
      ),
    ).thenAnswer(
      (_) async => const TokenDto(
        accessToken: 'access',
        refreshToken: 'refresh',
        user: UserDto(id: 7, phone: '+994501234567', fullName: 'Aysel'),
      ),
    );
    when(() => session.save(any())).thenAnswer((_) async {});

    final result = await repo.verifyEmail(email: 'a@b.com', code: '123456');

    expect(result.isSuccess, isTrue);
    expect((result as Success).value.id, 7);
    verify(() => session.save(any())).called(1);
  });

  test('verifyEmail wrong code (401) → Err(OtpFailure)', () async {
    when(
      () => remote.verifyEmail(
        email: any(named: 'email'),
        code: any(named: 'code'),
      ),
    ).thenThrow(
      dioError(
        status: 401,
        body: {
          'errors': {'code': 'wrong_code'},
        },
      ),
    );

    final result = await repo.verifyEmail(email: 'a@b.com', code: '000000');

    expect((result as Err).failure, isA<OtpFailure>());
    verifyNever(() => session.save(any()));
  });

  test('logout always clears the session, even if the API throws', () async {
    when(() => remote.logout()).thenThrow(dioError(status: 401));
    when(() => session.clear()).thenAnswer((_) async {});

    final result = await repo.logout();

    expect(result.isSuccess, isTrue);
    verify(() => session.clear()).called(1);
  });
}
