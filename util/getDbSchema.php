<?php
define('IN_SYS', true);
require '../framework/bootstrap.inc.php';
load()->web('template');
load()->web('common');
load()->func('db');
$db = new DB($_W['config']['db']);
$schemas=db_table_serialize($db,'kaipan');
$fileName=IA_ROOT.'/util/db.php';
$code = file_get_contents($fileName);
preg_match_all("/(schemas\s=\s\')([^\']+)\';/",$code, $match);
$new = preg_replace('/(schemas\s=\s\')([^\']+)\';/', "\\1{$schemas}';", $code);

//$fileName=IA_ROOT.'/util/db_.php';
file_put_contents($fileName, $new);
$dat = require $fileName;
if(!empty($dat)){
    foreach($dat['schemas'] as $row){
        echo $row['tablename'];
    }  
}
//'CURRENT_TIMESTAMP' '0000-00-00 00:00:00'