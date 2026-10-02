import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/deeplinks/deep_link_service.dart';
import '../../core/di/providers.dart';
import '../../core/network/envelope.dart';
import '../../core/session/session_roles.dart';
import 'auth_providers.dart';

/// Roles of the signed-in user from GET /v1/me (B13). Re-evaluated whenever the auth state changes; guest
/// (no roles) when signed out or when the lookup fails — the server stays the authority for every action.
final sessionRolesProvider = FutureProvider<SessionRoles>((ref) async {
  if (ref.watch(authStateProvider) != AuthState.authenticated) return SessionRoles.guest;
  try {
    final res = await ref.read(apiClientProvider).get('/v1/me');
    return SessionRoles.fromMe(Envelope.data(res.data));
  } catch (_) {
    return SessionRoles.guest;
  }
});

final deepLinkServiceProvider = Provider<DeepLinkService>((ref) => DeepLinkService());

final pendingInviteStoreProvider = Provider<PendingInviteStore>(
  (ref) => PendingInviteStore(ref.watch(secureStoreProvider)),
);
