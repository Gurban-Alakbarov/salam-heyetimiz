import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure.dart';
import '../../../design_system/components/app_components.dart';
import '../../../design_system/components/app_inputs.dart';
import '../../../design_system/components/data_components.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/session_roles_provider.dart';
import '../domain/komendant_entities.dart';
import '../komendant_providers.dart';
import 'komendant_failure.dart';

/// KomendantHomeScreen (IMPLEMENTATION_PLAN §12 / §21 / B15): the linked complex, its stats, entry points
/// to invite / invitations / residents, and the complex devices (status + monthly subscription price only).
class KomendantHomeScreen extends ConsumerWidget {
  const KomendantHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final complex = ref.watch(komendantComplexProvider);
    // R-12: the link was revoked or the manager suspended → refresh the roles (the Komendant entries
    // disappear) and leave the area; the snackbar lives on the root messenger, so it survives the move.
    ref.listen(komendantComplexProvider, (_, next) {
      final e = next.error;
      if (e is ForbiddenFailure && e.code == 'not_komendant') {
        AppSnackBar.show(context, l.kmErrNotKomendant, isError: true);
        ref.invalidate(sessionRolesProvider);
        context.go('/home');
      }
    });
    final devices = ref.watch(komendantDevicesProvider);

    Future<void> refresh() {
      ref.invalidate(komendantDevicesProvider);
      return ref.refresh(komendantComplexProvider.future);
    }

    return AppScaffold(
      title: l.kmTitle,
      body: RefreshIndicator(
        onRefresh: refresh,
        child: complex.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => ListView(
            children: [
              const SizedBox(height: 160),
              ErrorStateView(
                message: e is Failure
                    ? komendantFailureMessage(l, e)
                    : l.errUnknown,
                onRetry: () => ref.invalidate(komendantComplexProvider),
              ),
            ],
          ),
          data: (c) => ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              Text(c.name, style: Theme.of(context).textTheme.titleLarge),
              if (c.address != null && c.address!.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.xs),
                Text(c.address!, style: Theme.of(context).textTheme.bodyMedium),
              ],
              const SizedBox(height: AppSpacing.lg),
              IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Expanded(
                      child: _Stat(
                        key: const Key('km-stat-devices'),
                        count: c.devices,
                        label: l.kmStatDevices,
                        icon: Icons.meeting_room_outlined,
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: _Stat(
                        key: const Key('km-stat-residents'),
                        count: c.residents,
                        label: l.kmStatResidents,
                        icon: Icons.people_outline,
                        onTap: () => context.push('/komendant/residents'),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: _Stat(
                        key: const Key('km-stat-pending'),
                        count: c.pendingInvitations,
                        label: l.kmStatPending,
                        icon: Icons.mark_email_unread_outlined,
                        onTap: () => context.push('/komendant/invitations'),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              AppButton(
                key: const Key('km-invite'),
                label: l.kmInvite,
                icon: Icons.person_add_alt_1_outlined,
                onPressed: () => context.push('/komendant/invite'),
              ),
              const SizedBox(height: AppSpacing.sm),
              AppSecondaryButton(
                label: l.kmInvitations,
                icon: Icons.mail_outline,
                onPressed: () => context.push('/komendant/invitations'),
              ),
              const SizedBox(height: AppSpacing.sm),
              AppSecondaryButton(
                label: l.kmResidentsTitle,
                icon: Icons.people_outline,
                onPressed: () => context.push('/komendant/residents'),
              ),
              const SizedBox(height: AppSpacing.xl),
              Text(
                l.kmDevicesTitle,
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: AppSpacing.sm),
              ...devices.when(
                loading: () => const [AppSkeletonCard()],
                error: (e, _) => [
                  ErrorStateView(
                    message: e is Failure
                        ? komendantFailureMessage(l, e)
                        : l.errUnknown,
                    onRetry: () => ref.invalidate(komendantDevicesProvider),
                  ),
                ],
                data: (list) => list.isEmpty
                    ? [
                        EmptyState(
                          message: l.kmNoDevices,
                          icon: Icons.meeting_room_outlined,
                        ),
                      ]
                    : [for (final d in list) _DeviceTile(device: d)],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({
    required this.count,
    required this.label,
    required this.icon,
    this.onTap,
    super.key,
  });

  final int count;
  final String label;
  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: AppRadius.brCard,
      child: AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: AppColors.brand),
            const SizedBox(height: AppSpacing.sm),
            Text('$count', style: Theme.of(context).textTheme.titleLarge),
            Text(
              label,
              style: Theme.of(context).textTheme.bodySmall,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}

class _DeviceTile extends StatelessWidget {
  const _DeviceTile({required this.device});

  final KomendantDevice device;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final price = device.subscriptionPriceMinor;
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    device.label,
                    style: Theme.of(context).textTheme.titleSmall,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                StatusBadge(
                  label: device.online ? l.kmOnline : l.kmOffline,
                  tone: device.online ? BadgeTone.success : BadgeTone.neutral,
                ),
              ],
            ),
            if (device.address != null && device.address!.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(
                device.address!,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
            if (price != null && device.subscriptionTermDays != null) ...[
              const SizedBox(height: AppSpacing.xs),
              Text(
                l.kmDevicePrice(
                  '${(price / 100).toStringAsFixed(2)} ${device.currency ?? 'AZN'}',
                  device.subscriptionTermDays!,
                ),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ],
        ),
      ),
    );
  }
}
