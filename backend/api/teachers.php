<?php
// =============================================================================
// SMART ATTENDANCE SYSTEM (SAS) - SMK
// File   : backend/api/teachers.php
// Tujuan : CRUD data guru.
//
// Endpoint:
//   GET    /api/teachers.php?action=list              → Daftar semua guru
//   GET    /api/teachers.php?action=detail&id={id}   → Detail satu guru
//   POST   /api/teachers.php?action=create            → Tambah guru baru
//   PUT    /api/teachers.php?action=update&id={id}   → Update guru
//   DELETE /api/teachers.php?action=delete&id={id}   → Hapus guru
// =============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

setCorsHeaders();

$action      = $_GET['action'] ?? '';
$method      = $_SERVER['REQUEST_METHOD'];
$authPayload = verifyToken();

switch ($action) {
    case 'list':
        if ($method !== 'GET') sendResponse(false, 'Gunakan method GET.', null, 405);
        handleListTeachers();
        break;

    case 'detail':
        if ($method !== 'GET') sendResponse(false, 'Gunakan method GET.', null, 405);
        handleGetTeacherDetail((int) ($_GET['id'] ?? 0));
        break;

    case 'create':
        if ($method !== 'POST') sendResponse(false, 'Gunakan method POST.', null, 405);
        requireRole('tata_usaha');
        handleCreateTeacher();
        break;

    case 'update':
        if ($method !== 'PUT') sendResponse(false, 'Gunakan method PUT.', null, 405);
        requireRole('tata_usaha');
        handleUpdateTeacher((int) ($_GET['id'] ?? 0));
        break;

    case 'delete':
        if ($method !== 'DELETE') sendResponse(false, 'Gunakan method DELETE.', null, 405);
        requireRole('tata_usaha');
        handleDeleteTeacher((int) ($_GET['id'] ?? 0));
        break;

    default:
        sendResponse(false, 'Action tidak valid.', null, 400);
}

function handleListTeachers(): void
{
    $pdo  = getDB();
    $page  = max(1, (int) ($_GET['page'] ?? 1));
    $limit = max(1, (int) ($_GET['limit'] ?? 50));
    $offset = ($page - 1) * $limit;

    $totalItems = (int) $pdo->query('SELECT COUNT(id) FROM teachers')->fetchColumn();
    $totalPages = ceil($totalItems / $limit);

    $stmt = $pdo->prepare('SELECT id, nip, name, email, phone, status, created_at FROM teachers ORDER BY name ASC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    sendResponse(true, 'Data guru berhasil diambil.', [
        'pagination' => ['total_items' => $totalItems, 'total_pages' => $totalPages, 'current_page' => $page, 'limit' => $limit],
        'count'    => $stmt->rowCount(),
        'teachers' => $stmt->fetchAll(),
    ]);
}

function handleGetTeacherDetail(int $id): void
{
    if ($id <= 0) sendResponse(false, 'ID tidak valid.', null, 400);
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, nip, name, email, phone, status, created_at, updated_at FROM teachers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $teacher = $stmt->fetch();
    if (!$teacher) sendResponse(false, 'Guru tidak ditemukan.', null, 404);
    sendResponse(true, 'Detail guru berhasil diambil.', $teacher);
}

function handleCreateTeacher(): void
{
    $body     = getRequestBody();
    $nip      = trim($body['nip']   ?? '');
    $password = $body['password'] ?? '';
    $name     = trim($body['name']  ?? '');
    $email    = trim($body['email'] ?? '');
    $phone    = trim($body['phone'] ?? '');
    $status   = $body['status'] ?? 'aktif';

    if (empty($nip) || empty($password) || empty($name)) sendResponse(false, 'NIP, Password, dan nama wajib diisi.', null, 400);
    if (!in_array($status, ['aktif', 'non_aktif'], true)) sendResponse(false, 'Status tidak valid.', null, 400);

    $pdo = getDB();

    $checkStmt = $pdo->prepare('SELECT id FROM teachers WHERE nip = :nip LIMIT 1');
    $checkStmt->execute([':nip' => $nip]);
    if ($checkStmt->fetch()) sendResponse(false, "NIP '{$nip}' sudah terdaftar.", null, 409);

    $checkUser = $pdo->prepare('SELECT id FROM users WHERE username = :name LIMIT 1');
    $checkUser->execute([':name' => $name]);
    if ($checkUser->fetch()) sendResponse(false, "Nama '{$name}' sudah digunakan sebagai username. Harap bedakan nama guru.", null, 409);

    $pdo->beginTransaction();
    try {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $userStmt = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (:username, :password, :role)');
        $userStmt->execute([
            ':username' => $name,
            ':password' => $hashedPassword,
            ':role'     => 'guru'
        ]);

        $stmt = $pdo->prepare('INSERT INTO teachers (nip, name, email, phone, status) VALUES (:nip, :name, :email, :phone, :status)');
        $stmt->execute([':nip' => $nip, ':name' => $name, ':email' => $email, ':phone' => $phone, ':status' => $status]);
        
        $pdo->commit();
        sendResponse(true, 'Guru baru berhasil ditambahkan beserta akun aksesnya.', ['id' => (int) $pdo->lastInsertId()], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        sendResponse(false, 'Gagal menambahkan guru: ' . $e->getMessage(), null, 500);
    }
}

function handleUpdateTeacher(int $id): void
{
    if ($id <= 0) sendResponse(false, 'ID tidak valid.', null, 400);
    $body = getRequestBody();
    $pdo  = getDB();

    $checkStmt = $pdo->prepare('SELECT id, nip, name FROM teachers WHERE id = :id LIMIT 1');
    $checkStmt->execute([':id' => $id]);
    $teacher = $checkStmt->fetch();
    if (!$teacher) sendResponse(false, 'Guru tidak ditemukan.', null, 404);

    $pdo->beginTransaction();
    try {
        $allowed    = ['name', 'email', 'phone', 'status'];
        $setClauses = [];
        $params     = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $setClauses[]        = "`{$field}` = :{$field}";
                $params[":{$field}"] = $body[$field];
            }
        }

        $hasUpdate = false;

        if (!empty($setClauses)) {
            $pdo->prepare('UPDATE teachers SET ' . implode(', ', $setClauses) . ' WHERE id = :id')->execute($params);
            $hasUpdate = true;
        }

        // Update username jika nama guru diubah
        if (array_key_exists('name', $body) && !empty($body['name']) && $body['name'] !== $teacher['name']) {
            $pdo->prepare('UPDATE users SET username = :newname WHERE username = :oldname AND role = "guru"')->execute([
                ':newname' => trim($body['name']),
                ':oldname' => $teacher['name']
            ]);
            $hasUpdate = true;
        }

        if (!empty($body['password'])) {
            $hashedPassword = password_hash($body['password'], PASSWORD_BCRYPT);
            $currentName = (array_key_exists('name', $body) && !empty($body['name'])) ? trim($body['name']) : $teacher['name'];
            $pdo->prepare('UPDATE users SET password = :password WHERE username = :name AND role = "guru"')->execute([
                ':password' => $hashedPassword,
                ':name'      => $currentName
            ]);
            $hasUpdate = true;
        }

        if (!$hasUpdate) {
            $pdo->rollBack();
            sendResponse(false, 'Tidak ada field yang valid untuk diupdate.', null, 400);
        }

        $pdo->commit();
        sendResponse(true, 'Data guru berhasil diperbarui.');
    } catch (Exception $e) {
        $pdo->rollBack();
        sendResponse(false, 'Gagal memperbarui data guru: ' . $e->getMessage(), null, 500);
    }
}

function handleDeleteTeacher(int $id): void
{
    if ($id <= 0) sendResponse(false, 'ID tidak valid.', null, 400);
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT nip, name FROM teachers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $teacher = $stmt->fetch();
    if (!$teacher) sendResponse(false, 'Guru tidak ditemukan.', null, 404);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM teachers WHERE id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM users WHERE username = :name AND role = "guru"')->execute([':name' => $teacher['name']]);
        
        $pdo->commit();
        sendResponse(true, "Guru '{$teacher['name']}' beserta akun aksesnya berhasil dihapus.");
    } catch (Exception $e) {
        $pdo->rollBack();
        sendResponse(false, 'Gagal menghapus guru: ' . $e->getMessage(), null, 500);
    }
}
