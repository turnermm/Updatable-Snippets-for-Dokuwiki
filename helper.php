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
        if($snip_time === $time_stamp) 
           $res = "true";
        else $res = "false";
        msg("\n$id sniptime($snip_time) equals $snippet time_stamp($time_stamp ): $res\n");
        return $snip_time  ==  $time_stamp;
       
   }
   
  function updateMetaTime($id,$snippet) {
    global $ID;
    if(empty($ID)) $ID = $id;    
    $isref = p_get_metadata($id, 'relation isreferencedby');
    $time = $this->mostRecentVersion($snippet, $modified) ;
    $data = array();
    
    if(!is_array($isref)) {
       $is_array= array();
    }
    $isref['snippets'][$snippet] = $time;       
 
    $data['relation']['isreferencedby']=$isref;
     p_set_metadata($id, $data);      
}
}
// vim:ts=4:sw=4:et:
