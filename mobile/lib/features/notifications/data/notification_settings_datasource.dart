import '../../../core/network/api_client.dart';

/// O13 — one switchable notification and whether its push reaches the phone. Off = no push; the in-app
/// inbox still records it. Which templates are switchable is the server's call (visitor opens, announcements).
class NotificationSetting {
  const NotificationSetting({required this.templateKey, required this.pushEnabled});

  factory NotificationSetting.fromJson(Map<String, dynamic> json) => NotificationSetting(
    templateKey: json['template_key'] as String,
    pushEnabled: json['push_enabled'] as bool,
  );

  final String templateKey;
  final bool pushEnabled;

  NotificationSetting copyWith({bool? pushEnabled}) =>
      NotificationSetting(templateKey: templateKey, pushEnabled: pushEnabled ?? this.pushEnabled);
}

/// GET / PUT /v1/me/notification-settings (openapi getNotificationSettings / updateNotificationSetting).
class NotificationSettingsDataSource {
  NotificationSettingsDataSource(this._api);

  final ApiClient _api;

  Future<List<NotificationSetting>> load() async =>
      _parse((await _api.get('/v1/me/notification-settings')).data);

  Future<List<NotificationSetting>> setPush(String templateKey, bool enabled) async => _parse(
    (await _api.put(
      '/v1/me/notification-settings',
      data: {'template_key': templateKey, 'push_enabled': enabled},
    )).data,
  );

  static List<NotificationSetting> _parse(dynamic body) =>
      ((body as Map<String, dynamic>)['data'] as List<dynamic>)
          .map((e) => NotificationSetting.fromJson(e as Map<String, dynamic>))
          .toList();
}
