import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/data_components.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/devices/devices_providers.dart';
import 'package:salam_mobile/features/devices/domain/entity/device_entities.dart';
import 'package:salam_mobile/features/devices/presentation/widgets/device_card.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/shared/failure_message.dart';

/// GEOFENCE-4 — the screen the home-screen widget lands on when a geofenced open
/// answered `location_required`. It shows ONLY the barrier the widget is bound to,
/// with the standard open button; the user presses it themselves (manual continue,
/// D4) and the GEOFENCE-3 foreground GPS flow takes over from there.
///
/// [deviceId] is navigation context ONLY. The device is looked up in the user's own
/// list (`deviceListProvider`, server-canonical); a device that is no longer theirs
/// shows a safe notice and NO open action — the widget can never force-open a barrier
/// the server would not authorize.
class WidgetOpenScreen extends ConsumerWidget {
  const WidgetOpenScreen({required this.deviceId, super.key});

  final int deviceId;

  Device? _lookup(List<Device> devices) {
    for (final device in devices) {
      if (device.id == deviceId) return device;
    }
    return null;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final devicesAsync = ref.watch(deviceListProvider);

    // Title from the resolved device when we have it, else the generic open label.
    final known = _lookup(devicesAsync.value?.devices ?? const []);

    return Scaffold(
      appBar: AppBar(title: Text(known?.label ?? l.barrierOpen)),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(deviceListProvider.future),
        child: devicesAsync.when(
          loading: () => ListView(
            padding: const EdgeInsets.all(AppSpacing.md),
            children: const [AppSkeletonCard()],
          ),
          error: (error, _) => ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              const SizedBox(height: 120),
              ErrorStateView(
                message: error is Failure
                    ? deviceFailureMessage(l, error)
                    : l.errUnknown,
                onRetry: () => ref.refresh(deviceListProvider),
              ),
            ],
          ),
          data: (page) {
            final device = _lookup(page.devices);
            // Not in the user's roster (removed / never theirs) → safe notice, no open.
            if (device == null) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  const SizedBox(height: 120),
                  EmptyState(
                    message: l.errAccessDenied,
                    icon: Icons.lock_outline,
                  ),
                ],
              );
            }
            // Reuse the standard card in its "operating" state so the open button +
            // live status (+ GEOFENCE-3 GPS gate via geofenceEnabled) are identical
            // to the device list. The user taps Open — nothing auto-fires (D4).
            return ListView(
              padding: const EdgeInsets.all(AppSpacing.md),
              children: [DeviceCard(device: device, isActive: true)],
            );
          },
        ),
      ),
    );
  }
}
