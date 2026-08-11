import '../../../core/error/failure.dart';
import 'entity/notification_entities.dart';

/// Inbox repository contract. Reads return a [NotificationPage]; mutations return `void` on success.
abstract class NotificationRepository {
  Future<Result<NotificationPage>> load({String? cursor});

  Future<Result<void>> markRead(int id);

  Future<Result<void>> markAllRead();
}
