<?php
/**
 * Bhardwaj Gurukul - Notices System Configuration
 * Supabase Database & File Storage Configuration
 */

// Supabase Configuration
// Load safe local or environment overrides before falling back to repository defaults.
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://znijavgkwipxtwqmmgxh.supabase.co');
}

if (!defined('SUPABASE_KEY')) {
    define('SUPABASE_KEY', getenv('SUPABASE_KEY') ?: 'sb_publishable_2N3zplrvZ0F6xiZZbysqVg_UwOTHtYQ');
}

if (!defined('SUPABASE_ANON_KEY')) {
    define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: 'sb_publishable_2N3zplrvZ0F6xiZZbysqVg_UwOTHtYQ');
}

if (!defined('SUPABASE_SERVICE_KEY')) {
    define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY') ?: '');
}

// Database Tables
define('TABLE_NOTICES', 'notices');
define('TABLE_ADMINS', 'admins');
define('TABLE_SESSIONS', 'admin_sessions');

// File Storage Configuration
define('UPLOAD_DIR', __DIR__ . '/../dashboard/uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'txt']);
define('ALLOWED_MIME_TYPES', [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain'
]);

// Security Configuration
define('SESSION_LIFETIME', 24 * 60 * 60); // 24 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 15 * 60); // 15 minutes

// CORS Headers — only when running under a web server
if (php_sapi_name() !== 'cli' && empty($_SERVER['REQUEST_METHOD'])) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Content-Type: application/json; charset=utf-8');
}

// Handle preflight requests
if (php_sapi_name() !== 'cli' && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session for authentication (only in web context)
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Error reporting (disable in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Fix SSL CA bundle on Windows (common issue)
if (!ini_get('curl.cainfo') && file_exists(__DIR__ . '/../cacert.pem')) {
    ini_set('curl.cainfo', __DIR__ . '/../cacert.pem');
}

/**
 * Supabase Database Connection
 */
class SupabaseDB {
    private $url;
    private $key;
    private $serviceKey;

    public function __construct() {
        $this->url = rtrim(SUPABASE_URL, '/');
        $this->key = SUPABASE_KEY;
        $this->serviceKey = SUPABASE_SERVICE_KEY;
    }

    /**
     * Make a request to Supabase REST API
     */
    private function request($method, $table, $data = [], $options = []) {
        $url = $this->url . '/rest/v1/' . $table;

        $headers = [
            'apikey: ' . $this->serviceKey,
            'Authorization: Bearer ' . $this->serviceKey,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        // Configure CA bundle path if available
        $caBundle = __DIR__ . '/../cacert.pem';
        if (file_exists($caBundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        if (!empty($options['query'])) {
            $url .= '?' . http_build_query($options['query']);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true),
            'raw' => $response
        ];
    }

    /**
     * SELECT query
     */
    public function select($table, $filters = [], $order = null, $limit = null) {
        $options = ['query' => $filters];

        if ($order) {
            $options['query']['order'] = $order;
        }

        if ($limit) {
            $options['query']['limit'] = $limit;
        }

        return $this->request('GET', $table, [], $options);
    }

    /**
     * INSERT query
     */
    public function insert($table, $data) {
        return $this->request('POST', $table, $data);
    }

    /**
     * UPDATE query
     */
    public function update($table, $data, $filters) {
        $options = ['query' => array_merge($filters, ['method' => 'PATCH'])];
        return $this->request('PATCH', $table, $data, $options);
    }

    /**
     * DELETE query
     */
    public function delete($table, $filters) {
        $options = ['query' => array_merge($filters, ['method' => 'DELETE'])];
        return $this->request('DELETE', $table, [], $options);
    }

    /**
     * Upload file to Supabase Storage
     */
    public function uploadFile($bucket, $path, $fileData, $contentType) {
        $url = $this->url . '/storage/v1/object/' . $bucket . '/' . $path;

        $headers = [
            'apikey: ' . $this->serviceKey,
            'Authorization: Bearer ' . $this->serviceKey,
            'Content-Type: ' . $contentType,
            'x-upsert: true'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $fileData,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true),
            'raw' => $response
        ];
    }

    /**
     * Get public URL for uploaded file
     */
    public function getPublicUrl($bucket, $path) {
        return $this->url . '/storage/v1/object/public/' . $bucket . '/' . $path;
    }

    /**
     * Delete file from Supabase Storage
     */
    public function deleteFile($bucket, $path) {
        $url = $this->url . '/storage/v1/object/' . $bucket . '/' . $path;

        $headers = [
            'apikey: ' . $this->serviceKey,
            'Authorization: Bearer ' . $this->serviceKey,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true),
            'raw' => $response
        ];
    }
}

/**
 * Utility Functions
 */

/**
 * Send JSON response
 */
function jsonResponse($status, $data, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $status === 'success',
        'status' => $status,
        'timestamp' => date('c'),
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate secure token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Generate session token
 */
function generateSessionToken() {
    return hash('sha256', generateToken(32) . microtime(true) . uniqid());
}

/**
 * Hash password using bcrypt
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT, [
        'cost' => 12
    ]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Clean expired sessions
 */
function cleanExpiredSessions($db) {
    $result = $db->delete(TABLE_SESSIONS, ['expires_at' => ['lt' => date('c')]]);
    return $result;
}

/**
 * Get client IP address
 */
function getClientIP() {
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Get user agent
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
}

/**
 * Set login attempt tracking
 */
function setLoginAttempt($email) {
    $ip = getClientIP();
    $attempts = $_SESSION['login_attempts'][$ip . '_' . strtolower($email)] ?? ['count' => 0, 'time' => time()];

    if (time() - $attempts['time'] > LOGIN_LOCKOUT_TIME) {
        $attempts = ['count' => 0, 'time' => time()];
    }

    $attempts['count']++;
    $attempts['time'] = time();
    $_SESSION['login_attempts'][$ip . '_' . strtolower($email)] = $attempts;
}

/**
 * Check if login is allowed
 */
function isLoginAllowed($email) {
    $ip = getClientIP();
    $attempts = $_SESSION['login_attempts'][$ip . '_' . strtolower($email)] ?? ['count' => 0, 'time' => time()];

    if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS) {
        if (time() - $attempts['time'] < LOGIN_LOCKOUT_TIME) {
            return false;
        }
    }

    return true;
}

/**
 * Validate CSRF token
 */
function validateCSRF() {
    if (!isset($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(16);
    }
    return $_SESSION['csrf_token'];
}

// Initialize CSRF token
generateCSRFToken();
