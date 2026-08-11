import 'package:flutter/material.dart';

import '../tokens/tokens.dart';

/// One destination in [AppBottomNav].
class AppBottomNavItem {
  const AppBottomNavItem({required this.icon, required this.label});
  final IconData icon;
  final String label;
}

/// Floating capsule bottom navigation. A rounded pill container hovers above the
/// content (the scaffold background shows around it). Each tab is an icon over a
/// label; the SELECTED tab shows a soft green ([AppColors.brandContainer]) pill
/// behind a brand-green icon + label, while unselected tabs stay visually quiet.
/// Purely presentational — selection state is owned by the caller.
///
/// Height is INTRINSIC (content-driven). Accessibility text scaling is clamped so
/// the bar stays consistent, and [SafeArea] keeps clear of the home indicator.
class AppBottomNav extends StatelessWidget {
  const AppBottomNav({
    required this.items,
    required this.currentIndex,
    required this.onTap,
    super.key,
  });

  final List<AppBottomNavItem> items;
  final int currentIndex;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final surface = Theme.of(context).colorScheme.surface;
    final onSurface = Theme.of(context).colorScheme.onSurface;
    final outline = Theme.of(context).colorScheme.outline;

    return SafeArea(
      top: false,
      child: MediaQuery.withClampedTextScaling(
        maxScaleFactor: 1.3,
        child: Padding(
          // Margin around the pill → it floats, separated from the content.
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg,
            AppSpacing.xs,
            AppSpacing.lg,
            AppSpacing.sm,
          ),
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: surface,
              borderRadius: AppRadius.brPill,
              boxShadow: AppShadows.card,
              border: Border.all(color: outline.withValues(alpha: 0.4)),
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.sm,
                vertical: AppSpacing.sm,
              ),
              child: Row(
                children: [
                  for (var i = 0; i < items.length; i++)
                    Expanded(
                      child: _NavItem(
                        item: items[i],
                        selected: i == currentIndex,
                        onSurface: onSurface,
                        onTap: () => onTap(i),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.item,
    required this.selected,
    required this.onSurface,
    required this.onTap,
  });

  final AppBottomNavItem item;
  final bool selected;
  final Color onSurface;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final labelColor = selected
        ? AppColors.brand
        : onSurface.withValues(alpha: 0.7);

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      // heightFactor:1 → wrap to the child's height while filling the tab width
      // for a full-cell tap target.
      child: Align(
        alignment: Alignment.center,
        heightFactor: 1,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Soft green highlight pill behind the icon on the selected tab.
            AnimatedContainer(
              duration: AppDurations.base,
              curve: Curves.easeOut,
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.lg,
                vertical: 5,
              ),
              decoration: BoxDecoration(
                color: selected
                    ? AppColors.brandContainer
                    : Colors.transparent,
                borderRadius: AppRadius.brPill,
              ),
              child: Icon(
                item.icon,
                size: 22,
                color: selected ? AppColors.brand : AppColors.n400,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              item.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: labelColor,
                fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
                fontSize: 11,
                height: 1.1,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
