<?php
/**
 * DokuWiki Plugin snippets (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Myron Turner <turnermm02@shaw.ca>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_snippets extends DokuWiki_Plugin {

    private $metafn;

    function __construct() {
        $this->metafn = metaFN('snippets_upd','.ser');
        if(!file_exists($this->metafn)) {
            $ar = array('snip'=>array(), 'doc'=>array());
            io_saveFile($this->metafn,serialize($ar));
        }
    }
    
    /**
     * Return info about supported methods in this Helper Plugin
     *
     * @return array of public methods
     */
    public function getMethods() {
        return array(
            array(
                'name'   => 'mostRecentVersion',
                'desc'   => 'checks metadata for most recent version of  $id',
                'params' => array(
                    'id'         => 'string'               
                ),
                'return' => array('timestamp' => 'integer')
            ),
            array(
                'name' => 'snippetWasUpdated',
                'desc' => 'check if snippet inserted in a page was updated to most recent version',
                'params' => array( 'id'=>'string', 'snippet'=>'string'),
                'return' =>array('timstamp'=>'boolean')
            ),
           array(
                'name' => 'updateMetaTime',
                'desc' => 'sets and updates timestamp of snippet in metafile of page where snippet is inserted',
                'params' => array(
                    'id' => 'string',
                    'snippet' => 'string',
                    'set_time' => 'int'
                ),
                'return' =>array()
            ), 
           array(
                'name' => 'insertSnippet',
                'desc' => 'inserts updated snippet into page',
                'params' => array(
                  'result (reference)' => 'string',
                  'page_id' => 'string'
                ),
                'return' =>array()
            ),            
        );
    }

    /**
     *  If the metadata shows that the (snippet) file has been restored from an earlier version, that is the most recent
     *  version; otherwise the last modified date is the most recent version
     *  @param string  $id  id of page to be checked for most recent version
     *  @modified int  timestamp
     *  @return int timestamp  
    */     
    function mostRecentVersion($id, &$modified) {
        $modified = p_get_metadata($id, 'date modified');            
        $sum = p_get_metadata($id, 'last_change sum');            
        if($sum && preg_match('#restored\s+(\(.*?\))#',$sum,$matches)){
            return  strtotime(trim($matches[1],'()'));                       
        }
        return $modified;
  }    
    /**
      *  Checks the most recent version of  snippet against the timestamp for snippet
      *  stored in the meta file for page into which the snippet has been inserted
      *  If the timestamps are the same then the snippet has been updated
      *  @param $id string  id of page where snippet was inserted
      *  @param snippet string  page id of snippet
    */      
   function snippetWasUpdated($id, $snippet) {       
       $isref = p_get_metadata($id, 'relation isreferencedby' );
       $snip_time = $isref['snippets'][$snippet] ;
       if(empty($snip_time)) {
          $this->updateMetaTime($id,$snippet);
       }
       $time_stamp = $this->mostRecentVersion($snippet, $modified);
 
        return $snip_time  ==  $time_stamp;
   }
   
    /**
      *  Updates time stamp of snippet in metafile of page where snippet is inserted
    */  
    function updateMetaTime($id,$snippet) {
    global $ID;
    if(empty($ID)) $ID = $id;    
        if(!$snippet) return;
    $isref = p_get_metadata($id, 'relation isreferencedby');
        $time =  $this->mostRecentVersion($snippet, $modified) ; 
      
    $data = array();
    
    if(!is_array($isref)) {
       $is_array= array();
    }
    $isref['snippets'][$snippet] = $time;       
 
    $data['relation']['isreferencedby']=$isref;
     p_set_metadata($id, $data);      
}
    
    /** 
      * Inserts updated snippets in page after checking to see if snippets have been updated
      * @param result   reference to string that holds the page contents
      * @param page_id  string  
    */  
    function insertSnippet(&$result, $page_id) {

         $snip_data=unserialize(io_readFile($this->metafn,false));          
         if(!array_key_exists($page_id,$snip_data['doc'])) return; //Check if page contains snippet

         global $replacement;  // will hold new version of snippet
         
         $snippets = $snip_data['doc'][$page_id];
         $page_t = filemtime(wikiFN($page_id));
        
         foreach ($snippets as $snip) {            
             $snip_file = wikiFN($snip);          
             $snip_t = filemtime($snip_file);     

             if($snip_t < $page_t && $this->snippetWasUpdated($page_id,$snip)) {  
                   continue;
             }
             
             $replacement =  trim(preg_replace('/<snippet>.*?<\/snippet>/s', '', io_readFile($snip_file)));             
             $snip_id = preg_quote($snip);  
             $result = preg_replace_callback(
                "|(?<=~~SNIPPET_O)\d*(~~$snip_id~~).*?(?=~~SNIPPET_C~~$snip_id~~)|ms",
                     function($matches){
                         global $replacement;                         
                         return  time()  . $matches[1]. "\n" .$replacement  . "\n";  // time() makes each update unique for renderer 
                  }, 
                  $result
                );           
          }
          
    }
    
    /*
      *    create new page array for a snippet entry in the database
      *
      *    @ $pages  array   page ids currently held for snippet  
      *    @ $id  string        id of current page
    */
    function getPageArray($pages, $id) {   
        $id_array = array();         
        foreach($pages as $page_id) {
            if($id != $page_id) {  // remove current page from array of pages held by the snippet
                $id_array[] = $page_id;
            }
        }       
        return $id_array;
    }
   
   function getMetaFileName() {
       return $this->metafn;
   }

}
// vim:ts=4:sw=4:et:
