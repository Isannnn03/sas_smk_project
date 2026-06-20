import 'package:dio/dio.dart';
import '../core/network/dio_client.dart';
import '../../models/schedule_model.dart';

class ScheduleService {
  final Dio _dio = DioClient.instance.dio;

  Future<List<ScheduleModel>> getTeacherSchedules(int teacherId) async {
    try {
      final response = await _dio
          .get('/schedules.php?action=by_teacher&teacher_id=$teacherId');

      if (response.data['success'] == true) {
        final List<dynamic> schedulesJson = response.data['data']['schedules'];
        return schedulesJson
            .map((json) => ScheduleModel.fromJson(json))
            .toList();
      } else {
        throw Exception(response.data['message'] ?? 'Failed to get schedules');
      }
    } on DioException catch (e) {
      if (e.response != null && e.response?.data != null) {
        throw Exception(
            e.response?.data['message'] ?? 'Failed to get schedules');
      }
      throw Exception('Network error: ${e.message}');
    }
  }
}
