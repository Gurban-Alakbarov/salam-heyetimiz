import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import '../../core/error/failure.dart';
import '../../core/network/api_client.dart';
import '../../core/network/envelope.dart';

/// A place / address found by the map search (GoMap.az through our server).
class GeoPlace {
  const GeoPlace({required this.name, required this.address, required this.latitude, required this.longitude});

  factory GeoPlace.fromJson(Map<String, dynamic> j) => GeoPlace(
    name: (j['name'] as String?) ?? '',
    address: (j['address'] as String?) ?? '',
    latitude: (j['lat'] as num).toDouble(),
    longitude: (j['lng'] as num).toDouble(),
  );

  final String name;
  final String address;
  final double latitude;
  final double longitude;
}

/// Map picker backend (GoMap.az, 1.0.1): /v1/geo/search, /v1/geo/reverse, /v1/geo/tiles. The GoMap key stays on the
/// server; the tile URL comes from it too, so it never ships inside the app.
class GeoRepository {
  GeoRepository(this._api);

  final ApiClient _api;

  Future<Result<T>> _guard<T>(Future<T> Function() run) async {
    try {
      return Success(await run());
    } on DioException catch (e) {
      return Err(mapDioError(e));
    } catch (_) {
      return const Err(UnknownFailure());
    }
  }

  Future<Result<List<GeoPlace>>> search(String query, String lang) => _guard(() async {
    final body = (await _api.get('/v1/geo/search', query: {'q': query, 'lang': lang})).data;
    final rows = body is Map && body['data'] is List ? body['data'] as List : const [];
    return [for (final r in rows) if (r is Map) GeoPlace.fromJson(Map<String, dynamic>.from(r))];
  });

  /// The address under the pin (with house number when known), or null.
  Future<Result<String?>> reverse(double lat, double lng, String lang) => _guard(() async {
    final body = (await _api.get('/v1/geo/reverse', query: {'lat': lat, 'lng': lng, 'lang': lang})).data;
    final data = body is Map && body['data'] is Map ? body['data'] as Map : const {};
    final address = data['address'];
    return address is String && address.trim().isNotEmpty ? address.trim() : null;
  });

  /// GoMap XYZ tile URL, or null when the server has no GoMap key (the picker then uses OpenStreetMap).
  Future<Result<String?>> tileUrl(String lang) => _guard(() async {
    final body = (await _api.get('/v1/geo/tiles', query: {'lang': lang})).data;
    final url = body is Map && body['data'] is Map ? (body['data'] as Map)['url'] : null;
    return url is String && url.isNotEmpty ? url : null;
  });
}

final geoRepositoryProvider = Provider<GeoRepository>((ref) => GeoRepository(ref.watch(apiClientProvider)));

/// Tile URL per language (fetched once; null → OpenStreetMap fallback).
final geoTileUrlProvider = FutureProvider.family<String?, String>((ref, lang) async {
  final r = await ref.watch(geoRepositoryProvider).tileUrl(lang);
  return r.fold((_) => null, (url) => url);
});
