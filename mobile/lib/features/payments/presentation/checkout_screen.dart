import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../../../core/deeplinks/deep_link.dart';
import '../../../core/error/failure.dart';
import '../../../design_system/components/app_components.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/failure_message.dart';
import '../domain/payment_entities.dart';
import '../payments_providers.dart';
import 'test_payment_banner.dart';

/// Hosted checkout inside a WebView (IMPLEMENTATION_PLAN §16 / B13). The page is the order's
/// `bank_redirect_url` (BirPay, or the fake "TEST ÖDƏNİŞ" page) loaded from GET /v1/orders/{id} — it is
/// never carried in a route or logged. The bank → return page then redirects to
/// `salam://payment/return…`; that navigation is intercepted here and the app moves to the result screen,
/// which confirms the outcome with the server (the link itself is not trusted).
class CheckoutScreen extends ConsumerWidget {
  const CheckoutScreen({required this.orderId, super.key});

  final int orderId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final orderAsync = ref.watch(paymentOrderProvider(orderId));

    return AppScaffold(
      title: l.checkoutTitle,
      body: orderAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorStateView(
          message: e is Failure ? deviceFailureMessage(l, e) : l.errUnknown,
          onRetry: () => ref.invalidate(paymentOrderProvider(orderId)),
        ),
        data: (order) {
          final url = Uri.tryParse(order.redirectUrl ?? '');
          if (order.outcome.isFinal || url == null || !(url.isScheme('https') || url.isScheme('http'))) {
            // Already settled (or no hosted page) → straight to the server-confirmed result.
            WidgetsBinding.instance.addPostFrameCallback((_) {
              if (context.mounted) context.pushReplacement('/payment/return?orderId=${order.id}');
            });
            return const Center(child: CircularProgressIndicator());
          }
          return _CheckoutWebView(order: order, url: url);
        },
      ),
    );
  }
}

enum CheckoutNav { allow, finish, block }

/// Pure navigation policy for the checkout WebView (unit tested): web pages load; the
/// `salam://payment/return` hand-off finishes the checkout; every other scheme (intent:, tel:, file:,
/// other salam:// paths …) is blocked and never handed to the WebView or the OS.
CheckoutNav checkoutNavigation(String url) {
  final uri = Uri.tryParse(url);
  if (uri == null) return CheckoutNav.block;
  if (uri.isScheme(DeepLinkParser.customScheme)) {
    return DeepLinkParser.parse(uri) is PaymentReturnLink ? CheckoutNav.finish : CheckoutNav.block;
  }
  return uri.isScheme('https') || uri.isScheme('http') ? CheckoutNav.allow : CheckoutNav.block;
}

class _CheckoutWebView extends StatefulWidget {
  const _CheckoutWebView({required this.order, required this.url});

  final PaymentOrder order;
  final Uri url;

  @override
  State<_CheckoutWebView> createState() => _CheckoutWebViewState();
}

class _CheckoutWebViewState extends State<_CheckoutWebView> {
  late final WebViewController _controller;
  bool _loading = true;
  bool _finished = false;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: _onNavigation,
          onPageStarted: (_) => setState(() => _loading = true),
          onPageFinished: (_) => setState(() => _loading = false),
        ),
      )
      ..loadRequest(widget.url);
  }

  NavigationDecision _onNavigation(NavigationRequest request) {
    switch (checkoutNavigation(request.url)) {
      case CheckoutNav.finish:
        _finish();
        return NavigationDecision.prevent;
      case CheckoutNav.allow:
        return NavigationDecision.navigate;
      case CheckoutNav.block:
        return NavigationDecision.prevent;
    }
  }

  void _finish() {
    if (_finished || !mounted) return;
    _finished = true;
    context.pushReplacement('/payment/return?orderId=${widget.order.id}');
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return PopScope(
      canPop: false,
      // Leaving checkout early still confirms with the server (the user may already have paid).
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _finish();
      },
      child: Column(
        children: [
          if (widget.order.isTest) TestPaymentBanner(label: l.paymentTestBanner),
          if (_loading) const LinearProgressIndicator(minHeight: 2),
          Expanded(child: WebViewWidget(controller: _controller)),
        ],
      ),
    );
  }
}
