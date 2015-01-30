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
     * Register callbacks
     */
    function register(&$controller) {
        $controller->register_hook('TOOLBAR_DEFINE','AFTER', $this, 'handle_toolbar_define');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call');
        $controller->register_hook('IO_WIKIPAGE_READ', 'AFTER', $this, 'handle_wiki_read');
       $controller->register_hook('TPL_ACT_RENDER', 'AFTER', $this, 'handle_content_display');
    }
    
        /**
     *  Sets up database file for pages requiring updates
     *  @author Myron Turner<turnermm02@shaw.ca>
    */     

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
   //  $this->metafn = metaFN('snippets_upd','.ser');      
         $page_id = ltrim($event->data[1] . ':' . $event->data[2], ":");       
         $snip_data=unserialize(io_readFile($this->metafn,false));          
         if(!array_key_exists($page_id,$snip_data['doc'])) return; //Check if page contains snippet
  
         global $replacement;  // will hold new version of snippet
     
         $snippets = $snip_data['doc'][$page_id];
         $page_t = filemtime(wikiFN($page_id));
         foreach ($snippets as $snip) {
             $snip_file = wikiFN($snip);          
             $snip_t = filemtime($snip_file);             
             if($snip_t < $page_t)  continue;  // Is snippet older than page?  If newer proceed to replacement       
             $helper = $this->loadHelper('snippets');
             $helper->updateMetaTime($page_id, $snip);              
             $replacement =  trim(preg_replace('/<snippet>.*?<\/snippet>/s', '', io_readFile($snip_file)));             
             $snip_id = preg_quote($snip);  
             $event->result = preg_replace_callback(
                "|(?<=~~SNIPPET_O)\d*(~~$snip_id~~).*?(?=~~SNIPPET_C~~$snip_id~~)|ms",
                     function($matches){
                         global $replacement;                         
                         return  time()  . $matches[1]. "\n" .$replacement  . "\n";  // time() makes each update unique for renderer 
                  }, 
                  $event->result
                );           
          }
    }
    
    function handle_content_display(&$event, $param) {
        global $INFO;
        $snipid = $INFO['id'];  
        $table = array();
      //  $this->metafn = metaFN('snippets_upd','.ser');      
        $snip_data=unserialize(io_readFile($this->metafn,false));
        if(!array_key_exists($snipid,$snip_data['snip'])) return;
        $helper = $this->loadHelper('snippets');
        $snip_time= filemtime(wikiFN($snipid));
   
        $table[] = "<div id='snippet_update_table'>\nSnippet date: " . date('r',$snip_time) ."<br />";
        $table[] ="<table>\n";
        $table[] ='<tr><th>Page date<th>click to update</tr>';
        $bounding_rows = count($table);
        $page_ids = $snip_data['snip'][$snipid];
        foreach($page_ids as $pid) {
           $page_time= filemtime(wikiFN($pid));
            if($snip_time > $page_time) {
              $span = str_replace(':','_',$pid);
              $table[]= "<tr><td>" . date('r',$page_time) . '<td><a href="javascript:update_snippets(\''.$pid .'\');"  id="' .$span . '">' .$pid .'</a><tr />';
            }
        }
        $table[]="</table></div><p><span id='snip_updates_but' style='color:#2b73b7;'>Hide Updates Table</span></p>";
           
        if(count($table) > ++$bounding_rows) {
            foreach($table as $line) {
                print $line  . NL;
        }
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
        //$this->metafn = metaFN('snippets_upd','.ser');      
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
                          $tm = time();
                           print "\n~~SNIPPET_O${tm}~~$id~~\n";
                            print('data='.$event->data);
                        }
                        print "\n\n"; // always start on a new line (just to be safe)
                        print trim(preg_replace('/<snippet>.*?<\/snippet>/s', '', io_readFile(wikiFN($id))));
                       
                        if($event->data == 'snippet_update' ) {                       
                             print "\n\n~~SNIPPET_C~~$id~~\n";
                             $curpage = cleanID($_REQUEST['curpage']);   // $curpage is page into which snippet is being inserted
                             $snip_data=unserialize(io_readFile($this->metafn,false));                                        
                             if(!array_key_exists($curpage,$snip_data['doc'])) {   // insert $curpage into doc array 
                                 $snip_data['doc'][$curpage] = array($id);                // and put current snippet into its list of snippets
                            }                          
                             elseif(!in_array($id,$snip_data['doc'][$curpage])) {
                                      // if already in doc array just  put current snippet in its list of snippets         
                                  $snip_data['doc'][$curpage][]= $id;
                             }
                             if(!array_key_exists($id,$snip_data['snip'])) {  //   do the same as above but in reverse for snippets
                                 $snip_data['snip'][$id] = array($curpage);
                             }
                             elseif(!in_array($curpage,$snip_data['snip'][$id])) {
                                $snip_data['snip'][$id][] = $curpage; 
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
