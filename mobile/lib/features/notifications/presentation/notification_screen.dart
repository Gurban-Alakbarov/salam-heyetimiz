import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:salam_mobile/core/di/providers.dart';
import 'package:salam_mobile/core/feature_flags/feature_flag_service.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/data_components.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/notifications/domain/entity/notification_entities.dart';
import 'package:salam_mobile/features/notifications/notifications_providers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

/// The notification centre — the single in-app inbox (reached from BOTH the app-bar bell and a push
/// tap). Lists the resident's `inapp` notifications newest-first with cursor pagination, unread state,
/// mark-read (on tap) and mark-all-read. Ships behind [FeatureFlag.notifications]; while the flag is off
/// this is the canonical empty inbox. No feed is fabricated — the list is backend-driven.
class NotificationScreen extends ConsumerStatefulWidget {
  const NotificationScreen({super.key});

  @override
  ConsumerState<NotificationScreen> createState() => _NotificationScreenState();
}

class _NotificationScreenState extends ConsumerState<NotificationScreen> {
  final ScrollController _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scroll.removeListener(_onScroll);
    _scroll.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 300) {
      ref.read(notificationInboxProvider.notifier).loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final enabled = ref
        .watch(featureFlagServiceProvider)
        .isEnabled(FeatureFlag.notifications);

    if (!enabled) {
      return AppScaffold(title: l.notifications, body: _OffState(l: l));
    }

    final inbox = ref.watch(notificationInboxProvider);
    final unread = inbox.value?.unreadCount ?? 0;
    final notifier = ref.read(notificationInboxProvider.notifier);

    return AppScaffold(
      title: l.notifications,
      body: Column(
        children: [
          if (unread > 0)
            Align(
              alignment: Alignment.centerRight,
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.md,
                  vertical: AppSpacing.xs,
                ),
                child: TextButton.icon(
                  onPressed: notifier.markAllRead,
                  icon: const Icon(Icons.done_all_rounded, size: 18),
                  label: Text(l.notificationsMarkAllRead),
                ),
              ),
            ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: notifier.refresh,
              child: inbox.when(
                loading: () => ListView(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  children: List.generate(
                    4,
                    (_) => const Padding(
                      padding: EdgeInsets.symmetric(vertical: AppSpacing.xs),
                      child: AppSkeletonCard(),
                    ),
                  ),
                ),
                error: (error, _) => ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: [
                    const SizedBox(height: 160),
                    ErrorStateView(
                      message: l.errUnknown,
                      onRetry: () => notifier.refresh(),
                    ),
                  ],
                ),
                data: (state) {
                  if (state.items.isEmpty) {
                    return ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: [
                        const SizedBox(height: 160),
                        EmptyState(
                          message: l.notificationsEmpty,
                          icon: Icons.notifications_none_rounded,
                        ),
                      ],
                    );
                  }
                  return ListView.builder(
                    controller: _scroll,
                    padding: const EdgeInsets.all(AppSpacing.md),
                    itemCount: state.items.length + (state.hasMore ? 1 : 0),
                    itemBuilder: (context, index) {
                      if (index >= state.items.length) {
                        return const Padding(
                          padding: EdgeInsets.all(AppSpacing.md),
                          child: Center(
                            child: SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            ),
                          ),
                        );
                      }
                      final n = state.items[index];
                      return _NotificationCard(
                        notification: n,
                        onTap: () => notifier.markRead(n.id),
                      );
                    },
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({required this.notification, required this.onTap});

  final AppNotification notification;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final n = notification;
    final df = DateFormat('dd.MM.yyyy HH:mm');

    return AppAppear(
      child: AppCard(
        onTap: onTap,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 8,
              height: 8,
              margin: const EdgeInsets.only(top: 6, right: AppSpacing.sm),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: n.isRead ? Colors.transparent : AppColors.brand,
              ),
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    n.title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: n.isRead ? FontWeight.w400 : FontWeight.w700,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (n.body.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(
                      n.body,
                      style: Theme.of(context).textTheme.bodyMedium,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                  if (n.createdAt != null) ...[
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      df.format(n.createdAt!.toLocal()),
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.n400,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _OffState extends StatelessWidget {
  const _OffState({required this.l});

  final AppLocalizations l;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.xl),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(
            Icons.notifications_off_outlined,
            size: 56,
            color: AppColors.n400,
          ),
          const SizedBox(height: AppSpacing.lg),
          Text(l.notificationsEmpty, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: AppSpacing.sm),
          Text(
            l.notificationsEmptyHint,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium,
          ),
        ],
      ),
    );
  }
}
