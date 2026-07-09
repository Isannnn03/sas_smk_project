import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SecureStorage {
  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(
      encryptedSharedPreferences: true,
    ),
  );
  
  static const String _keyToken = 'auth_token';
  static const String _keyUserId = 'user_id';
  static const String _keyUserRole = 'user_role';

  // Token
  static Future<void> saveToken(String token) async {
    await _storage.write(key: _keyToken, value: token);
  }

  static Future<String?> getToken() async {
    return await _storage.read(key: _keyToken);
  }

  static Future<void> deleteToken() async {
    await _storage.delete(key: _keyToken);
  }

  // User details
  static Future<void> saveUserDetails(String userId, String role) async {
    await _storage.write(key: _keyUserId, value: userId);
    await _storage.write(key: _keyUserRole, value: role);
  }

  static Future<Map<String, String?>> getUserDetails() async {
    final userId = await _storage.read(key: _keyUserId);
    final role = await _storage.read(key: _keyUserRole);
    return {
      'user_id': userId,
      'role': role,
    };
  }

  static Future<void> clearAll() async {
    await _storage.deleteAll();
  }
}
