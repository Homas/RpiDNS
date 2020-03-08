<?php
	require_once "/opt/rpidns/www/rpidns_vars.php";

	$REQUEST=getRequest();
	if (!empty($REQUEST['rowid'])) $ReqRowId=ctype_digit($REQUEST['rowid'])?$REQUEST['rowid']:implode(",",array_filter(json_decode($REQUEST['rowid'],true),'is_numeric'));

	$db = new SQLite3("/opt/rpidns/www/".DBFile);
	$db->busyTimeout(15000);

	
switch ($REQUEST['method'].' '.$REQUEST["req"]):
    case "GET queries_raw":
			$sql="select strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip,fqdn,type,class, options, server, action, '1' as cnt from queries_raw where dt>=strftime('%s', 'now')-1800 order by dt desc;";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
      break;
    case "GET hits_raw":
			$sql="select strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip,fqdn, action, rule_type, rule, feed, '1' as cnt from hits_raw where dt>=strftime('%s', 'now')-1800 order by dt desc";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
      break;
    default:
      $response='{"status":"failed", "reason":"not supported '.$REQUEST['method'].' '.$REQUEST["req"].'"}';
endswitch;

	
  #close DB
  $db->close();
	echo $response;

//phpinfo();	
	
?>