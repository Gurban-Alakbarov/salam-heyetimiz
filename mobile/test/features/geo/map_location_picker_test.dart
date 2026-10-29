import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/applications/domain/application_entities.dart';
import 'package:salam_mobile/features/geo/geo_repository.dart';
import 'package:salam_mobile/features/geo/presentation/map_location_picker.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

class _MockGeo extends Mock implements GeoRepository {}

/// 1.0.1 GoMap picker: searching an address and picking a result places the pin there and fills the address field
/// from the pin; a search outage only shows a note (the pin can still be placed by hand).
void main() {
  late _MockGeo geo;
  GeoPoint? picked;
  String? address;

  setUp(() {
    geo = _MockGeo();
    picked = null;
    address = null;
  });

  Future<void> pump(WidgetTester tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          geoRepositoryProvider.overrideWithValue(geo),
          // no tile network in tests: the map falls back to its default (tiles fail quietly)
          geoTileUrlProvider.overrideWith((ref, lang) async => null),
        ],
        child: MaterialApp(
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          locale: const Locale('az'),
          home: Scaffold(
            body: SingleChildScrollView(
              child: StatefulBuilder(
                builder: (context, setState) => MapLocationPicker(
                  value: picked,
                  onChanged: (p) => setState(() => picked = p),
                  onAddress: (a) => address = a,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  testWidgets('search → pick → pin placed and address filled', (tester) async {
    when(() => geo.search('Nizami', 'az')).thenAnswer((_) async => const Success([
      GeoPlace(name: 'Nizami Street Hotel', address: 'Nizami 180, Nəsimi, Bakı', latitude: 40.3775519, longitude: 49.8562735),
    ]));
    when(() => geo.reverse(40.3775519, 49.8562735, 'az'))
        .thenAnswer((_) async => const Success('Nizami 180, Nəsimi, Bakı, AZ1000'));

    await pump(tester);
    await tester.enterText(find.byKey(const Key('geo-search')), 'Nizami');
    await tester.pump(const Duration(milliseconds: 400)); // debounce
    await tester.pump();
    expect(find.text('Nizami Street Hotel'), findsOneWidget);

    await tester.tap(find.text('Nizami Street Hotel'));
    await tester.pump();
    await tester.pump();

    expect(picked?.latitude, 40.3775519);
    expect(picked?.longitude, 49.8562735);
    expect(address, 'Nizami 180, Nəsimi, Bakı, AZ1000');
    expect(find.byKey(const Key('geo-pin')), findsOneWidget);
  });

  testWidgets('a search outage shows a note instead of results', (tester) async {
    when(() => geo.search('Nizami', 'az')).thenAnswer((_) async => const Err(ServerFailure()));

    await pump(tester);
    await tester.enterText(find.byKey(const Key('geo-search')), 'Nizami');
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump();

    expect(find.textContaining('Axtarış müvəqqəti işləmir'), findsOneWidget);
  });
}
