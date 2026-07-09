<?php
require 'backend/config/database.php';
$pdo = getDB();
$stmt = $pdo->query("SELECT id, nis, name, qr_code FROM students");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== QR CODE STATUS CHECK ===\n\n";
foreach ($students as $s) {
    echo "ID: {$s['id']} | NIS: {$s['nis']} | Name: {$s['name']}\n";
    $qr = $s['qr_code'];
    $decoded = json_decode($qr, true);
    if ($decoded && isset($decoded['student_id'], $decoded['nis'], $decoded['uuid'], $decoded['signature_hash'])) {
        echo "  Format: VALID JSON\n";
        echo "  student_id: {$decoded['student_id']}\n";
        echo "  nis: {$decoded['nis']}\n";
        echo "  uuid: {$decoded['uuid']}\n";
        echo "  signature_hash: " . substr($decoded['signature_hash'], 0, 20) . "...\n";
        
        // Verify signature
        $secretKey = 'SAS_SMK_SECRET_KEY_2026';
        $dataString = $decoded['student_id'] . '|' . $decoded['nis'] . '|' . $decoded['uuid'];
        $recalc = hash_hmac('sha256', $dataString, $secretKey);
        $sigValid = hash_equals($recalc, $decoded['signature_hash']);
        echo "  Signature Valid: " . ($sigValid ? "YES" : "NO") . "\n";
    } else {
        echo "  Format: INVALID (not JSON or missing keys)\n";
        echo "  Raw QR (first 80 chars): " . substr($qr, 0, 80) . "\n";
    }
    echo "\n";
}
