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

    var $output = '';
    private $metaFn;
    private $helper;
    function __construct() {       
       $metafile= 'snippets_upd';
       $this->metaFn = metaFN($metafile,'.ser');
       $this->helper = $this->loadHelper('snippets',1);
    }
    /**
     * handle user request
     */
    function handle() {
    
      if (!isset($_REQUEST['cmd'])) return;   // first time - nothing to do
//echo $this->metaFn .'<br>';
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
 //   ptln('<a href="javascript:prereg_Inf.toggle();void 0"><span style="line-height: 175%">Toggle info</span></a><br />');
 //    ptln('<div id="prereg_info" style="border: 1px solid silver; padding:4px;">'.     $this->locale_xhtml('info') . '</div><br>' );
    
      
      ptln('<form action="'.wl($ID).'" method="post">');
      
      // output hidden values to ensure dokuwiki will return back to this plugin
      ptln('  <input type="hidden" name="do"   value="admin" />');
      ptln('  <input type="hidden" name="page" value="'.$this->getPluginName().'" />');
      formSecurityToken();      
      $but_1 = $this->getLang('btn_secure')| 'Snips';
      $but_2 = $this->getLang('btn_prune')| 'Docs';
      ptln('  <input type="submit" name="cmd[prune]"  value="'.$but_1 .'" />&nbsp;&nbsp;');
      ptln('  <input type="submit" name="cmd[docs]"  value="'. $but_2.'" />');
       ptln('<br /><br /><div>');
      
      
      
      if($this->output == 'prune') {
           echo $this->prune_datafile('snip');
      }
      else  if($this->output == 'doc') {
           echo $this->prune_datafile('doc');
      }

      ptln('</div>');
      ptln('</form>');
      $this->js();  
    }
 

    function secure_datafile() {
         $perm = substr(sprintf('%o', fileperms($this->metaFn )), -4);         
         if(preg_match('/\d\d(\d)/',$perm,$matches)) {   
            if($matches[1] > 0) {
                 msg("Data file is currently accessible to all: $perm");
                 if(chmod($this->metaFn ,0600)) { 
                    msg("Succesfully change permissions to: 0600");
                 }
                 else  msg("Unable to change permissions to: 0600");
             }
         }
    }

    function prune_datafile($which="doc") {
   
   $ret = '';
        $data_all = unserialize(io_readFile($this->metaFn,false));
        $data = $data_all[$which];   
        if (!is_array($data)) $data = array();
        foreach($data as $id=>$snips) {  
          // $snips_found = $this->helper -> snippets_prune_meta($id);
            $ret .= '<p><b>id: ' . $id .'</b><br />';         
            $snips =implode('<br />',$snips);    
            $found = $this->update_all($id, $snips) ;
            $ret .= "<b><u>snippets logged:</u></b><br />$snips<br /><b>Found</b><br />$found</p>";
            
            }  
            
         return $ret;
//        io_saveFile($this->metaFn,serialize($data));
    }

    function update_all($id, $snips) {    
        
       $file=wikiFN($id);
       $text = file_get_contents($file);
       preg_match_all("/~~SNIPPET_C~~(.*?)~~/",$text,$matches);    
        $snips =implode('<br />',$matches[1]);    
        return $snips;
    }
    
    function js() {
echo <<<SCRIPT
<script type="text/javascript">
    //<![CDATA[    
var prereg_Inf = {
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