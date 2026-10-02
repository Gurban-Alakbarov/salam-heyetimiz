import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:salam_mobile/features/applications/presentation/register_type_screen.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

GoRouter _router(String initial) => GoRouter(
  initialLocation: initial,
  routes: [
    GoRoute(
      path: '/welcome',
      builder: (context, _) => Scaffold(
        body: TextButton(
          onPressed: () => context.push('/auth/register/type'),
          child: const Text('welcome'),
        ),
      ),
    ),
    GoRoute(
      path: '/auth/register/type',
      builder: (_, _) => const RegisterTypeScreen(),
    ),
    GoRoute(
      path: '/auth/register',
      builder: (_, _) => const Scaffold(body: Text('register-form')),
    ),
  ],
);

Future<void> _pump(WidgetTester tester, GoRouter router) async {
  await tester.pumpWidget(
    ProviderScope(
      child: MaterialApp.router(
        routerConfig: router,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        locale: const Locale('az'),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

/// Simulates the Android system Back button; returns whether the app handled it (false = app would close).
Future<bool> _systemBack(WidgetTester tester) async {
  final handled = await tester.binding.handlePopRoute();
  await tester.pumpAndSettle();
  return handled;
}

void main() {
  testWidgets(
    'opened from Welcome: Back returns to Welcome (not out of the app)',
    (tester) async {
      final router = _router('/welcome');
      await _pump(tester, router);
      await tester.tap(find.text('welcome'));
      await tester.pumpAndSettle();
      expect(find.byType(RegisterTypeScreen), findsOneWidget);
      expect(
        find.byType(BackButton),
        findsOneWidget,
      ); // app-bar back arrow is offered

      expect(await _systemBack(tester), isTrue);
      expect(find.text('welcome'), findsOneWidget);
    },
  );

  testWidgets('type → form → Back returns to the type choice', (tester) async {
    final router = _router('/welcome');
    await _pump(tester, router);
    await tester.tap(find.text('welcome'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('reg-type-legal')));
    await tester.pumpAndSettle();
    expect(find.text('register-form'), findsOneWidget);

    expect(await _systemBack(tester), isTrue);
    expect(find.byType(RegisterTypeScreen), findsOneWidget);
  });

  testWidgets(
    'entry point with nothing underneath: Back lands on Welcome instead of closing',
    (tester) async {
      final router = _router('/auth/register/type');
      await _pump(tester, router);
      expect(find.byType(RegisterTypeScreen), findsOneWidget);

      expect(await _systemBack(tester), isTrue);
      expect(find.text('welcome'), findsOneWidget);
    },
  );
}
