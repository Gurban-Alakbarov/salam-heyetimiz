import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure.dart';
import '../../../design_system/components/app_components.dart';
import '../../../design_system/components/app_inputs.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/validators/auth_validators.dart';
import '../domain/family_entities.dart';
import '../family_providers.dart';
import 'family_failure.dart';

/// FamilyInviteScreen (BR-1 / §14 / B17): the head picks one of the devices they head a family on, then
/// first name, last name, email → POST /v1/devices/{id}/invitations (one invitation = one device, B8).
/// An already-active member gets the device at once (`granted`); anyone else receives the 7-day link and,
/// on accepting, their own pending `additional` subscription.
class FamilyInviteScreen extends ConsumerStatefulWidget {
  const FamilyInviteScreen({this.deviceId, super.key});

  final int? deviceId;

  @override
  ConsumerState<FamilyInviteScreen> createState() => _FamilyInviteScreenState();
}

class _FamilyInviteScreenState extends ConsumerState<FamilyInviteScreen> {
  final _first = TextEditingController();
  final _last = TextEditingController();
  final _email = TextEditingController();
  int? _deviceId;
  Map<String, String?> _errors = {};
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _deviceId = widget.deviceId;
    for (final (key, c) in [
      ('first_name', _first),
      ('last_name', _last),
      ('email', _email),
    ]) {
      c.addListener(() {
        if (_errors[key] != null) {
          setState(() => _errors = {..._errors, key: null});
        }
      });
    }
  }

  @override
  void dispose() {
    _first.dispose();
    _last.dispose();
    _email.dispose();
    super.dispose();
  }

  bool _validate(AppLocalizations l) {
    final e = <String, String?>{
      'device': _deviceId == null ? l.vRequired : null,
      'first_name': AuthValidators.isNotBlank(_first.text) ? null : l.vRequired,
      'last_name': AuthValidators.isNotBlank(_last.text) ? null : l.vRequired,
      'email': AuthValidators.isEmail(_email.text) ? null : l.vEmail,
    };
    setState(() => _errors = e);
    return e.values.every((v) => v == null);
  }

  Future<void> _submit() async {
    final l = AppLocalizations.of(context);
    if (!_validate(l)) return;
    setState(() => _busy = true);
    final result = await ref
        .read(familyRepositoryProvider)
        .invite(
          deviceId: _deviceId!,
          firstName: _first.text.trim(),
          lastName: _last.text.trim(),
          email: _email.text.trim(),
        );
    if (!mounted) return;
    setState(() => _busy = false);
    result.fold(
      (f) {
        if (f is ValidationFailure && f.fields.isNotEmpty) {
          setState(
            () => _errors = {
              for (final k in const ['first_name', 'last_name', 'email'])
                k: f.firstFor(k),
            },
          );
        } else {
          AppSnackBar.show(context, familyFailureMessage(l, f), isError: true);
        }
      },
      (res) {
        ref
          ..invalidate(managedDevicesProvider)
          ..invalidate(familyMembersProvider);
        AppSnackBar.show(context, res.granted ? l.famGranted : l.kmInviteSent);
        context.pop();
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final managed = ref.watch(managedDevicesProvider);

    return AppScaffold(
      title: l.famInvite,
      body: managed.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: ErrorStateView(
            message: e is Failure ? familyFailureMessage(l, e) : l.errUnknown,
            onRetry: () => ref.invalidate(managedDevicesProvider),
          ),
        ),
        data: (devices) {
          if (devices.isEmpty) {
            return Center(
              child: EmptyState(
                message: l.famNotHead,
                icon: Icons.family_restroom,
              ),
            );
          }
          if (_deviceId != null &&
              devices.every((d) => d.deviceId != _deviceId)) {
            _deviceId = null;
          }
          if (_deviceId == null && devices.length == 1) {
            _deviceId = devices.single.deviceId;
          }
          return SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  l.famInviteIntro,
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: AppSpacing.lg),
                _DevicePicker(
                  devices: devices,
                  value: _deviceId,
                  errorText: _errors['device'],
                  onChanged: (id) => setState(() {
                    _deviceId = id;
                    _errors = {..._errors, 'device': null};
                  }),
                ),
                const SizedBox(height: AppSpacing.md),
                AppTextField(
                  key: const Key('fam-first'),
                  label: l.kmFirstName,
                  controller: _first,
                  errorText: _errors['first_name'],
                  prefixIcon: Icons.person_outline,
                ),
                const SizedBox(height: AppSpacing.md),
                AppTextField(
                  key: const Key('fam-last'),
                  label: l.kmLastName,
                  controller: _last,
                  errorText: _errors['last_name'],
                  prefixIcon: Icons.person_outline,
                ),
                const SizedBox(height: AppSpacing.md),
                AppTextField(
                  key: const Key('fam-email'),
                  label: l.kmEmail,
                  controller: _email,
                  errorText: _errors['email'],
                  prefixIcon: Icons.email_outlined,
                  keyboardType: TextInputType.emailAddress,
                ),
                const SizedBox(height: AppSpacing.lg),
                AppButton(
                  key: const Key('fam-invite-send'),
                  label: l.kmInviteSend,
                  loading: _busy,
                  onPressed: _busy ? null : _submit,
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _DevicePicker extends StatelessWidget {
  const _DevicePicker({
    required this.devices,
    required this.value,
    required this.onChanged,
    this.errorText,
  });

  final List<ManagedDevice> devices;
  final int? value;
  final String? errorText;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return DropdownButtonFormField<int>(
      key: const Key('fam-device'),
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(
        labelText: l.famDeviceLabel,
        errorText: errorText,
        prefixIcon: const Icon(Icons.meeting_room_outlined),
      ),
      items: [
        for (final d in devices)
          DropdownMenuItem(
            value: d.deviceId,
            child: Text(d.label, overflow: TextOverflow.ellipsis),
          ),
      ],
      onChanged: onChanged,
    );
  }
}
