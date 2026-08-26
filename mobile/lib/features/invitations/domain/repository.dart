import '../../../core/error/failure.dart';
import 'entity/invitation_entities.dart';

abstract class InvitationRepository {
  /// The caller's own invitations. [status] is the server-side filter
  /// (active | used | expired | revoked); null lists all.
  Future<Result<List<Invitation>>> list({String? status});
}
