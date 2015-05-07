/**
 * Javascript for DokuWiki Plugin snippets
 * @author Michael Klier <chi@chimeric.de>
 */

snippets = {
    keepopen: false,
    update: false,
   
    // Attach all events to elements
    attach: function(obj) {
        if(!obj) { return; }
        if(!opener) { return; }

        // add keepopen checkbox
        var opts = jQuery('#plugin_snippets__opts');
        if(opts) {
            var kobox  = document.createElement('input');
            kobox.type = 'checkbox';
            kobox.id   = 'snippets__keepopen';
            
            var updatebox  = document.createElement('input');
            updatebox.type = 'checkbox';
            updateid   = 'snippets__update';            
            
            if(DokuCookie.getValue('snippets_keepopen')){
                kobox.checked  = true;
                kobox.defaultChecked = true; //IE wants this
                snippets.keepopen = true;
            }
            jQuery(kobox).bind('click', function(event){
                snippets.togglekeepopen(this); }
            );

            var kolbl       = document.createElement('label');
            kolbl.htmlFor   = 'snippets__keepopen';
            if(LANG['keepopen']) {
            kolbl.innerHTML = " " + LANG['keepopen'];
            }
            else {
            kolbl.innerHTML = " " + LANG['plugins']['snippets']['keepopen'];
            }

            var kobr = document.createElement('br');

            opts.append(kobox);
            opts.append(kolbl);
            opts.append(kobr);
            
            // Code for inserting update request box
            if(DokuCookie.getValue('snippets_update')){
                updatebox.checked  = true;
                updatebox.defaultChecked = true; //IE wants this
                snippets.update = true;
            }
            jQuery(updatebox).bind('click', function(event){
                snippets.toggleupdate(this); }
            );            
            var updl       = document.createElement('label');
            var kobr2 = document.createElement('br');            
            updl.htmlFor   = 'snippets__update';
            updl.innerHTML = " " + LANG['plugins']['snippets']['check_for_updates'];  //" Check for snippet updates"; // needs language entry
          
            opts.append(updatebox); 
            opts.append(updl);
            opts.append(kobr2);
        }

        // attach events
        links = jQuery(obj).find('a.wikilink1');
        if(links) {
            for(var i = 0; i < links.length; i ++) {
                link = links[i];
                page = link.title;
                div  = link.parentNode;

                span = document.createElement('span');
                span.innerHTML = link.innerHTML;
                div.removeChild(link);
                // Exclude template snippets from preview since they have no data at this point
                if(!link.href.match(/templ_|templ:/)) { 
                preview = document.createElement('a');
                preview.className = 'plugin_snippets_preview';
                preview.title = LANG['plugins']['snippets']['preview'];
                preview.href = page;

                jQuery(preview).bind('click', {'page': page}, function(event) {
                    snippets.preview(event.data.page);
                    return false;
                });
                div.appendChild(preview);
                }
                insert = document.createElement('a');
                insert.className = 'plugin_snippets_insert';
                insert.title = LANG['plugins']['snippets']['insert'];
                insert.href = page;

                jQuery(insert).bind('click', {'page': page}, function(event) {
                    snippets.insert(event.data.page);
                    return false;
                });

                div.appendChild(insert);
                div.appendChild(span);
            }
        }

        // strip out links to non-existing pages
        links = jQuery(obj).find('a.wikilink2');
        if(links) {
            for(var i = 0; i < links.length; i ++) {
                link = links[i];
                span = document.createElement('span');
                span.innerHTML = link.innerHTML;
                div = link.parentNode;
                div.removeChild(link);
                div.appendChild(span);
            }
        }

        // add toggle to sub lists
        lists = jQuery(obj).find('ul');
        if(lists) {
            for(var i = 1; i < lists.length; i++) {
            list = lists[i];
                list.style.display = 'none';
                div = list.previousSibling;
                if(div.nodeType != 1) {
                    // IE7 and FF treat whitespace different
                    div = div.previousSibling;
                }
                div.className = 'li closed';
                jQuery(div).bind('click', function(event) { snippets.toggle(this); });
            }
        }
    },
    
    // toggle open/close state in template list
    toggle: function(obj) {
        if(!obj) return;
        list = obj.nextSibling;
        if(list.nodeType != 1) {
            list = list.nextSibling;
        }
        if(list.style.display == 'none') {
            list.style.display = 'block';
            obj.className = 'li open';
        } else {
            list.style.display = 'none';
            obj.className = 'li closed';
        }
        return false;
    },

    /**
     * Toggles the keep open state
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    togglekeepopen: function(cb){
        if(cb.checked){
            DokuCookie.setValue('snippets_keepopen',1);
            snippets.keepopen = true;
        }else{
            DokuCookie.setValue('snippets_keepopen','');
            snippets.keepopen = false;
        }
    },

     toggleupdate: function(cb){
        if(cb.checked){
            DokuCookie.setValue('snippets_update',1);
            snippets.update = true;
        }else{
            DokuCookie.setValue('snippets_update','');
            snippets.update = false;
        }
    },
    // perform AJAX preview
    preview: function(page) {
        preview = jQuery('#plugin_snippets__preview');
        if(!preview) return;

        preview.html('<img src="' + DOKU_BASE + 'lib/images/throbber.gif" />');

        jQuery.post(
            DOKU_BASE+'lib/exe/ajax.php',
            { call: 'snippet_preview', id: page },
            function(data){
                if(data === '') return;
                preview.html(data);
            }
        );

        return false;
    },

    // perform AJAX insert
    insert: function(page) {
        if(!opener) return;
       
        var which = snippets.update ? 'snippet_update' : 'snippet_insert';  // selects whether to insert with or without update request
        jQuery.post(
            DOKU_BASE+'lib/exe/ajax.php',
            { call: which, id: page, curpage: opener.JSINFO['id'] },  // curpage is used for updates
            function(data){
                opener.insertAtCarret('wiki__text', data, '');
                if(!snippets.keepopen) {
                    window.close();
                }
                opener.focus();
            }
        );
        return false;
    }
};

jQuery(function(){
    var idx = jQuery('#plugin_snippets__idx');
    if(idx.length == 0) return;
    snippets.attach(idx);
});

function update_snippets(which) {       
  var prune = document.getElementById('snip_prune');

        var debug = false;      
        var params = "update=" +encodeURIComponent(which);
        params += "&snippet="+ encodeURIComponent(JSINFO['id']);
        if(prune.checked) params += "&prune=" +encodeURIComponent('prune');
     
        jQuery.ajax({
           url: DOKU_BASE + 'lib/plugins/snippets/exe/update.php',
           async: true,
           data: params,    
           type: 'POST',
           dataType: 'html',         
           success: function(data){                 
               if(debug) {            
                  alert(data);
               }
    }
    });
   var span_id = '#' + which.replace(/:/g,'_');  // de-activate links that have been called
   jQuery(span_id).css({'color':'gray', 'text-decoration':'none', 'cursor': 'initial','pointer-events': 'none'});
}

jQuery(document).ready(function() {
 // hide and show update table in footer of snippets
jQuery( "#snip_updates_but" ).click(function() { 
    if(this.innerHTML.match(/Hide/)) {
        this.innerHTML="Show Updates Table";
    } else  this.innerHTML =  "Hide Updates Table";
    jQuery( "#snippet_update_table" ).toggle();
});
jQuery( "#snip_updates_but" ).mouseover(function() { 
      jQuery( "#snip_updates_but" ).css({'cursor': 'pointer', "font-weight": "bolder"});
 });
 jQuery( "#snip_updates_but" ).mouseout(function() { 
      jQuery( "#snip_updates_but" ).css({'cursor': 'initial', "font-weight": "initial"});
 });
 
if(opener) {
    if(opener.JSINFO['updatable']) {  // initialize 'on' state of update checkbox in pop-up
       DokuCookie.setValue('snippets_update',1)   
    }       
    else DokuCookie.setValue('snippets_update','');        
       
     DokuCookie.setValue('qs',opener.JSINFO['id']);
  }
});
 

 
 

// vim:ts=4:sw=4:et:enc=utf-8:
