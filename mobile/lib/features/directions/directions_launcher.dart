import 'dart:io' show Platform;

import 'package:android_intent_plus/android_intent.dart';
import 'package:flutter/material.dart';
import 'package:installed_apps/installed_apps.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../design_system/components/app_inputs.dart';
import '../../l10n/app_localizations.dart';

/// AzNav — Azerbaijan's national navigation app (Sinam).
const _azNavPackage = 'net.sinam.aznav';

/// AzNav on the App Store (Sinam MMC), for an iPhone without AzNav or with a version too old for the route link.
const _azNavAppStoreUrl = 'https://apps.apple.com/app/id6758716660';

/// AzNav's route link on iPhone, as given by Sinam. Note it is `lon`, not `lng`. The `aznav` scheme is listed under
/// LSApplicationQueriesSchemes in Info.plist so iOS lets the app check for it.
@visibleForTesting
Uri azNavRouteUri(double lat, double lng) =>
    Uri.parse('aznav://route?lat=$lat&lon=$lng');

/// Resident "Directions" — navigate to a barrier using **AzNav only**.
///
/// Independent of Visitor Links. AzNav is the single supported navigation
/// provider (no picker, no Google Maps/Waze/HERE/2GIS/Yandex, and no generic-map
/// fallback). The destination coordinates are handed off via [_launchAzNav] on
/// Android and via [azNavRouteUri] on iPhone.
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
    if (Platform.isIOS) {
      await _launchAzNavOnIos(context, lat, lng);
      return;
    }
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

  /// iPhone: the route link when AzNav can take it, otherwise AzNav's App Store page.
  static Future<void> _launchAzNavOnIos(
    BuildContext context,
    double lat,
    double lng,
  ) async {
    final uri = azNavRouteUri(lat, lng);
    final canOpen = await canLaunchUrl(uri).catchError((_) => false);
    final opened = canOpen &&
        await launchUrl(uri, mode: LaunchMode.externalApplication)
            .catchError((_) => false);
    if (!opened && context.mounted) {
      await _openAzNavAppStore(context);
    }
  }

  static Future<void> _openAzNavAppStore(BuildContext context) async {
    final l = AppLocalizations.of(context);
    final ok = await launchUrl(Uri.parse(_azNavAppStoreUrl), mode: LaunchMode.externalApplication)
        .catchError((_) => false);
    if (!ok && context.mounted) {
      AppSnackBar.show(context, l.directionsFailed, isError: true);
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
