<?php
/**
 * Local Database and File Upload Verification Script
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Local Flat-File Database & Upload Test ===\n\n";

$db = new SupabaseDB();

// Test 1: Check admin initialization
echo "1. Checking Admin table...\n";
$adminResult = $db->select('admins', ['username' => 'eq.admin2']);
if ($adminResult['status'] === 200 && !empty($adminResult['data'])) {
    $admin = $adminResult['data'][0];
    echo "   [PASS] Found initialized default admin user: " . $admin['username'] . "\n";
    echo "          Name: " . $admin['full_name'] . "\n";
    echo "          Email: " . $admin['email'] . "\n";
} else {
    echo "   [FAIL] Admin table check failed or admin not found! Response: " . json_encode($adminResult) . "\n";
    exit(1);
}

// Test 2: Insert a test notice
echo "\n2. Testing Insert notice...\n";
$testNotice = [
    'title' => 'Test Notice Title ' . uniqid(),
    'content' => 'This is a test notice content. It is stored locally in the flat-file database.',
    'priority' => 'high',
    'is_active' => true,
    'created_by' => 'admin2'
];

$insertResult = $db->insert('notices', $testNotice);
if ($insertResult['status'] === 201 && !empty($insertResult['data'])) {
    $noticeId = $insertResult['data'][0]['id'];
    echo "   [PASS] Notice created with ID: " . $noticeId . "\n";
} else {
    echo "   [FAIL] Notice creation failed! Response: " . json_encode($insertResult) . "\n";
    exit(1);
}

// Test 3: Select the created notice
echo "\n3. Testing Select notice...\n";
$selectResult = $db->select('notices', ['id' => 'eq.' . $noticeId]);
if ($selectResult['status'] === 200 && !empty($selectResult['data'])) {
    $notice = $selectResult['data'][0];
    echo "   [PASS] Selected notice matches:\n";
    echo "          Title: " . $notice['title'] . "\n";
    echo "          Priority: " . $notice['priority'] . "\n";
    echo "          Active: " . ($notice['is_active'] ? 'Yes' : 'No') . "\n";
} else {
    echo "   [FAIL] Select notice failed! Response: " . json_encode($selectResult) . "\n";
    exit(1);
}

// Test 4: Update the notice
echo "\n4. Testing Update notice...\n";
$updateResult = $db->update('notices', ['priority' => 'urgent', 'title' => 'Updated Test Title'], ['id' => ['eq.' => $noticeId]]);
if ($updateResult['status'] === 200 && !empty($updateResult['data'])) {
    $updatedNotice = $updateResult['data'][0];
    echo "   [PASS] Updated notice details:\n";
    echo "          New Title: " . $updatedNotice['title'] . "\n";
    echo "          New Priority: " . $updatedNotice['priority'] . "\n";
} else {
    echo "   [FAIL] Update notice failed! Response: " . json_encode($updateResult) . "\n";
    exit(1);
}

// Test 5: File upload local simulation
echo "\n5. Testing Local File Upload & public URL generation...\n";
$testFileData = "Dummy PDF Content for testing upload of notice circular.";
$testFilename = "test_notices/test_upload_" . uniqid() . ".pdf";

$uploadResult = $db->uploadFile('documents', $testFilename, $testFileData, 'application/pdf');
if ($uploadResult['status'] === 200) {
    echo "   [PASS] Mock file upload completed successfully.\n";
    
    // Test URL generation
    $publicUrl = $db->getPublicUrl('documents', $testFilename);
    echo "          Generated public URL: " . $publicUrl . "\n";
    
    // Check if file is written to the physical static directory
    $physicalPath = UPLOAD_DIR . 'documents/' . $testFilename;
    if (file_exists($physicalPath)) {
        echo "   [PASS] Physical file exists on server disk: " . $physicalPath . "\n";
        
        // Clean up uploaded test file
        $deleteFileResult = $db->deleteFile('documents', $testFilename);
        if ($deleteFileResult['status'] === 200) {
            echo "   [PASS] Physical test file successfully deleted after test.\n";
        } else {
            echo "   [WARNING] Failed to delete test file from storage: " . json_encode($deleteFileResult) . "\n";
        }
    } else {
        echo "   [FAIL] Physical file does NOT exist on disk! Path: " . $physicalPath . "\n";
        exit(1);
    }
} else {
    echo "   [FAIL] File upload failed! Response: " . json_encode($uploadResult) . "\n";
    exit(1);
}

// Test 6: Delete notice
echo "\n6. Testing Delete notice...\n";
$deleteResult = $db->delete('notices', ['id' => ['eq.' => $noticeId]]);
if ($deleteResult['status'] === 200) {
    echo "   [PASS] Notice deleted successfully.\n";
    
    // Verify deleted
    $verifyResult = $db->select('notices', ['id' => 'eq.' . $noticeId]);
    if (empty($verifyResult['data'])) {
        echo "   [PASS] Notice verified as no longer existing.\n";
    } else {
        echo "   [FAIL] Notice still exists in table after delete!\n";
        exit(1);
    }
} else {
    echo "   [FAIL] Delete notice failed! Response: " . json_encode($deleteResult) . "\n";
    exit(1);
}

echo "\n=== All Tests Passed Successfully! ===\n";
