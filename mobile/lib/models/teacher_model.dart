class TeacherModel {
  final int id;
  final String nip;
  final String name;
  final String email;
  final String phone;
  final String status;

  TeacherModel({
    required this.id,
    required this.nip,
    required this.name,
    required this.email,
    required this.phone,
    required this.status,
  });

  factory TeacherModel.fromJson(Map<String, dynamic> json) {
    return TeacherModel(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      nip: json['nip'] ?? '',
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      phone: json['phone'] ?? '',
      status: json['status'] ?? '',
    );
  }
}
