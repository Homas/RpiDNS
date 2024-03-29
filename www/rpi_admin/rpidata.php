<?php
	require_once "/opt/rpidns/www/rpidns_vars.php";
	require_once "/opt/rpidns/www/rpisettings.php";
	$join=$assets_by=="mac"?"mac":"client_ip";

	$REQUEST=getRequest();
	if (!empty($REQUEST['rowid'])) $ReqRowId=ctype_digit($REQUEST['rowid'])?$REQUEST['rowid']:implode(",",array_filter(json_decode($REQUEST['rowid'],true),'is_numeric'));

	$db = new SQLite3("/opt/rpidns/www/db/".DBFile);
	$db->busyTimeout(15000);

	//sortBy, sortDesc, period, cp, pp, filter
	 $default_sortBy=(array_key_exists("ltype",$REQUEST) and $REQUEST["ltype"] == 'stats' )?'cnt':'dtz';
	if (array_key_exists("sortDesc",$REQUEST)) $sort=$REQUEST["sortDesc"]=='true'?'desc':''; else $sort='';
	if (array_key_exists("sortBy",$REQUEST)) $sortBy=(in_array($REQUEST["sortBy"], array('dtz', 'client_ip', 'mac', 'fqdn', 'action', 'rule_type', 'rule', 'feed', 'cnt','type', 'class', 'options', 'server'),true))?(($REQUEST["sortBy"]=='dtz' and $default_sortBy=='cnt')?$default_sortBy:$REQUEST["sortBy"]):$default_sortBy; else $sortBy=$default_sortBy;
	if (array_key_exists("pp",$REQUEST)) $perPage=(intval($REQUEST["pp"])>1 and intval($REQUEST["pp"])<=500)?$REQUEST["pp"]:100; else $perPage=0;
	if (array_key_exists("cp",$REQUEST)) $currentPage=intval($REQUEST["cp"]); else $currentPage=0;

	if (array_key_exists("filter",$REQUEST)) {

			$filter=explode("=",$REQUEST["filter"],2);

			if (!array_key_exists(1,$filter)){
				$filter_queries=$REQUEST["filter"]!=''?' and (client_ip like "%'.($db->escapeString($REQUEST["filter"])).'%" or mac like "%'.($db->escapeString($REQUEST["filter"])).'%"  or fqdn like "%'.($db->escapeString($REQUEST["filter"])).'%" or type like "%'.($db->escapeString($REQUEST["filter"])).'%" or class like "%'.($db->escapeString($REQUEST["filter"])).'%" or action like "%'.($db->escapeString($REQUEST["filter"])).'%" or name like "%'.($db->escapeString($REQUEST["filter"])).'%" or vendor like "%'.($db->escapeString($REQUEST["filter"])).'%")':'';

				$filter_hits=$REQUEST["filter"]!=''?' and (client_ip like "%'.($db->escapeString($REQUEST["filter"])).'%" or mac like "%'.($db->escapeString($REQUEST["filter"])).'%"  or fqdn like "%'.($db->escapeString($REQUEST["filter"])).'%" or action like "%'.($db->escapeString($REQUEST["filter"])).'%" or rule like "%'.($db->escapeString($REQUEST["filter"])).'%" or name like "%'.($db->escapeString($REQUEST["filter"])).'%" or vendor like "%'.($db->escapeString($REQUEST["filter"])).'%" )':'';
			}else{
				$filter_queries=in_array($filter[0],$filter_fields_q)?" and ".($db->escapeString($filter[0])).' = "'.($db->escapeString($filter[1])).'" ':'';
				$filter_hits=in_array($filter[0],$filter_fields_h)?" and ".($db->escapeString($filter[0])).' = "'.($db->escapeString($filter[1])).'" ':'';
			};

		} else {
			$filter_queries='';
			$filter_hits='';
		}; //not really safe but should be Ok for home usage


	$order="order by $sortBy $sort LIMIT $perPage OFFSET ".($perPage*($currentPage-1));
	$qps_pref='';$qps_post='';

	$fields_h=(array_key_exists("fields",$REQUEST) and $REQUEST["req"]=='hits_raw')?($REQUEST["fields"]?", ":"").$REQUEST["fields"].(strpos($REQUEST["fields"],'cname')!==false?", client_ip, mac, vendor, comment ":"").(preg_match('/rule[^_]/',$REQUEST["fields"])==1?", feed ":""):"client_ip, mac, fqdn, action, rule_type, rule, feed, cname, vendor, comment";
	$fields_q=(array_key_exists("fields",$REQUEST) and $REQUEST["req"]=='queries_raw')?($REQUEST["fields"]?", ":"").$REQUEST["fields"].(strpos($REQUEST["fields"],'cname')!==false?", client_ip, mac, vendor, comment ":""):"client_ip, mac, fqdn, type, class, options, server, action, cname, vendor, comment";

	if (array_key_exists("period",$REQUEST))  switch ($REQUEST["period"]):
		case "30m":
		case "1h":
			if ($REQUEST["period"] == "30m"){
				$table="_raw";$period=1800;
			}else{
				$table="_5m";$period=3600;
				$qps_pref='';$qps_post='';
			};
			if (array_key_exists("ltype",$REQUEST) and $REQUEST["ltype"] == 'stats' ){
				$sql_hits="select 'st' as tbl, rowid $fields_h, sum(cnt) as cnt from (select row_number() over (order by client_ip) as rowid, client_ip, mac, fqdn, action, rule_type, rule, feed, count(*) as cnt, ifnull(a.name,client_ip) as cname, vendor, comment from hits_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_hits group by client_ip, mac, fqdn, action, rule_type, rule, feed, cname, vendor, comment) group by tbl $fields_h";
				$sql_hits_count="select count(*) as cnt from ($sql_hits)";
				$sql_hits.=" $order;";

				$sql_queries="select 'st' as tbl,  rowid $fields_q, sum(cnt) as cnt from (select row_number() over (order by client_ip) as rowid, client_ip, mac, fqdn, type, class, options, server, action, ifnull(a.name,client_ip) as cname, vendor, comment, count(*) as cnt from queries_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, cname, vendor, comment) group by tbl $fields_q";
				$sql_queries_count="select count(*) as cnt from ($sql_queries)";
				$sql_queries.=" $order;";

			}else{
				$sql_hits="select qr.rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, '1' as cnt, ifnull(a.name,client_ip) as cname, vendor, comment from hits_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_hits";
				$sql_hits_count="select count(*) as cnt from ($sql_hits)";
				$sql_hits.=" $order;";

				$sql_queries="select qr.rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, '1' as cnt, ifnull(a.name,client_ip) as cname, vendor, comment from queries_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_queries";
				$sql_queries_count="select count(*) as cnt from ($sql_queries)";
				$sql_queries.=" $order;";
			};
			break;
		case "1d":
			$table="_1h";$period=86400;
			$qps_pref='select (dtz - dtz % 1800) as dtx, max(cnt) as cntx from (';$qps_post=') group by dtx';

			if (array_key_exists("ltype",$REQUEST) and $REQUEST["ltype"] == 'stats' ){

				$sql_hits="
				select  'st' as tbl, row_number() over (order by client_ip) as rowid $fields_h, sum(cnt) as cnt from (
				select client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, count(qr.rowid) as cnt from hits_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_5m),0) $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, cname
				union
				select client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from hits_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_1h),0) $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, cname
				union
				select client_ip, mac, fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from hits_1h qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, cname
				) group by tbl $fields_h";

				$sql_hits_count="select count(*) as cnt from ($sql_hits)";
				$sql_hits.=" $order;";

				$sql_queries="
				select 'st' as tbl, row_number() over (order by client_ip) as rowid $fields_q, sum(cnt) as cnt from (
				select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, count(*) as cnt from queries_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_5m),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
				union
				select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from queries_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_1h),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
				union
				select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment,ifnull(name,client_ip) as cname, sum(cnt) as cnt from queries_1h qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
				) group by tbl $fields_q";

				$sql_queries_count="select count(*) as cnt from ($sql_queries)";
				$sql_queries.=" $order;";

			}else{

				$sql_hits="
				select *, ifnull(name,client_ip) as cname from (
				select max(qr.rowid)  as rowid, 'raw' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(qr.rowid) as cnt, name, vendor, comment from hits_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_5m),0) $filter_hits group by tbl, client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
				union
				select max(qr.rowid)  as rowid, '5m' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, sum(cnt) as cnt, name, vendor, comment from hits_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_1h),0) $filter_hits group by tbl, client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
				union
				select qr.rowid, '1h' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, cnt, name, vendor, comment from hits_1h qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_hits
				)
				";
				$sql_hits_count="select count(*) as cnt from ($sql_hits)";
				$sql_hits.=" $order;";

				$sql_queries="
				select *, ifnull(name,client_ip) as cname from (
				select max(qr.rowid) as rowid, 'raw' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(qr.rowid) as cnt, name, vendor, comment from queries_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_5m),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment
				union
				select max(qr.rowid) as rowid, '5m' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, sum(cnt) as cnt, name, vendor, comment from queries_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_1h),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment
				union
				select qr.rowid, '1h' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt, name, vendor, comment from queries_1h qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_queries
				)";

				$sql_queries_count="select count(*) as cnt from ($sql_queries)";
				$sql_queries.=" $order;";
			};
			break;
		case "1w":
		case "30d":
			if ($REQUEST["period"] == "1w"){
				$table="_1d";$period=86400*7;
				$qps_pref='select (dtz - dtz % 21600) as dtx, max(cnt) as cntx from (';$qps_post=') group by dtx';
			}else{
				$table="_1d";$period=86400*30;
				$qps_pref='select (dtz - dtz % 86400) as dtx,max(cnt) as cntx from (';$qps_post=') group by dtx';
			};


			if (array_key_exists("ltype",$REQUEST) and $REQUEST["ltype"] == 'stats' ){

				$sql_hits="
				select 'st' as tbl, row_number() over (order by client_ip) as rowid $fields_h, sum(cnt) as cnt from (
				select client_ip, mac, fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, count(*) as cnt from hits_raw  qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_5m),0) $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
				union
				select client_ip, mac, fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from hits_5m  qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_1h),0) $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
				union
				select client_ip, mac, fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from hits_1h  qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_1d),0) $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
				union
				select client_ip, mac, fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from hits_1d qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
				) group by tbl $fields_h";
				$sql_hits_count="select count(*) as cnt from ($sql_hits)";
				$sql_hits.=" $order;";

				$sql_queries="
				select 'st' as tbl, row_number() over (order by client_ip) as rowid $fields_q, sum(cnt) as cnt from (
				select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, count(*) as cnt  from queries_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_5m),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
				union
				select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt  from queries_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_1h),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
				union
				select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt  from queries_1h qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_1d),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
				union
				select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt  from queries_1d qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
				) group by tbl $fields_q";

				$sql_queries_count="select count(*) as cnt from ($sql_queries)";
				$sql_queries.=" $order;";


			}else{
				$sql_hits="
				select *, ifnull(name,client_ip) as cname from (
				select max(qr.rowid) as rowid, 'raw' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(qr.rowid) as cnt, name, vendor, comment from hits_raw  qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_5m),0) $filter_hits group by tbl, client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
				union
				select max(qr.rowid) as rowid, '5m' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, sum(cnt) as cnt, name, vendor, comment from hits_5m  qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_1h),0) $filter_hits group by tbl, client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
				union
				select max(qr.rowid) as rowid, '1h' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, sum(cnt) as cnt, name, vendor, comment from hits_1h  qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_1d),0) $filter_hits group by tbl, client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
				union
				select qr.rowid, '1d' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, cnt, name, vendor, comment from hits_1d qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_hits
				)";
				$sql_hits_count="select count(*) as cnt from ($sql_hits)";
				$sql_hits.=" $order;";

				$sql_queries="
				select *, ifnull(name,client_ip) as cname from (
				select max(qr.rowid) as rowid, 'raw' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(qr.rowid) as cnt, name, vendor, comment from queries_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_5m),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment
				union
				select max(qr.rowid) as rowid, '5m' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, sum(cnt) as cnt, name, vendor, comment from queries_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_1h),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment
				union
				select max(qr.rowid) as rowid, '1h' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, sum(cnt) as cnt, name, vendor, comment from queries_1h qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_1d),0) $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment
				union
				select qr.rowid, '1d' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt, name, vendor, comment from queries_1d qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_queries
				)";

				$sql_queries_count="select count(*) as cnt from ($sql_queries)";
				$sql_queries.=" $order;";
			};
		break;
		default:
			$table="_raw";$period=1800;


			$sql_hits="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, cnt from hits_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_hits $order;";
			$sql_hits_count="select count(qr.rowid) as cnt from hits_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_hits;";

			$sql_queries="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt from queries_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_queries $order;";
			$sql_queries_count="select count(qr.rowid) as cnt from queries_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period $filter_queries;";
	endswitch;

	switch ($REQUEST['method'].' '.$REQUEST["req"]):
    case "GET queries_raw":
//			$sql="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, ".($table=="_raw"?"'1' as":"")." cnt from queries$table where dt>=strftime('%s', 'now')-$period order by dt desc;";
			$response='{"status":"ok", "records":"'.(DB_fetchRecord($db,$sql_queries_count)['cnt']).'","data":'.json_encode(DB_selectArray($db,$sql_queries)).'}'; //,"sql":"'.$sql_queries.'"
      break;
    case "GET hits_raw":
//			$sql="select rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, ".($table=="_raw"?"'1' as":"")." cnt from hits$table where dt>=strftime('%s', 'now')-$period order by dt desc";
			$response='{"status":"ok", "records":"'.(DB_fetchRecord($db,$sql_hits_count)['cnt']).'","data":'.json_encode(DB_selectArray($db,$sql_hits)).'}';
      break;
		case "GET dash_topX_req":
			if ($period<=86400)
				$sql="select fqdn as fname, count(rowid) as cnt from queries_raw where dt>=strftime('%s', 'now')-$period and action='allowed' group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select fqdn as fname, count(rowid) as cnt2 from queries_raw where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 and action='allowed' group by fqdn  order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select fqdn as fname, sum(cnt) as cnt2 from queries_1d where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period and action='allowed' group by fqdn order by cnt2 desc limit $dash_topx)
				)  group by fname order by cnt desc limit $dash_topx
				";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;

		case "GET dash_topX_server":
			if ($period<=86400)
				$sql="select server as fname, count(rowid) as cnt from queries_raw where dt>=strftime('%s', 'now')-$period and action='allowed' group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select server as fname, count(rowid) as cnt2 from queries_raw where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 and action='allowed' group by fname order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select server as fname, sum(cnt) as cnt2 from queries_1d where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period and action='allowed' group by fname order by cnt2 desc limit $dash_topx)
				)  group by fname order by cnt desc limit $dash_topx
				";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;

		case "GET dash_topX_req_type":
			if ($period<=86400)
				$sql="select type as fname, count(rowid) as cnt from queries_raw where dt>=strftime('%s', 'now')-$period and action='allowed' group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select type as fname, count(rowid) as cnt2 from queries_raw where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 and action='allowed' group by fname order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select type as fname, sum(cnt) as cnt2 from queries_1d where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period and action='allowed' group by fname order by cnt2 desc limit $dash_topx)
				)  group by fname order by cnt desc limit $dash_topx
				";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET dash_topX_client":
			$join=$assets_by=="mac"?"mac":"client_ip";
			if ($period<=86400)
			$sql="select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as fname, count(qr.rowid) as cnt, mac from queries_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period and action='allowed' group by fname, mac order by cnt desc limit $dash_topx";
				else $sql="
				select cname as fname, mac, sum(cnt2) as cnt from (
					select cname, mac, cnt2 from (select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as cname, count(qr.rowid) as cnt2, mac from queries_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 and action='allowed' group by cname, mac order by cnt2 desc limit $dash_topx)
				union
					select cname, mac, cnt2 from (select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as cname, sum(qr.cnt) as cnt2, mac from queries_1d qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period and action='allowed' group by cname, mac order by cnt2 desc limit $dash_topx)
				)  group by fname, mac order by cnt desc limit $dash_topx
				";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET dash_topX_breq":
			if ($period<=86400)
				$sql="select fqdn as fname, count(rowid) as cnt from hits_raw where dt>=strftime('%s', 'now')-$period group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select fqdn as fname, count(rowid) as cnt2 from hits_raw where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 group by fname order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select fqdn as fname, sum(cnt) as cnt2 from hits_1d where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period group by fname order by cnt2 desc limit $dash_topx)
				)  group by fname order by cnt desc limit $dash_topx
				";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET dash_topX_bclient":
			$join=$assets_by=="mac"?"mac":"client_ip";
			if ($period<=86400)
				$sql="select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as fname, count(qr.rowid) as cnt, mac from hits_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period group by fname, mac order by cnt desc limit $dash_topx";
				else $sql="
				select fname, mac, sum(cnt2) as cnt from (
					select fname, mac, cnt2 from (select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as fname, count(qr.rowid) as cnt2, mac from hits_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 group by fname, mac order by cnt2 desc limit $dash_topx)
				union
					select fname, mac, cnt2 from (select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as fname, sum(qr.cnt) as cnt2, mac from hits_1d qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period group by fname, mac order by cnt2 desc limit $dash_topx)
				)  group by fname,mac order by cnt desc limit $dash_topx
				";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET dash_topX_feeds":
			if ($period<=86400)
				$sql="select feed as fname, count(rowid) as cnt from hits_raw where dt>=strftime('%s', 'now')-$period group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select feed as fname, count(rowid) as cnt2 from hits_raw where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 group by fname order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select feed as fname, sum(cnt) as cnt2 from hits_1d where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period group by fname order by cnt2 desc limit $dash_topx)
				)  group by fname order by cnt desc limit $dash_topx
				";
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET qps_chart":
			if ($retention['queries_5m']>=30) {$tbl="5m";$div=5;} else {$tbl="1h";$div=60;};
			if ($period<=86400) //we need queries per minute and show max QPM, to make it accurate we need max/min per minute
				$sql="$qps_pref select (dt - dt % 60) as dtz, count(rowid) as cnt from queries_raw where dt>=strftime('%s', 'now')-$period group by dtz $qps_post";
				else $sql="
				$qps_pref select dtz, sum(cnt2) as cnt from (
					select (dt - dt % 60) as dtz, count(rowid) as cnt2 from queries_raw where dt>=strftime('%s', 'now')-strftime('%s', 'now')%300 group by dtz
				union
					select dt as dtz, sum(cnt)/$div as cnt2 from queries_$tbl where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period group by dtz
				)  group by dtz $qps_post
				";
			$qps=array();
			foreach(DB_selectArrayNum($db,$sql) as $rec){
				$qps[]=[$rec[0]*1000,$rec[1]];
			};
			if ($period<=86400) //we need queries per minute and show max QPM, to make it accurate we need max/min per minute
			$sql="$qps_pref select (dt - dt % 60) as dtz, count(rowid) as cnt from hits_raw where dt>=strftime('%s', 'now')-$period group by dtz $qps_post";
				else $sql="
				$qps_pref select dtz, sum(cnt2) as cnt from (
					select (dt - dt % 60) as dtz, count(rowid) as cnt2 from hits_raw where dt>=strftime('%s', 'now')-strftime('%s', 'now')%300 group by dtz
				union
					select (dt - dt % 60) as dtz, sum(cnt)/5 as cnt2 from hits_5m where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period group by dtz
				)  group by dtz $qps_post
				";
			$hits=array();
			foreach(DB_selectArrayNum($db,$sql) as $rec){
				$hits[]=[$rec[0]*1000,$rec[1]];
			};
			$response='[{"name":"Queries","data":'.json_encode($qps).'},{"name":"Blocked","data":'.json_encode($hits).'}]';
			break;
		case "GET RPIsettings":

			$sql="
			select 'queries_raw' as tbl, count(rowid) as cnt, strftime('%Y-%m-%dT%H:%M:%SZ',min(dt), 'unixepoch', 'utc') as dtz, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtmax from queries_raw
			union
			select 'queries_5m' as tbl, count(rowid) as cnt, strftime('%Y-%m-%dT%H:%M:%SZ',min(dt), 'unixepoch', 'utc') as dtz, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtmax from queries_5m
			union
			select 'queries_1h' as tbl, count(rowid) as cnt, strftime('%Y-%m-%dT%H:%M:%SZ',min(dt), 'unixepoch', 'utc') as dtz, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtmax from queries_1h
			union
			select 'queries_1d' as tbl, count(rowid) as cnt, strftime('%Y-%m-%dT%H:%M:%SZ',min(dt), 'unixepoch', 'utc') as dtz, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtmax from queries_1d
			union
			select 'hits_raw' as tbl, count(rowid) as cnt, strftime('%Y-%m-%dT%H:%M:%SZ',min(dt), 'unixepoch', 'utc') as dtz, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtmax from hits_raw
			union
			select 'hits_5m' as tbl, count(rowid) as cnt, strftime('%Y-%m-%dT%H:%M:%SZ',min(dt), 'unixepoch', 'utc') as dtz, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtmax from hits_5m
			union
			select 'hits_1h' as tbl, count(rowid) as cnt, strftime('%Y-%m-%dT%H:%M:%SZ',min(dt), 'unixepoch', 'utc') as dtz, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtmax from hits_1h
			union
			select 'hits_1d' as tbl, count(rowid) as cnt, strftime('%Y-%m-%dT%H:%M:%SZ',min(dt), 'unixepoch', 'utc') as dtz, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtmax from hits_1d
			";
			$cnts=[];
			foreach(DB_selectArray($db,$sql) as $rec){
				$cnts[$rec['tbl']]=[$rec['cnt'],$rec['dtz'],$rec['dtmax']];
			};
			$sql="select rtrim(substr(name,1,INSTR(name,'_')+3),'_') as tbl, sum(pgsize) as size from dbstat where name like 'queries%' or name like 'hits%' group by tbl order by substr(tbl,-3,3) desc, tbl desc;";
			$stats=[];
			foreach(DB_selectArray($db,$sql) as $rec){
				$stats[]=[$rec['tbl'],$rec['size'],$cnts[$rec['tbl']][0],$cnts[$rec['tbl']][1],$cnts[$rec['tbl']][2],$retention[$rec['tbl']]];
			};
			$response='{"status":"success","retention":'.json_encode($stats).',"assets_by":"'.$assets_by.'","assets_autocreate":"'.$assets_autocreate.'","dashboard_topx":'.$dash_topx.'}';
			break;
		case "PUT RPIsettings":
			$settings='
<?php
/*
RpiDNS powered by https://ioc2rpz.net
(c) Vadim Pavlov 2020
*/
	$assets_by="'.($REQUEST['assets_by']=='mac'?'mac':'ip').'";//or ip
	$assets_autocreate='.($REQUEST['assets_autocreate']=='true'?true:false).';
	$retention["hits_raw"]='.(intval($REQUEST['hits_raw'])>0?intval($REQUEST['hits_raw']):14).'; //retention in days
	$retention["hits_5m"]='.(intval($REQUEST['hits_5m'])>0?intval($REQUEST['hits_5m']):30).'; //retention in days
	$retention["hits_1h"]='.(intval($REQUEST['hits_1h'])>0?intval($REQUEST['hits_1h']):180).'; //retention in days
	$retention["hits_1d"]='.(intval($REQUEST['hits_1d'])>0?intval($REQUEST['hits_1d']):730).'; //retention in days
	$retention["queries_raw"]='.(intval($REQUEST['queries_raw'])>0?intval($REQUEST['queries_raw']):14).'; //retention in days
	$retention["queries_5m"]='.(intval($REQUEST['queries_5m'])>0?intval($REQUEST['queries_5m']):30).'; //retention in days
	$retention["queries_1h"]='.(intval($REQUEST['queries_1h'])>0?intval($REQUEST['queries_1h']):90).'; //retention in days
	$retention["queries_1d"]='.(intval($REQUEST['queries_1d'])>0?intval($REQUEST['queries_1d']):365).'; //retention in days
	$dash_topx='.(intval($REQUEST['dash_topx'])>0?intval($REQUEST['dash_topx']):100).';
?>
			';
			if (file_put_contents("/opt/rpidns/www/rpisettings.php",$settings,LOCK_EX) === false) $response='{"status":"error", "reason","can not save settings"}'; else $response='{"status":"success"}';
			break;

		case "GET download":
			$zip=false;
			switch ($REQUEST['file']):
				case "DB":
					$zip=true;
					$file_name="rpidns.sqlite.gzip";
					//$file = fopen( "/opt/rpidns/www/rpidns.sqlite", "rb");
					$file = popen( "/bin/gzip -q -c -5 "."/opt/rpidns/www/db/".DBFile, "rb");
					$file_type="gzip";//"vnd.sqlite3";
					break;
				case "CA":
					$file_name="ioc2rpzCA.crt";
					$file = fopen( "/opt/rpidns/www/ioc2rpzCA.crt", "rb");
					$file_type="x-pem-file";
					break;
				case "bind.log":
					//$zip=true;
					$file_name="bind.log.zip";
					$file = popen( "/bin/gzip -q -c -5 /opt/rpidns/logs/bind.log", "rb");
					//$file = fopen( "/opt/rpidns/www/bind.log", "r");
					$file_type="gzip";
					break;
				case "bind_queries.log":
					$file_name="bind_queries.log.zip";
					$file = popen( "/bin/gzip -q -c -5 /opt/rpidns/logs/bind_queries.log", "rb");
					$file_type="gzip";
					break;
				case "bind_rpz.log":
					$file_name="bind_rpz.log.zip";
					$file = popen( "/bin/gzip -q -c -5 /opt/rpidns/logs/bind_rpz.log", "rb");
					$file_type="gzip";
					break;
			endswitch;

				header("Content-Type: application/$file_type");
				header("Content-Transfer-Encoding: Binary");
				header("Content-Disposition: attachment; filename=\"$file_name\"");
				header('Expires: 0');

				ob_end_clean();
				fpassthru($file);
				if ($zip) pclose($file); else fclose($file);

		break;

    case "GET assets":
			$sql="select rowid, strftime('%Y-%m-%dT%H:%M:%SZ',added_dt, 'unixepoch', 'utc') as dtz, name, address, vendor, comment from assets;";
			$sql_count="select count(rowid) as cnt from assets;";
			$response='{"status":"ok", "records":"'.(DB_fetchRecord($db,$sql_count)['cnt']).'","data":'.json_encode(DB_selectArray($db,$sql)).'}';
      break;

    case "POST assets":
      $sql="insert into assets(name, address, vendor, comment, added_dt) values('".DB_escape($db,$REQUEST['name'])."','".DB_escape($db,$REQUEST['address'])."','".DB_escape($db,$REQUEST['vendor'])."','".DB_escape($db,$REQUEST['comment'])."',".time().")";
      if (DB_execute($db,$sql)) $response='{"status":"success"}'; else $response='{"status":"failed", "reason":"'.DB_lasterror($db).'"}';
			break;

    case "PUT assets":
			$sql="update assets set name='".DB_escape($db,$REQUEST['name'])."',address='".DB_escape($db,$REQUEST['address'])."',vendor='".DB_escape($db,$REQUEST['vendor'])."',comment='".DB_escape($db,$REQUEST['comment'])."' where rowid=".intval($REQUEST['id']);
      if (DB_execute($db,$sql)) $response='{"status":"success"}'; else $response='{"status":"failed", "reason":"'.DB_lasterror($db).'"}';
			break;

    case "DELETE assets":
			$sql="delete from assets where rowid=".intval($REQUEST['id']);
      if (DB_execute($db,$sql)) $response='{"status":"success"}'; else $response='{"status":"failed", "reason":"'.DB_lasterror($db).'"}';
			break;

    case "GET blacklist":
    case "GET whitelist":
			$list=$REQUEST["req"]=='blacklist'?'bl':'wl';
			$sql="select rowid, strftime('%Y-%m-%dT%H:%M:%SZ',added_dt, 'unixepoch', 'utc') as dtz, ioc, comment, subdomains, active from localzone where ltype='$list';";
			$sql_count="select count(rowid) as cnt from localzone where ltype='$list';";
			$response='{"status":"ok", "records":"'.(DB_fetchRecord($db,$sql_count)['cnt']).'","data":'.json_encode(DB_selectArray($db,$sql)).'}';
      break;

    case "POST blacklist":
    case "POST whitelist":
			$list=$REQUEST["req"]=='blacklist'?'bl':'wl';
			$ioc=filter_var($REQUEST['ioc'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
      $sql="insert into localzone(ioc, active, subdomains, comment, added_dt, ltype) values('".DB_escape($db,$ioc)."',".($REQUEST['active']=='true'?'true':'false').",".($REQUEST['subdomains']=='true'?'true':'false').",'".DB_escape($db,$REQUEST['comment'])."',".time().",'$list')";
      if (DB_execute($db,$sql)) {
				$out=[];
				if ($REQUEST['active']=='true') {if ($REQUEST['subdomains']=='true') exec('printf "server 127.0.0.1\nupdate add '.$ioc.'.'.$list.'.ioc2rpz.local 60 CNAME .\nupdate add *.'.$ioc.'.'.$list.'.ioc2rpz.local 60 CNAME .\nsend\n"| /usr/bin/nsupdate -d -v',$out); else exec('printf "server 127.0.0.1\nupdate add '.$ioc.'.'.$list.'.ioc2rpz.local 60 CNAME .\nsend\n" | /usr/bin/nsupdate -d -v',$out);};
				$response='{"status":"success","details":'.json_encode($out).'}';
			} else $response='{"status":"failed", "reason":"'.DB_lasterror($db).'"}';
			break;

    case "PUT blacklist":
    case "PUT whitelist":
			$list=$REQUEST["req"]=='blacklist'?'bl':'wl';
			$rec=DB_fetchRecord($db,"select ioc,active,subdomains from localzone where rowid=".intval($REQUEST['id']));
			$ioc=filter_var($REQUEST['ioc'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
      $sql="update localzone set ioc='".DB_escape($db,$ioc)."', active=".($REQUEST['active']=='true'?'true':'false').", subdomains=".($REQUEST['subdomains']=='true'?'true':'false').", comment='".DB_escape($db,$REQUEST['comment'])."' where rowid=".intval($REQUEST['id']);
      if (DB_execute($db,$sql)) {
				$response='{"status":"success"}';
				$out=[];
				if (($rec['active']=='1' and $REQUEST['active']!='true') or ($ioc != $rec['active'])) exec('printf "server 127.0.0.1\nupdate delete '.$rec['ioc'].'.'.$list.'.ioc2rpz.local 60 CNAME .\nupdate delete *.'.$rec['ioc'].'.'.$list.'.ioc2rpz.local 60 CNAME .\nsend\n" | /usr/bin/nsupdate -d -v',$out);
				if (($rec['subdomains']=='1' and $REQUEST['subdomains']!='true')) exec('printf "server 127.0.0.1\nupdate delete *.'.$rec['ioc'].'.'.$list.'.ioc2rpz.local 60 CNAME .\nsend\n" | /usr/bin/nsupdate -d -v',$out);
				if ($REQUEST['active']=='true') {if ($REQUEST['subdomains']=='true') exec('printf "server 127.0.0.1\nupdate add '.$ioc.'.'.$list.'.ioc2rpz.local 60 CNAME .\nupdate add *.'.$ioc.'.'.$list.'.ioc2rpz.local 60 CNAME .\nsend\n"| /usr/bin/nsupdate -d -v',$out); else exec('printf "server 127.0.0.1\nupdate add '.$ioc.'.'.$list.'.ioc2rpz.local 60 CNAME .\nsend\n" | /usr/bin/nsupdate -d -v',$out);};
			} else $response='{"status":"failed", "reason":"'.DB_lasterror($db).'"}';
			break;

    case "DELETE blacklist":
    case "DELETE whitelist":
			$list=$REQUEST["req"]=='blacklist'?'bl':'wl';
			$ioc=DB_fetchRecord($db,"select ioc from localzone where rowid=".intval($REQUEST['id']))['ioc'];
			$sql="delete from localzone where rowid=".intval($REQUEST['id']);
      if (DB_execute($db,$sql)) {
				$out=[];
				exec('printf "server 127.0.0.1\nupdate delete '.$ioc.'.'.$list.'.ioc2rpz.local 60 CNAME .\nupdate delete *.'.$ioc.'.'.$list.'.ioc2rpz.local 60 CNAME .\nsend\n" | /usr/bin/nsupdate -d -v',$out);
				$response='{"status":"success","details":'.json_encode($out).'}';
			} else $response='{"status":"failed", "reason":"'.DB_lasterror($db).'"}';
			break;


    case "GET server_stats":
			$server_stats=[];
			$cores=intval(trim(exec('/usr/bin/nproc')));
			$load=sys_getloadavg();
			$server_stats[0]["fname"]='CPU load';$server_stats[0]["cnt"]="".round(($load[0] * 100) / $cores,2).'%, '.round(($load[1] * 100) / $cores,2).'%, '.round(($load[2] * 100) / $cores,2).'%';
			$memory=preg_split('/\s+/',trim(exec('/usr/bin/free | /bin/grep Mem')));
			$server_stats[1]["fname"]='Memory usage';$server_stats[1]["cnt"]=round(intval($memory[2])/intval($memory[1])*100,2)."%";
			$server_stats[2]["fname"]='Disk usage';$server_stats[2]["cnt"]=round (100 - ((disk_free_space  ($RpiPath) / disk_total_space ($RpiPath)) * 100)) .'%';
			$uptime=floatval(@file_get_contents('/proc/uptime'));
			$server_stats[3]["fname"]='Uptime'; $server_stats[3]["cnt"] = intdiv($uptime, 86400).' days '.(intdiv($uptime, 3600) % 24).' hours '.(intdiv($uptime, 60) % 60).' min '.($uptime % 60).' sec';
			#$temp=exec('/opt/vc/bin/vcgencmd measure_temp | awk -F "=" \'{print $2}\'');
			$temp=round(intval(trim(exec('cat /sys/class/thermal/thermal_zone0/temp')))/1000,2)."'C";
			$server_stats[4]["fname"]='Temp'; $server_stats[4]["cnt"]=$temp;
			$response='{"status":"ok", "records":"4","data":'.json_encode($server_stats).'}';
		break;
	case "GET rpz_feeds":
			$feeds=[];
			exec('/bin/grep "zone.*policy" /etc/bind/named.conf.options',$out);
			#zone "wl-ip.ioc2rpz.local" policy passthru log no;#local whitelist ip-based
			foreach ($out as $line){
				if (preg_match('/^zone "([^"]+)" policy ([^;]+);\h*#?(.*)$/',$line,$rpz)){
					$feeds[]=["feed"=>trim($rpz[1]), "action"=>trim($rpz[2]), "desc"=>trim($rpz[3])];
				};
			};

			$response='{"status":"ok", "records":"'.count($feeds).'","data":'.json_encode($feeds).'}';
		break;
		//
	case "PUT retransfer_feed":
		exec('/bin/grep "zone.*policy" /etc/bind/named.conf.options',$out);
		#zone "wl-ip.ioc2rpz.local" policy passthru log no;#local whitelist ip-based
		foreach ($out as $line){
			if (preg_match('/^zone "([^"]+)" policy ([^;]+);\h*#?(.*)$/',$line,$rpz)){
				$feeds[trim($rpz[1])]=["feed"=>trim($rpz[1]), "action"=>trim($rpz[2]), "desc"=>trim($rpz[3])];
			};
		};
		if (array_key_exists($REQUEST['feed'],$feeds)) {
			exec('/usr/sbin/rndc -Vr retransfer '.escapeshellcmd($REQUEST['feed']),$out2,$exres);
			$response='{"status":"success","details":"feed retransfer was requested"}'; #'.$REQUEST['feed'].implode($out2).' result:'.$exres.'
			} else $response='{"status":"failed", "reason":"feed was not provisioned"}';
		break;

  case "POST import":
			$import_db_file="";
			$postfix = bin2hex(random_bytes(10));
			if (!file_exists(TMPDir)) {$oldumask=umask(0);mkdir(TMPDir, 0775, true);umask($oldumask);};
			switch (exec("/usr/bin/file ".$_FILES['file']['tmp_name']." | /usr/bin/awk '{print $2}'")):
				case "SQLite":
					if (move_uploaded_file($_FILES['file']['tmp_name'],TMPDir."/import_db_".$postfix.".sqlite")) $import_db_file=TMPDir."/import_db_".$postfix.".sqlite";
				break;
				case "gzip":
					exec("gzip -dc ".$_FILES['file']['tmp_name']. " > ".TMPDir."/import_db_".$postfix.".sqlite");
					$import_db_file=exec("/usr/bin/file ".TMPDir."/import_db_".$postfix.".sqlite"." | /usr/bin/awk '{print $2}'")=="SQLite"?TMPDir."/import_db_".$postfix.".sqlite":"";
				break;
				case "Zip":
					exec("unzip -p ".$_FILES['file']['tmp_name']. ">".TMPDir."/import_db_".$postfix.".sqlite");
					$import_db_file=exec("/usr/bin/file ".TMPDir."/import_db_".$postfix.".sqlite"." | /usr/bin/awk '{print $2}'")=="SQLite"?TMPDir."/import_db_".$postfix.".sqlite":"";
				break;
			endswitch;
			if ($import_db_file!=""){
				chmod(TMPDir."/import_db_".$postfix.".sqlite",0660);
				file_put_contents(TMPDir."/rpidns_import_ready",TMPDir."/import_db_".$postfix.".sqlite"."|".$REQUEST['objects']);
				chmod(TMPDir."/rpidns_import_ready",0660);
				$response='{"status":"success","details":"import started","file_data":'.json_encode($_FILES).',"debug":"'.TMPDir."/import_db_".$postfix.'.sqlite|'.$REQUEST['objects'].'"}';
			} else $response='{"status":"error","details":"bad file","file_data":'.json_encode($_FILES).'}';
			if (is_uploaded_file($_FILES['file']['tmp_name'])) unlink($_FILES['file']['tmp_name']);

		break;


    default:
      $response='{"status":"failed", "records":"0", "reason":"not supported API call:'.$REQUEST['method'].' '.$REQUEST["req"].'"}';
	endswitch;


  #close DB
  $db->close();
	if (isset($response)) echo $response;

//phpinfo();

?>
