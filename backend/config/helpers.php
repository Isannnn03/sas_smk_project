<?php
// =============================================================================
// SMART ATTENDANCE SYSTEM (SAS) - SMK
// File   : backend/config/helpers.php
// Tujuan : Fungsi-fungsi pembantu (helper) yang digunakan di seluruh endpoint.
//          Termasuk: CORS header, response JSON, autentikasi token.
// =============================================================================

// -----------------------------------------------------------------------
// KONFIGURASI GLOBAL
// -----------------------------------------------------------------------

/** Secret key untuk signing token. Diambil dari .env. */
define('TOKEN_SECRET', $_ENV['TOKEN_SECRET'] ?? 'SAS_SMK_SUPER_SECRET_KEY_2024_CHANGE_IN_PRODUCTION');

/** Durasi token dalam detik (default: 8 jam) */
define('TOKEN_LIFETIME', isset($_ENV['TOKEN_LIFETIME']) ? (int) $_ENV['TOKEN_LIFETIME'] : 8 * 60 * 60);

// -----------------------------------------------------------------------
// FUNGSI: PENANGANAN ERROR GLOBAL
// -----------------------------------------------------------------------

/**
 * Tangani unhandled exceptions agar mengembalikan JSON.
 */
set_exception_handler(function (Throwable $e) {
    error_log('[SAS-EXCEPTION] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem internal. Silakan coba beberapa saat lagi.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

/**
 * Tangani PHP errors (Warning, Notice) agar mengembalikan JSON (di luar E_DEPRECATED).
 */
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    if (!(error_reporting() & $errno)) return false;
    error_log("[SAS-ERROR] [$errno] $errstr in $errfile:$errline");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan pada server.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// -----------------------------------------------------------------------
// FUNGSI: HEADER CORS & CONTENT TYPE
// -----------------------------------------------------------------------

/**
 * setCorsHeaders()
 *
 * Mengatur HTTP header untuk CORS (Cross-Origin Resource Sharing) dan
 * memastikan semua respons bertipe JSON.
 * Dipanggil di paling atas setiap file API.
 *
 * @return void
 */
function setCorsHeaders(): void
{
    // Izinkan semua origin di fase development.
    // Di production, ganti '*' dengan domain spesifik: 'https://dashboard.sekolah.sch.id'
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Content-Type: application/json; charset=utf-8');

    // Tangani preflight request (OPTIONS) dari browser
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204); // No Content
        exit;
    }
}

// -----------------------------------------------------------------------
// FUNGSI: RESPONS JSON STANDAR
// -----------------------------------------------------------------------

/**
 * sendResponse()
 *
 * Mengirimkan respons JSON yang terstruktur dan konsisten ke client,
 * kemudian menghentikan eksekusi script.
 *
 * @param bool   $success    Status keberhasilan operasi.
 * @param string $message    Pesan deskriptif untuk client.
 * @param mixed  $data       Data payload (array, object, atau null).
 * @param int    $statusCode HTTP status code (200, 201, 400, 401, 404, 500, dsb.)
 * @return void
 */
function sendResponse(bool $success, string $message, mixed $data = null, int $statusCode = 200): void
{
    http_response_code($statusCode);

    $body = [
        'success' => $success,
        'message' => $message,
    ];

    // Sertakan data payload hanya jika tidak null
    if ($data !== null) {
        $body['data'] = $data;
    }

    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// -----------------------------------------------------------------------
// FUNGSI: MANAJEMEN TOKEN AUTENTIKASI
// -----------------------------------------------------------------------

/**
 * generateToken()
 *
 * Membuat token autentikasi berbasis HMAC-SHA256.
 * Token terdiri dari payload (user ID + role + expiry) yang di-encode Base64
 * dan di-sign menggunakan secret key.
 *
 * Format token: base64(payload) . '.' . hmac_signature
 *
 * @param int    $userId ID user dari tabel `users`.
 * @param string $role   Role user ('tata_usaha' atau 'guru').
 * @return string Token autentikasi dalam format string.
 */
function generateToken(int $userId, string $role): string
{
    $payload = json_encode([
        'user_id' => $userId,
        'role'    => $role,
        'exp'     => time() + TOKEN_LIFETIME,
        'iat'     => time(), // issued at
    ]);

    $encodedPayload = base64_encode($payload);
    $signature      = hash_hmac('sha256', $encodedPayload, TOKEN_SECRET);

    return $encodedPayload . '.' . $signature;
}

/**
 * verifyToken()
 *
 * Memvalidasi token dari header Authorization.
 * Memeriksa integritas signature dan masa berlaku token.
 *
 * @return array Data payload token jika valid.
 *               Script dihentikan dengan respons 401 jika token tidak valid/kadaluarsa.
 */
function verifyToken(): array
{
    // Ambil token dari header Authorization: Bearer <token>
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
        sendResponse(false, 'Token autentikasi tidak ditemukan. Silakan login terlebih dahulu.', null, 401);
    }

    $token = substr($authHeader, 7); // Hapus prefix 'Bearer '
    $parts = explode('.', $token);

    if (count($parts) !== 2) {
        sendResponse(false, 'Format token tidak valid.', null, 401);
    }

    [$encodedPayload, $receivedSignature] = $parts;

    // Verifikasi signature menggunakan hash_equals (aman dari timing attack)
    $expectedSignature = hash_hmac('sha256', $encodedPayload, TOKEN_SECRET);
    if (!hash_equals($expectedSignature, $receivedSignature)) {
        sendResponse(false, 'Token tidak valid atau telah dimodifikasi.', null, 401);
    }

    // Decode payload
    $payload = json_decode(base64_decode($encodedPayload), true);

    if (!$payload || !isset($payload['exp'])) {
        sendResponse(false, 'Payload token tidak valid.', null, 401);
    }

    // Periksa masa berlaku token
    if (time() > $payload['exp']) {
        sendResponse(false, 'Token telah kadaluarsa. Silakan login kembali.', null, 401);
    }

    return $payload;
}

/**
 * requireRole()
 *
 * Memverifikasi token DAN memeriksa apakah role user sesuai
 * dengan yang dibutuhkan oleh endpoint ini.
 *
 * @param string|array $allowedRoles Role yang diizinkan mengakses endpoint.
 * @return array Data payload token.
 */
function requireRole(string|array $allowedRoles): array
{
    $payload = verifyToken();

    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];

    if (!in_array($payload['role'], $roles, true)) {
        sendResponse(false, 'Akses ditolak. Anda tidak memiliki izin untuk endpoint ini.', null, 403);
    }

    return $payload;
}

// -----------------------------------------------------------------------
// FUNGSI: PARSING REQUEST BODY
// -----------------------------------------------------------------------

/**
 * getRequestBody()
 *
 * Membaca dan men-decode body request JSON dari php://input.
 * Mendukung request body dari POST dengan Content-Type: application/json.
 *
 * @return array Data dari request body, atau array kosong jika tidak ada/invalid.
 */
function getRequestBody(): array
{
    $rawBody = file_get_contents('php://input');
    if (empty($rawBody)) {
        return [];
    }

    $decoded = json_decode($rawBody, true);
    if (!is_array($decoded)) {
        return [];
    }

    // Sanitasi semua nilai string dalam array untuk mencegah XSS dasar
    array_walk_recursive($decoded, function (&$item) {
        if (is_string($item)) {
            $item = strip_tags(trim($item));
        }
    });

    return $decoded;
}
