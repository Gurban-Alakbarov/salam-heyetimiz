/// Feature flags (Constitution §13, FEATURE_FLAGS.md). v1 = local defaults +
/// (later) the live backend bootstrap flags + a future Remote-Config adapter.
/// New modules ship behind a flag, default OFF.
enum FeatureFlag {
  payments,
  biometric,
  notifications,
  push,
  password,
  profileEdit,
  family,
  visitors,
  marketplace,
  maintenance,
  debug,
}

abstract class FeatureFlagService {
  bool isEnabled(FeatureFlag flag);
}

class LocalFeatureFlagService implements FeatureFlagService {
  const LocalFeatureFlagService([this._overrides = const {}]);

  final Map<FeatureFlag, bool> _overrides;

  static const Map<FeatureFlag, bool> _defaults = {
    FeatureFlag.payments: true,
    FeatureFlag.biometric: true,
    // In-app inbox implemented (Frozen Phase 3); the screen self-gates, so this turns on the inbox + badge.
    FeatureFlag.notifications: true,
    // Push delivery is implemented for Android (Phase 4A); the service self-guards
    // to Android + an initialised Firebase app, so this is inert elsewhere.
    FeatureFlag.push: true,
    FeatureFlag.password: false,
    FeatureFlag.profileEdit: false,
    FeatureFlag.family: false,
    FeatureFlag.visitors: false,
    FeatureFlag.marketplace: false,
    FeatureFlag.maintenance: false,
    FeatureFlag.debug: false,
  };

  @override
  bool isEnabled(FeatureFlag flag) =>
      _overrides[flag] ?? _defaults[flag] ?? false;
}
