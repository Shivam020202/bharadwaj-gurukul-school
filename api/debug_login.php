<?php
// Debug login flow
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../api/config.php';

$db = new SupabaseDB();

// 1. Check if admin exists
$result = $db->select(TABLE_ADMINS, ['username' => 'eq.admin2']);
echo "Step 1 - Admin lookup:\n";
echo "Status: " . $result['status'] . "\n";
echo "Data: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n\n";

if ($result['status'] === 200 && !empty($result['data'])) {
    $admin = $result['data'][0];
    echo "Found admin: " . $admin['username'] . "\n";
    echo "Password hash: " . $admin['password_hash'] . "\n\n";

    // 2. Verify password
    $password = 'Admin@2026';
    $valid = password_verify($password, $admin['password_hash']);
    echo "Step 2 - Password verify: " . ($valid ? 'PASS' : 'FAIL') . "\n\n";

    // 3. Test session
    session_start();
    echo "Step 3 - Session started: " . (session_status() === PHP_SESSION_ACTIVE ? 'OK' : 'FAIL') . "\n";
    echo "Session ID: " . session_id() . "\n\n";

    // 4. Test cURL to auth.php
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'http://localhost/api/auth.php?action=login',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'username' => 'admin2',
            'password' => 'Admin@2026'
        ]),
        CURLOPT_COOKIEJAR => __DIR__ . '/debug_cookies.txt',
        CURLOPT_COOKIEFILE => __DIR__ . '/debug_cookies.txt',
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Step 4 - cURL to auth.php:\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
} else {
    echo "Admin not found!\n";
}
