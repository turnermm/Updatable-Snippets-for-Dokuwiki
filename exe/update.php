<?php
define('DOKU_INC', realpath(dirname(__FILE__)) . '/../../../../');
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/io.php');

/*
  *  @author Myron Turner <turnermm02@shaw.ca>
  *  When a snippet is updated a table of pages appears on the snippet page
  *  listing the pages where this snippet has been inserted. Each page name is
  *  a link which accesses this script by means of an ajax call.
  *  This script does two things:
  *    1. Inserts updated snippet into page
  *    2. Checks and updates meta file of page to prune away any references
  *    to snippets which are no longer on page.  This is done only if requested
  *    by ticking off check box at head of table
*/  

if(isset($_REQUEST) && !empty($_REQUEST['update'])) {
    $id = urldecode($_REQUEST['update']);  // file to update
    $snippet = urldecode($_REQUEST['snippet']);
}
else {  // used for testing
  $id = $argv[1];
  echo $id . "\n";
 $snippet = 'snippet_1';
}

$helper = plugin_load('helper', 'snippets');
$page = wikiFN($id);
$result = io_readFile($page);
$helper->insertSnippet($result, $id);   // insert all updated snippets

// update timestamps in metafiles
global $snip_data;
$snip_data=unserialize(io_readFile($helper->getMetaFileName(),false));
if(array_key_exists($id,$snip_data['doc'])) {
    $snippets = $snip_data['doc'][$id];
    foreach($snippets as $snip) {                          
        $helper->updateMetaTime($id,$snip) ;
    }
}
io_saveFile($page,$result);  // save updated page


// if requested prune out dead timestamps: default is to prune
if(isset($_REQUEST['prune'])) {
   snippets_prune_meta($id,$snippet);
}

function snippets_prune_meta($id,$snip) {
    global $snip_data,$helper;
   
    $data = p_get_metadata($id, 'relation isreferencedby');
    $snippets = array_keys($data['snippets']);
    $file=wikiFN($id);
    $text = file_get_contents($file);
    preg_match_all("/~~SNIPPET_C~~(.*?)~~/",$text,$matches);
    $intersect = array_intersect($matches[1],$snippets);

    if(!in_array($snip,$intersect) ){  //was this snippet found in the current page
      $pages = $helper->getPageArray($snip_data['snip'][$snip] , $id);  // if not, remove it from the snippet's page array
      $snip_data['snip'][$snip] = $pages;
       io_saveFile($helper->getMetaFileName(),serialize($snip_data));
    }
 
    $isref = array('snippets'=>array());
    foreach ($intersect as $i) {
        $isref['snippets'][$i]=$data['snippets'][$i];
    }

    $data = array();
     $data['relation']['isreferencedby']=$isref;
    
     p_set_metadata($id, $data);
} 
exit;





