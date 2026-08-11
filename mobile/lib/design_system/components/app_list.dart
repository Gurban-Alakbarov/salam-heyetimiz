import 'package:flutter/material.dart';

import '../tokens/tokens.dart';

/// A grouped, rounded list container (Settings/Profile style): an optional muted
/// header above a card whose rows are separated by hairline dividers and clipped
/// to the card radius. Rows are typically [AppListTile]s. Shared so Profile and
/// Settings present the same iOS-style grouped list.
class AppListSection extends StatelessWidget {
  const AppListSection({
    required this.children,
    this.header,
    this.dividerIndent = iconRowIndent,
    super.key,
  });

  /// Divider indent that aligns with the title when rows carry a leading icon
  /// square (icon 38 + surrounding gaps). Icon-less sections pass [AppSpacing.lg].
  static const double iconRowIndent = AppSpacing.lg + 38 + AppSpacing.md;

  final List<Widget> children;
  final String? header;
  final double dividerIndent;

  @override
  Widget build(BuildContext context) {
    final rows = <Widget>[];
    for (var i = 0; i < children.length; i++) {
      if (i > 0) {
        rows.add(
          Divider(
            height: 1,
            thickness: 1,
            indent: dividerIndent,
            color: AppColors.border,
          ),
        );
      }
      rows.add(children[i]);
    }

    final card = Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: AppRadius.brCard,
        boxShadow: AppShadows.card,
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(children: rows),
    );

    if (header == null) return card;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.md,
            0,
            AppSpacing.md,
            AppSpacing.sm,
          ),
          child: Text(
            header!,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w600,
              color: AppColors.textSecondary,
            ),
          ),
        ),
        card,
      ],
    );
  }
}

/// A single row in an [AppListSection]: an optional leading icon (in a tinted
/// square), a title, an optional subtitle, and either a custom [trailing] widget
/// or an opt-in chevron. [danger] tints the icon + title with the error color.
class AppListTile extends StatelessWidget {
  const AppListTile({
    required this.title,
    this.icon,
    this.subtitle,
    this.trailing,
    this.onTap,
    this.danger = false,
    this.chevron = false,
    super.key,
  });

  final String title;
  final IconData? icon;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  final bool danger;
  final bool chevron;

  @override
  Widget build(BuildContext context) {
    final accent = danger ? AppColors.danger : AppColors.brand;
    final hasSubtitle = subtitle != null && subtitle!.trim().isNotEmpty;
    final trail =
        trailing ??
        (chevron
            ? const Icon(Icons.chevron_right_rounded, color: AppColors.n400)
            : null);

    return ListTile(
      leading: icon == null
          ? null
          : Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: accent.withValues(alpha: 0.12),
                borderRadius: AppRadius.brMd,
              ),
              child: Icon(icon, color: accent, size: 20),
            ),
      title: Text(
        title,
        style: TextStyle(
          fontWeight: FontWeight.w600,
          color: danger ? AppColors.danger : null,
        ),
      ),
      subtitle: hasSubtitle
          ? Text(subtitle!, maxLines: 1, overflow: TextOverflow.ellipsis)
          : null,
      trailing: trail,
      onTap: onTap,
    );
  }
}
