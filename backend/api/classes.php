<?php
// =============================================================================
// SMART ATTENDANCE SYSTEM (SAS) - SMK
// File   : backend/api/classes.php
// Tujuan : CRUD data kelas (classes) dan mata pelajaran (subjects).
//
// Endpoint Kelas:
//   GET    /api/classes.php?action=list              → Daftar kelas
//   POST   /api/classes.php?action=create            → Tambah kelas
//   PUT    /api/classes.php?action=update&id={id}   → Update kelas
//   DELETE /api/classes.php?action=delete&id={id}   → Hapus kelas
//
// Endpoint Mata Pelajaran:
//   GET    /api/classes.php?action=list_subjects
//   POST   /api/classes.php?action=create_subject
//   PUT    /api/classes.php?action=update_subject&id={id}
//   DELETE /api/classes.php?action=delete_subject&id={id}
// =============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

setCorsHeaders();

$action      = $_GET['action'] ?? '';
$method      = $_SERVER['REQUEST_METHOD'];
$authPayload = verifyToken();

switch ($action) {
    // ── KELAS ──
    case 'list':
        if ($method !== 'GET') sendResponse(false, 'Gunakan GET.', null, 405);
        handleListClasses();
        break;

    case 'create':
        if ($method !== 'POST') sendResponse(false, 'Gunakan POST.', null, 405);
        requireRole('tata_usaha');
        handleCreateClass();
        break;

    case 'update':
        if ($method !== 'PUT') sendResponse(false, 'Gunakan PUT.', null, 405);
        requireRole('tata_usaha');
        handleUpdateClass((int) ($_GET['id'] ?? 0));
        break;

    case 'delete':
        if ($method !== 'DELETE') sendResponse(false, 'Gunakan DELETE.', null, 405);
        requireRole('tata_usaha');
        handleDeleteClass((int) ($_GET['id'] ?? 0));
        break;

    // ── MATA PELAJARAN ──
    case 'list_subjects':
        if ($method !== 'GET') sendResponse(false, 'Gunakan GET.', null, 405);
        handleListSubjects();
        break;

    case 'create_subject':
        if ($method !== 'POST') sendResponse(false, 'Gunakan POST.', null, 405);
        requireRole('tata_usaha');
        handleCreateSubject();
        break;

    case 'update_subject':
        if ($method !== 'PUT') sendResponse(false, 'Gunakan PUT.', null, 405);
        requireRole('tata_usaha');
        handleUpdateSubject((int) ($_GET['id'] ?? 0));
        break;

    case 'delete_subject':
        if ($method !== 'DELETE') sendResponse(false, 'Gunakan DELETE.', null, 405);
        requireRole('tata_usaha');
        handleDeleteSubject((int) ($_GET['id'] ?? 0));
        break;

    default:
        sendResponse(false, 'Action tidak valid.', null, 400);
}

// =============================================================================
// KELAS
// =============================================================================

function handleListClasses(): void
{
    $pdo  = getDB();
    $stmt = $pdo->query(
        'SELECT c.id, c.name, c.grade, c.major,
                (SELECT COUNT(s.id) FROM students s WHERE s.class_id = c.id) AS student_count,
                c.created_at
         FROM classes c
         ORDER BY c.name ASC'
    );
    sendResponse(true, 'Data kelas berhasil diambil.', [
        'count'   => $stmt->rowCount(),
        'classes' => $stmt->fetchAll(),
    ]);
}

function handleCreateClass(): void
{
    $body  = getRequestBody();
    $name  = trim($body['name']  ?? '');
    $grade = trim($body['grade'] ?? '');
    $major = trim($body['major'] ?? '');

    if (empty($name)) sendResponse(false, 'Nama kelas wajib diisi.', null, 400);

    $pdo = getDB();
    $check = $pdo->prepare('SELECT id FROM classes WHERE name = :name LIMIT 1');
    $check->execute([':name' => $name]);
    if ($check->fetch()) sendResponse(false, "Kelas '{$name}' sudah terdaftar.", null, 409);

    $stmt = $pdo->prepare('INSERT INTO classes (name, grade, major) VALUES (:name, :grade, :major)');
    $stmt->execute([':name' => $name, ':grade' => $grade, ':major' => $major]);
    sendResponse(true, 'Kelas baru berhasil ditambahkan.', ['id' => (int) $pdo->lastInsertId()], 201);
}

function handleUpdateClass(int $id): void
{
    if ($id <= 0) sendResponse(false, 'ID tidak valid.', null, 400);
    $body = getRequestBody();
    $pdo  = getDB();

    $check = $pdo->prepare('SELECT id FROM classes WHERE id = :id LIMIT 1');
    $check->execute([':id' => $id]);
    if (!$check->fetch()) sendResponse(false, 'Kelas tidak ditemukan.', null, 404);

    $allowed = ['name', 'grade', 'major'];
    $sets = []; $params = [':id' => $id];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $body)) {
            $sets[] = "`{$f}` = :{$f}";
            $params[":{$f}"] = $body[$f];
        }
    }
    if (empty($sets)) sendResponse(false, 'Tidak ada field untuk diupdate.', null, 400);
    $pdo->prepare('UPDATE classes SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    sendResponse(true, 'Kelas berhasil diperbarui.');
}

function handleDeleteClass(int $id): void
{
    if ($id <= 0) sendResponse(false, 'ID tidak valid.', null, 400);
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT name FROM classes WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $cls = $stmt->fetch();
    if (!$cls) sendResponse(false, 'Kelas tidak ditemukan.', null, 404);

    $pdo->prepare('DELETE FROM classes WHERE id = :id')->execute([':id' => $id]);
    sendResponse(true, "Kelas '{$cls['name']}' berhasil dihapus.");
}

// =============================================================================
// MATA PELAJARAN
// =============================================================================

function handleListSubjects(): void
{
    $pdo  = getDB();
    $stmt = $pdo->query('SELECT id, name, code, created_at FROM subjects ORDER BY name ASC');
    sendResponse(true, 'Data mapel berhasil diambil.', [
        'count'    => $stmt->rowCount(),
        'subjects' => $stmt->fetchAll(),
    ]);
}

function handleCreateSubject(): void
{
    $body = getRequestBody();
    $name = trim($body['name'] ?? '');
    $code = trim($body['code'] ?? '');

    if (empty($name)) sendResponse(false, 'Nama mata pelajaran wajib diisi.', null, 400);

    $pdo = getDB();
    $check = $pdo->prepare('SELECT id FROM subjects WHERE name = :name LIMIT 1');
    $check->execute([':name' => $name]);
    if ($check->fetch()) sendResponse(false, "Mapel '{$name}' sudah terdaftar.", null, 409);

    $stmt = $pdo->prepare('INSERT INTO subjects (name, code) VALUES (:name, :code)');
    $stmt->execute([':name' => $name, ':code' => $code]);
    sendResponse(true, 'Mata pelajaran baru berhasil ditambahkan.', ['id' => (int) $pdo->lastInsertId()], 201);
}

function handleUpdateSubject(int $id): void
{
    if ($id <= 0) sendResponse(false, 'ID tidak valid.', null, 400);
    $body = getRequestBody();
    $pdo  = getDB();

    $check = $pdo->prepare('SELECT id FROM subjects WHERE id = :id LIMIT 1');
    $check->execute([':id' => $id]);
    if (!$check->fetch()) sendResponse(false, 'Mata pelajaran tidak ditemukan.', null, 404);

    $allowed = ['name', 'code'];
    $sets = []; $params = [':id' => $id];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $body)) {
            $sets[] = "`{$f}` = :{$f}";
            $params[":{$f}"] = $body[$f];
        }
    }
    if (empty($sets)) sendResponse(false, 'Tidak ada field untuk diupdate.', null, 400);
    $pdo->prepare('UPDATE subjects SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    sendResponse(true, 'Mata pelajaran berhasil diperbarui.');
}

function handleDeleteSubject(int $id): void
{
    if ($id <= 0) sendResponse(false, 'ID tidak valid.', null, 400);
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT name FROM subjects WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $sub = $stmt->fetch();
    if (!$sub) sendResponse(false, 'Mata pelajaran tidak ditemukan.', null, 404);

    $pdo->prepare('DELETE FROM subjects WHERE id = :id')->execute([':id' => $id]);
    sendResponse(true, "Mapel '{$sub['name']}' berhasil dihapus.");
}
