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

}

// vim:ts=4:sw=4:et:
