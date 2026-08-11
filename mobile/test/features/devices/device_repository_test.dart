import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/devices/data/repository_impl.dart';

import '../../helpers/mocks.dart';

void main() {
  late MockDeviceRemoteDataSource remote;
  late DeviceRepositoryImpl repo;

  setUp(() {
    remote = MockDeviceRemoteDataSource();
    repo = DeviceRepositoryImpl(remote);
  });

  test('list parses {data, page} into a DevicePage', () async {
    when(
      () => remote.list(
        filter: any(named: 'filter'),
        cursor: any(named: 'cursor'),
        limit: any(named: 'limit'),
      ),
    ).thenAnswer(
      (_) async => {
        'data': [
          {
            'id': 1,
            'label': 'My Gate',
            'serial': 'SALAM-1',
            'status': 'active',
            'role': 'owner',
            'can_open': true,
            'last_online_at': '2026-06-29T15:00:00Z',
          },
        ],
        'page': {'next_cursor': 'abc', 'has_more': true, 'limit': 25},
      },
    );

    final result = await repo.list(limit: 25);

    expect(result.isSuccess, isTrue);
    final page = (result as Success).value;
    expect(page.devices.length, 1);
    expect(page.devices.first.label, 'My Gate');
    expect(page.devices.first.isActive, isTrue);
    expect(page.devices.first.isOwner, isTrue);
    expect(page.hasMore, isTrue);
  });

  test('list maps 401 → UnauthorizedFailure (e.g. offline session)', () async {
    when(
      () => remote.list(
        filter: any(named: 'filter'),
        cursor: any(named: 'cursor'),
        limit: any(named: 'limit'),
      ),
    ).thenThrow(dioError(status: 401));

    final result = await repo.list(limit: 25);

    expect((result as Err).failure, isA<UnauthorizedFailure>());
  });

  test('list maps connectionError → NetworkFailure (offline list)', () async {
    when(
      () => remote.list(
        filter: any(named: 'filter'),
        cursor: any(named: 'cursor'),
        limit: any(named: 'limit'),
      ),
    ).thenThrow(dioOffline());

    final result = await repo.list(limit: 25);

    expect((result as Err).failure, isA<NetworkFailure>());
  });

  test('detail success maps the bare device object', () async {
    when(() => remote.detail(any())).thenAnswer(
      (_) async => {
        'id': 42,
        'label': 'Gate 42',
        'status': 'active',
        'role': 'user',
        'can_open': true,
        'device_model': {'vendor': 'UMKa', 'model_code': '310'},
      },
    );

    final result = await repo.detail(42);

    expect(result.isSuccess, isTrue);
    final device = (result as Success).value;
    expect(device.id, 42);
    expect(device.model?.vendor, 'UMKa');
  });

  test('detail maps 404 → NotFoundFailure', () async {
    when(() => remote.detail(any())).thenThrow(dioError(status: 404));

    final result = await repo.detail(99);

    expect((result as Err).failure, isA<NotFoundFailure>());
  });
}
