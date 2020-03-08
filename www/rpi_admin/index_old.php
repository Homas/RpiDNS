<?php
	require_once "/opt/rpidns/www/rpidns_vars.php";

	$db = new SQLite3("/opt/rpidns/www/".DBFile);
	$db->busyTimeout(15000);
 
  $sql="select datetime(dt, 'unixepoch', 'localtime') as dtz ,client_ip,fqdn,type,class, options, server, action, '1' as cnt from queries_raw where dt>=strftime('%s', 'now')-1800 order by dt desc;";
	$results = $db->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
	</head>
	<body>
		<h1>Requests</h1>
		<table>			
<?php
	while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
			echo "<tr><td>${row['dtz']}</td><td>${row['client_ip']}</td><td>${row['fqdn']}</td><td>${row['type']}</td><td>${row['class']}</td><td>${row['options']}</td><td>${row['server']}</td><td>${row['action']}</td><td>${row['cnt']}</td></tr>\n";
	}
?>
		</table>		
		<h1>Hits</h1>
		<table>			
<?php
  $sql="select datetime(dt, 'unixepoch', 'localtime') as dtz ,client_ip,fqdn, action, rule_type, rule, feed, '1' as cnt from hits_raw where dt>=strftime('%s', 'now')-1800 order by dt desc";
	$results = $db->query($sql);
	while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
			echo "<tr><td>${row['dtz']}</td><td>${row['client_ip']}</td><td>${row['fqdn']}</td><td>${row['action']}</td><td>${row['rule_type']}</td><td>${row['rule']}</td><td>${row['feed']}</td><td>${row['cnt']}</td></tr>\n";
	}
?>
		</table>		
	</body>
<?php
  #close DB
  $db->close();

?>