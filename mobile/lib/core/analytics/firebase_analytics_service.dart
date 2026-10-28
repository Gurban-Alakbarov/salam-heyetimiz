import 'package:firebase_analytics/firebase_analytics.dart';
import 'package:salam_mobile/core/analytics/analytics_service.dart';

/// Firebase Analytics adapter (1.0.1). Firebase accepts only String / num parameter values, so others are
/// stringified and nulls dropped. No PII: callers pass enums, counts and latencies, never names, emails
/// or phone numbers. Every call is fire-and-forget — analytics must never break a user flow.
class FirebaseAnalyticsService implements AnalyticsService {
  FirebaseAnalyticsService(this._analytics);

  final FirebaseAnalytics _analytics;

  @override
  void logEvent(String name, {Map<String, Object?> params = const {}}) {
    final parameters = <String, Object>{
      for (final MapEntry(:key, :value) in params.entries)
        if (value != null) key: value is num ? value : value.toString(),
    };
    _analytics
        .logEvent(
          name: name,
          parameters: parameters.isEmpty ? null : parameters,
        )
        .ignore();
  }

  @override
  void logScreenView(String screen) =>
      _analytics.logScreenView(screenName: screen).ignore();

  @override
  void setUserId(String? id) => _analytics.setUserId(id: id).ignore();

  @override
  void reset() => _analytics.resetAnalyticsData().ignore();

  @override
  Future<void> setEnabled(bool enabled) =>
      _analytics.setAnalyticsCollectionEnabled(enabled);
}
