import 'dart:io' show Platform;

import 'package:android_intent_plus/android_intent.dart';
import 'package:flutter/material.dart';
import 'package:installed_apps/installed_apps.dart';

import '../../design_system/components/app_inputs.dart';
import '../../l10n/app_localizations.dart';

/// AzNav — Azerbaijan's national navigation app (Sinam). Android-only.
const _azNavPackage = 'net.sinam.aznav';

/// Resident "Directions" — navigate to a barrier using **AzNav only**.
///
/// Independent of Visitor Links. AzNav is the single supported navigation
/// provider (no picker, no Google Maps/Waze/HERE/2GIS/Yandex, and no generic-map
/// fallback). AzNav is Android-only; the destination coordinates are handed off
/// exactly as before via [_launchAzNav].
class DirectionsLauncher {
  DirectionsLauncher._();

  /// Open turn-by-turn directions to ([lat], [lng]) in AzNav. If AzNav is not
  /// installed, prompt the user to install it (AzNav is the only supported
  /// navigation app) — never a substitute map provider, never a generic error.
  static Future<void> open(
    BuildContext context, {
    required double lat,
    required double lng,
    String? label,
  }) async {
    final available = Platform.isAndroid && await _isAzNavInstalled();
    if (!context.mounted) return;

    if (available) {
      await _launchAzNav(context, lat, lng, label);
      return;
    }
    await _promptInstallAzNav(context);
  }

  static Future<bool> _isAzNavInstalled() async {
    try {
      return await InstalledApps.isAppInstalled(_azNavPackage) == true;
    } catch (_) {
      return false;
    }
  }

  /// AzNav is missing → a friendly prompt to install it (rather than a dead-end
  /// error). Confirming opens AzNav's official Play Store page.
  static Future<void> _promptInstallAzNav(BuildContext context) async {
    final l = AppLocalizations.of(context);
    final install = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l.azNavRequiredTitle),
        content: Text(l.azNavRequiredMessage),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(l.cancel),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(l.azNavInstall),
          ),
        ],
      ),
    );
    if (install == true && context.mounted) {
      await _openAzNavStore(context);
    }
  }

  /// Open AzNav's official Play Store page: the Play Store app if present, else
  /// the same official URL in the browser.
  static Future<void> _openAzNavStore(BuildContext context) async {
    final l = AppLocalizations.of(context);
    const marketUri = 'market://details?id=$_azNavPackage';
    const webUri =
        'https://play.google.com/store/apps/details?id=$_azNavPackage';
    try {
      await AndroidIntent(action: 'action_view', data: marketUri).launch();
    } catch (_) {
      try {
        await AndroidIntent(action: 'action_view', data: webUri).launch();
      } catch (_) {
        if (context.mounted) {
          AppSnackBar.show(context, l.directionsFailed, isError: true);
        }
      }
    }
  }

  static Future<void> _launchAzNav(
    BuildContext context,
    double lat,
    double lng,
    String? label,
  ) async {
    final l = AppLocalizations.of(context);
    final base = 'geo:$lat,$lng?q=$lat,$lng';
    final data = (label != null && label.trim().isNotEmpty)
        ? '$base(${Uri.encodeComponent(label.trim())})'
        : base;
    try {
      // A geo: route intent scoped to AzNav (navigation apps handle geo:); if it can't, just open it.
      await AndroidIntent(
        action: 'action_view',
        data: data,
        package: _azNavPackage,
      ).launch();
    } catch (_) {
      try {
        await InstalledApps.startApp(_azNavPackage);
      } catch (_) {
        if (context.mounted) {
          AppSnackBar.show(context, l.directionsFailed, isError: true);
        }
      }
    }
  }
}
