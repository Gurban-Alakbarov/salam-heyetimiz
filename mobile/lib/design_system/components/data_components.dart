import 'package:flutter/material.dart';

import '../tokens/tokens.dart';

/// Tokenised card surface — white, rounded (radius 18) with a very light shadow
/// and hairline border. Features compose data UI from this (Constitution §4.1).
class AppCard extends StatelessWidget {
  const AppCard({
    required this.child,
    this.onTap,
    this.padding,
    this.margin,
    this.clip = false,
    super.key,
  });

  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry? padding;
  final EdgeInsetsGeometry? margin;

  /// Clip the child to the card radius (use for cards with a flush top image).
  final bool clip;

  @override
  Widget build(BuildContext context) {
    final surface = Theme.of(context).colorScheme.surface;
    final outline = Theme.of(context).colorScheme.outline;
    final content = Padding(
      padding: padding ?? const EdgeInsets.all(AppSpacing.lg),
      child: child,
    );
    return Container(
      margin: margin ?? const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      decoration: BoxDecoration(
        color: surface,
        borderRadius: AppRadius.brCard,
        boxShadow: AppShadows.card,
        border: Border.all(color: outline.withValues(alpha: 0.5)),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: AppRadius.brCard,
          child: clip
              ? ClipRRect(borderRadius: AppRadius.brCard, child: content)
              : content,
        ),
      ),
    );
  }
}

enum BadgeTone { success, danger, warning, neutral, info, brand }

/// Status pill (online/offline, command states, etc.). Generic — the label +
/// tone are decided by the feature.
class StatusBadge extends StatelessWidget {
  const StatusBadge({
    required this.label,
    this.tone = BadgeTone.neutral,
    super.key,
  });

  final String label;
  final BadgeTone tone;

  Color get _color => switch (tone) {
    BadgeTone.success => AppColors.success,
    BadgeTone.danger => AppColors.danger,
    BadgeTone.warning => AppColors.warning,
    BadgeTone.info => AppColors.info,
    BadgeTone.brand => AppColors.brand,
    BadgeTone.neutral => AppColors.n400,
  };

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.sm,
        vertical: 2,
      ),
      decoration: BoxDecoration(
        color: _color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(AppRadius.sm),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(color: _color, shape: BoxShape.circle),
          ),
          const SizedBox(width: AppSpacing.xs),
          Text(
            label,
            style: TextStyle(
              color: _color,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

/// A single pulsing skeleton block. Compose lists/cards from these while loading.
class AppSkeleton extends StatefulWidget {
  const AppSkeleton({
    this.width,
    this.height = 16,
    this.radius = AppRadius.sm,
    super.key,
  });

  final double? width;
  final double height;
  final double radius;

  @override
  State<AppSkeleton> createState() => _AppSkeletonState();
}

class _AppSkeletonState extends State<AppSkeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final base = Theme.of(context).colorScheme.onSurface;
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) => Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          color: base.withValues(alpha: 0.06 + 0.10 * _controller.value),
          borderRadius: BorderRadius.circular(widget.radius),
        ),
      ),
    );
  }
}

/// A card-shaped skeleton used by list/detail loading states.
class AppSkeletonCard extends StatelessWidget {
  const AppSkeletonCard({super.key});

  @override
  Widget build(BuildContext context) {
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          AppSkeleton(width: 160, height: 18),
          SizedBox(height: AppSpacing.sm),
          AppSkeleton(width: 100, height: 14),
          SizedBox(height: AppSpacing.sm),
          AppSkeleton(width: 80, height: 22),
        ],
      ),
    );
  }
}
