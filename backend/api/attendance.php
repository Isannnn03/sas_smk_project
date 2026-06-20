<?php
// =============================================================================
// SMART ATTENDANCE SYSTEM (SAS) - SMK
// File   : backend/api/attendance.php
// Tujuan : LOGIKA INTI sistem absensi berbasis QR Code.
//
// Endpoint yang tersedia:
//   POST /api/attendance.php?action=start_session   → Buka sesi absensi baru
//   POST /api/attendance.php?action=scan_qr         → Proses scan QR Code siswa
//   POST /api/attendance.php?action=close_session   → Tutup sesi + Auto-Alpha Engine
//   GET  /api/attendance.php?action=session_status  → Cek status sesi aktif
//   GET  /api/attendance.php?action=session_report  → Laporan per sesi
//   PUT  /api/attendance.php?action=update_status   → Update status absensi manual
//   GET  /api/attendance.php?action=report_by_class → Rekap absensi per kelas
// =============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

setCorsHeaders();

$action      = $_GET['action'] ?? '';
$method      = $_SERVER['REQUEST_METHOD'];
$authPayload = verifyToken(); // Semua endpoint butuh autentikasi

switch ($action) {

    // ------------------------------------------------------------------
    // ACTION: start_session
    // Membuka sesi absensi baru untuk suatu jadwal pada tanggal tertentu.
    // ------------------------------------------------------------------
    case 'start_session':
        if ($method !== 'POST') sendResponse(false, 'Gunakan method POST.', null, 405);
        requireRole(['tata_usaha', 'guru']); // Guru bisa membuka sesi
        handleStartSession();
        break;

    // ------------------------------------------------------------------
    // ACTION: scan_qr
    // Memproses scan QR Code dari aplikasi mobile siswa/petugas.
    // ------------------------------------------------------------------
    case 'scan_qr':
        if ($method !== 'POST') sendResponse(false, 'Gunakan method POST.', null, 405);
        requireRole(['tata_usaha', 'guru']);
        handleScanQr();
        break;

    // ------------------------------------------------------------------
    // ACTION: close_session
    // Menutup sesi dan menjalankan Auto-Alpha Engine untuk siswa yang
    // tidak hadir.
    // ------------------------------------------------------------------
    case 'close_session':
        if ($method !== 'POST') sendResponse(false, 'Gunakan method POST.', null, 405);
        requireRole(['tata_usaha', 'guru']);
        handleCloseSession();
        break;

    // ------------------------------------------------------------------
    // ACTION: session_status
    // Melihat daftar siswa dan status absensi mereka dalam satu sesi.
    // ------------------------------------------------------------------
    case 'session_status':
        if ($method !== 'GET') sendResponse(false, 'Gunakan method GET.', null, 405);
        $sessionId = (int) ($_GET['session_id'] ?? 0);
        handleGetSessionStatus($sessionId);
        break;

    // ------------------------------------------------------------------
    // ACTION: session_report
    // Laporan lengkap rekap kehadiran dalam satu sesi.
    // ------------------------------------------------------------------
    case 'session_report':
        if ($method !== 'GET') sendResponse(false, 'Gunakan method GET.', null, 405);
        $sessionId = (int) ($_GET['session_id'] ?? 0);
        handleGetSessionReport($sessionId);
        break;

    // ------------------------------------------------------------------
    // ACTION: update_status
    // Update status absensi secara manual (izin/sakit).
    // ------------------------------------------------------------------
    case 'update_status':
        if ($method !== 'PUT') sendResponse(false, 'Gunakan method PUT.', null, 405);
        requireRole(['tata_usaha', 'guru']);
        handleUpdateAttendanceStatus();
        break;

    // ------------------------------------------------------------------
    // ACTION: report_by_class
    // Rekap absensi untuk seluruh sesi pada kelas dan rentang tanggal tertentu.
    // ------------------------------------------------------------------
    case 'report_by_class':
        if ($method !== 'GET') sendResponse(false, 'Gunakan method GET.', null, 405);
        handleReportByClass();
        break;

    default:
        sendResponse(false, 'Action tidak valid. Pilih: start_session, scan_qr, close_session, session_status, session_report, update_status, report_by_class.', null, 400);
}

// =============================================================================
// HANDLER 1: START SESSION
// =============================================================================

/**
 * handleStartSession()
 *
 * Membuat record baru di tabel `attendance_sessions` dengan status 'aktif'.
 * Melakukan validasi:
 *   - schedule_id harus ada di database
 *   - Tidak boleh ada sesi aktif lain untuk jadwal & tanggal yang sama
 *
 * Body JSON:
 *   { "schedule_id": 1, "date": "2024-07-15" }  ← date opsional (default: hari ini)
 *
 * @return void
 */
function handleStartSession(): void
{
    $body       = getRequestBody();
    $scheduleId = (int) ($body['schedule_id'] ?? 0);
    // Tanggal default adalah hari ini jika tidak disertakan
    $date       = trim($body['date'] ?? date('Y-m-d'));

    // --- Validasi input ---
    if ($scheduleId <= 0) {
        sendResponse(false, 'Parameter schedule_id wajib diisi dan harus valid.', null, 400);
    }

    // Validasi format tanggal
    $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
        sendResponse(false, "Format tanggal tidak valid. Gunakan format: YYYY-MM-DD (contoh: 2024-07-15).", null, 400);
    }

    $pdo = getDB();

    // --- Validasi schedule_id ada di database ---
    $schedStmt = $pdo->prepare(
        'SELECT sc.id, sc.day, sc.start_time, sc.end_time,
                cl.name AS class_name, su.name AS subject_name, t.name AS teacher_name
         FROM schedules sc
         JOIN classes  cl ON sc.class_id  = cl.id
         JOIN subjects su ON sc.subject_id = su.id
         JOIN teachers t  ON sc.teacher_id = t.id
         WHERE sc.id = :id LIMIT 1'
    );
    $schedStmt->execute([':id' => $scheduleId]);
    $schedule = $schedStmt->fetch();

    if (!$schedule) {
        sendResponse(false, 'Jadwal dengan ID yang diberikan tidak ditemukan.', null, 404);
    }

    // --- Cek apakah sesi untuk jadwal & tanggal ini sudah ada ---
    $existStmt = $pdo->prepare(
        'SELECT id, status FROM attendance_sessions
         WHERE schedule_id = :schedule_id AND date = :date
         LIMIT 1'
    );
    $existStmt->execute([':schedule_id' => $scheduleId, ':date' => $date]);
    $existingSession = $existStmt->fetch();

    if ($existingSession) {
        $msg = $existingSession['status'] === 'aktif'
            ? 'Sesi absensi untuk jadwal dan tanggal ini sudah aktif.'
            : 'Sesi absensi untuk jadwal dan tanggal ini sudah pernah ditutup.';
        sendResponse(false, $msg, ['session_id' => $existingSession['id']], 409);
    }

    // --- Buat sesi baru ---
    $insertStmt = $pdo->prepare(
        'INSERT INTO attendance_sessions (schedule_id, date, status)
         VALUES (:schedule_id, :date, "aktif")'
    );
    $insertStmt->execute([':schedule_id' => $scheduleId, ':date' => $date]);
    $newSessionId = (int) $pdo->lastInsertId();

    sendResponse(true, 'Sesi absensi berhasil dibuka.', [
        'session_id'    => $newSessionId,
        'schedule_info' => $schedule,
        'date'          => $date,
        'status'        => 'aktif',
    ], 201);
}

// =============================================================================
// HANDLER 2: SCAN QR CODE
// =============================================================================

/**
 * handleScanQr()
 *
 * Memproses scan QR Code dari mobile app dan mencatat kehadiran siswa.
 *
 * Alur validasi:
 *   1. Cek session_id ada dan statusnya 'aktif'
 *   2. Cek qr_code cocok dengan siswa yang ada dan statusnya 'aktif'
 *   3. Cek siswa berada di kelas yang sesuai dengan jadwal sesi ini
 *   4. Cek siswa belum scan di sesi yang sama (mencegah duplikasi)
 *   5. Jika semua valid → insert ke attendances dengan status 'hadir'
 *
 * Body JSON:
 *   { "qr_code": "abc123hash...", "session_id": 5 }
 *
 * @return void
 */
function handleScanQr(): void
{
    $body      = getRequestBody();
    $qrCode    = trim($body['qr_code']    ?? '');
    $sessionId = (int) ($body['session_id'] ?? 0);

    // --- Validasi input dasar ---
    if (empty($qrCode) || $sessionId <= 0) {
        sendResponse(false, 'Parameter qr_code dan session_id wajib diisi.', null, 400);
    }

    $pdo = getDB();

    // --- VALIDASI 1: Cek sesi valid dan masih aktif ---
    $sessionStmt = $pdo->prepare(
        'SELECT ase.id, ase.status, ase.date,
                sc.class_id, cl.name AS class_name,
                su.name AS subject_name
         FROM attendance_sessions ase
         JOIN schedules sc ON ase.schedule_id = sc.id
         JOIN classes   cl ON sc.class_id     = cl.id
         JOIN subjects  su ON sc.subject_id   = su.id
         WHERE ase.id = :session_id
         LIMIT 1'
    );
    $sessionStmt->execute([':session_id' => $sessionId]);
    $session = $sessionStmt->fetch();

    if (!$session) {
        sendResponse(false, 'Sesi absensi tidak ditemukan.', null, 404);
    }

    if ($session['status'] !== 'aktif') {
        sendResponse(false, 'Sesi absensi ini sudah ditutup. Scan QR Code tidak dapat diproses.', null, 403);
    }

    // --- VALIDASI 2: Parsing JSON dan Cek Signature (Anti-Forgery) ---
    $qrData = json_decode($qrCode, true);
    
    // Pastikan QR Code mengandung semua key yang diwajibkan
    if (!$qrData || !isset($qrData['student_id'], $qrData['nis'], $qrData['uuid'], $qrData['signature_hash'])) {
        sendResponse(false, 'Format QR Code tidak valid atau rusak.', null, 400);
    }

    $secretKey = 'SAS_SMK_SECRET_KEY_2026';
    
    // Rekonstruksi string data untuk divalidasi
    $dataString = $qrData['student_id'] . '|' . $qrData['nis'] . '|' . $qrData['uuid'];
    
    // Rekalkulasi hash menggunakan secret key server
    $recalculatedSignature = hash_hmac('sha256', $dataString, $secretKey);

    // Cocokkan hasil hash server dengan hash bawaan QR Code menggunakan perbandingan constant-time
    if (!hash_equals($recalculatedSignature, $qrData['signature_hash'])) {
        sendResponse(false, 'QR Code palsu atau tidak sah! Signature tidak valid.', null, 403);
    }

    // --- Lanjutkan pengecekan eksistensi siswa ke Database ---
    $studentStmt = $pdo->prepare(
        'SELECT id, nis, name, class_id, status
         FROM students
         WHERE id = :id AND nis = :nis
         LIMIT 1'
    );
    $studentStmt->execute([
        ':id'  => $qrData['student_id'],
        ':nis' => $qrData['nis']
    ]);
    $student = $studentStmt->fetch();

    if (!$student) {
        sendResponse(false, 'Data siswa yang terikat di QR ini tidak ditemukan dalam database.', null, 404);
    }

    if ($student['status'] !== 'aktif') {
        sendResponse(false, "Siswa '{$student['name']}' berstatus non-aktif dan tidak dapat melakukan absensi.", null, 403);
    }

    // --- VALIDASI 3: Cek siswa berada di kelas yang sesuai dengan jadwal ---
    if ((int) $student['class_id'] !== (int) $session['class_id']) {
        sendResponse(
            false,
            "Siswa '{$student['name']}' bukan anggota kelas '{$session['class_name']}'. Scan ditolak.",
            null,
            403
        );
    }

    // --- VALIDASI 4: Cek apakah siswa sudah scan di sesi ini (cegah duplikasi) ---
    $dupStmt = $pdo->prepare(
        'SELECT id, status, scan_time
         FROM attendances
         WHERE session_id = :session_id AND student_id = :student_id
         LIMIT 1'
    );
    $dupStmt->execute([':session_id' => $sessionId, ':student_id' => $student['id']]);
    $existingAttendance = $dupStmt->fetch();

    if ($existingAttendance) {
        sendResponse(false, "Siswa '{$student['name']}' sudah melakukan absensi pada sesi ini dengan status '{$existingAttendance['status']}' pada {$existingAttendance['scan_time']}.", [
            'attendance_id' => $existingAttendance['id'],
            'current_status'=> $existingAttendance['status'],
        ], 409);
    }

    // --- SEMUA VALID: Insert record kehadiran ---
    $scanTime = date('Y-m-d H:i:s');
    $insertStmt = $pdo->prepare(
        'INSERT INTO attendances (session_id, student_id, status, scan_time)
         VALUES (:session_id, :student_id, "hadir", :scan_time)'
    );
    $insertStmt->execute([
        ':session_id' => $sessionId,
        ':student_id' => $student['id'],
        ':scan_time'  => $scanTime,
    ]);

    sendResponse(true, "✅ Berhasil! Kehadiran '{$student['name']}' tercatat.", [
        'attendance_id' => (int) $pdo->lastInsertId(),
        'student'       => [
            'id'   => $student['id'],
            'nis'  => $student['nis'],
            'name' => $student['name'],
        ],
        'session_info'  => [
            'id'          => $sessionId,
            'class_name'  => $session['class_name'],
            'subject_name'=> $session['subject_name'],
            'date'        => $session['date'],
        ],
        'status'    => 'hadir',
        'scan_time' => $scanTime,
    ], 201);
}

// =============================================================================
// HANDLER 3: CLOSE SESSION + AUTO-ALPHA ENGINE
// =============================================================================

/**
 * handleCloseSession()
 *
 * Menutup sesi absensi yang sedang aktif dan menjalankan "Auto-Alpha Engine".
 *
 * Alur kerja:
 *   1. Validasi session_id ada dan statusnya 'aktif'
 *   2. Update status sesi menjadi 'ditutup'
 *   3. Auto-Alpha Engine:
 *      a. Ambil semua student_id di kelas yang bersangkutan (dari jadwal)
 *      b. Ambil student_id yang sudah hadir di sesi ini
 *      c. Hitung selisihnya (siswa yang absen)
 *      d. Batch insert ke tabel attendances dengan status 'alpha'
 *
 * Body JSON:
 *   { "session_id": 5 }
 *
 * @return void
 */
function handleCloseSession(): void
{
    $body      = getRequestBody();
    $sessionId = (int) ($body['session_id'] ?? 0);

    if ($sessionId <= 0) {
        sendResponse(false, 'Parameter session_id wajib diisi.', null, 400);
    }

    $pdo = getDB();

    // --- Ambil data sesi beserta class_id dari jadwal ---
    $sessionStmt = $pdo->prepare(
        'SELECT ase.id, ase.status, ase.date, ase.schedule_id,
                sc.class_id,
                cl.name AS class_name,
                su.name AS subject_name,
                t.name  AS teacher_name
         FROM attendance_sessions ase
         JOIN schedules sc ON ase.schedule_id = sc.id
         JOIN classes   cl ON sc.class_id     = cl.id
         JOIN subjects  su ON sc.subject_id   = su.id
         JOIN teachers  t  ON sc.teacher_id   = t.id
         WHERE ase.id = :session_id
         LIMIT 1'
    );
    $sessionStmt->execute([':session_id' => $sessionId]);
    $session = $sessionStmt->fetch();

    if (!$session) {
        sendResponse(false, 'Sesi absensi tidak ditemukan.', null, 404);
    }

    if ($session['status'] !== 'aktif') {
        sendResponse(false, 'Sesi ini sudah ditutup sebelumnya.', ['session_id' => $sessionId], 409);
    }

    // Gunakan transaksi untuk memastikan atomicity:
    // close_session + auto-alpha harus berhasil semua atau tidak sama sekali.
    $pdo->beginTransaction();

    try {
        // --- LANGKAH 1: Update status sesi menjadi 'ditutup' ---
        $closeStmt = $pdo->prepare(
            'UPDATE attendance_sessions SET status = "ditutup" WHERE id = :session_id'
        );
        $closeStmt->execute([':session_id' => $sessionId]);

        // --- LANGKAH 2: AUTO-ALPHA ENGINE ---

        // 2a. Ambil semua student_id yang aktif di kelas ini
        $allStudentsStmt = $pdo->prepare(
            'SELECT id FROM students
             WHERE class_id = :class_id AND status = "aktif"'
        );
        $allStudentsStmt->execute([':class_id' => $session['class_id']]);
        $allStudentIds = array_column($allStudentsStmt->fetchAll(), 'id');

        // 2b. Ambil student_id yang SUDAH punya record di sesi ini (hadir/apapun)
        $presentStmt = $pdo->prepare(
            'SELECT student_id FROM attendances WHERE session_id = :session_id'
        );
        $presentStmt->execute([':session_id' => $sessionId]);
        $presentStudentIds = array_column($presentStmt->fetchAll(), 'student_id');

        // 2c. Hitung siswa yang tidak hadir (selisih): array_diff
        $absentStudentIds = array_diff($allStudentIds, $presentStudentIds);
        $alphaCount       = count($absentStudentIds);

        // 2d. Batch insert 'alpha' untuk semua siswa yang tidak hadir
        if ($alphaCount > 0) {
            // Siapkan prepared statement untuk batch insert
            $insertAlphaStmt = $pdo->prepare(
                'INSERT INTO attendances (session_id, student_id, status, scan_time)
                 VALUES (:session_id, :student_id, "alpha", NULL)'
            );

            foreach ($absentStudentIds as $absentStudentId) {
                $insertAlphaStmt->execute([
                    ':session_id' => $sessionId,
                    ':student_id' => (int) $absentStudentId,
                ]);
            }
        }

        // Commit transaksi jika semua langkah berhasil
        $pdo->commit();

        // --- Hitung ringkasan final untuk respons ---
        $summaryStmt = $pdo->prepare(
            'SELECT status, COUNT(*) AS total
             FROM attendances
             WHERE session_id = :session_id
             GROUP BY status'
        );
        $summaryStmt->execute([':session_id' => $sessionId]);
        $rawSummary = $summaryStmt->fetchAll();

        // Format summary menjadi key-value yang mudah dibaca
        $summary = ['hadir' => 0, 'alpha' => 0, 'izin' => 0, 'sakit' => 0, 'terlambat' => 0];
        foreach ($rawSummary as $row) {
            $summary[$row['status']] = (int) $row['total'];
        }

        sendResponse(true, 'Sesi berhasil ditutup. Auto-Alpha Engine telah dijalankan.', [
            'session_id'         => $sessionId,
            'class_name'         => $session['class_name'],
            'subject_name'       => $session['subject_name'],
            'date'               => $session['date'],
            'auto_alpha_applied' => $alphaCount,
            'summary'            => $summary,
            'total_students'     => count($allStudentIds),
        ]);

    } catch (Exception $e) {
        // Rollback semua perubahan jika terjadi error di tengah proses
        $pdo->rollBack();
        error_log('[SAS-CLOSE-SESSION-ERROR] ' . $e->getMessage());
        sendResponse(false, 'Terjadi kesalahan saat menutup sesi. Perubahan dibatalkan.', null, 500);
    }
}

// =============================================================================
// HANDLER 4: SESSION STATUS (Live Monitor)
// =============================================================================

/**
 * handleGetSessionStatus()
 *
 * Menampilkan status absensi real-time semua siswa dalam satu sesi.
 * Berguna untuk tampilan live di dashboard saat sesi sedang berlangsung.
 *
 * @param int $sessionId ID sesi.
 * @return void
 */
function handleGetSessionStatus(int $sessionId): void
{
    if ($sessionId <= 0) sendResponse(false, 'Parameter session_id tidak valid.', null, 400);

    $pdo = getDB();

    // Ambil info sesi
    $sessionStmt = $pdo->prepare(
        'SELECT ase.id, ase.status, ase.date,
                sc.class_id, cl.name AS class_name,
                su.name AS subject_name, t.name AS teacher_name,
                sc.start_time, sc.end_time
         FROM attendance_sessions ase
         JOIN schedules sc ON ase.schedule_id = sc.id
         JOIN classes   cl ON sc.class_id     = cl.id
         JOIN subjects  su ON sc.subject_id   = su.id
         JOIN teachers  t  ON sc.teacher_id   = t.id
         WHERE ase.id = :id LIMIT 1'
    );
    $sessionStmt->execute([':id' => $sessionId]);
    $session = $sessionStmt->fetch();

    if (!$session) sendResponse(false, 'Sesi tidak ditemukan.', null, 404);

    // Ambil semua siswa di kelas + status absensi mereka (LEFT JOIN)
    $studentsStmt = $pdo->prepare(
        'SELECT s.id AS student_id, s.nis, s.name AS student_name,
                COALESCE(a.status, "belum_scan") AS attendance_status,
                a.scan_time,
                a.id AS attendance_id
         FROM students s
         LEFT JOIN attendances a ON s.id = a.student_id AND a.session_id = :session_id
         WHERE s.class_id = :class_id AND s.status = "aktif"
         ORDER BY
           CASE COALESCE(a.status, "belum_scan")
             WHEN "hadir"     THEN 1
             WHEN "terlambat" THEN 2
             WHEN "izin"      THEN 3
             WHEN "sakit"     THEN 4
             WHEN "alpha"     THEN 5
             ELSE 6
           END,
           s.name ASC'
    );
    $studentsStmt->execute([
        ':session_id' => $sessionId,
        ':class_id'   => $session['class_id'],
    ]);
    $students = $studentsStmt->fetchAll();

    // Hitung statistik ringkas
    $stats = ['hadir' => 0, 'terlambat' => 0, 'izin' => 0, 'sakit' => 0, 'alpha' => 0, 'belum_scan' => 0];
    foreach ($students as $s) {
        $stats[$s['attendance_status']] = ($stats[$s['attendance_status']] ?? 0) + 1;
    }

    sendResponse(true, 'Status sesi berhasil diambil.', [
        'session'        => $session,
        'statistics'     => $stats,
        'total_students' => count($students),
        'attendances'    => $students,
    ]);
}

// =============================================================================
// HANDLER 5: SESSION REPORT
// =============================================================================

/**
 * handleGetSessionReport()
 *
 * Laporan rekap final setelah sesi ditutup.
 * Menampilkan statistik dan daftar detail per status kehadiran.
 *
 * @param int $sessionId ID sesi.
 * @return void
 */
function handleGetSessionReport(int $sessionId): void
{
    if ($sessionId <= 0) sendResponse(false, 'Parameter session_id tidak valid.', null, 400);

    $pdo = getDB();

    // Ambil info sesi
    $sessionStmt = $pdo->prepare(
        'SELECT ase.id, ase.status, ase.date,
                cl.name AS class_name,
                su.name AS subject_name,
                t.name  AS teacher_name
         FROM attendance_sessions ase
         JOIN schedules sc ON ase.schedule_id = sc.id
         JOIN classes   cl ON sc.class_id     = cl.id
         JOIN subjects  su ON sc.subject_id   = su.id
         JOIN teachers  t  ON sc.teacher_id   = t.id
         WHERE ase.id = :id LIMIT 1'
    );
    $sessionStmt->execute([':id' => $sessionId]);
    $session = $sessionStmt->fetch();
    if (!$session) sendResponse(false, 'Sesi tidak ditemukan.', null, 404);

    // Ambil detail rekap absensi
    $reportStmt = $pdo->prepare(
        'SELECT a.id, a.status, a.scan_time,
                s.id AS student_id, s.nis, s.name AS student_name
         FROM attendances a
         JOIN students s ON a.student_id = s.id
         WHERE a.session_id = :session_id
         ORDER BY a.status, s.name'
    );
    $reportStmt->execute([':session_id' => $sessionId]);
    $records = $reportStmt->fetchAll();

    // Kelompokkan per status
    $grouped = [];
    $summary = [];
    foreach ($records as $rec) {
        $status = $rec['status'];
        if (!isset($grouped[$status])) {
            $grouped[$status] = [];
            $summary[$status] = 0;
        }
        $grouped[$status][] = $rec;
        $summary[$status]++;
    }

    sendResponse(true, 'Laporan sesi berhasil diambil.', [
        'session'   => $session,
        'summary'   => $summary,
        'total'     => count($records),
        'details'   => $grouped,
    ]);
}

// =============================================================================
// HANDLER 6: UPDATE ATTENDANCE STATUS (Manual Override)
// =============================================================================

/**
 * handleUpdateAttendanceStatus()
 *
 * Mengubah status kehadiran secara manual (contoh: alpha → izin, atau hadir → terlambat).
 * Berguna ketika siswa membawa surat izin/sakit setelah sesi ditutup.
 *
 * Body JSON:
 *   { "attendance_id": 12, "status": "izin" }
 *   ATAU
 *   { "session_id": 5, "student_id": 3, "status": "sakit" }
 *
 * @return void
 */
function handleUpdateAttendanceStatus(): void
{
    $body          = getRequestBody();
    $newStatus     = trim($body['status'] ?? '');
    $attendanceId  = (int) ($body['attendance_id'] ?? 0);

    $allowedStatuses = ['hadir', 'alpha', 'izin', 'sakit', 'terlambat'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        sendResponse(false, 'Status tidak valid. Pilih: ' . implode(', ', $allowedStatuses) . '.', null, 400);
    }

    $pdo = getDB();

    // Metode 1: via attendance_id langsung
    if ($attendanceId > 0) {
        $checkStmt = $pdo->prepare('SELECT id FROM attendances WHERE id = :id LIMIT 1');
        $checkStmt->execute([':id' => $attendanceId]);
        if (!$checkStmt->fetch()) {
            sendResponse(false, 'Record absensi tidak ditemukan.', null, 404);
        }

        $updateStmt = $pdo->prepare(
            'UPDATE attendances SET status = :status WHERE id = :id'
        );
        $updateStmt->execute([':status' => $newStatus, ':id' => $attendanceId]);

    } else {
        // Metode 2: via session_id + student_id
        $sessionId = (int) ($body['session_id']  ?? 0);
        $studentId = (int) ($body['student_id']  ?? 0);

        if ($sessionId <= 0 || $studentId <= 0) {
            sendResponse(false, 'Sediakan attendance_id ATAU kombinasi session_id + student_id.', null, 400);
        }

        $checkStmt = $pdo->prepare(
            'SELECT id FROM attendances WHERE session_id = :sid AND student_id = :stid LIMIT 1'
        );
        $checkStmt->execute([':sid' => $sessionId, ':stid' => $studentId]);
        $record = $checkStmt->fetch();

        if (!$record) {
            sendResponse(false, 'Record absensi untuk kombinasi session_id dan student_id tidak ditemukan.', null, 404);
        }

        $updateStmt = $pdo->prepare(
            'UPDATE attendances SET status = :status WHERE id = :id'
        );
        $updateStmt->execute([':status' => $newStatus, ':id' => $record['id']]);
    }

    sendResponse(true, "Status absensi berhasil diubah menjadi '{$newStatus}'.");
}

// =============================================================================
// HANDLER 7: REPORT BY CLASS (Rekapitulasi)
// =============================================================================

/**
 * handleReportByClass()
 *
 * Menghasilkan laporan rekapitulasi absensi per siswa untuk kelas tertentu
 * dalam rentang tanggal yang ditentukan.
 *
 * Query params:
 *   ?class_id=1&start_date=2024-07-01&end_date=2024-07-31
 *
 * @return void
 */
function handleReportByClass(): void
{
    $classId   = (int) ($_GET['class_id']   ?? 0);
    $startDate = trim($_GET['start_date'] ?? '');
    $endDate   = trim($_GET['end_date']   ?? '');

    if ($classId <= 0 || empty($startDate) || empty($endDate)) {
        sendResponse(false, 'Parameter class_id, start_date, dan end_date wajib diisi.', null, 400);
    }

    $pdo = getDB();

    // Rekap total hadir/alpha/izin/sakit/terlambat per siswa dalam rentang tanggal
    $reportStmt = $pdo->prepare(
        'SELECT s.id AS student_id, s.nis, s.name AS student_name,
                SUM(CASE WHEN a.status = "hadir"     THEN 1 ELSE 0 END) AS total_hadir,
                SUM(CASE WHEN a.status = "terlambat" THEN 1 ELSE 0 END) AS total_terlambat,
                SUM(CASE WHEN a.status = "izin"      THEN 1 ELSE 0 END) AS total_izin,
                SUM(CASE WHEN a.status = "sakit"     THEN 1 ELSE 0 END) AS total_sakit,
                SUM(CASE WHEN a.status = "alpha"     THEN 1 ELSE 0 END) AS total_alpha,
                COUNT(a.id)                                               AS total_pertemuan
         FROM students s
         LEFT JOIN attendances a       ON s.id             = a.student_id
         LEFT JOIN attendance_sessions ase ON a.session_id = ase.id
         WHERE s.class_id = :class_id
           AND s.status   = "aktif"
           AND (ase.date IS NULL OR (ase.date >= :start_date AND ase.date <= :end_date))
         GROUP BY s.id, s.nis, s.name
         ORDER BY s.name ASC'
    );
    $reportStmt->execute([
        ':class_id'   => $classId,
        ':start_date' => $startDate,
        ':end_date'   => $endDate,
    ]);
    $students = $reportStmt->fetchAll();

    // Hitung total sesi dalam rentang ini untuk kelas yang bersangkutan
    $sessionCountStmt = $pdo->prepare(
        'SELECT COUNT(ase.id) AS total_sessions
         FROM attendance_sessions ase
         JOIN schedules sc ON ase.schedule_id = sc.id
         WHERE sc.class_id = :class_id
           AND ase.date >= :start_date
           AND ase.date <= :end_date
           AND ase.status = "ditutup"'
    );
    $sessionCountStmt->execute([
        ':class_id'   => $classId,
        ':start_date' => $startDate,
        ':end_date'   => $endDate,
    ]);
    $totalSessions = (int) $sessionCountStmt->fetchColumn();

    sendResponse(true, 'Laporan rekapitulasi absensi kelas berhasil dibuat.', [
        'class_id'        => $classId,
        'period'          => ['start' => $startDate, 'end' => $endDate],
        'total_sessions'  => $totalSessions,
        'total_students'  => count($students),
        'report'          => $students,
    ]);
}
