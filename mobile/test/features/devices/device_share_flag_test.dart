import 'package:flutter_test/flutter_test.dart';
import 'package:salam_mobile/features/devices/data/dto/device_dto.dart';
import 'package:salam_mobile/features/devices/data/mapper/device_mappers.dart';

/// A4 — the server's visitor-link share flag, with the old-server fallback (share when the caller can open).
void main() {
  test('takes the server flag', () {
    final d = deviceDtoToEntity(DeviceDto.fromJson({'id': 1, 'can_open': true, 'via_family': true, 'can_share_visitor_link': false}));
    expect(d.viaFamily, isTrue);
    expect(d.canShareVisitorLink, isFalse);
  });

  test('falls back to can_open when an older server sends no flag', () {
    expect(deviceDtoToEntity(DeviceDto.fromJson({'id': 1, 'can_open': true})).canShareVisitorLink, isTrue);
    expect(deviceDtoToEntity(DeviceDto.fromJson({'id': 1, 'can_open': false})).canShareVisitorLink, isFalse);
  });
}
