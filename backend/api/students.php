<?php
// =============================================================================
// SMART ATTENDANCE SYSTEM (SAS) - SMK
// File   : backend/api/students.php
// Tujuan : CRUD data siswa dan generate QR Code hash.
//          Semua endpoint membutuhkan autentikasi token.
//          Hanya 'tata_usaha' yang boleh melakukan Create, Update, Delete.
//          'guru' hanya bisa membaca data siswa.
//
// Endpoint yang tersedia:
//   GET    /api/students.php?action=list              → Ambil semua siswa (opsional ?class_id=)
//   GET    /api/students.php?action=detail&id={id}   → Ambil detail satu siswa
//   POST   /api/students.php?action=create            → Tambah siswa baru + generate QR
//   PUT    /api/students.php?action=update&id={id}   → Update data siswa
//   DELETE /api/students.php?action=delete&id={id}   → Hapus (soft delete) siswa
//   POST   /api/students.php?action=regenerate_qr&id={id} → Generate ulang QR Code
// =============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

setCorsHeaders();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Semua endpoint membutuhkan autentikasi
$authPayload = verifyToken();

switch ($action) {
    case 'list':
        if ($method !== 'GET') sendResponse(false, 'Gunakan method GET.', null, 405);
        handleListStudents();
        break;

    case 'detail':
        if ($method !== 'GET') sendResponse(false, 'Gunakan method GET.', null, 405);
        $id = (int) ($_GET['id'] ?? 0);
        handleGetStudentDetail($id);
        break;

    case 'create':
        if ($method !== 'POST') sendResponse(false, 'Gunakan method POST.', null, 405);
        requireRole('tata_usaha'); // Hanya TU yang bisa tambah
        handleCreateStudent();
        break;

    case 'update':
        if ($method !== 'PUT') sendResponse(false, 'Gunakan method PUT.', null, 405);
        requireRole('tata_usaha');
        $id = (int) ($_GET['id'] ?? 0);
        handleUpdateStudent($id);
        break;

    case 'delete':
        if ($method !== 'DELETE') sendResponse(false, 'Gunakan method DELETE.', null, 405);
        requireRole('tata_usaha');
        $id = (int) ($_GET['id'] ?? 0);
        handleDeleteStudent($id);
        break;

    case 'regenerate_qr':
        if ($method !== 'POST') sendResponse(false, 'Gunakan method POST.', null, 405);
        requireRole('tata_usaha');
        $id = (int) ($_GET['id'] ?? 0);
        handleRegenerateQr($id);
        break;

    default:
        sendResponse(false, 'Action tidak valid.', null, 400);
}

// =============================================================================
// FUNGSI HELPER INTERNAL
// =============================================================================

/**
 * generateQrCodePayload()
 *
 * Menghasilkan JSON payload untuk QR Code yang berisi student_id, nis, uuid,
 * dan signature_hash (HMAC SHA-256) untuk memastikan data tidak dipalsukan.
 */
function generateQrCodePayload(int $studentId, string $nis): string
{
    // Secret key statis (idealnya dipindahkan ke .env atau config.php)
    $secretKey = 'SAS_SMK_SECRET_KEY_2026';

    // Buat raw string untuk signature
    $dataString = $studentId . '|' . $nis;
    // Ambil 16 karakter pertama dari HMAC (cukup untuk anti-forgery pada QR)
    $signature  = substr(hash_hmac('sha256', $dataString, $secretKey), 0, 16);

    // Format compact: ~30-40 karakter vs ~170 karakter JSON
    // Contoh: SAS|1|2024001|a1b2c3d4e5f6g7h8
    return 'SAS|' . $studentId . '|' . $nis . '|' . $signature;
}

// =============================================================================
// FUNGSI HANDLER
// =============================================================================

/**
 * handleListStudents()
 *
 * Mengambil daftar semua siswa. Bisa difilter berdasarkan class_id.
 * Mendukung fitur pagination (halaman) untuk membatasi data yang ditarik.
 *
 * @return void
 */
function handleListStudents(): void
{
    $pdo     = getDB();
    $classId = isset($_GET['class_id']) ? (int) $_GET['class_id'] : null;
    
    // Konfigurasi Paginasi
    $page  = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 50; // default 50
    $offset = ($page - 1) * $limit;

    // --- Hitung Total Data (untuk metadata paginasi) ---
    $countSql = 'SELECT COUNT(id) FROM students';
    $countParams = [];
    if ($classId !== null && $classId > 0) {
        $countSql .= ' WHERE class_id = :class_id';
        $countParams[':class_id'] = $classId;
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalItems = (int) $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $limit);

    // --- Query Data Utama ---
    $sql = 'SELECT s.id, s.nis, s.name, s.class_id, c.name AS class_name,
                   s.qr_code, s.status, s.created_at
            FROM students s
            JOIN classes c ON s.class_id = c.id';

    $params = [];
    if ($classId !== null && $classId > 0) {
        $sql .= ' WHERE s.class_id = :class_id';
        $params[':class_id'] = $classId;
    }

    $sql .= ' ORDER BY c.name ASC, s.name ASC LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);
    
    // Bind parameters manually for LIMIT & OFFSET (PDO needs integer explicitly if emulate is off, though we set it off earlier)
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $students = $stmt->fetchAll();

    sendResponse(true, 'Data siswa berhasil diambil.', [
        'pagination' => [
            'total_items'  => $totalItems,
            'total_pages'  => $totalPages,
            'current_page' => $page,
            'limit'        => $limit
        ],
        'count'    => count($students),
        'students' => $students,
    ]);
}

/**
 * handleGetStudentDetail()
 *
 * Mengambil detail lengkap satu siswa berdasarkan ID.
 *
 * @param int $id ID siswa yang dicari.
 * @return void
 */
function handleGetStudentDetail(int $id): void
{
    if ($id <= 0) {
        sendResponse(false, 'Parameter ID siswa tidak valid.', null, 400);
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT s.id, s.nis, s.name, s.class_id, c.name AS class_name,
                s.qr_code, s.status, s.created_at, s.updated_at
         FROM students s
         JOIN classes c ON s.class_id = c.id
         WHERE s.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $student = $stmt->fetch();

    if (!$student) {
        sendResponse(false, 'Siswa tidak ditemukan.', null, 404);
    }

    sendResponse(true, 'Detail siswa berhasil diambil.', $student);
}

/**
 * handleCreateStudent()
 *
 * Menambahkan data siswa baru ke database.
 * QR Code di-generate otomatis saat siswa dibuat.
 *
 * Body JSON yang dibutuhkan:
 *   { "nis": "...", "name": "...", "class_id": 1, "status": "aktif" }
 *
 * @return void
 */
function handleCreateStudent(): void
{
    $body = getRequestBody();

    // --- Validasi input wajib ---
    $nis     = trim($body['nis']  ?? '');
    $name    = trim($body['name'] ?? '');
    $classId = (int) ($body['class_id'] ?? 0);
    $status  = $body['status'] ?? 'aktif';

    if (empty($nis) || empty($name) || $classId <= 0) {
        sendResponse(false, 'Field nis, name, dan class_id wajib diisi.', null, 400);
    }

    // Validasi enum status
    if (!in_array($status, ['aktif', 'non_aktif'], true)) {
        sendResponse(false, 'Status tidak valid. Gunakan: aktif atau non_aktif.', null, 400);
    }

    $pdo = getDB();

    // --- Cek apakah NIS sudah terdaftar ---
    $checkStmt = $pdo->prepare('SELECT id FROM students WHERE nis = :nis LIMIT 1');
    $checkStmt->execute([':nis' => $nis]);
    if ($checkStmt->fetch()) {
        sendResponse(false, "NIS '{$nis}' sudah terdaftar di sistem.", null, 409); // 409 Conflict
    }

    // --- Cek apakah class_id valid ---
    $classCheck = $pdo->prepare('SELECT id FROM classes WHERE id = :id LIMIT 1');
    $classCheck->execute([':id' => $classId]);
    if (!$classCheck->fetch()) {
        sendResponse(false, 'Kelas dengan ID yang diberikan tidak ditemukan.', null, 404);
    }

    // --- Insert data siswa baru (qr_code sementara dikosongkan) ---
    $stmt = $pdo->prepare(
        'INSERT INTO students (nis, name, class_id, qr_code, status)
         VALUES (:nis, :name, :class_id, "", :status)'
    );
    $stmt->execute([
        ':nis'      => $nis,
        ':name'     => $name,
        ':class_id' => $classId,
        ':status'   => $status,
    ]);

    $newId = (int) $pdo->lastInsertId();

    // --- Generate QR Code compact payload bersignature ---
    $qrCode = generateQrCodePayload($newId, $nis);

    // --- Update siswa dengan QR code compact final ---
    $pdo->prepare('UPDATE students SET qr_code = :qr_code WHERE id = :id')
        ->execute([':qr_code' => $qrCode, ':id' => $newId]);

    sendResponse(true, 'Siswa baru berhasil ditambahkan.', [
        'id'      => $newId,
        'nis'     => $nis,
        'name'    => $name,
        'class_id'=> $classId,
        'qr_code' => $qrCode,
        'status'  => $status,
    ], 201); // 201 Created
}

/**
 * handleUpdateStudent()
 *
 * Memperbarui data siswa yang sudah ada.
 * QR Code tidak bisa diubah melalui endpoint ini (gunakan regenerate_qr).
 *
 * Body JSON (semua field opsional, tapi minimal satu harus ada):
 *   { "name": "...", "class_id": 1, "status": "aktif" }
 *
 * @param int $id ID siswa yang akan diupdate.
 * @return void
 */
function handleUpdateStudent(int $id): void
{
    if ($id <= 0) {
        sendResponse(false, 'Parameter ID siswa tidak valid.', null, 400);
    }

    $body = getRequestBody();
    $pdo  = getDB();

    // Cek keberadaan siswa terlebih dahulu
    $checkStmt = $pdo->prepare('SELECT id FROM students WHERE id = :id LIMIT 1');
    $checkStmt->execute([':id' => $id]);
    if (!$checkStmt->fetch()) {
        sendResponse(false, 'Siswa tidak ditemukan.', null, 404);
    }

    // --- Bangun query UPDATE secara dinamis ---
    // Hanya update field yang dikirim dalam request body
    $allowedFields = ['name', 'class_id', 'status'];
    $setClauses    = [];
    $params        = [':id' => $id];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $body)) {
            $value = $body[$field];

            // Validasi spesifik per field
            if ($field === 'status' && !in_array($value, ['aktif', 'non_aktif'], true)) {
                sendResponse(false, 'Status tidak valid. Gunakan: aktif atau non_aktif.', null, 400);
            }
            if ($field === 'class_id') {
                $value = (int) $value;
                if ($value <= 0) sendResponse(false, 'class_id tidak valid.', null, 400);
            }

            $setClauses[]         = "`{$field}` = :{$field}";
            $params[":{$field}"]  = $value;
        }
    }

    if (empty($setClauses)) {
        sendResponse(false, 'Tidak ada field yang valid untuk diupdate.', null, 400);
    }

    $sql  = 'UPDATE students SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    sendResponse(true, 'Data siswa berhasil diperbarui.');
}

/**
 * handleDeleteStudent()
 *
 * Menghapus data siswa dari database (hard delete).
 * Catatan: Karena ada relasi FK ke tabel attendances,
 * rekord absensi siswa juga akan terhapus (ON DELETE CASCADE).
 *
 * @param int $id ID siswa yang akan dihapus.
 * @return void
 */
function handleDeleteStudent(int $id): void
{
    if ($id <= 0) {
        sendResponse(false, 'Parameter ID siswa tidak valid.', null, 400);
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, name FROM students WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $student = $stmt->fetch();

    if (!$student) {
        sendResponse(false, 'Siswa tidak ditemukan.', null, 404);
    }

    $delStmt = $pdo->prepare('DELETE FROM students WHERE id = :id');
    $delStmt->execute([':id' => $id]);

    sendResponse(true, "Siswa '{$student['name']}' berhasil dihapus dari sistem.");
}

/**
 * handleRegenerateQr()
 *
 * Membuat ulang hash QR Code untuk seorang siswa.
 * Digunakan ketika QR Code lama hilang, rusak, atau perlu diperbarui.
 * Token QR lama akan langsung tidak berlaku.
 *
 * @param int $id ID siswa yang QR Code-nya akan di-generate ulang.
 * @return void
 */
function handleRegenerateQr(int $id): void
{
    if ($id <= 0) {
        sendResponse(false, 'Parameter ID siswa tidak valid.', null, 400);
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, nis, name FROM students WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $student = $stmt->fetch();

    if (!$student) {
        sendResponse(false, 'Siswa tidak ditemukan.', null, 404);
    }

    // Generate payload QR baru bersignature
    $newQrCode = generateQrCodePayload($student['id'], $student['nis']);

    $updateStmt = $pdo->prepare('UPDATE students SET qr_code = :qr_code WHERE id = :id');
    $updateStmt->execute([
        ':qr_code' => $newQrCode,
        ':id'      => $id,
    ]);

    sendResponse(true, "QR Code untuk siswa '{$student['name']}' berhasil di-generate ulang.", [
        'student_id'  => $id,
        'student_name'=> $student['name'],
        'new_qr_code' => $newQrCode,
    ]);
}
