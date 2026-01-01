<?php
/**
 * BindConfigManager - Manages BIND DNS server configuration for RPZ feeds
 * 
 * This class handles parsing, modifying, and validating BIND configuration files
 * for Response Policy Zone (RPZ) feed management.
 */
class BindConfigManager {
    /** @var string Path to the BIND configuration file */
    private $configPath;
    
    /** @var string Directory for configuration backups */
    private $backupDir;
    
    /** @var array Possible BIND config file locations */
    private static $configPaths = [
        '/etc/bind/named.conf.options',
        '/etc/bind/named.conf',
        '/etc/named.conf',
        '/etc/named/named.conf'
    ];
    
    /** @var array Valid policy actions for RPZ feeds */
    public static $validActions = ['nxdomain', 'nodata', 'passthru', 'drop', 'cname', 'given'];
    
    /**
     * Constructor - detects and sets the BIND configuration file path
     * 
     * @param string|null $configPath Optional explicit config path (for testing)
     * @throws Exception If no valid BIND configuration file is found
     */
    public function __construct(?string $configPath = null) {
        if ($configPath !== null) {
            $this->configPath = $configPath;
        } else {
            $this->configPath = $this->detectConfigPath();
        }
        
        $this->backupDir = '/opt/rpidns/backups/bind';
    }
    
    /**
     * Detect the BIND configuration file path
     * 
     * @return string The path to the configuration file
     * @throws Exception If no valid configuration file is found
     */
    private function detectConfigPath(): string {
        foreach (self::$configPaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }
        
        throw new Exception('No valid BIND configuration file found');
    }
    
    /**
     * Get the current configuration file path
     * 
     * @return string The configuration file path
     */
    public function getConfigPath(): string {
        return $this->configPath;
    }

    
    /**
     * Extract TSIG key name from the BIND configuration
     * 
     * Parses the configuration file to find TSIG key definitions used for
     * zone transfers, particularly for ioc2rpz.net feeds.
     * 
     * Handles various TSIG key definition formats:
     * - key "keyname" { algorithm ...; secret "..."; };
     * - key keyname { ... };
     * 
     * @return string|null The TSIG key name, or null if not found
     */
    public function getTsigKeyName(): ?string {
        if (!file_exists($this->configPath)) {
            return null;
        }
        
        $content = file_get_contents($this->configPath);
        if ($content === false) {
            return null;
        }
        
        // Pattern 1: key "keyname" { ... } or key 'keyname' { ... }
        // This matches keys with quoted names
        if (preg_match('/key\s+["\']([^"\']+)["\']\s*\{/i', $content, $matches)) {
            return $matches[1];
        }
        
        // Pattern 2: key keyname { ... } (unquoted)
        // This matches keys without quotes
        if (preg_match('/key\s+([a-zA-Z0-9_.-]+)\s*\{/i', $content, $matches)) {
            return $matches[1];
        }
        
        // Pattern 3: Look for TSIG key referenced in zone transfer configuration
        // masters { ip key "keyname"; };
        if (preg_match('/masters\s*\{[^}]*key\s+["\']?([^"\';\s]+)["\']?\s*;/i', $content, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get full TSIG key configuration including algorithm and secret
     * 
     * @param string $keyName The name of the key to find
     * @return array|null Array with 'name', 'algorithm', 'secret' or null if not found
     */
    public function getTsigKeyConfig(string $keyName): ?array {
        if (!file_exists($this->configPath)) {
            return null;
        }
        
        $content = file_get_contents($this->configPath);
        if ($content === false) {
            return null;
        }
        
        // Match the full key block for the specified key name
        $pattern = '/key\s+["\']?' . preg_quote($keyName, '/') . '["\']?\s*\{([^}]+)\}/is';
        
        if (!preg_match($pattern, $content, $matches)) {
            return null;
        }
        
        $keyBlock = $matches[1];
        $result = ['name' => $keyName];
        
        // Extract algorithm
        if (preg_match('/algorithm\s+([^;]+);/i', $keyBlock, $algoMatch)) {
            $result['algorithm'] = trim($algoMatch[1], " \t\n\r\0\x0B\"'");
        }
        
        // Extract secret
        if (preg_match('/secret\s+["\']?([^"\';\s]+)["\']?\s*;/i', $keyBlock, $secretMatch)) {
            $result['secret'] = $secretMatch[1];
        }
        
        return $result;
    }

    
    /**
     * Get all configured RPZ feeds from the BIND configuration
     * 
     * Parses the response-policy statement and zone definitions to extract
     * feed information including order, policy action, and source type.
     * 
     * @return array Array of feed objects with keys: feed, action, desc, source, enabled, order
     */
    public function getFeeds(): array {
        if (!file_exists($this->configPath)) {
            return [];
        }
        
        $content = file_get_contents($this->configPath);
        if ($content === false) {
            return [];
        }
        
        $feeds = [];
        $order = 1;
        
        // Parse response-policy statement for feed order and actions
        // Format: zone "feedname" policy action [log yes|no];
        // Disabled feeds are commented out: // zone "feedname" ...
        
        // First, get all feeds from response-policy (both enabled and disabled)
        $responsePolicyFeeds = $this->parseResponsePolicy($content);
        
        // Get zone definitions for additional metadata
        $zoneDefinitions = $this->parseZoneDefinitions($content);
        
        // Combine information from both sources
        foreach ($responsePolicyFeeds as $feedName => $feedInfo) {
            $zoneInfo = $zoneDefinitions[$feedName] ?? [];
            
            $feeds[] = [
                'feed' => $feedName,
                'action' => $feedInfo['action'],
                'desc' => $feedInfo['desc'] ?? $zoneInfo['desc'] ?? '',
                'source' => $this->determineSourceType($feedName, $zoneInfo),
                'enabled' => $feedInfo['enabled'],
                'order' => $order++,
                'cnameTarget' => $feedInfo['cnameTarget'] ?? null,
                'primaryServer' => $zoneInfo['primaryServer'] ?? null,
                'tsigKeyName' => $zoneInfo['tsigKeyName'] ?? null
            ];
        }
        
        return $feeds;
    }
    
    /**
     * Parse the response-policy statement from config content
     * 
     * @param string $content The configuration file content
     * @return array Associative array of feed name => feed info
     */
    private function parseResponsePolicy(string $content): array {
        $feeds = [];
        
        // Match response-policy block
        if (!preg_match('/response-policy\s*\{([^}]+)\}/is', $content, $rpMatch)) {
            // Fallback: try to match individual zone policy lines (legacy format)
            return $this->parseLegacyResponsePolicy($content);
        }
        
        $rpBlock = $rpMatch[1];
        
        // Match each zone line in response-policy
        // Formats supported:
        // - zone "name" policy nxdomain; # comment
        // - zone "name" policy passthru log no; # comment
        // - zone "name" policy cname target.domain; # comment (CNAME action with target)
        // - // zone "name" policy action; (commented/disabled)
        // 
        // The regex captures everything after "policy" up to the semicolon,
        // then we parse the action and optional cname target separately
        $pattern = '/^\s*(\/\/|#)?\s*zone\s+["\']([^"\']+)["\']\s+policy\s+([^;]+);\s*(?:#(.*))?$/im';
        
        if (preg_match_all($pattern, $rpBlock, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $isCommented = !empty($match[1]);
                $feedName = $match[2];
                $policyPart = trim($match[3]);
                $desc = isset($match[4]) ? trim($match[4]) : '';
                
                // Parse the policy part to extract action and optional cname target
                // Possible formats:
                // - "nxdomain"
                // - "passthru log no"
                // - "cname target.domain"
                // - "cname target.domain log no"
                $action = '';
                $cnameTarget = null;
                
                // Check if it's a CNAME action (format: "cname target [log yes|no]")
                if (preg_match('/^cname\s+([^\s]+)(?:\s+log\s+\S+)?$/i', $policyPart, $cnameMatch)) {
                    $action = 'cname';
                    $cnameTarget = $cnameMatch[1];
                } else {
                    // Regular action (format: "action [log yes|no]")
                    // Remove optional "log yes|no" suffix
                    $action = preg_replace('/\s+log\s+\S+$/i', '', $policyPart);
                    $action = strtolower(trim($action));
                }
                
                $feeds[$feedName] = [
                    'action' => $action,
                    'desc' => $desc,
                    'enabled' => !$isCommented,
                    'cnameTarget' => $cnameTarget
                ];
            }
        }
        
        return $feeds;
    }
    
    /**
     * Parse legacy response-policy format (zone lines outside response-policy block)
     * 
     * @param string $content The configuration file content
     * @return array Associative array of feed name => feed info
     */
    private function parseLegacyResponsePolicy(string $content): array {
        $feeds = [];
        
        // Match zone lines with policy (legacy format used in existing RpiDNS)
        // Format: zone "name" policy action; #comment
        // Or: zone "name" policy cname target; #comment
        $pattern = '/^\s*(\/\/|#)?\s*zone\s+["\']([^"\']+)["\']\s+policy\s+([^;]+);\s*(?:#(.*))?$/im';
        
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $isCommented = !empty($match[1]);
                $feedName = $match[2];
                $policyPart = trim($match[3]);
                $desc = isset($match[4]) ? trim($match[4]) : '';
                
                // Parse the policy part to extract action and optional cname target
                $action = '';
                $cnameTarget = null;
                
                // Check if it's a CNAME action (format: "cname target [log yes|no]")
                if (preg_match('/^cname\s+([^\s]+)(?:\s+log\s+\S+)?$/i', $policyPart, $cnameMatch)) {
                    $action = 'cname';
                    $cnameTarget = $cnameMatch[1];
                } else {
                    // Regular action (format: "action [log yes|no]")
                    $action = preg_replace('/\s+log\s+\S+$/i', '', $policyPart);
                    $action = strtolower(trim($action));
                }
                
                $feeds[$feedName] = [
                    'action' => $action,
                    'desc' => $desc,
                    'enabled' => !$isCommented,
                    'cnameTarget' => $cnameTarget
                ];
            }
        }
        
        return $feeds;
    }
    
    /**
     * Parse zone definitions from config content
     * 
     * @param string $content The configuration file content
     * @return array Associative array of zone name => zone info
     */
    private function parseZoneDefinitions(string $content): array {
        $zones = [];
        
        // Match zone blocks: zone "name" { type ...; masters { ... }; file "..."; };
        $pattern = '/zone\s+["\']([^"\']+)["\']\s*\{([^}]+)\}/is';
        
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $zoneName = $match[1];
                $zoneBlock = $match[2];
                
                $zoneInfo = [];
                
                // Extract zone type
                if (preg_match('/type\s+(\S+)\s*;/i', $zoneBlock, $typeMatch)) {
                    $zoneInfo['type'] = strtolower(trim($typeMatch[1]));
                }
                
                // Extract masters (for slave zones)
                if (preg_match('/masters\s*\{([^}]+)\}/i', $zoneBlock, $mastersMatch)) {
                    $mastersBlock = $mastersMatch[1];
                    
                    // Extract IP address
                    if (preg_match('/(\d+\.\d+\.\d+\.\d+|[a-fA-F0-9:]+)/', $mastersBlock, $ipMatch)) {
                        $zoneInfo['primaryServer'] = $ipMatch[1];
                    }
                    
                    // Extract TSIG key reference
                    if (preg_match('/key\s+["\']?([^"\';\s]+)["\']?/i', $mastersBlock, $keyMatch)) {
                        $zoneInfo['tsigKeyName'] = $keyMatch[1];
                    }
                }
                
                // Extract file path
                if (preg_match('/file\s+["\']([^"\']+)["\']\s*;/i', $zoneBlock, $fileMatch)) {
                    $zoneInfo['file'] = $fileMatch[1];
                }
                
                // Extract allow-update (for local zones)
                if (preg_match('/allow-update\s*\{([^}]+)\}/i', $zoneBlock, $updateMatch)) {
                    $zoneInfo['allowUpdate'] = trim($updateMatch[1]);
                }
                
                $zones[$zoneName] = $zoneInfo;
            }
        }
        
        return $zones;
    }
    
    /**
     * Determine the source type of a feed based on its name and zone configuration
     * 
     * @param string $feedName The feed name
     * @param array $zoneInfo Zone configuration info
     * @return string One of: 'ioc2rpz', 'local', 'third-party'
     */
    private function determineSourceType(string $feedName, array $zoneInfo): string {
        // ioc2rpz.net feeds typically have .ioc2rpz in the name (but not .rpidns)
        if (strpos($feedName, '.ioc2rpz') !== false && strpos($feedName, '.rpidns') === false) {
            return 'ioc2rpz';
        }
        
        // Local feeds are typically master zones with .rpidns suffix
        if (strpos($feedName, '.rpidns') !== false) {
            return 'local';
        }
        
        // Check zone type - master zones without .rpidns are local
        if (isset($zoneInfo['type']) && $zoneInfo['type'] === 'master') {
            return 'local';
        }
        
        // Slave zones with external masters are third-party
        if (isset($zoneInfo['type']) && $zoneInfo['type'] === 'slave') {
            // Check if it's from ioc2rpz.net (known IPs)
            $ioc2rpzIps = ['94.130.30.123', '2a01:4f8:121:43ea::100:53'];
            if (isset($zoneInfo['primaryServer']) && in_array($zoneInfo['primaryServer'], $ioc2rpzIps)) {
                return 'ioc2rpz';
            }
            return 'third-party';
        }
        
        // Default to third-party for unknown configurations
        return 'third-party';
    }

    
    /**
     * Create a timestamped backup of the configuration file
     * 
     * @return string The path to the backup file
     * @throws Exception If backup creation fails
     */
    public function backup(): string {
        if (!file_exists($this->configPath)) {
            throw new Exception('Configuration file does not exist: ' . $this->configPath);
        }
        
        // Ensure backup directory exists
        if (!file_exists($this->backupDir)) {
            $oldumask = umask(0);
            if (!mkdir($this->backupDir, 0755, true)) {
                umask($oldumask);
                throw new Exception('Failed to create backup directory: ' . $this->backupDir);
            }
            umask($oldumask);
        }
        
        // Create timestamped backup filename
        $timestamp = date('Y-m-d_H-i-s');
        $configBasename = basename($this->configPath);
        $backupPath = $this->backupDir . '/' . $configBasename . '.' . $timestamp . '.bak';
        
        // Copy the configuration file
        if (!copy($this->configPath, $backupPath)) {
            throw new Exception('Failed to create backup: ' . $backupPath);
        }
        
        // Set appropriate permissions
        chmod($backupPath, 0644);
        
        // Clean up old backups (keep last 10)
        $this->cleanupOldBackups();
        
        return $backupPath;
    }
    
    /**
     * Restore configuration from a backup file
     * 
     * @param string $backupPath Path to the backup file to restore
     * @return bool True on success
     * @throws Exception If restore fails
     */
    public function restore(string $backupPath): bool {
        if (!file_exists($backupPath)) {
            throw new Exception('Backup file does not exist: ' . $backupPath);
        }
        
        if (!is_readable($backupPath)) {
            throw new Exception('Backup file is not readable: ' . $backupPath);
        }
        
        // Verify the backup is a valid BIND config before restoring
        $validation = $this->validateConfigFile($backupPath);
        if (!$validation['success']) {
            throw new Exception('Backup file is not a valid BIND configuration: ' . $validation['error']);
        }
        
        // Copy backup to config location
        if (!copy($backupPath, $this->configPath)) {
            throw new Exception('Failed to restore configuration from backup');
        }
        
        return true;
    }
    
    /**
     * Get list of available backup files
     * 
     * @return array Array of backup file info with 'path', 'timestamp', 'size'
     */
    public function getBackups(): array {
        if (!file_exists($this->backupDir)) {
            return [];
        }
        
        $backups = [];
        $configBasename = basename($this->configPath);
        $pattern = $this->backupDir . '/' . $configBasename . '.*.bak';
        
        foreach (glob($pattern) as $file) {
            $backups[] = [
                'path' => $file,
                'filename' => basename($file),
                'timestamp' => filemtime($file),
                'size' => filesize($file)
            ];
        }
        
        // Sort by timestamp descending (newest first)
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return $backups;
    }
    
    /**
     * Clean up old backup files, keeping only the most recent ones
     * 
     * @param int $keepCount Number of backups to keep (default: 10)
     */
    private function cleanupOldBackups(int $keepCount = 10): void {
        $backups = $this->getBackups();
        
        // Remove backups beyond the keep count
        $toDelete = array_slice($backups, $keepCount);
        
        foreach ($toDelete as $backup) {
            @unlink($backup['path']);
        }
    }

    
    /**
     * Validate the current BIND configuration using named-checkconf
     * 
     * @return array ['success' => bool, 'error' => string|null, 'output' => string]
     */
    public function validate(): array {
        return $this->validateConfigFile($this->configPath);
    }
    
    /**
     * Validate a specific BIND configuration file using named-checkconf
     * 
     * @param string $configFile Path to the configuration file to validate
     * @return array ['success' => bool, 'error' => string|null, 'output' => string]
     */
    private function validateConfigFile(string $configFile): array {
        $output = [];
        $returnCode = 0;
        
        // Find named-checkconf binary
        $checkconfPaths = [
            '/usr/sbin/named-checkconf',
            '/usr/bin/named-checkconf',
            '/usr/local/sbin/named-checkconf'
        ];
        
        $checkconfBin = null;
        foreach ($checkconfPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $checkconfBin = $path;
                break;
            }
        }
        
        if ($checkconfBin === null) {
            // Try to find it via which
            $checkconfBin = trim(shell_exec('which named-checkconf 2>/dev/null') ?? '');
            if (empty($checkconfBin) || !file_exists($checkconfBin)) {
                // named-checkconf not available - skip validation
                // This allows development/testing without BIND installed
                return [
                    'success' => true,
                    'error' => null,
                    'output' => 'Validation skipped: named-checkconf not found'
                ];
            }
        }
        
        // Run named-checkconf
        $command = escapeshellcmd($checkconfBin) . ' ' . escapeshellarg($configFile) . ' 2>&1';
        exec($command, $output, $returnCode);
        
        $outputStr = implode("\n", $output);
        
        return [
            'success' => ($returnCode === 0),
            'error' => ($returnCode !== 0) ? $outputStr : null,
            'output' => $outputStr
        ];
    }

    
    /**
     * Reload BIND service to apply configuration changes
     * 
     * Supports both local and containerized BIND deployments.
     * Uses rndc reload for graceful configuration reload.
     * 
     * @return array ['success' => bool, 'error' => string|null, 'output' => string]
     */
    public function reloadBind(): array {
        // First validate the configuration
        $validation = $this->validate();
        if (!$validation['success']) {
            return [
                'success' => false,
                'error' => 'Configuration validation failed: ' . $validation['error'],
                'output' => $validation['output']
            ];
        }
        
        // Detect deployment type and use appropriate reload method
        $deploymentType = $this->detectDeploymentType();
        
        switch ($deploymentType) {
            case 'container':
                return $this->reloadBindContainer();
            case 'local':
            default:
                return $this->reloadBindLocal();
        }
    }
    
    /**
     * Detect whether BIND is running locally or in a container
     * 
     * @return string 'container' or 'local'
     */
    private function detectDeploymentType(): string {
        // Check for Docker environment
        if (file_exists('/.dockerenv')) {
            return 'container';
        }
        
        // Check if we're in a container by looking at cgroup
        if (file_exists('/proc/1/cgroup')) {
            $cgroup = file_get_contents('/proc/1/cgroup');
            if (strpos($cgroup, 'docker') !== false || strpos($cgroup, 'lxc') !== false) {
                return 'container';
            }
        }
        
        // Check for docker-compose environment variable
        if (getenv('BIND_CONTAINER_NAME') !== false) {
            return 'container';
        }
        
        return 'local';
    }
    
    /**
     * Reload BIND running locally using rndc
     * 
     * @return array ['success' => bool, 'error' => string|null, 'output' => string]
     */
    private function reloadBindLocal(): array {
        $output = [];
        $returnCode = 0;
        
        // Find rndc binary
        $rndcPaths = [
            '/usr/sbin/rndc',
            '/usr/bin/rndc',
            '/usr/local/sbin/rndc'
        ];
        
        $rndcBin = null;
        foreach ($rndcPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $rndcBin = $path;
                break;
            }
        }
        
        if ($rndcBin === null) {
            $rndcBin = trim(shell_exec('which rndc 2>/dev/null') ?? '');
            if (empty($rndcBin) || !file_exists($rndcBin)) {
                return [
                    'success' => false,
                    'error' => 'rndc not found',
                    'output' => ''
                ];
            }
        }
        
        // Execute rndc reload
        $command = escapeshellcmd($rndcBin) . ' reload 2>&1';
        exec($command, $output, $returnCode);
        
        $outputStr = implode("\n", $output);
        
        // rndc reload returns 0 on success
        // Also check output for success message
        $success = ($returnCode === 0) || 
                   (stripos($outputStr, 'server reload successful') !== false);
        
        return [
            'success' => $success,
            'error' => $success ? null : $outputStr,
            'output' => $outputStr
        ];
    }
    
    /**
     * Reload BIND running in a container
     * 
     * @return array ['success' => bool, 'error' => string|null, 'output' => string]
     */
    private function reloadBindContainer(): array {
        $output = [];
        $returnCode = 0;
        
        // Get container name from environment or use default
        $containerName = getenv('BIND_CONTAINER_NAME') ?: 'rpidns-bind';
        
        // Try docker exec first
        $command = 'docker exec ' . escapeshellarg($containerName) . ' rndc reload 2>&1';
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            // Try docker-compose if docker exec failed
            $output = [];
            $command = 'docker-compose exec -T bind rndc reload 2>&1';
            exec($command, $output, $returnCode);
        }
        
        $outputStr = implode("\n", $output);
        
        $success = ($returnCode === 0) || 
                   (stripos($outputStr, 'server reload successful') !== false);
        
        return [
            'success' => $success,
            'error' => $success ? null : $outputStr,
            'output' => $outputStr
        ];
    }
    
    /**
     * Get the backup directory path
     * 
     * @return string The backup directory path
     */
    public function getBackupDir(): string {
        return $this->backupDir;
    }
    
    /**
     * Set the backup directory path
     * 
     * @param string $dir The backup directory path
     */
    public function setBackupDir(string $dir): void {
        $this->backupDir = $dir;
    }

    
    /**
     * Add one or more feeds to the BIND configuration
     * 
     * Supports ioc2rpz, local, and third-party feed types.
     * Creates zone configurations and adds feeds to response-policy statement.
     * 
     * @param array $feeds Array of feed configurations, each containing:
     *   - feed: string (required) - Feed/zone name
     *   - source: string (required) - 'ioc2rpz', 'local', or 'third-party'
     *   - action: string (optional) - Policy action, defaults based on source
     *   - description: string (optional) - Feed description
     *   - cnameTarget: string (optional) - Required if action is 'cname'
     *   - primaryServer: string (optional) - Required for third-party feeds
     *   - tsigKeyName: string (optional) - TSIG key name for zone transfers
     *   - tsigKeySecret: string (optional) - TSIG key secret (for new keys)
     * @return array ['success' => bool, 'error' => string|null, 'added' => int]
     */
    public function addFeeds(array $feeds): array {
        if (empty($feeds)) {
            return ['success' => false, 'error' => 'No feeds provided', 'added' => 0];
        }
        
        // Validate all feeds before making changes
        foreach ($feeds as $feed) {
            $validation = $this->validateFeedConfig($feed);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error'], 'added' => 0];
            }
        }
        
        // Check for duplicate feeds
        $existingFeeds = $this->getFeeds();
        $existingNames = array_column($existingFeeds, 'feed');
        
        foreach ($feeds as $feed) {
            if (in_array($feed['feed'], $existingNames)) {
                return [
                    'success' => false, 
                    'error' => 'Feed already exists: ' . $feed['feed'], 
                    'added' => 0
                ];
            }
        }
        
        // Create backup before making changes
        try {
            $backupPath = $this->backup();
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Backup failed: ' . $e->getMessage(), 'added' => 0];
        }
        
        // Read current configuration
        $content = file_get_contents($this->configPath);
        if ($content === false) {
            return ['success' => false, 'error' => 'Failed to read configuration file', 'added' => 0];
        }
        
        $addedCount = 0;
        
        try {
            foreach ($feeds as $feed) {
                // Add zone configuration
                $content = $this->addZoneConfig($content, $feed);
                
                // Add to response-policy statement
                $content = $this->addToResponsePolicy($content, $feed);
                
                $addedCount++;
            }
            
            // Write updated configuration
            if (file_put_contents($this->configPath, $content) === false) {
                throw new Exception('Failed to write configuration file');
            }
            
            // Validate the new configuration
            $validation = $this->validate();
            if (!$validation['success']) {
                // Rollback on validation failure
                $this->restore($backupPath);
                return [
                    'success' => false, 
                    'error' => 'Configuration validation failed: ' . $validation['error'], 
                    'added' => 0
                ];
            }
            
            return ['success' => true, 'error' => null, 'added' => $addedCount];
            
        } catch (Exception $e) {
            // Rollback on any error
            try {
                $this->restore($backupPath);
            } catch (Exception $restoreEx) {
                // Log restore failure but return original error
            }
            return ['success' => false, 'error' => $e->getMessage(), 'added' => 0];
        }
    }
    
    /**
     * Validate a feed configuration array
     * 
     * @param array $feed Feed configuration to validate
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private function validateFeedConfig(array $feed): array {
        // Required fields
        if (empty($feed['feed'])) {
            return ['valid' => false, 'error' => 'Feed name is required'];
        }
        
        if (empty($feed['source'])) {
            return ['valid' => false, 'error' => 'Feed source is required'];
        }
        
        // Validate source type
        $validSources = ['ioc2rpz', 'local', 'third-party'];
        if (!in_array($feed['source'], $validSources)) {
            return ['valid' => false, 'error' => 'Invalid feed source: ' . $feed['source']];
        }
        
        // Validate feed name (DNS naming conventions)
        if (!$this->isValidDnsName($feed['feed'])) {
            return ['valid' => false, 'error' => 'Invalid feed name: must follow DNS naming conventions'];
        }
        
        // Validate action if provided
        if (!empty($feed['action'])) {
            $action = strtolower($feed['action']);
            if (!in_array($action, self::$validActions)) {
                return ['valid' => false, 'error' => 'Invalid policy action: ' . $feed['action']];
            }
            
            // CNAME action requires target
            if ($action === 'cname' && empty($feed['cnameTarget'])) {
                return ['valid' => false, 'error' => 'CNAME action requires a target domain'];
            }
        }
        
        // Third-party feeds require primary server
        if ($feed['source'] === 'third-party' && empty($feed['primaryServer'])) {
            return ['valid' => false, 'error' => 'Third-party feeds require a primary server'];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Validate a DNS name according to RFC 1035
     * 
     * @param string $name The DNS name to validate
     * @return bool True if valid
     */
    private function isValidDnsName(string $name): bool {
        // Max total length is 253 characters
        if (strlen($name) > 253 || strlen($name) < 1) {
            return false;
        }
        
        // Split into labels
        $labels = explode('.', $name);
        
        foreach ($labels as $label) {
            // Each label max 63 characters
            if (strlen($label) > 63 || strlen($label) < 1) {
                return false;
            }
            
            // Must start and end with alphanumeric
            if (!preg_match('/^[a-zA-Z0-9]/', $label) || !preg_match('/[a-zA-Z0-9]$/', $label)) {
                return false;
            }
            
            // Can only contain alphanumeric and hyphens
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $label)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Add zone configuration for a feed
     * 
     * @param string $content Current config content
     * @param array $feed Feed configuration
     * @return string Updated config content
     */
    private function addZoneConfig(string $content, array $feed): string {
        $zoneName = $feed['feed'];
        $source = $feed['source'];
        
        $zoneConfig = "\n// Zone configuration for {$zoneName}\n";
        $zoneConfig .= "zone \"{$zoneName}\" {\n";
        
        switch ($source) {
            case 'ioc2rpz':
                $zoneConfig .= $this->generateIoc2rpzZoneConfig($feed);
                break;
                
            case 'local':
                $zoneConfig .= $this->generateLocalZoneConfig($feed);
                break;
                
            case 'third-party':
                $zoneConfig .= $this->generateThirdPartyZoneConfig($feed);
                break;
        }
        
        $zoneConfig .= "};\n";
        
        // Append zone config at the end of the file
        return $content . $zoneConfig;
    }
    
    /**
     * Generate zone configuration for ioc2rpz.net feeds
     * 
     * @param array $feed Feed configuration
     * @return string Zone configuration content
     */
    private function generateIoc2rpzZoneConfig(array $feed): string {
        $zoneName = $feed['feed'];
        $tsigKey = $feed['tsigKeyName'] ?? $this->getTsigKeyName();
        
        // ioc2rpz.net server IPs
        $primaryIp = '94.130.30.123';
        
        $config = "    type slave;\n";
        $config .= "    file \"/var/cache/bind/{$zoneName}\";\n";
        $config .= "    masters { {$primaryIp}";
        
        if ($tsigKey) {
            $config .= " key \"{$tsigKey}\"";
        }
        
        $config .= "; };\n";
        
        return $config;
    }
    
    /**
     * Generate zone configuration for local feeds
     * 
     * @param array $feed Feed configuration
     * @return string Zone configuration content
     */
    private function generateLocalZoneConfig(array $feed): string {
        $zoneName = $feed['feed'];
        
        $config = "    type master;\n";
        $config .= "    file \"/var/cache/bind/{$zoneName}\";\n";
        $config .= "    allow-update { localhost; };\n";
        
        return $config;
    }
    
    /**
     * Generate zone configuration for third-party feeds
     * 
     * @param array $feed Feed configuration
     * @return string Zone configuration content
     */
    private function generateThirdPartyZoneConfig(array $feed): string {
        $zoneName = $feed['feed'];
        $primaryServer = $feed['primaryServer'];
        $tsigKeyName = $feed['tsigKeyName'] ?? null;
        
        $config = "    type slave;\n";
        $config .= "    file \"/var/cache/bind/{$zoneName}\";\n";
        $config .= "    masters { {$primaryServer}";
        
        if ($tsigKeyName) {
            $config .= " key \"{$tsigKeyName}\"";
        }
        
        $config .= "; };\n";
        
        return $config;
    }
    
    /**
     * Add a feed to the response-policy statement
     * 
     * @param string $content Current config content
     * @param array $feed Feed configuration
     * @return string Updated config content
     */
    private function addToResponsePolicy(string $content, array $feed): string {
        $zoneName = $feed['feed'];
        $source = $feed['source'];
        
        // Determine default action based on source
        $action = $feed['action'] ?? ($source === 'ioc2rpz' ? 'given' : 'nxdomain');
        $action = strtolower($action);
        
        // Build the policy line
        // For CNAME action, format is: zone "name" policy cname target;
        // For other actions, format is: zone "name" policy action;
        if ($action === 'cname' && !empty($feed['cnameTarget'])) {
            $policyLine = "    zone \"{$zoneName}\" policy cname " . $feed['cnameTarget'];
        } else {
            $policyLine = "    zone \"{$zoneName}\" policy {$action}";
        }
        
        $policyLine .= ";";
        
        // Add description as comment if provided
        if (!empty($feed['description'])) {
            $policyLine .= " # " . $feed['description'];
        }
        
        $policyLine .= "\n";
        
        // Find response-policy block and add the new feed
        // Match response-policy block with optional semicolon after closing brace
        if (preg_match('/response-policy\s*\{([^}]*)\}\s*;?/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $rpBlock = $matches[1][0];
            $rpStart = $matches[0][1];
            $rpEnd = $rpStart + strlen($matches[0][0]);
            
            // Insert new feed at the end of the response-policy block (before closing brace)
            $newRpBlock = rtrim($rpBlock) . "\n" . $policyLine;
            $newContent = substr($content, 0, $rpStart) . 
                          "response-policy {\n" . $newRpBlock . "};" . 
                          substr($content, $rpEnd);
            
            return $newContent;
        }
        
        // If no response-policy block exists, create one
        $rpBlock = "\nresponse-policy {\n" . $policyLine . "};\n";
        
        // Insert before the first zone definition or at the end
        if (preg_match('/^zone\s+/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $insertPos = $matches[0][1];
            return substr($content, 0, $insertPos) . $rpBlock . "\n" . substr($content, $insertPos);
        }
        
        return $content . $rpBlock;
    }

    
    /**
     * Update an existing feed's configuration
     * 
     * Updates policy action in response-policy and zone configuration for third-party feeds.
     * Preserves feed order.
     * 
     * @param string $feedName The name of the feed to update
     * @param array $config Updated configuration:
     *   - action: string (optional) - New policy action
     *   - description: string (optional) - New description
     *   - cnameTarget: string (optional) - Required if action is 'cname'
     *   - primaryServer: string (optional) - For third-party feeds
     *   - tsigKeyName: string (optional) - For third-party feeds
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function updateFeed(string $feedName, array $config): array {
        if (empty($feedName)) {
            return ['success' => false, 'error' => 'Feed name is required'];
        }
        
        // Get existing feeds to verify feed exists and get its source type
        $existingFeeds = $this->getFeeds();
        $feedIndex = array_search($feedName, array_column($existingFeeds, 'feed'));
        
        if ($feedIndex === false) {
            return ['success' => false, 'error' => 'Feed not found: ' . $feedName];
        }
        
        $existingFeed = $existingFeeds[$feedIndex];
        $source = $existingFeed['source'];
        
        // Validate action if provided
        if (!empty($config['action'])) {
            $action = strtolower($config['action']);
            if (!in_array($action, self::$validActions)) {
                return ['success' => false, 'error' => 'Invalid policy action: ' . $config['action']];
            }
            
            // CNAME action requires target
            if ($action === 'cname' && empty($config['cnameTarget'])) {
                return ['success' => false, 'error' => 'CNAME action requires a target domain'];
            }
        }
        
        // For ioc2rpz feeds, only action can be changed
        if ($source === 'ioc2rpz') {
            $allowedFields = ['action', 'cnameTarget'];
            foreach (array_keys($config) as $key) {
                if (!in_array($key, $allowedFields) && !empty($config[$key])) {
                    return ['success' => false, 'error' => 'Only policy action can be modified for ioc2rpz feeds'];
                }
            }
        }
        
        // Create backup before making changes
        try {
            $backupPath = $this->backup();
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Backup failed: ' . $e->getMessage()];
        }
        
        // Read current configuration
        $content = file_get_contents($this->configPath);
        if ($content === false) {
            return ['success' => false, 'error' => 'Failed to read configuration file'];
        }
        
        try {
            // Update response-policy entry
            if (isset($config['action']) || isset($config['cnameTarget']) || isset($config['description'])) {
                $content = $this->updateResponsePolicyEntry($content, $feedName, $config, $existingFeed);
            }
            
            // Update zone configuration for third-party feeds
            if ($source === 'third-party' && (isset($config['primaryServer']) || isset($config['tsigKeyName']))) {
                $content = $this->updateZoneConfig($content, $feedName, $config);
            }
            
            // Write updated configuration
            if (file_put_contents($this->configPath, $content) === false) {
                throw new Exception('Failed to write configuration file');
            }
            
            // Validate the new configuration
            $validation = $this->validate();
            if (!$validation['success']) {
                // Rollback on validation failure
                $this->restore($backupPath);
                return [
                    'success' => false, 
                    'error' => 'Configuration validation failed: ' . $validation['error']
                ];
            }
            
            return ['success' => true, 'error' => null];
            
        } catch (Exception $e) {
            // Rollback on any error
            try {
                $this->restore($backupPath);
            } catch (Exception $restoreEx) {
                // Log restore failure but return original error
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update a feed's entry in the response-policy statement
     * 
     * @param string $content Current config content
     * @param string $feedName Feed name to update
     * @param array $config New configuration values
     * @param array $existingFeed Existing feed configuration
     * @return string Updated config content
     */
    private function updateResponsePolicyEntry(string $content, string $feedName, array $config, array $existingFeed): string {
        $action = $config['action'] ?? $existingFeed['action'];
        $action = strtolower($action);
        $cnameTarget = $config['cnameTarget'] ?? $existingFeed['cnameTarget'] ?? null;
        $description = $config['description'] ?? $existingFeed['desc'] ?? '';
        $enabled = $existingFeed['enabled'];
        
        // Build the new policy line
        // For CNAME action, format is: zone "name" policy cname target;
        // For other actions, format is: zone "name" policy action;
        $prefix = $enabled ? '    ' : '    // ';
        
        if ($action === 'cname' && $cnameTarget) {
            $newLine = $prefix . "zone \"{$feedName}\" policy cname " . $cnameTarget . ";";
        } else {
            $newLine = $prefix . "zone \"{$feedName}\" policy {$action};";
        }
        
        if (!empty($description)) {
            $newLine .= " # " . $description;
        }
        
        // Pattern to match the existing feed line (enabled or disabled)
        $escapedName = preg_quote($feedName, '/');
        $pattern = '/^(\s*)(\/\/\s*)?zone\s+["\']' . $escapedName . '["\']\s+policy\s+[^;]+;[^\n]*/m';
        
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $newLine, $content);
        }
        
        return $content;
    }
    
    /**
     * Update zone configuration for a feed
     * 
     * @param string $content Current config content
     * @param string $feedName Feed name to update
     * @param array $config New configuration values
     * @return string Updated config content
     */
    private function updateZoneConfig(string $content, string $feedName, array $config): string {
        $escapedName = preg_quote($feedName, '/');
        
        // Match the zone block for this feed
        $pattern = '/(zone\s+["\']' . $escapedName . '["\']\s*\{)([^}]+)(\})/is';
        
        if (!preg_match($pattern, $content, $matches)) {
            return $content;
        }
        
        $zoneBlock = $matches[2];
        
        // Update masters if primaryServer is provided
        if (isset($config['primaryServer'])) {
            $newMasters = $config['primaryServer'];
            $tsigKey = $config['tsigKeyName'] ?? null;
            
            $mastersLine = "    masters { {$newMasters}";
            if ($tsigKey) {
                $mastersLine .= " key \"{$tsigKey}\"";
            }
            $mastersLine .= "; }";
            
            // Replace existing masters line
            $zoneBlock = preg_replace('/\s*masters\s*\{[^}]+\}\s*;?/i', "\n" . $mastersLine . ";\n", $zoneBlock);
        }
        
        // Reconstruct the zone block
        $newZoneBlock = $matches[1] . $zoneBlock . $matches[3];
        $content = preg_replace($pattern, $newZoneBlock, $content);
        
        return $content;
    }

    
    /**
     * Remove a feed from the BIND configuration
     * 
     * Removes the feed from both the response-policy statement and zone configuration.
     * Optionally deletes the zone file for local feeds.
     * 
     * @param string $feedName The name of the feed to remove
     * @param bool $deleteZoneFile Whether to delete the zone file (for local feeds)
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function removeFeed(string $feedName, bool $deleteZoneFile = false): array {
        if (empty($feedName)) {
            return ['success' => false, 'error' => 'Feed name is required'];
        }
        
        // Get existing feeds to verify feed exists
        $existingFeeds = $this->getFeeds();
        $feedIndex = array_search($feedName, array_column($existingFeeds, 'feed'));
        
        if ($feedIndex === false) {
            return ['success' => false, 'error' => 'Feed not found: ' . $feedName];
        }
        
        $existingFeed = $existingFeeds[$feedIndex];
        
        // Create backup before making changes
        try {
            $backupPath = $this->backup();
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Backup failed: ' . $e->getMessage()];
        }
        
        // Read current configuration
        $content = file_get_contents($this->configPath);
        if ($content === false) {
            return ['success' => false, 'error' => 'Failed to read configuration file'];
        }
        
        try {
            // Remove from response-policy statement
            $content = $this->removeFromResponsePolicy($content, $feedName);
            
            // Remove zone configuration
            $content = $this->removeZoneConfig($content, $feedName);
            
            // Write updated configuration
            if (file_put_contents($this->configPath, $content) === false) {
                throw new Exception('Failed to write configuration file');
            }
            
            // Validate the new configuration
            $validation = $this->validate();
            if (!$validation['success']) {
                // Rollback on validation failure
                $this->restore($backupPath);
                return [
                    'success' => false, 
                    'error' => 'Configuration validation failed: ' . $validation['error']
                ];
            }
            
            // Delete zone file if requested and feed is local
            if ($deleteZoneFile && $existingFeed['source'] === 'local') {
                $zoneFilePath = "/var/cache/bind/{$feedName}";
                if (file_exists($zoneFilePath)) {
                    @unlink($zoneFilePath);
                }
            }
            
            return ['success' => true, 'error' => null];
            
        } catch (Exception $e) {
            // Rollback on any error
            try {
                $this->restore($backupPath);
            } catch (Exception $restoreEx) {
                // Log restore failure but return original error
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Remove a feed from the response-policy statement
     * 
     * @param string $content Current config content
     * @param string $feedName Feed name to remove
     * @return string Updated config content
     */
    private function removeFromResponsePolicy(string $content, string $feedName): string {
        $escapedName = preg_quote($feedName, '/');
        
        // Pattern to match the feed line in response-policy (enabled or disabled)
        // Also matches the preceding newline to avoid leaving blank lines
        $pattern = '/\n?\s*(\/\/\s*)?zone\s+["\']' . $escapedName . '["\']\s+policy\s+[^;]+;[^\n]*/i';
        
        $content = preg_replace($pattern, '', $content);
        
        return $content;
    }
    
    /**
     * Remove zone configuration for a feed
     * 
     * @param string $content Current config content
     * @param string $feedName Feed name to remove
     * @return string Updated config content
     */
    private function removeZoneConfig(string $content, string $feedName): string {
        $escapedName = preg_quote($feedName, '/');
        
        // Pattern to match the zone block including any preceding comment
        // Format: // Zone configuration for feedname\nzone "feedname" { ... };
        // Use a more robust pattern that handles nested braces by matching balanced braces
        $pattern = '/\n?\/\/\s*Zone configuration for\s+' . $escapedName . '\s*\n?zone\s+["\']' . $escapedName . '["\']\s*\{(?:[^{}]|\{[^{}]*\})*\};\s*/is';
        
        $content = preg_replace($pattern, '', $content);
        
        // Also try without the comment (for existing configs)
        // This pattern handles one level of nested braces (e.g., allow-update { localhost; })
        $pattern2 = '/\n?zone\s+["\']' . $escapedName . '["\']\s*\{(?:[^{}]|\{[^{}]*\})*\};\s*/is';
        $content = preg_replace($pattern2, '', $content);
        
        return $content;
    }

    
    /**
     * Enable or disable a feed
     * 
     * When disabled, the feed is commented out in the response-policy statement
     * but the zone configuration is preserved.
     * 
     * @param string $feedName The name of the feed to enable/disable
     * @param bool $enabled True to enable, false to disable
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function setFeedEnabled(string $feedName, bool $enabled): array {
        if (empty($feedName)) {
            return ['success' => false, 'error' => 'Feed name is required'];
        }
        
        // Get existing feeds to verify feed exists and get current state
        $existingFeeds = $this->getFeeds();
        $feedIndex = array_search($feedName, array_column($existingFeeds, 'feed'));
        
        if ($feedIndex === false) {
            return ['success' => false, 'error' => 'Feed not found: ' . $feedName];
        }
        
        $existingFeed = $existingFeeds[$feedIndex];
        
        // Check if already in desired state
        if ($existingFeed['enabled'] === $enabled) {
            return ['success' => true, 'error' => null];
        }
        
        // Create backup before making changes
        try {
            $backupPath = $this->backup();
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Backup failed: ' . $e->getMessage()];
        }
        
        // Read current configuration
        $content = file_get_contents($this->configPath);
        if ($content === false) {
            return ['success' => false, 'error' => 'Failed to read configuration file'];
        }
        
        try {
            // Update the feed's enabled state in response-policy
            $content = $this->toggleFeedInResponsePolicy($content, $feedName, $enabled, $existingFeed);
            
            // Write updated configuration
            if (file_put_contents($this->configPath, $content) === false) {
                throw new Exception('Failed to write configuration file');
            }
            
            // Validate the new configuration
            $validation = $this->validate();
            if (!$validation['success']) {
                // Rollback on validation failure
                $this->restore($backupPath);
                return [
                    'success' => false, 
                    'error' => 'Configuration validation failed: ' . $validation['error']
                ];
            }
            
            return ['success' => true, 'error' => null];
            
        } catch (Exception $e) {
            // Rollback on any error
            try {
                $this->restore($backupPath);
            } catch (Exception $restoreEx) {
                // Log restore failure but return original error
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Toggle a feed's enabled state in the response-policy statement
     * 
     * @param string $content Current config content
     * @param string $feedName Feed name to toggle
     * @param bool $enabled New enabled state
     * @param array $existingFeed Existing feed configuration
     * @return string Updated config content
     */
    private function toggleFeedInResponsePolicy(string $content, string $feedName, bool $enabled, array $existingFeed): string {
        $escapedName = preg_quote($feedName, '/');
        $action = $existingFeed['action'];
        $cnameTarget = $existingFeed['cnameTarget'] ?? null;
        $description = $existingFeed['desc'] ?? '';
        
        // Build the new policy line
        // For CNAME action, format is: zone "name" policy cname target;
        // For other actions, format is: zone "name" policy action;
        $prefix = $enabled ? '    ' : '    // ';
        
        if ($action === 'cname' && $cnameTarget) {
            $newLine = $prefix . "zone \"{$feedName}\" policy cname " . $cnameTarget . ";";
        } else {
            $newLine = $prefix . "zone \"{$feedName}\" policy {$action};";
        }
        
        if (!empty($description)) {
            $newLine .= " # " . $description;
        }
        
        // Pattern to match the existing feed line (enabled or disabled)
        $pattern = '/^(\s*)(\/\/\s*)?zone\s+["\']' . $escapedName . '["\']\s+policy\s+[^;]+;[^\n]*/m';
        
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $newLine, $content);
        }
        
        return $content;
    }

    
    /**
     * Update the order of feeds in the response-policy statement
     * 
     * Reorders feeds in the response-policy statement to match the provided order.
     * All feed configurations are preserved.
     * 
     * @param array $order Array of feed names in the desired order
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function updateFeedOrder(array $order): array {
        if (empty($order)) {
            return ['success' => false, 'error' => 'Feed order array is required'];
        }
        
        // Get existing feeds
        $existingFeeds = $this->getFeeds();
        $existingNames = array_column($existingFeeds, 'feed');
        
        // Validate that all feeds in order exist
        foreach ($order as $feedName) {
            if (!in_array($feedName, $existingNames)) {
                return ['success' => false, 'error' => 'Feed not found: ' . $feedName];
            }
        }
        
        // Validate that all existing feeds are in the order array
        foreach ($existingNames as $feedName) {
            if (!in_array($feedName, $order)) {
                return ['success' => false, 'error' => 'Missing feed in order: ' . $feedName];
            }
        }
        
        // Create backup before making changes
        try {
            $backupPath = $this->backup();
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Backup failed: ' . $e->getMessage()];
        }
        
        // Read current configuration
        $content = file_get_contents($this->configPath);
        if ($content === false) {
            return ['success' => false, 'error' => 'Failed to read configuration file'];
        }
        
        try {
            // Rebuild response-policy statement with new order
            $content = $this->rebuildResponsePolicy($content, $order, $existingFeeds);
            
            // Write updated configuration
            if (file_put_contents($this->configPath, $content) === false) {
                throw new Exception('Failed to write configuration file');
            }
            
            // Validate the new configuration
            $validation = $this->validate();
            if (!$validation['success']) {
                // Rollback on validation failure
                $this->restore($backupPath);
                return [
                    'success' => false, 
                    'error' => 'Configuration validation failed: ' . $validation['error']
                ];
            }
            
            return ['success' => true, 'error' => null];
            
        } catch (Exception $e) {
            // Rollback on any error
            try {
                $this->restore($backupPath);
            } catch (Exception $restoreEx) {
                // Log restore failure but return original error
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Rebuild the response-policy statement with feeds in the specified order
     * 
     * @param string $content Current config content
     * @param array $order Array of feed names in desired order
     * @param array $existingFeeds Array of existing feed configurations
     * @return string Updated config content
     */
    private function rebuildResponsePolicy(string $content, array $order, array $existingFeeds): string {
        // Create a map of feed name to feed config for quick lookup
        $feedMap = [];
        foreach ($existingFeeds as $feed) {
            $feedMap[$feed['feed']] = $feed;
        }
        
        // Build new response-policy content
        $newRpContent = "";
        
        foreach ($order as $feedName) {
            $feed = $feedMap[$feedName];
            $enabled = $feed['enabled'];
            $action = $feed['action'];
            $cnameTarget = $feed['cnameTarget'] ?? null;
            $description = $feed['desc'] ?? '';
            
            // Build the policy line
            $prefix = $enabled ? '    ' : '    // ';
            
            // Build the policy line
            // For CNAME action, format is: zone "name" policy cname target;
            // For other actions, format is: zone "name" policy action;
            if ($action === 'cname' && $cnameTarget) {
                $line = $prefix . "zone \"{$feedName}\" policy cname " . $cnameTarget . ";";
            } else {
                $line = $prefix . "zone \"{$feedName}\" policy {$action};";
            }
            
            if (!empty($description)) {
                $line .= " # " . $description;
            }
            
            $newRpContent .= $line . "\n";
        }
        
        // Replace the response-policy block content
        // Match response-policy block with optional semicolon after closing brace
        if (preg_match('/response-policy\s*\{([^}]*)\}\s*;?/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $rpStart = $matches[0][1];
            $rpEnd = $rpStart + strlen($matches[0][0]);
            
            $newRpBlock = "response-policy {\n" . $newRpContent . "};";
            $content = substr($content, 0, $rpStart) . $newRpBlock . substr($content, $rpEnd);
        }
        
        return $content;
    }
}
