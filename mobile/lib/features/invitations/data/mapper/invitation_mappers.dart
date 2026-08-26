import '../../domain/entity/invitation_entities.dart';
import '../dto/invitation_dto.dart';

DateTime? _date(String? value) =>
    value == null ? null : DateTime.tryParse(value);

Invitation invitationDtoToEntity(InvitationDto d) => Invitation(
  id: d.id,
  visitorName: d.visitorName,
  purpose: d.purpose,
  accessType: d.accessType ?? 'time_limited',
  status: d.status ?? 'active',
  expiresAt: _date(d.expiresAt),
  maxUsage: d.maxUsage,
  usageCount: d.usageCount ?? 0,
  firstUsedAt: _date(d.firstUsedAt),
  lastUsedAt: _date(d.lastUsedAt),
  revokedAt: _date(d.revokedAt),
  createdAt: _date(d.createdAt),
);
