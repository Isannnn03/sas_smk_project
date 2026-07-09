class AttendanceSessionModel {
  final int id;
  final int scheduleId;
  final String date;
  final String status;
  final int totalHadir;
  final int totalBelumScan;

  AttendanceSessionModel({
    required this.id,
    required this.scheduleId,
    required this.date,
    required this.status,
    required this.totalHadir,
    required this.totalBelumScan,
  });

  factory AttendanceSessionModel.fromJson(Map<String, dynamic> json) {
    return AttendanceSessionModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      scheduleId: json['schedule_id'] is int ? json['schedule_id'] : int.tryParse(json['schedule_id']?.toString() ?? '0') ?? 0,
      date: json['date'] ?? '',
      status: json['status'] ?? 'aktif',
      totalHadir: json['total_hadir'] ?? 0,
      totalBelumScan: json['total_belum_scan'] ?? 0,
    );
  }
}

class AttendanceRecordModel {
  final int id;
  final int studentId;
  final String studentName;
  final String nis;
  final String status;
  final String? scanTime;

  AttendanceRecordModel({
    required this.id,
    required this.studentId,
    required this.studentName,
    required this.nis,
    required this.status,
    this.scanTime,
  });

  factory AttendanceRecordModel.fromJson(Map<String, dynamic> json) {
    return AttendanceRecordModel(
      id: json['attendance_id'] is int ? json['attendance_id'] : int.tryParse(json['attendance_id']?.toString() ?? '0') ?? 0,
      studentId: json['student_id'] is int ? json['student_id'] : int.tryParse(json['student_id']?.toString() ?? '0') ?? 0,
      studentName: json['student_name'] ?? '',
      nis: json['nis'] ?? '',
      status: json['attendance_status'] ?? 'belum_scan',
      scanTime: json['scan_time'],
    );
  }
}
