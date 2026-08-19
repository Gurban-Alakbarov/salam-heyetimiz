import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/app_list.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/door_widget/door_widget_providers.dart';
import 'package:salam_mobile/features/door_widget/presentation/door_widget_picker_screen.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// Profile › Home-screen widgets (W5). Lists the user's installed door-widget
/// instances, each showing the barrier it currently opens (or "not configured"),
/// and lets them reconfigure any one. Adding a NEW widget goes through the Android
/// configure flow (from the launcher's widget picker); this screen manages the
/// widgets that already exist.
class DoorWidgetManagementScreen extends ConsumerWidget {
  const DoorWidgetManagementScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final widgetsAsync = ref.watch(installedDoorWidgetsProvider);

    return AppScaffold(
      title: l.doorWidgetTitle,
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(installedDoorWidgetsProvider.future),
        child: widgetsAsync.when(
          loading: () => const AppLoading(),
          error: (_, _) => ErrorStateView(
            message: l.errUnknown,
            onRetry: () => ref.invalidate(installedDoorWidgetsProvider),
          ),
          data: (widgets) {
            if (widgets.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  const SizedBox(height: 140),
                  EmptyState(
                    message: l.doorWidgetAddHint,
                    icon: Icons.widgets_outlined,
                  ),
                ],
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
                    for (final w in widgets)
                      AppListTile(
                        icon: Icons.sensor_door_outlined,
                        // Primary = the door name (never the technical id); the widget
                        // id is a small secondary detail.
                        title: w.config?.deviceLabel ?? l.doorWidgetUnconfigured,
                        subtitle: l.doorWidgetInstance(w.widgetId),
                        chevron: true,
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) =>
                                DoorWidgetPickerScreen(widgetId: w.widgetId),
                          ),
                        ),
                      ),
                  ],
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
