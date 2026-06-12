<?php
#(c) Vadim Pavlov 2020 - 2026
#RpiDNS - auto-disable expired local indicators (block/allow)
#Indicators with expires_dt>0 and expires_dt<=now are disabled (active=0),
#removed from the BIND RPZ zones and their comment is annotated on top.
require_once "/opt/rpidns/www/rpidns_vars.php";

function expireIOCs($DBF){
  global $bind_host;
  $db = DB_open($DBF);

  $now = time();
  $sql = "select rowid, ioc, ltype, subdomains, comment from localzone ".
         "where active='1' and expires_dt is not null and expires_dt>0 and expires_dt<=".$now.";";

  foreach (DB_selectArray($db,$sql) as $rec) {
    $ioc  = $rec['ioc'];
    $list = $rec['ltype']; // 'block' or 'allow'

    // Remove the record (and wildcard) from the dynamic BIND RPZ zone
    $out = [];
    exec('printf "server '.$bind_host.'\nupdate delete '.$ioc.'.'.$list.'.ioc2rpz.rpidns 60 CNAME .\nupdate delete *.'.$ioc.'.'.$list.'.ioc2rpz.rpidns 60 CNAME .\nsend\n" | /usr/bin/nsupdate -d -v', $out);

    // Annotate the comment on top and disable the indicator
    $stamp   = gmdate('Y-m-d H:i:s');
    $note    = "[Auto-disabled ".$stamp." UTC] ";
    $comment = $note.$rec['comment'];

    $usql = "update localzone set active=0, expires_dt=0, comment='".DB_escape($db,$comment)."' where rowid=".intval($rec['rowid']).";";
    DB_execute($db,$usql);
  }

  DB_close($db);
};

expireIOCs("/opt/rpidns/www/db/".DBFile);

?>
