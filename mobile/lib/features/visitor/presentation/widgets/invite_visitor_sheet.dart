import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:share_plus/share_plus.dart';

import '../../../../design_system/components/app_components.dart';
import '../../../../design_system/components/app_inputs.dart';
import '../../../../design_system/tokens/tokens.dart';
import '../../../../l10n/app_localizations.dart';
import '../../../../shared/failure_message.dart';
import '../../domain/entity/visitor_entities.dart';
import '../../visitor_providers.dart';

/// "Invite Visitor" — the ONLY entry point to the visitor-link flow. A bottom
/// sheet where the resident sets access type + duration + optional name/purpose,
/// generates a shareable link, and hands it to the native share sheet. Independent
/// of the "Directions" and "Open Barrier" actions.
class InviteVisitorSheet extends ConsumerStatefulWidget {
  const InviteVisitorSheet({
    required this.deviceId,
    required this.barrierLabel,
    super.key,
  });

  final int deviceId;
  final String barrierLabel;

  /// Opens the sheet (scroll-controlled so the keyboard never clips the form).
  static Future<void> show(
    BuildContext context, {
    required int deviceId,
    required String barrierLabel,
  }) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) =>
          InviteVisitorSheet(deviceId: deviceId, barrierLabel: barrierLabel),
    );
  }

  @override
  ConsumerState<InviteVisitorSheet> createState() => _InviteVisitorSheetState();
}

class _InviteVisitorSheetState extends ConsumerState<InviteVisitorSheet> {
  static const _durations = <int>[15, 30, 60, 120, 240, 480, 720];

  VisitorAccessType _accessType = VisitorAccessType.oneTime;
  int _durationMinutes = 30;
  VisitorPurpose? _purpose;
  final _nameController = TextEditingController();

  @override
  void dispose() {
    _nameController.dispose();
    super.dispose();
  }

  void _submit() {
    ref
        .read(visitorCreateProvider.notifier)
        .create(
          widget.deviceId,
          accessType: _accessType,
          durationMinutes: _accessType == VisitorAccessType.timeLimited
              ? _durationMinutes
              : null,
          visitorName: _nameController.text,
          purpose: _purpose,
        );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final state = ref.watch(visitorCreateProvider);

    return Padding(
      // Keep the form clear of the keyboard.
      padding: EdgeInsets.only(
        left: AppSpacing.xl,
        right: AppSpacing.xl,
        top: AppSpacing.xs,
        bottom: MediaQuery.of(context).viewInsets.bottom + AppSpacing.xl,
      ),
      child: SingleChildScrollView(
        child: state is VisitorCreated
            ? _ResultView(link: state.link, barrierLabel: widget.barrierLabel)
            : _form(context, l, state),
      ),
    );
  }

  Widget _form(
    BuildContext context,
    AppLocalizations l,
    VisitorCreateState state,
  ) {
    final creating = state is VisitorCreating;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l.visitorInviteTitle, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: AppSpacing.xs),
        Text(
          widget.barrierLabel,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: AppSpacing.lg),

        // Access type
        SegmentedButton<VisitorAccessType>(
          segments: [
            ButtonSegment(
              value: VisitorAccessType.oneTime,
              label: Text(l.visitorAccessOneTime),
              icon: const Icon(Icons.looks_one_outlined),
            ),
            ButtonSegment(
              value: VisitorAccessType.timeLimited,
              label: Text(l.visitorAccessTimeLimited),
              icon: const Icon(Icons.timelapse_outlined),
            ),
          ],
          selected: {_accessType},
          onSelectionChanged: creating
              ? null
              : (s) => setState(() => _accessType = s.first),
        ),

        // Duration (time-limited only)
        if (_accessType == VisitorAccessType.timeLimited) ...[
          const SizedBox(height: AppSpacing.lg),
          _label(context, l.visitorDurationLabel),
          const SizedBox(height: AppSpacing.sm),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              for (final m in _durations)
                ChoiceChip(
                  label: Text(_durationLabel(l, m)),
                  selected: _durationMinutes == m,
                  onSelected: creating
                      ? null
                      : (_) => setState(() => _durationMinutes = m),
                ),
            ],
          ),
        ],

        const SizedBox(height: AppSpacing.md),
        AppTextField(
          label: l.visitorNameLabel,
          controller: _nameController,
          enabled: !creating,
          prefixIcon: Icons.person_outline,
          textInputAction: TextInputAction.done,
        ),

        const SizedBox(height: AppSpacing.sm),
        _label(context, l.visitorPurposeLabel),
        const SizedBox(height: AppSpacing.sm),
        Wrap(
          spacing: AppSpacing.sm,
          runSpacing: AppSpacing.sm,
          children: [
            for (final p in VisitorPurpose.values)
              ChoiceChip(
                label: Text(_purposeLabel(l, p)),
                selected: _purpose == p,
                onSelected: creating
                    ? null
                    : (sel) => setState(() => _purpose = sel ? p : null),
              ),
          ],
        ),

        if (state is VisitorCreateFailed) ...[
          const SizedBox(height: AppSpacing.md),
          _errorLine(context, deviceFailureMessage(l, state.failure)),
        ],

        const SizedBox(height: AppSpacing.xl),
        AppButton(
          label: l.visitorGenerate,
          icon: Icons.link_rounded,
          loading: creating,
          onPressed: creating ? null : _submit,
        ),
        const SizedBox(height: AppSpacing.sm),
      ],
    );
  }

  Widget _label(BuildContext context, String text) => Align(
    alignment: Alignment.centerLeft,
    child: Text(text, style: Theme.of(context).textTheme.labelLarge),
  );

  Widget _errorLine(BuildContext context, String message) => Row(
    children: [
      const Icon(Icons.error_outline, size: 18, color: AppColors.danger),
      const SizedBox(width: AppSpacing.xs),
      Expanded(
        child: Text(
          message,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: AppColors.danger,
          ),
        ),
      ),
    ],
  );

  String _durationLabel(AppLocalizations l, int minutes) => minutes < 60
      ? l.visitorMinutesShort(minutes)
      : l.visitorHoursShort(minutes ~/ 60);

  String _purposeLabel(AppLocalizations l, VisitorPurpose p) => switch (p) {
    VisitorPurpose.guest => l.visitorPurposeGuest,
    VisitorPurpose.delivery => l.visitorPurposeDelivery,
    VisitorPurpose.courier => l.visitorPurposeCourier,
    VisitorPurpose.service => l.visitorPurposeService,
    VisitorPurpose.cleaning => l.visitorPurposeCleaning,
    VisitorPurpose.taxi => l.visitorPurposeTaxi,
    VisitorPurpose.other => l.visitorPurposeOther,
  };
}

/// Success view — the generated link plus Share / Copy / Done. The link is shown
/// once; the plaintext token cannot be retrieved again.
class _ResultView extends ConsumerWidget {
  const _ResultView({required this.link, required this.barrierLabel});

  final CreatedVisitorLink link;
  final String barrierLabel;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: AppSpacing.sm),
        const Icon(Icons.check_circle_outline, color: AppColors.success, size: 44),
        const SizedBox(height: AppSpacing.sm),
        Text(
          l.visitorLinkReady,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: AppSpacing.xs),
        Text(
          barrierLabel,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: AppSpacing.lg),
        Container(
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            color: AppColors.brandContainer,
            borderRadius: AppRadius.brMd,
            border: Border.all(color: AppColors.border),
          ),
          child: SelectableText(
            link.url,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: AppColors.textPrimary,
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        AppButton(
          label: l.visitorShare,
          icon: Icons.ios_share_rounded,
          onPressed: () => SharePlus.instance.share(ShareParams(text: link.url)),
        ),
        const SizedBox(height: AppSpacing.sm),
        Row(
          children: [
            Expanded(
              child: AppSecondaryButton(
                label: l.visitorCopy,
                icon: Icons.copy_rounded,
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: link.url));
                  if (context.mounted) AppSnackBar.show(context, l.visitorCopied);
                },
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: AppSecondaryButton(
                label: l.visitorDone,
                onPressed: () => Navigator.of(context).maybePop(),
              ),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.sm),
      ],
    );
  }
}
