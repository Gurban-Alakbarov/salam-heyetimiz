import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/design_system/components/app_components.dart';
import 'package:salam_mobile/design_system/components/app_inputs.dart';
import 'package:salam_mobile/design_system/tokens/tokens.dart';
import 'package:salam_mobile/features/auth/auth_providers.dart';
import 'package:salam_mobile/features/auth/domain/usecase/auth_use_cases.dart';
import 'package:salam_mobile/features/auth/presentation/failure_l10n.dart';
import 'package:salam_mobile/features/auth/presentation/providers/auth_controllers.dart';
import 'package:salam_mobile/features/auth/session_roles_provider.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

class VerifyOtpScreen extends ConsumerStatefulWidget {
  const VerifyOtpScreen({
    required this.email,
    required this.flow,
    this.accountType,
    this.invite = false,
    super.key,
  });

  final String email;
  final AuthFlow flow;

  /// B14: account type chosen at registration ('physical' | 'legal'); routes to its application form.
  final String? accountType;

  /// B16: registration started from an invitation → claim it right after verification.
  final bool invite;

  @override
  ConsumerState<VerifyOtpScreen> createState() => _VerifyOtpScreenState();
}

class _VerifyOtpScreenState extends ConsumerState<VerifyOtpScreen> {
  final _otp = TextEditingController();
  String? _otpError;
  Timer? _timer;
  int _secondsLeft = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _startCountdown());
  }

  void _startCountdown() {
    final seconds =
        ref.read(bootstrapControllerProvider).value?.otpResendSeconds ?? 30;
    _timer?.cancel();
    setState(() => _secondsLeft = seconds);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (_secondsLeft <= 1) {
        t.cancel();
        setState(() => _secondsLeft = 0);
      } else {
        setState(() => _secondsLeft -= 1);
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _otp.dispose();
    super.dispose();
  }

  Future<void> _verify(String code) async {
    final l = AppLocalizations.of(context);
    setState(() => _otpError = null);
    // B16: read before verifying — once the session exists the router may already leave this screen.
    final resumeInvite =
        widget.flow == AuthFlow.login &&
        !widget.invite &&
        await ref.read(pendingInviteStoreProvider).read() != null;
    if (!mounted) return;
    final user = await ref
        .read(verifyControllerProvider.notifier)
        .submit(email: widget.email, code: code, flow: widget.flow);
    if (!mounted) return;
    if (user != null) {
      // B16: an invitation-driven registration claims the invitation at once; a login with an invitation
      // still pending on this device returns to it (explicit accept).
      if (widget.invite) {
        context.go('/invite?accept=1');
        return;
      }
      if (resumeInvite) {
        context.go('/invite');
        return;
      }
      // B14: a typed new registration continues to its application form (skippable — "later").
      final type = widget.accountType;
      if (widget.flow == AuthFlow.register &&
          (type == 'physical' || type == 'legal')) {
        context.go('/applications/new/$type?onboarding=1');
        return;
      }
      context.go('/home');
      return;
    }
    final failure = ref.read(verifyControllerProvider).error;
    if (failure is Failure) {
      setState(() {
        _otpError = failureMessage(l, failure);
        _otp.clear();
      });
    }
  }

  Future<void> _resend() async {
    final l = AppLocalizations.of(context);
    final otp = await ref
        .read(verifyControllerProvider.notifier)
        .resend(email: widget.email);
    if (!mounted) return;
    if (otp != null) {
      _startCountdown();
      AppSnackBar.show(context, l.otpSentTo(widget.email));
    } else {
      final failure = ref
          .read(verifyControllerProvider.notifier)
          .lastResendFailure;
      if (failure != null) {
        AppSnackBar.show(context, failureMessage(l, failure), isError: true);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final loading = ref.watch(verifyControllerProvider).isLoading;
    final canResend = _secondsLeft == 0;

    return AppScaffold(
      title: l.otpTitle,
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SizedBox(height: AppSpacing.lg),
            Text(
              l.otpSentTo(widget.email),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: AppSpacing.xl),
            AppOtpField(
              controller: _otp,
              enabled: !loading,
              errorText: _otpError,
              onCompleted: (code) => _verify(code),
            ),
            const SizedBox(height: AppSpacing.lg),
            AppButton(
              label: l.verifySubmit,
              loading: loading,
              onPressed: () {
                if (_otp.text.length == 6) _verify(_otp.text);
              },
            ),
            const SizedBox(height: AppSpacing.sm),
            AppTextButton(
              label: canResend ? l.resend : l.resendIn(_secondsLeft),
              onPressed: canResend ? _resend : null,
            ),
          ],
        ),
      ),
    );
  }
}
