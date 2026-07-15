<?php
// (c) Vadim Pavlov 2020 - 2026
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

	// Custom period parameters
	$start_dt = array_key_exists("start_dt", $REQUEST) ? intval($REQUEST["start_dt"]) : 0;
	$end_dt = array_key_exists("end_dt", $REQUEST) ? intval($REQUEST["end_dt"]) : 0;

	if (array_key_exists("period",$REQUEST))  switch ($REQUEST["period"]):
		case "custom":
			// Validate custom period parameters
			if ($start_dt <= 0 || $end_dt <= 0) {
				echo '{"status":"error","reason":"start_dt and end_dt are required for custom period"}';
				exit;
			}
			if ($start_dt >= $end_dt) {
				echo '{"status":"error","reason":"start_dt must be less than end_dt"}';
				exit;
			}

			$duration = $end_dt - $start_dt;

			// Determine aggregation level based on duration (per Requirements 6.1-6.4)
			if ($duration <= 3600) {
				// <= 1 hour: use raw data only
				$table = "_raw";
				$qps_pref = '';
				$qps_post = '';

				if (array_key_exists("ltype", $REQUEST) and $REQUEST["ltype"] == 'stats') {
					$sql_hits = "select 'st' as tbl, rowid $fields_h, sum(cnt) as cnt from (select row_number() over (order by client_ip) as rowid, client_ip, mac, fqdn, action, rule_type, rule, feed, count(*) as cnt, ifnull(a.name,client_ip) as cname, vendor, comment from hits_raw qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_hits group by client_ip, mac, fqdn, action, rule_type, rule, feed, cname, vendor, comment) group by tbl $fields_h";
					$sql_hits_count = "select count(*) as cnt from ($sql_hits)";
					$sql_hits .= " $order;";

					$sql_queries = "select 'st' as tbl, rowid $fields_q, sum(cnt) as cnt from (select row_number() over (order by client_ip) as rowid, client_ip, mac, fqdn, type, class, options, server, action, ifnull(a.name,client_ip) as cname, vendor, comment, count(*) as cnt from queries_raw qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, cname, vendor, comment) group by tbl $fields_q";
					$sql_queries_count = "select count(*) as cnt from ($sql_queries)";
					$sql_queries .= " $order;";
				} else {
					$sql_hits = "select qr.rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, '1' as cnt, ifnull(a.name,client_ip) as cname, vendor, comment from hits_raw qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_hits";
					$sql_hits_count = "select count(*) as cnt from ($sql_hits)";
					$sql_hits .= " $order;";

					$sql_queries = "select qr.rowid,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, '1' as cnt, ifnull(a.name,client_ip) as cname, vendor, comment from queries_raw qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_queries";
					$sql_queries_count = "select count(*) as cnt from ($sql_queries)";
					$sql_queries .= " $order;";
				}
			} else if ($duration <= 86400) {
				// <= 1 day: use 5m + raw for recent data
				$table = "_5m";
				$qps_pref = 'select (dtz - dtz % 1800) as dtx, max(cnt) as cntx from (';
				$qps_post = ') group by dtx';

				if (array_key_exists("ltype", $REQUEST) and $REQUEST["ltype"] == 'stats') {
					$sql_hits = "
					select 'st' as tbl, row_number() over (order by client_ip) as rowid $fields_h, sum(cnt) as cnt from (
					select client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, count(qr.rowid) as cnt from hits_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_5m),0) and dt>=$start_dt and dt<=$end_dt $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, cname
					union
					select client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from hits_5m qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, cname
					) group by tbl $fields_h";
					$sql_hits_count = "select count(*) as cnt from ($sql_hits)";
					$sql_hits .= " $order;";

					$sql_queries = "
					select 'st' as tbl, row_number() over (order by client_ip) as rowid $fields_q, sum(cnt) as cnt from (
					select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, count(*) as cnt from queries_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_5m),0) and dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
					union
					select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from queries_5m qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
					) group by tbl $fields_q";
					$sql_queries_count = "select count(*) as cnt from ($sql_queries)";
					$sql_queries .= " $order;";
				} else {
					$sql_hits = "
					select *, ifnull(name,client_ip) as cname from (
					select max(qr.rowid) as rowid, 'raw' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(qr.rowid) as cnt, name, vendor, comment from hits_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_5m),0) and dt>=$start_dt and dt<=$end_dt $filter_hits group by tbl, client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
					union
					select qr.rowid, '5m' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, cnt, name, vendor, comment from hits_5m qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_hits
					)";
					$sql_hits_count = "select count(*) as cnt from ($sql_hits)";
					$sql_hits .= " $order;";

					$sql_queries = "
					select *, ifnull(name,client_ip) as cname from (
					select max(qr.rowid) as rowid, 'raw' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(qr.rowid) as cnt, name, vendor, comment from queries_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_5m),0) and dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment
					union
					select qr.rowid, '5m' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt, name, vendor, comment from queries_5m qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_queries
					)";
					$sql_queries_count = "select count(*) as cnt from ($sql_queries)";
					$sql_queries .= " $order;";
				}
			} else if ($duration <= 604800) {
				// <= 7 days: use 1h + 5m + raw
				$table = "_1h";
				$qps_pref = 'select (dtz - dtz % 21600) as dtx, max(cnt) as cntx from (';
				$qps_post = ') group by dtx';

				if (array_key_exists("ltype", $REQUEST) and $REQUEST["ltype"] == 'stats') {
					$sql_hits = "
					select 'st' as tbl, row_number() over (order by client_ip) as rowid $fields_h, sum(cnt) as cnt from (
					select client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, count(qr.rowid) as cnt from hits_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_5m),0) and dt>=$start_dt and dt<=$end_dt $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, cname
					union
					select client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from hits_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_1h),0) and dt>=$start_dt and dt<=$end_dt $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, cname
					union
					select client_ip, mac, fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from hits_1h qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment, cname
					) group by tbl $fields_h";
					$sql_hits_count = "select count(*) as cnt from ($sql_hits)";
					$sql_hits .= " $order;";

					$sql_queries = "
					select 'st' as tbl, row_number() over (order by client_ip) as rowid $fields_q, sum(cnt) as cnt from (
					select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, count(*) as cnt from queries_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_5m),0) and dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
					union
					select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from queries_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_1h),0) and dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
					union
					select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment,ifnull(name,client_ip) as cname, sum(cnt) as cnt from queries_1h qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
					) group by tbl $fields_q";
					$sql_queries_count = "select count(*) as cnt from ($sql_queries)";
					$sql_queries .= " $order;";
				} else {
					$sql_hits = "
					select *, ifnull(name,client_ip) as cname from (
					select max(qr.rowid) as rowid, 'raw' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(qr.rowid) as cnt, name, vendor, comment from hits_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_5m),0) and dt>=$start_dt and dt<=$end_dt $filter_hits group by tbl, client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
					union
					select max(qr.rowid) as rowid, '5m' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, sum(cnt) as cnt, name, vendor, comment from hits_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_1h),0) and dt>=$start_dt and dt<=$end_dt $filter_hits group by tbl, client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
					union
					select qr.rowid, '1h' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, cnt, name, vendor, comment from hits_1h qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_hits
					)";
					$sql_hits_count = "select count(*) as cnt from ($sql_hits)";
					$sql_hits .= " $order;";

					$sql_queries = "
					select *, ifnull(name,client_ip) as cname from (
					select max(qr.rowid) as rowid, 'raw' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(qr.rowid) as cnt, name, vendor, comment from queries_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_5m),0) and dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment
					union
					select max(qr.rowid) as rowid, '5m' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, sum(cnt) as cnt, name, vendor, comment from queries_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_1h),0) and dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment
					union
					select qr.rowid, '1h' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt, name, vendor, comment from queries_1h qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_queries
					)";
					$sql_queries_count = "select count(*) as cnt from ($sql_queries)";
					$sql_queries .= " $order;";
				}
			} else {
				// > 7 days: use 1d + 1h + 5m + raw
				$table = "_1d";
				$qps_pref = 'select (dtz - dtz % 86400) as dtx,max(cnt) as cntx from (';
				$qps_post = ') group by dtx';

				if (array_key_exists("ltype", $REQUEST) and $REQUEST["ltype"] == 'stats') {
					$sql_hits = "
					select 'st' as tbl, row_number() over (order by client_ip) as rowid $fields_h, sum(cnt) as cnt from (
					select client_ip, mac, fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, count(*) as cnt from hits_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_5m),0) and dt>=$start_dt and dt<=$end_dt $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
					union
					select client_ip, mac, fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from hits_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_1h),0) and dt>=$start_dt and dt<=$end_dt $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
					union
					select client_ip, mac, fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from hits_1h qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_1d),0) and dt>=$start_dt and dt<=$end_dt $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
					union
					select client_ip, mac, fqdn, action, rule_type, rule, feed, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from hits_1d qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_hits group by client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
					) group by tbl $fields_h";
					$sql_hits_count = "select count(*) as cnt from ($sql_hits)";
					$sql_hits .= " $order;";

					$sql_queries = "
					select 'st' as tbl, row_number() over (order by client_ip) as rowid $fields_q, sum(cnt) as cnt from (
					select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, count(*) as cnt from queries_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_5m),0) and dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
					union
					select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from queries_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_1h),0) and dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
					union
					select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from queries_1h qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_1d),0) and dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
					union
					select client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, ifnull(name,client_ip) as cname, sum(cnt) as cnt from queries_1d qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment, cname
					) group by tbl $fields_q";
					$sql_queries_count = "select count(*) as cnt from ($sql_queries)";
					$sql_queries .= " $order;";
				} else {
					$sql_hits = "
					select *, ifnull(name,client_ip) as cname from (
					select max(qr.rowid) as rowid, 'raw' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, count(qr.rowid) as cnt, name, vendor, comment from hits_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_5m),0) and dt>=$start_dt and dt<=$end_dt $filter_hits group by tbl, client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
					union
					select max(qr.rowid) as rowid, '5m' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, sum(cnt) as cnt, name, vendor, comment from hits_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_1h),0) and dt>=$start_dt and dt<=$end_dt $filter_hits group by tbl, client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
					union
					select max(qr.rowid) as rowid, '1h' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac,fqdn, action, rule_type, rule, feed, sum(cnt) as cnt, name, vendor, comment from hits_1h qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_1d),0) and dt>=$start_dt and dt<=$end_dt $filter_hits group by tbl, client_ip, mac,fqdn, action, rule_type, rule, feed, name, vendor, comment
					union
					select qr.rowid, '1d' as tbl, strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, action, rule_type, rule, feed, cnt, name, vendor, comment from hits_1d qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_hits
					)";
					$sql_hits_count = "select count(*) as cnt from ($sql_hits)";
					$sql_hits .= " $order;";

					$sql_queries = "
					select *, ifnull(name,client_ip) as cname from (
					select max(qr.rowid) as rowid, 'raw' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, count(qr.rowid) as cnt, name, vendor, comment from queries_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_5m),0) and dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment
					union
					select max(qr.rowid) as rowid, '5m' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, sum(cnt) as cnt, name, vendor, comment from queries_5m qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_1h),0) and dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment
					union
					select max(qr.rowid) as rowid, '1h' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',max(dt), 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, sum(cnt) as cnt, name, vendor, comment from queries_1h qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_1d),0) and dt>=$start_dt and dt<=$end_dt $filter_queries group by client_ip, mac, fqdn, type, class, options, server, action, name, vendor, comment
					union
					select qr.rowid, '1d' as tbl,strftime('%Y-%m-%dT%H:%M:%SZ',dt, 'unixepoch', 'utc') as dtz ,client_ip, mac, fqdn, type, class, options, server, action, cnt, name, vendor, comment from queries_1d qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt $filter_queries
					)";
					$sql_queries_count = "select count(*) as cnt from ($sql_queries)";
					$sql_queries .= " $order;";
				}
			}

			// Set period for dashboard queries (used by other endpoints)
			$period = $duration;
			break;
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

    case "GET research_unique":
			// Research: unique non-blocked (allowed) FQDNs over the selected range.
			// Auth guard runs first, before any query is built or executed
			// (Requirements 1.7, 9.1, 9.4).
			require_once "/opt/rpidns/www/rpi_admin/ResearchAuth.php";
			requireResearchSession();

			// Sortable columns (allowlist) + direction (Requirement 2.9).
			$ru_sortReq = array_key_exists("sortBy",$REQUEST) ? $REQUEST["sortBy"] : 'cnt';
			$ru_allowed = array('fqdn'=>'fqdn', 'cnt'=>'cnt', 'last_seen'=>'last_seen');
			$ru_sortCol = array_key_exists($ru_sortReq,$ru_allowed) ? $ru_allowed[$ru_sortReq] : 'cnt';
			$ru_dir = (array_key_exists("sortDesc",$REQUEST) and $REQUEST["sortDesc"]=='true') ? 'desc' : 'asc';

			// Pagination (Requirement 2.7).
			$ru_pp = (array_key_exists("pp",$REQUEST) and intval($REQUEST["pp"])>0 and intval($REQUEST["pp"])<=500) ? intval($REQUEST["pp"]) : 100;
			$ru_cp = (array_key_exists("cp",$REQUEST) and intval($REQUEST["cp"])>0) ? intval($REQUEST["cp"]) : 1;
			$ru_offset = $ru_pp * ($ru_cp - 1);

			// Case-insensitive substring filter on fqdn (Requirement 2.6). SQLite
			// LIKE is case-insensitive for ASCII. Escaped via DB_escape.
			$ru_filterval = array_key_exists("filter",$REQUEST) ? $REQUEST["filter"] : '';
			$ru_filter = $ru_filterval !== '' ? " and fqdn like '%".DB_escape($db,$ru_filterval)."%'" : '';

			// Reuse the period pre-switch conventions ($period / $start_dt / $end_dt).
			$ru_period = isset($period) ? intval($period) : 1800;

			// Tiered aggregation over the allowed queries in the selected range
			// (Requirements 2.2, 2.3, 2.4). Inclusive bounds for custom range.
			if (array_key_exists("period",$REQUEST) and $REQUEST["period"] === 'custom') {
				if ($ru_period <= 86400) {
					$ru_agg = "select fqdn, count(rowid) as cnt, max(dt) as last_dt from queries_raw where dt>=$start_dt and dt<=$end_dt and action='allowed' $ru_filter group by fqdn";
				} else {
					$ru_agg = "select fqdn, sum(cnt2) as cnt, max(last2) as last_dt from (
						select fqdn, count(rowid) as cnt2, max(dt) as last2 from queries_raw where dt>ifnull((select max(dt) from queries_1d),0) and dt>=$start_dt and dt<=$end_dt and action='allowed' $ru_filter group by fqdn
						union all
						select fqdn, sum(cnt) as cnt2, max(dt) as last2 from queries_1d where dt>=$start_dt and dt<=$end_dt and action='allowed' $ru_filter group by fqdn
					) group by fqdn";
				}
			} else {
				if ($ru_period <= 86400) {
					$ru_agg = "select fqdn, count(rowid) as cnt, max(dt) as last_dt from queries_raw where dt>=strftime('%s', 'now')-$ru_period and action='allowed' $ru_filter group by fqdn";
				} else {
					$ru_agg = "select fqdn, sum(cnt2) as cnt, max(last2) as last_dt from (
						select fqdn, count(rowid) as cnt2, max(dt) as last2 from queries_raw where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 and action='allowed' $ru_filter group by fqdn
						union all
						select fqdn, sum(cnt) as cnt2, max(dt) as last2 from queries_1d where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$ru_period and action='allowed' $ru_filter group by fqdn
					) group by fqdn";
				}
			}

			// Group by fqdn guarantees distinctness (Requirement 2.1); MAX(dt) ->
			// ISO8601 UTC last_seen mirrors the strftime convention (Requirement 2.5).
			$ru_data_sql = "select fqdn, cnt, strftime('%Y-%m-%dT%H:%M:%SZ', last_dt, 'unixepoch', 'utc') as last_seen from ($ru_agg) order by $ru_sortCol $ru_dir limit $ru_pp offset $ru_offset;";
			$ru_count_sql = "select count(*) as cnt from ($ru_agg);";

			// SELECT-only: never modifies DB state (Requirement 9.2). On failure
			// return an error and never present partial data as complete (Req 2.10).
			$ru_result = $db->query($ru_data_sql);
			if ($ru_result === false) {
				$response='{"status":"error","reason":"failed to retrieve unique allowed queries"}';
			} else {
				$ru_rows = [];
				while ($row = $ru_result->fetchArray(SQLITE3_ASSOC)) { $ru_rows[] = $row; }
				$ru_countRec = DB_fetchRecord($db,$ru_count_sql);
				$ru_records = (is_array($ru_countRec) and array_key_exists('cnt',$ru_countRec)) ? $ru_countRec['cnt'] : count($ru_rows);
				$response='{"status":"ok", "records":"'.$ru_records.'","data":'.json_encode($ru_rows).'}';
			}
      break;

    case "GET research_tables":
			// Research: list available table names so the SQL tool can build queries
			// against the schema (Requirement 4.9). Auth guard runs first, before any
			// query is executed (Requirements 1.7, 9.1).
			require_once "/opt/rpidns/www/rpi_admin/ResearchAuth.php";
			requireResearchSession();

			// Open a SEPARATE read-only connection: this endpoint must never modify
			// DB state (Requirement 9.1 / read-only per design).
			$rt_names = [];
			$rt_ok = true;
			try {
				$roDb = new SQLite3("/opt/rpidns/www/db/".DBFile, SQLITE3_OPEN_READONLY);
				$roDb->busyTimeout(15000);
				// Exclude internal sqlite_* objects; ordered for stable output.
				$rt_sql = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name;";
				$rt_result = $roDb->query($rt_sql);
				if ($rt_result === false) {
					$rt_ok = false;
				} else {
					while ($row = $rt_result->fetchArray(SQLITE3_ASSOC)) { $rt_names[] = $row['name']; }
				}
				$roDb->close();
			} catch (Exception $e) {
				$rt_ok = false;
			}

			if ($rt_ok) {
				$response='{"status":"ok","data":'.json_encode($rt_names).'}';
			} else {
				$response='{"status":"error","reason":"failed to retrieve table names"}';
			}
      break;

    case "POST research_sql":
			// Research: execute an administrator-supplied read-only SQL statement.
			// Auth guard runs first, before any validation or execution
			// (Requirements 1.7, 9.1, 9.4).
			require_once "/opt/rpidns/www/rpi_admin/ResearchAuth.php";
			require_once "/opt/rpidns/www/rpi_admin/SqlQueryValidator.php";
			require_once "/opt/rpidns/www/rpi_admin/RejectionAudit.php";
			$rs_user = requireResearchSession();

			// The submitted SQL is merged into $REQUEST from the JSON body by
			// getRequest(). Missing input is treated as an empty (invalid) query.
			$rs_sql = array_key_exists("sql",$REQUEST) ? $REQUEST["sql"] : '';

			// Validate BEFORE any execution occurs (Requirement 4.1). The
			// validator enforces: single statement (Req 4.4), read-only SELECT/
			// WITH entry point (Req 4.1/4.2), no write keywords (Req 4.3), and the
			// <= 10,000 character bound (Req 4.11). It never executes SQL.
			$rs_check = SqlQueryValidator::validate($rs_sql);
			if ($rs_check['valid'] !== true) {
				// On rejection: audit the attempt (Req 9.5/9.6) and return the
				// descriptive reason WITHOUT executing anything (Req 4.3/4.4/4.11).
				// The DB is untouched because no query is ever run.
				$rs_sid = (is_array($rs_user) and array_key_exists('session_id',$rs_user)) ? $rs_user['session_id'] : '';
				RejectionAudit::record($rs_sid, $rs_check['category'], 'research_sql');
				$response='{"status":"error","reason":'.json_encode($rs_check['reason']).'}';
				break;
			}

			// Valid single read-only SELECT/WITH: execute it against a SEPARATE
			// connection opened in READ-ONLY mode (Requirement 4.5). Read-only
			// mode is defense-in-depth: even if the validator had a gap, the
			// connection itself cannot modify the database (Req 9.2/9.5).
			//
			// Execution-time bound (Requirement 4.8/4.10): PHP's SQLite3 binding
			// does not expose a per-query timeout or a progress/interrupt
			// callback, so a hard interrupt of a long-running query is not
			// available. We apply a best-effort 30s bound with two mechanisms:
			//   1. set_time_limit(30) - a PHP-level wall-clock guard.
			//   2. busyTimeout - bounds waits on locks (not relevant for a
			//      read-only single-reader connection, but set for safety).
			//   3. A manual elapsed-time check in the row-fetch loop: if fetching
			//      the result set exceeds 30s we abort, return a timeout error and
			//      DO NOT present the partial rows as a complete result (Req 4.10).
			// The read-only connection guarantees the DB is unchanged regardless
			// of how execution ends (Req 9.2).
			// --- Server-side pagination inputs ---------------------------------
			// The full result set is never transferred; a single page is fetched
			// by wrapping the (validated) user query in a subquery and applying a
			// server-controlled LIMIT/OFFSET. `count=1` requests a bounded total
			// row count, computed once when a new query is submitted; page
			// navigation omits it and the client reuses its cached total.
			$rs_pp = (array_key_exists("pp",$REQUEST) and intval($REQUEST["pp"])>0 and intval($REQUEST["pp"])<=10000) ? intval($REQUEST["pp"]) : 100;
			$rs_cp = (array_key_exists("cp",$REQUEST) and intval($REQUEST["cp"])>0) ? intval($REQUEST["cp"]) : 1;
			$rs_wantCount = (array_key_exists("count",$REQUEST) and ($REQUEST["count"]==='1' or $REQUEST["count"]===1 or $REQUEST["count"]===true));

			// Strip a single optional trailing ';' so the statement can be safely
			// wrapped as a subquery (the validator guarantees a single statement).
			$rs_base = rtrim(trim((string)$rs_sql));
			if (substr($rs_base, -1) === ';') { $rs_base = rtrim(substr($rs_base, 0, -1)); }

			// Page window, clamped to the 10,000-row cap (Requirement 4.6). Rows
			// beyond the cap are not navigable; `truncated` signals when the total
			// exceeded it.
			$rs_cap = 10000;
			$rs_offset = ($rs_cp - 1) * $rs_pp;
			if ($rs_offset < 0) { $rs_offset = 0; }
			$rs_pageLimit = ($rs_offset >= $rs_cap) ? 0 : min($rs_pp, $rs_cap - $rs_offset);

			// Execution-time bound (Req 4.8/4.10): PHP's SQLite3 binding exposes no
			// per-query timeout, so a best-effort 30s bound is applied via
			// set_time_limit plus a wall-clock guard around fetching. The
			// read-only connection guarantees the DB is unchanged (Req 9.2).
			$rs_start = microtime(true);
			$rs_limit_s = 30;
			@set_time_limit($rs_limit_s);

			$rs_columns = [];
			$rs_rows = [];
			$rs_total = null;      // capped total row count (only when requested)
			$rs_truncated = false; // total exceeded the cap (only when requested)
			$rs_error = null;
			$rs_timeout = false;
			$rs_roDb = null;

			// Newlines around the base statement protect a trailing line comment
			// (`-- ...`) from commenting out the wrapping ')'. LIMIT/OFFSET are
			// server-controlled integers, so they cannot alter the statement.
			$rs_dataSql  = "select * from (\n".$rs_base."\n) limit ".$rs_pageLimit." offset ".$rs_offset;
			$rs_countSql = "select count(*) as c from (select 1 from (\n".$rs_base."\n) limit ".($rs_cap + 1).")";

			try {
				$rs_roDb = new SQLite3("/opt/rpidns/www/db/".DBFile, SQLITE3_OPEN_READONLY);
				$rs_roDb->busyTimeout(5000);
				$rs_roDb->enableExceptions(true);

				// Bounded total (only on a new query submission). Counts at most
				// cap+1 rows so an oversized result is detected without scanning
				// unbounded data.
				if ($rs_wantCount) {
					$rs_countRes = $rs_roDb->query($rs_countSql);
					if ($rs_countRes === false) {
						$rs_error = 'the submitted query failed to execute';
					} else {
						$rs_countRow = $rs_countRes->fetchArray(SQLITE3_NUM);
						$rs_countRes->finalize();
						$rs_rawCount = ($rs_countRow !== false) ? (int)$rs_countRow[0] : 0;
						$rs_truncated = ($rs_rawCount > $rs_cap);
						$rs_total = $rs_truncated ? $rs_cap : $rs_rawCount;
					}
				}

				// Fetch the requested page. LIMIT 0 (page beyond the cap) still
				// yields the column names for a consistent header (Requirement 5.1).
				if ($rs_error === null) {
					$rs_result = $rs_roDb->query($rs_dataSql);
					if ($rs_result === false) {
						$rs_error = 'the submitted query failed to execute';
					} else {
						$rs_numCols = $rs_result->numColumns();
						for ($c = 0; $c < $rs_numCols; $c++) {
							$rs_columns[] = $rs_result->columnName($c);
						}
						while (($row = $rs_result->fetchArray(SQLITE3_NUM)) !== false) {
							if ((microtime(true) - $rs_start) > $rs_limit_s) { $rs_timeout = true; break; }
							$rs_rows[] = $row;
						}
						$rs_result->finalize();
					}
				}
			} catch (Exception $e) {
				// Syntactically invalid or runtime failure (Requirement 4.7):
				// return a descriptive error and no partial data.
				$rs_error = $e->getMessage();
			}

			if ($rs_roDb !== null) {
				$rs_roDb->close();
			}

			if ($rs_timeout) {
				// Terminated by the execution bound: timeout error, no partial
				// results presented as complete (Requirement 4.10).
				$response='{"status":"error","reason":"query exceeded the '.$rs_limit_s.'-second execution limit"}';
			} else if ($rs_error !== null) {
				// Runtime/syntax error: descriptive error, DB unchanged, no
				// partial-as-complete (Requirement 4.7).
				$response='{"status":"error","reason":'.json_encode($rs_error).'}';
			} else {
				// Success: SqlResult {columns, rows, rowCount, truncated}
				// (Requirement 4.6, design SqlResult model).
				// Success: one page of the paginated SqlResult. `totalRows` /
				// `truncated` are present only when a bounded count was requested
				// (a new query); page navigation omits them and the client reuses
				// its cached total.
				$rs_payload = [
					'columns'  => $rs_columns,
					'rows'     => $rs_rows,
					'rowCount' => count($rs_rows),
					'page'     => $rs_cp,
					'perPage'  => $rs_pp,
				];
				if ($rs_total !== null) {
					$rs_payload['totalRows'] = $rs_total;
					$rs_payload['truncated'] = $rs_truncated;
				}
				$response='{"status":"ok","data":'.json_encode($rs_payload).'}';
			}
      break;

    case "POST research_tool":
			// Research: execute a network research tool (RDAP/WHOIS, dig, ping,
			// traceroute) against a validated target and return its ToolResult.
			// Auth guard runs first, before any validation or command execution
			// (Requirements 1.7, 9.1, 9.4).
			require_once "/opt/rpidns/www/rpi_admin/ResearchAuth.php";
			require_once "/opt/rpidns/www/rpi_admin/InputValidator.php";
			require_once "/opt/rpidns/www/rpi_admin/CommandBuilder.php";
			require_once "/opt/rpidns/www/rpi_admin/ToolRunner.php";
			require_once "/opt/rpidns/www/rpi_admin/RejectionAudit.php";
			require_once "/opt/rpidns/www/rpi_admin/ResearchFormatter.php";
			$rtl_user = requireResearchSession();
			$rtl_sid = (is_array($rtl_user) and array_key_exists('session_id',$rtl_user)) ? $rtl_user['session_id'] : '';

			// Inputs are merged into $REQUEST from the JSON body by getRequest().
			$rtl_tool   = array_key_exists("tool",$REQUEST)   ? (string)$REQUEST["tool"]   : '';
			$rtl_target = array_key_exists("target",$REQUEST) ? (string)$REQUEST["target"] : '';
			$rtl_dns    = (array_key_exists("dns_server",$REQUEST) and $REQUEST["dns_server"] !== null and $REQUEST["dns_server"] !== '')
				? (string)$REQUEST["dns_server"] : null;

			// Feature flag for the website-preview tool (Req 8.7). Disabled by
			// default because it requires a headless chromium binary in the web
			// container. Define it as true in configuration (e.g. rpisettings.php)
			// once chromium is installed to enable server-side screenshots.
			if (!defined('RESEARCH_WEBSITE_PREVIEW')) {
				define('RESEARCH_WEBSITE_PREVIEW', false);
			}

			// Additional bulk request inputs. `items` is the bulk target list and
			// may arrive either as a native array (JSON body) or as a JSON string
			// (form-encoded); normalize both to an array. `subtool` names the
			// per-item single-command tool to run for a bulk request.
			$rtl_items = array_key_exists("items",$REQUEST) ? $REQUEST["items"] : array();
			if (is_string($rtl_items)) {
				$rtl_decoded = json_decode($rtl_items, true);
				$rtl_items = is_array($rtl_decoded) ? $rtl_decoded : array();
			}
			if (!is_array($rtl_items)) { $rtl_items = array(); }
			$rtl_subtool = (array_key_exists("subtool",$REQUEST) and $REQUEST["subtool"] !== null and $REQUEST["subtool"] !== '')
				? (string)$REQUEST["subtool"] : 'rdap';

			// Tool allowlist. Core single-command tools plus the additional
			// threat-hunting tools (reverse_dns, nsmx, geoip, asn, tls_cert,
			// reputation, website_preview, bulk). Only allowlisted tools may run.
			$rtl_allowlist = array(
				'rdap','dig','ping','traceroute',
				'reverse_dns','nsmx','geoip','asn',
				'tls_cert','reputation','website_preview','bulk'
			);

			// Tool input-shape classes used for per-tool validation below.
			// IP-only tools reject anything that is not a valid IPv4/IPv6 address;
			// domain-only tools reject anything that is not a valid hostname.
			$rtl_ip_tools     = array('reverse_dns','geoip','asn');
			$rtl_domain_tools = array('nsmx','tls_cert','reputation','website_preview');
			// A bulk sub-tool must itself be one of the single-command tools that
			// take a domain-or-IP target (nsmx is multi-command, website_preview is
			// image-producing, bulk cannot nest).
			$rtl_bulk_subtool_allowlist = array(
				'rdap','dig','ping','traceroute',
				'reverse_dns','geoip','asn','tls_cert','reputation'
			);

			if (!in_array($rtl_tool, $rtl_allowlist, true)) {
				// Unknown/unsupported tool: audit and reject before any execution
				// (Requirements 6.5, 9.5). No command is ever built or run.
				RejectionAudit::record($rtl_sid, 'invalid_input', 'research_tool');
				$response='{"status":"error","reason":"unknown or unsupported tool"}';
				break;
			}

			// Validate the input BEFORE execution (Requirements 6.5, 8.10, 8.12,
			// 9.4, 9.5). Each tool class enforces its required input format and
			// rejects with the matching audit category; nothing is built or run on
			// a validation failure.
			if (in_array($rtl_tool, $rtl_ip_tools, true)) {
				// reverse_dns, geoip, asn require a valid IP address (Req 8.1, 8.3, 8.4).
				if (!InputValidator::isValidIp($rtl_target)) {
					RejectionAudit::record($rtl_sid, 'invalid_ip', 'research_tool');
					$response='{"status":"error","reason":"invalid target: must be an IP address"}';
					break;
				}
			} elseif (in_array($rtl_tool, $rtl_domain_tools, true)) {
				// nsmx, tls_cert, reputation, website_preview require a valid domain
				// (Req 8.2, 8.5, 8.6, 8.7).
				if (!InputValidator::isValidDomain($rtl_target)) {
					RejectionAudit::record($rtl_sid, 'invalid_domain', 'research_tool');
					$response='{"status":"error","reason":"invalid target: must be a domain name"}';
					break;
				}
			} elseif ($rtl_tool === 'bulk') {
				// Bulk: reject lists that exceed 100 items or contain a malformed
				// item (Req 8.8, 8.9). isValidBulkList covers both size and items.
				if (!InputValidator::isValidBulkList($rtl_items)) {
					RejectionAudit::record($rtl_sid, 'bulk_too_large', 'research_tool');
					$response='{"status":"error","reason":"invalid bulk list: at most 100 valid domain or IP items are permitted"}';
					break;
				}
				// The per-item sub-tool must be a recognized single-command tool.
				if (!in_array($rtl_subtool, $rtl_bulk_subtool_allowlist, true)) {
					RejectionAudit::record($rtl_sid, 'invalid_input', 'research_tool');
					$response='{"status":"error","reason":"invalid bulk sub-tool"}';
					break;
				}
			} else {
				// Core domain-or-IP tools: rdap, dig, ping, traceroute (Req 6.5).
				if (!InputValidator::isDomainOrIp($rtl_target)) {
					RejectionAudit::record($rtl_sid, 'invalid_input', 'research_tool');
					$response='{"status":"error","reason":"invalid target: must be a domain name or IP address"}';
					break;
				}
			}

			// For dig with a user-supplied DNS server, validate it as an IP or
			// hostname before execution (Requirement 6.4). Only the dig-based
			// tools (dig, nsmx, reverse_dns) honor a custom DNS server; the other
			// tools ignore dns_server.
			$rtl_dns_aware = array('dig','nsmx','reverse_dns');
			if (in_array($rtl_tool, $rtl_dns_aware, true) and $rtl_dns !== null and !InputValidator::isValidDnsServer($rtl_dns)) {
				RejectionAudit::record($rtl_sid, 'invalid_dns_server', 'research_tool');
				$response='{"status":"error","reason":"invalid dns_server: must be a valid IP address or hostname"}';
				break;
			}

			// All inputs validated: build the argument vector(s) and execute. The
			// CommandBuilder passes user input as discrete argv slots (no shell),
			// and ToolRunner enforces the 30s wall-clock bound, bounds ping/
			// traceroute probes, truncates output, and surfaces tool_start_failed,
			// non-zero exit, and timeout in the ToolResult. Neither modifies DB or
			// system state (Requirements 6.2, 6.6, 6.7, 6.8, 6.9, 8.11, 8.12, 9.3).
			$rtl_params = array('target' => $rtl_target);
			if (in_array($rtl_tool, $rtl_dns_aware, true) and $rtl_dns !== null) {
				$rtl_params['dns_server'] = $rtl_dns;
			}

			try {
				$rtl_builder = new CommandBuilder();

				if ($rtl_tool === 'website_preview') {
					// Website preview is gated behind a feature flag (Req 8.7).
					if (!RESEARCH_WEBSITE_PREVIEW) {
						// Disabled: never execute chromium; report no preview.
						$response='{"status":"ok","data":'.json_encode(array('image'=>null,'reason'=>'no preview available')).'}';
					} else {
						// Server-generated temp output path (never user input).
						$rtl_tmp = tempnam(sys_get_temp_dir(), 'rpidns_preview_');
						$rtl_png = $rtl_tmp . '.png';
						$rtl_image = null;
						$rtl_reason = null;
						try {
							$rtl_cmds = $rtl_builder->build('website_preview', array('target'=>$rtl_target, 'output_path'=>$rtl_png));
							$rtl_runner = new ToolRunner();
							$rtl_result = $rtl_runner->run('website_preview', $rtl_target, $rtl_cmds[0]);
							if (!$rtl_result['exitError'] and is_file($rtl_png) and filesize($rtl_png) > 0) {
								$rtl_bytes = @file_get_contents($rtl_png);
								if ($rtl_bytes !== false and $rtl_bytes !== '') {
									$rtl_image = base64_encode($rtl_bytes);
								} else {
									// Screenshot could not be read: report gracefully (Req 8.11).
									$rtl_reason = 'no preview available';
								}
							} else {
								// chromium failed/timed out: surface without partial data (Req 8.11).
								$rtl_reason = ($rtl_result['reason'] !== null) ? $rtl_result['reason'] : 'no preview available';
							}
						} catch (Exception $e) {
							$rtl_reason = 'no preview available';
						}
						// Always clean up the server-side temp files.
						if (is_file($rtl_png)) { @unlink($rtl_png); }
						if (is_file($rtl_tmp)) { @unlink($rtl_tmp); }
						$response='{"status":"ok","data":'.json_encode(array('image'=>$rtl_image,'reason'=>$rtl_reason)).'}';
					}
				} elseif ($rtl_tool === 'nsmx') {
					// NS/MX enumeration builds two dig commands; run them under one
					// shared budget and return the combined ToolResult (Req 8.2).
					$rtl_cmds = $rtl_builder->build('nsmx', $rtl_params);
					$rtl_runner = new ToolRunner();
					$rtl_result = $rtl_runner->runMany('nsmx', $rtl_target, $rtl_cmds);
					$response='{"status":"ok","data":'.json_encode($rtl_result).'}';
				} elseif ($rtl_tool === 'bulk') {
					// Bulk analysis: one result per item, in submitted order (Req 8.8).
					// Share the overall 30s wall-clock bound across items so the
					// aggregate self-terminates (Req 6.7 / 8.12).
					$rtl_items = array_values($rtl_items);
					$rtl_count = count($rtl_items);
					$rtl_bulk_out = array();
					$rtl_deadline = microtime(true) + ToolRunner::DEFAULT_TIMEOUT_SEC;
					foreach ($rtl_items as $rtl_idx => $rtl_item) {
						$rtl_item = (string)$rtl_item;
						$rtl_remaining = $rtl_deadline - microtime(true);
						$rtl_left = $rtl_count - $rtl_idx;
						$rtl_per = ($rtl_remaining > 0 and $rtl_left > 0) ? max(1, (int)floor($rtl_remaining / $rtl_left)) : 1;
						try {
							$rtl_built = $rtl_builder->build($rtl_subtool, array('target'=>$rtl_item));
							$rtl_item_runner = new ToolRunner($rtl_per);
							if (count($rtl_built) > 1) {
								$rtl_item_result = $rtl_item_runner->runMany($rtl_subtool, $rtl_item, $rtl_built);
							} else {
								$rtl_item_result = $rtl_item_runner->run($rtl_subtool, $rtl_item, $rtl_built[0]);
							}
						} catch (Exception $e) {
							// Per-item build/start failure: record a failed result for
							// this item and continue; never present partial as complete.
							$rtl_item_result = array(
								'tool'=>$rtl_subtool,'target'=>$rtl_item,'output'=>'',
								'truncated'=>false,'exitError'=>true,'reason'=>'tool_start_failed'
							);
						}
						// Format each item's output for readability (JSON tools).
						if (is_array($rtl_item_result) and isset($rtl_item_result['output'])) {
							$rtl_item_result['output'] = ResearchFormatter::format($rtl_subtool, $rtl_item_result['output']);
						}
						$rtl_bulk_out[] = array('target'=>$rtl_item, 'result'=>$rtl_item_result);
					}
					$response='{"status":"ok","data":'.json_encode(array('items'=>$rtl_bulk_out)).'}';
				} else {
					// Single-command tools: rdap, dig, ping, traceroute, reverse_dns,
					// geoip, asn, tls_cert, reputation. Build then run the first argv.
					$rtl_cmds = $rtl_builder->build($rtl_tool, $rtl_params);
					$rtl_runner = new ToolRunner();
					$rtl_result = $rtl_runner->run($rtl_tool, $rtl_target, $rtl_cmds[0]);
					// Render JSON-producing tools (geoip, asn, rdap, reputation)
					// in a human-readable form; text tools/errors pass through.
					$rtl_result['output'] = ResearchFormatter::format($rtl_tool, $rtl_result['output']);
					$response='{"status":"ok","data":'.json_encode($rtl_result).'}';
				}
			} catch (Exception $e) {
				// A build/start failure is surfaced without changing system state
				// (Requirement 6.9).
				$response='{"status":"error","reason":"tool_start_failed"}';
			}
      break;

		case "GET dash_topX_req":
			if ($REQUEST["period"] === 'custom') {
				// Custom period: use absolute timestamps
				if ($period <= 86400)
					$sql="select fqdn as fname, count(rowid) as cnt from queries_raw where dt>=$start_dt and dt<=$end_dt and action='allowed' group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select fqdn as fname, count(rowid) as cnt2 from queries_raw where dt>ifnull((select max(dt) from queries_1d),0) and dt>=$start_dt and dt<=$end_dt and action='allowed' group by fqdn order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select fqdn as fname, sum(cnt) as cnt2 from queries_1d where dt>=$start_dt and dt<=$end_dt and action='allowed' group by fqdn order by cnt2 desc limit $dash_topx)
				) group by fname order by cnt desc limit $dash_topx
				";
			} else {
				// Predefined periods: use relative time
				if ($period<=86400)
					$sql="select fqdn as fname, count(rowid) as cnt from queries_raw where dt>=strftime('%s', 'now')-$period and action='allowed' group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select fqdn as fname, count(rowid) as cnt2 from queries_raw where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 and action='allowed' group by fqdn  order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select fqdn as fname, sum(cnt) as cnt2 from queries_1d where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period and action='allowed' group by fqdn order by cnt2 desc limit $dash_topx)
				)  group by fname order by cnt desc limit $dash_topx
				";
			}
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;

		case "GET dash_topX_server":
			if ($REQUEST["period"] === 'custom') {
				// Custom period: use absolute timestamps
				if ($period <= 86400)
					$sql="select server as fname, count(rowid) as cnt from queries_raw where dt>=$start_dt and dt<=$end_dt and action='allowed' group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select server as fname, count(rowid) as cnt2 from queries_raw where dt>ifnull((select max(dt) from queries_1d),0) and dt>=$start_dt and dt<=$end_dt and action='allowed' group by fname order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select server as fname, sum(cnt) as cnt2 from queries_1d where dt>=$start_dt and dt<=$end_dt and action='allowed' group by fname order by cnt2 desc limit $dash_topx)
				) group by fname order by cnt desc limit $dash_topx
				";
			} else {
				// Predefined periods: use relative time
				if ($period<=86400)
					$sql="select server as fname, count(rowid) as cnt from queries_raw where dt>=strftime('%s', 'now')-$period and action='allowed' group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select server as fname, count(rowid) as cnt2 from queries_raw where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 and action='allowed' group by fname order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select server as fname, sum(cnt) as cnt2 from queries_1d where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period and action='allowed' group by fname order by cnt2 desc limit $dash_topx)
				)  group by fname order by cnt desc limit $dash_topx
				";
			}
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;

		case "GET dash_topX_req_type":
			if ($REQUEST["period"] === 'custom') {
				// Custom period: use absolute timestamps
				if ($period <= 86400)
					$sql="select type as fname, count(rowid) as cnt from queries_raw where dt>=$start_dt and dt<=$end_dt and action='allowed' group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select type as fname, count(rowid) as cnt2 from queries_raw where dt>ifnull((select max(dt) from queries_1d),0) and dt>=$start_dt and dt<=$end_dt and action='allowed' group by fname order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select type as fname, sum(cnt) as cnt2 from queries_1d where dt>=$start_dt and dt<=$end_dt and action='allowed' group by fname order by cnt2 desc limit $dash_topx)
				) group by fname order by cnt desc limit $dash_topx
				";
			} else {
				// Predefined periods: use relative time
				if ($period<=86400)
					$sql="select type as fname, count(rowid) as cnt from queries_raw where dt>=strftime('%s', 'now')-$period and action='allowed' group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select type as fname, count(rowid) as cnt2 from queries_raw where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 and action='allowed' group by fname order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select type as fname, sum(cnt) as cnt2 from queries_1d where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period and action='allowed' group by fname order by cnt2 desc limit $dash_topx)
				)  group by fname order by cnt desc limit $dash_topx
				";
			}
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET dash_topX_client":
			$join=$assets_by=="mac"?"mac":"client_ip";
			if ($REQUEST["period"] === 'custom') {
				// Custom period: use absolute timestamps
				if ($period <= 86400)
					$sql="select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as fname, count(qr.rowid) as cnt, mac from queries_raw qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt and action='allowed' group by fname, mac order by cnt desc limit $dash_topx";
				else $sql="
				select cname as fname, mac, sum(cnt2) as cnt from (
					select cname, mac, cnt2 from (select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as cname, count(qr.rowid) as cnt2, mac from queries_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from queries_1d),0) and dt>=$start_dt and dt<=$end_dt and action='allowed' group by cname, mac order by cnt2 desc limit $dash_topx)
				union
					select cname, mac, cnt2 from (select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as cname, sum(qr.cnt) as cnt2, mac from queries_1d qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt and action='allowed' group by cname, mac order by cnt2 desc limit $dash_topx)
				) group by fname, mac order by cnt desc limit $dash_topx
				";
			} else {
				// Predefined periods: use relative time
				if ($period<=86400)
					$sql="select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as fname, count(qr.rowid) as cnt, mac from queries_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period and action='allowed' group by fname, mac order by cnt desc limit $dash_topx";
				else $sql="
				select cname as fname, mac, sum(cnt2) as cnt from (
					select cname, mac, cnt2 from (select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as cname, count(qr.rowid) as cnt2, mac from queries_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 and action='allowed' group by cname, mac order by cnt2 desc limit $dash_topx)
				union
					select cname, mac, cnt2 from (select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as cname, sum(qr.cnt) as cnt2, mac from queries_1d qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period and action='allowed' group by cname, mac order by cnt2 desc limit $dash_topx)
				)  group by fname, mac order by cnt desc limit $dash_topx
				";
			}
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET dash_topX_breq":
			if ($REQUEST["period"] === 'custom') {
				// Custom period: use absolute timestamps
				if ($period <= 86400)
					$sql="select fqdn as fname, count(rowid) as cnt from hits_raw where dt>=$start_dt and dt<=$end_dt group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select fqdn as fname, count(rowid) as cnt2 from hits_raw where dt>ifnull((select max(dt) from hits_1d),0) and dt>=$start_dt and dt<=$end_dt group by fname order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select fqdn as fname, sum(cnt) as cnt2 from hits_1d where dt>=$start_dt and dt<=$end_dt group by fname order by cnt2 desc limit $dash_topx)
				) group by fname order by cnt desc limit $dash_topx
				";
			} else {
				// Predefined periods: use relative time
				if ($period<=86400)
					$sql="select fqdn as fname, count(rowid) as cnt from hits_raw where dt>=strftime('%s', 'now')-$period group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select fqdn as fname, count(rowid) as cnt2 from hits_raw where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 group by fname order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select fqdn as fname, sum(cnt) as cnt2 from hits_1d where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period group by fname order by cnt2 desc limit $dash_topx)
				)  group by fname order by cnt desc limit $dash_topx
				";
			}
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET dash_topX_bclient":
			$join=$assets_by=="mac"?"mac":"client_ip";
			if ($REQUEST["period"] === 'custom') {
				// Custom period: use absolute timestamps
				if ($period <= 86400)
					$sql="select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as fname, count(qr.rowid) as cnt, mac from hits_raw qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt group by fname, mac order by cnt desc limit $dash_topx";
				else $sql="
				select fname, mac, sum(cnt2) as cnt from (
					select fname, mac, cnt2 from (select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as fname, count(qr.rowid) as cnt2, mac from hits_raw qr left join assets a on qr.$join=a.address where dt>ifnull((select max(dt) from hits_1d),0) and dt>=$start_dt and dt<=$end_dt group by fname, mac order by cnt2 desc limit $dash_topx)
				union
					select fname, mac, cnt2 from (select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as fname, sum(qr.cnt) as cnt2, mac from hits_1d qr left join assets a on qr.$join=a.address where dt>=$start_dt and dt<=$end_dt group by fname, mac order by cnt2 desc limit $dash_topx)
				) group by fname, mac order by cnt desc limit $dash_topx
				";
			} else {
				// Predefined periods: use relative time
				if ($period<=86400)
					$sql="select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as fname, count(qr.rowid) as cnt, mac from hits_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-$period group by fname, mac order by cnt desc limit $dash_topx";
				else $sql="
				select fname, mac, sum(cnt2) as cnt from (
					select fname, mac, cnt2 from (select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as fname, count(qr.rowid) as cnt2, mac from hits_raw qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 group by fname, mac order by cnt2 desc limit $dash_topx)
				union
					select fname, mac, cnt2 from (select ifnull(a.name,ifnull(nullif(mac,''),client_ip)) as fname, sum(qr.cnt) as cnt2, mac from hits_1d qr left join assets a on qr.$join=a.address where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period group by fname, mac order by cnt2 desc limit $dash_topx)
				)  group by fname,mac order by cnt desc limit $dash_topx
				";
			}
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET dash_topX_feeds":
			if ($REQUEST["period"] === 'custom') {
				// Custom period: use absolute timestamps
				if ($period <= 86400)
					$sql="select feed as fname, count(rowid) as cnt from hits_raw where dt>=$start_dt and dt<=$end_dt group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select feed as fname, count(rowid) as cnt2 from hits_raw where dt>ifnull((select max(dt) from hits_1d),0) and dt>=$start_dt and dt<=$end_dt group by fname order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select feed as fname, sum(cnt) as cnt2 from hits_1d where dt>=$start_dt and dt<=$end_dt group by fname order by cnt2 desc limit $dash_topx)
				) group by fname order by cnt desc limit $dash_topx
				";
			} else {
				// Predefined periods: use relative time
				if ($period<=86400)
					$sql="select feed as fname, count(rowid) as cnt from hits_raw where dt>=strftime('%s', 'now')-$period group by fname order by cnt desc limit $dash_topx";
				else $sql="
				select fname, sum(cnt2) as cnt from (
					select fname, cnt2 from (select feed as fname, count(rowid) as cnt2 from hits_raw where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400 group by fname order by cnt2 desc limit $dash_topx)
				union
					select fname, cnt2 from (select feed as fname, sum(cnt) as cnt2 from hits_1d where dt>=strftime('%s', 'now')-strftime('%s', 'now')%86400-$period group by fname order by cnt2 desc limit $dash_topx)
				)  group by fname order by cnt desc limit $dash_topx
				";
			}
			$response='{"status":"ok","data":'.json_encode(DB_selectArray($db,$sql)).'}';
			break;
		case "GET qps_chart":
			if ($retention['queries_5m']>=30) {$tbl="5m";$div=5;} else {$tbl="1h";$div=60;};
			if ($REQUEST["period"] === 'custom') {
				// Custom period: use absolute timestamps
				if ($period<=86400) //we need queries per minute and show max QPM, to make it accurate we need max/min per minute
					$sql="$qps_pref select (dt - dt % 60) as dtz, count(rowid) as cnt from queries_raw where dt>=$start_dt and dt<=$end_dt group by dtz $qps_post";
				else $sql="
				$qps_pref select dtz, sum(cnt2) as cnt from (
					select (dt - dt % 60) as dtz, count(rowid) as cnt2 from queries_raw where dt>ifnull((select max(dt) from queries_$tbl),0) and dt>=$start_dt and dt<=$end_dt group by dtz
				union
					select dt as dtz, sum(cnt)/$div as cnt2 from queries_$tbl where dt>=$start_dt and dt<=$end_dt group by dtz
				) group by dtz $qps_post
				";
				$qps=array();
				foreach(DB_selectArrayNum($db,$sql) as $rec){
					$qps[]=[$rec[0]*1000,$rec[1]];
				};
				if ($period<=86400) //we need queries per minute and show max QPM, to make it accurate we need max/min per minute
					$sql="$qps_pref select (dt - dt % 60) as dtz, count(rowid) as cnt from hits_raw where dt>=$start_dt and dt<=$end_dt group by dtz $qps_post";
				else $sql="
				$qps_pref select dtz, sum(cnt2) as cnt from (
					select (dt - dt % 60) as dtz, count(rowid) as cnt2 from hits_raw where dt>ifnull((select max(dt) from hits_$tbl),0) and dt>=$start_dt and dt<=$end_dt group by dtz
				union
					select (dt - dt % 60) as dtz, sum(cnt)/5 as cnt2 from hits_5m where dt>=$start_dt and dt<=$end_dt group by dtz
				) group by dtz $qps_post
				";
				$hits=array();
				foreach(DB_selectArrayNum($db,$sql) as $rec){
					$hits[]=[$rec[0]*1000,$rec[1]];
				};
			} else {
				// Predefined periods: use relative time
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
			}
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
			$list=$REQUEST["req"]=='blacklist'?'block':'allow';
			$sql="select rowid, strftime('%Y-%m-%dT%H:%M:%SZ',added_dt, 'unixepoch', 'utc') as dtz, ioc, comment, subdomains, active, expires_dt from localzone where ltype='$list';";
			$sql_count="select count(rowid) as cnt from localzone where ltype='$list';";
			$response='{"status":"ok", "records":"'.(DB_fetchRecord($db,$sql_count)['cnt']).'","data":'.json_encode(DB_selectArray($db,$sql)).'}';
      break;

    case "POST blacklist":
    case "POST whitelist":
			$list=$REQUEST["req"]=='blacklist'?'block':'allow';
			$ioc=filter_var($REQUEST['ioc'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
			$expires=isset($REQUEST['expires_dt'])?intval($REQUEST['expires_dt']):0;
      $sql="insert into localzone(ioc, active, subdomains, comment, added_dt, expires_dt, ltype) values('".DB_escape($db,$ioc)."',".($REQUEST['active']=='true'?'true':'false').",".($REQUEST['subdomains']=='true'?'true':'false').",'".DB_escape($db,$REQUEST['comment'])."',".time().",".$expires.",'$list')";
      if (DB_execute($db,$sql)) {
				$out=[];
				if ($REQUEST['active']=='true') {if ($REQUEST['subdomains']=='true') exec('printf "server '.$bind_host.'\nupdate add '.$ioc.'.'.$list.'.ioc2rpz.rpidns 60 CNAME .\nupdate add *.'.$ioc.'.'.$list.'.ioc2rpz.rpidns 60 CNAME .\nsend\n"| /usr/bin/nsupdate -d -v',$out); else exec('printf "server '.$bind_host.'\nupdate add '.$ioc.'.'.$list.'.ioc2rpz.rpidns 60 CNAME .\nsend\n" | /usr/bin/nsupdate -d -v',$out);};
				$response='{"status":"success","details":'.json_encode($out).'}';
			} else $response='{"status":"failed", "reason":"'.DB_lasterror($db).'"}';
			break;

    case "PUT blacklist":
    case "PUT whitelist":
			$list=$REQUEST["req"]=='blacklist'?'block':'allow';
			$rec=DB_fetchRecord($db,"select ioc,active,subdomains,expires_dt from localzone where rowid=".intval($REQUEST['id']));
			$ioc=filter_var($REQUEST['ioc'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
			$expires=isset($REQUEST['expires_dt'])?intval($REQUEST['expires_dt']):intval($rec['expires_dt']);
      $sql="update localzone set ioc='".DB_escape($db,$ioc)."', active=".($REQUEST['active']=='true'?'true':'false').", subdomains=".($REQUEST['subdomains']=='true'?'true':'false').", comment='".DB_escape($db,$REQUEST['comment'])."', expires_dt=".$expires." where rowid=".intval($REQUEST['id']);
      if (DB_execute($db,$sql)) {
				$response='{"status":"success"}';
				$out=[];
				if (($rec['active']=='1' and $REQUEST['active']!='true') or ($ioc != $rec['active'])) exec('printf "server '.$bind_host.'\nupdate delete '.$rec['ioc'].'.'.$list.'.ioc2rpz.rpidns 60 CNAME .\nupdate delete *.'.$rec['ioc'].'.'.$list.'.ioc2rpz.rpidns 60 CNAME .\nsend\n" | /usr/bin/nsupdate -d -v',$out);
				if (($rec['subdomains']=='1' and $REQUEST['subdomains']!='true')) exec('printf "server '.$bind_host.'\nupdate delete *.'.$rec['ioc'].'.'.$list.'.ioc2rpz.rpidns 60 CNAME .\nsend\n" | /usr/bin/nsupdate -d -v',$out);
				if ($REQUEST['active']=='true') {if ($REQUEST['subdomains']=='true') exec('printf "server '.$bind_host.'\nupdate add '.$ioc.'.'.$list.'.ioc2rpz.rpidns 60 CNAME .\nupdate add *.'.$ioc.'.'.$list.'.ioc2rpz.rpidns 60 CNAME .\nsend\n"| /usr/bin/nsupdate -d -v',$out); else exec('printf "server '.$bind_host.'\nupdate add '.$ioc.'.'.$list.'.ioc2rpz.rpidns 60 CNAME .\nsend\n" | /usr/bin/nsupdate -d -v',$out);};
			} else $response='{"status":"failed", "reason":"'.DB_lasterror($db).'"}';
			break;

    case "DELETE blacklist":
    case "DELETE whitelist":
			$list=$REQUEST["req"]=='blacklist'?'block':'allow';
			$ioc=DB_fetchRecord($db,"select ioc from localzone where rowid=".intval($REQUEST['id']))['ioc'];
			$sql="delete from localzone where rowid=".intval($REQUEST['id']);
      if (DB_execute($db,$sql)) {
				$out=[];
				exec('printf "server '.$bind_host.'\nupdate delete '.$ioc.'.'.$list.'.ioc2rpz.rpidns 60 CNAME .\nupdate delete *.'.$ioc.'.'.$list.'.ioc2rpz.rpidns 60 CNAME .\nsend\n" | /usr/bin/nsupdate -d -v',$out);
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
			// Enhanced endpoint using BindConfigManager for full metadata
			require_once __DIR__ . '/BindConfigManager.php';
			try {
				$bindManager = new BindConfigManager();
				$feeds = $bindManager->getFeeds();
				$response = json_encode([
					'status' => 'ok',
					'records' => count($feeds),
					'data' => $feeds
				]);
			} catch (Exception $e) {
				$response = json_encode([
					'status' => 'error',
					'reason' => $e->getMessage(),
					'code' => 'CONFIG_PARSE_ERROR'
				]);
			}
		break;

	case "GET ioc2rpz_available":
			// Fetch available feeds from ioc2rpz.net API
			require_once __DIR__ . '/BindConfigManager.php';
			try {
				$bindManager = new BindConfigManager();
				$tsigKeyName = $bindManager->getTsigKeyName();
				
				if ($tsigKeyName === null) {
					$response = json_encode([
						'status' => 'error',
						'reason' => 'No TSIG key configured for ioc2rpz.net',
						'code' => 'TSIG_NOT_FOUND',
						'tsig_key_found' => false
					]);
					break;
				}
				
				// Fetch available feeds from ioc2rpz.net API
				$apiUrl = 'https://ioc2rpz.net/get_feeds.php?tkey_name=' . urlencode($tsigKeyName);
				
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $apiUrl);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
				
				$apiResponse = curl_exec($ch);
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$curlError = curl_error($ch);
				curl_close($ch);
				
				if ($apiResponse === false || $httpCode !== 200) {
					$response = json_encode([
						'status' => 'error',
						'reason' => 'Failed to fetch feeds from ioc2rpz.net: ' . ($curlError ?: "HTTP $httpCode"),
						'code' => 'IOC2RPZ_API_ERROR',
						'tsig_key_found' => true,
						'tsig_key_name' => $tsigKeyName
					]);
					break;
				}
				
				$availableFeeds = json_decode($apiResponse, true);
				if ($availableFeeds === null) {
					$response = json_encode([
						'status' => 'error',
						'reason' => 'Invalid response from ioc2rpz.net API',
						'code' => 'IOC2RPZ_API_ERROR',
						'tsig_key_found' => true,
						'tsig_key_name' => $tsigKeyName
					]);
					break;
				}
				
				// Get currently configured feeds to mark which are already added
				$configuredFeeds = $bindManager->getFeeds();
				$configuredNames = array_column($configuredFeeds, 'feed');
				
				// Mark feeds that are already configured
				foreach ($availableFeeds as &$feed) {
					$feed['already_configured'] = in_array($feed['rpz'] ?? '', $configuredNames);
				}
				
				$response = json_encode([
					'status' => 'ok',
					'tsig_key_found' => true,
					'tsig_key_name' => $tsigKeyName,
					'data' => $availableFeeds
				]);
			} catch (Exception $e) {
				$response = json_encode([
					'status' => 'error',
					'reason' => $e->getMessage(),
					'code' => 'CONFIG_PARSE_ERROR'
				]);
			}
		break;

	case "POST rpz_feed":
			// Add new feed(s)
			require_once __DIR__ . '/BindConfigManager.php';
			try {
				$bindManager = new BindConfigManager();
				
				// Get JSON input
				$input = json_decode(file_get_contents('php://input'), true);
				if ($input === null) {
					$input = $REQUEST;
				}
				
				$feeds = $input['feeds'] ?? [$input];
				
				if (empty($feeds)) {
					$response = json_encode([
						'status' => 'error',
						'reason' => 'No feeds provided',
						'code' => 'INVALID_REQUEST'
					]);
					break;
				}
				
				// Add feeds using BindConfigManager
				$result = $bindManager->addFeeds($feeds);
				
				if (!$result['success']) {
					$response = json_encode([
						'status' => 'error',
						'reason' => $result['error'],
						'code' => 'FEED_ADD_FAILED'
					]);
					break;
				}
				
				// Reload BIND to apply changes
				$reloadResult = $bindManager->reloadBind();
				
				if (!$reloadResult['success']) {
					$response = json_encode([
						'status' => 'warning',
						'reason' => 'Feeds added but BIND reload failed: ' . $reloadResult['error'],
						'added' => $result['added'],
						'details' => 'Configuration saved. Manual BIND reload may be required.'
					]);
					break;
				}
				
				$response = json_encode([
					'status' => 'success',
					'added' => $result['added'],
					'details' => 'Feed(s) added successfully'
				]);
			} catch (Exception $e) {
				$response = json_encode([
					'status' => 'error',
					'reason' => $e->getMessage(),
					'code' => 'FEED_ADD_FAILED'
				]);
			}
		break;

	case "PUT rpz_feed":
			// Update existing feed configuration
			require_once __DIR__ . '/BindConfigManager.php';
			try {
				$bindManager = new BindConfigManager();
				
				// Get JSON input
				$input = json_decode(file_get_contents('php://input'), true);
				if ($input === null) {
					$input = $REQUEST;
				}
				
				$feedName = $input['feed'] ?? '';
				
				if (empty($feedName)) {
					$response = json_encode([
						'status' => 'error',
						'reason' => 'Feed name is required',
						'code' => 'INVALID_REQUEST'
					]);
					break;
				}
				
				// Build config array from input
				$config = [];
				if (isset($input['action'])) $config['action'] = $input['action'];
				if (isset($input['description'])) $config['description'] = $input['description'];
				if (isset($input['cnameTarget'])) $config['cnameTarget'] = $input['cnameTarget'];
				if (isset($input['primaryServer'])) $config['primaryServer'] = $input['primaryServer'];
				if (isset($input['tsigKeyName'])) $config['tsigKeyName'] = $input['tsigKeyName'];
				if (isset($input['tsigAlgorithm'])) $config['tsigAlgorithm'] = $input['tsigAlgorithm'];
				if (isset($input['tsigKeySecret'])) $config['tsigKeySecret'] = $input['tsigKeySecret'];
				
				// Update feed using BindConfigManager
				$result = $bindManager->updateFeed($feedName, $config);
				
				if (!$result['success']) {
					$response = json_encode([
						'status' => 'error',
						'reason' => $result['error'],
						'code' => 'FEED_UPDATE_FAILED'
					]);
					break;
				}
				
				// Reload BIND to apply changes
				$reloadResult = $bindManager->reloadBind();
				
				if (!$reloadResult['success']) {
					$response = json_encode([
						'status' => 'warning',
						'reason' => 'Feed updated but BIND reload failed: ' . $reloadResult['error'],
						'details' => 'Configuration saved. Manual BIND reload may be required.'
					]);
					break;
				}
				
				$response = json_encode([
					'status' => 'success',
					'details' => 'Feed updated successfully'
				]);
			} catch (Exception $e) {
				$response = json_encode([
					'status' => 'error',
					'reason' => $e->getMessage(),
					'code' => 'FEED_UPDATE_FAILED'
				]);
			}
		break;

	case "DELETE rpz_feed":
			// Remove a feed from configuration
			require_once __DIR__ . '/BindConfigManager.php';
			try {
				$bindManager = new BindConfigManager();
				
				$feedName = $REQUEST['feed'] ?? '';
				$deleteZoneFile = ($REQUEST['delete_zone_file'] ?? 'false') === 'true';
				
				if (empty($feedName)) {
					$response = json_encode([
						'status' => 'error',
						'reason' => 'Feed name is required',
						'code' => 'INVALID_REQUEST'
					]);
					break;
				}
				
				// Remove feed using BindConfigManager
				$result = $bindManager->removeFeed($feedName, $deleteZoneFile);
				
				if (!$result['success']) {
					$response = json_encode([
						'status' => 'error',
						'reason' => $result['error'],
						'code' => 'FEED_REMOVE_FAILED'
					]);
					break;
				}
				
				// Reload BIND to apply changes
				$reloadResult = $bindManager->reloadBind();
				
				if (!$reloadResult['success']) {
					$response = json_encode([
						'status' => 'warning',
						'reason' => 'Feed removed but BIND reload failed: ' . $reloadResult['error'],
						'details' => 'Configuration saved. Manual BIND reload may be required.'
					]);
					break;
				}
				
				$response = json_encode([
					'status' => 'success',
					'details' => 'Feed removed successfully'
				]);
			} catch (Exception $e) {
				$response = json_encode([
					'status' => 'error',
					'reason' => $e->getMessage(),
					'code' => 'FEED_REMOVE_FAILED'
				]);
			}
		break;

	case "PUT rpz_feeds_order":
			// Update the order of feeds
			require_once __DIR__ . '/BindConfigManager.php';
			try {
				$bindManager = new BindConfigManager();
				
				// Get JSON input
				$input = json_decode(file_get_contents('php://input'), true);
				if ($input === null) {
					$input = $REQUEST;
				}
				
				$order = $input['order'] ?? [];
				
				if (empty($order) || !is_array($order)) {
					$response = json_encode([
						'status' => 'error',
						'reason' => 'Feed order array is required',
						'code' => 'INVALID_REQUEST'
					]);
					break;
				}
				
				// Update order using BindConfigManager
				$result = $bindManager->updateFeedOrder($order);
				
				if (!$result['success']) {
					$response = json_encode([
						'status' => 'error',
						'reason' => $result['error'],
						'code' => 'ORDER_UPDATE_FAILED'
					]);
					break;
				}
				
				// Reload BIND to apply changes
				$reloadResult = $bindManager->reloadBind();
				
				if (!$reloadResult['success']) {
					$response = json_encode([
						'status' => 'warning',
						'reason' => 'Order updated but BIND reload failed: ' . $reloadResult['error'],
						'details' => 'Configuration saved. Manual BIND reload may be required.'
					]);
					break;
				}
				
				$response = json_encode([
					'status' => 'success',
					'details' => 'Feed order updated'
				]);
			} catch (Exception $e) {
				$response = json_encode([
					'status' => 'error',
					'reason' => $e->getMessage(),
					'code' => 'ORDER_UPDATE_FAILED'
				]);
			}
		break;

	case "PUT rpz_feed_status":
			// Enable or disable a feed
			require_once __DIR__ . '/BindConfigManager.php';
			try {
				$bindManager = new BindConfigManager();
				
				// Get JSON input
				$input = json_decode(file_get_contents('php://input'), true);
				if ($input === null) {
					$input = $REQUEST;
				}
				
				$feedName = $input['feed'] ?? '';
				$enabled = $input['enabled'] ?? null;
				
				if (empty($feedName)) {
					$response = json_encode([
						'status' => 'error',
						'reason' => 'Feed name is required',
						'code' => 'INVALID_REQUEST'
					]);
					break;
				}
				
				if ($enabled === null) {
					$response = json_encode([
						'status' => 'error',
						'reason' => 'Enabled status is required',
						'code' => 'INVALID_REQUEST'
					]);
					break;
				}
				
				// Convert to boolean
				$enabledBool = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
				
				// Update status using BindConfigManager
				$result = $bindManager->setFeedEnabled($feedName, $enabledBool);
				
				if (!$result['success']) {
					$response = json_encode([
						'status' => 'error',
						'reason' => $result['error'],
						'code' => 'STATUS_UPDATE_FAILED'
					]);
					break;
				}
				
				// Reload BIND to apply changes
				$reloadResult = $bindManager->reloadBind();
				
				if (!$reloadResult['success']) {
					$response = json_encode([
						'status' => 'warning',
						'reason' => 'Status updated but BIND reload failed: ' . $reloadResult['error'],
						'details' => 'Configuration saved. Manual BIND reload may be required.'
					]);
					break;
				}
				
				$response = json_encode([
					'status' => 'success',
					'details' => $enabledBool ? 'Feed enabled' : 'Feed disabled'
				]);
			} catch (Exception $e) {
				$response = json_encode([
					'status' => 'error',
					'reason' => $e->getMessage(),
					'code' => 'STATUS_UPDATE_FAILED'
				]);
			}
		break;
		//
	case "PUT retransfer_feed":
		require_once __DIR__ . '/BindConfigManager.php';
		try {
			$bindManager = new BindConfigManager();
			$feedName = $REQUEST['feed'] ?? '';
			
			if (empty($feedName)) {
				$response = json_encode([
					'status' => 'error',
					'reason' => 'Feed name is required',
					'code' => 'INVALID_REQUEST'
				]);
				break;
			}
			
			// Get feed info to check if it's a secondary zone
			$feeds = $bindManager->getFeeds();
			$feedInfo = null;
			foreach ($feeds as $feed) {
				if ($feed['feed'] === $feedName) {
					$feedInfo = $feed;
					break;
				}
			}
			
			if ($feedInfo === null) {
				$response = json_encode([
					'status' => 'error',
					'reason' => 'Feed not found',
					'code' => 'FEED_NOT_FOUND'
				]);
				break;
			}
			
			// Only allow retransfer for secondary zones (ioc2rpz and third-party)
			if ($feedInfo['source'] === 'local') {
				$response = json_encode([
					'status' => 'error',
					'reason' => 'Cannot retransfer local zones. Retransfer is only available for secondary zones.',
					'code' => 'INVALID_ZONE_TYPE'
				]);
				break;
			}
			
			// Request retransfer via rndc
			$result = $bindManager->retransferZone($feedName);
			
			if ($result['success']) {
				$response = json_encode([
					'status' => 'success',
					'details' => 'Zone retransfer requested'
				]);
			} else {
				$response = json_encode([
					'status' => 'error',
					'reason' => $result['error'],
					'code' => 'RETRANSFER_FAILED'
				]);
			}
		} catch (Exception $e) {
			$response = json_encode([
				'status' => 'error',
				'reason' => $e->getMessage(),
				'code' => 'RETRANSFER_FAILED'
			]);
		}
		break;

  case "POST import":
			$import_db_file="";
			$postfix = bin2hex(random_bytes(10));
			if (!file_exists(TMPDir)) {$oldumask=umask(0);mkdir(TMPDir, 0775, true);umask($oldumask);};
			
			// Debug: Log import request details
			$file_type_cmd = "/usr/bin/file ".$_FILES['file']['tmp_name']." | /usr/bin/awk '{print $2}'";
			$detected_type = exec($file_type_cmd);
			$file_type_full = exec("/usr/bin/file ".$_FILES['file']['tmp_name']);
			error_log("[ImportDB] POST import request received");
			error_log("[ImportDB] Uploaded file: " . json_encode($_FILES));
			error_log("[ImportDB] File type command: " . $file_type_cmd);
			error_log("[ImportDB] Detected type (awk): " . $detected_type);
			error_log("[ImportDB] Full file type: " . $file_type_full);
			error_log("[ImportDB] Objects to import: " . $REQUEST['objects']);
			
			switch ($detected_type):
				case "SQLite":
					error_log("[ImportDB] Processing as SQLite file");
					if (move_uploaded_file($_FILES['file']['tmp_name'],TMPDir."/import_db_".$postfix.".sqlite")) $import_db_file=TMPDir."/import_db_".$postfix.".sqlite";
				break;
				case "gzip":
					error_log("[ImportDB] Processing as gzip file");
					$gzip_cmd = "gzip -dc ".$_FILES['file']['tmp_name']. " > ".TMPDir."/import_db_".$postfix.".sqlite";
					error_log("[ImportDB] Gzip command: " . $gzip_cmd);
					exec($gzip_cmd);
					$extracted_type = exec("/usr/bin/file ".TMPDir."/import_db_".$postfix.".sqlite"." | /usr/bin/awk '{print $2}'");
					error_log("[ImportDB] Extracted file type: " . $extracted_type);
					$import_db_file=$extracted_type=="SQLite"?TMPDir."/import_db_".$postfix.".sqlite":"";
				break;
				case "Zip":
					error_log("[ImportDB] Processing as Zip file");
					exec("unzip -p ".$_FILES['file']['tmp_name']. ">".TMPDir."/import_db_".$postfix.".sqlite");
					$extracted_type = exec("/usr/bin/file ".TMPDir."/import_db_".$postfix.".sqlite"." | /usr/bin/awk '{print $2}'");
					error_log("[ImportDB] Extracted file type: " . $extracted_type);
					$import_db_file=$extracted_type=="SQLite"?TMPDir."/import_db_".$postfix.".sqlite":"";
				break;
				default:
					error_log("[ImportDB] Unknown file type: " . $detected_type . " - not matching SQLite, gzip, or Zip");
			endswitch;
			if ($import_db_file!=""){
				chmod(TMPDir."/import_db_".$postfix.".sqlite",0660);
				file_put_contents(TMPDir."/rpidns_import_ready",TMPDir."/import_db_".$postfix.".sqlite"."|".$REQUEST['objects']);
				chmod(TMPDir."/rpidns_import_ready",0660);
				error_log("[ImportDB] Import started successfully: " . TMPDir."/import_db_".$postfix.".sqlite");
				$response='{"status":"success","details":"import started","file_data":'.json_encode($_FILES).',"debug":"'.TMPDir."/import_db_".$postfix.'.sqlite|'.$REQUEST['objects'].'"}';
			} else {
				error_log("[ImportDB] Import failed - bad file. Detected type was: " . $detected_type);
				$response='{"status":"error","details":"bad file","file_data":'.json_encode($_FILES).'}';
			}
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
