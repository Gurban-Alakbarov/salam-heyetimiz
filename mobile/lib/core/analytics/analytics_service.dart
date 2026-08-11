/// Provider-agnostic analytics (Constitution §11, ANALYTICS_PLAN.md). Screens never
/// call a vendor SDK directly. Default = Noop; Firebase/PostHog plug in as adapters.
abstract class AnalyticsService {
  void logEvent(String name, {Map<String, Object?> params});
  void logScreenView(String screen);
  void setUserId(String? id);
  void reset();
}

class NoopAnalytics implements AnalyticsService {
  const NoopAnalytics();

  @override
  void logEvent(String name, {Map<String, Object?> params = const {}}) {}
  @override
  void logScreenView(String screen) {}
  @override
  void setUserId(String? id) {}
  @override
  void reset() {}
}
