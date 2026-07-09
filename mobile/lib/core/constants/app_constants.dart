class AppConstants {
  // Use 10.0.2.2 to access localhost from Android Emulator
  // Or replace with your local IP (e.g. 192.168.1.x) for physical device
  static const String baseUrl = 'http://192.168.1.7/sas%20smk%20project/sas_smk_project/backend/api';

  static const int connectTimeout = 15000; // 15 seconds
  static const int receiveTimeout = 15000; // 15 seconds

  // List of days in Indonesian
  static const List<String> days = [
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu',
    'Minggu'
  ];
}
