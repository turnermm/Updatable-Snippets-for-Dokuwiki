<?php
define('DOKU_INC', realpath(dirname(__FILE__)) . '/../../../../');
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/io.php');

if(isset($_REQUEST) && !empty($_REQUEST['update'])) {
    $id = urldecode($_REQUEST['update']);  // file to update
    $snippet = urldecode($_REQUEST['snippet']);
    echo $id ."\n";
    echo $snippet ."\n";
    echo $prune ."\n";   
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

$snip_data=unserialize(io_readFile($this->metafn,false));
if(array_key_exists($page_id,$snip_data['doc'])) {
    $snippets = $snip_data['doc'][$page_id];
    foreach($snippets as $snip) {                          
        $helper->updateMetaTime($id,$snip) ;
    }
}
io_saveFile($page,$result);

if(isset($_REQUEST['prune'])) {
   snippets_prune_meta($id);
}

function snippets_prune_meta($id) {
    $data = p_get_metadata($id, 'relation isreferencedby');
    $snippets = array_keys($data['snippets']);
    $file=wikiFN($id);
    $text = file_get_contents($file);
    preg_match_all("/~~SNIPPET_C~~(.*?)~~/",$text,$matches);
    $intersect = array_intersect($matches[1],$snippets);

    $isref = array('snippets'=>array());

    foreach ($intersect as $i) {
        $isref['snippets'][$i]=$data['snippets'][$i];
    }

    $data = array();
     $data['relation']['isreferencedby']=$isref;
    echo print_r($data,true);
     p_set_metadata($id, $data);
} 
exit;





