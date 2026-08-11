import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:salam_mobile/core/di/providers.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/data_components.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/barrier/barrier_providers.dart';
import 'package:salam_mobile/features/devices/devices_providers.dart';
import 'package:salam_mobile/features/devices/presentation/widgets/device_card.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/shared/failure_message.dart';

/// "My Devices" tab body (the HomeShell provides the app bar). Pull-to-refresh,
/// skeleton loading, empty + error states.
class DeviceListScreen extends ConsumerStatefulWidget {
  const DeviceListScreen({super.key});

  @override
  ConsumerState<DeviceListScreen> createState() => _DeviceListScreenState();
}

class _DeviceListScreenState extends ConsumerState<DeviceListScreen> {
  /// The device the user is currently operating — only this card shows the live
  /// command status + the "Bağla" button (the barrier provider is a single
  /// global, so we scope the UI to one card here).
  int? _activeDeviceId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => ref.read(analyticsProvider).logEvent(DeviceEvents.listOpened),
    );
  }

  void _open(int deviceId) {
    setState(() => _activeDeviceId = deviceId);
    ref.read(barrierOpenProvider.notifier).open(deviceId);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // Keep the (single, autoDispose) open-command state alive for the screen's
    // lifetime. _open dispatches via ref.read, and the active card starts
    // watching only a frame later — without this listener the provider could be
    // disposed + recreated in that gap, dropping the in-flight command.
    //
    // The relay is a pulse (RELAY,1# → ~1.5s → RELAY,0#) — there is no manual close.
    // After a successful open we let the "Qapı açıldı" message show briefly, then
    // reset the operated card back to its normal idle state.
    ref.listen<BarrierOpenState>(barrierOpenProvider, (_, next) {
      if (next is! BarrierSuccess) return;
      Future.delayed(const Duration(seconds: 3), () {
        if (!mounted) return;
        if (ref.read(barrierOpenProvider) is! BarrierSuccess) return; // a newer command started
        ref.read(barrierOpenProvider.notifier).reset();
        setState(() => _activeDeviceId = null);
      });
    });
    final devicesAsync = ref.watch(deviceListProvider);

    return RefreshIndicator(
      onRefresh: () => ref.refresh(deviceListProvider.future),
      child: devicesAsync.when(
        loading: () => ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: List.generate(
            4,
            (_) => const Padding(
              padding: EdgeInsets.symmetric(vertical: AppSpacing.xs),
              child: AppSkeletonCard(),
            ),
          ),
        ),
        error: (error, _) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            const SizedBox(height: 160),
            ErrorStateView(
              message: error is Failure
                  ? deviceFailureMessage(l, error)
                  : l.errUnknown,
              onRetry: () => ref.refresh(deviceListProvider),
            ),
          ],
        ),
        data: (page) {
          if (page.devices.isEmpty) {
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                const SizedBox(height: 160),
                EmptyState(
                  message: l.devicesEmpty,
                  icon: Icons.meeting_room_outlined,
                ),
              ],
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.all(AppSpacing.md),
            itemCount: page.devices.length,
            itemBuilder: (context, index) {
              final device = page.devices[index];
              return AppAppear(
                child: DeviceCard(
                  device: device,
                  isActive: _activeDeviceId == device.id,
                  onOpenPressed: _open,
                ),
              );
            },
          );
        },
      ),
    );
  }
}
