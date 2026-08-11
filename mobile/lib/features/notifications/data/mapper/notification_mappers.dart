import '../../domain/entity/notification_entities.dart';
import '../dto/notification_dto.dart';

DateTime? _date(String? value) =>
    value == null ? null : DateTime.tryParse(value);

/// Maps the wire DTO to the domain entity, lifting the server-rendered copy + deep-link data out of
/// `payload`. A missing/unknown payload degrades to empty strings/maps (never throws).
AppNotification notificationDtoToEntity(NotificationDto d) {
  final payload = d.payload ?? const <String, dynamic>{};
  final ids = payload['ids'];
  return AppNotification(
    id: d.id,
    type: (payload['type'] as String?) ?? '',
    title: (payload['title'] as String?) ?? '',
    body: (payload['body'] as String?) ?? '',
    ids: ids is Map ? Map<String, dynamic>.from(ids) : const <String, dynamic>{},
    isRead: d.status == 'read' || d.readAt != null,
    createdAt: _date(d.createdAt),
  );
}
