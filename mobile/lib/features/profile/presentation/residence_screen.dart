import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/devices/devices_providers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// Residential complex — the resident's barriers and their addresses, i.e. where
/// they live. There is no dedicated "complex" field in GET /v1/me, so rather than
/// fabricate one this screen reuses the existing [deviceListProvider] data (each
/// barrier's label + address) as the authoritative residence information.
class ResidenceScreen extends ConsumerWidget {
  const ResidenceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final devicesAsync = ref.watch(deviceListProvider);

    return AppScaffold(
      title: l.profileResidence,
      body: devicesAsync.when(
        loading: () => const AppLoading(),
        error: (_, _) => ErrorStateView(
          message: l.errUnknown,
          onRetry: () => ref.invalidate(deviceListProvider),
        ),
        data: (page) {
          final devices = page.devices;
          if (devices.isEmpty) {
            return EmptyState(
              message: l.residenceEmpty,
              icon: Icons.apartment_outlined,
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(AppSpacing.lg),
            itemCount: devices.length,
            separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
            itemBuilder: (context, i) {
              final d = devices[i];
              final address = (d.address?.isNotEmpty ?? false)
                  ? d.address!
                  : l.deviceAddressMissing;
              return Container(
                padding: const EdgeInsets.all(AppSpacing.lg),
                decoration: BoxDecoration(
                  color: Theme.of(context).cardColor,
                  borderRadius: AppRadius.brMd,
                  border: Border.all(color: AppColors.border),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      decoration: const BoxDecoration(
                        color: AppColors.brandContainer,
                        borderRadius: AppRadius.brMd,
                      ),
                      child: const Icon(
                        Icons.sensor_door_outlined,
                        color: AppColors.brand,
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            d.label,
                            style: Theme.of(context).textTheme.bodyLarge,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            address,
                            style: Theme.of(context).textTheme.bodyMedium,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }
}
