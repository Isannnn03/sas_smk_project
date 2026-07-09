import 'package:flutter/material.dart';
import '../../models/attendance_session_model.dart';
import '../../services/attendance_service.dart';

class AttendanceProvider with ChangeNotifier {
  final AttendanceService _attendanceService = AttendanceService();

  int? _activeSessionId;
  AttendanceSessionModel? _sessionStatus;
  List<AttendanceRecordModel> _attendanceRecords = [];
  bool _isLoading = false;
  String? _error;

  int? get activeSessionId => _activeSessionId;
  AttendanceSessionModel? get sessionStatus => _sessionStatus;
  List<AttendanceRecordModel> get attendanceRecords => _attendanceRecords;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<bool> startSession(int scheduleId) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final sessionId = await _attendanceService.startSession(scheduleId);
      _activeSessionId = sessionId;
      await fetchSessionStatus(sessionId);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> fetchSessionStatus(int sessionId) async {
    try {
      final data = await _attendanceService.getSessionStatus(sessionId);
      
      _sessionStatus = AttendanceSessionModel.fromJson(data['session']);
      
      final List<dynamic> detailsJson = data['attendances'];
      _attendanceRecords = detailsJson.map((json) => AttendanceRecordModel.fromJson(json)).toList();
      
      notifyListeners();
    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
      notifyListeners();
    }
  }

  Future<String?> scanQr(String qrCode) async {
    if (_activeSessionId == null) return "Tidak ada sesi aktif";
    
    try {
      final message = await _attendanceService.scanQr(_activeSessionId!, qrCode);
      // Await refresh to prevent race condition between consecutive scans
      await fetchSessionStatus(_activeSessionId!);
      return message; // Success message
    } catch (e) {
      throw Exception(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<bool> closeSession() async {
    if (_activeSessionId == null) return false;

    _isLoading = true;
    notifyListeners();

    try {
      await _attendanceService.closeSession(_activeSessionId!);
      _activeSessionId = null;
      _sessionStatus = null;
      _attendanceRecords = [];
      return true;
    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> updateStatus(int attendanceId, String status) async {
    try {
      await _attendanceService.updateStatus(attendanceId, status);
      // Refresh local list
      if (_activeSessionId != null) {
        await fetchSessionStatus(_activeSessionId!);
      }
      return true;
    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
      notifyListeners();
      return false;
    }
  }
}
