import 'package:flutter/foundation.dart';

import '../config/app_config.dart';

/// Incoming links the app understands (IMPLEMENTATION_PLAN §8 / B13). Anything
/// else is [UnknownLink] and ignored. Pure + side-effect free → unit tested.
///
/// Security: tokens and payment references are credentials / identifiers — they
/// are never logged; [toString] is redacted.
@immutable
sealed class DeepLink {
  const DeepLink();
}

/// `https://salamheyetimiz.com/invite/{token}` (B6 web landing / app link).
class InviteLink extends DeepLink {
  const InviteLink(this.token);
  final String token;

  @override
  bool operator ==(Object other) => other is InviteLink && other.token == token;
  @override
  int get hashCode => token.hashCode;
  @override
  String toString() => 'InviteLink(<redacted>)';
}

/// Normalised outcome of a hosted-checkout return.
enum PaymentReturnStatus { success, failure, cancel, pending }

/// `salam://payment/return?status=&order=<reference>&test=1` (B1 PaymentReturnController).
class PaymentReturnLink extends DeepLink {
  const PaymentReturnLink({required this.status, this.orderReference, this.isTest = false});
  final PaymentReturnStatus status;
  final String? orderReference;
  final bool isTest;

  @override
  bool operator ==(Object other) =>
      other is PaymentReturnLink && other.status == status && other.orderReference == orderReference && other.isTest == isTest;
  @override
  int get hashCode => Object.hash(status, orderReference, isTest);
  @override
  String toString() => 'PaymentReturnLink($status, test=$isTest)';
}

class UnknownLink extends DeepLink {
  const UnknownLink();
}

class DeepLinkParser {
  DeepLinkParser._();

  static const String customScheme = 'salam';
  static final RegExp _token = RegExp(r'^[A-Za-z0-9_-]{16,128}$');
  static final RegExp _reference = RegExp(r'^[A-Za-z0-9_-]{1,64}$');
  static final Set<String> _inviteHosts = {AppConfig.inviteLinkHost, 'www.${AppConfig.inviteLinkHost}'};

  static DeepLink parse(Uri uri) {
    final scheme = uri.scheme.toLowerCase();

    if (scheme == 'https' && _inviteHosts.contains(uri.host.toLowerCase())) {
      final seg = uri.pathSegments.where((s) => s.isNotEmpty).toList();
      if (seg.length == 2 && seg[0] == 'invite' && _token.hasMatch(seg[1])) {
        return InviteLink(seg[1]);
      }
      return const UnknownLink();
    }

    if (scheme == customScheme && uri.host == 'payment' && uri.path == '/return') {
      final ref = uri.queryParameters['order'];
      return PaymentReturnLink(
        status: _status(uri.queryParameters['status']),
        orderReference: ref != null && _reference.hasMatch(ref) ? ref : null,
        isTest: uri.queryParameters['test'] == '1',
      );
    }

    return const UnknownLink();
  }

  static PaymentReturnStatus _status(String? raw) => switch (raw) {
        'success' => PaymentReturnStatus.success,
        'failure' => PaymentReturnStatus.failure,
        'cancel' => PaymentReturnStatus.cancel,
        _ => PaymentReturnStatus.pending,
      };

  /// The in-app route for a link, or null when there is nothing to navigate to. An [InviteLink] is not
  /// routed: its token is kept as a pending invitation (PendingInviteStore) and claimed by the invite
  /// flow (B16) — the token never travels in a route or the navigation history.
  static String? routeFor(DeepLink link) => switch (link) {
        InviteLink() => null,
        PaymentReturnLink(:final status, :final orderReference, :final isTest) => Uri(
            path: '/payment/return',
            queryParameters: {
              'status': status.name,
              'order': ?orderReference,
              if (isTest) 'test': '1',
            },
          ).toString(),
        UnknownLink() => null,
      };
}
