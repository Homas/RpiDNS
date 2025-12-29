<?php
  $rpiver='1.0.0.0';
	$RpiPath="/opt/rpidns";
	define("DBFile", "rpidns.sqlite");
	const DB="sqlite";
  const TMPDir="/tmp/rpidns";
  const DBVersion=1;
  $bind_host="bind";
	$filter_fields_q=['client_ip','fqdn','mac','type', 'class', 'server', 'options', 'action'];
	$filter_fields_h=['client_ip','fqdn','mac','rule_type', 'rule', 'feed', 'action'];

function getRequest(){
  #do it simple for now
  #support only 1 level request
  $rawRequest = file_get_contents('php://input');
  if (empty($rawRequest)){
    $Data=$_REQUEST;
  }else{
    $Data=array_merge($_REQUEST,json_decode($rawRequest,true));
  };
  $Data['method'] = $_SERVER['REQUEST_METHOD'];
  //$Data['req'] = explode("/", substr(@$_SERVER['PATH_INFO'], 1))[0];
  /*
   * TODO escape values for SQL safety
   */
  //if ($Data['method'] == 'PUT') print_r($Data);
  return $Data;
};

function getProto(){
  return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
};

function secHeaders(){
    header("Content-Security-Policy: frame-ancestors 'self';");
};


function DB_open($file)
{
  switch (DB){
    case "sqlite":
      $db = new SQLite3($file);
      $db->busyTimeout(5000);
      $db->exec('PRAGMA journal_mode = wal;'); //PRAGMA foreign_keys = ON;
    break;
  }
  return $db;
}

function DB_close($db)
{
  switch (DB){
    case "sqlite":
      $db->close();
    break;
  }
}

function DB_select($db,$sql){
  switch (DB){
    case "sqlite":
      $result=$db->query($sql);
    break;
  }
  return $result;
};

function DB_escape($db,$text){
  switch (DB){
    case "sqlite":
      $result=$db->escapeString($text);
    break;
  }
  return $result;
};

function DB_boolval($val){
  switch (DB){
    case "sqlite":
      $result=$val=="1"?1:0;
    break;
  }
  return $result;
};



function DB_selectArray($db,$sql){
  switch (DB){
    case "sqlite":
			#error_log("$sql\n");
      $data=[];
      $result=$db->query($sql);
      while ($row=$result->fetchArray(SQLITE3_ASSOC)){
        $data[]=$row;
      };
    break;
  }
  return $data;
};

function DB_selectArrayNum($db,$sql){
  switch (DB){
    case "sqlite":
			#error_log("$sql\n");
      $data=[];
      $result=$db->query($sql);
      while ($row=$result->fetchArray(SQLITE3_NUM)){
        $data[]=$row;
      };
    break;
  }
  return $data;
};

function DB_fetchRecord($db,$sql){
	$row=[];
  switch (DB){
    case "sqlite":
			#error_log("$sql\n");
      $result=$db->query($sql);
      $row=$result->fetchArray(SQLITE3_ASSOC);
    break;
  }
  return $row;
};

function DB_fetchArray($result){
  switch (DB){
    case "sqlite":
      $data=$result->fetchArray(SQLITE3_ASSOC);
    break;
  }
  return $data;
};

function DB_fetchArrayNum($result){
  switch (DB){
    case "sqlite":
      $data=$result->fetchArray(SQLITE3_NUM);
    break;
  }
  return $data;
};

function DB_execute($db,$sql){
  switch (DB){
    case "sqlite":
      $result=$db->exec($sql);
    break;
  }
  return $result;
};

function DB_lasterror($db){
  switch (DB){
    case "sqlite":
      $result=$db->lastErrorMsg();
    break;
  }
  return $result;
};

?>
