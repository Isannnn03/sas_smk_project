<?php
// =============================================================================
// SMART ATTENDANCE SYSTEM (SAS) - SMK
// File   : backend/api/auth.php
// Tujuan : Menangani autentikasi pengguna (Login & Logout).
//
// Endpoint yang tersedia:
//   POST /api/auth.php?action=login   → Login dan mendapatkan token
//   POST /api/auth.php?action=logout  → Invalidasi token (sisi client)
//   GET  /api/auth.php?action=profile → Cek profil dari token aktif
// =============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

// Set header CORS dan Content-Type untuk semua respons
setCorsHeaders();

// -------------------------------------------------------------------
// ROUTER UTAMA
// Membaca parameter 'action' dari query string untuk menentukan operasi.
// -------------------------------------------------------------------
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {

    // ---------------------------------------------------------------
    // ACTION: login
    // Method : POST
    // Body   : { "username": "...", "password": "..." }
    // ---------------------------------------------------------------
    case 'login':
        if ($method !== 'POST') {
            sendResponse(false, 'Method tidak diizinkan. Gunakan POST.', null, 405);
        }
        handleLogin();
        break;

    // ---------------------------------------------------------------
    // ACTION: logout
    // Method : POST
    // Header : Authorization: Bearer <token>
    // Catatan: Token berbasis stateless. Logout di sisi server hanya
    //          memberitahu client untuk menghapus token lokal mereka.
    //          Implementasi blacklist token membutuhkan penyimpanan sesi.
    // ---------------------------------------------------------------
    case 'logout':
        if ($method !== 'POST') {
            sendResponse(false, 'Method tidak diizinkan. Gunakan POST.', null, 405);
        }
        verifyToken(); // Pastikan token valid sebelum logout
        sendResponse(true, 'Logout berhasil. Silakan hapus token dari penyimpanan lokal Anda.');
        break;

    // ---------------------------------------------------------------
    // ACTION: profile
    // Method : GET
    // Header : Authorization: Bearer <token>
    // ---------------------------------------------------------------
    case 'profile':
        if ($method !== 'GET') {
            sendResponse(false, 'Method tidak diizinkan. Gunakan GET.', null, 405);
        }
        $payload = verifyToken();
        handleGetProfile($payload);
        break;

    default:
        sendResponse(false, 'Action tidak valid.', null, 400);
}

// =============================================================================
// FUNGSI HANDLER
// =============================================================================

/**
 * handleLogin()
 *
 * Memvalidasi kredensial username dan password.
 * Jika valid, menghasilkan dan mengembalikan token autentikasi.
 *
 * @return void
 */
function handleLogin(): void
{
    $body = getRequestBody();

    // --- Validasi input ---
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';
    $source   = $body['source'] ?? '';

    if (empty($username) || empty($password)) {
        sendResponse(false, 'Username dan password wajib diisi.', null, 400);
    }

    // --- Query ke database menggunakan PDO Prepared Statement ---
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, username, password, role FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    // --- Verifikasi user dan password (bcrypt) ---
    // Menggunakan password_verify yang aman terhadap timing attack
    if (!$user || !password_verify($password, $user['password'])) {
        // Gunakan pesan generik agar tidak mengekspos info apakah username ada
        sendResponse(false, 'Username atau password salah.', null, 401);
    }

    if ($source === 'dashboard' && $user['role'] === 'guru') {
        sendResponse(false, 'Akses ditolak. Guru tidak diizinkan masuk ke Dashboard.', null, 403);
    }

    // --- Generate token ---
    $token = generateToken((int) $user['id'], $user['role']);

    // Karena username sekarang menggunakan nama asli guru, kita bisa langsung menggunakannya
    $displayName = $user['username'];

    sendResponse(true, 'Login berhasil.', [
        'token' => $token,
        'user'  => [
            'id'       => $user['id'],
            'username' => $displayName,
            'role'     => $user['role'],
        ],
    ]);
}

/**
 * handleGetProfile()
 *
 * Mendekode token aktif dan mengembalikan data profil user dari database.
 *
 * @return void
 */
function handleGetProfile(): void
{
    // verifyToken() otomatis mengembalikan respons 401 jika token tidak valid
    $payload = verifyToken();

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $payload['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse(false, 'User tidak ditemukan.', null, 404);
    }

    $displayName = $user['username'];
    if ($user['role'] === 'guru') {
        $teacherStmt = $pdo->prepare('SELECT name FROM teachers WHERE name = :nip1 OR nip = :nip2 OR :nip3 LIKE CONCAT("%", SUBSTRING_INDEX(name, " ", 1), "%") LIMIT 1');
        $teacherStmt->execute([
            ':nip1' => $user['username'],
            ':nip2' => $user['username'],
            ':nip3' => $user['username']
        ]);
        $teacher = $teacherStmt->fetch();
        if ($teacher) {
            $displayName = $teacher['name'];
        }
    }

    sendResponse(true, 'Data profil berhasil diambil.', [
        'id'         => $user['id'],
        'username'   => $displayName,
        'role'       => $user['role'],
        'token_info' => [
            'expires_at' => date('Y-m-d H:i:s', $payload['exp']),
        ],
    ]);
}
