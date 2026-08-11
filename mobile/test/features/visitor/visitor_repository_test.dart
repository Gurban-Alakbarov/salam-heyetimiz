import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/visitor/data/repository_impl.dart';
import 'package:salam_mobile/features/visitor/domain/entity/visitor_entities.dart';

import '../../helpers/mocks.dart';

void main() {
  late MockVisitorRemoteDataSource remote;
  late VisitorRepositoryImpl repo;

  setUp(() {
    remote = MockVisitorRemoteDataSource();
    repo = VisitorRepositoryImpl(remote);
  });

  Future<Result<CreatedVisitorLink>> create() => repo.create(
    42,
    accessType: VisitorAccessType.oneTime,
    visitorName: 'Kuryer',
    purpose: VisitorPurpose.courier,
  );

  test('create success → CreatedVisitorLink (url + token + access type)', () async {
    when(
      () => remote.create(
        any(),
        accessType: any(named: 'accessType'),
        durationMinutes: any(named: 'durationMinutes'),
        visitorName: any(named: 'visitorName'),
        purpose: any(named: 'purpose'),
      ),
    ).thenAnswer(
      (_) async => {
        'link': {
          'id': 1,
          'visitor_name': 'Kuryer',
          'purpose': 'courier',
          'access_type': 'one_time',
          'status': 'active',
        },
        'token': 'PLAINTOKEN',
        'url': 'https://salamheyetimiz.com/v/PLAINTOKEN',
      },
    );

    final result = await create();

    expect(result.isSuccess, isTrue);
    final link = (result as Success).value;
    expect(link.url, 'https://salamheyetimiz.com/v/PLAINTOKEN');
    expect(link.token, 'PLAINTOKEN');
    expect(link.accessType, VisitorAccessType.oneTime);
    expect(link.visitorName, 'Kuryer');
  });

  test('create subscription_required (403) → ForbiddenFailure with code', () async {
    when(
      () => remote.create(
        any(),
        accessType: any(named: 'accessType'),
        durationMinutes: any(named: 'durationMinutes'),
        visitorName: any(named: 'visitorName'),
        purpose: any(named: 'purpose'),
      ),
    ).thenThrow(
      dioError(
        status: 403,
        body: {
          'error': {'code': 'subscription_required'},
        },
      ),
    );

    final failure = (await create() as Err).failure;
    expect(failure, isA<ForbiddenFailure>());
    expect(failure.code, 'subscription_required');
  });

  test('create limit reached (409) → ConflictFailure with code', () async {
    when(
      () => remote.create(
        any(),
        accessType: any(named: 'accessType'),
        durationMinutes: any(named: 'durationMinutes'),
        visitorName: any(named: 'visitorName'),
        purpose: any(named: 'purpose'),
      ),
    ).thenThrow(
      dioError(
        status: 409,
        body: {
          'error': {
            'code': 'visitor_link_limit_reached',
            'message': 'Limit reached',
          },
        },
      ),
    );

    final failure = (await create() as Err).failure;
    expect(failure, isA<ConflictFailure>());
    expect(failure.code, 'visitor_link_limit_reached');
  });

  test('time-limited create forwards duration_minutes to the datasource', () async {
    when(
      () => remote.create(
        any(),
        accessType: any(named: 'accessType'),
        durationMinutes: any(named: 'durationMinutes'),
        visitorName: any(named: 'visitorName'),
        purpose: any(named: 'purpose'),
      ),
    ).thenAnswer(
      (_) async => {
        'link': {'access_type': 'time_limited', 'status': 'active'},
        'token': 'T',
        'url': 'u',
      },
    );

    await repo.create(
      7,
      accessType: VisitorAccessType.timeLimited,
      durationMinutes: 120,
    );

    verify(
      () => remote.create(
        7,
        accessType: 'time_limited',
        durationMinutes: 120,
        visitorName: any(named: 'visitorName'),
        purpose: any(named: 'purpose'),
      ),
    ).called(1);
  });
}
