import 'package:dio/dio.dart';
import 'package:mocktail/mocktail.dart';
import 'package:salam_mobile/core/analytics/analytics_service.dart';
import 'package:salam_mobile/core/crash/crash_reporter.dart';
import 'package:salam_mobile/core/location/location_service.dart';
import 'package:salam_mobile/core/session/session_manager.dart';
import 'package:salam_mobile/core/storage/app_storage.dart';
import 'package:salam_mobile/core/network/api_client.dart';
import 'package:salam_mobile/features/auth/data/datasource/auth_remote_datasource.dart';
import 'package:salam_mobile/features/auth/domain/repository.dart';
import 'package:salam_mobile/features/barrier/data/datasource/barrier_remote_datasource.dart';
import 'package:salam_mobile/features/barrier/domain/repository.dart';
import 'package:salam_mobile/features/devices/data/datasource/device_remote_datasource.dart';
import 'package:salam_mobile/features/devices/domain/repository.dart';
import 'package:salam_mobile/features/invitations/data/datasource/invitation_remote_datasource.dart';
import 'package:salam_mobile/features/notifications/data/datasource/notification_remote_datasource.dart';
import 'package:salam_mobile/features/notifications/domain/repository.dart';
import 'package:salam_mobile/features/subscriptions/data/datasource/subscription_remote_datasource.dart';
import 'package:salam_mobile/features/subscriptions/domain/repository.dart';
import 'package:salam_mobile/features/visitor/data/datasource/visitor_remote_datasource.dart';
import 'package:salam_mobile/features/visitor/domain/repository.dart';

class MockAuthRepository extends Mock implements AuthRepository {}

class MockAuthRemoteDataSource extends Mock implements AuthRemoteDataSource {}

class MockSessionManager extends Mock implements SessionManager {}

class MockSecureStore extends Mock implements SecureStore {}

class MockAnalyticsService extends Mock implements AnalyticsService {}

class MockCrashReporter extends Mock implements CrashReporter {}

class MockApiClient extends Mock implements ApiClient {}

class MockDeviceRemoteDataSource extends Mock
    implements DeviceRemoteDataSource {}

class MockDeviceRepository extends Mock implements DeviceRepository {}

class MockInvitationRemoteDataSource extends Mock
    implements InvitationRemoteDataSource {}

class MockSubscriptionRemoteDataSource extends Mock
    implements SubscriptionRemoteDataSource {}

class MockSubscriptionRepository extends Mock
    implements SubscriptionRepository {}

class MockBarrierRepository extends Mock implements BarrierRepository {}

class MockBarrierRemoteDataSource extends Mock
    implements BarrierRemoteDataSource {}

class MockLocationService extends Mock implements LocationService {}

class MockVisitorRepository extends Mock implements VisitorRepository {}

class MockVisitorRemoteDataSource extends Mock
    implements VisitorRemoteDataSource {}

class MockNotificationRemoteDataSource extends Mock
    implements NotificationRemoteDataSource {}

class MockNotificationRepository extends Mock implements NotificationRepository {}

/// Builds a Dio 4xx/5xx error with an optional JSON body + Retry-After header.
DioException dioError({
  required int status,
  Object? body,
  String? retryAfter,
  String path = '/v1/test',
}) {
  final headers = Headers();
  if (retryAfter != null) headers.add('retry-after', retryAfter);
  final options = RequestOptions(path: path);
  return DioException(
    requestOptions: options,
    type: DioExceptionType.badResponse,
    response: Response<dynamic>(
      requestOptions: options,
      statusCode: status,
      data: body,
      headers: headers,
    ),
  );
}

DioException dioTimeout() => DioException(
  requestOptions: RequestOptions(path: '/v1/test'),
  type: DioExceptionType.receiveTimeout,
);

DioException dioOffline() => DioException(
  requestOptions: RequestOptions(path: '/v1/test'),
  type: DioExceptionType.connectionError,
);

/// Registers fallback values for mocktail `any()` on custom types.
void registerCommonFallbacks() {
  registerFallbackValue(const Session(accessToken: 'a', refreshToken: 'r'));
  registerFallbackValue(
    const GeoFix(latitude: 0, longitude: 0, accuracy: 0),
  );
}
