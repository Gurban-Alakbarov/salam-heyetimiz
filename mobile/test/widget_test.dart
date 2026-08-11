import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/features/auth/presentation/screens/register_screen.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

void main() {
  testWidgets('RegisterScreen surfaces inline validation on empty submit', (
    tester,
  ) async {
    await tester.pumpWidget(
      const ProviderScope(
        child: MaterialApp(
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          locale: Locale('az'),
          home: RegisterScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Qeydiyyatdan keç'));
    await tester.tap(find.text('Qeydiyyatdan keç'));
    await tester.pumpAndSettle();

    // firstName + lastName empty → two "required" errors.
    expect(find.text('Bu sahə tələb olunur'), findsWidgets);
    // phone '+994' is invalid, email is empty.
    expect(
      find.text('Nömrə +994XXXXXXXXX formatında olmalıdır'),
      findsOneWidget,
    );
    expect(find.text('Düzgün email daxil edin'), findsOneWidget);
  });
}
