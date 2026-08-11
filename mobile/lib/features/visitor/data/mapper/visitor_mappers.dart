import '../../domain/entity/visitor_entities.dart';
import '../dto/visitor_dto.dart';

CreatedVisitorLink createVisitorLinkDtoToEntity(
  CreateVisitorLinkResponseDto d,
) {
  final accessType = VisitorAccessType.values.firstWhere(
    (t) => t.wire == d.link?.accessType,
    orElse: () => VisitorAccessType.oneTime,
  );

  return CreatedVisitorLink(
    url: d.url ?? '',
    token: d.token ?? '',
    accessType: accessType,
    visitorName: d.link?.visitorName,
    status: d.link?.status,
  );
}
