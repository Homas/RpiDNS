<?php

	$root_dir="/opt/rpidns";
  require_once "$root_dir/www/rpidns_vars.php";
	$logs="$root_dir/logs/*_queries.log";

	$qlog_files=[];
	foreach (glob($logs) as $qfn) { #*_queries.log
			$qlog_files[$qfn]="$qfn.pos";
			echo "file $qfn\n";
	};
	
	//set PID file and check PID
	if (file_exists( $root_dir."/logs/rpidns_parser.pid" )){
		$pid=file_get_contents($root_dir."/logs/rpidns_parser.pid");
		if (posix_getpgid($pid)) {echo "Long running process $pid\n";exit;};
		echo "rpidns_parser pid: $pid died\n";
	};
	file_put_contents($root_dir."/logs/rpidns_parser.pid",getmypid());
	
	$qlogs=array();
	$hits=array();
	$hits_unique=array();

	foreach ($qlog_files as $qlog=>$fpos){
		if (file_exists($fpos)) {$pos=intval(file_get_contents($fpos));}else{$pos=0;};
		$reset_pos=0;
		if (filesize($qlog)<$pos){$qlog=file_exists("$qlog.0")?"$qlog.0":"$qlog.1";$reset_pos=1;}; //file was rotated

		$fh=fopen("$qlog","r");
		fseek($fh,$pos);
		while($line = (fgets($fh))){ #strtolower
###local logs
#02-feb-2020 13:49:27.122 queries: info: client @0xb1db89c0 127.0.0.1#39119 (0.debian.pool.ntp.org): query: 0.debian.pool.ntp.org in a + (127.0.0.1)
#03-Jan-2020 07:59:42.221 rpz: info: client @0xb2a85030 192.168.43.15#50368 (dns.google): rpz QNAME NXDOMAIN rewrite dns.google via dns.google.doh.ioc2rpz
#07-Mar-2020 04:33:15.862 queries: info: client @0xb1229b10 2601:646:8f00:20b0::5#61401 (ss-prod-ue1-notif-16.aws.adobess.com): query: ss-prod-ue1-notif-16.aws.adobess.com IN A + (2601:646:8f00:20b0::3)
#07-Mar-2020 04:34:54.331 rpz: info: client @0xb1241678 2601:646:8f00:20b0::1#60800 (mobile.pipe.aria.microsoft.com): rpz QNAME CNAME rewrite mobile.pipe.aria.microsoft.com via mobile.pipe.aria.microsoft.com.notracking.ioc2rpz (CNAME to: pi-dev.rpidns.ioc2rpz.local)
#07-Mar-2020 21:24:43.315 rpz: info: client @0x79ad6e18 192.168.43.12#55362 (zillow.pages.zgtools.net): rpz IP CNAME rewrite zillow.pages.zgtools.net via 8.0.0.0.10.rpz-ip.local.ioc2rpz (CNAME to: pi-dev.rpidns.ioc2rpz.local)
###syslog logs
#2020-02-02T13:28:42+00:00 raspberrypi named[5317]: info  queries: client @0xb2b42618 127.0.0.1#39475 (rpidns.ioc2rpz.local): query: rpidns.ioc2rpz.local IN A + (127.0.0.1)

			$query=[];
			if (preg_match("/^(\d+[a-zA-Z0-9\-]+[ |T][^ ]+).*client (@0x[0-9a-zA-Z]+ )?([0-9a-fA-F\.\:]+)#([0-9]+) \([^\)]+\): query: ([^ ]+) ([^ ]+) ([^ ]+) ([^ ]+) \(([^ ]+)\)/",$line,$query)){
				# get queries
				# 1 - date/time, 2 - id, 3 - client IP, 4 - client port, 5 - fqdn, 6 - class, 7 - type, 8 - options, 9 - server				
				#echo "$line \n-----\n";print_r($query);
				$qlogs[] = [$query[1],$query[3],$query[4],$query[5],$query[6],$query[7],$query[8],$query[9]];
			};

			$rpz=[];
			if (preg_match("/^(\d+[a-zA-Z0-9\-]+[ |T][^ ]+).*rpz:.*client (@0x[0-9A-Za-z]+ )?([0-9a-fA-F\.\:]+)#([0-9]+) \(([^\)]+)\): rpz ([^ ]+) ([^ ]+) ([^ ]+) ([^ ]+) via ([^ ]+)/",$line,$rpz)){
				# get rpz hits
				# 1 - date/time, 2 - id, 3 - client IP, 4 - client port, 5 - request, 6 - policy type, 7 - action, 9 - domain, 10 - rpz rule*
				#echo "$line \n-----\n";print_r($rpz);
				$hits[] = [$rpz[1],$rpz[3],$rpz[4],$rpz[5],$rpz[6],$rpz[7],$rpz[8],$rpz[9],$rpz[10]];
				$hits_unique[$rpz[3].' '.$rpz[5]]=true;
			};


		};
		if($reset_pos){file_put_contents($fpos,"0");}else{file_put_contents($fpos,ftell($fh));};
		fclose($fh);
		$sql="";$cmm="";
		foreach ($qlogs as $query) { #*_queries.log
			$sql.=$cmm."(".strval(strtotime($query[0])).",'${query[1]}','${query[2]}','${query[3]}','${query[4]}','${query[5]}','${query[6]}','${query[7]}','".(array_key_exists($query[1].' '.$query[3],$hits_unique)?'blocked':'allowed')."')";
			$cmm=",";
		};
		$db = new SQLite3("/opt/rpidns/www/".DBFile);
		if ($sql !=""){
			$sql="insert into queries_raw (dt, client_ip, client_port, fqdn, type, class, options, server, action) values ".$sql;
			$db->exec($sql);
			//echo $sql;
		};

		$sql="";$cmm="";
		foreach ($hits as $query) { #*_queries.log
			switch ($query[4]){
				case "QNAME":
					$sql.=$cmm."(".strval(strtotime($query[0])).",'${query[1]}','${query[2]}','${query[3]}','${query[5]}','${query[4]}','${query[7]}','".substr($query[8],strlen($query[7])+1)."')";
					break;
				case "IP":
					$sql.=$cmm."(".strval(strtotime($query[0])).",'${query[1]}','${query[2]}','${query[3]}','${query[5]}','${query[4]}','${query[7]}','".substr($query[8],strpos($query[8],"rpz-ip")+7)."')";
					break;
				default:
					echo $query[4]." is not supported\n";
			}
			$cmm=",";
		};
		if ($sql !=""){
			$sql="insert into hits_raw (dt, client_ip, client_port, fqdn, action, rule_type, rule, feed) values ".$sql;
		  $db->exec($sql);
			//echo "\n\n\n".$sql;
		};

		#data aggergation;
		#delete from queries_raw;delete from hits_raw;delete from queries_5m;delete from hits_5m;delete from queries_1h;delete from hits_1h;delete from queries_1d;delete from hits_1d;
		$sql="
INSERT INTO queries_5m (dt, client_ip, fqdn, type, class, options, server, action, cnt)
select (dt - dt % 300) as dtz ,client_ip,fqdn,type,class, options, server, action, count(rowid) as cnt from queries_raw
where dt>=ifnull((select max(dt) from queries_5m),0) and dt<((select max(dt) from queries_raw) - (select max(dt) from queries_raw) % 300)
group by dtz ,client_ip,fqdn,type,class, options, server, action;

INSERT INTO hits_5m (dt, client_ip, fqdn, action, rule_type, rule, feed, cnt)
select (dt - dt % 300) as dtz ,client_ip,fqdn, action, rule_type, rule, feed, count(rowid) as cnt from hits_raw
where dt>=ifnull((select max(dt) from hits_5m),0) and dt<((select max(dt) from hits_raw) - (select max(dt) from hits_raw) % 300)
group by dtz ,client_ip,fqdn, action, rule_type, rule, feed;

INSERT INTO queries_1h (dt, client_ip, fqdn, type, class, options, server, action, cnt)
select (dt - dt % 3600) as dtz ,client_ip,fqdn,type,class, options, server, action, count(rowid) as cnt from queries_raw
where dt>=ifnull((select max(dt) from queries_1h),0) and dt<((select max(dt) from queries_raw) - (select max(dt) from queries_raw) % 3600)
group by dtz ,client_ip,fqdn,type,class, options, server, action;

INSERT INTO hits_1h (dt, client_ip, fqdn, action, rule_type, rule, feed, cnt)
select (dt - dt % 3600) as dtz ,client_ip,fqdn, action, rule_type, rule, feed, count(rowid) as cnt from hits_raw
where dt>=ifnull((select max(dt) from hits_1h),0) and dt<((select max(dt) from hits_raw) - (select max(dt) from hits_raw) % 3600)
group by dtz ,client_ip,fqdn, action, rule_type, rule, feed;

INSERT INTO queries_1d (dt, client_ip, fqdn, type, class, options, server, action, cnt)
select (dt - dt % 86400) as dtz ,client_ip,fqdn,type,class, options, server, action, count(rowid) as cnt from queries_raw
where dt>=ifnull((select max(dt) from queries_1d),0) and dt<((select max(dt) from queries_raw) - (select max(dt) from queries_raw) % 86400)
group by dtz ,client_ip,fqdn,type,class, options, server, action;

INSERT INTO hits_1d (dt, client_ip, fqdn, action, rule_type, rule, feed, cnt)
select (dt - dt % 86400) as dtz ,client_ip,fqdn, action, rule_type, rule, feed, count(rowid) as cnt from hits_raw
where dt>=ifnull((select max(dt) from hits_1d),0) and dt<((select max(dt) from hits_raw) - (select max(dt) from hits_raw) % 86400)
group by dtz ,client_ip,fqdn, action, rule_type, rule, feed;
		";
		$db->exec($sql);
		$db->close();
		$qlogs=[];
		$hits=[];
		$hits_unique=[];

	};	
	unlink($root_dir."/logs/rpidns_parser.pid");
		
?>