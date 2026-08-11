import 'package:freezed_annotation/freezed_annotation.dart';

import '../../../core/error/failure.dart';

part 'home_data.freezed.dart';

/// Home dashboard snapshot (name/email/phone + counts + last activity).
@freezed
abstract class HomeData with _$HomeData {
  const factory HomeData({
    String? fullName,
    String? email,
    String? phone,
    @Default(0) int deviceCount,
    @Default(0) int subscriptionCount,
    DateTime? lastActivityAt,
  }) = _HomeData;
}

abstract class HomeRepository {
  Future<Result<HomeData>> load();
}
