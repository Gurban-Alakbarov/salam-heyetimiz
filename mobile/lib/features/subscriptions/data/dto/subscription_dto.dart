import 'package:freezed_annotation/freezed_annotation.dart';

part 'subscription_dto.freezed.dart';
part 'subscription_dto.g.dart';

/// Wire shape for a subscription list item (GET /v1/subscriptions →
/// SubscriptionResource). camelCase fields map to the snake_case JSON via the
/// project-wide json_serializable `field_rename: snake`. DTOs stay in the data layer.
@freezed
abstract class SubscriptionDto with _$SubscriptionDto {
  const factory SubscriptionDto({
    required int id,
    String? tier,
    String? status,
    String? startsAt,
    String? endsAt,
    int? daysRemaining,
    bool? autoRenew,
    int? deviceId,
    int? userId,
    int? termDays,
    int? priceMinor,
    String? currency,
  }) = _SubscriptionDto;

  factory SubscriptionDto.fromJson(Map<String, dynamic> json) =>
      _$SubscriptionDtoFromJson(json);
}
