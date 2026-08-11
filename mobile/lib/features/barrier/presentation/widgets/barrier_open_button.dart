import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/app_inputs.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/barrier/barrier_providers.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/shared/failure_message.dart';

/// A barrier action (Aç / Bağla) + its live lifecycle (idle → sending → pending
/// → success | failed | timeout | cooldown). The button is locked while a command
/// is in flight (no duplicate command). Open and close use independent
/// providers, so each button shows its own status. Progress is REAL polling
/// elapsed. (Phase 5 — both flow through the same DeviceComm pipeline.)
class BarrierActionButton extends ConsumerStatefulWidget {
  const BarrierActionButton({
    required this.deviceId,
    required this.canDo,
    required this.direction,
    super.key,
  });

  final int deviceId;
  final bool canDo;
  final BarrierDirection direction;

  @override
  ConsumerState<BarrierActionButton> createState() =>
      _BarrierActionButtonState();
}

class _BarrierActionButtonState extends ConsumerState<BarrierActionButton> {
  Timer? _cooldownTimer;
  int _cooldownLeft = 0;

  bool get _isOpen => widget.direction == BarrierDirection.open;

  void _startCooldown(int seconds) {
    _cooldownTimer?.cancel();
    setState(() => _cooldownLeft = seconds);
    _cooldownTimer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (_cooldownLeft <= 1) {
        t.cancel();
        setState(() => _cooldownLeft = 0);
      } else {
        setState(() => _cooldownLeft -= 1);
      }
    });
  }

  void _onState(BarrierOpenState? _, BarrierOpenState next) {
    if (next is BarrierCooldown) _startCooldown(next.retryAfterSeconds);
  }

  @override
  void dispose() {
    _cooldownTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final labels = _BarrierLabels.of(l, widget.direction);

    final BarrierOpenState state;
    final VoidCallback trigger;
    if (_isOpen) {
      state = ref.watch(barrierOpenProvider);
      ref.listen(barrierOpenProvider, _onState);
      trigger = () => ref.read(barrierOpenProvider.notifier).open(widget.deviceId);
    } else {
      state = ref.watch(barrierCloseProvider);
      ref.listen(barrierCloseProvider, _onState);
      trigger = () =>
          ref.read(barrierCloseProvider.notifier).close(widget.deviceId);
    }

    final inProgress = state is BarrierSending || state is BarrierPending;
    final cooling = _cooldownLeft > 0;
    final enabled = widget.canDo && !inProgress && !cooling;

    final label = switch (state) {
      BarrierSending() => labels.sending,
      BarrierPending() => labels.pending,
      _ => labels.idle,
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _BarrierStatus(
          state: state,
          cooldownLeft: _cooldownLeft,
          labels: labels,
        ),
        const SizedBox(height: AppSpacing.md),
        AppButton(
          label: label,
          loading: inProgress,
          onPressed: enabled ? trigger : null,
        ),
        // Actuation feedback only for open ("did the gate move?").
        if (_isOpen && state is BarrierSuccess && !state.actuated)
          _FeedbackRow(commandId: state.commandId),
      ],
    );
  }
}

/// Backward-compatible open button (thin wrapper over the shared action button).
class BarrierOpenButton extends StatelessWidget {
  const BarrierOpenButton({
    required this.deviceId,
    required this.canOpen,
    super.key,
  });

  final int deviceId;
  final bool canOpen;

  @override
  Widget build(BuildContext context) => BarrierActionButton(
    deviceId: deviceId,
    canDo: canOpen,
    direction: BarrierDirection.open,
  );
}

/// Localized labels for a direction (Aç vs Bağla).
class _BarrierLabels {
  const _BarrierLabels({
    required this.idle,
    required this.sending,
    required this.pending,
    required this.successActuated,
    required this.successSent,
    required this.failed,
    required this.pendingIcon,
  });

  final String idle;
  final String sending;
  final String pending;
  final String successActuated;
  final String successSent;
  final String failed;
  final IconData pendingIcon;

  static _BarrierLabels of(AppLocalizations l, BarrierDirection direction) =>
      direction == BarrierDirection.open
      ? _BarrierLabels(
          idle: l.barrierOpen,
          sending: l.barrierSending,
          pending: l.barrierPending,
          successActuated: l.barrierSuccessOpened,
          successSent: l.barrierSuccessSent,
          failed: l.barrierFailed,
          pendingIcon: Icons.lock_open_outlined,
        )
      : _BarrierLabels(
          idle: l.barrierClose,
          sending: l.barrierCloseSending,
          pending: l.barrierClosePending,
          successActuated: l.barrierCloseSuccessClosed,
          successSent: l.barrierCloseSuccessSent,
          failed: l.barrierCloseFailed,
          pendingIcon: Icons.lock_outline,
        );
}

class _BarrierStatus extends StatelessWidget {
  const _BarrierStatus({
    required this.state,
    required this.cooldownLeft,
    required this.labels,
  });

  final BarrierOpenState state;
  final int cooldownLeft;
  final _BarrierLabels labels;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return switch (state) {
      BarrierIdle() => const SizedBox.shrink(),
      BarrierSending() => _line(
        context,
        labels.sending,
        AppColors.info,
        Icons.send_outlined,
      ),
      BarrierPending(:final progress) => Column(
        children: [
          _line(context, labels.pending, AppColors.info, labels.pendingIcon),
          const SizedBox(height: AppSpacing.sm),
          ClipRRect(
            borderRadius: BorderRadius.circular(AppRadius.sm),
            child: LinearProgressIndicator(
              value: progress == 0 ? null : progress,
            ),
          ),
        ],
      ),
      BarrierSuccess(:final actuated) => _line(
        context,
        actuated ? labels.successActuated : labels.successSent,
        AppColors.success,
        Icons.check_circle_outline,
      ),
      BarrierFailed() => _line(
        context,
        _failMessage(l, state as BarrierFailed),
        AppColors.danger,
        Icons.error_outline,
      ),
      BarrierTimeout() => _line(
        context,
        l.barrierTimeout,
        AppColors.warning,
        Icons.timer_off_outlined,
      ),
      BarrierCooldown() => _line(
        context,
        l.barrierCooldown(cooldownLeft),
        AppColors.warning,
        Icons.hourglass_bottom,
      ),
    };
  }

  Widget _line(BuildContext context, String text, Color color, IconData icon) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(icon, color: color, size: 20),
        const SizedBox(width: AppSpacing.sm),
        Flexible(
          child: Text(
            text,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: color),
          ),
        ),
      ],
    );
  }

  String _failMessage(AppLocalizations l, BarrierFailed state) {
    final reason = state.reason;
    if (reason == 'whitelist_blocked') return l.errWhitelist;
    if (reason == 'device_offline' || state.failure is DeviceOfflineFailure) {
      return l.errDeviceOffline;
    }
    if (reason == 'access_denied' || state.failure is ForbiddenFailure) {
      return l.errAccessDenied;
    }
    if (reason == 'offline' ||
        state.failure is NetworkFailure ||
        state.failure is TimeoutFailure) {
      return l.errNetwork;
    }
    if (state.failure is ServerFailure) return l.errServer;
    // Fall back to the typed failure message, else this action's failure label.
    final mapped = deviceFailureMessage(l, state.failure);
    return mapped == l.errUnknown ? labels.failed : mapped;
  }
}

class _FeedbackRow extends ConsumerWidget {
  const _FeedbackRow({required this.commandId});

  final int commandId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.md),
      child: Column(
        children: [
          Text(
            l.barrierGateMovedQuestion,
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: AppSpacing.sm),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              AppTextButton(
                label: l.yes,
                onPressed: () => ref
                    .read(barrierOpenProvider.notifier)
                    .sendFeedback(commandId, gateMoved: true),
              ),
              const SizedBox(width: AppSpacing.lg),
              AppTextButton(
                label: l.no,
                onPressed: () => ref
                    .read(barrierOpenProvider.notifier)
                    .sendFeedback(commandId, gateMoved: false),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
