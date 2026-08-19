import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/app_inputs.dart';
import 'package:salam_mobile/design_system/components/data_components.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/barrier/barrier_providers.dart';
import 'package:salam_mobile/features/barrier/presentation/widgets/barrier_open_button.dart';
import 'package:salam_mobile/features/devices/domain/entity/device_entities.dart';
import 'package:salam_mobile/features/directions/directions_launcher.dart';
import 'package:salam_mobile/features/visitor/presentation/widgets/invite_visitor_sheet.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// A compact, self-contained barrier card:
///
///   ┌──────────────────────────────────────┐
///   │ Name                     ⓘ  ● Online  │   header (name + info + status)
///   │ ┌──────────────┐  ┌────────────────┐  │
///   │ │ device image │  │  Invite        │  │   50% image · 50% actions
///   │ │   (~50%)     │  │  Directions    │  │
///   │ └──────────────┘  └────────────────┘  │
///   │ [        Open barrier (100%)       ]  │   full-width primary
///   └──────────────────────────────────────┘
///
/// Address / IMEI / last-online moved off the card into the ⓘ info sheet. Online/
/// offline stays display-only (the server gates `canOpen`). The open action reuses
/// the single [BarrierActionButton] pipeline; the live status renders only on the
/// card the user is operating ([isActive]) so the one global barrier provider never
/// lights up every card at once. No detail page, no navigation.
class DeviceCard extends StatelessWidget {
  const DeviceCard({
    required this.device,
    this.isActive = false,
    this.onOpenPressed,
    super.key,
  });

  final Device device;

  /// Whether this is the card the user is currently operating. Only the active
  /// card watches the (single, global) barrier command state.
  final bool isActive;
  final ValueChanged<int>? onOpenPressed;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final hasSignal = device.lastOnlineAt != null;
    final online = device.isOnlineAt(DateTime.now());

    final tone = !hasSignal
        ? BadgeTone.neutral
        : (online ? BadgeTone.success : BadgeTone.neutral);
    final statusLabel = !hasSignal
        ? l.deviceUnknownStatus
        : (online ? l.deviceOnline : l.deviceOffline);

    return AppCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // ── Header: name (flexes) · info button · status badge ────────────
          Row(
            children: [
              Expanded(
                child: Text(
                  device.label,
                  style: Theme.of(context).textTheme.titleMedium,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              _InfoButton(device: device),
              const SizedBox(width: AppSpacing.xs),
              StatusBadge(label: statusLabel, tone: tone),
            ],
          ),
          const SizedBox(height: AppSpacing.md),

          // ── Middle: 50% image · 50% stacked actions ───────────────────────
          SizedBox(
            height: _mediaHeight,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(flex: 5, child: _DeviceImage(imageUrl: device.imageUrl)),
                const SizedBox(width: AppSpacing.md),
                Expanded(flex: 5, child: _ActionColumn(device: device)),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.md),

          // ── Bottom: full-width open (active card shows the live status) ────
          _OpenAction(
            device: device,
            isActive: isActive,
            onOpenPressed: onOpenPressed,
          ),
        ],
      ),
    );
  }

  /// Height of the image / action row — keeps the two stacked action buttons a
  /// comfortable tap size while staying compact on small screens.
  static const double _mediaHeight = 116;
}

/// Circular "ⓘ" button in the header — opens a compact sheet with the details that
/// used to clutter the card (address, IMEI, last-online). Reads existing device
/// data only; no new API.
class _InfoButton extends StatelessWidget {
  const _InfoButton({required this.device});

  final Device device;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return IconButton(
      onPressed: () => _showDeviceInfo(context, device, l),
      icon: const Icon(Icons.info_outline, color: AppColors.brand),
      iconSize: 24,
      visualDensity: VisualDensity.compact,
      padding: EdgeInsets.zero,
      constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
      tooltip: l.deviceInfoTitle,
    );
  }
}

void _showDeviceInfo(BuildContext context, Device device, AppLocalizations l) {
  final address = (device.address != null && device.address!.trim().isNotEmpty)
      ? device.address!
      : l.deviceAddressMissing;
  final lastOnline = device.lastOnlineAt != null
      ? DateFormat('dd.MM.yyyy HH:mm').format(device.lastOnlineAt!.toLocal())
      : '—';

  showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    builder: (sheetContext) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.xl,
          AppSpacing.xs,
          AppSpacing.xl,
          AppSpacing.xl,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l.deviceInfoTitle, style: Theme.of(sheetContext).textTheme.titleMedium),
            const SizedBox(height: AppSpacing.md),
            _InfoRow(
              icon: Icons.location_on_outlined,
              iconColor: AppColors.brand,
              label: l.deviceAddress,
              value: address,
            ),
            if (device.serial != null)
              _InfoRow(icon: Icons.tag, label: l.deviceImei, value: device.serial!),
            _InfoRow(icon: Icons.schedule, label: l.deviceLastOnlineLabel, value: lastOnline),
          ],
        ),
      ),
    ),
  );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
    this.iconColor = AppColors.textSecondary,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color iconColor;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: iconColor),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 2),
                SelectableText(
                  value,
                  style: Theme.of(context).textTheme.bodyLarge,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// The 40% column: "Invite Visitor" over "Directions" (both permanent, independent
/// actions). Buttons flex to share the media height, so they never overflow.
class _ActionColumn extends StatelessWidget {
  const _ActionColumn({required this.device});

  final Device device;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: AppSecondaryButton(
            label: l.inviteVisitor,
            icon: Icons.person_add_alt_1_outlined,
            onPressed: () => InviteVisitorSheet.show(
              context,
              deviceId: device.id,
              barrierLabel: device.label,
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.sm),
        Expanded(
          child: AppSecondaryButton(
            label: l.directions,
            icon: Icons.directions_outlined,
            onPressed: () => _directions(context, l),
          ),
        ),
      ],
    );
  }

  void _directions(BuildContext context, AppLocalizations l) {
    if (!device.hasLocation) {
      AppSnackBar.show(context, l.directionsNoLocation, isError: true);
      return;
    }
    DirectionsLauncher.open(
      context,
      lat: device.latitude!,
      lng: device.longitude!,
      label: device.label,
    );
  }
}

/// The full-width open action — the ONLY barrier command surface in the app.
///
/// Idle (or another card is active): a single "Qapını Aç". Tapping it asks the
/// parent to mark this card active and dispatch the open, so only ONE card ever
/// watches the single global barrier state. The active card then renders the shared
/// [BarrierActionButton] (live sending → pending → success). There is NO close
/// button — the relay is a pulse, so after a successful open the list screen briefly
/// shows the success message then auto-returns the card to idle.
class _OpenAction extends ConsumerWidget {
  const _OpenAction({
    required this.device,
    required this.isActive,
    required this.onOpenPressed,
  });

  final Device device;
  final bool isActive;
  final ValueChanged<int>? onOpenPressed;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);

    // Active card: reuse the shared open button (owns the live command status).
    if (isActive) {
      return BarrierActionButton(
        deviceId: device.id,
        canDo: device.canOpen,
        direction: BarrierDirection.open,
        geofenceEnabled: device.geofenceEnabled,
      );
    }

    // Not active → a plain button; tapping it asks the parent to activate + fire the
    // open (keeps a single card bound to the global state).
    final button = AppButton(
      label: l.barrierOpen,
      icon: Icons.lock_open_rounded,
      onPressed: device.canOpen ? () => onOpenPressed?.call(device.id) : null,
    );
    // The server gates opening via `can_open`. When it's false the button is disabled
    // (greyed) — surface WHY instead of a silent dead button.
    if (device.canOpen) return button;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        button,
        const SizedBox(height: AppSpacing.sm),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.info_outline, size: 15, color: AppColors.textSecondary),
            const SizedBox(width: AppSpacing.xs),
            Flexible(
              child: Text(
                _openBlockedReason(l, device.suspensionReason),
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

/// Why the server disabled opening (`can_open=false`), mapped from the device's
/// `suspension_reason` to a user-facing message. Falls back to a generic
/// access-denied line for unknown/`none` reasons.
String _openBlockedReason(AppLocalizations l, String reason) {
  if (reason.contains('whitelist')) return l.errWhitelist;
  if (reason.contains('subscription') || reason.contains('expired')) {
    return l.errSubscriptionRequired;
  }
  return l.errAccessDenied;
}

/// The 60% media tile — the barrier photo (cached, rounded) or a branded green
/// placeholder while loading / when absent / on error. Fills its parent height.
class _DeviceImage extends StatelessWidget {
  const _DeviceImage({this.imageUrl});

  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    final url = imageUrl?.trim();
    return ClipRRect(
      borderRadius: AppRadius.brMd,
      child: SizedBox.expand(
        child: (url == null || url.isEmpty)
            ? const _ImagePlaceholder()
            : CachedNetworkImage(
                imageUrl: url,
                fit: BoxFit.cover,
                fadeInDuration: AppDurations.base,
                placeholder: (context, _) => const _ImagePlaceholder(loading: true),
                errorWidget: (context, _, _) => const _ImagePlaceholder(),
              ),
      ),
    );
  }
}

/// Branded green placeholder shown when a barrier has no photo, while its photo
/// loads, or if the photo fails to load. Fills its parent (no fixed size).
class _ImagePlaceholder extends StatelessWidget {
  const _ImagePlaceholder({this.loading = false});

  final bool loading;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.brand, AppColors.brandDark],
        ),
      ),
      child: Stack(
        fit: StackFit.expand,
        children: [
          Positioned(
            right: -10,
            bottom: -14,
            child: Icon(
              Icons.sensor_door_rounded,
              size: 96,
              color: AppColors.onBrand.withValues(alpha: 0.14),
            ),
          ),
          Center(
            child: loading
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.2,
                      color: AppColors.onBrand,
                    ),
                  )
                : const Icon(
                    Icons.sensor_door_rounded,
                    size: 36,
                    color: AppColors.onBrand,
                  ),
          ),
        ],
      ),
    );
  }
}
