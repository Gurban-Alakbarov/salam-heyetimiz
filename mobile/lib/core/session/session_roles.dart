import 'package:flutter/foundation.dart';

/// Server-derived roles from GET /v1/me (B5 contract: `roles` = ["resident"] or ["resident","komendant"],
/// `komendant` = {complex:{id,name}} | null, `complexes` = [{id,name}]). UI gating only — every action
/// is authorised again by the server. Unknown role strings are ignored.
@immutable
class SessionRoles {
  const SessionRoles({this.roles = const {}, this.komendantComplexId, this.komendantComplexName, this.complexIds = const []});

  static const SessionRoles guest = SessionRoles();

  final Set<String> roles;
  final int? komendantComplexId;
  final String? komendantComplexName;
  final List<int> complexIds;

  bool get isResident => roles.contains('resident');
  bool get isKomendant => roles.contains('komendant') && komendantComplexId != null;
  bool get hasComplex => complexIds.isNotEmpty;

  factory SessionRoles.fromMe(Map<String, dynamic> data) {
    final rawRoles = data['roles'];
    final komendant = data['komendant'];
    final complex = komendant is Map ? komendant['complex'] : null;
    final complexes = data['complexes'];
    return SessionRoles(
      roles: rawRoles is List ? rawRoles.whereType<String>().where(_known.contains).toSet() : const {},
      komendantComplexId: complex is Map ? (complex['id'] as num?)?.toInt() : null,
      komendantComplexName: complex is Map ? complex['name'] as String? : null,
      complexIds: complexes is List
          ? complexes.whereType<Map>().map((c) => (c['id'] as num?)?.toInt()).whereType<int>().toList()
          : const [],
    );
  }

  static const Set<String> _known = {'resident', 'komendant'};
}

/// Role gate for the single router redirect: Komendant-only areas (`/komendant…`, screens land in B15)
/// bounce everyone else to Home. Returns the redirect target or null.
String? roleRedirect(String location, SessionRoles? roles) {
  if (location == '/komendant' || location.startsWith('/komendant/')) {
    if (roles == null || !roles.isKomendant) return '/home';
  }
  return null;
}
