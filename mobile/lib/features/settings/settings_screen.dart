import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:salam_mobile/core/di/providers.dart';
import 'package:salam_mobile/design_system/components/app_list.dart';
import 'package:salam_mobile/design_system/components/app_inputs.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/door_widget/door_widget_providers.dart';
import 'package:salam_mobile/features/notifications/data/notification_settings_datasource.dart';
import 'package:salam_mobile/features/notifications/notifications_providers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// App settings — reached from Profile › App settings. Live theme-mode +
/// language switching (the canonical localization switch — fully reused; do not
/// duplicate it elsewhere). Grouped-list presentation shared with Profile
/// ([AppListSection]/[AppListTile]). Logout lives on the Profile screen.
class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final themeMode = ref.watch(themeModeProvider);
    final locale = ref.watch(localeProvider);
    final currentLang = locale?.languageCode ?? 'system';

    return Scaffold(
      appBar: AppBar(
        title: Text(l.settings),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          // Pushed from Profile → pop back to it; fall back to /home if this was
          // reached as a top-level route with nothing to pop.
          onPressed: () {
            final nav = Navigator.of(context);
            if (nav.canPop()) {
              nav.pop();
            } else {
              context.go('/home');
            }
          },
        ),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          children: [
            // ── Appearance ────────────────────────────────────────────────
            AppListSection(
              children: [
                AppListTile(
                  icon: Icons.dark_mode_outlined,
                  title: l.theme,
                  subtitle: themeMode.name,
                  trailing: Switch(
                    value: themeMode == ThemeMode.dark,
                    onChanged: (v) => ref
                        .read(themeModeProvider.notifier)
                        .set(v ? ThemeMode.dark : ThemeMode.light),
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.lg),

            // ── Language (icon-less rows → tighter divider indent) ─────────
            AppListSection(
              header: l.language,
              dividerIndent: AppSpacing.lg,
              children: [
                _LangTile(code: 'az', label: 'Azərbaycan', current: currentLang),
                _LangTile(code: 'en', label: 'English', current: currentLang),
                _LangTile(code: 'ru', label: 'Русский', current: currentLang),
                _LangTile(code: 'system', label: 'System', current: currentLang),
              ],
            ),
            const SizedBox(height: AppSpacing.lg),

            // ── Notifications (O13) ──────────────────────────────────────────
            const _NotificationSwitches(),
            const SizedBox(height: AppSpacing.lg),

            // ── Privacy (1.0.1): usage statistics; crash reports always stay on ──
            AppListSection(
              header: l.privacyHeader,
              children: [
                AppListTile(
                  icon: Icons.insights_outlined,
                  title: l.analyticsSwitch,
                  trailing: Switch(
                    key: const Key('settings-analytics'),
                    value: ref.watch(analyticsEnabledProvider),
                    onChanged: (v) => ref.read(analyticsEnabledProvider.notifier).set(v),
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(AppSpacing.lg, AppSpacing.sm, AppSpacing.lg, 0),
              child: Text(l.analyticsHint, style: Theme.of(context).textTheme.bodySmall),
            ),
          ],
        ),
      ),
    );
  }
}

class _LangTile extends ConsumerWidget {
  const _LangTile({
    required this.code,
    required this.label,
    required this.current,
  });

  final String code;
  final String label;
  final String current;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final selected = code == current;
    return AppListTile(
      title: label,
      trailing: selected
          ? const Icon(Icons.check, color: AppColors.brand)
          : null,
      onTap: () async {
        final locale = code == 'system' ? null : Locale(code);
        await ref.read(localeProvider.notifier).set(locale);
        // Mirror the choice to the home-screen widget (non-secret locale code) and
        // refresh all instances so they re-render in the new language (W5 D2/K).
        await ref
            .read(doorWidgetServiceProvider)
            .setLocale(locale?.languageCode ?? 'az');
      },
    );
  }
}

/// O13 — the notifications a user may keep off the phone (visitor opens, announcements). Off = no push; the
/// inbox still lists them. Hidden while loading / when the server lists none; a failed save is rolled back.
class _NotificationSwitches extends ConsumerWidget {
  const _NotificationSwitches();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final settings = ref.watch(notificationSettingsProvider).value ?? const <NotificationSetting>[];
    if (settings.isEmpty) return const SizedBox.shrink();

    String labelFor(String key) => switch (key) {
      'device.opened' => l.notifSettingVisitorOpened,
      'system.admin_campaign' => l.notifSettingAnnouncements,
      _ => key,
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AppListSection(
          header: l.notifSettingsHeader,
          children: [
            for (final s in settings)
              AppListTile(
                icon: s.templateKey == 'device.opened'
                    ? Icons.door_front_door_outlined
                    : Icons.campaign_outlined,
                title: labelFor(s.templateKey),
                trailing: Switch(
                  value: s.pushEnabled,
                  onChanged: (v) async {
                    final ok = await ref
                        .read(notificationSettingsProvider.notifier)
                        .setPush(s.templateKey, v);
                    if (!ok && context.mounted) {
                      AppSnackBar.show(context, l.notifSettingsError, isError: true);
                    }
                  },
                ),
              ),
          ],
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(AppSpacing.lg, AppSpacing.sm, AppSpacing.lg, 0),
          child: Text(l.notifSettingsHint, style: Theme.of(context).textTheme.bodySmall),
        ),
      ],
    );
  }
}
