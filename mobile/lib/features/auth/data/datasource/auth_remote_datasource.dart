import '../../../../core/network/api_client.dart';
import '../../../../core/network/envelope.dart';
import '../../../../core/services/device_info_service.dart';
import '../dto/bootstrap_dto.dart';
import '../dto/me_dto.dart';
import '../dto/token_dto.dart';

/// Thin remote layer: builds request bodies (exact backend field names), calls
/// [ApiClient], unwraps the envelope into DTOs. Lets DioException propagate —
/// the repository maps it to a [Failure]. This is the only place these paths
/// are spoken to.
class AuthRemoteDataSource {
  AuthRemoteDataSource(this._api, this._deviceInfo);

  final ApiClient _api;
  final DeviceInfoService _deviceInfo;

  Future<BootstrapDto> bootstrap() async {
    final res = await _api.get('/v1/bootstrap');
    return BootstrapDto.fromJson(Envelope.data(res.data));
  }

  Future<Map<String, dynamic>> register({
    required String firstName,
    required String lastName,
    required String phone,
    required String email,
  }) async {
    final res = await _api.post(
      '/v1/auth/register',
      data: {
        'first_name': firstName,
        'last_name': lastName,
        'phone': phone,
        'email': email,
      },
    );
    return Envelope.meta(res.data);
  }

  Future<Map<String, dynamic>> login({required String email}) async {
    final res = await _api.post('/v1/auth/login', data: {'email': email});
    return Envelope.meta(res.data);
  }

  Future<Map<String, dynamic>> resendOtp({required String email}) async {
    final res = await _api.post('/v1/auth/resend-otp', data: {'email': email});
    return Envelope.meta(res.data);
  }

  Future<TokenDto> verifyEmail({
    required String email,
    required String code,
  }) async {
    final device = await _deviceInfo.get();
    final res = await _api.post(
      '/v1/auth/verify-email',
      data: {
        'email': email,
        'code': code,
        'device': {
          'install_uuid': device.installUuid,
          'platform': device.platform,
          'os_version': device.osVersion,
          'app_version': device.appVersion,
          'device_model': device.deviceModel,
          'push_token': device.pushToken,
        },
      },
    );
    return TokenDto.fromJson(Envelope.data(res.data));
  }

  Future<MeDto> me() async {
    final res = await _api.get('/v1/me');
    return MeDto.fromJson(Envelope.data(res.data));
  }

  Future<void> logout() async {
    await _api.post('/v1/auth/logout');
  }
}
