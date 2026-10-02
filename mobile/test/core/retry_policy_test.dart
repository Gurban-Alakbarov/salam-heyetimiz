import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/core/error/retry_policy.dart';
import 'package:salam_mobile/features/applications/applications_providers.dart';
import 'package:salam_mobile/features/applications/data/applications_repository.dart';
import 'package:salam_mobile/features/applications/domain/application_entities.dart';
import 'package:salam_mobile/features/applications/presentation/applications_screen.dart';
import 'package:salam_mobile/l10n/app_localizations.dart';

class _MockAppsRepo extends Mock implements ApplicationsRepository {}

void main() {
  group('retryTransientFailures', () {
    test('definitive server answers are never retried', () {
      for (final f in <Failure>[
        const UnauthorizedFailure(),
        const ForbiddenFailure('x', 'not_komendant'),
        const NotFoundFailure('x'),
        const ConflictFailure('account_type_mismatch', 'x'),
        const ValidationFailure({}, 'x'),
        const RateLimitedFailure(30, 'x'),
      ]) {
        expect(
          retryTransientFailures(0, f),
          isNull,
          reason: f.runtimeType.toString(),
        );
      }
    });

    test('transient and non-Failure errors keep Riverpod\'s default retry', () {
      for (final e in <Object>[
        const NetworkFailure(),
        const TimeoutFailure(),
        const ServerFailure(),
        const UnknownFailure(),
        Exception('x'),
      ]) {
        expect(
          retryTransientFailures(0, e),
          ProviderContainer.defaultRetry(0, e),
          reason: e.toString(),
        );
        expect(retryTransientFailures(0, e), isNotNull);
      }
      expect(
        retryTransientFailures(10, const NetworkFailure()),
        isNull,
      ); // default cap
    });
  });

  testWidgets(
    '"Müraciətlərim": a 403 shows the error state at once instead of retrying behind a spinner',
    (tester) async {
      final repo = _MockAppsRepo();
      var calls = 0;
      when(() => repo.mine()).thenAnswer((_) async {
        calls++;
        return const Err<MyApplications>(
          ForbiddenFailure('İcazə yoxdur', 'forbidden'),
        );
      });

      await tester.pumpWidget(
        ProviderScope(
          overrides: [applicationsRepositoryProvider.overrideWithValue(repo)],
          child: const MaterialApp(
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            supportedLocales: AppLocalizations.supportedLocales,
            locale: Locale('az'),
            home: ApplicationsScreen(),
          ),
        ),
      );
      // pumpAndSettle would time out on a pending retry timer / spinning indicator.
      await tester.pumpAndSettle();

      expect(find.byType(CircularProgressIndicator), findsNothing);
      expect(find.text('İcazə yoxdur'), findsOneWidget);
      expect(calls, 1);
    },
  );
}
