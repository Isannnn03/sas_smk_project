<?php
require 'backend/config/database.php';

/**
 * Generate QR Code payload dalam format compact.
 * Format: SAS|student_id|nis|signature_16chars
 * Total ~30-40 karakter (vs ~170 karakter format JSON lama)
 */
function generateQrCodePayload(int $studentId, string $nis): string
{
    $secretKey = 'SAS_SMK_SECRET_KEY_2026';
    $dataString = $studentId . '|' . $nis;
    $signature  = substr(hash_hmac('sha256', $dataString, $secretKey), 0, 16);
    return 'SAS|' . $studentId . '|' . $nis . '|' . $signature;
}

$pdo = getDB();

// Regenerate SEMUA QR code siswa ke format compact baru
$stmt = $pdo->query("SELECT id, nis, qr_code FROM students");
$count = 0;

while ($row = $stmt->fetch()) {
    $newQr = generateQrCodePayload((int)$row['id'], $row['nis']);
    $pdo->prepare("UPDATE students SET qr_code = :qr WHERE id = :id")->execute([
        ':qr' => $newQr,
        ':id' => $row['id']
    ]);
    echo "  [{$row['id']}] {$row['nis']} => {$newQr}\n";
    $count++;
}

echo "\n✅ Regenerated $count QR codes to compact format!\n";
