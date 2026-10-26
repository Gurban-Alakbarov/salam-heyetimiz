import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/komendant/data/komendant_repository.dart';
import 'package:salam_mobile/features/komendant/domain/komendant_entities.dart';
import 'package:salam_mobile/features/komendant/komendant_providers.dart';
import 'package:salam_mobile/features/komendant/presentation/komendant_residents_screen.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';
import 'package:salam_mobile/l10n/app_localizations_az.dart';

/// O7 — the Komendant's family actions: remove a member, hand the family over, decide about the family when
/// removing its head.
class _MockRepo extends Mock implements KomendantRepository {}

const _head = KomendantResident(
  userId: 7,
  fullName: 'Rauf Başçı',
  activeSubscriptions: 1,
  isFamilyHead: true,
  family: [
    KomendantFamilyMember(userId: 8, fullName: 'Aysel Üzv', activeSubscriptions: 1),
    KomendantFamilyMember(userId: 9, fullName: 'Kamran Üzv'),
  ],
);

Widget _app(_MockRepo repo) => ProviderScope(
  overrides: [
    komendantRepositoryProvider.overrideWithValue(repo),
    komendantResidentsProvider.overrideWith((ref) async => [_head]),
    komendantComplexProvider.overrideWith((ref) async => const KomendantComplex(id: 1, name: 'P')),
  ],
  child: MaterialApp(
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: AppLocalizations.supportedLocales,
    locale: const Locale('az'),
    home: const KomendantResidentsScreen(),
  ),
);

void main() {
  final l = AppLocalizationsAz();
  const removal = Success(ResidentRemoval(revokedRows: 1, cancelledSubscriptions: 1));

  test('parses a head with its family', () {
    final r = KomendantResident.fromJson({
      'user_id': 7,
      'full_name': 'Rauf',
      'is_family_head': true,
      'family': [
        {'user_id': 8, 'full_name': 'Aysel', 'active_subscriptions': 1},
      ],
    });
    expect(r.isFamilyHead, isTrue);
    expect(r.family.single.userId, 8);
    expect(r.family.single.activeSubscriptions, 1);
  });

  testWidgets('lists the family and removes one member on its own', (tester) async {
    final repo = _MockRepo();
    when(() => repo.removeResident(8)).thenAnswer((_) async => removal);
    await tester.pumpWidget(_app(repo));
    await tester.pumpAndSettle();

    expect(find.text('Aysel Üzv'), findsOneWidget);
    expect(find.text('Kamran Üzv'), findsOneWidget);

    await tester.tap(find.byKey(const Key('km-remove-member-8')));
    await tester.pumpAndSettle();
    expect(find.text(l.kmRemoveMemberBody('Aysel Üzv')), findsOneWidget);
    await tester.tap(find.byKey(const Key('km-remove-confirm')));
    await tester.pumpAndSettle();

    verify(() => repo.removeResident(8)).called(1);
  });

  testWidgets('removing the head asks about the family — a member takes over', (tester) async {
    final repo = _MockRepo();
    when(
      () => repo.removeResident(7, familyChoice: FamilyRemovalChoice.newHead, newHeadUserId: 9),
    ).thenAnswer((_) async => removal);
    await tester.pumpWidget(_app(repo));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('km-remove-7')));
    await tester.pumpAndSettle();
    expect(find.text(l.kmRemoveHeadBody('Rauf Başçı')), findsOneWidget);
    await tester.tap(find.byKey(const Key('km-new-head-9')));
    await tester.tap(find.byKey(const Key('km-family-confirm')));
    await tester.pumpAndSettle();

    verify(() => repo.removeResident(7, familyChoice: FamilyRemovalChoice.newHead, newHeadUserId: 9)).called(1);
  });

  testWidgets('removing the head can take the whole family with it', (tester) async {
    final repo = _MockRepo();
    when(() => repo.removeResident(7, familyChoice: FamilyRemovalChoice.removeFamily)).thenAnswer((_) async => removal);
    await tester.pumpWidget(_app(repo));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('km-remove-7')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('km-family-remove-all')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('km-new-head-8')), findsNothing);
    await tester.tap(find.byKey(const Key('km-family-confirm')));
    await tester.pumpAndSettle();

    verify(() => repo.removeResident(7, familyChoice: FamilyRemovalChoice.removeFamily)).called(1);
  });

  testWidgets('hands the family to a member; the old head stays unless chosen otherwise', (tester) async {
    final repo = _MockRepo();
    when(() => repo.changeFamilyHead(7, newHeadUserId: 8, removeOldHead: true)).thenAnswer((_) async => const Success(null));
    await tester.pumpWidget(_app(repo));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('km-change-head-7')));
    await tester.pumpAndSettle();
    expect(find.text(l.kmChangeHeadBody('Rauf Başçı')), findsOneWidget);
    await tester.tap(find.byKey(const Key('km-old-head-leaves')));
    await tester.tap(find.byKey(const Key('km-family-confirm')));
    await tester.pumpAndSettle();

    verify(() => repo.changeFamilyHead(7, newHeadUserId: 8, removeOldHead: true)).called(1);
    expect(find.text(l.kmHeadChanged), findsOneWidget);
  });
}
