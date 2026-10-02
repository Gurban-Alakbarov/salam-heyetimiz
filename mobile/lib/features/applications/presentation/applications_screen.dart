import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/error/failure.dart';
import '../../../design_system/components/app_components.dart';
import '../../../design_system/components/data_components.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../applications_providers.dart';
import 'application_failure.dart';

/// "Müraciətlərim" — ApplicationStatus (IMPLEMENTATION_PLAN §21 / B14): the caller's own physical and legal
/// applications (separate sections), their status, and "new application" actions limited to what the
/// account may file (typed accounts: own kind only; legacy NULL: either).
class ApplicationsScreen extends ConsumerWidget {
  const ApplicationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final async = ref.watch(myApplicationsProvider);

    return AppScaffold(
      title: l.appMineTitle,
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(myApplicationsProvider.future),
        child: async.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => ListView(children: [
            const SizedBox(height: 160),
            ErrorStateView(
              message: e is Failure ? applicationFailureMessage(l, e) : l.errUnknown,
              onRetry: () => ref.invalidate(myApplicationsProvider),
            ),
          ]),
          data: (mine) => ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              if (mine.individual.isEmpty && mine.legal.isEmpty)
                Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                  child: EmptyState(message: l.appNone, icon: Icons.assignment_outlined),
                ),
              if (mine.canFilePhysical)
                Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                  child: AppSecondaryButton(label: l.appNewPhysical, icon: Icons.home_outlined, onPressed: () => context.push('/applications/new/physical')),
                ),
              if (mine.canFileLegal)
                Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                  child: AppSecondaryButton(label: l.appNewLegal, icon: Icons.apartment_outlined, onPressed: () => context.push('/applications/new/legal')),
                ),
              if (mine.individual.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.md),
                Text(l.appSectionPhysical, style: Theme.of(context).textTheme.titleMedium),
                for (final a in mine.individual)
                  _AppTile(
                    title: a.address,
                    status: a.status,
                    label: _individualLabel(l, a.status),
                    tone: _tone(a.status),
                    date: a.statusChangedAt ?? a.createdAt,
                    reason: a.rejectionReason,
                  ),
              ],
              if (mine.legal.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.md),
                Text(l.appSectionLegal, style: Theme.of(context).textTheme.titleMedium),
                for (final a in mine.legal)
                  _AppTile(
                    title: a.complexName,
                    subtitle: '${l.appVoen}: ${a.voen}',
                    status: a.status,
                    label: _legalLabel(l, a.status),
                    tone: _tone(a.status),
                    date: a.reviewedAt ?? a.createdAt,
                    reason: a.rejectionReason,
                  ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  static String _individualLabel(AppLocalizations l, String s) => switch (s) {
        'new' => l.appStatusNew,
        'contacted' => l.appStatusContacted,
        'in_progress' => l.appStatusInProgress,
        'installed' => l.appStatusInstalled,
        'rejected' => l.appStatusRejected,
        _ => s,
      };

  static String _legalLabel(AppLocalizations l, String s) => switch (s) {
        'pending' => l.appStatusPending,
        'approved' => l.appStatusApproved,
        'rejected' => l.appStatusRejected,
        _ => s,
      };

  static BadgeTone _tone(String s) => switch (s) {
        'installed' || 'approved' => BadgeTone.success,
        'rejected' => BadgeTone.danger,
        'new' || 'pending' => BadgeTone.warning,
        _ => BadgeTone.info,
      };
}

class _AppTile extends StatelessWidget {
  const _AppTile({required this.title, required this.status, required this.label, required this.tone, this.subtitle, this.date, this.reason});

  final String title;
  final String? subtitle;
  final String status;
  final String label;
  final BadgeTone tone;
  final DateTime? date;
  final String? reason;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.sm),
      child: AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              Expanded(child: Text(title, style: Theme.of(context).textTheme.titleSmall, maxLines: 2, overflow: TextOverflow.ellipsis)),
              StatusBadge(label: label, tone: tone),
            ]),
            if (subtitle != null) ...[const SizedBox(height: 2), Text(subtitle!, style: Theme.of(context).textTheme.bodySmall)],
            if (date != null) ...[
              const SizedBox(height: AppSpacing.xs),
              Text(DateFormat('dd.MM.yyyy HH:mm').format(date!.toLocal()), style: Theme.of(context).textTheme.bodySmall),
            ],
            if (status == 'rejected' && reason != null && reason!.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.xs),
              Text('${l.appRejectReason}: $reason', style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ],
          ],
        ),
      ),
    );
  }
}
