import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import 'data/datasource/invitation_remote_datasource.dart';
import 'data/repository_impl.dart';
import 'domain/entity/invitation_entities.dart';
import 'domain/repository.dart';

/// The status tabs on the Invitations screen. [query] is the server-side `?status=` value
/// (null = all → no filter). `used` maps to the backend's `used_up` derived status.
enum InvitationFilter { all, active, used, expired, revoked }

extension InvitationFilterQuery on InvitationFilter {
  String? get query => switch (this) {
    InvitationFilter.all => null,
    InvitationFilter.active => 'active',
    InvitationFilter.used => 'used',
    InvitationFilter.expired => 'expired',
    InvitationFilter.revoked => 'revoked',
  };
}

final invitationRepositoryProvider = Provider<InvitationRepository>(
  (ref) => InvitationRepositoryImpl(
    InvitationRemoteDataSource(ref.watch(apiClientProvider)),
  ),
);

/// The currently selected status tab. Uses the project's Notifier pattern (mirrors
/// HomeTabController) — StateProvider is not part of this Riverpod setup.
class InvitationFilterController extends Notifier<InvitationFilter> {
  @override
  InvitationFilter build() => InvitationFilter.all;

  void select(InvitationFilter filter) => state = filter;
}

final invitationFilterProvider =
    NotifierProvider<InvitationFilterController, InvitationFilter>(
      InvitationFilterController.new,
    );

/// The caller's invitations for one status filter (server-side filtered). Keyed by the filter
/// so each tab caches independently. Pull-to-refresh via `ref.refresh(...future)`.
final invitationsProvider = FutureProvider.autoDispose
    .family<List<Invitation>, InvitationFilter>((ref, filter) async {
      final result = await ref
          .watch(invitationRepositoryProvider)
          .list(status: filter.query);
      return result.fold((failure) => throw failure, (list) => list);
    });
