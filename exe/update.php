<?php
define('DOKU_INC', realpath(dirname(__FILE__)) . '/../../../../');
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/io.php');

if(isset($_REQUEST) && !empty($_REQUEST['update'])) {
    $id = urldecode($_REQUEST['update']);  // file to update
    $snippet = urldecode($_REQUEST['snippet']);
    echo $id ."\n";
}
else {
  $id = $argv[1];
  echo $id . "\n";
 $snippet = 'snippet_1';
}

$helper = plugin_load('helper', 'snippets');
$page = wikiFN($id);
$result = io_readFile($page);
$helper->insertSnippet($result, $id); 
io_saveFile($page,$result);

exit;





