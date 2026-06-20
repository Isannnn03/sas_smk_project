import 'package:flutter/material.dart';
import '../../models/schedule_model.dart';
import '../../services/schedule_service.dart';
import '../core/constants/app_constants.dart';

class ScheduleProvider with ChangeNotifier {
  final ScheduleService _scheduleService = ScheduleService();

  List<ScheduleModel> _allSchedules = [];
  List<ScheduleModel> _todaySchedules = [];
  bool _isLoading = false;
  String? _error;

  List<ScheduleModel> get todaySchedules => _todaySchedules;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> fetchTeacherSchedules(int teacherId) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _allSchedules = await _scheduleService.getTeacherSchedules(teacherId);
      _filterTodaySchedules();
    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  void _filterTodaySchedules() {
    // The backend already filters schedules to only return today's active schedules.
    // We just need to assign it directly and sort by time.
    _todaySchedules = List.from(_allSchedules);
    
    // Sort by start_time
    _todaySchedules.sort((a, b) => a.startTime.compareTo(b.startTime));
  }
}
