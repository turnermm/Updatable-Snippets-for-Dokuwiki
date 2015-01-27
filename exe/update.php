<?php
define('DOKU_INC', realpath(dirname(__FILE__)) . '/../../../../');
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/io.php');


$id = urldecode($_REQUEST['update']);  // file to updte
echo 'updating: ' . wikiFN($id) . "\n";
$snippet = urldecode($_REQUEST['snippet']);
echo 'snippet: ' .wikiFN($snippet);
echo "\ndone";

exit;

if(file_exists($cname)) {
   @io_lock($cname);
   if(file_exists($ckgedit_cname)) {
      unlink($ckgedit_cname); 
   }
   unlink($cname); 

  exit;
}

echo "done";

