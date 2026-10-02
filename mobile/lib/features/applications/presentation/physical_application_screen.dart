import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure.dart';
import '../../../design_system/components/app_components.dart';
import '../../../design_system/components/app_inputs.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/validators/auth_validators.dart';
import '../../auth/auth_providers.dart';
import 'application_failure.dart';
import '../applications_providers.dart';
import '../domain/application_entities.dart';
import 'osm_location_picker.dart';

/// Physical person (private yard) application — IMPLEMENTATION_PLAN §10 / B14. Contact fields are
/// prefilled from the signed-in profile; address + OSM pin are required. [onboarding] = shown right after
/// registration: a "later" action skips to Home (decision B14).
class PhysicalApplicationScreen extends ConsumerStatefulWidget {
  const PhysicalApplicationScreen({this.onboarding = false, super.key});

  final bool onboarding;

  @override
  ConsumerState<PhysicalApplicationScreen> createState() => _PhysicalApplicationScreenState();
}

class _PhysicalApplicationScreenState extends ConsumerState<PhysicalApplicationScreen> {
  final _fullName = TextEditingController();
  final _phone = TextEditingController(text: '+994');
  final _email = TextEditingController();
  final _address = TextEditingController();
  final _note = TextEditingController();
  GeoPoint? _location;
  Map<String, String?> _errors = {};
  bool _busy = false;
  bool _prefilled = false;

  @override
  void dispose() {
    for (final c in [_fullName, _phone, _email, _address, _note]) {
      c.dispose();
    }
    super.dispose();
  }

  void _prefill() {
    if (_prefilled) return;
    final me = ref.read(currentUserProvider).value?.user;
    if (me == null) return;
    _prefilled = true;
    _fullName.text = me.fullName ?? '';
    _phone.text = me.phone;
    _email.text = me.email ?? '';
  }

  bool _validate(AppLocalizations l) {
    final e = <String, String?>{
      'full_name': AuthValidators.isNotBlank(_fullName.text) ? null : l.vRequired,
      'phone': AuthValidators.isAzPhone(_phone.text) ? null : l.vPhone,
      'email': AuthValidators.isEmail(_email.text) ? null : l.vEmail,
      'address': AuthValidators.isNotBlank(_address.text) ? null : l.vRequired,
      'location': _location == null ? l.appLocationRequired : (_location!.inServiceArea ? null : l.appLocationOutside),
    };
    setState(() => _errors = e);
    return e.values.every((v) => v == null);
  }

  Future<void> _submit() async {
    final l = AppLocalizations.of(context);
    if (!_validate(l)) return;
    setState(() => _busy = true);
    final result = await ref.read(applicationsRepositoryProvider).submitIndividual(
          fullName: _fullName.text.trim(),
          phone: _phone.text.trim(),
          email: _email.text.trim(),
          address: _address.text.trim(),
          location: _location!,
          note: _note.text.trim(),
        );
    if (!mounted) return;
    setState(() => _busy = false);
    result.fold((failure) {
      if (failure is ValidationFailure) {
        setState(() => _errors = {
              for (final f in ['full_name', 'phone', 'email', 'address', 'note']) f: failure.firstFor(f),
              'location': failure.firstFor('latitude') ?? failure.firstFor('longitude'),
            });
      } else {
        AppSnackBar.show(context, applicationFailureMessage(l, failure), isError: true);
      }
    }, (_) {
      ref.invalidate(myApplicationsProvider);
      AppSnackBar.show(context, l.appSubmitted);
      // Home underneath the status list, so Back leads home (the form may have been the only route).
      final router = GoRouter.of(context);
      router.go('/home');
      router.push('/applications');
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    ref.watch(currentUserProvider);
    _prefill();

    return AppScaffold(
      title: l.appPhysicalTitle,
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l.appPhysicalIntro, style: Theme.of(context).textTheme.bodyMedium),
            const SizedBox(height: AppSpacing.lg),
            AppTextField(label: l.appFullName, controller: _fullName, errorText: _errors['full_name'], prefixIcon: Icons.person_outline),
            const SizedBox(height: AppSpacing.md),
            AppTextField(label: l.appPhone, controller: _phone, errorText: _errors['phone'], keyboardType: TextInputType.phone, prefixIcon: Icons.phone_outlined),
            const SizedBox(height: AppSpacing.md),
            AppTextField(label: l.appEmail, controller: _email, errorText: _errors['email'], keyboardType: TextInputType.emailAddress, prefixIcon: Icons.email_outlined),
            const SizedBox(height: AppSpacing.md),
            AppTextField(label: l.appAddress, controller: _address, errorText: _errors['address'], prefixIcon: Icons.home_outlined),
            const SizedBox(height: AppSpacing.md),
            OsmLocationPicker(value: _location, errorText: _errors['location'], onChanged: (p) => setState(() => _location = p)),
            const SizedBox(height: AppSpacing.md),
            AppTextField(label: l.appNote, controller: _note, errorText: _errors['note'], prefixIcon: Icons.notes_outlined),
            const SizedBox(height: AppSpacing.lg),
            AppButton(label: l.appSubmit, loading: _busy, onPressed: _busy ? null : _submit),
            if (widget.onboarding) ...[
              const SizedBox(height: AppSpacing.sm),
              AppTextButton(label: l.appLater, onPressed: () => context.go('/home')),
            ],
          ],
        ),
      ),
    );
  }
}
