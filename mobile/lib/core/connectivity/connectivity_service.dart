import 'package:connectivity_plus/connectivity_plus.dart';

/// Online/offline signal (Constitution §8, OFFLINE_STRATEGY.md §6). Drives the
/// offline gating + sync triggers.
class ConnectivityService {
  ConnectivityService([Connectivity? connectivity])
    : _connectivity = connectivity ?? Connectivity();

  final Connectivity _connectivity;

  Stream<bool> get onlineChanges =>
      _connectivity.onConnectivityChanged.map(_isOnline);

  Future<bool> get isOnline async =>
      _isOnline(await _connectivity.checkConnectivity());

  bool _isOnline(List<ConnectivityResult> results) =>
      results.any((r) => r != ConnectivityResult.none);
}
