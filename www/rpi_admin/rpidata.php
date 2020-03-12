<?php
	require_once "/opt/rpidns/www/rpidns_vars.php";

	$REQUEST=getRequest();
	if (!empty($REQUEST['rowid'])) $ReqRowId=ctype_digit($REQUEST['rowid'])?$REQUEST['rowid']:implode(",",array_filter(json_decode($REQUEST['rowid'],true),'is_numeric'));

	$db = new SQLite3("/opt/rpidns/www/".DBFile);
	$db->busyTimeout(15000);

	//sortBy, sortDesc, period, cp, pp, filter
	
	if (array_key_exists("sortDesc",$REQUEST)) $sort=$REQUEST["sortDesc"]=='true'?'desc':''; else $sort='';
	if (array_key_exists("sortBy",$REQUEST)) $sortBy=(in_array($REQUEST["sortBy"], array('dtz', 'client_ip', 'mac', 'fqdn', 'action', 'rule_type', 'rule', 'feed', 'cnt','type', 'class', 'options', 'server'),true))?$REQUEST["sortBy"]:'dtz'; else $sortBy='dtz';
	if (array_key_exists("pp",$REQUEST)) $perPage=(intval($REQUEST["pp"])>1 and intval($REQUEST["pp"])<=500)?$REQUEST["pp"]:100; else $perPage=0;
	if (array_key_exists("cp",$REQUEST)) $currentPage=intval($REQUEST["cp"]); else $currentPage=0;
	
	if (array_key_exists("filter",$REQUEST)) $filter_queries=$REQUEST["filter"]!=''?' and (client_ip like "%'.($db->escapeString($REQUEST["filter"])).'%" or mac like "%'.($db->escapeString($REQUEST["filter"])).'%"  or fqdn like "%'.($db->escapeString($REQUEST["filter"])).'%" or type like "%'.($db->escapeString($REQUEST["filter"])).'%" or class like "%'.($db->escapeString($REQUEST["filter"])).'%" or action like "%'.($db->escapeString($REQUEST["filter"])).'%")':''; else $filter_queries=''; //not really safe but should be Ok for home usage

	if (array_key_exists("filter",$REQUEST)) $filter_hits=$REQUEST["filter"]!=''?' and (client_ip like "%'.($db->escapeString($REQUEST["filter"])).'%" or mac like "%'.($db->escapeString($REQUEST["filter"])).'%"  or fqdn like "%'.($db->escapeString($REQUEST["filter"])).'%" or action like "%'.($db->escapeString($REQUEST["filter"])).'%" or rule like "%'.($db->escapeString($REQUEST["filter"])).'%" )':'';  else $filter_hits=''; //not really safe but should be Ok for home usage
	
	$order="order by $sortBy $sort LIMIT $perPage OFFSET ".($perPage*($currentPage-1));
	
	switch ($REQUEST["period"]):
		case "30m":
			$table="_raw";$period=1800;
			$qpX='qpm';$hpX='hpm';$div=60;
			$sql_hits="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, '1' as cnt from hits_raw where dt>=strftime('%s', 'now')-$period $filter_hits $order;";
			$sql_hits_count="select count(rowid) as cnt from hits_raw where dt>=strftime('%s', 'now')-$period $filter_hits;";

			$sql_queries="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, '1' as cnt from queries_raw where dt>=strftime('%s', 'now')-$period $filter_queries $order;";
			$sql_queries_count="select count(rowid) as cnt from queries_raw where dt>=strftime('%s', 'now')-$period $filter_queries;";
			break;
		case "1h":
			$table="_5m";$period=3600;
			$qps_pref='';$qps_post='';
			//for good pagination we need to add timestamp from the page 1

			$sql_hits="
			select * from (
			select 0 as rowid, 'raw' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(rowid) as cnt from hits_raw where dt>ifnull((select max(dt) from hits$table),0) $filter_hits group by rowid, tbl, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select rowid, '5m' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, cnt from hits$table where dt>=strftime('%s', 'now')-$period $filter_hits
			)
			$order;
			";
			$sql_hits_count="
			select count(*) as cnt from (
			select max(rowid) as rowid from hits_raw where dt>ifnull((select max(dt) from hits$table),0) $filter_hits group by rowid, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select rowid as cnt from hits$table where dt>=strftime('%s', 'now')-$period $filter_hits
			)";

			$sql_queries="
			select * from (
			select rowid, 'raw' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(rowid) as cnt from queries_raw where dt>ifnull((select max(dt) from queries$table),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select rowid, '5m' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt from queries$table where dt>=strftime('%s', 'now')-$period $filter_queries) $order;";

			$sql_queries_count="
			select count (*) as cnt from (
			select max(rowid) from queries_raw where dt>ifnull((select max(dt) from queries$table),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select rowid as cnt from queries$table where dt>=strftime('%s', 'now')-$period $filter_queries
			)";

			break;
		case "1d":
			$table="_1h";$period=86400;
			$qps_pref='select (dtz - dtz % 1800) as dtx, max(cnt) as cntx from (';$qps_post=') group by dtx';

//			$sql_hits="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, cnt from hits_1h where dt>=strftime('%s', 'now')-$period $filter_hits $order;";
//			$sql_hits_count="select count(rowid) as cnt from hits_1h where dt>=strftime('%s', 'now')-$period $filter_hits;";

//			$sql_queries="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt from queries_1h where dt>=strftime('%s', 'now')-$period $filter_queries $order;";
//			$sql_queries_count="select count(rowid) as cnt from queries_1h where dt>=strftime('%s', 'now')-$period $filter_queries;";
			$sql_hits="
			select * from (
			select 0 as rowid, 'raw' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(rowid) as cnt from hits_raw where dt>ifnull((select max(dt) from hits_5m),0) $filter_hits group by rowid, tbl, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select 0 as rowid, '5m' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(rowid) as cnt from hits_5m where dt>ifnull((select max(dt) from hits_1h),0) $filter_hits group by rowid, tbl, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select rowid, '1h' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, cnt from hits$table where dt>=strftime('%s', 'now')-$period $filter_hits
			)
			$order;
			";
			$sql_hits_count="
			select count(*) as cnt from (
			select max(rowid) as rowid from hits_raw where dt>ifnull((select max(dt) from hits_5m),0) $filter_hits group by rowid, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select max(rowid) as rowid from hits_5m where dt>ifnull((select max(dt) from hits_1h),0) $filter_hits group by rowid, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select rowid as cnt from hits$table where dt>=strftime('%s', 'now')-$period $filter_hits
			)";

			$sql_queries="
			select * from (
			select rowid, 'raw' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(rowid) as cnt from queries_raw where dt>ifnull((select max(dt) from queries_5m),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select rowid, '5m' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(rowid) as cnt from queries_5m where dt>ifnull((select max(dt) from queries_1h),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select rowid, '1h' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt from queries$table where dt>=strftime('%s', 'now')-$period $filter_queries) $order;";

			$sql_queries_count="
			select count (*) as cnt from (
			select max(rowid) from queries_raw where dt>ifnull((select max(dt) from queries_5m),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select max(rowid) from queries_5m where dt>ifnull((select max(dt) from queries_1h),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select rowid as cnt from queries$table where dt>=strftime('%s', 'now')-$period $filter_queries
			)";
			break;
		case "1w":
			$table="_1d";$period=86400*7;
			$qps_pref='select (dtz - dtz % 21600) as dtx, max(cnt) as cntx from (';$qps_post=') group by dtx';

//			$sql_hits="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, cnt from hits_1d where dt>=strftime('%s', 'now')-$period $filter_hits $order;";
//			$sql_hits_count="select count(rowid) as cnt from hits_1d where dt>=strftime('%s', 'now')-$period $filter_hits;";

//			$sql_queries="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt from queries_1d where dt>=strftime('%s', 'now')-$period $filter_queries $order;";
//			$sql_queries_count="select count(rowid) as cnt from queries_1d where dt>=strftime('%s', 'now')-$period $filter_queries;";
			$sql_hits="
			select * from (
			select 0 as rowid, 'raw' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(rowid) as cnt from hits_raw where dt>ifnull((select max(dt) from hits_5m),0) $filter_hits group by rowid, tbl, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select 0 as rowid, '5m' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(rowid) as cnt from hits_5m where dt>ifnull((select max(dt) from hits_1h),0) $filter_hits group by rowid, tbl, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select 0 as rowid, '1h' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(rowid) as cnt from hits_1h where dt>ifnull((select max(dt) from hits_1d),0) $filter_hits group by rowid, tbl, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select rowid, '1d' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, cnt from hits$table where dt>=strftime('%s', 'now')-$period $filter_hits
			)
			$order;
			";
			$sql_hits_count="
			select count(*) as cnt from (
			select max(rowid) as rowid from hits_raw where dt>ifnull((select max(dt) from hits_5m),0) $filter_hits group by rowid, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select max(rowid) as rowid from hits_5m where dt>ifnull((select max(dt) from hits_1h),0) $filter_hits group by rowid, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select max(rowid) as rowid from hits_1h where dt>ifnull((select max(dt) from hits_1d),0) $filter_hits group by rowid, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select rowid as cnt from hits$table where dt>=strftime('%s', 'now')-$period $filter_hits
			)";

			$sql_queries="
			select * from (
			select rowid, 'raw' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(rowid) as cnt from queries_raw where dt>ifnull((select max(dt) from queries_5m),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select rowid, '5m' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(rowid) as cnt from queries_5m where dt>ifnull((select max(dt) from queries_1h),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select rowid, '1h' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(rowid) as cnt from queries_1h where dt>ifnull((select max(dt) from queries_1d),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select rowid, '1d' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt from queries$table where dt>=strftime('%s', 'now')-$period $filter_queries) $order;";

			$sql_queries_count="
			select count (*) as cnt from (
			select max(rowid) from queries_raw where dt>ifnull((select max(dt) from queries_5m),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select max(rowid) from queries_5m where dt>ifnull((select max(dt) from queries_1h),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select max(rowid) from queries_1h where dt>ifnull((select max(dt) from queries_1d),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select rowid as cnt from queries$table where dt>=strftime('%s', 'now')-$period $filter_queries
			)";
		break;
		case "30d":
			$table="_1d";$period=86400*30;
			$qps_pref='select (dtz - dtz % 86400) as dtx,max(cnt) as cntx from (';$qps_post=') group by dtx';

//			$sql_hits="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, cnt from hits_1d where dt>=strftime('%s', 'now')-$period $filter_hits $order;";
//			$sql_hits_count="select count(rowid) as cnt from hits_1d where dt>=strftime('%s', 'now')-$period $filter_hits;";

//			$sql_queries="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt from queries_1d where dt>=strftime('%s', 'now')-$period $filter_queries $order;";
//			$sql_queries_count="select count(rowid) as cnt from queries_1d where dt>=strftime('%s', 'now')-$period $filter_queries;";
			$sql_hits="
			select * from (
			select 0 as rowid, 'raw' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(rowid) as cnt from hits_raw where dt>ifnull((select max(dt) from hits_5m),0) $filter_hits group by rowid, tbl, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select 0 as rowid, '5m' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(rowid) as cnt from hits_5m where dt>ifnull((select max(dt) from hits_1h),0) $filter_hits group by rowid, tbl, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select 0 as rowid, '1h' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(rowid) as cnt from hits_1h where dt>ifnull((select max(dt) from hits_1d),0) $filter_hits group by rowid, tbl, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select rowid, '1d' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, cnt from hits$table where dt>=strftime('%s', 'now')-$period $filter_hits
			)
			$order;
			";
			$sql_hits_count="
			select count(*) as cnt from (
			select max(rowid) as rowid from hits_raw where dt>ifnull((select max(dt) from hits_5m),0) $filter_hits group by rowid, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select max(rowid) as rowid from hits_5m where dt>ifnull((select max(dt) from hits_1h),0) $filter_hits group by rowid, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select max(rowid) as rowid from hits_1h where dt>ifnull((select max(dt) from hits_1d),0) $filter_hits group by rowid, client_ip, mac,fqdn, action, rule_type, rule, feed
			union
			select rowid as cnt from hits$table where dt>=strftime('%s', 'now')-$period $filter_hits
			)";

			$sql_queries="
			select * from (
			select rowid, 'raw' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(rowid) as cnt from queries_raw where dt>ifnull((select max(dt) from queries_5m),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select rowid, '5m' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(rowid) as cnt from queries_5m where dt>ifnull((select max(dt) from queries_1h),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select rowid, '1h' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(rowid) as cnt from queries_1h where dt>ifnull((select max(dt) from queries_1d),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select rowid, '1d' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt from queries$table where dt>=strftime('%s', 'now')-$period $filter_queries) $order;";

			$sql_queries_count="
			select count (*) as cnt from (
			select max(rowid) from queries_raw where dt>ifnull((select max(dt) from queries_5m),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select max(rowid) from queries_5m where dt>ifnull((select max(dt) from queries_1h),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select max(rowid) from queries_1h where dt>ifnull((select max(dt) from queries_1d),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action
			union
			select rowid as cnt from queries$table where dt>=strftime('%s', 'now')-$period $filter_queries
			)";
			break;
		default:
			$table="_raw";$period=1800;
			$qps_pref='';$qps_post='';

			$sql_hits="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, cnt from hits_raw where dt>=strftime('%s', 'now')-$period $filter_hits $order;";
			$sql_hits_count="select count(rowid) as cnt from hits_raw where dt>=strftime('%s', 'now')-$period $filter_hits;";

			$sql_queries="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt from queries_raw where dt>=strftime('%s', 'now')-$period $filter_queries $order;";
			$sql_queries_count="select count(rowid) as cnt from queries_raw where dt>=strftime('%s', 'now')-$period $filter_queries;";
	endswitch;

	switch ($REQUEST['method'].' '.$REQUEST["req"]):
    case "GET queries_raw":
//			$sql="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, ".($table=="_raw"?"'1' as":"")." cnt from queries$table where dt>=strftime('%s', 'now')-$period order by dt desc;";
			$response='{"status":"ok", "records":"'.(DB_fetchRecord($db,$sql_queries_count)['cnt']).'","data":'.json_encode(DB_selectArray($db,$sql_queries)).'}';
      break;
    case "GET hits_raw":
//			$sql="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, ".($table=="_raw"?"'1' as":"")." cnt from hits$table where dt>=strftime('%s', 'now')-$period order by dt desc";
			$response='{"status":"ok", "records":"'.(DB_fetchRecord($db,$sql_hits_count)['cnt']).'","data":'.json_encode(DB_selectArray($db,$sql_hits)).'}';
      break;
		case "GET dash_topX_req":
			$sql="select fqdn as name, count(rowid) as cnt from queries_raw where dt>=strftime('%s', 'now')-$period and action='allowed' group by name order by cnt desc limit 100";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET dash_topX_req_type":
			$sql="select type as name, count(rowid) as cnt from queries_raw where dt>=strftime('%s', 'now')-$period and action='allowed' group by name order by cnt desc limit 100";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET dash_topX_client":
			$sql="select client_ip as name, count(rowid) as cnt from queries_raw where dt>=strftime('%s', 'now')-$period and action='allowed' group by name order by cnt desc limit 100";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET dash_topX_breq":
			$sql="select fqdn as name, count(rowid) as cnt from hits_raw where dt>=strftime('%s', 'now')-$period group by name order by cnt desc limit 100";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET dash_topX_bclient":
			$sql="select client_ip as name, count(rowid) as cnt from hits_raw where dt>=strftime('%s', 'now')-$period group by name order by cnt desc limit 100";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET dash_topX_feeds":
			$sql="select feed as name, count(rowid) as cnt from hits_raw where dt>=strftime('%s', 'now')-$period group by name order by cnt desc limit 100";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET qps_chart":
			$sql="$qps_pref select (dt - dt % 60) as dtz, count(rowid) as cnt from queries_raw where dt>=strftime('%s', 'now')-$period group by dtz $qps_post";
			$qps=array();
			foreach(DB_selectArrayNum($db,$sql) as $rec){
				$qps[]=[$rec[0]*1000,$rec[1]];
			};
			$sql="$qps_pref select (dt - dt % 60) as dtz, count(rowid) as cnt from hits_raw where dt>=strftime('%s', 'now')-$period group by dtz $qps_post";
			$hits=array();
			foreach(DB_selectArrayNum($db,$sql) as $rec){
				$hits[]=[$rec[0]*1000,$rec[1]];
			};
			$response='[{"name":"Queries","data":'.json_encode($qps).'},{"name":"Blocked","data":'.json_encode($hits).'}]';
			break;
    default:
      $response='{"status":"failed", "records":"0", "reason":"not supported '.$REQUEST['method'].' '.$REQUEST["req"].'"}';
	endswitch;

	
  #close DB
  $db->close();
	echo $response;

//phpinfo();	
	
?>