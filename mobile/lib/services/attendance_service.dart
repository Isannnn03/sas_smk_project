import 'package:dio/dio.dart';
import '../core/network/dio_client.dart';

class AttendanceService {
  final Dio _dio = DioClient.instance.dio;

  Future<int> startSession(int scheduleId, {String? date}) async {
    try {
      final Map<String, dynamic> data = {'schedule_id': scheduleId};
      if (date != null) {
        data['date'] = date;
      }
      final response = await _dio.post('/attendance.php?action=start_session', data: data);

      if (response.data['success'] == true) {
        return response.data['data']['session_id'];
      } else {
        throw Exception(response.data['message'] ?? 'Failed to start session');
      }
    } on DioException catch (e) {
      if (e.response != null && e.response?.data != null) {
        throw Exception(e.response?.data['message'] ?? 'Failed to start session');
      }
      throw Exception('Network error: ${e.message}');
    }
  }

  Future<String> scanQr(int sessionId, String qrCode) async {
    try {
      final response = await _dio.post('/attendance.php?action=scan_qr', data: {
        'session_id': sessionId,
        'qr_code': qrCode,
      });

      if (response.data['success'] == true) {
        return response.data['message'] ?? 'Scan success';
      } else {
        throw Exception(response.data['message'] ?? 'Scan failed');
      }
    } on DioException catch (e) {
      if (e.response != null && e.response?.data != null) {
        throw Exception(e.response?.data['message'] ?? 'Scan failed');
      }
      throw Exception('Network error: ${e.message}');
    }
  }

  Future<Map<String, dynamic>> closeSession(int sessionId) async {
    try {
      final response = await _dio.post('/attendance.php?action=close_session', data: {
        'session_id': sessionId,
      });

      if (response.data['success'] == true) {
        return response.data['data'];
      } else {
        throw Exception(response.data['message'] ?? 'Failed to close session');
      }
    } on DioException catch (e) {
      if (e.response != null && e.response?.data != null) {
        throw Exception(e.response?.data['message'] ?? 'Failed to close session');
      }
      throw Exception('Network error: ${e.message}');
    }
  }

  Future<Map<String, dynamic>> getSessionStatus(int sessionId) async {
    try {
      final response = await _dio.get('/attendance.php?action=session_status&session_id=$sessionId');

      if (response.data['success'] == true) {
        return response.data['data'];
      } else {
        throw Exception(response.data['message'] ?? 'Failed to get session status');
      }
    } on DioException catch (e) {
      if (e.response != null && e.response?.data != null) {
        throw Exception(e.response?.data['message'] ?? 'Failed to get session status');
      }
      throw Exception('Network error: ${e.message}');
    }
  }

  Future<void> updateStatus(int attendanceId, String status) async {
    try {
      final response = await _dio.put('/attendance.php?action=update_status', data: {
        'attendance_id': attendanceId,
        'status': status,
      });

      if (response.data['success'] != true) {
         throw Exception(response.data['message'] ?? 'Failed to update status');
      }
    } on DioException catch (e) {
      if (e.response != null && e.response?.data != null) {
        throw Exception(e.response?.data['message'] ?? 'Failed to update status');
      }
      throw Exception('Network error: ${e.message}');
    }
  }
}
