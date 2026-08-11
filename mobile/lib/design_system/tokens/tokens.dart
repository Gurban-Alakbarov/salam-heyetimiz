import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Design tokens — the single source of truth for color, type, spacing, radius
/// and motion. Features MUST reference these, never literals (Constitution §4).
///
/// Palette: Salam green system (parking/barrier inspired). Primary #5FAF1D.
class AppColors {
  AppColors._();

  // Brand — green
  static const Color brand = Color(0xFF5FAF1D); // Primary
  static const Color brandDark = Color(0xFF4A9216); // Dark primary / pressed
  static const Color onBrand = Color(0xFFFFFFFF);
  static const Color brandContainer = Color(0xFFEAF6DD); // soft green tint

  // Semantic
  static const Color success = Color(0xFF69C400);
  static const Color warning = Color(0xFFD97706);
  static const Color danger = Color(0xFFD32F2F); // Error
  static const Color info = Color(0xFF6B7280); // neutral (no blue accent)

  // Neutral ramp
  static const Color n0 = Color(0xFFFFFFFF); // Card
  static const Color n50 = Color(0xFFFAFBFC);
  static const Color n100 = Color(0xFFF4F6F8); // Background
  static const Color n200 = Color(0xFFE5E7EB); // Border
  static const Color n400 = Color(0xFF9CA3AF); // inactive icon
  static const Color n600 = Color(0xFF6B7280); // Secondary text
  static const Color n800 = Color(0xFF374151);
  static const Color n900 = Color(0xFF1E1E1E); // Text

  // Semantic aliases (readable names for features)
  static const Color background = n100;
  static const Color card = n0;
  static const Color textPrimary = n900;
  static const Color textSecondary = n600;
  static const Color border = n200;

  // Light surfaces
  static const Color lightBackground = n100;
  static const Color lightSurface = n0;
  static const Color lightOnSurface = n900;

  // Dark surfaces
  static const Color darkBackground = Color(0xFF0F1115);
  static const Color darkSurface = Color(0xFF181B20);
  static const Color darkOnSurface = Color(0xFFE5E7EB);

  // Very light shadow (used by cards / bottom nav).
  static const Color shadow = Color(0x14000000); // ~8% black
  static const Color shadowSoft = Color(0x0F000000); // ~6% black
}

class AppSpacing {
  AppSpacing._();
  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 24;
  static const double xxl = 32;
}

class AppRadius {
  AppRadius._();
  static const double sm = 8;
  static const double md = 12;
  static const double button = 14;
  static const double card = 18;
  static const double lg = 20;
  static const double pill = 999;

  static const Radius rMd = Radius.circular(md);
  static const BorderRadius brMd = BorderRadius.all(rMd);
  static const BorderRadius brButton = BorderRadius.all(Radius.circular(button));
  static const BorderRadius brCard = BorderRadius.all(Radius.circular(card));
  static const BorderRadius brPill = BorderRadius.all(Radius.circular(pill));
}

/// Soft, very-light elevation shadows composed from tokens.
class AppShadows {
  AppShadows._();

  static const List<BoxShadow> card = [
    BoxShadow(
      color: AppColors.shadow,
      blurRadius: 18,
      offset: Offset(0, 6),
      spreadRadius: -6,
    ),
  ];

  static const List<BoxShadow> nav = [
    BoxShadow(
      color: AppColors.shadow,
      blurRadius: 20,
      offset: Offset(0, -2),
      spreadRadius: -4,
    ),
  ];
}

class AppDurations {
  AppDurations._();
  static const Duration fast = Duration(milliseconds: 120);
  static const Duration base = Duration(milliseconds: 200);
  static const Duration slow = Duration(milliseconds: 320);
}

class AppTypography {
  AppTypography._();

  /// Inter (Google Fonts) — close to SF Pro Display. Registered family name.
  static String get fontFamily => GoogleFonts.inter().fontFamily ?? 'Inter';

  static TextTheme textTheme(Color onSurface) {
    final muted = onSurface.withValues(alpha: 0.7);
    TextStyle t(
      double size,
      FontWeight weight,
      Color color, {
      double spacing = 0,
      double? height,
    }) => GoogleFonts.inter(
      fontSize: size,
      fontWeight: weight,
      color: color,
      letterSpacing: spacing,
      height: height,
    );

    return TextTheme(
      titleLarge: t(22, FontWeight.w700, onSurface, spacing: -0.3),
      titleMedium: t(18, FontWeight.w600, onSurface, spacing: -0.2),
      bodyLarge: t(16, FontWeight.w400, onSurface),
      bodyMedium: t(14, FontWeight.w400, muted),
      labelLarge: t(14, FontWeight.w600, onSurface),
    );
  }
}
