<?php
#(c) Vadim Pavlov 2020
#ioc2rpz DB import
require_once "/opt/rpidns/www/rpidns_vars.php";

function upgrade_db($import_db_file){
  $db = new SQLite3($import_db_file);
  $db_version=DB_selectArray($db,"PRAGMA user_version")[0]["user_version"];
  $sql="";
  switch ($db_version) {
      case 0:
        $sql.="PRAGMA user_version=".DBVersion.";";
        $sql.="alter table localzone add provisioned text;";
  };
  if ($db_version != DBVersion){
    echo "Upgrading DB from version $db_version to ".DBVersion."\n";
    DB_execute($db,$sql);
  };
  $db->close();
};

function importSQLiteDB($master_db_file,$import_db_file, $objects){
  upgrade_db($import_db_file);

  $db = new SQLite3($master_db_file);

  $db->exec("ATTACH '$import_db_file' AS db_import");
  foreach(explode(",",$objects) as $obj){
    switch ($obj):
      case "assets":
        $sql="INSERT INTO assets SELECT * FROM db_import.assets WHERE true ON CONFLICT(address) DO UPDATE SET name=excluded.name,comment=excluded.comment;";
        DB_execute($db,$sql);
      break;
      case "q_raw":
        $sql="INSERT INTO queries_raw SELECT * FROM db_import.queries_raw WHERE true ON CONFLICT DO NOTHING;"; DB_execute($db,$sql);
      break;
      case "h_raw":
        $sql="INSERT INTO hits_raw SELECT * FROM db_import.hits_raw WHERE true ON CONFLICT DO NOTHING;";DB_execute($db,$sql);
      break;
      case "q_5m":
        $sql="INSERT INTO queries_5m SELECT * FROM db_import.queries_5m WHERE true ON CONFLICT DO NOTHING;";DB_execute($db,$sql);
      break;
      case "h_5m":
        $sql="INSERT INTO hits_5m SELECT * FROM db_import.hits_5m WHERE true ON CONFLICT DO NOTHING;";DB_execute($db,$sql);
      break;
      case "q_1h":
        $sql="INSERT INTO queries_1h SELECT * FROM db_import.queries_1h WHERE true ON CONFLICT DO NOTHING;";DB_execute($db,$sql);
      break;
      case "h_1h":
        $sql="INSERT INTO hits_1h SELECT * FROM db_import.hits_1h WHERE true ON CONFLICT DO NOTHING;";DB_execute($db,$sql);
      break;
      case "q_1d":
        $sql="INSERT INTO queries_1d SELECT * FROM db_import.queries_1d WHERE true ON CONFLICT DO NOTHING;";DB_execute($db,$sql);
      break;
      case "h_1d":
        $sql="INSERT INTO hits_1d SELECT * FROM db_import.hits_1d WHERE true ON CONFLICT DO NOTHING;";DB_execute($db,$sql);
      break;
      case "bl":
        $sql="INSERT INTO localzone SELECT * FROM db_import.localzone WHERE ltype='bl' ON CONFLICT DO NOTHING;";
        DB_execute($db,$sql);
        $sql="select * from localzone where ltype='bl' and active='1';";
        foreach (DB_selectArray($db,$sql) as $RPZ) {
          if ($RPZ['subdomains']=='true') exec('echo "server 127.0.0.1\nupdate add '.$RPZ['ioc'].'.block.ioc2rpz.rpidns 60 CNAME .\nupdate add *.'.$RPZ['ioc'].'.block.ioc2rpz.rpidns 60 CNAME .\nsend\n"| /usr/bin/nsupdate -d -v',$out);
              else exec('echo "server 127.0.0.1\nupdate add '.$RPZ['ioc'].'.block.ioc2rpz.rpidns 60 CNAME .\nsend\n" | /usr/bin/nsupdate -d -v',$out);
        };
      break;
      case "wl":
        $sql="INSERT INTO localzone SELECT * FROM db_import.localzone WHERE ltype='wl' ON CONFLICT DO NOTHING;";
        DB_execute($db,$sql);
        $sql="select * from localzone where ltype='wl' and active='1';";
        foreach (DB_selectArray($db,$sql) as $RPZ) {
          if ($RPZ['subdomains']=='true') exec('echo "server 127.0.0.1\nupdate add '.$RPZ['ioc'].'.allow.ioc2rpz.rpidns 60 CNAME .\nupdate add *.'.$RPZ['ioc'].'.allow.ioc2rpz.rpidns 60 CNAME .\nsend\n"| /usr/bin/nsupdate -d -v',$out);
              else exec('echo "server 127.0.0.1\nupdate add '.$RPZ['ioc'].'.allow.ioc2rpz.rpidns 60 CNAME .\nsend\n" | /usr/bin/nsupdate -d -v',$out);
        };
      break;

    endswitch;
  };
  $db->exec("DETACH DATABASE db_import");
  //import assets
  //import q_raw,h_raw
  //import and provision one by one wl and bl
  //objects
  unlink($import_db_file);

  $db->close();
};


//importSQLiteDB("/opt/rpidns/www/db/".DBFile,$argv[1],$argv[2]);

?>
