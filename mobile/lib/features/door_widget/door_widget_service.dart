import 'package:home_widget/home_widget.dart';

/// The barrier a single home-screen widget instance is bound to (W5). Only the
/// non-sensitive id + display label — never a token.
class DoorWidgetConfig {
  const DoorWidgetConfig({required this.deviceId, required this.deviceLabel});

  final int deviceId;
  final String deviceLabel;
}

/// Bridges the app and the Android home-screen widgets. W5: configuration is
/// PER-INSTANCE, keyed by the real Android `AppWidgetId`, so two widgets can point
/// at two different doors. Legacy (pre-W5) global keys are kept as a read fallback
/// so existing widgets keep working until reconfigured.
///
/// Every value written here is NON-SENSITIVE (device id/label, a status CODE — never
/// localized text —, a cooldown timestamp, the flavor base URL, the app locale code).
/// No token or secret is ever stored.
class DoorWidgetService {
  const DoorWidgetService();

  static const String androidProvider =
      'com.salamheyetimiz.salam_mobile.DoorWidgetProvider';

  // ── Per-instance keys (suffixed by the real AppWidgetId) ───────────────────
  static String deviceIdKey(int widgetId) => 'door_widget_device_id_$widgetId';
  static String deviceLabelKey(int widgetId) =>
      'door_widget_device_label_$widgetId';
  static String statusCodeKey(int widgetId) =>
      'door_widget_status_code_$widgetId';
  static String lastOpenMsKey(int widgetId) =>
      'door_widget_last_open_ms_$widgetId';

  // ── Legacy global keys (pre-W5) — READ fallback only ───────────────────────
  static const String legacyDeviceId = 'door_widget_device_id';
  static const String legacyDeviceLabel = 'door_widget_device_label';
  static const String legacyStatusCode = 'door_widget_status_code';
  static const String legacyLastOpenMs = 'door_widget_last_open_ms';

  // ── Truly global (instance-independent) — non-secret config ────────────────
  static const String keyBaseUrl = 'door_widget_base_url';
  static const String keyLocale = 'door_widget_locale';

  /// Status-code storage key for [widgetId] (per-instance), or the legacy global key
  /// when [widgetId] is null (a pre-W5 click with no widgetId in its URI).
  static String resolvedStatusKey(int? widgetId) =>
      widgetId != null ? statusCodeKey(widgetId) : legacyStatusCode;

  /// Cooldown storage key for [widgetId] (per-instance) or the legacy global key.
  static String resolvedLastOpenKey(int? widgetId) =>
      widgetId != null ? lastOpenMsKey(widgetId) : legacyLastOpenMs;

  /// The barrier bound to [widgetId]. Falls back to the legacy global config so a
  /// pre-W5 widget keeps working until it is reconfigured per-instance. Returns null
  /// (→ native shows the "no device" hint) when nothing is bound.
  Future<DoorWidgetConfig?> current(int? widgetId) async {
    if (widgetId != null) {
      final perInstance = await _read(
        deviceIdKey(widgetId),
        deviceLabelKey(widgetId),
      );
      if (perInstance != null) return perInstance;
    }
    return _read(legacyDeviceId, legacyDeviceLabel); // legacy global fallback
  }

  Future<DoorWidgetConfig?> _read(String idKey, String labelKey) async {
    final rawId = await HomeWidget.getWidgetData<dynamic>(idKey);
    final label = await HomeWidget.getWidgetData<String>(labelKey);
    final id = rawId is int ? rawId : (rawId is num ? rawId.toInt() : null);
    if (id == null || label == null || label.isEmpty) return null;
    return DoorWidgetConfig(deviceId: id, deviceLabel: label);
  }

  /// Bind [deviceId]/[deviceLabel] to [widgetId] and refresh. A (re)selected barrier
  /// starts clean: its prior status + cooldown are reset so nothing stale lingers.
  Future<void> setDevice(
    int widgetId, {
    required int deviceId,
    required String deviceLabel,
  }) async {
    await HomeWidget.saveWidgetData<int>(deviceIdKey(widgetId), deviceId);
    await HomeWidget.saveWidgetData<String>(deviceLabelKey(widgetId), deviceLabel);
    await HomeWidget.saveWidgetData<String>(statusCodeKey(widgetId), null);
    await HomeWidget.saveWidgetData<int>(lastOpenMsKey(widgetId), null);
    await refresh();
  }

  /// Clear a single instance's config (in-app unconfigure). The native `onDeleted`
  /// handles OS-driven widget removal directly.
  Future<void> clearInstance(int widgetId) async {
    await HomeWidget.saveWidgetData<int>(deviceIdKey(widgetId), null);
    await HomeWidget.saveWidgetData<String>(deviceLabelKey(widgetId), null);
    await HomeWidget.saveWidgetData<String>(statusCodeKey(widgetId), null);
    await HomeWidget.saveWidgetData<int>(lastOpenMsKey(widgetId), null);
    await refresh();
  }

  /// Logout cleanup: wipe EVERY installed instance's config + the legacy global keys
  /// + the base URL. Keeps the app locale (not user-specific). Never touched a token.
  Future<void> clearAll() async {
    for (final widget in await _installedDoorWidgets()) {
      final id = widget.androidWidgetId;
      if (id != null) {
        await HomeWidget.saveWidgetData<int>(deviceIdKey(id), null);
        await HomeWidget.saveWidgetData<String>(deviceLabelKey(id), null);
        await HomeWidget.saveWidgetData<String>(statusCodeKey(id), null);
        await HomeWidget.saveWidgetData<int>(lastOpenMsKey(id), null);
      }
    }
    await HomeWidget.saveWidgetData<int>(legacyDeviceId, null);
    await HomeWidget.saveWidgetData<String>(legacyDeviceLabel, null);
    await HomeWidget.saveWidgetData<String>(legacyStatusCode, null);
    await HomeWidget.saveWidgetData<int>(legacyLastOpenMs, null);
    await HomeWidget.saveWidgetData<String>(keyBaseUrl, null);
    await refresh();
  }

  /// Installed instances of THIS widget provider (canonical enumeration).
  Future<List<HomeWidgetInfo>> _installedDoorWidgets() async {
    try {
      final all = await HomeWidget.getInstalledWidgets();
      return all
          .where(
            (w) => (w.androidClassName ?? '').contains('DoorWidgetProvider'),
          )
          .toList();
    } catch (_) {
      return const [];
    }
  }

  /// Public accessor for the widget-management screen.
  Future<List<HomeWidgetInfo>> installedWidgets() => _installedDoorWidgets();

  // ── Global config (base URL + locale) ──────────────────────────────────────
  Future<String?> baseUrl() async {
    final url = await HomeWidget.getWidgetData<String>(keyBaseUrl);
    return (url != null && url.isNotEmpty) ? url : null;
  }

  Future<void> setBaseUrl(String baseUrl) =>
      HomeWidget.saveWidgetData<String>(keyBaseUrl, baseUrl);

  /// Persist the app locale code (az/ru/en) for the native widget to localize with,
  /// then refresh all widgets so they re-render in the new language (W5 D2).
  Future<void> setLocale(String localeCode) async {
    await HomeWidget.saveWidgetData<String>(keyLocale, localeCode);
    await refresh();
  }

  /// Refresh EVERY instance of the provider in one call.
  Future<void> refresh() =>
      HomeWidget.updateWidget(qualifiedAndroidName: androidProvider);
}
