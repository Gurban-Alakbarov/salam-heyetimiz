import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import '../../core/error/failure.dart';
import '../../core/error/retry_policy.dart';
import '../devices/devices_providers.dart';
import 'data/family_repository.dart';
import 'domain/family_entities.dart';

/// Family head DI (B17). Read providers throw the typed [Failure] so screens render it via `when(error:)`;
/// definitive refusals are not retried (retryTransientFailures).
final familyRepositoryProvider = Provider<FamilyRepository>(
  (ref) => FamilyRepository(ref.watch(apiClientProvider)),
);

/// The caller's active family members (relation layer) with granted devices + each member's own
/// subscription on them (access + billing layers).
final familyMembersProvider = FutureProvider.autoDispose<List<FamilyMember>>((
  ref,
) async {
  final result = await ref.watch(familyRepositoryProvider).members();
  return result.fold((f) => throw f, (v) => v);
}, retry: retryTransientFailures);

/// The caller's devices on which the server lets them run a family, each with its invitations. Probed per
/// device through the existing invitations endpoint (403 / 404 → not a head there).
final managedDevicesProvider = FutureProvider.autoDispose<List<ManagedDevice>>((
  ref,
) async {
  final page = await ref.watch(deviceListProvider.future);
  final repo = ref.watch(familyRepositoryProvider);
  final results = await Future.wait(
    page.devices.map((d) async => (d, await repo.deviceInvitations(d.id))),
  );
  final managed = <ManagedDevice>[];
  for (final (device, result) in results) {
    final invitations = result.fold<List<FamilyInvitation>?>(
      (f) => throw f,
      (v) => v,
    );
    if (invitations != null) {
      managed.add(
        ManagedDevice(
          deviceId: device.id,
          label: device.label,
          invitations: invitations,
        ),
      );
    }
  }
  return managed;
}, retry: retryTransientFailures);
