import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/core/session/session_roles.dart';
import 'package:salam_mobile/features/payments/domain/payment_entities.dart';
import 'package:salam_mobile/features/payments/domain/payment_poller.dart';

PaymentOrder _order(String status, {bool isTest = false}) => PaymentOrder(
      id: 7,
      reference: 'SH-202610-000007',
      status: status,
      amountMinor: 1200,
      currency: 'AZN',
      isTest: isTest,
    );

void main() {
  group('PaymentOrder', () {
    test('parses the bare OrderResource and a {data:{}} wrapper; reads is_test + redirect', () {
      final json = {
        'id': 7, 'reference': 'SH-1', 'status': 'authorising', 'amount_minor': 1200, 'currency': 'AZN',
        'bank_redirect_url': 'https://x/fake-checkout/SH-1?signature=s', 'is_test': true,
      };
      for (final body in [json, {'data': json}]) {
        final o = PaymentOrder.fromJson(body);
        expect(o.id, 7);
        expect(o.isTest, isTrue);
        expect(o.redirectUrl, contains('fake-checkout'));
        expect(o.outcome, PaymentOutcome.pending);
      }
      expect(PaymentOrder.fromJson(json).toString(), isNot(contains('signature')));
    });

    test('maps every server order status to an outcome', () {
      const expected = {
        'paid': PaymentOutcome.success,
        'refunded': PaymentOutcome.success,
        'partially_refunded': PaymentOutcome.success,
        'failed': PaymentOutcome.failed,
        'cancelled': PaymentOutcome.cancelled,
        'expired': PaymentOutcome.expired,
        'pending': PaymentOutcome.pending,
        'authorising': PaymentOutcome.pending,
        'something_new': PaymentOutcome.pending,
      };
      expected.forEach((status, outcome) => expect(_order(status).outcome, outcome, reason: status));
      expect(PaymentOutcome.pending.isFinal, isFalse);
      expect(PaymentOutcome.success.isFinal, isTrue);
    });
  });

  group('PaymentPoller (2 s × 30 → recheck)', () {
    test('stops at the first final outcome', () async {
      final statuses = ['pending', 'authorising', 'paid', 'paid'];
      var calls = 0, rechecks = 0;
      final delays = <Duration>[];
      final poller = PaymentPoller(
        fetch: () async => _order(statuses[calls++]),
        recheck: () async { rechecks++; return _order('paid'); },
        delay: (d) async => delays.add(d),
      );
      final seen = await poller.run().map((o) => o.status).toList();
      expect(seen, ['pending', 'authorising', 'paid']);
      expect(rechecks, 0);
      expect(delays, everyElement(const Duration(seconds: 2)));
      expect(delays.length, 2);
    });

    test('still pending after maxAttempts → one server recheck, then stops', () async {
      var calls = 0, rechecks = 0;
      final poller = PaymentPoller(
        fetch: () async { calls++; return _order('authorising'); },
        recheck: () async { rechecks++; return _order('pending'); },
        maxAttempts: 30,
        delay: (_) async {},
      );
      final seen = await poller.run().toList();
      expect(calls, 30);
      expect(rechecks, 1);
      expect(seen.length, 31);
      expect(seen.last.outcome, PaymentOutcome.pending);
    });

    test('a recheck can settle the order (late bank answer)', () async {
      final poller = PaymentPoller(
        fetch: () async => _order('pending'),
        recheck: () async => _order('failed'),
        maxAttempts: 3,
        delay: (_) async {},
      );
      expect((await poller.run().last).outcome, PaymentOutcome.failed);
    });

    test('cancel stops polling without a recheck', () async {
      var rechecks = 0;
      late PaymentPoller poller;
      var calls = 0;
      poller = PaymentPoller(
        fetch: () async {
          if (++calls == 2) poller.cancel();
          return _order('pending');
        },
        recheck: () async { rechecks++; return _order('paid'); },
        delay: (_) async {},
      );
      await poller.run().toList();
      expect(calls, 2);
      expect(rechecks, 0);
    });

    test('a fetch failure surfaces as a stream error', () async {
      final poller = PaymentPoller(
        fetch: () async => throw StateError('network'),
        recheck: () async => _order('paid'),
        delay: (_) async {},
      );
      expect(poller.run().toList(), throwsA(isA<StateError>()));
    });
  });

  group('SessionRoles (/v1/me B5 contract) + role gate', () {
    test('parses resident / komendant / complexes; ignores unknown roles', () {
      final r = SessionRoles.fromMe({
        'roles': ['resident', 'komendant', 'superuser'],
        'komendant': {'complex': {'id': 3, 'name': 'Gənclik Park'}},
        'complexes': [{'id': 1, 'name': 'A'}, {'id': 3, 'name': 'B'}],
      });
      expect(r.roles, {'resident', 'komendant'});
      expect(r.isKomendant, isTrue);
      expect(r.komendantComplexId, 3);
      expect(r.komendantComplexName, 'Gənclik Park');
      expect(r.complexIds, [1, 3]);

      final plain = SessionRoles.fromMe({'roles': ['resident'], 'komendant': null, 'complexes': []});
      expect(plain.isResident, isTrue);
      expect(plain.isKomendant, isFalse);
      expect(plain.hasComplex, isFalse);

      // legacy /v1/me without the B5 keys → no roles (no crash)
      expect(SessionRoles.fromMe(const {}).roles, isEmpty);
      // a "komendant" role without a complex is not trusted
      expect(SessionRoles.fromMe({'roles': ['komendant']}).isKomendant, isFalse);
    });

    test('komendant area is gated; everything else passes through', () {
      const kom = SessionRoles(roles: {'resident', 'komendant'}, komendantComplexId: 3);
      const res = SessionRoles(roles: {'resident'});
      expect(roleRedirect('/komendant', kom), isNull);
      expect(roleRedirect('/komendant/residents', kom), isNull);
      expect(roleRedirect('/komendant', res), '/home');
      expect(roleRedirect('/komendant/invitations', null), '/home');
      expect(roleRedirect('/komendantx', res), isNull);
      expect(roleRedirect('/home', res), isNull);
      expect(roleRedirect('/checkout/7', res), isNull);
    });
  });
}
