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
$most_recent = $helper->mostRecentVersion($id, $modified);
echo "date modified: " . date('r',$modified) . "\n";
echo "most_recent: " . date('r',$most_recent) . "\n";
echo 'updating: ' . wikiFN($id) . "\n";
if($snippet) echo 'snippet: ' .wikiFN($snippet);
$helper->isNewSnippet($id, $snippet) ;
echo "\ndone\n";
//$helper->updateMetaTime($id, $snippet);
//touch( wikiFN($id));

exit;




