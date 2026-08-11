import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/error/failure.dart';
import 'package:salam_mobile/features/visitor/domain/entity/visitor_entities.dart';
import 'package:salam_mobile/features/visitor/visitor_providers.dart';

import '../../helpers/mocks.dart';

void main() {
  late MockVisitorRepository repo;

  setUpAll(() => registerFallbackValue(VisitorAccessType.oneTime));

  setUp(() => repo = MockVisitorRepository());

  ProviderContainer container() {
    final c = ProviderContainer(
      overrides: [visitorRepositoryProvider.overrideWithValue(repo)],
    );
    c.listen(visitorCreateProvider, (_, _) {}); // keep alive
    addTearDown(c.dispose);
    return c;
  }

  void stubCreate(Result<CreatedVisitorLink> result) {
    when(
      () => repo.create(
        any(),
        accessType: any(named: 'accessType'),
        durationMinutes: any(named: 'durationMinutes'),
        visitorName: any(named: 'visitorName'),
        purpose: any(named: 'purpose'),
      ),
    ).thenAnswer((_) async => result);
  }

  test('create success → VisitorCreated with the link', () async {
    stubCreate(
      const Success(
        CreatedVisitorLink(
          url: 'https://x/v/T',
          token: 'T',
          accessType: VisitorAccessType.oneTime,
        ),
      ),
    );

    final c = container();
    await c
        .read(visitorCreateProvider.notifier)
        .create(42, accessType: VisitorAccessType.oneTime);

    final state = c.read(visitorCreateProvider);
    expect(state, isA<VisitorCreated>());
    expect((state as VisitorCreated).link.url, 'https://x/v/T');
  });

  test('create failure → VisitorCreateFailed', () async {
    stubCreate(const Err(ForbiddenFailure('İcazə yoxdur', 'subscription_required')));

    final c = container();
    await c
        .read(visitorCreateProvider.notifier)
        .create(42, accessType: VisitorAccessType.oneTime);

    final state = c.read(visitorCreateProvider);
    expect(state, isA<VisitorCreateFailed>());
    expect((state as VisitorCreateFailed).failure.code, 'subscription_required');
  });

  test('double submit sends exactly one create', () async {
    stubCreate(
      const Success(
        CreatedVisitorLink(
          url: 'u',
          token: 't',
          accessType: VisitorAccessType.oneTime,
        ),
      ),
    );

    final c = container();
    final n = c.read(visitorCreateProvider.notifier);
    await Future.wait([
      n.create(42, accessType: VisitorAccessType.oneTime),
      n.create(42, accessType: VisitorAccessType.oneTime),
    ]);

    verify(
      () => repo.create(
        any(),
        accessType: any(named: 'accessType'),
        durationMinutes: any(named: 'durationMinutes'),
        visitorName: any(named: 'visitorName'),
        purpose: any(named: 'purpose'),
      ),
    ).called(1);
  });

  test('reset returns to idle', () async {
    stubCreate(
      const Success(
        CreatedVisitorLink(
          url: 'u',
          token: 't',
          accessType: VisitorAccessType.oneTime,
        ),
      ),
    );

    final c = container();
    final n = c.read(visitorCreateProvider.notifier);
    await n.create(42, accessType: VisitorAccessType.oneTime);
    expect(c.read(visitorCreateProvider), isA<VisitorCreated>());

    n.reset();
    expect(c.read(visitorCreateProvider), isA<VisitorCreateIdle>());
  });
}
