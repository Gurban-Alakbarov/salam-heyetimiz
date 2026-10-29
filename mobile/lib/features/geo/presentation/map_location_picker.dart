import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/di/providers.dart';
import '../../../core/location/location_service.dart';
import '../../../design_system/components/app_inputs.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../applications/domain/application_entities.dart';
import '../geo_repository.dart';

/// Location picker on GoMap.az (1.0.1, replaces the OpenStreetMap picker): search an address → pick → the pin lands
/// there; drag the pin or tap the map to move it; "my location" uses GPS (When-In-Use, the existing location
/// service). Every time the pin stops, the address under it (with house number) is looked up and handed to
/// [onAddress] so the form can fill its address field — the user may still edit it. Tiles + search go through our
/// server (/v1/geo/*); without a GoMap key the map falls back to OpenStreetMap and search simply shows nothing.
class MapLocationPicker extends ConsumerStatefulWidget {
  const MapLocationPicker({
    required this.value,
    required this.onChanged,
    this.onAddress,
    this.errorText,
    super.key,
  });

  final GeoPoint? value;
  final ValueChanged<GeoPoint> onChanged;

  /// The address found under the pin (null when GoMap does not know one).
  final ValueChanged<String>? onAddress;
  final String? errorText;

  static const LatLng defaultCenter = LatLng(40.4093, 49.8671); // Baku
  static const String userAgentPackageName = 'az.salam.sezam';
  static const String osmTiles = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

  @override
  ConsumerState<MapLocationPicker> createState() => _MapLocationPickerState();
}

class _MapLocationPickerState extends ConsumerState<MapLocationPicker> {
  final MapController _map = MapController();
  final TextEditingController _search = TextEditingController();
  Timer? _debounce;
  List<GeoPlace> _results = const [];
  String? _searchNote;
  bool _searching = false;
  bool _locating = false;

  /// The pin while it is being dragged (the parent only hears about it when the drag ends).
  LatLng? _dragging;

  String get _lang => ref.read(effectiveLocaleProvider).languageCode;

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _onQuery(String text) {
    _debounce?.cancel();
    final q = text.trim();
    if (q.length < 2) {
      setState(() { _results = const []; _searchNote = null; });
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 350), () async {
      setState(() => _searching = true);
      final l = AppLocalizations.of(context);
      final r = await ref.read(geoRepositoryProvider).search(q, _lang);
      if (!mounted || _search.text.trim() != q) return;
      setState(() {
        _searching = false;
        r.fold(
          (_) { _results = const []; _searchNote = l.geoSearchUnavailable; },
          (rows) { _results = rows.take(6).toList(); _searchNote = rows.isEmpty ? l.geoNoResults : null; },
        );
      });
    });
  }

  void _pick(GeoPlace p) {
    FocusScope.of(context).unfocus();
    _search.clear();
    setState(() { _results = const []; _searchNote = null; });
    _map.move(LatLng(p.latitude, p.longitude), 17);
    _place(LatLng(p.latitude, p.longitude));
  }

  /// The pin stopped at [at]: report it and look up the address under it.
  Future<void> _place(LatLng at) async {
    widget.onChanged(GeoPoint(at.latitude, at.longitude));
    if (widget.onAddress == null) return;
    final r = await ref.read(geoRepositoryProvider).reverse(at.latitude, at.longitude, _lang);
    if (!mounted) return;
    r.fold((_) {}, (address) { if (address != null) widget.onAddress!(address); });
  }

  Future<void> _useMyLocation() async {
    final l = AppLocalizations.of(context);
    setState(() => _locating = true);
    final result = await ref.read(locationServiceProvider).getCurrent();
    if (!mounted) return;
    setState(() => _locating = false);
    switch (result) {
      case LocationOk(:final fix):
        final p = LatLng(fix.latitude, fix.longitude);
        _map.move(p, 17);
        await _place(p);
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
    final pin = _dragging ?? (v == null ? null : LatLng(v.latitude, v.longitude));
    final center = pin ?? MapLocationPicker.defaultCenter;
    final outside = v != null && !v.inServiceArea;
    final lang = ref.watch(effectiveLocaleProvider).languageCode;
    final gomapUrl = ref.watch(geoTileUrlProvider(lang)).value;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l.appLocationLabel, style: Theme.of(context).textTheme.labelLarge),
        const SizedBox(height: AppSpacing.xs),
        TextField(
          key: const Key('geo-search'),
          controller: _search,
          onChanged: _onQuery,
          textInputAction: TextInputAction.search,
          onSubmitted: (_) { if (_results.isNotEmpty) _pick(_results.first); },
          decoration: InputDecoration(
            hintText: l.geoSearchHint,
            prefixIcon: const Icon(Icons.search),
            suffixIcon: _searching
                ? const Padding(padding: EdgeInsets.all(12), child: SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)))
                : null,
            isDense: true,
            border: OutlineInputBorder(borderRadius: AppRadius.brMd),
          ),
        ),
        if (_results.isNotEmpty)
          Card(
            margin: const EdgeInsets.only(top: AppSpacing.xs),
            child: Column(
              children: [
                for (final p in _results)
                  ListTile(
                    dense: true,
                    leading: const Icon(Icons.place_outlined),
                    title: Text(p.name, maxLines: 1, overflow: TextOverflow.ellipsis),
                    subtitle: p.address.isNotEmpty && p.address != p.name
                        ? Text(p.address, maxLines: 1, overflow: TextOverflow.ellipsis)
                        : null,
                    onTap: () => _pick(p),
                  ),
              ],
            ),
          ),
        if (_searchNote != null)
          Padding(
            padding: const EdgeInsets.only(top: AppSpacing.xs),
            child: Text(_searchNote!, style: Theme.of(context).textTheme.bodySmall),
          ),
        const SizedBox(height: AppSpacing.sm),
        ClipRRect(
          borderRadius: AppRadius.brMd,
          child: SizedBox(
            height: 280,
            child: FlutterMap(
              mapController: _map,
              options: MapOptions(
                initialCenter: center,
                initialZoom: pin == null ? 12 : 16,
                onTap: (_, latLng) => _place(latLng),
              ),
              children: [
                TileLayer(
                  urlTemplate: gomapUrl ?? MapLocationPicker.osmTiles,
                  userAgentPackageName: MapLocationPicker.userAgentPackageName,
                  maxZoom: 18,
                ),
                if (pin != null)
                  MarkerLayer(markers: [
                    Marker(
                      point: pin,
                      width: 44,
                      height: 44,
                      alignment: Alignment.topCenter,
                      child: GestureDetector(
                        key: const Key('geo-pin'),
                        // drag the pin: move it with the finger, report it when the finger lifts
                        onPanUpdate: (d) {
                          final camera = _map.camera;
                          final from = camera.latLngToScreenOffset(_dragging ?? pin);
                          setState(() => _dragging = camera.screenOffsetToLatLng(from + d.delta));
                        },
                        onPanEnd: (_) {
                          final end = _dragging;
                          setState(() => _dragging = null);
                          if (end != null) _place(end);
                        },
                        child: const Icon(Icons.location_on, size: 44, color: AppColors.danger),
                      ),
                    ),
                  ]),
                SimpleAttributionWidget(source: Text(gomapUrl != null ? 'GoMap.az' : 'OpenStreetMap')), // widget prefixes ©
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.xs),
        Row(
          children: [
            Expanded(
              child: Text(
                v == null ? l.appLocationHint : '${v.latitude.toStringAsFixed(6)}, ${v.longitude.toStringAsFixed(6)} · ${l.geoPinHint}',
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
