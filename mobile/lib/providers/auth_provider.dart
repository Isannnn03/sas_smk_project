import 'package:flutter/material.dart';
import '../../models/user_model.dart';
import '../../services/auth_service.dart';
import '../core/storage/secure_storage.dart';

class AuthProvider with ChangeNotifier {
  final AuthService _authService = AuthService();

  UserModel? _user;
  bool _isLoading = false;
  String? _error;
  bool _isAuthenticated = false;

  UserModel? get user => _user;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isAuthenticated => _isAuthenticated;

  Future<bool> checkAuthStatus() async {
    _isLoading = true;
    notifyListeners();

    try {
      final token = await SecureStorage.getToken();
      if (token != null && token.isNotEmpty) {
        // Token exists, verify it
        _user = await _authService.getProfile();
        _isAuthenticated = true;
      } else {
        _isAuthenticated = false;
      }
    } catch (e) {
      // Token might be expired or invalid
      await logout();
      _isAuthenticated = false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
    return _isAuthenticated;
  }

  Future<bool> login(String username, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final userModel = await _authService.login(username, password);
      if (userModel.token != null) {
        await SecureStorage.saveToken(userModel.token!);
        await SecureStorage.saveUserDetails(
            userModel.id.toString(), userModel.role);
        _user = userModel;
        _isAuthenticated = true;
        _isLoading = false;
        notifyListeners();
        return true;
      }
      return false;
    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
      _isAuthenticated = false;
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    await SecureStorage.clearAll();
    _user = null;
    _isAuthenticated = false;
    notifyListeners();
  }
}
