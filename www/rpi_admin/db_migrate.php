<?php
/**
 * Database Migration Service for RpiDNS
 * Handles schema versioning and incremental database upgrades
 * 
 * (c) Vadim Pavlov 2020-2024
 */

require_once "/opt/rpidns/www/rpidns_vars.php";

class DbMigration {
    private $db;
    private $dbFile;
    private $targetVersion;
    private $inTransaction = false;
    
    /**
     * Constructor
     * @param string $dbFile Path to SQLite database file
     * @param int $targetVersion Target schema version (defaults to DBVersion constant)
     */
    public function __construct($dbFile = null, $targetVersion = null) {
        $this->dbFile = $dbFile ?? "/opt/rpidns/www/db/" . DBFile;
        $this->targetVersion = $targetVersion ?? DBVersion;
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
            error_log("[DbMigration] Failed to open database: " . $e->getMessage());
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
     * Get current schema version from database
     * @return int Current schema version (0 if not set)
     */
    public function getSchemaVersion() {
        if (!$this->openDb()) {
            return 0;
        }
        
        // First check if schema_version table exists
        $result = $this->db->querySingle(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='schema_version'"
        );
        
        if (!$result) {
            // Table doesn't exist, check PRAGMA user_version as fallback
            $version = $this->db->querySingle("PRAGMA user_version");
            return (int)$version;
        }
        
        // Get version from schema_version table
        $version = $this->db->querySingle("SELECT MAX(version) FROM schema_version");
        return $version ? (int)$version : 0;
    }
    
    /**
     * Set schema version after successful migration
     * @param int $version New schema version
     * @return bool Success status
     */
    public function setSchemaVersion($version) {
        if (!$this->openDb()) {
            return false;
        }
        
        // Ensure schema_version table exists
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS schema_version (
                version INTEGER NOT NULL,
                applied_at INTEGER NOT NULL
            )
        ");
        
        // Insert new version record
        $stmt = $this->db->prepare("INSERT INTO schema_version (version, applied_at) VALUES (:version, :applied_at)");
        $stmt->bindValue(':version', $version, SQLITE3_INTEGER);
        $stmt->bindValue(':applied_at', time(), SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($result) {
            // Also update PRAGMA user_version for compatibility
            $this->db->exec("PRAGMA user_version = $version");
            return true;
        }
        
        return false;
    }
    
    /**
     * Run all pending migrations
     * @return array Result with status and message
     */
    public function migrate() {
        if (!$this->openDb()) {
            return ['status' => 'error', 'message' => 'Failed to open database'];
        }
        
        $currentVersion = $this->getSchemaVersion();
        $results = [];
        
        error_log("[DbMigration] Current version: $currentVersion, Target version: {$this->targetVersion}");
        
        if ($currentVersion >= $this->targetVersion) {
            return [
                'status' => 'success',
                'message' => 'Database is up to date',
                'version' => $currentVersion
            ];
        }
        
        // Run migrations sequentially
        for ($v = $currentVersion + 1; $v <= $this->targetVersion; $v++) {
            $methodName = "migrateV" . ($v - 1) . "ToV" . $v;
            
            if (!method_exists($this, $methodName)) {
                error_log("[DbMigration] Migration method $methodName not found");
                continue;
            }
            
            error_log("[DbMigration] Running migration: $methodName");
            
            // Start transaction
            $this->beginTransaction();
            
            try {
                $result = $this->$methodName();
                
                if ($result['status'] === 'success') {
                    // Update schema version
                    if (!$this->setSchemaVersion($v)) {
                        throw new Exception("Failed to update schema version to $v");
                    }
                    
                    $this->commitTransaction();
                    $results[] = [
                        'migration' => $methodName,
                        'status' => 'success',
                        'message' => $result['message'] ?? "Migration to v$v completed"
                    ];
                    
                    error_log("[DbMigration] Migration $methodName completed successfully");
                } else {
                    throw new Exception($result['message'] ?? "Migration $methodName failed");
                }
            } catch (Exception $e) {
                $this->rollback();
                error_log("[DbMigration] Migration $methodName failed: " . $e->getMessage());
                
                return [
                    'status' => 'error',
                    'message' => "Migration $methodName failed: " . $e->getMessage(),
                    'version' => $currentVersion,
                    'results' => $results
                ];
            }
        }
        
        $this->closeDb();
        
        return [
            'status' => 'success',
            'message' => 'All migrations completed successfully',
            'version' => $this->targetVersion,
            'results' => $results
        ];
    }
    
    /**
     * Begin a database transaction
     */
    private function beginTransaction() {
        if (!$this->inTransaction) {
            $this->db->exec('BEGIN TRANSACTION');
            $this->inTransaction = true;
        }
    }
    
    /**
     * Commit the current transaction
     */
    private function commitTransaction() {
        if ($this->inTransaction) {
            $this->db->exec('COMMIT');
            $this->inTransaction = false;
        }
    }
    
    /**
     * Rollback the current transaction
     * @return bool Success status
     */
    public function rollback() {
        if ($this->inTransaction) {
            $result = $this->db->exec('ROLLBACK');
            $this->inTransaction = false;
            error_log("[DbMigration] Transaction rolled back");
            return $result;
        }
        return true;
    }
    
    /**
     * Migration from v1 to v2: Add authentication tables
     * @return array Result with status and message
     */
    protected function migrateV1ToV2() {
        $sql = "
            -- Users table
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                is_admin INTEGER NOT NULL DEFAULT 0,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
            
            -- Sessions table
            CREATE TABLE IF NOT EXISTS sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token TEXT NOT NULL UNIQUE,
                created_at INTEGER NOT NULL,
                expires_at INTEGER NOT NULL,
                ip_address TEXT,
                user_agent TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token);
            CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);
            CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions(expires_at);
            
            -- Login attempts table for rate limiting
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                attempted_at INTEGER NOT NULL,
                success INTEGER NOT NULL DEFAULT 0
            );
            CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address);
            CREATE INDEX IF NOT EXISTS idx_login_attempts_time ON login_attempts(attempted_at);
        ";
        
        // Execute each statement separately
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (empty($statement)) continue;
            
            if (!$this->db->exec($statement)) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to execute: ' . $statement . ' - Error: ' . $this->db->lastErrorMsg()
                ];
            }
        }
        
        // Import users from htpasswd if file exists
        $htpasswdPath = "/opt/rpidns/conf/.htpasswd";
        $importResult = $this->importHtpasswdUsers($htpasswdPath);
        
        return [
            'status' => 'success',
            'message' => 'Authentication tables created. ' . ($importResult['imported'] ?? 0) . ' users imported from htpasswd.'
        ];
    }
    
    /**
     * Import users from .htpasswd file
     * @param string $htpasswdPath Path to htpasswd file
     * @return array Result with imported count
     */
    public function importHtpasswdUsers($htpasswdPath) {
        if (!file_exists($htpasswdPath)) {
            error_log("[DbMigration] htpasswd file not found: $htpasswdPath");
            return ['imported' => 0, 'message' => 'htpasswd file not found'];
        }
        
        $lines = file($htpasswdPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return ['imported' => 0, 'message' => 'htpasswd file is empty'];
        }
        
        $imported = 0;
        $isFirstUser = true;
        $now = time();
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            
            $username = trim($parts[0]);
            $hash = trim($parts[1]);
            
            if (empty($username) || empty($hash)) {
                continue;
            }
            
            // Check if user already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $result = $stmt->execute();
            
            if ($result->fetchArray()) {
                error_log("[DbMigration] User '$username' already exists, skipping");
                continue;
            }
            
            // Determine hash type and convert if needed
            $passwordHash = $this->convertHtpasswdHash($hash);
            
            if ($passwordHash === null) {
                error_log("[DbMigration] Unsupported hash format for user '$username'");
                continue;
            }
            
            // First imported user becomes admin
            $isAdmin = $isFirstUser ? 1 : 0;
            
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password_hash, is_admin, created_at, updated_at)
                VALUES (:username, :password_hash, :is_admin, :created_at, :updated_at)
            ");
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':password_hash', $passwordHash, SQLITE3_TEXT);
            $stmt->bindValue(':is_admin', $isAdmin, SQLITE3_INTEGER);
            $stmt->bindValue(':created_at', $now, SQLITE3_INTEGER);
            $stmt->bindValue(':updated_at', $now, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                $imported++;
                $isFirstUser = false;
                error_log("[DbMigration] Imported user '$username'" . ($isAdmin ? " as admin" : ""));
            } else {
                error_log("[DbMigration] Failed to import user '$username': " . $this->db->lastErrorMsg());
            }
        }
        
        return ['imported' => $imported, 'message' => "$imported users imported"];
    }
    
    /**
     * Convert htpasswd hash to bcrypt if needed
     * @param string $hash Original hash from htpasswd
     * @return string|null Bcrypt hash or null if unsupported
     */
    private function convertHtpasswdHash($hash) {
        // Check if already bcrypt ($2y$ or $2a$ or $2b$)
        if (preg_match('/^\$2[ayb]\$/', $hash)) {
            return $hash;
        }
        
        // Apache MD5 ($apr1$) - cannot be converted, user will need to reset password
        if (strpos($hash, '$apr1$') === 0) {
            error_log("[DbMigration] Apache MD5 hash detected - user will need password reset");
            // Store the hash as-is, but mark it for reset
            // We'll handle this in the auth service
            return $hash;
        }
        
        // SHA1 ({SHA}) - cannot be converted
        if (strpos($hash, '{SHA}') === 0) {
            error_log("[DbMigration] SHA1 hash detected - user will need password reset");
            return $hash;
        }
        
        // Plain crypt - cannot be converted
        if (strlen($hash) === 13) {
            error_log("[DbMigration] Crypt hash detected - user will need password reset");
            return $hash;
        }
        
        // Unknown format
        return $hash;
    }
    
    /**
     * Get list of available migrations
     * @return array List of migration methods
     */
    public function getMigrations() {
        $migrations = [];
        $methods = get_class_methods($this);
        
        foreach ($methods as $method) {
            if (preg_match('/^migrateV(\d+)ToV(\d+)$/', $method, $matches)) {
                $migrations[] = [
                    'method' => $method,
                    'from' => (int)$matches[1],
                    'to' => (int)$matches[2]
                ];
            }
        }
        
        usort($migrations, function($a, $b) {
            return $a['from'] - $b['from'];
        });
        
        return $migrations;
    }
    
    /**
     * Check if migration is needed
     * @return bool True if migration is needed
     */
    public function needsMigration() {
        return $this->getSchemaVersion() < $this->targetVersion;
    }
    
    /**
     * Get database connection (for testing purposes)
     * @return SQLite3|null Database connection
     */
    public function getDb() {
        $this->openDb();
        return $this->db;
    }
}

// CLI execution for manual migration
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'db_migrate.php') {
    $migration = new DbMigration();
    
    echo "RpiDNS Database Migration\n";
    echo "========================\n\n";
    
    $currentVersion = $migration->getSchemaVersion();
    echo "Current schema version: $currentVersion\n";
    echo "Target schema version: " . DBVersion . "\n\n";
    
    if (!$migration->needsMigration()) {
        echo "Database is up to date. No migration needed.\n";
        exit(0);
    }
    
    echo "Running migrations...\n\n";
    $result = $migration->migrate();
    
    if ($result['status'] === 'success') {
        echo "Migration completed successfully!\n";
        echo "New schema version: " . $result['version'] . "\n";
        exit(0);
    } else {
        echo "Migration failed: " . $result['message'] . "\n";
        exit(1);
    }
}
?>
