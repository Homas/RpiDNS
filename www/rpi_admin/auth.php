<?php
/**
 * Authentication Service for RpiDNS
 * Handles user authentication, session management, and user administration
 * 
 * (c) Vadim Pavlov 2020-2024
 */

require_once "/opt/rpidns/www/rpidns_vars.php";

class AuthService {
    private $db;
    private $dbFile;
    
    // Session configuration
    const SESSION_DURATION = 86400; // 24 hours in seconds
    const TOKEN_LENGTH = 32; // 32 bytes = 64 hex characters
    const BCRYPT_COST = 12;
    const MIN_PASSWORD_LENGTH = 8;
    
    // Rate limiting configuration
    const MAX_LOGIN_ATTEMPTS = 5;
    const RATE_LIMIT_WINDOW = 900; // 15 minutes in seconds
    
    /**
     * Constructor
     * @param string $dbFile Path to SQLite database file (optional)
     */
    public function __construct($dbFile = null) {
        $this->dbFile = $dbFile ?? "/opt/rpidns/www/db/" . DBFile;
    }
    
    /**
     * Open database connection
     * @return bool Success status
     */
    private function openDb() {
        if ($this->db !== null) {
            return true;
        }
        
        try {
            $this->db = new SQLite3($this->dbFile);
            $this->db->busyTimeout(15000);
            $this->db->exec('PRAGMA journal_mode = WAL;');
            $this->db->exec('PRAGMA foreign_keys = ON;');
            return true;
        } catch (Exception $e) {
            error_log("[AuthService] Failed to open database: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Close database connection
     */
    private function closeDb() {
        if ($this->db !== null) {
            $this->db->close();
            $this->db = null;
        }
    }
    
    /**
     * Generate a cryptographically secure session token
     * @return string 64-character hex string
     */
    public function generateToken() {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }
    
    /**
     * Hash a password using bcrypt with cost factor 12
     * @param string $password Plain text password
     * @return string Bcrypt hash
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }
    
    /**
     * Verify a password against a hash
     * @param string $password Plain text password
     * @param string $hash Password hash
     * @return bool True if password matches
     */
    public function verifyPassword($password, $hash) {
        // Handle bcrypt hashes
        if (preg_match('/^\$2[ayb]\$/', $hash)) {
            return password_verify($password, $hash);
        }
        
        // Handle Apache MD5 hashes ($apr1$)
        if (strpos($hash, '$apr1$') === 0) {
            return $this->verifyApr1Hash($password, $hash);
        }
        
        // Handle SHA1 hashes ({SHA})
        if (strpos($hash, '{SHA}') === 0) {
            $expectedHash = substr($hash, 5);
            return base64_encode(sha1($password, true)) === $expectedHash;
        }
        
        // Handle plain crypt (13 characters)
        if (strlen($hash) === 13) {
            return crypt($password, $hash) === $hash;
        }
        
        return false;
    }
    
    /**
     * Verify Apache MD5 hash ($apr1$)
     * @param string $password Plain text password
     * @param string $hash Apache MD5 hash
     * @return bool True if password matches
     */
    private function verifyApr1Hash($password, $hash) {
        $parts = explode('$', $hash);
        if (count($parts) < 4) {
            return false;
        }
        
        $salt = $parts[2];
        $computed = $this->computeApr1Hash($password, $salt);
        
        return hash_equals($hash, $computed);
    }
    
    /**
     * Compute Apache MD5 hash
     * @param string $password Plain text password
     * @param string $salt Salt value
     * @return string Apache MD5 hash
     */
    private function computeApr1Hash($password, $salt) {
        $salt = substr($salt, 0, 8);
        $text = $password . '$apr1$' . $salt;
        $bin = pack("H32", md5($password . $salt . $password));
        
        for ($i = strlen($password); $i > 0; $i -= 16) {
            $text .= substr($bin, 0, min(16, $i));
        }
        
        for ($i = strlen($password); $i > 0; $i >>= 1) {
            $text .= ($i & 1) ? chr(0) : $password[0];
        }
        
        $bin = pack("H32", md5($text));
        
        for ($i = 0; $i < 1000; $i++) {
            $new = ($i & 1) ? $password : $bin;
            if ($i % 3) $new .= $salt;
            if ($i % 7) $new .= $password;
            $new .= ($i & 1) ? $bin : $password;
            $bin = pack("H32", md5($new));
        }
        
        $tmp = '';
        for ($i = 0; $i < 5; $i++) {
            $k = $i + 6;
            $j = $i + 12;
            if ($j == 16) $j = 5;
            $tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
        }
        $tmp = chr(0) . chr(0) . $bin[11] . $tmp;
        
        $tmp = strtr(
            strrev(substr(base64_encode($tmp), 2)),
            "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
            "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz"
        );
        
        return '$apr1$' . $salt . '$' . $tmp;
    }

    
    /**
     * Authenticate user and create session
     * @param string $username Username
     * @param string $password Plain text password
     * @param string $ipAddress Client IP address
     * @param string $userAgent Client user agent
     * @return array Result with status, message, and session data
     */
    public function login($username, $password, $ipAddress = null, $userAgent = null) {
        if (!$this->openDb()) {
            return ['status' => 'error', 'message' => 'Database error occurred'];
        }
        
        $ipAddress = $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Check rate limiting
        if (!$this->checkRateLimit($ipAddress)) {
            return ['status' => 'error', 'message' => 'Too many attempts. Try again later.', 'code' => 429];
        }
        
        // Find user by username
        $stmt = $this->db->prepare("SELECT id, username, password_hash, is_admin FROM users WHERE username = :username");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        // Record login attempt (before checking password to prevent timing attacks)
        $success = false;
        
        if ($user && $this->verifyPassword($password, $user['password_hash'])) {
            $success = true;
        }
        
        // Record the attempt
        $this->recordLoginAttempt($ipAddress, $success);
        
        if (!$success) {
            // Return generic error message (don't reveal if username exists)
            return ['status' => 'error', 'message' => 'Invalid username or password', 'code' => 401];
        }
        
        // Check if password needs rehashing (e.g., migrated from htpasswd)
        if (!preg_match('/^\$2[ayb]\$/', $user['password_hash'])) {
            // Rehash with bcrypt
            $newHash = $this->hashPassword($password);
            $updateStmt = $this->db->prepare("UPDATE users SET password_hash = :hash, updated_at = :updated_at WHERE id = :id");
            $updateStmt->bindValue(':hash', $newHash, SQLITE3_TEXT);
            $updateStmt->bindValue(':updated_at', time(), SQLITE3_INTEGER);
            $updateStmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
            $updateStmt->execute();
        }
        
        // Create session
        $token = $this->generateToken();
        $now = time();
        $expiresAt = $now + self::SESSION_DURATION;
        
        $stmt = $this->db->prepare("
            INSERT INTO sessions (user_id, token, created_at, expires_at, ip_address, user_agent)
            VALUES (:user_id, :token, :created_at, :expires_at, :ip_address, :user_agent)
        ");
        $stmt->bindValue(':user_id', $user['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $stmt->bindValue(':created_at', $now, SQLITE3_INTEGER);
        $stmt->bindValue(':expires_at', $expiresAt, SQLITE3_INTEGER);
        $stmt->bindValue(':ip_address', $ipAddress, SQLITE3_TEXT);
        $stmt->bindValue(':user_agent', $userAgent, SQLITE3_TEXT);
        
        if (!$stmt->execute()) {
            return ['status' => 'error', 'message' => 'Failed to create session', 'code' => 500];
        }
        
        // Set HTTP-only session cookie
        $this->setSessionCookie($token, $expiresAt);
        
        return [
            'status' => 'success',
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'is_admin' => (bool)$user['is_admin']
            ],
            'expires_at' => $expiresAt
        ];
    }
    
    /**
     * Set HTTP-only session cookie
     * @param string $token Session token
     * @param int $expiresAt Expiration timestamp
     */
    private function setSessionCookie($token, $expiresAt) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        
        setcookie(
            'rpidns_session',
            $token,
            [
                'expires' => $expiresAt,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }
    
    /**
     * Clear session cookie
     */
    private function clearSessionCookie() {
        setcookie(
            'rpidns_session',
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }

    
    /**
     * Verify session token and return user info
     * @param string $token Session token (optional, will use cookie if not provided)
     * @return array|null User info if valid, null if invalid
     */
    public function verifySession($token = null) {
        if (!$this->openDb()) {
            return null;
        }
        
        // Get token from cookie if not provided
        if ($token === null) {
            $token = $_COOKIE['rpidns_session'] ?? null;
        }
        
        if (empty($token)) {
            return null;
        }
        
        $now = time();
        
        // Find session and join with user
        $stmt = $this->db->prepare("
            SELECT s.id as session_id, s.user_id, s.expires_at, u.username, u.is_admin
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.token = :token
        ");
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $result = $stmt->execute();
        $session = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$session) {
            return null;
        }
        
        // Check if session has expired
        if ($session['expires_at'] < $now) {
            // Clean up expired session
            $this->deleteSession($token);
            return null;
        }
        
        return [
            'id' => $session['user_id'],
            'username' => $session['username'],
            'is_admin' => (bool)$session['is_admin'],
            'session_id' => $session['session_id'],
            'expires_at' => $session['expires_at']
        ];
    }
    
    /**
     * Delete a session by token
     * @param string $token Session token
     * @return bool Success status
     */
    private function deleteSession($token) {
        if (!$this->openDb()) {
            return false;
        }
        
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE token = :token");
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        return $stmt->execute() !== false;
    }

    
    /**
     * Logout user and invalidate session
     * @param string $token Session token (optional, will use cookie if not provided)
     * @return array Result with status and message
     */
    public function logout($token = null) {
        if (!$this->openDb()) {
            return ['status' => 'error', 'message' => 'Database error occurred'];
        }
        
        // Get token from cookie if not provided
        if ($token === null) {
            $token = $_COOKIE['rpidns_session'] ?? null;
        }
        
        if (empty($token)) {
            // Clear cookie anyway
            $this->clearSessionCookie();
            return ['status' => 'success', 'message' => 'Logged out'];
        }
        
        // Delete session from database
        $this->deleteSession($token);
        
        // Clear session cookie
        $this->clearSessionCookie();
        
        return ['status' => 'success', 'message' => 'Logged out successfully'];
    }

    
    /**
     * Check if IP address is rate limited
     * @param string $ipAddress Client IP address
     * @return bool True if allowed, false if rate limited
     */
    private function checkRateLimit($ipAddress) {
        if (!$this->openDb()) {
            return true; // Allow on DB error to prevent lockout
        }
        
        $windowStart = time() - self::RATE_LIMIT_WINDOW;
        
        // Count failed attempts in the window
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM login_attempts 
            WHERE ip_address = :ip_address 
            AND attempted_at >= :window_start 
            AND success = 0
        ");
        $stmt->bindValue(':ip_address', $ipAddress, SQLITE3_TEXT);
        $stmt->bindValue(':window_start', $windowStart, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        return ($row['count'] ?? 0) < self::MAX_LOGIN_ATTEMPTS;
    }
    
    /**
     * Record a login attempt
     * @param string $ipAddress Client IP address
     * @param bool $success Whether the attempt was successful
     */
    private function recordLoginAttempt($ipAddress, $success) {
        if (!$this->openDb()) {
            return;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (ip_address, attempted_at, success)
            VALUES (:ip_address, :attempted_at, :success)
        ");
        $stmt->bindValue(':ip_address', $ipAddress, SQLITE3_TEXT);
        $stmt->bindValue(':attempted_at', time(), SQLITE3_INTEGER);
        $stmt->bindValue(':success', $success ? 1 : 0, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Clean up old attempts periodically (1% chance per request)
        if (mt_rand(1, 100) === 1) {
            $this->cleanupOldAttempts();
        }
    }
    
    /**
     * Clean up old login attempts
     */
    public function cleanupOldAttempts() {
        if (!$this->openDb()) {
            return;
        }
        
        $cutoff = time() - self::RATE_LIMIT_WINDOW;
        $this->db->exec("DELETE FROM login_attempts WHERE attempted_at < $cutoff");
    }
    
    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions() {
        if (!$this->openDb()) {
            return;
        }
        
        $now = time();
        $this->db->exec("DELETE FROM sessions WHERE expires_at < $now");
    }
    
    /**
     * Get the number of failed login attempts for an IP
     * @param string $ipAddress Client IP address
     * @return int Number of failed attempts
     */
    public function getFailedAttempts($ipAddress) {
        if (!$this->openDb()) {
            return 0;
        }
        
        $windowStart = time() - self::RATE_LIMIT_WINDOW;
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM login_attempts 
            WHERE ip_address = :ip_address 
            AND attempted_at >= :window_start 
            AND success = 0
        ");
        $stmt->bindValue(':ip_address', $ipAddress, SQLITE3_TEXT);
        $stmt->bindValue(':window_start', $windowStart, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        return (int)($row['count'] ?? 0);
    }
    
    /**
     * Change password for the current user
     * Requires current password verification, validates minimum length,
     * updates password hash, and invalidates all other sessions
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password for verification
     * @param string $newPassword New password to set
     * @param string|null $currentSessionToken Current session token to preserve (optional)
     * @return array Result with status and message
     */
    public function changePassword($userId, $currentPassword, $newPassword, $currentSessionToken = null) {
        if (!$this->openDb()) {
            return ['status' => 'error', 'message' => 'Database error occurred', 'code' => 500];
        }
        
        // Validate new password minimum length
        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            return [
                'status' => 'error', 
                'message' => 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters',
                'code' => 400
            ];
        }
        
        // Get user's current password hash
        $stmt = $this->db->prepare("SELECT id, password_hash FROM users WHERE id = :id");
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found', 'code' => 404];
        }
        
        // Verify current password
        if (!$this->verifyPassword($currentPassword, $user['password_hash'])) {
            return ['status' => 'error', 'message' => 'Current password is incorrect', 'code' => 400];
        }
        
        // Hash new password with bcrypt
        $newHash = $this->hashPassword($newPassword);
        $now = time();
        
        // Update password in database
        $updateStmt = $this->db->prepare("
            UPDATE users 
            SET password_hash = :hash, updated_at = :updated_at 
            WHERE id = :id
        ");
        $updateStmt->bindValue(':hash', $newHash, SQLITE3_TEXT);
        $updateStmt->bindValue(':updated_at', $now, SQLITE3_INTEGER);
        $updateStmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        
        if (!$updateStmt->execute()) {
            return ['status' => 'error', 'message' => 'Failed to update password', 'code' => 500];
        }
        
        // Invalidate all other sessions for this user (keep current session if provided)
        if ($currentSessionToken !== null) {
            $deleteStmt = $this->db->prepare("
                DELETE FROM sessions 
                WHERE user_id = :user_id AND token != :current_token
            ");
            $deleteStmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $deleteStmt->bindValue(':current_token', $currentSessionToken, SQLITE3_TEXT);
        } else {
            $deleteStmt = $this->db->prepare("
                DELETE FROM sessions 
                WHERE user_id = :user_id
            ");
            $deleteStmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        }
        $deleteStmt->execute();
        
        return ['status' => 'success', 'message' => 'Password changed successfully'];
    }
    
    /**
     * Invalidate all sessions for a user except the current one
     * @param int $userId User ID
     * @param string|null $exceptToken Session token to preserve
     * @return bool Success status
     */
    public function invalidateUserSessions($userId, $exceptToken = null) {
        if (!$this->openDb()) {
            return false;
        }
        
        if ($exceptToken !== null) {
            $stmt = $this->db->prepare("
                DELETE FROM sessions 
                WHERE user_id = :user_id AND token != :except_token
            ");
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':except_token', $exceptToken, SQLITE3_TEXT);
        } else {
            $stmt = $this->db->prepare("DELETE FROM sessions WHERE user_id = :user_id");
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        }
        
        return $stmt->execute() !== false;
    }
    
    /**
     * List all users (admin only)
     * Excludes password hashes from response for security
     * 
     * @return array Result with status and users list
     */
    public function listUsers() {
        if (!$this->openDb()) {
            return ['status' => 'error', 'message' => 'Database error occurred', 'code' => 500];
        }
        
        $stmt = $this->db->prepare("
            SELECT id, username, is_admin, created_at, updated_at 
            FROM users 
            ORDER BY username ASC
        ");
        $result = $stmt->execute();
        
        if (!$result) {
            return ['status' => 'error', 'message' => 'Failed to retrieve users', 'code' => 500];
        }
        
        $users = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'is_admin' => (bool)$row['is_admin'],
                'created_at' => (int)$row['created_at'],
                'updated_at' => (int)$row['updated_at']
            ];
        }
        
        return [
            'status' => 'success',
            'users' => $users
        ];
    }
    
    /**
     * Create a new user (admin only)
     * Validates unique username and stores bcrypt hashed password
     * 
     * @param string $username Username for the new user
     * @param string $password Password for the new user
     * @param bool $isAdmin Whether the user should have admin privileges
     * @return array Result with status and message
     */
    public function createUser($username, $password, $isAdmin = false) {
        if (!$this->openDb()) {
            return ['status' => 'error', 'message' => 'Database error occurred', 'code' => 500];
        }
        
        // Validate username
        $username = trim($username);
        if (empty($username)) {
            return ['status' => 'error', 'message' => 'Username is required', 'code' => 400];
        }
        
        // Validate password minimum length
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return [
                'status' => 'error', 
                'message' => 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters',
                'code' => 400
            ];
        }
        
        // Check if username already exists
        $checkStmt = $this->db->prepare("SELECT id FROM users WHERE username = :username");
        $checkStmt->bindValue(':username', $username, SQLITE3_TEXT);
        $checkResult = $checkStmt->execute();
        
        if ($checkResult->fetchArray()) {
            return ['status' => 'error', 'message' => 'Username already exists', 'code' => 400];
        }
        
        // Hash password with bcrypt
        $passwordHash = $this->hashPassword($password);
        $now = time();
        
        // Insert new user
        $stmt = $this->db->prepare("
            INSERT INTO users (username, password_hash, is_admin, created_at, updated_at)
            VALUES (:username, :password_hash, :is_admin, :created_at, :updated_at)
        ");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':password_hash', $passwordHash, SQLITE3_TEXT);
        $stmt->bindValue(':is_admin', $isAdmin ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':created_at', $now, SQLITE3_INTEGER);
        $stmt->bindValue(':updated_at', $now, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            return ['status' => 'error', 'message' => 'Failed to create user', 'code' => 500];
        }
        
        $userId = $this->db->lastInsertRowID();
        
        return [
            'status' => 'success',
            'message' => 'User created successfully',
            'user' => [
                'id' => $userId,
                'username' => $username,
                'is_admin' => $isAdmin
            ]
        ];
    }
    
    /**
     * Delete a user (admin only)
     * Removes user and all their sessions
     * Prevents deletion of the last admin account
     * 
     * @param int $userId User ID to delete
     * @return array Result with status and message
     */
    public function deleteUser($userId) {
        if (!$this->openDb()) {
            return ['status' => 'error', 'message' => 'Database error occurred', 'code' => 500];
        }
        
        // Check if user exists
        $checkStmt = $this->db->prepare("SELECT id, username, is_admin FROM users WHERE id = :id");
        $checkStmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $checkResult = $checkStmt->execute();
        $user = $checkResult->fetchArray(SQLITE3_ASSOC);
        
        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found', 'code' => 404];
        }
        
        // If user is admin, check if they're the last admin
        if ($user['is_admin']) {
            $adminCountStmt = $this->db->prepare("SELECT COUNT(*) as count FROM users WHERE is_admin = 1");
            $adminCountResult = $adminCountStmt->execute();
            $adminCount = $adminCountResult->fetchArray(SQLITE3_ASSOC);
            
            if ((int)$adminCount['count'] <= 1) {
                return ['status' => 'error', 'message' => 'Cannot delete the last administrator', 'code' => 400];
            }
        }
        
        // Delete all sessions for this user first (foreign key constraint)
        $deleteSessionsStmt = $this->db->prepare("DELETE FROM sessions WHERE user_id = :user_id");
        $deleteSessionsStmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $deleteSessionsStmt->execute();
        
        // Delete the user
        $deleteUserStmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        $deleteUserStmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        
        if (!$deleteUserStmt->execute()) {
            return ['status' => 'error', 'message' => 'Failed to delete user', 'code' => 500];
        }
        
        return [
            'status' => 'success',
            'message' => 'User deleted successfully'
        ];
    }
    
    /**
     * Reset a user's password (admin only)
     * Generates a new random password and invalidates all sessions
     * Returns the new password to the admin
     * 
     * @param int $userId User ID to reset password for
     * @return array Result with status, message, and new password
     */
    public function resetPassword($userId) {
        if (!$this->openDb()) {
            return ['status' => 'error', 'message' => 'Database error occurred', 'code' => 500];
        }
        
        // Check if user exists
        $checkStmt = $this->db->prepare("SELECT id, username FROM users WHERE id = :id");
        $checkStmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $checkResult = $checkStmt->execute();
        $user = $checkResult->fetchArray(SQLITE3_ASSOC);
        
        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found', 'code' => 404];
        }
        
        // Generate a new random password (12 characters, alphanumeric)
        $newPassword = $this->generateRandomPassword(12);
        
        // Hash the new password
        $passwordHash = $this->hashPassword($newPassword);
        $now = time();
        
        // Update password in database
        $updateStmt = $this->db->prepare("
            UPDATE users 
            SET password_hash = :hash, updated_at = :updated_at 
            WHERE id = :id
        ");
        $updateStmt->bindValue(':hash', $passwordHash, SQLITE3_TEXT);
        $updateStmt->bindValue(':updated_at', $now, SQLITE3_INTEGER);
        $updateStmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        
        if (!$updateStmt->execute()) {
            return ['status' => 'error', 'message' => 'Failed to reset password', 'code' => 500];
        }
        
        // Invalidate all sessions for this user
        $this->invalidateUserSessions($userId);
        
        return [
            'status' => 'success',
            'message' => 'Password reset successfully',
            'new_password' => $newPassword,
            'username' => $user['username']
        ];
    }
    
    /**
     * Generate a random password
     * @param int $length Password length
     * @return string Random password
     */
    private function generateRandomPassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        $max = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        
        return $password;
    }
}


// API endpoint handler
if (basename($_SERVER['SCRIPT_FILENAME']) === 'auth.php') {
    header('Content-Type: application/json');
    secHeaders();
    
    $auth = new AuthService();
    $request = getRequest();
    $action = $request['action'] ?? '';
    
    switch ($action) {
        case 'login':
            if ($request['method'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
                break;
            }
            
            $username = $request['username'] ?? '';
            $password = $request['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Username and password required']);
                break;
            }
            
            $result = $auth->login($username, $password);
            
            if ($result['status'] === 'error') {
                $code = $result['code'] ?? 401;
                http_response_code($code);
                unset($result['code']);
            }
            
            echo json_encode($result);
            break;
            
        case 'logout':
            if ($request['method'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
                break;
            }
            
            $result = $auth->logout();
            echo json_encode($result);
            break;
            
        case 'verify':
            $user = $auth->verifySession();
            
            if ($user) {
                echo json_encode([
                    'status' => 'success',
                    'authenticated' => true,
                    'user' => $user
                ]);
            } else {
                http_response_code(401);
                echo json_encode([
                    'status' => 'error',
                    'authenticated' => false,
                    'message' => 'Authentication required'
                ]);
            }
            break;
            
        case 'change_password':
            if ($request['method'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
                break;
            }
            
            // Verify user is authenticated
            $user = $auth->verifySession();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
                break;
            }
            
            $currentPassword = $request['current_password'] ?? '';
            $newPassword = $request['new_password'] ?? '';
            
            if (empty($currentPassword) || empty($newPassword)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Current password and new password required']);
                break;
            }
            
            // Get current session token to preserve it
            $currentToken = $_COOKIE['rpidns_session'] ?? null;
            
            $result = $auth->changePassword($user['id'], $currentPassword, $newPassword, $currentToken);
            
            if ($result['status'] === 'error') {
                $code = $result['code'] ?? 400;
                http_response_code($code);
                unset($result['code']);
            }
            
            echo json_encode($result);
            break;
            
        case 'users':
            if ($request['method'] !== 'GET') {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
                break;
            }
            
            // Verify user is authenticated and is admin
            $user = $auth->verifySession();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
                break;
            }
            
            if (!$user['is_admin']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Administrator access required']);
                break;
            }
            
            $result = $auth->listUsers();
            
            if ($result['status'] === 'error') {
                $code = $result['code'] ?? 500;
                http_response_code($code);
                unset($result['code']);
            }
            
            echo json_encode($result);
            break;
            
        case 'create_user':
            if ($request['method'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
                break;
            }
            
            // Verify user is authenticated and is admin
            $user = $auth->verifySession();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
                break;
            }
            
            if (!$user['is_admin']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Administrator access required']);
                break;
            }
            
            $username = $request['username'] ?? '';
            $password = $request['password'] ?? '';
            $isAdmin = isset($request['is_admin']) ? (bool)$request['is_admin'] : false;
            
            if (empty($username) || empty($password)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Username and password required']);
                break;
            }
            
            $result = $auth->createUser($username, $password, $isAdmin);
            
            if ($result['status'] === 'error') {
                $code = $result['code'] ?? 400;
                http_response_code($code);
                unset($result['code']);
            }
            
            echo json_encode($result);
            break;
            
        case 'delete_user':
            if ($request['method'] !== 'DELETE' && $request['method'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
                break;
            }
            
            // Verify user is authenticated and is admin
            $user = $auth->verifySession();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
                break;
            }
            
            if (!$user['is_admin']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Administrator access required']);
                break;
            }
            
            $userId = isset($request['user_id']) ? (int)$request['user_id'] : 0;
            
            if ($userId <= 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Valid user ID required']);
                break;
            }
            
            $result = $auth->deleteUser($userId);
            
            if ($result['status'] === 'error') {
                $code = $result['code'] ?? 400;
                http_response_code($code);
                unset($result['code']);
            }
            
            echo json_encode($result);
            break;
            
        case 'reset_password':
            if ($request['method'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
                break;
            }
            
            // Verify user is authenticated and is admin
            $user = $auth->verifySession();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
                break;
            }
            
            if (!$user['is_admin']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Administrator access required']);
                break;
            }
            
            $userId = isset($request['user_id']) ? (int)$request['user_id'] : 0;
            
            if ($userId <= 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Valid user ID required']);
                break;
            }
            
            $result = $auth->resetPassword($userId);
            
            if ($result['status'] === 'error') {
                $code = $result['code'] ?? 400;
                http_response_code($code);
                unset($result['code']);
            }
            
            echo json_encode($result);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
}
?>
