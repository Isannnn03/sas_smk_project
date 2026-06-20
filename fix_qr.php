<?php
require 'backend/config/database.php';

function generateQrCodePayload(int $studentId, string $nis): string
{
    $secretKey = 'SAS_SMK_SECRET_KEY_2026';
    $uuid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $dataString = $studentId . '|' . $nis . '|' . $uuid;
    $signature  = hash_hmac('sha256', $dataString, $secretKey);
    $payload = [
        'student_id'     => $studentId,
        'nis'            => $nis,
        'uuid'           => $uuid,
        'signature_hash' => $signature
    ];
    return json_encode($payload);
}

$pdo = getDB();
$stmt = $pdo->query("SELECT id, nis FROM students WHERE qr_code NOT LIKE '{%'");
$count = 0;

while ($row = $stmt->fetch()) {
    $newQr = generateQrCodePayload($row['id'], $row['nis']);
    $pdo->prepare("UPDATE students SET qr_code = :qr WHERE id = :id")->execute([
        ':qr' => $newQr,
        ':id' => $row['id']
    ]);
    $count++;
}

echo "Fixed $count legacy QR codes!\n";
