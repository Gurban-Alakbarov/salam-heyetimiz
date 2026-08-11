import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/core/network/envelope.dart';

import '../helpers/mocks.dart';

void main() {
  group('mapDioError', () {
    test('401 wrong_code → OtpFailure', () {
      final f = mapDioError(
        dioError(
          status: 401,
          body: {
            'message': 'Təsdiq kodu yanlışdır.',
            'errors': {'code': 'wrong_code'},
          },
        ),
      );
      expect(f, isA<OtpFailure>());
      expect(f.code, 'wrong_code');
    });

    test('401 otp_expired → OtpFailure', () {
      final f = mapDioError(
        dioError(
          status: 401,
          body: {
            'errors': {'code': 'otp_expired'},
          },
        ),
      );
      expect(f, isA<OtpFailure>());
      expect((f as OtpFailure).code, 'otp_expired');
    });

    test('401 without otp code → UnauthorizedFailure', () {
      final f = mapDioError(
        dioError(status: 401, body: {'message': 'Unauthenticated'}),
      );
      expect(f, isA<UnauthorizedFailure>());
    });

    test('409 email_already_registered → ConflictFailure', () {
      final f = mapDioError(
        dioError(
          status: 409,
          body: {
            'errors': {'code': 'email_already_registered'},
          },
        ),
      );
      expect(f, isA<ConflictFailure>());
      expect(f.code, 'email_already_registered');
    });

    test('422 field map → ValidationFailure with fields', () {
      final f = mapDioError(
        dioError(
          status: 422,
          body: {
            'message': 'Doğrulama xətası baş verdi.',
            'errors': {
              'phone': ['Bu telefon nömrəsi artıq istifadədədir.'],
            },
          },
        ),
      );
      expect(f, isA<ValidationFailure>());
      expect(
        (f as ValidationFailure).firstFor('phone'),
        'Bu telefon nömrəsi artıq istifadədədir.',
      );
    });

    test('429 → RateLimitedFailure carries Retry-After', () {
      final f = mapDioError(
        dioError(
          status: 429,
          retryAfter: '42',
          body: {
            'errors': {'code': 'rate_limited'},
          },
        ),
      );
      expect(f, isA<RateLimitedFailure>());
      expect((f as RateLimitedFailure).retryAfterSeconds, 42);
    });

    test('500 → ServerFailure', () {
      expect(mapDioError(dioError(status: 500)), isA<ServerFailure>());
    });

    test('timeout → TimeoutFailure', () {
      expect(mapDioError(dioTimeout()), isA<TimeoutFailure>());
    });

    test('offline (connectionError) → NetworkFailure', () {
      expect(mapDioError(dioOffline()), isA<NetworkFailure>());
    });

    test('legacy {error:{code}} shape parsed', () {
      final f = mapDioError(
        dioError(
          status: 401,
          body: {
            'error': {'code': 'invalid_refresh_token', 'message': 'revoked'},
          },
        ),
      );
      expect(f, isA<UnauthorizedFailure>());
    });
  });

  group('Envelope', () {
    test('extracts data + meta from unified envelope', () {
      final body = {
        'success': true,
        'data': {'x': 1},
        'meta': {'expires_in_seconds': 120},
      };
      expect(Envelope.data(body)['x'], 1);
      expect(Envelope.meta(body)['expires_in_seconds'], 120);
    });
  });
}
