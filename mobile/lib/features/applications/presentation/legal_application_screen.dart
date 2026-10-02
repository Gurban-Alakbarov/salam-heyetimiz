import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure.dart';
import '../../../design_system/components/app_components.dart';
import '../../../design_system/components/app_inputs.dart';
import '../../../design_system/tokens/tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/validators/auth_validators.dart';
import '../../auth/auth_providers.dart';
import '../applications_providers.dart';
import '../domain/application_entities.dart';
import 'application_failure.dart';
import 'osm_location_picker.dart';

/// Legal entity (residential complex / building) application — IMPLEMENTATION_PLAN §11 / B14.
/// Submitting never creates a complex: an admin reviews it (B9/B11). Contact person fields are prefilled
/// from the signed-in profile (the registering account is the contact person).
class LegalApplicationScreen extends ConsumerStatefulWidget {
  const LegalApplicationScreen({this.onboarding = false, super.key});

  final bool onboarding;

  @override
  ConsumerState<LegalApplicationScreen> createState() => _LegalApplicationScreenState();
}

class _LegalApplicationScreenState extends ConsumerState<LegalApplicationScreen> {
  final _complexName = TextEditingController();
  final _legalName = TextEditingController();
  final _voen = TextEditingController();
  final _legalAddress = TextEditingController();
  final _contactName = TextEditingController();
  final _contactPhone = TextEditingController(text: '+994');
  final _contactEmail = TextEditingController();
  final _address = TextEditingController();
  final _apartments = TextEditingController();
  final _note = TextEditingController();
  GeoPoint? _location;
  Map<String, String?> _errors = {};
  bool _busy = false;
  bool _prefilled = false;

  List<TextEditingController> get _all =>
      [_complexName, _legalName, _voen, _legalAddress, _contactName, _contactPhone, _contactEmail, _address, _apartments, _note];

  @override
  void dispose() {
    for (final c in _all) {
      c.dispose();
    }
    super.dispose();
  }

  void _prefill() {
    if (_prefilled) return;
    final me = ref.read(currentUserProvider).value?.user;
    if (me == null) return;
    _prefilled = true;
    _contactName.text = me.fullName ?? '';
    _contactPhone.text = me.phone;
    _contactEmail.text = me.email ?? '';
  }

  bool _validate(AppLocalizations l) {
    String? req(TextEditingController c) => AuthValidators.isNotBlank(c.text) ? null : l.vRequired;
    final e = <String, String?>{
      'complex_name': req(_complexName),
      'legal_name': req(_legalName),
      'voen': ApplicationValidators.isVoen(_voen.text) ? null : l.appVoenInvalid,
      'legal_address': req(_legalAddress),
      'contact_person_name': req(_contactName),
      'contact_phone': AuthValidators.isAzPhone(_contactPhone.text) ? null : l.vPhone,
      'contact_email': AuthValidators.isEmail(_contactEmail.text) ? null : l.vEmail,
      'address': req(_address),
      'apartments_count': ApplicationValidators.isApartmentsCount(_apartments.text) ? null : l.appApartmentsInvalid,
      'location': _location == null ? l.appLocationRequired : (_location!.inServiceArea ? null : l.appLocationOutside),
    };
    setState(() => _errors = e);
    return e.values.every((v) => v == null);
  }

  Future<void> _submit() async {
    final l = AppLocalizations.of(context);
    if (!_validate(l)) return;
    setState(() => _busy = true);
    final result = await ref.read(applicationsRepositoryProvider).submitLegal(
          complexName: _complexName.text.trim(),
          legalName: _legalName.text.trim(),
          voen: _voen.text.trim(),
          legalAddress: _legalAddress.text.trim(),
          contactPersonName: _contactName.text.trim(),
          contactPhone: _contactPhone.text.trim(),
          contactEmail: _contactEmail.text.trim(),
          address: _address.text.trim(),
          location: _location!,
          apartmentsCount: int.tryParse(_apartments.text.trim()),
          note: _note.text.trim(),
        );
    if (!mounted) return;
    setState(() => _busy = false);
    result.fold((failure) {
      if (failure is ValidationFailure) {
        const keys = ['complex_name', 'legal_name', 'voen', 'legal_address', 'contact_person_name', 'contact_phone', 'contact_email', 'address', 'apartments_count', 'note'];
        setState(() => _errors = {
              for (final f in keys) f: failure.firstFor(f),
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

  Widget _field(String key, String label, TextEditingController c, {IconData? icon, TextInputType? keyboard, List<TextInputFormatter>? formatters}) =>
      Padding(
        padding: const EdgeInsets.only(bottom: AppSpacing.md),
        child: AppTextField(label: label, controller: c, errorText: _errors[key], prefixIcon: icon, keyboardType: keyboard, inputFormatters: formatters),
      );

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    ref.watch(currentUserProvider);
    _prefill();
    final digits = [FilteringTextInputFormatter.digitsOnly];

    return AppScaffold(
      title: l.appLegalTitle,
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l.appLegalIntro, style: Theme.of(context).textTheme.bodyMedium),
            const SizedBox(height: AppSpacing.lg),
            _field('complex_name', l.appComplexName, _complexName, icon: Icons.apartment_outlined),
            _field('legal_name', l.appLegalName, _legalName, icon: Icons.business_outlined),
            _field('voen', l.appVoen, _voen, icon: Icons.badge_outlined, keyboard: TextInputType.number,
                formatters: [...digits, LengthLimitingTextInputFormatter(10)]),
            _field('legal_address', l.appLegalAddress, _legalAddress, icon: Icons.location_city_outlined),
            _field('contact_person_name', l.appContactName, _contactName, icon: Icons.person_outline),
            _field('contact_phone', l.appPhone, _contactPhone, icon: Icons.phone_outlined, keyboard: TextInputType.phone),
            _field('contact_email', l.appEmail, _contactEmail, icon: Icons.email_outlined, keyboard: TextInputType.emailAddress),
            _field('address', l.appComplexAddress, _address, icon: Icons.home_outlined),
            OsmLocationPicker(value: _location, errorText: _errors['location'], onChanged: (p) => setState(() => _location = p)),
            const SizedBox(height: AppSpacing.md),
            _field('apartments_count', l.appApartments, _apartments, icon: Icons.door_front_door_outlined, keyboard: TextInputType.number, formatters: digits),
            _field('note', l.appNote, _note, icon: Icons.notes_outlined),
            const SizedBox(height: AppSpacing.sm),
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
