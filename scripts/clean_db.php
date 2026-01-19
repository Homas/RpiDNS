<?php
#(c) Vadim Pavlov 2020 - 2026
#ioc2rpz DB init
require_once "/opt/rpidns/www/rpidns_vars.php";

function cleanDB($DBF){
	require_once "/opt/rpidns/www/rpisettings.php";
  $db = DB_open($DBF);
						
  $sql="
		delete from queries_raw where dt<strftime('%s', 'now')-${retention['queries_raw']}*86400;
		delete from queries_5m where dt<strftime('%s', 'now')-${retention['queries_5m']}*86400;
		delete from queries_1h where dt<strftime('%s', 'now')-${retention['queries_1h']}*86400;
		delete from queries_1d where dt<strftime('%s', 'now')-${retention['queries_1d']}*86400;
		delete from hits_raw where dt<strftime('%s', 'now')-${retention['hits_raw']}*86400;
		delete from hits_5m where dt<strftime('%s', 'now')-${retention['hits_5m']}*86400;
		delete from hits_1h where dt<strftime('%s', 'now')-${retention['hits_1h']}*86400;
		delete from hits_1d where dt<strftime('%s', 'now')-${retention['hits_1d']}*86400;
	";
	DB_execute($db,$sql);

  DB_close($db);
};


cleanDB("/opt/rpidns/www/db/".DBFile);

?>