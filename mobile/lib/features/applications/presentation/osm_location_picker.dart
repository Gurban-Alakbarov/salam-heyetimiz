import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/di/providers.dart';
import '../../../core/location/location_service.dart';
import '../../../design_system/components/app_inputs.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/application_entities.dart';

/// OpenStreetMap pin picker (IMPLEMENTATION_PLAN §21 / B14, BR-8). Tap the map to place the pin, or use
/// "my location" (foreground, When-In-Use — the existing geofence location service). Tiles come from
/// tile.openstreetmap.org under its usage policy: identifying User-Agent (package name) + visible
/// © OpenStreetMap attribution. Coordinates are the only output; nothing is geocoded server-side.
class OsmLocationPicker extends ConsumerStatefulWidget {
  const OsmLocationPicker({required this.value, required this.onChanged, this.errorText, super.key});

  final GeoPoint? value;
  final ValueChanged<GeoPoint> onChanged;
  final String? errorText;

  static const LatLng defaultCenter = LatLng(40.4093, 49.8671); // Baku
  static const String userAgentPackageName = 'com.salamheyetimiz.salam_mobile';

  @override
  ConsumerState<OsmLocationPicker> createState() => _OsmLocationPickerState();
}

class _OsmLocationPickerState extends ConsumerState<OsmLocationPicker> {
  final MapController _map = MapController();
  bool _locating = false;

  Future<void> _useMyLocation() async {
    final l = AppLocalizations.of(context);
    setState(() => _locating = true);
    final result = await ref.read(locationServiceProvider).getCurrent();
    if (!mounted) return;
    setState(() => _locating = false);
    switch (result) {
      case LocationOk(:final fix):
        final p = GeoPoint(fix.latitude, fix.longitude);
        widget.onChanged(p);
        _map.move(LatLng(p.latitude, p.longitude), 16);
      case LocationPermissionPermanentlyDenied():
        AppSnackBar.show(context, l.errLocationPermissionPermanent, isError: true);
      case LocationPermissionDenied():
        AppSnackBar.show(context, l.errLocationPermissionDenied, isError: true);
      case LocationServiceDisabled():
        AppSnackBar.show(context, l.errLocationServiceDisabled, isError: true);
      default:
        AppSnackBar.show(context, l.errLocationTimeout, isError: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final v = widget.value;
    final center = v == null ? OsmLocationPicker.defaultCenter : LatLng(v.latitude, v.longitude);
    final outside = v != null && !v.inServiceArea;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l.appLocationLabel, style: Theme.of(context).textTheme.labelLarge),
        const SizedBox(height: AppSpacing.xs),
        ClipRRect(
          borderRadius: AppRadius.brMd,
          child: SizedBox(
            height: 240,
            child: FlutterMap(
              mapController: _map,
              options: MapOptions(
                initialCenter: center,
                initialZoom: v == null ? 12 : 16,
                onTap: (_, latLng) => widget.onChanged(GeoPoint(latLng.latitude, latLng.longitude)),
              ),
              children: [
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: OsmLocationPicker.userAgentPackageName,
                ),
                if (v != null)
                  MarkerLayer(markers: [
                    Marker(
                      point: LatLng(v.latitude, v.longitude),
                      width: 40,
                      height: 40,
                      alignment: Alignment.topCenter,
                      child: const Icon(Icons.location_on, size: 40, color: AppColors.danger),
                    ),
                  ]),
                const SimpleAttributionWidget(source: Text('OpenStreetMap')), // widget prefixes ©
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.xs),
        Row(
          children: [
            Expanded(
              child: Text(
                v == null ? l.appLocationHint : '${v.latitude.toStringAsFixed(6)}, ${v.longitude.toStringAsFixed(6)}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
            TextButton.icon(
              onPressed: _locating ? null : _useMyLocation,
              icon: _locating
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.my_location, size: 18),
              label: Text(l.appUseMyLocation),
            ),
          ],
        ),
        if (outside || widget.errorText != null)
          Text(
            outside ? l.appLocationOutside : widget.errorText!,
            style: TextStyle(color: Theme.of(context).colorScheme.error, fontSize: 12),
          ),
      ],
    );
  }
}
