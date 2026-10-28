import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/di/providers.dart';
import 'package:salam_mobile/core/storage/storage_keys.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../helpers/mocks.dart';

/// 1.0.1 — the "send usage statistics" switch: starts from the persisted choice, applies a change to the
/// analytics SDK at once and persists it for the next launch.
void main() {
  late MockAnalyticsService analytics;

  setUp(() {
    analytics = MockAnalyticsService();
    when(() => analytics.setEnabled(any())).thenAnswer((_) async {});
  });

  ProviderContainer make({bool initial = true}) {
    final c = ProviderContainer(
      overrides: [
        analyticsProvider.overrideWithValue(analytics),
        initialAnalyticsEnabledProvider.overrideWithValue(initial),
      ],
    );
    addTearDown(c.dispose);
    return c;
  }

  test('starts from the hydrated choice', () {
    SharedPreferences.setMockInitialValues({});
    expect(make().read(analyticsEnabledProvider), isTrue);
    expect(make(initial: false).read(analyticsEnabledProvider), isFalse);
  });

  test('switching off disables the SDK and persists the choice', () async {
    SharedPreferences.setMockInitialValues({});
    final c = make();

    await c.read(analyticsEnabledProvider.notifier).set(false);

    expect(c.read(analyticsEnabledProvider), isFalse);
    verify(() => analytics.setEnabled(false)).called(1);
    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getBool(StorageKeys.analyticsEnabled), isFalse);
  });

  test('switching back on re-enables the SDK', () async {
    SharedPreferences.setMockInitialValues({StorageKeys.analyticsEnabled: false});
    final c = make(initial: false);

    await c.read(analyticsEnabledProvider.notifier).set(true);

    verify(() => analytics.setEnabled(true)).called(1);
    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getBool(StorageKeys.analyticsEnabled), isTrue);
  });
}
