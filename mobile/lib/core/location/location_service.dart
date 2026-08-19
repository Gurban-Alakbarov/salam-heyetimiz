import 'dart:async';

import 'package:geolocator/geolocator.dart';

/// An accepted GPS fix (GEOFENCE-3). Threaded into the `/open` request body once,
/// then discarded — never persisted (GEOFENCE-1 privacy: no storage, no history).
class GeoFix {
  const GeoFix({
    required this.latitude,
    required this.longitude,
    required this.accuracy,
  });

  final double latitude;
  final double longitude;

  /// Horizontal accuracy in metres (device-reported ±). The backend applies its
  /// own accuracy margin and HOLDS the radius — the client never decides distance.
  final double accuracy;
}

/// Outcome of a foreground location acquisition. Sealed so every branch
/// (permission / service / timeout / error) is handled explicitly by the caller.
sealed class LocationResult {
  const LocationResult();
}

class LocationOk extends LocationResult {
  const LocationOk(this.fix);
  final GeoFix fix;
}

class LocationPermissionDenied extends LocationResult {
  const LocationPermissionDenied();
}

class LocationPermissionPermanentlyDenied extends LocationResult {
  const LocationPermissionPermanentlyDenied();
}

class LocationServiceDisabled extends LocationResult {
  const LocationServiceDisabled();
}

class LocationTimeout extends LocationResult {
  const LocationTimeout();
}

class LocationError extends LocationResult {
  const LocationError();
}

/// Foreground-only location acquisition for the geofence open gate (GEOFENCE-3, D2/D3).
///
/// Acquires exactly ONE fresh high-accuracy fix on demand — no streaming, no
/// background, and `getLastKnownPosition` is intentionally NOT used (stale fixes are
/// rejected). The geolocator platform interface is called directly so tests can
/// substitute `GeolocatorPlatform.instance`.
class LocationService {
  const LocationService();

  GeolocatorPlatform get _geo => GeolocatorPlatform.instance;

  /// A single high-accuracy fix with an 8-second ceiling. Returns a sealed
  /// [LocationResult]; never throws.
  Future<LocationResult> getCurrent({
    Duration timeout = const Duration(seconds: 8),
  }) async {
    try {
      if (!await _geo.isLocationServiceEnabled()) {
        return const LocationServiceDisabled();
      }

      var permission = await _geo.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await _geo.requestPermission();
      }
      if (permission == LocationPermission.deniedForever) {
        return const LocationPermissionPermanentlyDenied();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.unableToDetermine) {
        return const LocationPermissionDenied();
      }

      final position = await _geo.getCurrentPosition(
        locationSettings: LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: timeout,
        ),
      );
      return LocationOk(
        GeoFix(
          latitude: position.latitude,
          longitude: position.longitude,
          accuracy: position.accuracy,
        ),
      );
    } on TimeoutException {
      return const LocationTimeout();
    } catch (_) {
      return const LocationError();
    }
  }

  /// Deep-link to OS app settings so a permanently-denied user can re-grant (D4).
  Future<bool> openSettings() => _geo.openAppSettings();
}
