import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/logger/app_logger.dart';
import 'package:salam_mobile/core/session/session_manager.dart';
import 'package:salam_mobile/core/storage/storage_keys.dart';

import '../helpers/mocks.dart';

void main() {
  late MockSecureStore store;
  late SessionManager session;

  setUp(() {
    store = MockSecureStore();
    session = SessionManager(
      store: store,
      baseUrl: 'https://example.test',
      logger: AppLogger(enabled: false),
    );
  });

  test('load() hydrates an existing session from secure storage', () async {
    when(
      () => store.read(StorageKeys.accessToken),
    ).thenAnswer((_) async => 'access');
    when(
      () => store.read(StorageKeys.refreshToken),
    ).thenAnswer((_) async => 'refresh');

    await session.load();

    expect(session.isAuthenticated, isTrue);
    expect(session.accessToken, 'access');
  });

  test('load() leaves guest state when no tokens stored', () async {
    when(() => store.read(any())).thenAnswer((_) async => null);

    await session.load();

    expect(session.isAuthenticated, isFalse);
  });

  test('save() persists tokens and notifies listeners', () async {
    when(() => store.write(any(), any())).thenAnswer((_) async {});
    var notified = false;
    session.addListener(() => notified = true);

    await session.save(const Session(accessToken: 'a', refreshToken: 'r'));

    expect(session.isAuthenticated, isTrue);
    expect(notified, isTrue);
    verify(() => store.write(StorageKeys.accessToken, 'a')).called(1);
    verify(() => store.write(StorageKeys.refreshToken, 'r')).called(1);
  });

  test('clear() wipes tokens and notifies', () async {
    when(() => store.write(any(), any())).thenAnswer((_) async {});
    when(() => store.delete(any())).thenAnswer((_) async {});
    await session.save(const Session(accessToken: 'a', refreshToken: 'r'));

    var notified = false;
    session.addListener(() => notified = true);
    await session.clear();

    expect(session.isAuthenticated, isFalse);
    expect(notified, isTrue);
    verify(() => store.delete(StorageKeys.accessToken)).called(1);
  });

  test('refresh() returns false when there is no refresh token', () async {
    expect(await session.refresh(), isFalse);
  });
}
