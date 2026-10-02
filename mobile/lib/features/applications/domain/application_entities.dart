import 'package:flutter/foundation.dart';

/// Registration applications (IMPLEMENTATION_PLAN §10–§11 / B14 over the B9 backend). Physical and legal
/// are separate models with separate status sets — never mixed.
enum AccountType { physical, legal }

AccountType? accountTypeFrom(Object? raw) => switch (raw) {
      'physical' => AccountType.physical,
      'legal' => AccountType.legal,
      _ => null,
    };

@immutable
class GeoPoint {
  const GeoPoint(this.latitude, this.longitude);
  final double latitude;
  final double longitude;

  /// Mirrors the backend service area (config/domain/applications.php — Azerbaijan box). The server
  /// stays authoritative; this only gives early feedback.
  static const double latMin = 38.3, latMax = 41.95, lngMin = 44.7, lngMax = 50.95;
  bool get inServiceArea => latitude >= latMin && latitude <= latMax && longitude >= lngMin && longitude <= lngMax;

  @override
  bool operator ==(Object other) => other is GeoPoint && other.latitude == latitude && other.longitude == longitude;
  @override
  int get hashCode => Object.hash(latitude, longitude);
}

GeoPoint? _point(Object? raw) {
  if (raw is! Map) return null;
  final lat = (raw['latitude'] as num?)?.toDouble();
  final lng = (raw['longitude'] as num?)?.toDouble();
  return lat == null || lng == null ? null : GeoPoint(lat, lng);
}

DateTime? _date(Object? raw) => raw is String ? DateTime.tryParse(raw) : null;

/// Physical (private yard) application: new → contacted → in_progress → installed | rejected.
@immutable
class IndividualApplication {
  const IndividualApplication({
    required this.id,
    required this.status,
    required this.address,
    this.location,
    this.note,
    this.rejectionReason,
    this.createdAt,
    this.statusChangedAt,
  });

  final int id;
  final String status;
  final String address;
  final GeoPoint? location;
  final String? note;
  final String? rejectionReason;
  final DateTime? createdAt;
  final DateTime? statusChangedAt;

  bool get isOpen => status == 'new' || status == 'contacted' || status == 'in_progress';

  factory IndividualApplication.fromJson(Map<String, dynamic> j) => IndividualApplication(
        id: (j['id'] as num).toInt(),
        status: (j['status'] ?? 'new').toString(),
        address: (j['address'] ?? '').toString(),
        location: _point(j['location']),
        note: j['note'] as String?,
        rejectionReason: j['rejection_reason'] as String?,
        createdAt: _date(j['created_at']),
        statusChangedAt: _date(j['status_changed_at']),
      );
}

/// Legal entity (residential complex / building) application: pending → approved | rejected.
@immutable
class LegalApplication {
  const LegalApplication({
    required this.id,
    required this.status,
    required this.complexName,
    required this.voen,
    required this.address,
    this.location,
    this.rejectionReason,
    this.createdAt,
    this.reviewedAt,
  });

  final int id;
  final String status;
  final String complexName;
  final String voen;
  final String address;
  final GeoPoint? location;
  final String? rejectionReason;
  final DateTime? createdAt;
  final DateTime? reviewedAt;

  factory LegalApplication.fromJson(Map<String, dynamic> j) => LegalApplication(
        id: (j['id'] as num).toInt(),
        status: (j['status'] ?? 'pending').toString(),
        complexName: (j['complex_name'] ?? '').toString(),
        voen: (j['voen'] ?? '').toString(),
        address: (j['address'] ?? '').toString(),
        location: _point(j['location']),
        rejectionReason: j['rejection_reason'] as String?,
        createdAt: _date(j['created_at']),
        reviewedAt: _date(j['reviewed_at']),
      );
}

/// GET /v1/applications/mine.
@immutable
class MyApplications {
  const MyApplications({this.accountType, this.individual = const [], this.legal = const []});

  final AccountType? accountType;
  final List<IndividualApplication> individual;
  final List<LegalApplication> legal;

  /// A typed account files only its own kind (server: 409 account_type_mismatch); legacy NULL may file either.
  bool get canFilePhysical => accountType != AccountType.legal && !individual.any((a) => a.isOpen);
  bool get canFileLegal => accountType != AccountType.physical;

  factory MyApplications.fromJson(Map<String, dynamic> j) {
    List<Map<String, dynamic>> rows(Object? raw) =>
        raw is List ? raw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : const [];
    return MyApplications(
      accountType: accountTypeFrom(j['account_type']),
      individual: rows(j['individual']).map(IndividualApplication.fromJson).toList(),
      legal: rows(j['legal']).map(LegalApplication.fromJson).toList(),
    );
  }
}

/// Client-side checks mirroring the backend rules (server is authoritative).
class ApplicationValidators {
  ApplicationValidators._();
  static final RegExp _voen = RegExp(r'^\d{10}$');
  static bool isVoen(String v) => _voen.hasMatch(v.trim());
  static bool isApartmentsCount(String v) {
    if (v.trim().isEmpty) return true; // optional
    final n = int.tryParse(v.trim());
    return n != null && n >= 1 && n <= 100000;
  }
}
