import 'package:dio/dio.dart';
import '../core/network/dio_client.dart';
import '../../models/user_model.dart';
import '../../models/teacher_model.dart';

class AuthService {
  final Dio _dio = DioClient.instance.dio;

  Future<UserModel> login(String username, String password) async {
    try {
      final response = await _dio.post('/auth.php?action=login', data: {
        'username': username,
        'password': password,
      });

      if (response.data['success'] == true) {
        final data = response.data['data'];
        final user = UserModel.fromJson(data['user']);
        return UserModel(
          id: user.id,
          username: user.username,
          role: user.role,
          token: data['token'],
        );
      } else {
        throw Exception(response.data['message'] ?? 'Login failed');
      }
    } on DioException catch (e) {
      if (e.response != null && e.response?.data != null) {
        throw Exception(e.response?.data['message'] ?? 'Login failed');
      }
      throw Exception('Network error: ${e.message}');
    }
  }

  Future<UserModel> getProfile() async {
    try {
      final response = await _dio.get('/auth.php?action=profile');

      if (response.data['success'] == true) {
        return UserModel.fromJson(response.data['data']);
      } else {
        throw Exception(response.data['message'] ?? 'Failed to get profile');
      }
    } on DioException catch (e) {
      if (e.response != null && e.response?.data != null) {
        throw Exception(e.response?.data['message'] ?? 'Failed to get profile');
      }
      throw Exception('Network error: ${e.message}');
    }
  }

  Future<TeacherModel> getTeacherDetail(int teacherId) async {
    try {
      final response = await _dio.get('/teachers.php?action=detail&id=$teacherId');

      if (response.data['success'] == true) {
        return TeacherModel.fromJson(response.data['data']);
      } else {
        throw Exception(response.data['message'] ?? 'Failed to get teacher detail');
      }
    } on DioException catch (e) {
      if (e.response != null && e.response?.data != null) {
        throw Exception(e.response?.data['message'] ?? 'Failed to get teacher detail');
      }
      throw Exception('Network error: ${e.message}');
    }
  }
}
