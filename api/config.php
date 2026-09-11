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
define('UPLOAD_DIR', __DIR__ . '/../static/uploads/');
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
    if (session_save_path() === '' || !is_writable(session_save_path())) {
        @session_save_path('/tmp');
    }
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if ($isHttps) {
        ini_set('session.cookie_secure', 1);
    }
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
class SupabaseDB
{
    public function __construct()
    {
        // No-op for local DB
    }

    private function loadTable($table)
    {
        $filePath = __DIR__ . '/data/' . $table . '.php';
        if (!file_exists($filePath)) {
            if ($table === 'admins') {
                $defaultAdmins = [
                    [
                        'id' => '11111111-1111-1111-1111-111111111111',
                        'username' => 'admin2',
                        'email' => 'admin@bhardwajgurukul.com',
                        'full_name' => 'Super Administrator',
                        'password_hash' => '$2y$10$e0bFQprp.0PP7FC0V3iQk.FG9RPwBhJjgqtSWea9t.GH8sH3x.bCK',
                        'is_super_admin' => true,
                        'is_active' => true,
                        'created_at' => date('c'),
                        'updated_at' => date('c')
                    ]
                ];
                $this->saveTable('admins', $defaultAdmins);
                return $defaultAdmins;
            }
            return [];
        }
        if (!defined('SECURE_ACCESS')) {
            define('SECURE_ACCESS', true);
        }
        $data = include $filePath;
        return is_array($data) ? $data : [];
    }

    private function saveTable($table, $data)
    {
        $dir = __DIR__ . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filePath = $dir . '/' . $table . '.php';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $content = "<?php\nif(!defined('SECURE_ACCESS')) { header('HTTP/1.1 403 Forbidden'); exit; }\nreturn json_decode(" . var_export($json, true) . ", true);\n";
        file_put_contents($filePath, $content, LOCK_EX);
    }

    private function matchRow($row, $filters)
    {
        foreach ($filters as $field => $filterVal) {
            if ($field === 'select' || $field === 'order' || $field === 'limit') {
                continue;
            }

            if (is_array($filterVal)) {
                foreach ($filterVal as $op => $val) {
                    $op = rtrim($op, '.');
                    $rowVal = $row[$field] ?? null;

                    if ($op === 'eq') {
                        if (strval($rowVal) !== strval($val)) return false;
                    } elseif ($op === 'gt') {
                        if (strval($rowVal) <= strval($val)) return false;
                    } elseif ($op === 'lt') {
                        if (strval($rowVal) >= strval($val)) return false;
                    }
                }
            } else {
                $rowVal = $row[$field] ?? null;
                if (strpos($filterVal, 'eq.') === 0) {
                    $val = substr($filterVal, 3);
                    if ($val === 'true') $val = true;
                    elseif ($val === 'false') $val = false;

                    if (is_bool($rowVal)) {
                        if ($rowVal !== $val) return false;
                    } else {
                        if (strval($rowVal) !== strval($val)) return false;
                    }
                } elseif (strpos($filterVal, 'gt.') === 0) {
                    $val = substr($filterVal, 3);
                    if (strval($rowVal) <= strval($val)) return false;
                } elseif (strpos($filterVal, 'lt.') === 0) {
                    $val = substr($filterVal, 3);
                    if (strval($rowVal) >= strval($val)) return false;
                } else {
                    if (strval($rowVal) !== strval($filterVal)) return false;
                }
            }
        }
        return true;
    }

    public function select($table, $filters = [], $order = null, $limit = null)
    {
        $rows = $this->loadTable($table);
        $filtered = [];

        foreach ($rows as $row) {
            if ($this->matchRow($row, $filters)) {
                $filtered[] = $row;
            }
        }

        if ($order) {
            $parts = explode('.', $order);
            $field = $parts[0];
            $dir = $parts[1] ?? 'asc';

            usort($filtered, function($a, $b) use ($field, $dir) {
                $valA = $a[$field] ?? '';
                $valB = $b[$field] ?? '';
                if ($dir === 'desc') {
                    return strcmp(strval($valB), strval($valA));
                } else {
                    return strcmp(strval($valA), strval($valB));
                }
            });
        }

        if ($limit) {
            $filtered = array_slice($filtered, 0, (int)$limit);
        }

        return [
            'status' => 200,
            'data' => $filtered
        ];
    }

    public function insert($table, $data)
    {
        $rows = $this->loadTable($table);

        if (!isset($data['id'])) {
            $data['id'] = $this->generateUUID();
        }

        $now = date('c');
        if (!isset($data['created_at'])) {
            $data['created_at'] = $now;
        }
        $data['updated_at'] = $now;

        $rows[] = $data;
        $this->saveTable($table, $rows);

        return [
            'status' => 201,
            'data' => [$data]
        ];
    }

    public function update($table, $data, $filters)
    {
        $rows = $this->loadTable($table);
        $updated = [];
        $hasChanges = false;

        foreach ($rows as &$row) {
            if ($this->matchRow($row, $filters)) {
                foreach ($data as $key => $val) {
                    $row[$key] = $val;
                }
                $row['updated_at'] = date('c');
                $updated[] = $row;
                $hasChanges = true;
            }
        }
        unset($row);

        if ($hasChanges) {
            $this->saveTable($table, $rows);
        }

        return [
            'status' => 200,
            'data' => $updated
        ];
    }

    public function delete($table, $filters)
    {
        $rows = $this->loadTable($table);
        $kept = [];
        $hasChanges = false;

        foreach ($rows as $row) {
            if ($this->matchRow($row, $filters)) {
                $hasChanges = true;
            } else {
                $kept[] = $row;
            }
        }

        if ($hasChanges) {
            $this->saveTable($table, $kept);
        }

        return [
            'status' => 200,
            'data' => []
        ];
    }

    public function uploadFile($bucket, $path, $fileData, $contentType)
    {
        $fullPath = UPLOAD_DIR . $bucket . '/' . $path;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $bytes = file_put_contents($fullPath, $fileData);
        if ($bytes === false) {
            return [
                'status' => 500,
                'raw' => 'Failed to write file to local disk.'
            ];
        }

        return [
            'status' => 200,
            'raw' => 'File uploaded successfully'
        ];
    }

    public function getPublicUrl($bucket, $path)
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $projectPath = dirname($scriptName);
        $projectPath = str_replace('\\', '/', $projectPath);
        
        if (substr($projectPath, -4) === '/api') {
            $projectPath = substr($projectPath, 0, -4);
        } elseif (substr($projectPath, -10) === '/dashboard') {
            $projectPath = substr($projectPath, 0, -10);
        }
        
        if ($projectPath === '.' || $projectPath === '/' || $projectPath === '\\') {
            $projectPath = '';
        } else {
            $projectPath = '/' . ltrim($projectPath, '/');
        }

        return $projectPath . '/static/uploads/' . $bucket . '/' . $path;
    }

    public function deleteFile($bucket, $path)
    {
        $fullPath = UPLOAD_DIR . $bucket . '/' . $path;
        if (file_exists($fullPath)) {
            unlink($fullPath);
            return [
                'status' => 200,
                'raw' => 'File deleted successfully'
            ];
        }
        return [
            'status' => 404,
            'raw' => 'File not found'
        ];
    }

    private function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

/**
 * Utility Functions
 */

/**
 * Send JSON response
 */
function jsonResponse($status, $data, $code = 200)
{
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
function sanitize($input)
{
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate secure token
 */
function generateToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

/**
 * Generate session token
 */
function generateSessionToken()
{
    return hash('sha256', generateToken(32) . microtime(true) . uniqid());
}

/**
 * Hash password using bcrypt
 */
function hashPassword($password)
{
    return password_hash($password, PASSWORD_DEFAULT, [
        'cost' => 12
    ]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

/**
 * Clean expired sessions
 */
function cleanExpiredSessions($db)
{
    $result = $db->delete(TABLE_SESSIONS, ['expires_at' => ['lt' => date('c')]]);
    return $result;
}

/**
 * Get client IP address
 */
function getClientIP()
{
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
function getUserAgent()
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
}

/**
 * Set login attempt tracking
 */
function setLoginAttempt($email)
{
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
function isLoginAllowed($email)
{
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
function validateCSRF($token = null)
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    if (empty($token)) {
        return false;
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = $token;
        return true;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token
 */
function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(16);
    }
    return $_SESSION['csrf_token'];
}

// Initialize CSRF token
generateCSRFToken();
