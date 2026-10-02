import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/error/failure.dart';
import '../../../design_system/components/app_components.dart';
import '../../../design_system/components/app_inputs.dart';
import '../../../design_system/components/data_components.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/auth_providers.dart';
import '../../auth/session_roles_provider.dart';
import '../../devices/devices_providers.dart';
import '../../home/home_providers.dart';
import '../complex_providers.dart';
import '../domain/complex_entities.dart';
import 'complex_failure.dart';

/// InviteLandingScreen (IMPLEMENTATION_PLAN §9 / §21 / B16). Opened from an invitation link: the token is
/// read from [PendingInviteStore] — it never travels in the route, a provider key or a log line.
///
/// Guest → invitation card + "register" (email locked to the invitation server-side) / "log in". Signed in →
/// accept / decline. Accept: `complex_resident` → complex membership only (devices are subscribed to one by
/// one later, B7); `family_member` → the head-granted device + the member's own pending `additional`
/// subscription (B8), payable from the pending-payments screen. [autoAccept] is set after an
/// invitation-driven registration: the user already chose to join, so the claim runs right away.
class InviteLandingScreen extends ConsumerStatefulWidget {
  const InviteLandingScreen({this.autoAccept = false, super.key});

  final bool autoAccept;

  @override
  ConsumerState<InviteLandingScreen> createState() =>
      _InviteLandingScreenState();
}

enum _Phase { loading, ready, gone, failed }

class _InviteLandingScreenState extends ConsumerState<InviteLandingScreen> {
  _Phase _phase = _Phase.loading;
  InvitePreview? _preview;
  Failure? _loadFailure;
  String? _actionError;
  String? _actionCode;
  bool _busy = false;
  bool _autoAccepted = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<String?> _token() => ref.read(pendingInviteStoreProvider).read();

  Future<void> _clearToken() async {
    await ref.read(pendingInviteStoreProvider).clear();
    ref.invalidate(hasPendingInviteProvider);
  }

  Future<void> _load() async {
    setState(() {
      _phase = _Phase.loading;
      _loadFailure = null;
    });
    final token = await _token();
    if (!mounted) return;
    if (token == null) {
      setState(() => _phase = _Phase.gone);
      return;
    }
    final result = await ref.read(inviteRepositoryProvider).preview(token);
    if (!mounted) return;
    await result.fold(
      (f) async {
        if (isTerminalInviteFailure(f)) {
          await _clearToken();
          if (mounted) setState(() => _phase = _Phase.gone);
        } else {
          setState(() {
            _phase = _Phase.failed;
            _loadFailure = f;
          });
        }
      },
      (preview) async {
        setState(() {
          _preview = preview;
          _phase = _Phase.ready;
        });
        if (widget.autoAccept &&
            !_autoAccepted &&
            ref.read(authStateProvider) == AuthState.authenticated) {
          _autoAccepted = true;
          await _accept();
        }
      },
    );
  }

  Future<void> _accept() async {
    final l = AppLocalizations.of(context);
    final token = await _token();
    if (!mounted) return;
    if (token == null) {
      setState(() => _phase = _Phase.gone);
      return;
    }
    setState(() {
      _busy = true;
      _actionError = null;
      _actionCode = null;
    });
    final result = await ref.read(inviteRepositoryProvider).accept(token);
    if (!mounted) return;
    setState(() => _busy = false);
    await result.fold(
      (f) async {
        if (f.code == 'invitation_invalid') {
          await _clearToken();
          if (mounted) setState(() => _phase = _Phase.gone);
          return;
        }
        if (isTerminalInviteFailure(f)) await _clearToken();
        if (mounted) {
          setState(() {
            _actionError = complexFailureMessage(l, f);
            _actionCode = f.code;
          });
        }
      },
      (accepted) async {
        await _clearToken();
        if (!mounted) return;
        ref
          ..invalidate(sessionRolesProvider)
          ..invalidate(myComplexesProvider)
          ..invalidate(pendingSubscriptionsProvider)
          ..invalidate(deviceListProvider)
          ..invalidate(homeProvider);
        final router = GoRouter.of(context);
        router.go('/home');
        if (accepted.kind == InviteKind.familyMember) {
          AppSnackBar.show(context, l.invAcceptedFamily);
          router.push('/payments/pending');
        } else {
          AppSnackBar.show(
            context,
            l.invAcceptedComplex(
              accepted.complexName ?? _preview?.complexName ?? '',
            ),
          );
          if (accepted.complexId != null) {
            router.push('/complex/${accepted.complexId}');
          }
        }
      },
    );
  }

  Future<void> _decline() async {
    final l = AppLocalizations.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l.invDeclineTitle),
        content: Text(l.invDeclineBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(l.cancel),
          ),
          TextButton(
            key: const Key('inv-decline-confirm'),
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(
              l.invDecline,
              style: const TextStyle(color: AppColors.danger),
            ),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    final token = await _token();
    if (token == null || !mounted) return;
    setState(() => _busy = true);
    final result = await ref.read(inviteRepositoryProvider).decline(token);
    if (!mounted) return;
    setState(() => _busy = false);
    await result.fold(
      (f) async {
        if (isTerminalInviteFailure(f)) await _clearToken();
        if (mounted) setState(() => _actionError = complexFailureMessage(l, f));
      },
      (_) async {
        await _clearToken();
        if (!mounted) return;
        AppSnackBar.show(context, l.invDeclined);
        context.go('/home');
      },
    );
  }

  Future<void> _close() async {
    await _clearToken();
    if (!mounted) return;
    context.go(
      ref.read(authStateProvider) == AuthState.authenticated
          ? '/home'
          : '/welcome',
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final authed = ref.watch(authStateProvider) == AuthState.authenticated;

    final Widget body = switch (_phase) {
      _Phase.loading => const Center(child: CircularProgressIndicator()),
      _Phase.failed => Center(
        child: ErrorStateView(
          message: _loadFailure == null
              ? l.errUnknown
              : complexFailureMessage(l, _loadFailure!),
          onRetry: _load,
        ),
      ),
      _Phase.gone => _Gone(onClose: _close),
      _Phase.ready => ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          _InviteCard(preview: _preview!),
          if (_actionError != null) ...[
            const SizedBox(height: AppSpacing.md),
            Text(
              _actionError!,
              key: const Key('inv-error'),
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ],
          const SizedBox(height: AppSpacing.lg),
          if (!authed) ...[
            AppButton(
              key: const Key('inv-register'),
              label: l.invRegister,
              icon: Icons.person_add_alt_1_outlined,
              onPressed: () =>
                  context.push('/auth/register?invite=1', extra: _preview),
            ),
            const SizedBox(height: AppSpacing.sm),
            AppSecondaryButton(
              label: l.invLogin,
              icon: Icons.login,
              onPressed: () => context.push('/auth/login'),
            ),
          ] else ...[
            AppButton(
              key: const Key('inv-accept'),
              label: l.invAccept,
              icon: Icons.check_circle_outline,
              loading: _busy,
              onPressed: _busy ? null : _accept,
            ),
            const SizedBox(height: AppSpacing.sm),
            AppSecondaryButton(
              label: l.invDecline,
              icon: Icons.close,
              onPressed: _busy ? null : _decline,
            ),
            if (_actionCode == 'invitation_email_mismatch') ...[
              const SizedBox(height: AppSpacing.sm),
              AppTextButton(
                label: l.logout,
                onPressed: () => ref.read(logoutUseCaseProvider).call(),
              ),
            ],
          ],
          const SizedBox(height: AppSpacing.sm),
          AppTextButton(label: l.invClose, onPressed: _close),
        ],
      ),
    };

    return AppScaffold(title: l.invTitle, body: body);
  }
}

class _InviteCard extends StatelessWidget {
  const _InviteCard({required this.preview});

  final InvitePreview preview;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;
    final isFamily = preview.kind == InviteKind.familyMember;
    final who = preview.inviterName ?? preview.complexName ?? '';

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: const BoxDecoration(
                  color: AppColors.brandContainer,
                  borderRadius: AppRadius.brMd,
                ),
                child: Icon(
                  isFamily ? Icons.family_restroom : Icons.apartment_outlined,
                  color: AppColors.brand,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    StatusBadge(
                      label: isFamily ? l.invFamilyKind : l.invComplexKind,
                      tone: BadgeTone.brand,
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      isFamily ? who : (preview.complexName ?? who),
                      style: text.titleMedium,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Text(
            isFamily
                ? l.invFamilyBody(who)
                : l.invComplexBody(preview.complexName ?? who),
            style: text.bodyMedium,
          ),
          if (preview.inviteeName.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(l.invFor(preview.inviteeName), style: text.bodySmall),
          ],
          if (preview.emailMasked != null) ...[
            const SizedBox(height: AppSpacing.xs),
            Text(l.invSentTo(preview.emailMasked!), style: text.bodySmall),
          ],
          if (preview.expiresAt != null) ...[
            const SizedBox(height: AppSpacing.xs),
            Text(
              l.invExpires(
                DateFormat(
                  'dd.MM.yyyy HH:mm',
                ).format(preview.expiresAt!.toLocal()),
              ),
              style: text.bodySmall,
            ),
          ],
        ],
      ),
    );
  }
}

class _Gone extends StatelessWidget {
  const _Gone({required this.onClose});

  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        const SizedBox(height: 80),
        const Icon(Icons.link_off, size: 56, color: AppColors.n400),
        const SizedBox(height: AppSpacing.md),
        Text(
          l.invGoneTitle,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          l.invGoneBody,
          key: const Key('inv-gone'),
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: AppSpacing.lg),
        AppButton(label: l.invClose, onPressed: onClose),
      ],
    );
  }
}
