<?php
/**
 * Quick DB connection & schema verification
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

echo "=== Supabase Connection Test ===\n\n";

// Test 1: Can we reach Supabase?
$caBundle = __DIR__ . '/../cacert.pem';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => SUPABASE_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
]);
if (file_exists($caBundle)) {
    curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
}
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_reset($ch);
echo "1. HTTP reachability: ";
if ($code >= 200 && $code < 300) {
    echo "OK (HTTP $code)\n";
} else {
    echo "FAILED (HTTP $code)\n";
    echo "   Raw response: $resp\n";
    exit(1);
}

$db = new SupabaseDB();

// Test 2: List tables via REST
echo "\n2. Checking tables via REST API...\n";
$tables = ['notices', 'admins', 'admin_sessions'];
foreach ($tables as $table) {
    $result = $db->select($table, [], null, 1);
    echo "   - $table: ";
    if ($result['status'] === 200) {
        echo "EXISTS (HTTP 200)\n";
    } elseif ($result['status'] === 401 || $result['status'] === 403) {
        echo "EXISTS but RLS blocked access (HTTP {$result['status']}) — table exists\n";
    } elseif ($result['status'] === 404) {
        echo "NOT FOUND (HTTP 404) — table does not exist\n";
    } else {
        echo "UNEXPECTED (HTTP {$result['status']}): {$result['raw']}\n";
    }
}

// Test 3: Check notices data
echo "\n3. Reading notices table...\n";
$noticeResult = $db->select(TABLE_NOTICES, [], 'created_at.desc', 5);
if ($noticeResult['status'] === 200) {
    $count = count($noticeResult['data'] ?? []);
    echo "   - Found $count notice(s)\n";
    foreach (($noticeResult['data'] ?? []) as $n) {
        echo "     * " . ($n['title'] ?? '(untitled)') . "\n";
    }
} else {
    echo "   - Could not read notices (HTTP {$noticeResult['status']})\n";
}

// Test 4: Check admins data
echo "\n4. Reading admins table...\n";
$adminResult = $db->select(TABLE_ADMINS, []);
if ($adminResult['status'] === 200) {
    $count = count($adminResult['data'] ?? []);
    echo "   - Found $count admin(s)\n";
    foreach (($adminResult['data'] ?? []) as $a) {
        echo "     * username: " . ($a['username'] ?? '?') . " | is_super_admin: " . ($a['is_super_admin'] ? 'yes' : 'no') . "\n";
    }
} else {
    echo "   - Could not read admins (HTTP {$adminResult['status']})\n";
}

// Test 5: Storage bucket check
echo "\n5. Checking Supabase Storage...\n";
$storageCh = curl_init();
curl_setopt_array($storageCh, [
    CURLOPT_URL => SUPABASE_URL . '/storage/v1/bucket',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: ' . SUPABASE_SERVICE_KEY,
    ],
    CURLOPT_TIMEOUT => 15,
]);
$storageResp = curl_exec($storageCh);
$storageCode = curl_getinfo($storageCh, CURLINFO_HTTP_CODE);
curl_close($storageCh);
if ($storageCode === 200) {
    $buckets = json_decode($storageResp, true);
    echo "   - Storage accessible (HTTP 200)\n";
    if (is_array($buckets)) {
        foreach ($buckets as $b) {
            echo "     * bucket: " . ($b['name'] ?? '?') . "\n";
        }
        if (empty($buckets)) {
            echo "   - No buckets found. You need to create a 'documents' bucket.\n";
        }
    }
} else {
    echo "   - Storage check returned HTTP $storageCode\n";
    echo "     $storageResp\n";
}

echo "\n=== Done ===\n";
