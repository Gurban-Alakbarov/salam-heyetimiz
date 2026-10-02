import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/core/deeplinks/deep_link.dart';
import 'package:salam_mobile/core/deeplinks/deep_link_service.dart';
import 'package:salam_mobile/core/storage/app_storage.dart';
import 'package:salam_mobile/features/payments/presentation/checkout_screen.dart';

const _token = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-abcd';

class _MemStore implements SecureStore {
  final Map<String, String> data = {};
  @override
  Future<String?> read(String key) async => data[key];
  @override
  Future<void> write(String key, String value) async => data[key] = value;
  @override
  Future<void> delete(String key) async => data.remove(key);
  @override
  Future<void> clear() async => data.clear();
}

void main() {
  group('DeepLinkParser — invitation app links (B6)', () {
    test('accepts https://salamheyetimiz.com/invite/{token} (and www.)', () {
      expect(DeepLinkParser.parse(Uri.parse('https://salamheyetimiz.com/invite/$_token')), const InviteLink(_token));
      expect(DeepLinkParser.parse(Uri.parse('https://www.salamheyetimiz.com/invite/$_token/')), const InviteLink(_token));
    });

    test('rejects foreign hosts, http, short/odd tokens and extra segments', () {
      for (final raw in [
        'https://evil.example/invite/$_token',
        'https://salamheyetimiz.com.evil.example/invite/$_token',
        'http://salamheyetimiz.com/invite/$_token',
        'https://salamheyetimiz.com/invite/short',
        'https://salamheyetimiz.com/invite/$_token/extra',
        'https://salamheyetimiz.com/invite/bad%20token%20value%20here',
        'https://salamheyetimiz.com/v/$_token',
      ]) {
        expect(DeepLinkParser.parse(Uri.parse(raw)), isA<UnknownLink>(), reason: raw);
      }
    });

    test('invite links are never routed (token stays out of navigation) and toString is redacted', () {
      expect(DeepLinkParser.routeFor(const InviteLink(_token)), isNull);
      expect(const InviteLink(_token).toString(), isNot(contains(_token)));
    });
  });

  group('DeepLinkParser — payment return (B1 salam://payment/return)', () {
    test('maps every backend outcome and the test flag', () {
      PaymentReturnLink p(String q) => DeepLinkParser.parse(Uri.parse('salam://payment/return?$q')) as PaymentReturnLink;
      expect(p('status=success&order=SH-202610-000001&test=1'),
          const PaymentReturnLink(status: PaymentReturnStatus.success, orderReference: 'SH-202610-000001', isTest: true));
      expect(p('status=failure&order=SH-1').status, PaymentReturnStatus.failure);
      expect(p('status=cancel&order=SH-1').status, PaymentReturnStatus.cancel);
      expect(p('status=pending&order=SH-1').status, PaymentReturnStatus.pending);
      expect(p('status=weird').status, PaymentReturnStatus.pending);
      expect(p('status=success&order=SH-1').isTest, isFalse);
    });

    test('drops a malformed order reference instead of trusting it', () {
      final link = DeepLinkParser.parse(Uri.parse('salam://payment/return?status=success&order=%3Cscript%3E')) as PaymentReturnLink;
      expect(link.orderReference, isNull);
    });

    test('other salam:// paths are unknown', () {
      expect(DeepLinkParser.parse(Uri.parse('salam://payment/other?status=success')), isA<UnknownLink>());
      expect(DeepLinkParser.parse(Uri.parse('salam://invite/$_token')), isA<UnknownLink>());
      expect(DeepLinkParser.parse(Uri.parse('salamwidget://foreground?deviceId=1')), isA<UnknownLink>());
    });

    test('routes to the result screen with only status / reference / test', () {
      final route = DeepLinkParser.routeFor(
          const PaymentReturnLink(status: PaymentReturnStatus.success, orderReference: 'SH-1', isTest: true));
      expect(route, '/payment/return?status=success&order=SH-1&test=1');
      expect(DeepLinkParser.routeFor(const PaymentReturnLink(status: PaymentReturnStatus.cancel)), '/payment/return?status=cancel');
    });
  });

  group('checkout WebView navigation policy', () {
    test('web pages load, the return hand-off finishes, everything else is blocked', () {
      expect(checkoutNavigation('https://api.salamheyetimiz.com/v1/payments/fake-checkout/SH-1?signature=x'), CheckoutNav.allow);
      expect(checkoutNavigation('http://10.0.2.2:8010/v1/payments/return?paymentId=KB-FAKE-1'), CheckoutNav.allow);
      expect(checkoutNavigation('salam://payment/return?status=success&order=SH-1&test=1'), CheckoutNav.finish);
      for (final raw in ['intent://scan/#Intent;scheme=zxing;end', 'tel:+994500000000', 'file:///etc/hosts', 'salam://other', 'javascript:alert(1)']) {
        expect(checkoutNavigation(raw), CheckoutNav.block, reason: raw);
      }
    });
  });

  group('PendingInviteStore', () {
    test('saves, reads and expires after the 7-day window', () async {
      var now = DateTime.utc(2026, 10, 2, 12);
      final store = PendingInviteStore(_MemStore(), now: () => now);
      expect(await store.read(), isNull);

      await store.save(_token);
      expect(await store.read(), _token);

      now = now.add(const Duration(days: 6, hours: 23));
      expect(await store.read(), _token);

      now = now.add(const Duration(hours: 2));
      expect(await store.read(), isNull, reason: 'past 7 days → cleared');
      expect(await store.read(), isNull);
    });

    test('clear removes the token', () async {
      final mem = _MemStore();
      final store = PendingInviteStore(mem);
      await store.save(_token);
      await store.clear();
      expect(mem.data, isEmpty);
    });
  });
}
