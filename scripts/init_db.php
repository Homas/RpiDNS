<?php
#(c) Vadim Pavlov 2020
#ioc2rpz DB init

#chmod 664 /srv/www/io2cfg/io2db.sqlite
#sudo chown homas:_apache /srv/www/io2cfg/io2db.sqlite

require_once "/opt/rpidns/www/rpidns_vars.php";

function initSQLiteDB($DBF){
  $db = new SQLite3($DBF);
						
  $sql="".
//			 "drop table if exists queries_raw;\ndrop table if exists queries_5m;\ndrop table if exists queries_1h;\ndrop table if exists queries_1d; drop table if exists hits_raw;\n drop table if exists hits_5m;\n drop table if exists hits_1h;\n drop table if exists hits_1d;\n".
		   "create table if not exists queries_raw (dt integer, client_ip text, client_port text, mac text, fqdn text, type text, class text, options text, server text, action text);\n".
			 "create index if not exists queries_raw_dt on queries_raw(dt);\n".
			 "create index if not exists queries_raw_client_ip on queries_raw(client_ip);\n".
			 "create index if not exists queries_raw_fqdn on queries_raw(fqdn);\n".
			 "create index if not exists queries_raw_server on queries_raw(server);\n".

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

		   "create table if not exists hits_1d (dt integer, client_ip text, mac text, fqdn text, action text, rule_type text, rule text, feed text, cnt integer);\n".
			 "create index if not exists hits_1d_dt on hits_1d(dt);\n".
			 "create index if not exists hits_1d_client_ip on hits_1d(client_ip);\n".
			 "create index if not exists hits_1d_fqdn on hits_1d(fqdn);\n".
			 "create index if not exists hits_1d_feed on hits_1d(feed);\n".

//hosts db
//blacklist and whitelist
			 
			 "";
  $db->exec($sql);

  #close DB
  $db->close();
};


initSQLiteDB("/opt/rpidns/www/".DBFile);

?>