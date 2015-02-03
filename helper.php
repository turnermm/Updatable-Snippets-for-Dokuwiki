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
                // and more supported methods...
            )
        );
    }

    /**
     *  If the metadata shows that the file has been restored from an earlier version, that is the most recent
     *  version; otherwise the last modified date is the most recent version
    */     
    function mostRecentVersion($id, &$modified) {
        $modified = p_get_metadata($id, 'date modified');            
        $sum = p_get_metadata($id, 'last_change sum');            
        if($sum && preg_match('#restored\s+(\(.*?\))#',$sum,$matches)){
            return  strtotime(trim($matches[1],'()'));                       
        }
        return $modified;
  }    

   function snippetWasUpdated($id, $snippet) {       
       $isref = p_get_metadata($id, 'relation isreferencedby' );
       $snip_time = $isref['snippets'][$snippet] ;
       if(empty($snip_time)) {
          $this->updateMetaTime($id,$snippet);
       }
       $time_stamp = $this->mostRecentVersion($snippet, $modified);
 
       // msg("found in $id =>$snip_time  --  found in $snippet =>$time_stamp");
        return $snip_time  ==  $time_stamp;
   }
   
    function updateMetaTime($id,$snippet, $set_time = "") {
    global $ID;
    if(empty($ID)) $ID = $id;    
        if(!$snippet) return;
    $isref = p_get_metadata($id, 'relation isreferencedby');
        $time = $set_time ? $set_time : $this->mostRecentVersion($snippet, $modified) ; 
      
    $data = array();
    
    if(!is_array($isref)) {
       $is_array= array();
    }
    $isref['snippets'][$snippet] = $time;       
 
    $data['relation']['isreferencedby']=$isref;
     p_set_metadata($id, $data);      
}
    
    
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
    

}
// vim:ts=4:sw=4:et:
