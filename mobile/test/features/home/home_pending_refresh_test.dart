import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/core/session/session_roles.dart';
import 'package:salam_mobile/features/auth/session_roles_provider.dart';
import 'package:salam_mobile/features/complex/complex_providers.dart';
import 'package:salam_mobile/features/complex/domain/complex_entities.dart';
import 'package:salam_mobile/features/home/domain/home_data.dart';
import 'package:salam_mobile/features/home/home_providers.dart';
import 'package:salam_mobile/features/home/presentation/home_screen.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// B18 regression: a subscription that starts waiting for payment elsewhere (the head grants the member a
/// device) appears on Home after pull-to-refresh — the pending-payments card has its own provider.
void main() {
  testWidgets('pull-to-refresh also reloads the pending payments card', (
    tester,
  ) async {
    var pending = <PendingSubscription>[];
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          homeProvider.overrideWith(
            (ref) async =>
                const HomeData(fullName: 'Nigar Üzv', deviceCount: 1),
          ),
          pendingSubscriptionsProvider.overrideWith((ref) async => pending),
          sessionRolesProvider.overrideWith((ref) async => SessionRoles.guest),
          hasPendingInviteProvider.overrideWith((ref) async => false),
        ],
        child: const MaterialApp(
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: AppLocalizations.supportedLocales,
          locale: Locale('az'),
          home: Scaffold(body: HomeScreen()),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('home-pending-payments')), findsNothing);

    pending = [
      const PendingSubscription(
        id: 6,
        tier: 'additional',
        deviceId: 2,
        priceMinor: 1200,
      ),
    ];
    await tester.fling(find.byType(ListView).first, const Offset(0, 400), 1000);
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('home-pending-payments')), findsOneWidget);
  });
}
