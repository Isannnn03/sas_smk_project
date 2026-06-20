<?php
// =============================================================================
// SMART ATTENDANCE SYSTEM (SAS) - SMK
// File   : backend/config/database.php
// Tujuan : Menyediakan koneksi PDO tunggal yang aman ke database MySQL.
//          File ini di-require oleh setiap endpoint API yang butuh DB.
// =============================================================================

/**
 * loadEnv()
 * Membaca file .env sederhana jika tersedia.
 */
function loadEnv(string $path): void
{
    if (!file_exists($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Muat konfigurasi .env
loadEnv(__DIR__ . '/../.env');

/**
 * Konfigurasi koneksi database.
 */
define('DB_HOST',    $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT',    $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME',    $_ENV['DB_NAME'] ?? 'sas_db');
define('DB_USER',    $_ENV['DB_USER'] ?? 'root');
define('DB_PASS',    $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

/**
 * getDB()
 *
 * Membuat dan mengembalikan instance PDO dengan konfigurasi yang aman.
 * Menggunakan pola Singleton sederhana agar koneksi hanya dibuat sekali
 * per request (hemat resource).
 *
 * @return PDO Instance koneksi PDO yang siap digunakan.
 * @throws RuntimeException Jika koneksi database gagal.
 */
function getDB(): PDO
{
    // Variabel statis: hanya diinisialisasi sekali dalam satu lifecycle request
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // DSN (Data Source Name) untuk PDO MySQL
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    // Opsi PDO untuk keamanan dan performa
    $options = [
        // Lempar exception saat terjadi error SQL (WAJIB untuk error handling yang baik)
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

        // Gunakan fetch mode array asosiatif secara default
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Matikan emulasi prepared statement agar query benar-benar dikirim
        // sebagai prepared statement ke MySQL (keamanan SQL Injection lebih kuat)
        PDO::ATTR_EMULATE_PREPARES   => false,

        // Pastikan charset UTF-8 digunakan untuk koneksi
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Log error ke server, jangan tampilkan detail ke client (keamanan)
        error_log('[SAS-DB-ERROR] Koneksi database gagal: ' . $e->getMessage());

        // Kembalikan respons JSON yang aman ke client
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Layanan database sementara tidak tersedia. Hubungi administrator.',
        ]);
        exit;
    }

    return $pdo;
}
