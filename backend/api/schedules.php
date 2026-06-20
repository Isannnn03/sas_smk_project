<?php
// =============================================================================
// SMART ATTENDANCE SYSTEM (SAS) - SMK
// File   : backend/api/schedules.php
// Tujuan : CRUD data jadwal pelajaran.
//          Mengatur relasi antara Kelas, Guru, Mata Pelajaran, Hari, dan Waktu.
//          Hanya 'tata_usaha' yang bisa Create, Update, Delete.
//          'guru' hanya bisa membaca jadwal yang relevan.
//
// Endpoint yang tersedia:
//   GET    /api/schedules.php?action=list                  → Semua jadwal (filter opsional)
//   GET    /api/schedules.php?action=detail&id={id}       → Detail satu jadwal
//   POST   /api/schedules.php?action=create                → Tambah jadwal baru
//   PUT    /api/schedules.php?action=update&id={id}       → Update jadwal
//   DELETE /api/schedules.php?action=delete&id={id}       → Hapus jadwal
//   GET    /api/schedules.php?action=by_class&class_id={} → Jadwal per kelas
//   GET    /api/schedules.php?action=by_teacher&teacher_id={} → Jadwal per guru
// =============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

date_default_timezone_set('Asia/Jakarta');

setCorsHeaders();

$action      = $_GET['action'] ?? '';
$method      = $_SERVER['REQUEST_METHOD'];
$authPayload = verifyToken(); // Semua endpoint butuh autentikasi

switch ($action) {
    case 'list':
        if ($method !== 'GET') sendResponse(false, 'Gunakan method GET.', null, 405);
        handleListSchedules();
        break;

    case 'detail':
        if ($method !== 'GET') sendResponse(false, 'Gunakan method GET.', null, 405);
        $id = (int) ($_GET['id'] ?? 0);
        handleGetScheduleDetail($id);
        break;

    case 'create':
        if ($method !== 'POST') sendResponse(false, 'Gunakan method POST.', null, 405);
        requireRole('tata_usaha');
        handleCreateSchedule();
        break;

    case 'update':
        if ($method !== 'PUT') sendResponse(false, 'Gunakan method PUT.', null, 405);
        requireRole('tata_usaha');
        $id = (int) ($_GET['id'] ?? 0);
        handleUpdateSchedule($id);
        break;

    case 'delete':
        if ($method !== 'DELETE') sendResponse(false, 'Gunakan method DELETE.', null, 405);
        requireRole('tata_usaha');
        $id = (int) ($_GET['id'] ?? 0);
        handleDeleteSchedule($id);
        break;

    case 'by_class':
        if ($method !== 'GET') sendResponse(false, 'Gunakan method GET.', null, 405);
        $classId = (int) ($_GET['class_id'] ?? 0);
        handleGetByClass($classId);
        break;

    case 'by_teacher':
        if ($method !== 'GET') sendResponse(false, 'Gunakan method GET.', null, 405);
        $teacherId = (int) ($_GET['teacher_id'] ?? 0);
        handleGetByTeacher($teacherId);
        break;

    default:
        sendResponse(false, 'Action tidak valid.', null, 400);
}

// =============================================================================
// FUNGSI HANDLER
// =============================================================================

/**
 * handleListSchedules()
 *
 * Mengambil seluruh data jadwal dengan informasi lengkap (join 3 tabel).
 * Mendukung filter opsional via query string: day, class_id.
 * Mendukung fitur pagination (halaman) untuk membatasi data yang ditarik.
 *
 * @return void
 */
function handleListSchedules(): void
{
    $pdo    = getDB();
    
    // Konfigurasi Paginasi
    $page  = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 50; // default 50
    $offset = ($page - 1) * $limit;
    
    $params = [];

    // --- Hitung Total Data (untuk metadata paginasi) ---
    $countSql = 'SELECT COUNT(sc.id) FROM schedules sc WHERE 1=1';
    
    // Filter opsional: hari (untuk COUNT)
    if (!empty($_GET['day'])) {
        $allowedDays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        if (in_array($_GET['day'], $allowedDays, true)) {
            $countSql      .= ' AND sc.day = :day';
        }
    }
    // Filter opsional: class_id (untuk COUNT)
    if (!empty($_GET['class_id'])) {
        $countSql      .= ' AND sc.class_id = :class_id';
    }
    
    $countStmt = $pdo->prepare($countSql);
    // Bind parameter untuk COUNT
    if (str_contains($countSql, ':day')) $countStmt->bindValue(':day', $_GET['day']);
    if (str_contains($countSql, ':class_id')) $countStmt->bindValue(':class_id', (int) $_GET['class_id'], PDO::PARAM_INT);
    
    $countStmt->execute();
    $totalItems = (int) $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $limit);

    // --- Query Data Utama ---
    $sql = 'SELECT sc.id,
                   sc.day, sc.start_time, sc.end_time,
                   sc.class_id,   cl.name AS class_name,
                   sc.teacher_id, t.name  AS teacher_name, t.nip AS teacher_nip,
                   sc.subject_id, su.name AS subject_name,
                   sc.created_at
            FROM schedules sc
            JOIN classes  cl ON sc.class_id   = cl.id
            JOIN teachers t  ON sc.teacher_id  = t.id
            JOIN subjects su ON sc.subject_id  = su.id
            WHERE 1=1'; 

    // Filter opsional: hari
    if (!empty($_GET['day'])) {
        $allowedDays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        if (in_array($_GET['day'], $allowedDays, true)) {
            $sql           .= ' AND sc.day = :day';
            $params[':day']  = $_GET['day'];
        }
    }

    // Filter opsional: class_id
    if (!empty($_GET['class_id'])) {
        $sql                .= ' AND sc.class_id = :class_id';
        $params[':class_id']  = (int) $_GET['class_id'];
    }

    // Urutan: hari -> jam mulai
    $dayOrder = "FIELD(sc.day, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu')";
    $sql .= " ORDER BY {$dayOrder}, sc.start_time ASC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    
    // Bind parameters manually for LIMIT & OFFSET
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $schedules = $stmt->fetchAll();

    sendResponse(true, 'Data jadwal berhasil diambil.', [
        'pagination' => [
            'total_items'  => $totalItems,
            'total_pages'  => $totalPages,
            'current_page' => $page,
            'limit'        => $limit
        ],
        'count'     => count($schedules),
        'schedules' => $schedules,
    ]);
}

/**
 * handleGetScheduleDetail()
 *
 * Mengambil detail lengkap satu jadwal berdasarkan ID.
 *
 * @param int $id ID jadwal.
 * @return void
 */
function handleGetScheduleDetail(int $id): void
{
    if ($id <= 0) sendResponse(false, 'ID jadwal tidak valid.', null, 400);

    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT sc.id,
                sc.day, sc.start_time, sc.end_time,
                sc.class_id,   cl.name AS class_name,
                sc.teacher_id, t.name  AS teacher_name, t.nip AS teacher_nip,
                sc.subject_id, su.name AS subject_name,
                sc.created_at, sc.updated_at
         FROM schedules sc
         JOIN classes  cl ON sc.class_id   = cl.id
         JOIN teachers t  ON sc.teacher_id  = t.id
         JOIN subjects su ON sc.subject_id  = su.id
         WHERE sc.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $schedule = $stmt->fetch();

    if (!$schedule) {
        sendResponse(false, 'Jadwal tidak ditemukan.', null, 404);
    }

    sendResponse(true, 'Detail jadwal berhasil diambil.', $schedule);
}

/**
 * handleCreateSchedule()
 *
 * Menambahkan jadwal pelajaran baru.
 * Melakukan validasi FK untuk class_id, teacher_id, subject_id,
 * serta validasi konflik jadwal (kelas & guru tidak boleh overlap).
 *
 * Body JSON:
 *   {
 *     "class_id": 1, "teacher_id": 1, "subject_id": 1,
 *     "day": "Senin", "start_time": "08:00", "end_time": "09:30"
 *   }
 *
 * @return void
 */
function handleCreateSchedule(): void
{
    $body = getRequestBody();

    // --- Validasi input wajib ---
    $classId   = (int) ($body['class_id']   ?? 0);
    $teacherId = (int) ($body['teacher_id'] ?? 0);
    $subjectId = (int) ($body['subject_id'] ?? 0);
    $day       = trim($body['day']          ?? '');
    $startTime = trim($body['start_time']   ?? '');
    $endTime   = trim($body['end_time']     ?? '');

    if (!$classId || !$teacherId || !$subjectId || empty($day) || empty($startTime) || empty($endTime)) {
        sendResponse(false, 'Semua field wajib diisi: class_id, teacher_id, subject_id, day, start_time, end_time.', null, 400);
    }

    $allowedDays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    if (!in_array($day, $allowedDays, true)) {
        sendResponse(false, 'Nilai day tidak valid. Gunakan: ' . implode(', ', $allowedDays) . '.', null, 400);
    }

    if ($startTime >= $endTime) {
        sendResponse(false, 'start_time harus lebih awal dari end_time.', null, 400);
    }

    $pdo = getDB();

    // --- Validasi keberadaan FK ---
    validateForeignKey($pdo, 'classes',  $classId,   'Kelas');
    validateForeignKey($pdo, 'teachers', $teacherId, 'Guru');
    validateForeignKey($pdo, 'subjects', $subjectId, 'Mata Pelajaran');

    // --- Validasi konflik jadwal untuk KELAS ---
    checkScheduleConflict($pdo, $classId, $day, $startTime, $endTime, 'class_id', 'Kelas ini');

    // --- Validasi konflik jadwal untuk GURU ---
    checkScheduleConflict($pdo, $teacherId, $day, $startTime, $endTime, 'teacher_id', 'Guru ini');

    // --- Insert jadwal baru ---
    $stmt = $pdo->prepare(
        'INSERT INTO schedules (class_id, teacher_id, subject_id, day, start_time, end_time)
         VALUES (:class_id, :teacher_id, :subject_id, :day, :start_time, :end_time)'
    );
    $stmt->execute([
        ':class_id'   => $classId,
        ':teacher_id' => $teacherId,
        ':subject_id' => $subjectId,
        ':day'        => $day,
        ':start_time' => $startTime,
        ':end_time'   => $endTime,
    ]);

    sendResponse(true, 'Jadwal baru berhasil ditambahkan.', [
        'id' => (int) $pdo->lastInsertId(),
    ], 201);
}

/**
 * handleUpdateSchedule()
 *
 * Memperbarui data jadwal yang sudah ada.
 *
 * @param int $id ID jadwal yang akan diupdate.
 * @return void
 */
function handleUpdateSchedule(int $id): void
{
    if ($id <= 0) sendResponse(false, 'ID jadwal tidak valid.', null, 400);

    $body = getRequestBody();
    $pdo  = getDB();

    // Cek keberadaan jadwal
    $checkStmt = $pdo->prepare('SELECT id FROM schedules WHERE id = :id LIMIT 1');
    $checkStmt->execute([':id' => $id]);
    if (!$checkStmt->fetch()) {
        sendResponse(false, 'Jadwal tidak ditemukan.', null, 404);
    }

    $allowedFields = ['class_id', 'teacher_id', 'subject_id', 'day', 'start_time', 'end_time'];
    $setClauses    = [];
    $params        = [':id' => $id];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $body)) {
            $setClauses[]         = "`{$field}` = :{$field}";
            $params[":{$field}"]  = $body[$field];
        }
    }

    if (empty($setClauses)) {
        sendResponse(false, 'Tidak ada field valid untuk diupdate.', null, 400);
    }

    $sql  = 'UPDATE schedules SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    sendResponse(true, 'Jadwal berhasil diperbarui.');
}

/**
 * handleDeleteSchedule()
 *
 * Menghapus jadwal. Akan otomatis menghapus sesi absensi terkait (ON DELETE CASCADE).
 *
 * @param int $id ID jadwal yang akan dihapus.
 * @return void
 */
function handleDeleteSchedule(int $id): void
{
    if ($id <= 0) sendResponse(false, 'ID jadwal tidak valid.', null, 400);

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id FROM schedules WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);

    if (!$stmt->fetch()) {
        sendResponse(false, 'Jadwal tidak ditemukan.', null, 404);
    }

    $delStmt = $pdo->prepare('DELETE FROM schedules WHERE id = :id');
    $delStmt->execute([':id' => $id]);

    sendResponse(true, 'Jadwal berhasil dihapus. Semua sesi absensi terkait juga ikut dihapus.');
}

/**
 * handleGetByClass()
 *
 * Mengambil semua jadwal untuk kelas tertentu.
 *
 * @param int $classId ID kelas.
 * @return void
 */
function handleGetByClass(int $classId): void
{
    if ($classId <= 0) sendResponse(false, 'Parameter class_id tidak valid.', null, 400);

    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT sc.id, sc.day, sc.start_time, sc.end_time,
                t.name AS teacher_name, su.name AS subject_name
         FROM schedules sc
         JOIN teachers t  ON sc.teacher_id = t.id
         JOIN subjects su ON sc.subject_id = su.id
         WHERE sc.class_id = :class_id
         ORDER BY FIELD(sc.day,"Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"), sc.start_time'
    );
    $stmt->execute([':class_id' => $classId]);
    $schedules = $stmt->fetchAll();

    sendResponse(true, 'Jadwal kelas berhasil diambil.', [
        'class_id'  => $classId,
        'count'     => count($schedules),
        'schedules' => $schedules,
    ]);
}

/**
 * handleGetByTeacher()
 *
 * Mengambil semua jadwal mengajar untuk guru tertentu.
 *
 * @param int $teacherId ID guru.
 * @return void
 */
function handleGetByTeacher(int $teacherId): void
{
    global $authPayload;
    $pdo  = getDB();

    // 1. Filter HANYA untuk teacher_id milik guru yang sedang login
    if ($authPayload['role'] === 'guru') {
        $userStmt = $pdo->prepare('SELECT username FROM users WHERE id = :id LIMIT 1');
        $userStmt->execute([':id' => $authPayload['user_id']]);
        $user = $userStmt->fetch();
        if ($user) {
            $teacherStmt = $pdo->prepare('SELECT id FROM teachers WHERE name = :name1 OR nip = :name2 OR :name3 LIKE CONCAT("%", SUBSTRING_INDEX(name, " ", 1), "%") LIMIT 1');
            $teacherStmt->execute([
                ':name1' => $user['username'],
                ':name2' => $user['username'],
                ':name3' => $user['username']
            ]);
            $teacher = $teacherStmt->fetch();
            if ($teacher) {
                $teacherId = (int) $teacher['id'];
            }
        }
    }

    if ($teacherId <= 0) sendResponse(false, 'Parameter teacher_id tidak valid.', null, 400);

    // 2. Filter berdasarkan hari ini (Konversi ke bahasa Indonesia)
    $daysMap = [
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu',
        'Sunday'    => 'Minggu'
    ];
    $todayIndo = $daysMap[date('l')] ?? '';

    // 3. Filter berdasarkan waktu saat ini
    $currentTime = date('H:i:s');

    $stmt = $pdo->prepare(
        'SELECT sc.id, sc.day, sc.start_time, sc.end_time,
                sc.class_id, cl.name AS class_name,
                sc.subject_id, su.name AS subject_name,
                sc.teacher_id, t.name AS teacher_name
         FROM schedules sc
         JOIN classes  cl ON sc.class_id  = cl.id
         JOIN subjects su ON sc.subject_id = su.id
         JOIN teachers t  ON sc.teacher_id = t.id
         WHERE sc.teacher_id = :teacher_id
           AND sc.day = :today
         ORDER BY sc.start_time'
    );
    
    $stmt->execute([
        ':teacher_id'   => $teacherId,
        ':today'        => $todayIndo
    ]);
    
    $schedules = $stmt->fetchAll();

    sendResponse(true, 'Jadwal aktif guru berhasil diambil.', [
        'teacher_id' => $teacherId,
        'count'      => count($schedules),
        'schedules'  => $schedules,
    ]);
}

// =============================================================================
// FUNGSI VALIDASI INTERNAL
// =============================================================================

/**
 * validateForeignKey()
 *
 * Memeriksa apakah sebuah ID ada di tabel yang dirujuk.
 * Menghentikan eksekusi dengan error 404 jika tidak ditemukan.
 *
 * @param PDO    $pdo   Instance koneksi PDO.
 * @param string $table Nama tabel yang dicek.
 * @param int    $id    ID yang diperiksa.
 * @param string $label Label human-readable untuk pesan error.
 * @return void
 */
function validateForeignKey(PDO $pdo, string $table, int $id, string $label): void
{
    // Whitelist nama tabel untuk menghindari SQL injection pada nama tabel
    $allowedTables = ['classes', 'teachers', 'subjects'];
    if (!in_array($table, $allowedTables, true)) {
        sendResponse(false, 'Validasi gagal: tabel tidak dikenali.', null, 500);
    }

    $stmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);

    if (!$stmt->fetch()) {
        sendResponse(false, "{$label} dengan ID {$id} tidak ditemukan.", null, 404);
    }
}

/**
 * checkScheduleConflict()
 *
 * Memeriksa apakah ada jadwal yang beririsan waktu untuk entitas tertentu
 * (kelas atau guru) pada hari yang sama.
 * Kondisi overlap: start_a < end_b AND end_a > start_b
 *
 * @param PDO    $pdo       Instance koneksi PDO.
 * @param int    $entityId  ID kelas atau guru.
 * @param string $day       Hari yang dicek.
 * @param string $startTime Jam mulai.
 * @param string $endTime   Jam selesai.
 * @param string $column    Nama kolom FK ('class_id' atau 'teacher_id').
 * @param string $label     Label untuk pesan error.
 * @return void
 */
function checkScheduleConflict(
    PDO    $pdo,
    int    $entityId,
    string $day,
    string $startTime,
    string $endTime,
    string $column,
    string $label
): void {
    $allowedColumns = ['class_id', 'teacher_id'];
    if (!in_array($column, $allowedColumns, true)) return;

    $stmt = $pdo->prepare(
        "SELECT id FROM schedules
         WHERE `{$column}` = :entity_id
           AND day = :day
           AND start_time < :end_time
           AND end_time   > :start_time
         LIMIT 1"
    );
    $stmt->execute([
        ':entity_id'  => $entityId,
        ':day'        => $day,
        ':start_time' => $startTime,
        ':end_time'   => $endTime,
    ]);

    if ($stmt->fetch()) {
        sendResponse(
            false,
            "{$label} sudah memiliki jadwal yang bertabrakan pada hari {$day} antara {$startTime} - {$endTime}.",
            null,
            409
        );
    }
}
