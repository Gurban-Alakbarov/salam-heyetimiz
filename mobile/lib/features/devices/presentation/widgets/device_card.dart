import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/app_inputs.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/barrier/barrier_providers.dart';
import 'package:salam_mobile/features/barrier/presentation/widgets/barrier_open_button.dart';
import 'package:salam_mobile/features/devices/domain/entity/device_entities.dart';
import 'package:salam_mobile/features/directions/directions_launcher.dart';
import 'package:salam_mobile/features/visitor/presentation/widgets/invite_visitor_sheet.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// Barrier card — B17 visual redesign (reference: the "Cihazlar" mobile mock-up). The device photo fills
/// the card under a dark gradient:
///
///   ┌────────────────────┐
///   │ ● Online         ⋮ │   status pill · menu (Cihaz məlumatı / Ailə üzvləri)
///   │   (device photo)   │
///   │ Name               │
///   │ Dəvət et │ Yol göstər │   visitor invite · directions
///   │ [    Qapını Aç   ] │   the single open pipeline
///   └────────────────────┘
///
/// UI only: every action is the same as before (visitor invite sheet, directions, info sheet, the shared
/// [BarrierActionButton] open flow gated by the server's `can_open`). The card's height follows its
/// content, so the live command status of the active card never overflows. No detail page.
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
    final statusLabel = !hasSignal
        ? l.deviceUnknownStatus
        : (online ? l.deviceOnline : l.deviceOffline);

    return ClipRRect(
      borderRadius: BorderRadius.circular(_radius),
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: _minHeight),
        child: Stack(
          children: [
            Positioned.fill(child: _DeviceImage(imageUrl: device.imageUrl)),
            const Positioned.fill(child: _Scrim()),
            Padding(
              padding: const EdgeInsets.all(_padding),
              // At least the card's minimum height; the name / actions / open group sits at the bottom.
              child: ConstrainedBox(
                constraints: const BoxConstraints(
                  minHeight: _minHeight - 2 * _padding,
                ),
                child: IntrinsicHeight(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // ── Top: status pill · menu ──────────────────────────────────
                      Row(
                        children: [
                          Flexible(
                            child: _StatusPill(
                              label: statusLabel,
                              online: hasSignal && online,
                            ),
                          ),
                          const SizedBox(width: AppSpacing.xs),
                          _MenuButton(device: device),
                        ],
                      ),
                      const SizedBox(height: _photoGap),
                      const Spacer(),

                      // ── Name ─────────────────────────────────────────────────────
                      Text(
                        device.label,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                              shadows: const [
                                Shadow(blurRadius: 6, color: Colors.black54),
                              ],
                            ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: AppSpacing.xs),

                      // ── Visitor invite | directions ──────────────────────────────
                      _ActionRow(device: device),
                      const SizedBox(height: AppSpacing.sm),

                      // ── Open (the active card shows the live status) ─────────────
                      _OpenAction(
                        device: device,
                        isActive: isActive,
                        onOpenPressed: onOpenPressed,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  static const double _radius = 22;
  static const double _minHeight = 290;
  static const double _padding = AppSpacing.sm + 2;

  /// Minimum space between the top row and the name — leaves the photo visible.
  static const double _photoGap = 72;
}

/// Bottom-heavy dark gradient so the white text and actions stay legible on any photo.
class _Scrim extends StatelessWidget {
  const _Scrim();

  @override
  Widget build(BuildContext context) {
    return const DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          stops: [0, 0.35, 1],
          colors: [Color(0x55000000), Color(0x22000000), Color(0xE6101010)],
        ),
      ),
    );
  }
}

/// "● Online" pill (display only — the server gates opening through `can_open`).
class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label, required this.online});

  final String label;
  final bool online;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm + 2,
          vertical: 6,
        ),
        decoration: BoxDecoration(
          color: const Color(0xB3141414),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 9,
              height: 9,
              decoration: BoxDecoration(
                color: online ? AppColors.success : AppColors.n400,
                shape: BoxShape.circle,
              ),
            ),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                  fontSize: 13,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// "⋮" — the card menu: the existing device info sheet and (B17) this device's family screen. Whether the
/// caller heads a family on the device is the server's call; the family screen explains a refusal.
class _MenuButton extends StatelessWidget {
  const _MenuButton({required this.device});

  final Device device;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Material(
      color: const Color(0xB3141414),
      shape: const CircleBorder(),
      child: InkWell(
        key: Key('device-menu-${device.id}'),
        customBorder: const CircleBorder(),
        onTap: () => _showMenu(context, l),
        child: Padding(
          padding: const EdgeInsets.all(6),
          child: Icon(
            Icons.more_vert,
            color: Colors.white,
            size: 20,
            semanticLabel: l.deviceInfoTitle,
          ),
        ),
      ),
    );
  }

  void _showMenu(BuildContext context, AppLocalizations l) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              key: const Key('device-menu-info'),
              leading: const Icon(Icons.info_outline, color: AppColors.brand),
              title: Text(l.deviceInfoTitle),
              onTap: () {
                Navigator.of(sheetContext).pop();
                _showDeviceInfo(context, device, l);
              },
            ),
            ListTile(
              key: const Key('device-menu-family'),
              leading: const Icon(
                Icons.family_restroom,
                color: AppColors.brand,
              ),
              title: Text(l.famTitle),
              onTap: () {
                Navigator.of(sheetContext).pop();
                GoRouter.of(context).push('/family?device=${device.id}');
              },
            ),
            const SizedBox(height: AppSpacing.sm),
          ],
        ),
      ),
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
            Text(
              l.deviceInfoTitle,
              style: Theme.of(sheetContext).textTheme.titleMedium,
            ),
            const SizedBox(height: AppSpacing.md),
            _InfoRow(
              icon: Icons.location_on_outlined,
              iconColor: AppColors.brand,
              label: l.deviceAddress,
              value: address,
            ),
            if (device.serial != null)
              _InfoRow(
                icon: Icons.tag,
                label: l.deviceImei,
                value: device.serial!,
              ),
            _InfoRow(
              icon: Icons.schedule,
              label: l.deviceLastOnlineLabel,
              value: lastOnline,
            ),
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

/// "Dəvət et | Yol göstər" — the existing visitor-invite sheet and directions, as white text actions.
class _ActionRow extends StatelessWidget {
  const _ActionRow({required this.device});

  final Device device;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: _CardAction(
              key: Key('device-invite-${device.id}'),
              icon: Icons.person_add_alt_1_outlined,
              label: l.inviteVisitor,
              onTap: () => InviteVisitorSheet.show(
                context,
                deviceId: device.id,
                barrierLabel: device.label,
              ),
            ),
          ),
          const VerticalDivider(
            width: 1,
            thickness: 1,
            color: Color(0x55FFFFFF),
            indent: 6,
            endIndent: 6,
          ),
          Expanded(
            child: _CardAction(
              key: Key('device-directions-${device.id}'),
              icon: Icons.directions_outlined,
              label: l.directions,
              onTap: () => _directions(context, l),
            ),
          ),
        ],
      ),
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

class _CardAction extends StatelessWidget {
  const _CardAction({
    required this.icon,
    required this.label,
    required this.onTap,
    super.key,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: AppRadius.brMd,
      child: Padding(
        padding: const EdgeInsets.symmetric(
          vertical: AppSpacing.sm,
          horizontal: 2,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: Colors.white, size: 17),
            const SizedBox(width: 3),
            // Scales down instead of truncating ("Yol göstər" must stay readable on a half-width card).
            Flexible(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  label,
                  maxLines: 1,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
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

    // Active card: reuse the shared open button (owns the live command status), on a light panel so the
    // coloured status line stays legible over the photo.
    if (isActive) {
      return Container(
        padding: const EdgeInsets.all(AppSpacing.sm),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.94),
          borderRadius: AppRadius.brMd,
        ),
        child: BarrierActionButton(
          deviceId: device.id,
          canDo: device.canOpen,
          direction: BarrierDirection.open,
          geofenceEnabled: device.geofenceEnabled,
        ),
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
        const SizedBox(height: AppSpacing.xs),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.info_outline, size: 14, color: Colors.white70),
            const SizedBox(width: AppSpacing.xs),
            Flexible(
              child: Text(
                _openBlockedReason(l, device.suspensionReason),
                textAlign: TextAlign.center,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: Colors.white),
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

/// The card background — the barrier photo (cached) or a branded green placeholder while loading / when
/// absent / on error. Fills the card.
class _DeviceImage extends StatelessWidget {
  const _DeviceImage({this.imageUrl});

  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    final url = imageUrl?.trim();
    return SizedBox.expand(
      child: (url == null || url.isEmpty)
          ? const _ImagePlaceholder()
          : CachedNetworkImage(
              imageUrl: url,
              fit: BoxFit.cover,
              fadeInDuration: AppDurations.base,
              placeholder: (context, _) =>
                  const _ImagePlaceholder(loading: true),
              errorWidget: (context, _, _) => const _ImagePlaceholder(),
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
      // No corner watermark: on the full-card layout it would sit behind the open button.
      child: Center(
        child: loading
            ? const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2.2,
                  color: AppColors.onBrand,
                ),
              )
            // Pinned inside the photo gap (below the status row, above the name) so a taller
            // active card never pushes the name onto the icon.
            : const Align(
                alignment: Alignment.topCenter,
                child: Padding(
                  padding: EdgeInsets.only(top: 66),
                  child: Icon(
                    Icons.sensor_door_rounded,
                    size: 40,
                    color: AppColors.onBrand,
                  ),
                ),
              ),
      ),
    );
  }
}
