/// How long a visitor link stays usable. Mirrors the backend `access_type`.
enum VisitorAccessType {
  oneTime('one_time'),
  timeLimited('time_limited');

  const VisitorAccessType(this.wire);

  /// Wire value sent to / received from the API.
  final String wire;
}

/// Optional, self-declared reason for a visitor link — reporting only (never
/// affects access). Mirrors the backend VisitorPurpose enum.
enum VisitorPurpose {
  guest('guest'),
  delivery('delivery'),
  courier('courier'),
  service('service'),
  cleaning('cleaning'),
  taxi('taxi'),
  other('other');

  const VisitorPurpose(this.wire);

  final String wire;
}

/// The result of creating a visitor link. `url` is the shareable link and `token`
/// is the plaintext secret — both are returned exactly once, at creation.
class CreatedVisitorLink {
  const CreatedVisitorLink({
    required this.url,
    required this.token,
    required this.accessType,
    this.visitorName,
    this.status,
  });

  final String url;
  final String token;
  final VisitorAccessType accessType;
  final String? visitorName;
  final String? status;
}
