import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/features/directions/directions_launcher.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// When AzNav is not available, "Yol göstər" must present the friendly
/// "AzNav required" install prompt — never a dead-end error. On the (non-Android)
/// test host `Platform.isAndroid` is false, so [DirectionsLauncher.open] takes the
/// not-available branch deterministically, without touching platform channels.
void main() {
  Widget harness(Locale locale) => MaterialApp(
    locale: locale,
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: AppLocalizations.supportedLocales,
    home: Scaffold(
      body: Builder(
        builder: (context) => ElevatedButton(
          onPressed: () =>
              DirectionsLauncher.open(context, lat: 40.4093, lng: 49.8671),
          child: const Text('go'),
        ),
      ),
    ),
  );

  testWidgets('AzNav unavailable → localized "AzNav required" dialog (AZ)', (
    tester,
  ) async {
    await tester.pumpWidget(harness(const Locale('az')));

    await tester.tap(find.text('go'));
    await tester.pumpAndSettle();

    expect(find.text('AzNav tələb olunur'), findsOneWidget);
    expect(find.text('AzNav-ı yüklə'), findsOneWidget);
    expect(find.text('İmtina'), findsOneWidget);
  });

  testWidgets('dialog dismisses on cancel without launching', (tester) async {
    await tester.pumpWidget(harness(const Locale('az')));

    await tester.tap(find.text('go'));
    await tester.pumpAndSettle();
    expect(find.text('AzNav tələb olunur'), findsOneWidget);

    await tester.tap(find.text('İmtina'));
    await tester.pumpAndSettle();
    expect(find.text('AzNav tələb olunur'), findsNothing);
  });

  testWidgets('English locale renders the English prompt', (tester) async {
    await tester.pumpWidget(harness(const Locale('en')));

    await tester.tap(find.text('go'));
    await tester.pumpAndSettle();

    expect(find.text('AzNav required'), findsOneWidget);
    expect(find.text('Install AzNav'), findsOneWidget);
    expect(find.text('Cancel'), findsOneWidget);
  });
}
