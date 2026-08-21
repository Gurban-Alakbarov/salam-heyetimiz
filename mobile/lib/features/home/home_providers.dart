import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import '../devices/devices_providers.dart';
import 'data/home_repository_impl.dart';
import 'domain/home_data.dart';

final homeRepositoryProvider = Provider<HomeRepository>(
  (ref) => HomeRepositoryImpl(
    ref.watch(apiClientProvider),
    ref.watch(deviceRepositoryProvider),
  ),
);

/// Home dashboard data. Pull-to-refresh via `ref.refresh(homeProvider.future)`.
final homeProvider = FutureProvider.autoDispose<HomeData>((ref) async {
  final result = await ref.watch(homeRepositoryProvider).load();
  return result.fold((failure) => throw failure, (data) => data);
});

/// The selected [HomeShell] bottom-nav tab (0=Home · 1=Devices · 2=Profile).
/// Held in a provider so in-tab widgets (e.g. the Home "Active devices" card) can
/// switch tabs without pushing a screen.
class HomeTabController extends Notifier<int> {
  /// Default landing tab = Devices (1). Authenticated users open the shell on their
  /// barriers; the Home + Profile tabs stay one tap away on the bottom nav. This only
  /// sets the initial tab — navigation/auth flow, logout, and deep-links are untouched.
  @override
  int build() => 1;

  void select(int index) => state = index;
}

final homeTabProvider = NotifierProvider<HomeTabController, int>(
  HomeTabController.new,
);
