import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:salam_mobile/core/di/providers.dart';
import 'package:salam_mobile/core/feature_flags/feature_flag_service.dart';
import 'package:salam_mobile/design_system/components/app_bottom_nav.dart';
import 'package:salam_mobile/features/devices/presentation/screens/device_list_screen.dart';
import 'package:salam_mobile/features/home/home_providers.dart';
import 'package:salam_mobile/features/home/presentation/home_screen.dart';
import 'package:salam_mobile/features/notifications/notifications_providers.dart';
import 'package:salam_mobile/features/notifications/presentation/notification_screen.dart';
import 'package:salam_mobile/features/profile/presentation/profile_screen.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// Authenticated shell with the 3-tab bottom navigation (Home · Devices · Profile).
/// All three tabs are live. The notification centre is reached from the app-bar
/// bell; app settings live inside the Profile tab. The selected tab lives in
/// [homeTabProvider] so in-tab widgets (the Home "Active devices" card) can switch
/// tabs without a navigation push.
class HomeShell extends ConsumerWidget {
  const HomeShell({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final index = ref.watch(homeTabProvider);
    final titles = [l.home, l.devices, l.profile];
    const bodies = [HomeScreen(), DeviceListScreen(), ProfileScreen()];

    // Unread badge on the bell — driven by the inbox (loaded once, kept alive). Off when the flag is off.
    final notifEnabled = ref
        .watch(featureFlagServiceProvider)
        .isEnabled(FeatureFlag.notifications);
    final unread = notifEnabled
        ? (ref.watch(notificationInboxProvider).value?.unreadCount ?? 0)
        : 0;

    return Scaffold(
      appBar: AppBar(
        title: Text(titles[index]),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 4),
            child: Badge(
              isLabelVisible: unread > 0,
              label: Text(unread > 99 ? '99+' : '$unread'),
              child: IconButton(
                icon: const Icon(Icons.notifications_none_rounded),
                tooltip: l.notifications,
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const NotificationScreen()),
                ),
              ),
            ),
          ),
        ],
      ),
      body: SafeArea(child: IndexedStack(index: index, children: bodies)),
      bottomNavigationBar: AppBottomNav(
        currentIndex: index,
        onTap: (i) => ref.read(homeTabProvider.notifier).select(i),
        items: [
          AppBottomNavItem(icon: Icons.home_rounded, label: l.home),
          AppBottomNavItem(icon: Icons.sensor_door_rounded, label: l.devices),
          AppBottomNavItem(icon: Icons.person_rounded, label: l.profile),
        ],
      ),
    );
  }
}
