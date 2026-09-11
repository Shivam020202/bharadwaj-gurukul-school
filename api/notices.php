<?php
/**
 * Bhardwaj Gurukul - Notices CRUD API
 * Manages PDF and text notices with Supabase
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

/**
 * Get all notices
 */
function getNotices($db) {
    // Check authentication to determine if requester is admin
    $headers = getallheaders();
    $sessionToken = $headers['X-Session-Token'] ?? '';
    if (empty($sessionToken) && isset($headers['Authorization']) && strpos($headers['Authorization'], 'Bearer ') === 0) {
        $sessionToken = substr($headers['Authorization'], 7);
    }
    if (empty($sessionToken) && isset($_SESSION['session_token'])) {
        $sessionToken = $_SESSION['session_token'];
    }
    if (empty($sessionToken) && !empty($_COOKIE['session_token'])) {
        $sessionToken = $_COOKIE['session_token'];
    }

    $isAdmin = false;
    if (!empty($sessionToken)) {
        $admin_id = validateSessionToken($sessionToken);
        if ($admin_id) {
            $isAdmin = true;
        }
    }

    $filters = [];
    if (!$isAdmin) {
        $filters['is_active'] = 'eq.true';
    }

    $result = $db->select(TABLE_NOTICES, $filters, 'created_at.desc');

    if ($result['status'] !== 200) {
        if ($isAdmin) {
            jsonResponse('error', [
                'message' => 'Failed to fetch notices.',
                'code' => 'FETCH_ERROR'
            ], 500);
        } else {
            http_response_code(500);
            echo json_encode([]);
            exit;
        }
    }

    $notices = $result['data'] ?? [];

    // Format notices
    foreach ($notices as &$notice) {
        if (!empty($notice['file_size'])) {
            $notice['file_size_formatted'] = formatBytes($notice['file_size']);
        }

        if ($isAdmin) {
            $notice['created_at'] = date('M d, Y h:i A', strtotime($notice['created_at']));
            $notice['updated_at'] = date('M d, Y h:i A', strtotime($notice['updated_at']));
        } else {
            $notice['created_at'] = date('c', strtotime($notice['created_at']));
            $notice['updated_at'] = date('c', strtotime($notice['updated_at']));
        }
    }

    if ($isAdmin) {
        jsonResponse('success', [
            'notices' => $notices,
            'count' => count($notices)
        ]);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($notices, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * Get single notice
 */
function getNotice($db, $id) {
    $result = $db->select(TABLE_NOTICES, ['id' => ['eq.' => $id]]);

    if ($result['status'] !== 200 || empty($result['data'])) {
        jsonResponse('error', [
            'message' => 'Notice not found.',
            'code' => 'NOT_FOUND'
        ], 404);
    }

    $notice = $result['data'][0];

    // Format file size
    if (!empty($notice['file_size'])) {
        $notice['file_size_formatted'] = formatBytes($notice['file_size']);
    }

    // Format dates
    $notice['created_at'] = date('c', strtotime($notice['created_at']));
    $notice['updated_at'] = date('c', strtotime($notice['updated_at']));

    jsonResponse('success', ['notice' => $notice]);
}

/**
 * Create new notice
 */
function createNotice($db, $data, $files = []) {
    // Validate required fields
    if (empty($data['title'])) {
        jsonResponse('error', [
            'message' => 'Title is required.',
            'code' => 'MISSING_TITLE'
        ], 400);
    }

    $title = sanitize($data['title']);
    $content = !empty($data['content']) ? sanitize($data['content']) : '';
    $priority = !empty($data['priority']) ? sanitize($data['priority']) : 'normal';
    $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;

    // Validate priority
    $validPriorities = ['low', 'normal', 'high', 'urgent'];
    if (!in_array($priority, $validPriorities)) {
        $priority = 'normal';
    }

    $noticeData = [
        'title' => $title,
        'content' => $content,
        'priority' => $priority,
        'is_active' => $isActive,
        'created_by' => $_SESSION['username'] ?? 'admin'
    ];

    // Handle file upload
    if (!empty($files['notice_file']['tmp_name']) && is_uploaded_file($files['notice_file']['tmp_name'])) {
        $file = $files['notice_file'];

        // Validate file
        $validation = validateFile($file);
        if (!$validation['valid']) {
            jsonResponse('error', [
                'message' => $validation['message'],
                'code' => 'INVALID_FILE'
            ], 400);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'notices/' . date('Y/m/') . uniqid() . '.' . $extension;

        // Upload to Supabase Storage
        $fileData = file_get_contents($file['tmp_name']);

        $uploadResult = $db->uploadFile(
            'documents',
            $filename,
            $fileData,
            $file['type']
        );

        if ($uploadResult['status'] !== 200 && $uploadResult['status'] !== 201) {
            jsonResponse('error', [
                'message' => 'Failed to upload file to storage.',
                'code' => 'UPLOAD_FAILED',
                'details' => $uploadResult['raw']
            ], 500);
        }

        // Get public URL
        $fileUrl = $db->getPublicUrl('documents', $filename);

        $noticeData['file_url'] = $fileUrl;
        $noticeData['file_type'] = $extension === 'pdf' ? 'pdf' : 'image';
        $noticeData['file_size'] = $file['size'];
    }

    $result = $db->insert(TABLE_NOTICES, $noticeData);

    if ($result['status'] === 201 || $result['status'] === 200) {
        $noticeId = $result['data'][0]['id'] ?? null;

        jsonResponse('success', [
            'message' => 'Notice created successfully.',
            'notice_id' => $noticeId,
            'data' => $result['data'][0] ?? []
        ], 201);
    } else {
        jsonResponse('error', [
            'message' => 'Failed to create notice.',
            'code' => 'CREATE_FAILED',
            'details' => $result['raw']
        ], 500);
    }
}

/**
 * Update notice
 */
function updateNotice($db, $id, $data, $files = []) {
    $updateData = [];

    // Validate and prepare update data
    if (isset($data['title'])) {
        $updateData['title'] = sanitize($data['title']);
    }

    if (isset($data['content'])) {
        $updateData['content'] = sanitize($data['content']);
    }

    if (isset($data['priority'])) {
        $validPriorities = ['low', 'normal', 'high', 'urgent'];
        $priority = sanitize($data['priority']);
        if (in_array($priority, $validPriorities)) {
            $updateData['priority'] = $priority;
        }
    }

    if (isset($data['is_active'])) {
        $updateData['is_active'] = (bool)$data['is_active'];
    }

    // Handle file upload
    if (!empty($files['notice_file']['tmp_name']) && is_uploaded_file($files['notice_file']['tmp_name'])) {
        $file = $files['notice_file'];

        // Validate file
        $validation = validateFile($file);
        if (!$validation['valid']) {
            jsonResponse('error', [
                'message' => $validation['message'],
                'code' => 'INVALID_FILE'
            ], 400);
        }

        // Get existing notice to delete old file
        $existingResult = $db->select(TABLE_NOTICES, ['id' => ['eq.' => $id]]);
        if ($existingResult['status'] === 200 && !empty($existingResult['data'])) {
            $existing = $existingResult['data'][0];
            if (!empty($existing['file_url'])) {
                // Extract path from URL
                $path = str_replace($db->getPublicUrl('documents', ''), '', $existing['file_url']);
                if ($path) {
                    $db->deleteFile('documents', ltrim($path, '/'));
                }
            }
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'notices/' . date('Y/m/') . uniqid() . '.' . $extension;

        // Upload to Supabase Storage
        $fileData = file_get_contents($file['tmp_name']);

        $uploadResult = $db->uploadFile(
            'documents',
            $filename,
            $fileData,
            $file['type']
        );

        if ($uploadResult['status'] !== 200 && $uploadResult['status'] !== 201) {
            jsonResponse('error', [
                'message' => 'Failed to upload file to storage.',
                'code' => 'UPLOAD_FAILED'
            ], 500);
        }

        // Get public URL
        $fileUrl = $db->getPublicUrl('documents', $filename);

        $updateData['file_url'] = $fileUrl;
        $updateData['file_type'] = $extension === 'pdf' ? 'pdf' : 'image';
        $updateData['file_size'] = $file['size'];
    }

    if (empty($updateData)) {
        jsonResponse('error', [
            'message' => 'No data to update.',
            'code' => 'NO_DATA'
        ], 400);
    }

    $result = $db->update(TABLE_NOTICES, $updateData, ['id' => ['eq.' => $id]]);

    if ($result['status'] === 204 || $result['status'] === 200) {
        jsonResponse('success', [
            'message' => 'Notice updated successfully.'
        ]);
    } else {
        jsonResponse('error', [
            'message' => 'Failed to update notice.',
            'code' => 'UPDATE_FAILED'
        ], 500);
    }
}

/**
 * Delete notice
 */
function deleteNotice($db, $id) {
    // Get notice to delete associated file
    $existingResult = $db->select(TABLE_NOTICES, ['id' => ['eq.' => $id]]);
    if ($existingResult['status'] === 200 && !empty($existingResult['data'])) {
        $existing = $existingResult['data'][0];
        if (!empty($existing['file_url'])) {
            $path = str_replace($db->getPublicUrl('documents', ''), '', $existing['file_url']);
            if ($path) {
                $db->deleteFile('documents', ltrim($path, '/'));
            }
        }
    }

    $result = $db->delete(TABLE_NOTICES, ['id' => ['eq.' => $id]]);

    if ($result['status'] === 204 || $result['status'] === 200) {
        jsonResponse('success', [
            'message' => 'Notice deleted successfully.'
        ]);
    } else {
        jsonResponse('error', [
            'message' => 'Failed to delete notice.',
            'code' => 'DELETE_FAILED'
        ], 500);
    }
}

/**
 * Toggle notice active status
 */
function toggleNoticeStatus($db, $id) {
    // Get current status
    $existingResult = $db->select(TABLE_NOTICES, ['id' => ['eq.' => $id]]);
    if ($existingResult['status'] !== 200 || empty($existingResult['data'])) {
        jsonResponse('error', [
            'message' => 'Notice not found.',
            'code' => 'NOT_FOUND'
        ], 404);
    }

    $current = $existingResult['data'][0];
    $newStatus = !$current['is_active'];

    $result = $db->update(TABLE_NOTICES, [
        'is_active' => $newStatus
    ], ['id' => ['eq.' => $id]]);

    if ($result['status'] === 204 || $result['status'] === 200) {
        jsonResponse('success', [
            'message' => 'Notice status updated.',
            'is_active' => $newStatus
        ]);
    } else {
        jsonResponse('error', [
            'message' => 'Failed to update status.',
            'code' => 'UPDATE_FAILED'
        ], 500);
    }
}

/**
 * Validate uploaded file
 */
function validateFile($file) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'valid' => false,
            'message' => 'File upload error: ' . $file['error']
        ];
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return [
            'valid' => false,
            'message' => 'File size exceeds maximum limit of ' . formatBytes(MAX_FILE_SIZE)
        ];
    }

    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return [
            'valid' => false,
            'message' => 'File type not allowed. Allowed types: ' . implode(', ', ALLOWED_EXTENSIONS)
        ];
    }

    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
        return [
            'valid' => false,
            'message' => 'Invalid file MIME type.'
        ];
    }

    return ['valid' => true];
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = (int)$bytes;
    $exp = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    return round($bytes / pow(1024, $exp), $precision) . ' ' . $units[$exp];
}

// ============================================
// Route Handling
// ============================================

$method = $_SERVER['REQUEST_METHOD'];
$db = new SupabaseDB();

// API Routes
switch ($_GET['action'] ?? '') {

    case 'list':
        if ($method !== 'GET') {
            jsonResponse('error', ['message' => 'Method not allowed.'], 405);
        }
        getNotices($db);
        break;

    case 'get':
        if ($method !== 'GET') {
            jsonResponse('error', ['message' => 'Method not allowed.'], 405);
        }
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            jsonResponse('error', ['message' => 'Notice ID is required.'], 400);
        }
        getNotice($db, $id);
        break;

    case 'create':
        if ($method !== 'POST') {
            jsonResponse('error', ['message' => 'Method not allowed.'], 405);
        }
        // Require authentication for create
        $admin = requireAuth($db);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data) && !empty($_POST)) {
            $data = $_POST;
        }
        createNotice($db, $data, $_FILES);
        break;

    case 'update':
        if ($method !== 'POST' && $method !== 'PUT') {
            jsonResponse('error', ['message' => 'Method not allowed.'], 405);
        }
        $admin = requireAuth($db);
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            jsonResponse('error', ['message' => 'Notice ID is required.'], 400);
        }
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data) && !empty($_POST)) {
            $data = $_POST;
        }
        updateNotice($db, $id, $data, $_FILES);
        break;

    case 'delete':
        if ($method !== 'POST' && $method !== 'DELETE') {
            jsonResponse('error', ['message' => 'Method not allowed.'], 405);
        }
        $admin = requireAuth($db);
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            jsonResponse('error', ['message' => 'Notice ID is required.'], 400);
        }
        deleteNotice($db, $id);
        break;

    case 'toggle':
        if ($method !== 'POST') {
            jsonResponse('error', ['message' => 'Method not allowed.'], 405);
        }
        $admin = requireAuth($db);
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            jsonResponse('error', ['message' => 'Notice ID is required.'], 400);
        }
        toggleNoticeStatus($db, $id);
        break;

    case 'stats':
        if ($method !== 'GET') {
            jsonResponse('error', ['message' => 'Method not allowed.'], 405);
        }
        $admin = requireAuth($db);

        // Get notice counts
        $totalResult = $db->select(TABLE_NOTICES, [], null, null);
        $total = $totalResult['data'] ?? [];

        $activeResult = $db->select(TABLE_NOTICES, ['is_active' => 'eq.true']);
        $active = $activeResult['data'] ?? [];

        $pdfResult = $db->select(TABLE_NOTICES, ['file_type' => 'eq.pdf']);
        $pdfs = $pdfResult['data'] ?? [];

        jsonResponse('success', [
            'total_notices' => count($total),
            'active_notices' => count($active),
            'inactive_notices' => count($total) - count($active),
            'pdf_notices' => count($pdfs)
        ]);
        break;

    default:
        jsonResponse('error', [
            'message' => 'Invalid action.',
            'code' => 'INVALID_ACTION',
            'available_actions' => ['list', 'get', 'create', 'update', 'delete', 'toggle', 'stats']
        ], 400);
        break;
}
