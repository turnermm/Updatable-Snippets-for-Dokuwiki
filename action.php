<?php /**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 * @author     Myron Turner <turnermm02@shaw.ca>
 */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class action_plugin_snippets extends DokuWiki_Action_Plugin {
    private $metafn;
      
    /**
     *  Sets up database file for pages requiring updates
     *  @author Myron Turner<turnermm02@shaw.ca>
    */     
    function __construct() {
        $this->metafn = metaFN('snippets_upd','.ser');      
        if(!file_exists($this->metafn)) {
            $ar = array('snip'=>array(), 'doc'=>array());
            io_saveFile($this->metafn,serialize($ar));
        }
    }
    /**
     * Register callbacks
     */
    function register(&$controller) {
        $controller->register_hook('TOOLBAR_DEFINE','AFTER', $this, 'handle_toolbar_define');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call');
        $controller->register_hook('IO_WIKIPAGE_READ', 'AFTER', $this, 'handle_wiki_read');
    }

    /**
     * Adds the new toolbar item
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function handle_toolbar_define(&$event, $param) {
        if(!page_exists($this->getConf('snippets_page'))) return;

        $item = array(
                'type' => 'mediapopup',
                'title' => $this->getLang('gb_snippets'),
                'icon' => '../../plugins/snippets/images/icon.png',
                'url' => 'lib/plugins/snippets/exe/snippets.php?ns=',
                'name' => 'snippets',
                'options' => 'width=800,height=500,left=20,top=20,scrollbars=no,resizable=yes'
                );
        $event->data[] = $item;
    }

     /**
     * Replaces outdated snippets with updated versions
     * Is capable of replacing more than one snippet in a page 
     *
     * @author Myron Turner <turnermm02@shaw.ca>
     */    
     function handle_wiki_read(&$event,$param) {         
         $page_id = ltrim($event->data[1] . ':' . $event->data[2], ":");       
         $snip_data=unserialize(io_readFile($this->metafn,false));          
         if(!array_key_exists($page_id,$snip_data['doc'])) return; //Check if page contains snippet
     
         global $replacement;  // will hold new version of snippet
     
         $snippets = $snip_data['doc'][$page_id];
         $page_t = filemtime(wikiFN($page_id));
         foreach ($snippets as $snip) {
             $snip_file = wikiFN($snip);          
             $snip_t = filemtime($snip_file);             
             if($snip_t < $page_t) return;  // Is snippet older than age.  If not proceed to replacement
             $replacement = io_readFile($snip_file); //get updated snippet
             $snip_id = preg_quote($snip);  
             $event->result = preg_replace_callback(
                "|(?<=~~SNIPPET_O~~$snip_id~~)(.*?)(?=~~SNIPPET_C~~$snip_id~~)|ms",
                     function($matches){
                         global $replacement;
                         return "\n" .$replacement  . "\n";
                  }, 
                  $event->result
                );           
          }
    }
    
    /**
     * Handles the AJAX calls
     *
     * @author Michael Klier <chi@chimeric.de>
     * @author Myron Turner <turnermm02@shaw.ca>
     */
    function handle_ajax_call(&$event, $param) {
        global $lang;
        if($event->data == 'snippet_preview' or $event->data == 'snippet_insert'  or $event->data == 'snippet_update' ) {
            $event->preventDefault();  
            $event->stopPropagation();
            $id = cleanID($_REQUEST['id']);
            if(page_exists($id)) {
                if($event->data == 'snippet_preview') {
                    if(auth_quickaclcheck($id) >= AUTH_READ) {
                        print p_wiki_xhtml($id);
                    } else {
                        print p_locale_xhtml('denied');
                    }
                } elseif($event->data == 'snippet_insert' || $event->data == 'snippet_update') {
                
                    if(auth_quickaclcheck($id) >= AUTH_READ) {
                        if($event->data == 'snippet_update' ) {
                           print "\n~~SNIPPET_O~~$id~~\n";
                        }
                        print "\n\n"; // always start on a new line (just to be safe)
                        print trim(preg_replace('/<snippet>.*?<\/snippet>/s', '', io_readFile(wikiFN($id))));
                        if($event->data == 'snippet_update' ) {                       
                             print "\n\n~~SNIPPET_C~~$id~~\n";
                             $curpage = cleanID($_REQUEST['curpage']); 
                             $snip_data=unserialize(io_readFile($this->metafn,false));
                             if(!array_key_exists($curpage,$snip_data['doc'])) {
                                 $snip_data['doc'][$curpage] = array($id);
                             }
                             elseif(!in_array($id,$snip_data['doc'][$curpage])) {
                                 array_push($id,$snip_data['doc'][$curpage]);              
                             }
                             if(!array_key_exists($id,$snip_data['snip'])) {
                                 $snip_data['snip'][$id] = array($curpage);
                             }
                             elseif(!in_array($curpage,$snip_data['snip'][$id])) {
                                 array_push($curpage,$snip_data['doc'][$id]);              
                             }      
                             io_saveFile($this->metafn,serialize($snip_data));                             
                        }
                    }
                }
            }
        }
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
