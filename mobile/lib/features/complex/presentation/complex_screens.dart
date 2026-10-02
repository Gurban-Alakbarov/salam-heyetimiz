import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure.dart';
import '../../../design_system/components/app_components.dart';
import '../../../design_system/components/app_inputs.dart';
import '../../../design_system/components/data_components.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../devices/devices_providers.dart';
import '../../home/home_providers.dart';
import '../complex_providers.dart';
import '../domain/complex_entities.dart';
import 'complex_failure.dart';

/// Resident complex screens (IMPLEMENTATION_PLAN §13 / §21 / B16): the caller's complexes → ComplexHome
/// (shared devices with the monthly price and the caller's OWN status) → ComplexDeviceDetail ("Abunə ol"
/// → the B13 hosted checkout). Access opens only once the caller's own subscription is active; the
/// device then appears in the existing "Cihazlar" tab.

String statusLabel(AppLocalizations l, MySubscriptionStatus s) => switch (s) {
  MySubscriptionStatus.none => l.cxStatusNone,
  MySubscriptionStatus.pendingPayment => l.cxStatusPending,
  MySubscriptionStatus.active => l.cxStatusActive,
  MySubscriptionStatus.expired => l.cxStatusExpired,
};

BadgeTone statusTone(MySubscriptionStatus s) => switch (s) {
  MySubscriptionStatus.active => BadgeTone.success,
  MySubscriptionStatus.pendingPayment => BadgeTone.warning,
  MySubscriptionStatus.expired => BadgeTone.danger,
  MySubscriptionStatus.none => BadgeTone.neutral,
};

Widget _errorList(AppLocalizations l, Object e, VoidCallback onRetry) =>
    ListView(
      children: [
        const SizedBox(height: 160),
        ErrorStateView(
          message: e is Failure ? complexFailureMessage(l, e) : l.errUnknown,
          onRetry: onRetry,
        ),
      ],
    );

/// `/complexes` — every complex the caller is an active resident of.
class MyComplexesScreen extends ConsumerWidget {
  const MyComplexesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final async = ref.watch(myComplexesProvider);
    return AppScaffold(
      title: l.cxMyComplexes,
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(myComplexesProvider.future),
        child: async.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) =>
              _errorList(l, e, () => ref.invalidate(myComplexesProvider)),
          data: (list) => list.isEmpty
              ? ListView(
                  children: [
                    const SizedBox(height: 120),
                    EmptyState(
                      message: l.cxNoComplexes,
                      icon: Icons.apartment_outlined,
                    ),
                  ],
                )
              : ListView(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  children: [
                    for (final c in list)
                      Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                        child: AppCard(
                          child: InkWell(
                            key: Key('cx-${c.id}'),
                            onTap: () => context.push('/complex/${c.id}'),
                            child: Row(
                              children: [
                                const Icon(
                                  Icons.apartment_outlined,
                                  color: AppColors.brand,
                                ),
                                const SizedBox(width: AppSpacing.md),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        c.name,
                                        style: Theme.of(
                                          context,
                                        ).textTheme.titleMedium,
                                      ),
                                      if (c.address != null)
                                        Text(
                                          c.address!,
                                          style: Theme.of(
                                            context,
                                          ).textTheme.bodySmall,
                                        ),
                                    ],
                                  ),
                                ),
                                const Icon(Icons.chevron_right),
                              ],
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
        ),
      ),
    );
  }
}

/// `/complex/:id` — ComplexHome: header + the shared devices with the caller's own status.
class ComplexHomeScreen extends ConsumerWidget {
  const ComplexHomeScreen({required this.complexId, super.key});

  final int complexId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final complex = ref.watch(complexProvider(complexId));
    final devices = ref.watch(complexDevicesProvider(complexId));

    Future<void> refresh() {
      ref.invalidate(complexDevicesProvider(complexId));
      return ref.refresh(complexProvider(complexId).future);
    }

    return AppScaffold(
      title: complex.value?.name ?? l.cxTitle,
      body: RefreshIndicator(
        onRefresh: refresh,
        child: complex.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => _errorList(
            l,
            e,
            () => ref.invalidate(complexProvider(complexId)),
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
              Text(
                l.cxDevicesTitle,
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: AppSpacing.xs),
              Text(l.cxPriceNote, style: Theme.of(context).textTheme.bodySmall),
              const SizedBox(height: AppSpacing.sm),
              ...devices.when(
                loading: () => const [AppSkeletonCard()],
                error: (e, _) => [
                  ErrorStateView(
                    message: e is Failure
                        ? complexFailureMessage(l, e)
                        : l.errUnknown,
                    onRetry: () =>
                        ref.invalidate(complexDevicesProvider(complexId)),
                  ),
                ],
                data: (list) => list.isEmpty
                    ? [
                        EmptyState(
                          message: l.cxNoDevices,
                          icon: Icons.meeting_room_outlined,
                        ),
                      ]
                    : [
                        for (final d in list)
                          _DeviceTile(
                            device: d,
                            onTap: () => context.push(
                              '/complex/$complexId/device/${d.id}',
                            ),
                          ),
                      ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DeviceTile extends StatelessWidget {
  const _DeviceTile({required this.device, required this.onTap});

  final ComplexDevice device;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;
    final price = device.subscriptionPriceMinor;
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: AppCard(
        child: InkWell(
          key: Key('cx-device-${device.id}'),
          onTap: onTap,
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      device.label,
                      style: text.titleSmall,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (device.address != null && device.address!.isNotEmpty)
                      Text(device.address!, style: text.bodySmall),
                    if (price != null && device.subscriptionTermDays != null)
                      Text(
                        l.cxPrice(
                          formatMinor(price, device.currency),
                          device.subscriptionTermDays!,
                        ),
                        style: text.bodySmall,
                      ),
                    const SizedBox(height: AppSpacing.xs),
                    StatusBadge(
                      label: statusLabel(l, device.myStatus),
                      tone: statusTone(device.myStatus),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}

/// `/complex/:id/device/:deviceId` — ComplexDeviceDetail: price block + the caller's status + the one
/// action the server allows (subscribe / finish paying / renew; active → open from "Cihazlar").
class ComplexDeviceScreen extends ConsumerStatefulWidget {
  const ComplexDeviceScreen({
    required this.complexId,
    required this.deviceId,
    super.key,
  });

  final int complexId;
  final int deviceId;

  @override
  ConsumerState<ComplexDeviceScreen> createState() =>
      _ComplexDeviceScreenState();
}

class _ComplexDeviceScreenState extends ConsumerState<ComplexDeviceScreen> {
  bool _busy = false;

  Future<void> _subscribe() async {
    final l = AppLocalizations.of(context);
    setState(() => _busy = true);
    final result = await ref
        .read(complexRepositoryProvider)
        .subscribe(widget.complexId, widget.deviceId);
    if (!mounted) return;
    setState(() => _busy = false);
    await result.fold(
      (f) async {
        AppSnackBar.show(context, complexFailureMessage(l, f), isError: true);
        ref.invalidate(complexDevicesProvider(widget.complexId));
      },
      (order) async {
        await context.push('/checkout/${order.id}');
        if (!mounted) return;
        ref
          ..invalidate(complexDevicesProvider(widget.complexId))
          ..invalidate(pendingSubscriptionsProvider)
          ..invalidate(deviceListProvider)
          ..invalidate(homeProvider);
      },
    );
  }

  void _openDevices() {
    ref.read(homeTabProvider.notifier).select(1);
    ref.invalidate(deviceListProvider);
    context.go('/home');
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final async = ref.watch(complexDevicesProvider(widget.complexId));
    final text = Theme.of(context).textTheme;

    return AppScaffold(
      title: l.cxDeviceTitle,
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => _errorList(
          l,
          e,
          () => ref.invalidate(complexDevicesProvider(widget.complexId)),
        ),
        data: (list) {
          final matches = list.where((d) => d.id == widget.deviceId);
          if (matches.isEmpty) {
            return Center(
              child: EmptyState(
                message: l.cxNoDevices,
                icon: Icons.meeting_room_outlined,
              ),
            );
          }
          final d = matches.first;
          final price = d.subscriptionPriceMinor;
          final (label, icon) = switch (d.myStatus) {
            MySubscriptionStatus.none => (l.cxSubscribe, Icons.add_card),
            MySubscriptionStatus.pendingPayment => (
              l.cxFinishPayment,
              Icons.payment,
            ),
            MySubscriptionStatus.expired => (l.cxRenew, Icons.autorenew),
            MySubscriptionStatus.active => (
              l.cxOpenInDevices,
              Icons.meeting_room_outlined,
            ),
          };
          return ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              Text(d.label, style: text.titleLarge),
              if (d.address != null && d.address!.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.xs),
                Text(d.address!, style: text.bodyMedium),
              ],
              const SizedBox(height: AppSpacing.lg),
              AppCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            l.cxSubscriptionBlock,
                            style: text.titleSmall,
                          ),
                        ),
                        StatusBadge(
                          label: statusLabel(l, d.myStatus),
                          tone: statusTone(d.myStatus),
                        ),
                      ],
                    ),
                    if (price != null && d.subscriptionTermDays != null) ...[
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        l.cxPrice(
                          formatMinor(price, d.currency),
                          d.subscriptionTermDays!,
                        ),
                        style: text.titleMedium,
                      ),
                    ],
                    const SizedBox(height: AppSpacing.xs),
                    Text(l.cxPriceNote, style: text.bodySmall),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              AppButton(
                key: const Key('cx-action'),
                label: label,
                icon: icon,
                loading: _busy,
                onPressed: _busy
                    ? null
                    : (d.canStartSubscription ? _subscribe : _openDevices),
              ),
            ],
          );
        },
      ),
    );
  }
}
