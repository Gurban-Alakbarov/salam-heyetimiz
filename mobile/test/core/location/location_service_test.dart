import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mocktail/mocktail.dart';
// Transitive via geolocator; needed only to mock the platform interface in tests.
// ignore: depend_on_referenced_packages
import 'package:plugin_platform_interface/plugin_platform_interface.dart';
import 'package:salam_mobile/core/location/location_service.dart';

class MockGeolocatorPlatform extends Mock
    with MockPlatformInterfaceMixin
    implements GeolocatorPlatform {}

Position _position({double lat = 40.4, double lng = 49.8, double acc = 12}) =>
    Position(
      latitude: lat,
      longitude: lng,
      timestamp: DateTime.fromMillisecondsSinceEpoch(0),
      accuracy: acc,
      altitude: 0,
      altitudeAccuracy: 0,
      heading: 0,
      headingAccuracy: 0,
      speed: 0,
      speedAccuracy: 0,
    );

void main() {
  late MockGeolocatorPlatform geo;
  const service = LocationService();

  setUpAll(() => registerFallbackValue(const LocationSettings()));

  setUp(() {
    geo = MockGeolocatorPlatform();
    GeolocatorPlatform.instance = geo;
    // Happy-path defaults; each test overrides the branch it exercises.
    when(() => geo.isLocationServiceEnabled()).thenAnswer((_) async => true);
    when(() => geo.checkPermission())
        .thenAnswer((_) async => LocationPermission.whileInUse);
    when(() => geo.requestPermission())
        .thenAnswer((_) async => LocationPermission.whileInUse);
    when(
      () => geo.getCurrentPosition(
        locationSettings: any(named: 'locationSettings'),
      ),
    ).thenAnswer((_) async => _position());
  });

  test('permission granted + fix → LocationOk with lat/lng/accuracy', () async {
    final result = await service.getCurrent();
    expect(result, isA<LocationOk>());
    final ok = result as LocationOk;
    expect(ok.fix.latitude, 40.4);
    expect(ok.fix.longitude, 49.8);
    expect(ok.fix.accuracy, 12);
  });

  test('location service disabled → LocationServiceDisabled (no fix call)', () async {
    when(() => geo.isLocationServiceEnabled()).thenAnswer((_) async => false);
    expect(await service.getCurrent(), isA<LocationServiceDisabled>());
    verifyNever(
      () => geo.getCurrentPosition(
        locationSettings: any(named: 'locationSettings'),
      ),
    );
  });

  test('permission denied (and denied on request) → LocationPermissionDenied', () async {
    when(() => geo.checkPermission())
        .thenAnswer((_) async => LocationPermission.denied);
    when(() => geo.requestPermission())
        .thenAnswer((_) async => LocationPermission.denied);
    expect(await service.getCurrent(), isA<LocationPermissionDenied>());
  });

  test('permission permanently denied → LocationPermissionPermanentlyDenied', () async {
    when(() => geo.checkPermission())
        .thenAnswer((_) async => LocationPermission.deniedForever);
    expect(
      await service.getCurrent(),
      isA<LocationPermissionPermanentlyDenied>(),
    );
  });

  test('denied then granted on request → LocationOk', () async {
    when(() => geo.checkPermission())
        .thenAnswer((_) async => LocationPermission.denied);
    when(() => geo.requestPermission())
        .thenAnswer((_) async => LocationPermission.whileInUse);
    expect(await service.getCurrent(), isA<LocationOk>());
  });

  test('fix times out → LocationTimeout', () async {
    when(
      () => geo.getCurrentPosition(
        locationSettings: any(named: 'locationSettings'),
      ),
    ).thenThrow(TimeoutException('timeout'));
    expect(await service.getCurrent(), isA<LocationTimeout>());
  });

  test('platform error → LocationError', () async {
    when(
      () => geo.getCurrentPosition(
        locationSettings: any(named: 'locationSettings'),
      ),
    ).thenThrow(Exception('boom'));
    expect(await service.getCurrent(), isA<LocationError>());
  });
}
