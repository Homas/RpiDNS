<?php
#(c) Vadim Pavlov 2020
#ioc2rpz DB init
require_once "/opt/rpidns/www/rpidns_vars.php";

function initSQLiteDB($DBF){
  $db = new SQLite3($DBF);
	$sql="PRAGMA user_version=".DBVersion.";\n";
  $sql.="".
//			 "drop table if exists queries_raw;\ndrop table if exists queries_5m;\ndrop table if exists queries_1h;\ndrop table if exists queries_1d; drop table if exists hits_raw;\n drop table if exists hits_5m;\n drop table if exists hits_1h;\n drop table if exists hits_1d;\n".
		   "create table if not exists queries_raw (dt integer, client_ip text, client_port text, mac text, fqdn text, type text, class text, options text, server text, action text);\n".
			 "create index if not exists queries_raw_dt on queries_raw(dt);\n".
			 "create index if not exists queries_raw_client_ip on queries_raw(client_ip);\n".
			 "create index if not exists queries_raw_fqdn on queries_raw(fqdn);\n".
			 "create index if not exists queries_raw_server on queries_raw(server);\n".
			 "create index if not exists queries_raw_action on queries_raw(action);\n".

		   "create table if not exists hits_raw (dt integer, client_ip text, client_port text, mac text, fqdn text, action text, rule_type text, rule text, feed text);\n".
			 "create index if not exists hits_raw_dt on hits_raw(dt);\n".
			 "create index if not exists hits_raw_client_ip on queries_raw(client_ip);\n".
			 "create index if not exists hits_raw_fqdn on hits_raw(fqdn);\n".
			 "create index if not exists hits_raw_feed on hits_raw(feed);\n".

		   "create table if not exists queries_5m (dt integer, client_ip text, mac text, fqdn text, type text, class text, options text, server text, action text, cnt integer);\n".
			 "create index if not exists queries_5m_dt on queries_5m(dt);\n".
			 "create index if not exists queries_5m_client_ip on queries_5m(client_ip);\n".
			 "create index if not exists queries_5m_fqdn on queries_5m(fqdn);\n".
			 "create index if not exists queries_5m_server on queries_5m(server);\n".
			 "create index if not exists queries_5m_action on queries_5m(action);\n".

		   "create table if not exists hits_5m (dt integer, client_ip text, mac text, fqdn text, action text, rule_type text, rule text, feed text, cnt integer);\n".
			 "create index if not exists hits_5m_dt on hits_5m(dt);\n".
			 "create index if not exists hits_5m_client_ip on hits_5m(client_ip);\n".
			 "create index if not exists hits_5m_fqdn on hits_5m(fqdn);\n".
			 "create index if not exists hits_5m_feed on hits_5m(feed);\n".


		   "create table if not exists queries_1h (dt integer, client_ip text, mac text, fqdn text, type text, class text, options text, server text, action text, cnt integer);\n".
			 "create index if not exists queries_1h_dt on queries_1h(dt);\n".
			 "create index if not exists queries_1h_client_ip on queries_1h(client_ip);\n".
			 "create index if not exists queries_1h_fqdn on queries_1h(fqdn);\n".
			 "create index if not exists queries_1h_server on queries_1h(server);\n".
			 "create index if not exists queries_1h_action on queries_1h(action);\n".

		   "create table if not exists hits_1h (dt integer, client_ip text, mac text, fqdn text, action text, rule_type text, rule text, feed text, cnt integer);\n".
			 "create index if not exists hits_1h_dt on hits_1h(dt);\n".
			 "create index if not exists hits_1h_client_ip on hits_1h(client_ip);\n".
			 "create index if not exists hits_1h_fqdn on hits_1h(fqdn);\n".
			 "create index if not exists hits_1h_feed on hits_1h(feed);\n".


		   "create table if not exists queries_1d (dt integer, client_ip text, mac text, fqdn text, type text, class text, options text, server text, action text, cnt integer);\n".
			 "create index if not exists queries_1d_dt on queries_1d(dt);\n".
			 "create index if not exists queries_1d_client_ip on queries_1d(client_ip);\n".
			 "create index if not exists queries_1d_fqdn on queries_1d(fqdn);\n".
			 "create index if not exists queries_1d_server on queries_1d(server);\n".
			 "create index if not exists queries_1d_action on queries_1d(action);\n".

		   "create table if not exists hits_1d (dt integer, client_ip text, mac text, fqdn text, action text, rule_type text, rule text, feed text, cnt integer);\n".
			 "create index if not exists hits_1d_dt on hits_1d(dt);\n".
			 "create index if not exists hits_1d_client_ip on hits_1d(client_ip);\n".
			 "create index if not exists hits_1d_fqdn on hits_1d(fqdn);\n".
			 "create index if not exists hits_1d_feed on hits_1d(feed);\n".

		   "create table if not exists assets (name text, address text, vendor text, comment text, added_dt integer,unique(address));\n".
			 "create index if not exists assets_name on assets(name);\n".
			 "create index if not exists assets_address on assets(address);\n".

		   "create table if not exists localzone (ioc text, type text, ltype text, comment text, active boolean, subdomains boolean, added_dt integer, provisioned text, unique(ioc));\n".
			 "create index if not exists assets_itype on localzone(ltype);\n".

       // Authentication tables (v2)
       "create table if not exists users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, is_admin INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL);\n".
       "create index if not exists idx_users_username on users(username);\n".

       "create table if not exists sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, token TEXT NOT NULL UNIQUE, created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL, ip_address TEXT, user_agent TEXT, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);\n".
       "create index if not exists idx_sessions_token on sessions(token);\n".
       "create index if not exists idx_sessions_user_id on sessions(user_id);\n".
       "create index if not exists idx_sessions_expires on sessions(expires_at);\n".

       "create table if not exists login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, ip_address TEXT NOT NULL, attempted_at INTEGER NOT NULL, success INTEGER NOT NULL DEFAULT 0);\n".
       "create index if not exists idx_login_attempts_ip on login_attempts(ip_address);\n".
       "create index if not exists idx_login_attempts_time on login_attempts(attempted_at);\n".

       "create table if not exists schema_version (version INTEGER NOT NULL, applied_at INTEGER NOT NULL);\n".

//blacklist and whitelist

			 "";
  $db->exec($sql);

  // Insert schema version record
  $db->exec("INSERT INTO schema_version (version, applied_at) VALUES (".DBVersion.", ".time().")");

  // Create default admin user
  createDefaultAdminUser($db);

  #close DB
  $db->close();
};

function createDefaultAdminUser($db) {
  $username = 'admin';
  $password = bin2hex(random_bytes(8)); // 16 character random password
  $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
  $now = time();
  
  // Check if admin user already exists
  $result = $db->querySingle("SELECT id FROM users WHERE username = '$username'");
  if ($result) {
    error_log("[init_db] Default admin user already exists");
    return;
  }
  
  $stmt = $db->prepare("
    INSERT INTO users (username, password_hash, is_admin, created_at, updated_at)
    VALUES (:username, :password_hash, 1, :created_at, :updated_at)
  ");
  $stmt->bindValue(':username', $username, SQLITE3_TEXT);
  $stmt->bindValue(':password_hash', $passwordHash, SQLITE3_TEXT);
  $stmt->bindValue(':created_at', $now, SQLITE3_INTEGER);
  $stmt->bindValue(':updated_at', $now, SQLITE3_INTEGER);
  
  if ($stmt->execute()) {
    error_log("[init_db] Created default admin user. Username: admin, Password: $password");
    error_log("[init_db] *** IMPORTANT: Please change the default password immediately! ***");
    
    // Write credentials to a file for the user to find
    $credFile = "/opt/rpidns/conf/default_credentials.txt";
    @file_put_contents($credFile, "RpiDNS Default Admin Credentials\n" .
      "================================\n" .
      "Username: admin\n" .
      "Password: $password\n\n" .
      "IMPORTANT: Please change this password immediately after first login!\n" .
      "This file will be deleted after you change your password.\n" .
      "Created: " . date('Y-m-d H:i:s') . "\n");
    @chmod($credFile, 0600);
    
    echo "Default admin user created.\n";
    echo "Username: admin\n";
    echo "Password: $password\n";
    echo "*** IMPORTANT: Please change this password immediately! ***\n";
  } else {
    error_log("[init_db] Failed to create default admin user: " . $db->lastErrorMsg());
  }
}

initSQLiteDB("/opt/rpidns/www/db/".DBFile);
