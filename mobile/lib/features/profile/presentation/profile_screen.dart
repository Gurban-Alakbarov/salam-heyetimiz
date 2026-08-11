import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:salam_mobile/core/di/providers.dart';
import 'package:salam_mobile/design_system/components/app_list.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/auth/auth_providers.dart';
import 'package:salam_mobile/features/auth/domain/entity/auth_entities.dart';
import 'package:salam_mobile/features/notifications/presentation/notification_screen.dart';
import 'package:salam_mobile/features/profile/presentation/help_screen.dart';
import 'package:salam_mobile/features/profile/presentation/personal_info_screen.dart';
import 'package:salam_mobile/features/profile/presentation/residence_screen.dart';
import 'package:salam_mobile/features/settings/settings_screen.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// Profile tab body. It lives inside [HomeShell]'s `IndexedStack`, so it renders
/// WITHOUT its own app bar — the shell supplies the "Profil" title. An identity
/// header, then grouped navigation into the profile sub-screens (shared
/// [AppListSection]/[AppListTile] — the same grouped-list style as Settings).
///
/// Data is read exclusively from existing providers ([currentUserProvider] →
/// GET /v1/me, [appVersionProvider] → real package version); no new backend and
/// no design system beyond the shared tokens.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final user = ref.watch(currentUserProvider).value?.user;
    final version = ref.watch(appVersionProvider);

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        AppSpacing.lg,
        AppSpacing.lg,
        AppSpacing.xl,
      ),
      children: [
        // ── Header: avatar (initials) · name · email ──────────────────────
        _ProfileHeader(user: user),
        const SizedBox(height: AppSpacing.xl),

        // ── A) Personal info · B) Residential complex ─────────────────────
        AppListSection(
          children: [
            AppListTile(
              icon: Icons.person_outline,
              title: l.profilePersonalInfo,
              subtitle: user?.fullName,
              chevron: true,
              onTap: () => _push(context, const PersonalInfoScreen()),
            ),
            AppListTile(
              icon: Icons.apartment_outlined,
              title: l.profileResidence,
              chevron: true,
              onTap: () => _push(context, const ResidenceScreen()),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.lg),

        // ── C) Notifications · D) App settings · E) Help & support ────────
        AppListSection(
          children: [
            AppListTile(
              icon: Icons.notifications_none_rounded,
              title: l.notifications,
              chevron: true,
              onTap: () => _push(context, const NotificationScreen()),
            ),
            AppListTile(
              icon: Icons.tune_rounded,
              title: l.profileAppSettings,
              chevron: true,
              onTap: () => _push(context, const SettingsScreen()),
            ),
            AppListTile(
              icon: Icons.help_outline_rounded,
              title: l.profileHelp,
              chevron: true,
              onTap: () => _push(context, const HelpScreen()),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.lg),

        // ── F) Logout ─────────────────────────────────────────────────────
        AppListSection(
          children: [
            AppListTile(
              icon: Icons.logout_rounded,
              title: l.logout,
              danger: true,
              // Reuses the existing logout use case: clears the session →
              // authStateProvider flips to guest → the router redirects out.
              onTap: () => ref.read(logoutUseCaseProvider).call(),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.xl),

        // ── G) Version (real package version, not hardcoded) ──────────────
        Center(
          child: Text(
            version == null ? '' : l.appVersionLabel(version),
            style: Theme.of(context).textTheme.bodyMedium,
          ),
        ),
      ],
    );
  }

  void _push(BuildContext context, Widget screen) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));
  }
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({this.user});

  final UserEntity? user;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final full = user?.fullName?.trim() ?? '';
    final name = full.isNotEmpty ? full : l.homeUser;
    final email = user?.email;

    return Row(
      children: [
        _Avatar(name: full),
        const SizedBox(width: AppSpacing.lg),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                name,
                style: Theme.of(context).textTheme.titleMedium,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              if (email != null && email.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.xs),
                Text(
                  email,
                  style: Theme.of(context).textTheme.bodyMedium,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    final initials = _initials(name);
    return Container(
      width: 64,
      height: 64,
      decoration: const BoxDecoration(
        shape: BoxShape.circle,
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.brand, AppColors.brandDark],
        ),
      ),
      alignment: Alignment.center,
      child: initials.isEmpty
          ? const Icon(Icons.person, color: AppColors.onBrand, size: 30)
          : Text(
              initials,
              style: const TextStyle(
                color: AppColors.onBrand,
                fontSize: 22,
                fontWeight: FontWeight.w700,
              ),
            ),
    );
  }

  static String _initials(String name) {
    final n = name.trim();
    if (n.isEmpty) return '';
    final parts = n.split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '';
    String head(String s) => s.substring(0, 1).toUpperCase();
    if (parts.length == 1) return head(parts.first);
    return head(parts.first) + head(parts[1]);
  }
}
