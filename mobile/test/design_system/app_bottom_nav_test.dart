import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/design_system/components/app_bottom_nav.dart';
import 'package:salam_mobile/design_system/theme/app_theme.dart';

/// The capsule bottom nav must render without a "BOTTOM OVERFLOWED" RenderFlex
/// overflow on any device size, text-scale factor, or safe-area inset. Its height
/// is intrinsic (content-driven) — these cases lock that in.
void main() {
  const items = [
    AppBottomNavItem(icon: Icons.home_rounded, label: 'Ana səhifə'),
    AppBottomNavItem(icon: Icons.sensor_door_rounded, label: 'Cihazlar'),
    AppBottomNavItem(icon: Icons.receipt_long_rounded, label: 'Sıralar'),
    AppBottomNavItem(icon: Icons.person_rounded, label: 'Profil'),
  ];

  Widget harness(double textScale, EdgeInsets safeArea) => MaterialApp(
    theme: AppTheme.light,
    builder: (context, child) => MediaQuery(
      data: MediaQuery.of(context).copyWith(
        textScaler: TextScaler.linear(textScale),
        padding: safeArea,
        viewPadding: safeArea,
      ),
      child: child!,
    ),
    home: Scaffold(
      body: const SizedBox.expand(),
      bottomNavigationBar: AppBottomNav(
        items: items,
        currentIndex: 0,
        onTap: (_) {},
      ),
    ),
  );

  final cases = <String, (Size, double, EdgeInsets)>{
    'small Android (320×600)': (const Size(320, 600), 1.0, EdgeInsets.zero),
    'large Android (480×1000)': (const Size(480, 1000), 1.0, EdgeInsets.zero),
    'iPhone + home indicator (390×844)': (
      const Size(390, 844),
      1.0,
      const EdgeInsets.only(bottom: 34),
    ),
    'large text scale 1.3': (const Size(360, 720), 1.3, EdgeInsets.zero),
    'extreme text scale 2.0 (clamped)': (
      const Size(360, 720),
      2.0,
      EdgeInsets.zero,
    ),
    'very narrow + 1.2 scale (280×560)': (
      const Size(280, 560),
      1.2,
      EdgeInsets.zero,
    ),
  };

  cases.forEach((name, cfg) {
    testWidgets('no BOTTOM OVERFLOW — $name', (tester) async {
      final (size, scale, safeArea) = cfg;
      tester.view.devicePixelRatio = 1.0;
      tester.view.physicalSize = size;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(harness(scale, safeArea));
      await tester.pumpAndSettle();

      expect(
        tester.takeException(),
        isNull,
        reason: 'bottom nav overflowed for: $name',
      );
      // All destinations still render.
      expect(find.text('Cihazlar'), findsOneWidget);
      expect(find.text('Profil'), findsOneWidget);
    });
  });

  testWidgets('selecting a tab reports its index', (tester) async {
    var tapped = -1;
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          bottomNavigationBar: AppBottomNav(
            items: items,
            currentIndex: 0,
            onTap: (i) => tapped = i,
          ),
        ),
      ),
    );

    await tester.tap(find.text('Profil'));
    expect(tapped, 3);
  });
}
