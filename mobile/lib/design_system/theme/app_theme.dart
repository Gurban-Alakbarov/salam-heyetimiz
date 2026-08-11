import 'package:flutter/cupertino.dart' show CupertinoPageTransitionsBuilder;
import 'package:flutter/material.dart';

import '../tokens/tokens.dart';

/// Light + dark Material 3 themes, derived ENTIRELY from design tokens
/// (Constitution §4.2/§4.3). No screen builds its own ThemeData.
class AppTheme {
  AppTheme._();

  static ThemeData get light => _build(Brightness.light);
  static ThemeData get dark => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final isDark = brightness == Brightness.dark;
    final background = isDark
        ? AppColors.darkBackground
        : AppColors.lightBackground;
    final surface = isDark ? AppColors.darkSurface : AppColors.lightSurface;
    final onSurface = isDark
        ? AppColors.darkOnSurface
        : AppColors.lightOnSurface;
    final border = isDark ? AppColors.n800 : AppColors.border;
    final fieldFill = isDark ? AppColors.darkBackground : AppColors.n50;

    final scheme = ColorScheme(
      brightness: brightness,
      primary: AppColors.brand,
      onPrimary: AppColors.onBrand,
      primaryContainer: AppColors.brandContainer,
      onPrimaryContainer: AppColors.brandDark,
      secondary: AppColors.success,
      onSecondary: AppColors.onBrand,
      error: AppColors.danger,
      onError: AppColors.n0,
      surface: surface,
      onSurface: onSurface,
      outline: border,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: background,
      fontFamily: AppTypography.fontFamily,
      textTheme: AppTypography.textTheme(onSurface),
      splashFactory: InkRipple.splashFactory,

      // Minimal, flat app bar: surface background, bold on-surface title,
      // green accent icons, no elevation.
      appBarTheme: AppBarTheme(
        backgroundColor: surface,
        foregroundColor: onSurface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        shadowColor: AppColors.shadow,
        centerTitle: false,
        titleTextStyle: AppTypography.textTheme(
          onSurface,
        ).titleLarge?.copyWith(fontSize: 20),
        iconTheme: IconThemeData(color: onSurface),
        actionsIconTheme: const IconThemeData(color: AppColors.brand),
      ),

      // Primary buttons: full-width friendly (height 54), radius 14, bold text,
      // pressed state darkens via the M3 state layer + explicit pressed overlay.
      filledButtonTheme: FilledButtonThemeData(
        style: ButtonStyle(
          minimumSize: const WidgetStatePropertyAll(Size.fromHeight(54)),
          // Disabled buttons must READ as disabled — a green "enabled-looking"
          // button that does nothing (e.g. when the server gates `can_open`) is
          // indistinguishable from a broken one. Grey it out instead.
          backgroundColor: WidgetStateProperty.resolveWith(
            (states) => states.contains(WidgetState.disabled)
                ? AppColors.n200
                : AppColors.brand,
          ),
          foregroundColor: WidgetStateProperty.resolveWith(
            (states) => states.contains(WidgetState.disabled)
                ? AppColors.n400
                : AppColors.onBrand,
          ),
          elevation: const WidgetStatePropertyAll(0),
          shape: const WidgetStatePropertyAll(
            RoundedRectangleBorder(borderRadius: AppRadius.brButton),
          ),
          textStyle: WidgetStatePropertyAll(
            AppTypography.textTheme(onSurface).labelLarge?.copyWith(
              fontSize: 16,
              fontWeight: FontWeight.w700,
            ),
          ),
          overlayColor: WidgetStateProperty.resolveWith((states) {
            if (states.contains(WidgetState.pressed)) {
              return AppColors.brandDark.withValues(alpha: 0.9);
            }
            return null;
          }),
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(52),
          foregroundColor: AppColors.brand,
          side: const BorderSide(color: AppColors.brand),
          shape: const RoundedRectangleBorder(
            borderRadius: AppRadius.brButton,
          ),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
        ),
      ),

      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.brand,
          textStyle: const TextStyle(fontWeight: FontWeight.w600),
        ),
      ),

      cardTheme: CardThemeData(
        color: surface,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        shape: const RoundedRectangleBorder(borderRadius: AppRadius.brCard),
      ),

      // Premium rounded inputs: soft fill, rounded 14, green focus ring.
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: fieldFill,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg,
          vertical: AppSpacing.lg,
        ),
        border: OutlineInputBorder(
          borderRadius: AppRadius.brButton,
          borderSide: BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: AppRadius.brButton,
          borderSide: BorderSide(color: border),
        ),
        focusedBorder: const OutlineInputBorder(
          borderRadius: AppRadius.brButton,
          borderSide: BorderSide(color: AppColors.brand, width: 1.6),
        ),
        errorBorder: const OutlineInputBorder(
          borderRadius: AppRadius.brButton,
          borderSide: BorderSide(color: AppColors.danger),
        ),
        focusedErrorBorder: const OutlineInputBorder(
          borderRadius: AppRadius.brButton,
          borderSide: BorderSide(color: AppColors.danger, width: 1.6),
        ),
        prefixIconColor: AppColors.n400,
        floatingLabelStyle: const TextStyle(color: AppColors.brand),
      ),

      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? AppColors.onBrand
              : AppColors.n0,
        ),
        trackColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? AppColors.brand
              : AppColors.n200,
        ),
        trackOutlineColor: const WidgetStatePropertyAll(Colors.transparent),
      ),

      dividerTheme: DividerThemeData(color: border, thickness: 1, space: 1),

      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: surface,
        indicatorColor: AppColors.brand,
        elevation: 0,
        labelTextStyle: WidgetStatePropertyAll(
          AppTypography.textTheme(onSurface).bodyMedium,
        ),
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            color: states.contains(WidgetState.selected)
                ? AppColors.onBrand
                : AppColors.n400,
          ),
        ),
      ),

      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.brand,
      ),

      snackBarTheme: const SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: AppRadius.brMd),
      ),

      // Light page transitions (no heavy motion).
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: FadeUpwardsPageTransitionsBuilder(),
          TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
          TargetPlatform.windows: FadeUpwardsPageTransitionsBuilder(),
          TargetPlatform.macOS: CupertinoPageTransitionsBuilder(),
        },
      ),
    );
  }
}
