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
    private $helper;      
    /**
     * Register callbacks
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call');
        $controller->register_hook('IO_WIKIPAGE_READ', 'AFTER', $this, 'handle_wiki_read');        
        $controller->register_hook('TPL_ACT_RENDER', 'AFTER', $this, 'handle_content_display');
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'AFTER', $this, 'handle_wiki_write',array('after'=>true,'before'=>false));
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, 'handle_wiki_write', array('before'=>true, 'after'=>false));        
        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'handle_dw_started');     
        $controller->register_hook('COMMON_PAGETPL_LOAD', 'AFTER', $this, 'handle_template');         
        $controller->register_hook('HTML_SHOWREV_OUTPUT', 'BEFORE', $this, 'handle_revoutput', array('before'));  
    }
    
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
        $this->helper = $this->loadHelper('snippets');
    }

     function handle_revoutput(Doku_Event $event, $param){

      global $INFO;   
     $metafn = $this->helper->getMetaFileName();
     $snip_data=unserialize(io_readFile($this->metafn,false));          
     if(!array_key_exists($INFO['id'],$snip_data['doc'])) return;      
       
     $event->preventDefault();
     $old_rev=$this->getConf('old_revisions')?'on':'off';
     $onoff = get_doku_pref('snippets_old_rev', $old_rev);
     $ON_CHKD="";
     $OFF_CHKD = '';
     $msg_2 = "";
     $yesno = "";
     if($onoff == 'on') {
         $ON_CHKD='CHECKED';
         $yesno =$this->getLang('no');
         $msg_2 = $this->getLang('msg_2');  // 'Any outdated snippets have already been inserted. ';
     }
     else {
         $OFF_CHKD='CHECKED';
         $yesno =$this->getLang('yes');          
     }   
     $msg = p_locale_xhtml("showrev");
     $n = preg_match('/strong>(.*?)<\/strong/',$msg, $matches);
     $msg = str_replace('!', '.',$matches[1]) . ' ';       
     echo $msg  .$this->getLang('oldrev_msg');

     echo ' ' . $msg_2 . '<br /><span class="yesnosnippet" id="yesnosnippet">' . $this->getLang('replace') . '</span>' 
       . '<input type="radio" name="snippetOldRevwhich" ' . $ON_CHKD . ' value="on"  onchange="snippets_InsertIntOldRev(this.value);" />Yes&nbsp;'
       .'<input type="radio"  onchange="snippets_InsertIntOldRev(this.value);" '  . $OFF_CHKD . ' name="snippetOldRevwhich" value="off" />No<br/>';
     echo  "<br /><hr />" ;
         
     }
     
    function handle_dw_started(Doku_Event $event, $param) {
        global $JSINFO;
        if($this->getConf('snips_updatable')) {
            $JSINFO['updatable'] = 1;
        }
        else $JSINFO['updatable'] = 0;
        
        if($this->getConf('userreplace')) {
            $JSINFO['userreplace'] = 1;
        }
      else $JSINFO['userreplace'] = 0;       

      $JSINFO['default_macro_string'] = $this->getConf('default_macro_string') ? $this->getConf('default_macro_string') : "";
    }
    
    function handle_template(Doku_Event $event, $param) {
        $file = get_doku_pref('qs','');
        $event->data['tpl'] = preg_replace('/<snippet>.*?<\/snippet>/s',"",$event->data['tpl']);
             
         $stringvars =   // from newpagtemplate userreplace
             array_map(function($v) { return explode(",",$v,2); },
                 explode(';',$_REQUEST['macros']));
         foreach($stringvars as $value) {
             $event->data['tpl'] = str_replace(trim($value[0]),hsc(trim($value[1])),$event->data['tpl']);
	    }
        
        if(!$file) return;
        $page_id = $file;
        $file = noNS($file);
        $page = cleanID($file);
        if($this->getConf('prettytitles')) {
            $title= str_replace('_',' ',$page);
        }
        else {
           $title = $page;
        }      
        $event->data['tpl'] = str_replace(array(
                              '#ID#',
                              '#NS#',
                              '#FILE#',
                              '#!FILE#',
                              '#!FILE!#',
                              '#PAGE#',
                              '#!PAGE#',
                              '#!!PAGE#',
                              '#!PAGE!#',

                           ),
                           array(
                              $page_id,
                              getNS($page_id),
                              $file,
                              utf8_ucfirst($file),
                              utf8_strtoupper($file),
                              $page,
                              utf8_ucfirst($title),
                              utf8_ucwords($title),
                              utf8_strtoupper($title)
                              ),
                              $event->data['tpl']);
   
    }
     /**
     * Replaces outdated snippets with updated versions
     * Is capable of replacing more than one snippet in a page 
     *
     * @author Myron Turner <turnermm02@shaw.ca>
     */    
     function handle_wiki_read(Doku_Event $event,$param) {  
     
      $force_old = $this->getConf('old_revisions');  
      $default = $force_old ? 'on' : 'off';  
      $default = get_doku_pref('snippets_old_rev', $default);
      if($default == 'on') {
          $force_old = true;
      }
      else $force_old = false;
      
       if($event->data[3] && !$force_old) return;   
      $force_old = true;
       if($event->data[1]) {
         $page_id = ltrim($event->data[1] . ':' . $event->data[2], ":");       
        }
        else {
           $page_id = $event->data[2];
        }
       $this->helper->insertSnippet($event->result, $page_id, $force_old);
     }

   
    
    /**
      *  Update the array of snippet timestamps in meta files of pages where snippets are inserted 
      *
      * @author Myron Turner <turnermm02@shaw.ca>       
     */
    function handle_wiki_write(Doku_Event $event, $param) {

       if(! $event->result && $param['after']) return;  //write fail
       
       if($event->data[1]) {
        $page_id = ltrim($event->data[1] . ':' . $event->data[2], ":"); 
        }
        else {
           $page_id = $event->data[2];
        }
         
        $snip_data=unserialize(io_readFile($this->metafn,false));                                                
       
        if(!array_key_exists($page_id,$snip_data['doc']))  return;
        $snippets = $snip_data['doc'][$page_id];
        if($param['before']) {
           preg_match_all("/~~SNIPPET_C~~(.*?)~~/",$event->data[0][1],$matches);
          $intersect = array_intersect($snippets,$matches[1]);
          if(!empty($intersect)) {
              $snip_data['doc'][$page_id] = $intersect;
               io_saveFile($this->metafn,serialize($snip_data));
          }
          return;
        }
      
         
        if(preg_match('#data/pages/#', $event->data[0][0])) {  //make sure this is data/page not meta/attic save                  
            foreach($snippets as $snip) {                          
             $retv =  $this->helper->updateMetaTime($page_id,$snip) ;
           //  msg($retv);
            }
        }
        
        
    }
    /*
      *  After a snippet has been revised, this outputs table of links on the snippet page itemizing those pages where this 
      * snippet is inserted.  The links implement an ajax call to exe/update.php where the snippets can be updated
      * and the timestamps of the updated snippets revised in the metafile of the pages where snippet is inserted
   */   
    function handle_content_display(Doku_Event $event, $param) {
        global $INFO;
        $snipid = $INFO['id'];  
        $table = array();
           
        $snip_data=unserialize(io_readFile($this->metafn,false));
        if(!array_key_exists($snipid,$snip_data['snip'])) return;
   
        $snip_time= filemtime(wikiFN($snipid));
   
        $table[] = "<div id='snippet_update_table'>\nSnippet date: " . date('r',$snip_time) .'<br />';
        $table[]='<form><input type="checkbox" name="prune" value="prune" id="snip_prune" checked><span title =" '. $this->getLang('refresh_title') . '"> ' . $this->getLang('refresh') .'</span></form><br />';
        $table[] ="<table>\n";
        $table[] ='<tr><th>Page date<th>' . $this->getLang('click_to_update') .'</tr>';
        $bounding_rows = count($table);
        $page_ids = $snip_data['snip'][$snipid];
        foreach($page_ids as $pid) {
           $page_time= filemtime(wikiFN($pid));
            if($snip_time > $page_time) {
              $span = str_replace(':','_',$pid);
              $table[]= "<tr><td>" . date('r',$page_time) . '<td><a href="javascript:update_snippets(\''.$pid .'\');"  id="' .$span . '">' .$pid .'</a><tr />';
            }
        }
        $table[]='</table></div>';
        $table[]='<p><span id="snip_updates_but" style="color:#2b73b7;">' .$this->getLang('hide_table') . '</span></p>';
           
        if(count($table) > $bounding_rows+2) {
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
    function handle_ajax_call(Doku_Event $event, $param) {
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
                    $template = false;
                    if(preg_match("/templ_|templ:/",$id)) $template = true;
                    if(auth_quickaclcheck($id) >= AUTH_READ) {
                        if($event->data == 'snippet_update'  && ! $template) {  // templates are permanent
                          $tm = time();
                           print "\n~~SNIPPET_O${tm}~~$id~~\n";
                        }
                        print "\n\n"; // always start on a new line (just to be safe)
                        if($template) {
                             $tpl = pageTemplate($id);
                              if($this->getConf('skip_unset_macros')) {
                                  $tpl = preg_replace("/@\w+@/m","",$tpl);
                              }   
                              print($tpl);
                            
                        }
                        else print trim(preg_replace('/<snippet>.*?<\/snippet>/s', '', io_readFile(wikiFN($id))));
                       
                        if($event->data == 'snippet_update' && ! $template) {                       
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
