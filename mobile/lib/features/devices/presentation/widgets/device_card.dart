import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:salam_mobile/core/di/providers.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/components/app_inputs.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/barrier/barrier_providers.dart';
import 'package:salam_mobile/features/barrier/presentation/widgets/barrier_open_button.dart';
import 'package:salam_mobile/features/complex/complex_providers.dart';
import 'package:salam_mobile/features/complex/presentation/complex_failure.dart';
import 'package:salam_mobile/features/devices/device_access_action.dart';
import 'package:salam_mobile/features/devices/devices_providers.dart';
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
///   │  (invite) (route)  │   visitor invite · directions (icon-only pills)
///   │       (power)      │   round open button — the single open pipeline
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

                      // ── B19: why it can't open — the card's central message, above the name ──
                      if (!device.canOpen) ...[
                        _InactiveNotice(
                          key: Key('device-inactive-${device.id}'),
                          reason: device.suspensionReason,
                        ),
                        const SizedBox(height: AppSpacing.sm),
                      ],

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

/// Visitor invite · directions as icon-only green pills (the label is the tooltip / screen-reader name).
/// A4 — the invite pill only when the server says this caller may create visitor links here: a family
/// member may open but never share access (BR-19), and nobody can share access they do not hold right now.
class _ActionRow extends ConsumerWidget {
  const _ActionRow({required this.device});

  final Device device;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final directions = _CardAction(
      key: Key('device-directions-${device.id}'),
      icon: Icons.directions_outlined,
      label: l.directions,
      onTap: () => _directions(context, ref, l),
    );
    if (!device.canShareVisitorLink) {
      return Center(
        child: FractionallySizedBox(widthFactor: 0.5, child: directions),
      );
    }
    return Row(
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
        const SizedBox(width: AppSpacing.sm),
        Expanded(child: directions),
      ],
    );
  }

  void _directions(BuildContext context, WidgetRef ref, AppLocalizations l) {
    if (!device.hasLocation) {
      AppSnackBar.show(context, l.directionsNoLocation, isError: true);
      return;
    }
    ref.read(analyticsProvider).logEvent(DeviceEvents.directionsOpened);
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
    return Tooltip(
      message: label,
      child: Material(
        color: AppColors.brand.withValues(alpha: 0.72),
        shape: StadiumBorder(
          side: BorderSide(color: AppColors.success.withValues(alpha: 0.9)),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 7),
            child: Icon(
              icon,
              color: Colors.white,
              size: 22,
              semanticLabel: label,
            ),
          ),
        ),
      ),
    );
  }
}

/// The round open action — the ONLY barrier command surface in the app.
///
/// Idle (or another card is active): the green power button. Tapping it asks the
/// parent to mark this card active and dispatch the open, so only ONE card ever
/// watches the single global barrier state. The active card then renders the shared
/// [BarrierActionButton] in its round style (red while sending / pending, then the result). There is NO close
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

    // Active card: the shared open button (owns the live command status) in its round style; the status
    // line under it is light text on the card's dark scrim.
    if (isActive) {
      return BarrierActionButton(
        deviceId: device.id,
        canDo: device.canOpen,
        direction: BarrierDirection.open,
        geofenceEnabled: device.geofenceEnabled,
        round: true,
      );
    }

    // Not active → the green power button; tapping it asks the parent to activate + fire the
    // open (keeps a single card bound to the global state).
    if (device.canOpen) {
      return Center(
        child: BarrierPowerButton(
          key: Key('device-open-${device.id}'),
          semanticLabel: l.barrierOpen,
          onPressed: () => onOpenPressed?.call(device.id),
        ),
      );
    }
    // B19: the server gates opening via `can_open`; the WHY is shown above the name (_InactiveNotice) and
    // the button becomes the subscription action the caller can actually take.
    return _AccessAction(device: device);
  }
}

/// B19 — the action for a card that cannot open: finish the pending payment, renew the expired
/// subscription, or subscribe to a complex device. Falls back to the grey power button when there is
/// nothing to pay for (device disabled, someone else's access, still resolving).
class _AccessAction extends ConsumerStatefulWidget {
  const _AccessAction({required this.device});

  final Device device;

  @override
  ConsumerState<_AccessAction> createState() => _AccessActionState();
}

class _AccessActionState extends ConsumerState<_AccessAction> {
  bool _busy = false;

  Future<void> _start(DeviceAccessAction action) async {
    final l = AppLocalizations.of(context);
    final deviceId = widget.device.id;
    setState(() => _busy = true);
    final result = await startDeviceAccessPayment(ref, deviceId, action);
    if (!mounted) return;
    setState(() => _busy = false);
    await result.fold(
      (f) async {
        AppSnackBar.show(
          context,
          f is ForbiddenFailure
              ? l.payErrForbidden
              : complexFailureMessage(l, f),
          isError: true,
        );
        ref.invalidate(deviceAccessActionProvider(deviceId));
      },
      (order) async {
        // The result screen refreshes the device list on success (B18); a cancelled checkout keeps the
        // subscription pending, so re-resolve the card's action either way.
        await GoRouter.of(context).push('/checkout/${order.id}');
        if (!mounted) return;
        ref
          ..invalidate(deviceAccessActionProvider(deviceId))
          ..invalidate(pendingSubscriptionsProvider)
          ..invalidate(deviceListProvider);
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final action =
        ref.watch(deviceAccessActionProvider(widget.device.id)).value ??
        const NoDeviceAccessAction();
    final (String label, IconData icon)? spec = switch (action) {
      PayPendingAction() => (l.cxFinishPayment, Icons.payment),
      RenewAction() => (l.devRenewSubscription, Icons.autorenew),
      SubscribeAction() => (l.cxSubscribe, Icons.add_card),
      NoDeviceAccessAction() => null,
    };
    if (spec == null) {
      return Center(
        child: BarrierPowerButton(
          key: Key('device-open-${widget.device.id}'),
          semanticLabel: l.barrierOpen,
          onPressed: null,
        ),
      );
    }
    return _CardButton(
      key: Key('device-access-${widget.device.id}'),
      label: spec.$1,
      icon: spec.$2,
      loading: _busy,
      onPressed: _busy ? null : () => _start(action),
    );
  }
}

/// The card's full-width button: the app's filled button style, but the icon + label scale down instead of
/// overflowing a half-width card on narrow phones / large accessibility text (B19).
class _CardButton extends StatelessWidget {
  const _CardButton({
    required this.label,
    required this.icon,
    required this.onPressed,
    this.loading = false,
    super.key,
  });

  final String label;
  final IconData icon;
  final VoidCallback? onPressed;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return FilledButton(
      onPressed: loading ? null : onPressed,
      style: FilledButton.styleFrom(
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      ),
      child: loading
          ? const SizedBox(
              height: 20,
              width: 20,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: AppColors.onBrand,
              ),
            )
          : FittedBox(
              fit: BoxFit.scaleDown,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(icon, size: 20),
                  const SizedBox(width: AppSpacing.sm),
                  Text(label, maxLines: 1),
                ],
              ),
            ),
    );
  }
}

/// B19 — "Abunəliyiniz aktiv deyil" (or the device's own reason) as the card's central message, in the
/// photo area right above the name. Wraps to two lines and never leaves the card.
class _InactiveNotice extends StatelessWidget {
  const _InactiveNotice({required this.reason, super.key});

  final String reason;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Align(
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm + 2,
          vertical: 6,
        ),
        decoration: BoxDecoration(
          color: const Color(0xCC141414),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.lock_outline, size: 15, color: Colors.white),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                _openBlockedReason(l, reason),
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                  fontSize: 12.5,
                ),
              ),
            ),
          ],
        ),
      ),
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
