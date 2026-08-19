import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:home_widget/home_widget.dart';
import 'package:salam_mobile/core/di/providers.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/app_inputs.dart';
import 'package:salam_mobile/design_system/components/app_list.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/devices/devices_providers.dart';
import 'package:salam_mobile/features/devices/domain/entity/device_entities.dart';
import 'package:salam_mobile/features/door_widget/door_widget_providers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/shared/failure_message.dart';

/// Per-instance barrier picker (W5). Binds ONE widget instance ([widgetId], a real
/// Android AppWidgetId) to an authorized barrier. Reuses the existing device list
/// ([deviceListProvider] → GET /v1/devices) — no new endpoint, no new authorization.
///
/// Reached two ways: the Android "add widget" configure flow ([fromConfigure] = true,
/// so a selection finishes the configuration and closes the activity) or the in-app
/// widget-management screen ([fromConfigure] = false, so a selection just pops back).
class DoorWidgetPickerScreen extends ConsumerWidget {
  const DoorWidgetPickerScreen({
    required this.widgetId,
    this.fromConfigure = false,
    super.key,
  });

  final int widgetId;
  final bool fromConfigure;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final devicesAsync = ref.watch(deviceListProvider);

    return AppScaffold(
      title: l.doorWidgetTitle,
      body: devicesAsync.when(
        loading: () => const AppLoading(),
        error: (error, _) => ErrorStateView(
          message: error is Failure
              ? deviceFailureMessage(l, error)
              : l.errUnknown,
          onRetry: () => ref.refresh(deviceListProvider),
        ),
        data: (page) {
          if (page.devices.isEmpty) {
            return EmptyState(
              message: l.devicesEmpty,
              icon: Icons.meeting_room_outlined,
            );
          }
          return ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.md),
                child: Text(
                  l.doorWidgetIntro,
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ),
              AppListSection(
                children: [
                  for (final device in page.devices)
                    AppListTile(
                      icon: Icons.sensor_door_outlined,
                      title: device.label,
                      chevron: true,
                      onTap: () => _select(context, ref, device),
                    ),
                ],
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _select(BuildContext context, WidgetRef ref, Device device) async {
    final l = AppLocalizations.of(context);
    final navigator = Navigator.of(context);
    final service = ref.read(doorWidgetServiceProvider);
    await service.setDevice(
      widgetId,
      deviceId: device.id,
      deviceLabel: device.label,
    );
    // Invariant: a configured widget ⇒ the background isolate knows the base URL.
    await service.setBaseUrl(ref.read(appConfigProvider).apiBaseUrl);
    ref.invalidate(installedDoorWidgetsProvider);

    if (fromConfigure) {
      // Complete the Android configure flow → the widget is added + the activity closes.
      await HomeWidget.finishHomeWidgetConfigure();
      return;
    }
    if (!context.mounted) return;
    AppSnackBar.show(context, l.doorWidgetSelected(device.label));
    navigator.pop();
  }
}
