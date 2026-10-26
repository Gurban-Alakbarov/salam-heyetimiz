import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/storage/storage_keys.dart';
import 'package:salam_mobile/features/door_widget/widget_token_sync.dart';

import '../../helpers/mocks.dart';

/// The widget's open-only token is fetched once, kept for a week, re-issued after, and a failed call never
/// throws or wipes the token the widget already has.
void main() {
  late MockApiClient api;
  late MockSecureStore store;
  final now = DateTime(2026, 10, 27, 12);
  final values = <String, String>{};

  Response<dynamic> ok(String token) => Response<dynamic>(
    data: {'data': {'token': token, 'expires_at': '2027-01-25T12:00:00+04:00'}},
    statusCode: 201,
    requestOptions: RequestOptions(path: '/v1/me/widget-token'),
  );

  setUp(() {
    api = MockApiClient();
    store = MockSecureStore();
    values.clear();
    when(() => store.read(any())).thenAnswer((i) async => values[i.positionalArguments.first as String]);
    when(() => store.write(any(), any())).thenAnswer((i) async {
      values[i.positionalArguments[0] as String] = i.positionalArguments[1] as String;
    });
  });

  test('fetches and stores a token when there is none', () async {
    when(() => api.post('/v1/me/widget-token')).thenAnswer((_) async => ok('wgt_new'));

    await WidgetTokenSync(api, store, now: () => now).ensure();

    expect(values[StorageKeys.widgetToken], 'wgt_new');
    expect(values[StorageKeys.widgetTokenIssuedAt], now.millisecondsSinceEpoch.toString());
  });

  test('keeps a token younger than a week, re-issues an older one', () async {
    values[StorageKeys.widgetToken] = 'wgt_old';
    values[StorageKeys.widgetTokenIssuedAt] = now.subtract(const Duration(days: 3)).millisecondsSinceEpoch.toString();
    await WidgetTokenSync(api, store, now: () => now).ensure();
    verifyNever(() => api.post(any()));

    values[StorageKeys.widgetTokenIssuedAt] = now.subtract(const Duration(days: 8)).millisecondsSinceEpoch.toString();
    when(() => api.post('/v1/me/widget-token')).thenAnswer((_) async => ok('wgt_next'));
    await WidgetTokenSync(api, store, now: () => now).ensure();
    expect(values[StorageKeys.widgetToken], 'wgt_next');
  });

  test('a failed call keeps the token the widget already has', () async {
    values[StorageKeys.widgetToken] = 'wgt_old';
    when(() => api.post('/v1/me/widget-token')).thenThrow(Exception('offline'));

    await WidgetTokenSync(api, store, now: () => now).ensure(force: true);

    expect(values[StorageKeys.widgetToken], 'wgt_old');
  });
}
