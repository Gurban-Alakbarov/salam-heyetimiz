import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'door_widget_service.dart';

/// Home-screen door-widget DI — granular providers (no god-provider; Constitution §5).

final doorWidgetServiceProvider = Provider<DoorWidgetService>(
  (ref) => const DoorWidgetService(),
);

/// One installed widget instance + the barrier it is currently bound to (null when
/// unconfigured). Drives the widget-management screen (W5).
class DoorWidgetInstance {
  const DoorWidgetInstance({required this.widgetId, this.config});

  final int widgetId;
  final DoorWidgetConfig? config;
}

/// The user's installed door-widget instances, each with its current binding.
/// autoDispose + refreshable so the management screen reflects a (re)configure.
final installedDoorWidgetsProvider =
    FutureProvider.autoDispose<List<DoorWidgetInstance>>((ref) async {
      final service = ref.watch(doorWidgetServiceProvider);
      final widgets = await service.installedWidgets();
      final result = <DoorWidgetInstance>[];
      for (final w in widgets) {
        final id = w.androidWidgetId;
        if (id == null) continue;
        result.add(
          DoorWidgetInstance(widgetId: id, config: await service.current(id)),
        );
      }
      return result;
    });
