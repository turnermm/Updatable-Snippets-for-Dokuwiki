<?php
/**
 * 
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Myron Turner <turnermm02@shaw.ca>
 */

 
/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_snippets extends DokuWiki_Admin_Plugin {

    private $output = '';
    private $metaFn;    
    private $debug;
 
    function __construct() {       
       $metafile= 'snippets_upd';
       $this->metaFn = metaFN($metafile,'.ser');   
       $this->debug = false;
    }
    /**
     * handle user request
     */
    function handle() {
    
      if (!isset($_REQUEST['cmd'])) return;   // first time - nothing to do

      $this->output = 'invalid';
   
      if (!checkSecurityToken()) return;
      if (!is_array($_REQUEST['cmd'])) return;
    
      // verify valid values
      switch (key($_REQUEST['cmd'])) {
        case 'prune' :             
         $this->output = 'prune';   
         break;
        case 'docs' : 
        $this->output = 'doc';   
      //  $this->secure_datafile() ;
        break;
      }      
 
   
    }
 
    /**
     * output appropriate html
     */
    function html() {
    ptln('<a href="javascript:snippets_Inf.toggle();void 0"><span style="line-height: 175%">Toggle info</span></a><br />');
     ptln('<div id="prereg_info" style="border: 1px solid silver; padding:4px;">'.     $this->locale_xhtml('info') . '</div><br>' );
    
      
      ptln('<form action="'.wl($ID).'" method="post">');
      
      // output hidden values to ensure dokuwiki will return back to this plugin
      ptln('  <input type="hidden" name="do"   value="admin" />');
      ptln('  <input type="hidden" name="page" value="'.$this->getPluginName().'" />');
      formSecurityToken();      
 
      $but_1 = $this->getLang('cleanup_but')| 'START'; 
      ptln('  <input type="submit" name="cmd[docs]"  value="'. $but_1.'" />');
       ptln('<br /><br /><div>');
      
       if($this->output == 'doc') {
           echo $this->prune_datafile('doc');
      }

      ptln('</div>');
      ptln('</form>');
      $this->js();  
    }
 

    function prune_datafile() {   
   $ret = '<b>'. $this->getLang("dbase") .  '</b>' . $this->metaFn;
    $data_all = unserialize(io_readFile($this->metaFn,false));    
    $data = $data_all['doc'];   
    $snip_data = array();
    if (!is_array($data)) $data = array();
    foreach($data as $id=>$snips) {            
         $ar = array();  // will hold snips currently in page file
         $found = $this->update_all($id, $snips, $ar) ;
         $ret .= '<br /><h2>id: ' .  $id .'</h2>';         
         $snips =implode('<br />',$snips);    
       
         $ret .= "<b><u>" . $this->getLang("logged_for") ." $id:</u></b><br />$snips<br /><b>" . $this->getLang("in_page") ."</b><br />$found<br />";
         $data[$id]   = $ar;     
         for($i=0; $i<count($ar); $i++) {
             $snip_data[$ar[$i]][] = $id;
         }
     }  
     $final = array();    
     $final['doc'] = $data;
     $final['snip'] = $snip_data; 
     io_saveFile($this->metaFn,serialize($final));
     return $ret;
    }

    function update_all($id, $snips, &$ar) {            
        $file=wikiFN($id);
        $text = file_get_contents($file);
        preg_match_all("/~~SNIPPET_C~~(.*?)~~/",$text,$matches);    
        $ar = $matches[1];
        preg_match_all("/~~SNIPPET_O(.*?)~~(.*?)~~/",$text,$matches_tm);      
     
       $page_entries = array();
       for($i=0; $i<count($matches_tm[1]);$i++) {
               $tm = $matches_tm[1][$i];     
               $sfile =   $matches_tm[2][$i];  
                $res .=  "&nbsp;&nbsp;&nbsp;&nbsp;<b>$sfile</b>   timestamp:  " . $tm . ", date: " .date('r', $tm) .  "<br />";
                $page_entries[$sfile] = $tm;
       } 
        
        /* from metafile */
          $isref = p_get_metadata($id, 'relation isreferencedby');
         $res .=   '<br /><b>' .$this->getLang("in_meta") . '</b><br />';  
         $refs = $isref['snippets'];
         foreach ($refs as $fnam=>$tm) {
             $res .= "$fnam: " .date('r', $tm) . '<br />';
         }
         $refs_keys = array_keys($refs);
         //get snippets in $refs_keys which are not in matches[1], i.e snips in metafile which are not in page file
         $refs_diff_1 = array_diff($refs_keys,$matches[1]);   
         $refs_diff_2 = array_diff($matches[1],$refs_keys);
         if(!empty( $refs_diff_1 ))
            $res .= $this->dbg('<b>Snippets in metafile not in page:</b><br />&nbsp;&nbsp; ', $refs_diff_1,"132") ; 
         else $res .= $this->dbg("No snippets found in metafile not in page","133");
         if(!empty($refs_diff_2)) {
             $res .= $this->dbg('<b>Snippets in page not in meta file:</b><br />&nbsp;&nbsp;', $refs_diff_2,"135"); 
         }
         else $res .= $this->dbg("No snippets found in page which are not logged in metafile","137");
  
        $res .= $this->update_metafile($refs_diff_2,$refs_diff_1,$id,  $matches_tm[1][0] ,$page_entries);

         $diff = array_diff($snips,$matches[1]) ; 
         if(empty($diff)) {         
            $diff = $this->getLang('finished');
         } 
         else $diff = implode('<br />',$diff);           
         
        $res .= "<br><b>" . $this->getLang("remove_from_db") . "</b></br>" . $diff;
        return $res;
    }
   
  function update_metafile($add_array,$remove_array, $id,$tm,$page_ar )   {
      $ret =""; 
     
      $to_add = false;
      $to_remove = false;
      if(empty($add_array) && empty($remove_array)) return "Nothing to be done for $id<br />";
      $isref = p_get_metadata($id, 'relation isreferencedby');
      if(!empty($add_array)) {
          $to_add = true;
          $ret .= $this->dbg('Add to metafile: ',$add_array);
      }
      else $ret .=$this->dbg("No additions to metafile&nbsp;&nbsp;'","163");
      
       if(!empty($remove_array)) {
         $to_remove=true;  
        $ret .= $this->dbg('Remove from metafile&nbsp;&nbsp; ', $remove_array,"167");
       }
       else $ret .=$this->dbg("No snippets to remove from metafile");
                  
      $snippet_array = $isref['snippets'];
      $ret .= $this->dbg('metafile snippet_array: ', $snippet_array,"172");
      $ret .= "<b>" . $this->getLang("updating_mf")."</b><br/>";
     if($to_remove) {
         $ret .= "<b>" . $this->getLang("removing"). "</b><br/>";
         foreach($snippet_array as $snippet=>$date) {
             if(in_array($snippet,$remove_array)) {
                    $ret .=  "&nbsp;&nbsp;&nbsp;&nbsp;$snippet<br />";     
                   continue;    
             }  
             $updated['snippets'][$snippet]=$date;
          }
            $ret .= "<br/>";
            if(count($updated))  {
             $ret .= $this->dbg('updated: ', $updated,"185");
            }
            else $ret .= $this->dbg('No snippets remaining in ' . $id);
      }
      
      if($to_add) {
      $ret  .="<b>" . $this->getLang("adding") . "</b><br />"; 
      foreach($add_array as $add){
              $updated ['snippets'] [$add] =$tm;
              $ret .= "&nbsp; &nbsp;&nbsp;&nbsp;$add<br />";
          }           
      }
 
      
    // merge page entries with updates  
    foreach($page_ar as $snip=>$tm) {
        if(!array_key_exists ( $snip , $updated ['snippets'] )) {
          $updated ['snippets'][$snip] = $tm;
      }
    }

      $ret .= $this->getLang("updated") .   print_r($updated ,1) . "<br />"; 
      
     $data['relation']['isreferencedby']['snippets']=$updated['snippets'];
     p_set_metadata($id, $data);      
      return $ret;
      
  }
  
  function dbg($text, $ar = array(),$line="") {   
  if(!$this->debug) return;
  if($ar) {
          $text .= print_r( $ar, 1);
      }
      if($line) $text .= " [$line]";
      return "dbg: " . $text . "<br />";
  }
  
    function js() {
echo <<<SCRIPT
<script type="text/javascript">
    //<![CDATA[    
var snippets_Inf = {
dom_style: document.getElementById('prereg_info').style,
open: function() { this.dom_style.display = 'block'; },
close: function() { this.dom_style.display = "none"; },
toggle: function() { 
if(this.is_open) { this.close(); this.is_open=false; return; }
this.open(); this.is_open=true;
},
is_open: true,
};    
    //]]>
 </script>
SCRIPT;

    }
}