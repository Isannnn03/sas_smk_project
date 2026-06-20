class ScheduleModel {
  final int id;
  final String day;
  final String startTime;
  final String endTime;
  final int classId;
  final String className;
  final int teacherId;
  final String teacherName;
  final int subjectId;
  final String subjectName;

  ScheduleModel({
    required this.id,
    required this.day,
    required this.startTime,
    required this.endTime,
    required this.classId,
    required this.className,
    required this.teacherId,
    required this.teacherName,
    required this.subjectId,
    required this.subjectName,
  });

  factory ScheduleModel.fromJson(Map<String, dynamic> json) {
    return ScheduleModel(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      day: json['day'] ?? '',
      startTime: json['start_time'] ?? '',
      endTime: json['end_time'] ?? '',
      classId: json['class_id'] is int ? json['class_id'] : int.parse(json['class_id'].toString()),
      className: json['class_name'] ?? '',
      teacherId: json['teacher_id'] is int ? json['teacher_id'] : int.parse(json['teacher_id'].toString()),
      teacherName: json['teacher_name'] ?? '',
      subjectId: json['subject_id'] is int ? json['subject_id'] : int.parse(json['subject_id'].toString()),
      subjectName: json['subject_name'] ?? '',
    );
  }
}
