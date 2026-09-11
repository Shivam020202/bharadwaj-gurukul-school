<?php
/**
 * Bhardwaj Gurukul - Authentication Handler
 * Super Admin Login & Session Management
 */

require_once __DIR__ . '/config.php';
define('ADMIN_REGISTRATION_ENABLED', false);

/**
 * Get authenticated admin from session
 */
function getAuthenticatedAdmin($db) {
    if (empty($_SESSION['admin_id']) || empty($_SESSION['session_token'])) {
        if (!empty($_COOKIE['admin_id']) && !empty($_COOKIE['session_token'])) {
            $_SESSION['admin_id'] = $_COOKIE['admin_id'];
            $_SESSION['session_token'] = $_COOKIE['session_token'];
        } else {
            return null;
        }
    }

    // Clean expired sessions
    cleanExpiredSessions($db);

    // Check session in database
    $result = $db->select(TABLE_SESSIONS, [
        'admin_id' => 'eq.' . $_SESSION['admin_id'],
        'session_token' => 'eq.' . $_SESSION['session_token'],
        'expires_at' => ['gt' => date('c')]
    ]);

    if ($result['status'] !== 200 || empty($result['data'])) {
        // Session expired or invalid
        logoutAdmin();
        return null;
    }

    // Get admin details
    $adminResult = $db->select(TABLE_ADMINS, [
        'id' => 'eq.' . $_SESSION['admin_id']
    ]);

    if ($adminResult['status'] !== 200 || empty($adminResult['data'])) {
        logoutAdmin();
        return null;
    }

    $admin = $adminResult['data'][0];

    // Remove password hash from response
    unset($admin['password_hash']);

    return $admin;
}

/**
 * Admin login
 */
function loginAdmin($db, $username, $password) {
    // Check login rate limiting
    if (!isLoginAllowed($username)) {
        jsonResponse('error', [
            'message' => 'Too many failed attempts. Please try again after 15 minutes.',
            'code' => 'RATE_LIMITED'
        ], 429);
    }

    // Validate input
    $username = sanitize($username);
    $password = trim($password);

    if (empty($username) || empty($password)) {
        setLoginAttempt($username);
        jsonResponse('error', [
            'message' => 'Username and password are required.',
            'code' => 'MISSING_CREDENTIALS'
        ], 400);
    }

    // Find admin
    $result = $db->select(TABLE_ADMINS, [
        'username' => 'eq.' . $username
    ]);

    if ($result['status'] !== 200 || empty($result['data'])) {
        setLoginAttempt($username);
        jsonResponse('error', [
            'message' => 'Invalid username or password.',
            'code' => 'INVALID_CREDENTIALS'
        ], 401);
    }

    $admin = $result['data'][0];

    // Verify password
    if (!verifyPassword($password, $admin['password_hash'])) {
        setLoginAttempt($username);
        jsonResponse('error', [
            'message' => 'Invalid username or password.',
            'code' => 'INVALID_CREDENTIALS'
        ], 401);
    }

    // Check if account is active
    if (isset($admin['is_active']) && !$admin['is_active']) {
        jsonResponse('error', [
            'message' => 'Account is disabled.',
            'code' => 'ACCOUNT_DISABLED'
        ], 403);
    }

    // Generate session token
    $sessionToken = generateSessionToken();
    $expiresAt = date('c', time() + SESSION_LIFETIME);

    // Create session in database
    $sessionResult = $db->insert(TABLE_SESSIONS, [
        'admin_id' => $admin['id'],
        'session_token' => $sessionToken,
        'expires_at' => $expiresAt,
        'ip_address' => getClientIP(),
        'user_agent' => getUserAgent()
    ]);

    if ($sessionResult['status'] !== 201) {
        jsonResponse('error', [
            'message' => 'Failed to create session.',
            'code' => 'SESSION_ERROR'
        ], 500);
    }

    // Update last login
    $db->update(TABLE_ADMINS, [
        'last_login' => date('c')
    ], ['id' => ['eq.' => $admin['id']]]);

    // Set session variables
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['session_token'] = $sessionToken;
    $_SESSION['is_super_admin'] = $admin['is_super_admin'];
    $_SESSION['full_name'] = $admin['full_name'];
    $_SESSION['username'] = $admin['username'];

    // Set cookies for serverless session persistence
    $cookieLifetime = time() + SESSION_LIFETIME;
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie('session_token', $sessionToken, $cookieLifetime, '/', '', $isHttps, true);
    setcookie('admin_id', $admin['id'], $cookieLifetime, '/', '', $isHttps, true);

    // Clear login attempts
    unset($_SESSION['login_attempts'][getClientIP() . '_' . strtolower($username)]);

    // Return success
    unset($admin['password_hash']);

    jsonResponse('success', [
        'message' => 'Login successful.',
        'admin' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'email' => $admin['email'],
            'full_name' => $admin['full_name'],
            'is_super_admin' => $admin['is_super_admin']
        ],
        'session' => [
            'token' => $sessionToken,
            'expires_at' => $expiresAt
        ]
    ]);
}

/**
 * Admin logout
 */
function logoutAdmin() {
    if (!empty($_SESSION['admin_id']) && !empty($_SESSION['session_token'])) {
        $db = new SupabaseDB();
        $db->delete(TABLE_SESSIONS, [
            'admin_id' => 'eq.' . $_SESSION['admin_id'],
            'session_token' => 'eq.' . $_SESSION['session_token']
        ]);
    }

    // Clear all session variables
    $_SESSION = [];
    setcookie('session_token', '', time() - 3600, '/');
    setcookie('admin_id', '', time() - 3600, '/');

    // Delete session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Require authentication
 */
function requireAuth($db) {
    $admin = getAuthenticatedAdmin($db);

    if (!$admin) {
        jsonResponse('error', [
            'message' => 'Authentication required.',
            'code' => 'UNAUTHORIZED'
        ], 401);
    }

    return $admin;
}

/**
 * Require super admin
 */
function requireSuperAdmin($db) {
    $admin = requireAuth($db);

    if (!$admin['is_super_admin']) {
        jsonResponse('error', [
            'message' => 'Super administrator access required.',
            'code' => 'FORBIDDEN'
        ], 403);
    }

    return $admin;
}

/**
 * Validate session token from request
 */
function validateTokenAuth() {
    $headers = getallheaders();

    // Check Authorization header
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            $_SESSION['session_token'] = $token;
        }
    }

    // Check X-Session-Token header
    if (isset($headers['X-Session-Token'])) {
        $_SESSION['session_token'] = $headers['X-Session-Token'];
    }

    // Check session
    if (empty($_SESSION['admin_id']) || empty($_SESSION['session_token'])) {
        return false;
    }

    return true;
}

// Handle login request only if auth.php is the primary script entry point
$isDirectRequest = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/auth.php') !== false) ||
                   (strpos($_SERVER['SCRIPT_NAME'] ?? '', 'auth.php') !== false) ||
                   (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'auth.php');
if ($isDirectRequest) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_GET['action'] ?? '';

        if ($action === 'login') {
            $csrfCandidate = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            $hostOnly = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
            $isLocalHost = $hostOnly === 'localhost' || $hostOnly === '127.0.0.1';
            if (!$isLocalHost && !validateCSRF($csrfCandidate)) {
                jsonResponse('error', ['message' => 'Invalid CSRF token.'], 403);
            }

            if (empty($input['username']) || empty($input['password'])) {
                jsonResponse('error', [
                    'message' => 'Username and password are required.',
                    'code' => 'MISSING_CREDENTIALS'
                ], 400);
            }

            $db = new SupabaseDB();
            loginAdmin($db, $input['username'], $input['password']);
        }

        if ($action === 'logout') {
            logoutAdmin();
            jsonResponse('success', ['message' => 'Logged out successfully.']);
        }

        if ($action === 'check') {
            $db = new SupabaseDB();
            $admin = getAuthenticatedAdmin($db);

            if ($admin) {
                jsonResponse('success', [
                    'authenticated' => true,
                    'admin' => $admin
                ]);
            } else {
                jsonResponse('success', [
                    'authenticated' => false
                ]);
            }
        }

        if ($action === 'register' && ADMIN_REGISTRATION_ENABLED) {
            // Admin registration (only for initial setup)
            if (empty($input['username']) || empty($input['password']) ||
                empty($input['email']) || empty($input['full_name'])) {
                jsonResponse('error', [
                    'message' => 'All fields are required.',
                    'code' => 'MISSING_FIELDS'
                ], 400);
            }

            if (!isValidEmail($input['email'])) {
                jsonResponse('error', [
                    'message' => 'Invalid email address.',
                    'code' => 'INVALID_EMAIL'
                ], 400);
            }

            if (strlen($input['password']) < 8) {
                jsonResponse('error', [
                    'message' => 'Password must be at least 8 characters.',
                    'code' => 'WEAK_PASSWORD'
                ], 400);
            }

            $db = new SupabaseDB();

            // Check if username exists
            $existing = $db->select(TABLE_ADMINS, ['username' => 'eq.' . $input['username']]);
            if ($existing['status'] === 200 && !empty($existing['data'])) {
                jsonResponse('error', [
                    'message' => 'Username already exists.',
                    'code' => 'USERNAME_EXISTS'
                ], 409);
            }

            // Check if email exists
            $existing = $db->select(TABLE_ADMINS, ['email' => 'eq.' . $input['email']]);
            if ($existing['status'] === 200 && !empty($existing['data'])) {
                jsonResponse('error', [
                    'message' => 'Email already exists.',
                    'code' => 'EMAIL_EXISTS'
                ], 409);
            }

            // Create admin
            $result = $db->insert(TABLE_ADMINS, [
                'username' => sanitize($input['username']),
                'email' => sanitize($input['email']),
                'full_name' => sanitize($input['full_name']),
                'password_hash' => hashPassword($input['password']),
                'is_super_admin' => false
            ]);

            if ($result['status'] === 201) {
                jsonResponse('success', [
                    'message' => 'Admin registered successfully.'
                ]);
            } else {
                jsonResponse('error', [
                    'message' => 'Failed to register admin.',
                    'code' => 'REGISTRATION_FAILED'
                ], 500);
            }
        }

        if ($action === 'change_password') {
            $db = new SupabaseDB();
            $admin = requireAuth($db);

            if (empty($input['current_password']) || empty($input['new_password'])) {
                jsonResponse('error', [
                    'message' => 'Current and new password are required.',
                    'code' => 'MISSING_FIELDS'
                ], 400);
            }

            if (strlen($input['new_password']) < 8) {
                jsonResponse('error', [
                    'message' => 'New password must be at least 8 characters.',
                    'code' => 'WEAK_PASSWORD'
                ], 400);
            }

            // Verify current password
            $adminResult = $db->select(TABLE_ADMINS, ['id' => 'eq.' . $admin['id']]);
            $currentAdmin = $adminResult['data'][0] ?? null;

            if (!$currentAdmin || !verifyPassword($input['current_password'], $currentAdmin['password_hash'])) {
                jsonResponse('error', [
                    'message' => 'Current password is incorrect.',
                    'code' => 'INVALID_PASSWORD'
                ], 401);
            }

            // Update password
            $result = $db->update(TABLE_ADMINS, [
                'password_hash' => hashPassword($input['new_password'])
            ], ['id' => ['eq.' => $admin['id']]]);

            if ($result['status'] === 204 || $result['status'] === 200) {
                jsonResponse('success', [
                    'message' => 'Password changed successfully.'
                ]);
            } else {
                jsonResponse('error', [
                    'message' => 'Failed to change password.',
                    'code' => 'UPDATE_FAILED'
                ], 500);
            }
        }
    }

    // If no action matched
    jsonResponse('error', [
        'message' => 'Invalid action.',
        'code' => 'INVALID_ACTION'
    ], 400);
}
