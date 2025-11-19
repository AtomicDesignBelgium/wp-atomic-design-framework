<?php
namespace ADF;
if (!defined('ABSPATH')) exit;

class ADF_InternalNotes {
    static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $notes = $wpdb->prefix . 'adf_notes';
        $comments = $wpdb->prefix . 'adf_notes_comments';
        $sql1 = "CREATE TABLE {$notes} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            author BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            validation_status VARCHAR(20) NOT NULL,
            priority VARCHAR(20) NOT NULL,
            media LONGTEXT NULL,
            validated_by BIGINT UNSIGNED NULL,
            ref VARCHAR(16) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status),
            KEY validation_status (validation_status),
            KEY priority (priority)
        ) {$charset};";
        $sql2 = "CREATE TABLE {$comments} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            note_id BIGINT UNSIGNED NOT NULL,
            author BIGINT UNSIGNED NOT NULL,
            content LONGTEXT NOT NULL,
            media LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY note_id (note_id)
        ) {$charset};";
        dbDelta($sql1);
        dbDelta($sql2);
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'adf-notes';
        if (!is_dir($dir)) wp_mkdir_p($dir);
        $ht = $dir . '/.htaccess';
        if (!file_exists($ht)) {
            $rules = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n";
            @file_put_contents($ht, $rules);
        }
    }

    function __construct() {
        add_action('add_meta_boxes', [$this,'add_box']);
        add_action('admin_menu', [$this,'register_admin']);
        $this->ensure_schema();
        add_action('wp_ajax_adf_create_note', [$this,'ajax_create_note']);
        add_action('wp_ajax_adf_add_comment', [$this,'ajax_add_comment']);
        add_action('wp_ajax_adf_update_note', [$this,'ajax_update_note']);
        add_action('wp_ajax_adf_upload_note_file', [$this,'ajax_upload_file']);
        add_action('admin_post_adf_download_note_file', [$this,'download_file']);up        add_action('admin_post_adf_download_note_root_file', [$this,'download_note_root_file']);
        add_action('wp_dashboard_setup', [$this,'register_dash_widgets']);
    }

    function register_admin() {
        add_menu_page('Internal Notes','Internal Notes','edit_pages','adf-internal-notes',[$this,'admin_page'],'dashicons-admin-comments',58);
    }

    function add_box() {
        add_meta_box('adf_internal_notes','Internal Notes',[$this,'box_html'],'page','side');
    }

    function box_html($post) {
        wp_nonce_field('adf_notes','adf_notes_nonce');
        echo '<div id="adfNotesBox">';
        $notes = $this->get_notes(['post_id'=>$post->ID,'limit'=>5]);
        if (!empty($notes)) {
            echo '<ul style="margin:0;padding-left:16px">';
            foreach ($notes as $n) {
                echo '<li><strong>'.esc_html($n['title']).'</strong> · '.esc_html($n['status']).' · '.esc_html($n['priority']).'</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>No notes yet</p>';
        }
        if (current_user_can('edit_pages')) {
            echo '<p><input type="text" id="adfNoteTitle" placeholder="Title" style="width:100%">';
            echo '<textarea id="adfNoteContent" placeholder="Content" style="width:100%;height:80px"></textarea>';
            echo '<button class="button button-primary" id="adfNoteCreate">Add note</button></p>';
            echo '<script>(function(){var btn=document.getElementById("adfNoteCreate");if(!btn)return;btn.addEventListener("click",function(){var t=document.getElementById("adfNoteTitle").value||"";var c=document.getElementById("adfNoteContent").value||"";var fd=new FormData();fd.append("action","adf_create_note");fd.append("post_id","'+intval($post->ID)+'");fd.append("title",t);fd.append("content",c);fd.append("_wpnonce","'.wp_create_nonce('adf_notes').'");fetch(ajaxurl,{method:"POST",body:fd}).then(r=>r.json()).then(function(){location.reload();});});})();</script>';
        }
        
    }

    function admin_page() {
        echo '<div class="wrap">';
        if (current_user_can('edit_pages')) {
            $is_admin = current_user_can('manage_options');
            
            echo '<div id="adfNewNoteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.35);z-index:9999;align-items:center;justify-content:center">';
            echo '<div class="adf-notes-create" style="position:relative;margin:16px 0;padding:12px 36px 12px 12px;border:1px solid #ddd;border-radius:6px;background:#fff">';
            echo '<button type="button" id="adfModalClose" class="button" style="position:absolute;top:8px;right:8px">✖</button>';
            echo '<h2 style="margin:0 0 12px;font-size:16px">Create a note</h2>';
            echo '<label>Related page: ';
            echo wp_dropdown_pages(['echo'=>0,'name'=>'adfNotePostId','id'=>'adfNotePostId','show_option_none'=>'Global (no page)','option_none_value'=>'0']);
            echo '</label> ';
            echo '<input type="text" id="adfNoteTitleAdmin" placeholder="Title" style="width:260px"> ';
            echo '<select id="adfNotePriorityAdmin"><option value="none">Priority: none</option><option value="low">Priority: low</option><option value="medium">Priority: medium</option><option value="high">Priority: high</option></select> ';
            echo '<select id="adfNoteProgressAdmin"><option value="pending">Progress: pending</option><option value="in_progress">Progress: in_progress</option><option value="done">Progress: done</option></select> ';
            if ($is_admin) {
                echo '<select id="adfNoteValidationAdmin"><option value="pending">Validation: pending</option><option value="validated">Validation: validated</option><option value="rejected">Validation: rejected</option></select> ';
            }
            echo '<br><textarea id="adfNoteContentAdmin" placeholder="Content" style="width:100%;height:90px;margin-top:8px"></textarea>';
            $fileId = 'adfNoteFile';
            echo '<div style="margin-top:8px;display:flex;justify-content:flex-end;align-items:center;gap:8px">';
            echo '<input type="file" id="'.$fileId.'" style="display:none" multiple>';
            echo '<label for="'.$fileId.'" class="button">📎 Attach file</label>';
            echo '<button class="button button-primary" id="adfNoteCreateAdmin">📝 Create note</button>';
            echo '</div>';
            echo '<div id="adfNoteSelected" class="adf-selected-files" style="margin-top:6px"></div>';
            $nonce_btn = wp_create_nonce('adf_notes');
            $val_js = $is_admin ? "document.getElementById('adfNoteValidationAdmin').value||'pending'" : "'pending'";
            echo "<script>(function(){var btn=document.getElementById('adfNoteCreateAdmin');if(!btn)return;var file=document.getElementById('{$fileId}');var list=document.getElementById('adfNoteSelected');var sel=[];var noteRef=(Math.random().toString(36).slice(2,10));function icon(ext){ext=String(ext||'').toLowerCase();return ext==='pdf'?'📄':((ext==='doc'||ext==='docx')?'📝':((ext==='xls'||ext==='xlsx')?'📊':((ext==='jpg'||ext==='jpeg'||ext==='png'||ext==='gif'||ext==='webp')?'🖼️':'📎')));}function trunc(n){n=String(n||'');return n.length>32?n.slice(0,18)+'…'+n.slice(-10):n;}function render(){if(!list)return;var html='';sel.forEach(function(f,i){var e=(f.name.split('.').pop()||'');html+=('<span data-i=\''+i+'\' style=\"display:inline-flex;align-items:center;gap:6px;padding:2px 8px;border:1px solid #ddd;border-radius:12px;margin:3px;\">'+icon(e)+' '+trunc(f.name)+' <a href=\"#\" class=\"adfFileRemove\">✖</a></span>');});list.innerHTML=html;list.querySelectorAll('.adfFileRemove').forEach(function(a){a.addEventListener('click',function(ev){ev.preventDefault();var p=this.parentElement;var idx=p&&p.getAttribute('data-i');if(idx!==null){sel.splice(parseInt(idx,10),1);render();}});});}
            file.addEventListener('change',function(){var add=Array.from(file.files||[]);var names=sel.map(function(f){return f.name;});add.forEach(function(f){if(names.indexOf(f.name)===-1){sel.push(f);}});file.value='';render();});
            function uploadFiles(files, cb){if(!files||!files.length){cb([]);return;}var tasks=Array.from(files).map(function(f){var uf=new FormData();uf.append('action','adf_upload_note_file');uf.append('file',f);uf.append('ref',noteRef);uf.append('_wpnonce','{$nonce_btn}');return fetch(ajaxurl,{method:'POST',body:uf}).then(function(r){return r.json();}).then(function(resp){return (resp&&resp.success&&resp.data&&resp.data.filename)?resp.data.filename:null;}).catch(function(){return null;});});Promise.all(tasks).then(function(names){cb(names.filter(function(n){return !!n;}));});}
            function createNote(mediaArr){var pid=document.getElementById('adfNotePostId').value||'0';var t=document.getElementById('adfNoteTitleAdmin').value||'';var c=document.getElementById('adfNoteContentAdmin').value||'';var pr=document.getElementById('adfNotePriorityAdmin').value||'none';var pg=document.getElementById('adfNoteProgressAdmin').value||'pending';var vl={$val_js};var fd=new FormData();fd.append('action','adf_create_note');fd.append('post_id',pid);fd.append('title',t);fd.append('content',c);fd.append('priority',pr);fd.append('progress',pg);fd.append('validation',vl);fd.append('ref',noteRef);if(mediaArr&&mediaArr.length){fd.append('media',JSON.stringify(mediaArr));}fd.append('_wpnonce','{$nonce_btn}');fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(){location.reload();});}
            btn.addEventListener('click',function(){var files=sel;if(files&&files.length){uploadFiles(files,function(names){createNote(names);});}else{createNote([]);} });})();</script>";
            echo '</div>';
            echo '</div>';
            echo '<script>(function(){var open=document.getElementById("adfOpenNewNote");var modal=document.getElementById("adfNewNoteModal");var closeBtn=document.getElementById("adfModalClose");if(modal&&modal.parentNode!==document.body){document.body.appendChild(modal);}function show(){if(modal){modal.style.display="flex";}}function hide(){if(modal){modal.style.display="none";}}if(open){open.addEventListener("click",show);}if(closeBtn){closeBtn.addEventListener("click",hide);}document.addEventListener("keydown",function(e){if(e.key==="Escape"){hide();}});})();</script>';
        }
        $fp = isset($_GET['adf_fp']) ? sanitize_text_field($_GET['adf_fp']) : 'all';
        $prf = isset($_GET['adf_pr']) ? sanitize_text_field($_GET['adf_pr']) : 'all';
        $stf = isset($_GET['adf_st']) ? sanitize_text_field($_GET['adf_st']) : 'all';
        
        $sf = isset($_GET['adf_so']) ? sanitize_text_field($_GET['adf_so']) : 'date';
        echo '<div id="adfFilters" class="notes-controls-bar" style="margin:20px 0;padding:12px 16px;border:1px solid #e3e3e3;background:#fff;border-radius:6px;box-shadow:0 1px 2px rgba(0,0,0,.04)">';
        echo '<form method="get" action="'.esc_url($_SERVER['REQUEST_URI'].'#adfFilters').'" style="display:block">';
        foreach (['page','tab'] as $hn) { if (!empty($_GET[$hn])) { echo '<input type="hidden" name="'.esc_attr($hn).'" value="'.esc_attr($_GET[$hn]).'">'; } }
        echo '<div class="notes-filter-bar" style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:8px">';
        echo '<label>Filter by page: <select name="adf_fp">';
        $page_rows = $this->get_pages_counts(['exclude_status'=>'done','priority'=>$prf!=='all'?$prf:null,'status'=>$stf!=='all'?$stf:null]);
        $global_count = 0; $page_opts = [];
        foreach ($page_rows as $r) { $pid = isset($r['post_id']) ? intval($r['post_id']) : 0; $cnt = isset($r['c']) ? intval($r['c']) : 0; if ($pid===0) { $global_count = $cnt; } else { $page_opts[] = ['id'=>$pid,'count'=>$cnt,'title'=>get_the_title($pid)]; } }
        echo '<option value="global"'.($fp==='global'?' selected':'').'>Global ('.$global_count.')</option>';
        foreach ($page_opts as $po) { echo '<option value="'.intval($po['id']).'"'.(strval($fp)==strval($po['id'])?' selected':'').'>'.esc_html($po['title']).' ('.intval($po['count']).')</option>'; }
        echo '<option value="all"'.($fp==='all'?' selected':'').'>All pages ('.$this->count_notes(['exclude_status'=>'done','priority'=>$prf!=='all'?$prf:null,'status'=>$stf!=='all'?$stf:null]).')</option>';
        echo '</select></label>';
        echo '</div>';
        echo '<label>Filter by priority: <select name="adf_pr">';
        $totalForPriority = function($val) use ($fp,$stf) {
            $args = ['status'=>$stf!=='all'?$stf:null];
            if ($fp==='global') { $args['post_is_null'] = true; }
            else if (is_numeric($fp)) { $args['post_id'] = intval($fp); }
            if ($val && $val!=='all') { $args['priority'] = $val; }
            return $this->count_notes($args);
        };
        echo '<option value="all"'.($prf==='all'?' selected':'').'>All ('.intval($totalForPriority('all')).')</option>';
        echo '<option value="high"'.($prf==='high'?' selected':'').'>🔴 High ('.intval($totalForPriority('high')).')</option>';
        echo '<option value="medium"'.($prf==='medium'?' selected':'').'>🟠 Medium ('.intval($totalForPriority('medium')).')</option>';
        echo '<option value="low"'.($prf==='low'?' selected':'').'>🔵 Low ('.intval($totalForPriority('low')).')</option>';
        echo '<option value="none"'.($prf==='none'?' selected':'').'>⚪ None ('.intval($totalForPriority('none')).')</option>';
        echo '</select></label>';
        echo '<label>Filter by progress: <select name="adf_st">';
        $totalForStatus = function($val) use ($fp,$prf) {
            $args = ['priority'=>$prf!=='all'?$prf:null];
            if ($fp==='global') { $args['post_is_null'] = true; }
            else if (is_numeric($fp)) { $args['post_id'] = intval($fp); }
            if ($val && $val!=='all') { $args['status'] = $val; }
            return $this->count_notes($args);
        };
        echo '<option value="all"'.($stf==='all'?' selected':'').'>All ('.intval($totalForStatus('all')).')</option>';
        echo '<option value="pending"'.($stf==='pending'?' selected':'').'>🟧 Pending ('.intval($totalForStatus('pending')).')</option>';
        echo '<option value="in_progress"'.($stf==='in_progress'?' selected':'').'>🔵 In progress ('.intval($totalForStatus('in_progress')).')</option>';
        echo '<option value="done"'.($stf==='done'?' selected':'').'>🟢 Done ('.intval($totalForStatus('done')).')</option>';
        echo '</select></label>';
        
        echo '<label>Sort by: <select name="adf_so">';
        echo '<option value="priority"'.($sf==='priority'?' selected':'').'>Priority</option>';
        echo '<option value="date"'.($sf==='date'?' selected':'').'>Date</option>';
        echo '</select></label>';
        $apply_class = 'button';
        if ($fp!=='all' || $prf!=='all' || $stf!=='all' || $sf!=='priority') { $apply_class = 'button button-primary'; }
        echo '<button id="adfFiltersApply" class="'.$apply_class.'">Apply</button>';
        echo '<a href="'.esc_url(remove_query_arg(['adf_fp','adf_pr','adf_st','adf_vl','adf_so'], $_SERVER['REQUEST_URI'])).'#adfFilters'."".'" class="button">Reset</a>';
        echo '</div>';
        $base = [];
        if ($fp==='global') { $base['post_is_null'] = true; }
        else if (is_numeric($fp)) { $base['post_id'] = intval($fp); }
        if ($stf!=='all') { $base['status'] = $stf; }
        $cnt_high = $this->count_notes(array_merge($base,['priority'=>'high']));
        $cnt_medium = $this->count_notes(array_merge($base,['priority'=>'medium']));
        $cnt_low = $this->count_notes(array_merge($base,['priority'=>'low']));
        $cnt_none = $this->count_notes(array_merge($base,['priority'=>'none']));
        echo '<div class="notes-stats-row" style="margin-top:10px;display:flex;align-items:center;justify-content:space-between;gap:8px">';
        echo '<div class="notes-stats-group" style="display:flex;gap:8px;padding:4px 8px;background:#f9f9f9;border-radius:6px">';
        echo '<span style="display:inline-flex;align-items:center;gap:6px;padding:2px 10px;border:1px solid #eee;border-radius:12px;background:#fff"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#e74c3c"></span> High ('.intval($cnt_high).')</span>';
        echo '<span style="display:inline-flex;align-items:center;gap:6px;padding:2px 10px;border:1px solid #eee;border-radius:12px;background:#fff"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#f39c12"></span> Medium ('.intval($cnt_medium).')</span>';
        echo '<span style="display:inline-flex;align-items:center;gap:6px;padding:2px 10px;border:1px solid #eee;border-radius:12px;background:#fff"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#3498db"></span> Low ('.intval($cnt_low).')</span>';
        echo '<span style="display:inline-flex;align-items:center;gap:6px;padding:2px 10px;border:1px solid #eee;border-radius:12px;background:#fff"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#7f8c8d"></span> None ('.intval($cnt_none).')</span>';
        echo '</div>';
        echo '<button type="button" id="adfOpenNewNote" class="button button-primary" style="padding:8px 16px;border-radius:6px;font-weight:600">+ New note</button>';
        echo '</div>';
        echo '</form></div>';
        echo '<script>(function(){var open=document.getElementById("adfOpenNewNote");var modal=document.getElementById("adfNewNoteModal");function show(){if(modal){modal.style.display="flex";}}if(open){open.addEventListener("click",show);}})();</script>';
        $initFp = esc_js($fp);
        $initPr = esc_js($prf);
        $initSt = esc_js($stf);
        $initSo = esc_js($sf);
        echo '<script>(function(){var box=document.getElementById("adfFilters");if(!box)return;var f=box.querySelector("form");if(!f)return;var ap=document.getElementById("adfFiltersApply");if(!ap)return;var initFp="'.$initFp.'";var initPr="'.$initPr.'";var initSt="'.$initSt.'";var initSo="'.$initSo.'";function up(){var fpEl=f.querySelector("[name=\'adf_fp\']");var prEl=f.querySelector("[name=\'adf_pr\']");var stEl=f.querySelector("[name=\'adf_st\']");var soEl=f.querySelector("[name=\'adf_so\']");var cFp=fpEl?fpEl.value:"";var cPr=prEl?prEl.value:"";var cSt=stEl?stEl.value:"";var cSo=soEl?soEl.value:"";var changed=(cFp!==initFp||cPr!==initPr||cSt!==initSt||cSo!==initSo);if(changed){ap.classList.add("button-primary");}else{ap.classList.remove("button-primary");}}["adf_fp","adf_pr","adf_st","adf_so"].forEach(function(n){var el=f.querySelector("[name=\""+n+"\"]");if(el){el.addEventListener("change",up);}});})();</script>';
        $args = ['limit'=>20];
        if ($prf!=='all') { $args['priority'] = $prf; }
        if ($stf!=='all') { $args['status'] = $stf; }
        
        $args['sort'] = ($sf==='date'?'date':'priority');
        if ($fp==='global') { $args['post_is_null'] = true; }
        else if (is_numeric($fp)) { $args['post_id'] = intval($fp); }
        $notes = $this->get_notes($args);
        if (!empty($notes)) {
            $is_admin = current_user_can('manage_options');
            echo '<div class="notes-container"><div class="adf-threads">';
            foreach ($notes as $n) {
                $page = $n['post_id'] ? '<a href="'.esc_url(get_edit_post_link($n['post_id'])).'">'.esc_html(get_the_title($n['post_id'])).'</a>' : 'Global';
                $author = get_the_author_meta('display_name',$n['author']);
                $comments = $this->get_comments($n['id']);
                echo '<div class="adf-thread" style="margin:12px 0;padding:12px;border:1px solid #ddd;border-radius:6px;background:#fff">';
                echo '<div style="border-top:1px solid #eaeaea;margin:-12px -12px 8px -12px"></div>';
                echo '<div class="adf-thread-head" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
                $t = function_exists('date_i18n') ? date_i18n('H:i', strtotime($n['created_at'])) : esc_html($n['created_at']);
                
                
                $sid = intval($n['id']);
                $s = $n['status'];
                $v = isset($n['validation_status']) ? $n['validation_status'] : 'pending';
                $p = $n['priority'];
                $scolor = ($s==='pending'?'#f39c12':($s==='in_progress'?'#3498db':($s==='approved'?'#2ecc71':($s==='done'?'#2ecc71':($s==='rejected'?'#e74c3c':'#7f8c8d')))));
                $slabel = ($s==='pending'?'Pending':($s==='in_progress'?'In progress':($s==='done'?'Done':ucfirst($s))));
                $vcolor = ($v==='pending'?'#f39c12':($v==='validated'?'#2ecc71':($v==='rejected'?'#e74c3c':'#7f8c8d')));
                $vlabel = ($v==='pending'?'Validation pending':($v==='validated'?'Validated':($v==='rejected'?'Rejected':ucfirst($v))));
                $pcolor = ($p==='high'?'#e74c3c':($p==='medium'?'#f39c12':($p==='low'?'#3498db':'#7f8c8d')));
                $plabel = ($p==='high'?'High':($p==='medium'?'Medium':($p==='low'?'Low':'None')));
                echo '<span style="display:inline-flex;align-items:center;gap:6px"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:'.$pcolor.'"></span><span style="color:'.$pcolor.'">'.esc_html($n['title']).'</span></span>';
                echo '<span>· '.$page.' · <span style="font-weight:600">'.esc_html($author).'</span> · <span style="color:#e91e63">'.esc_html($t).'</span></span>';
                echo '<span style="display:inline-flex;align-items:center;gap:6px"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:'.$pcolor.'"></span><span style="color:'.$pcolor.'">'.esc_html($n['title']).'</span></span>';
                echo '<span>· '.$page.' · <span style="font-weight:600">'.esc_html($author).'</span> · <span style="color:#e91e63">'.esc_html($t).'</span></span>';
                echo '<span class="adfBadge adfStatusBadge" data-note="'.$sid.'" style="margin-left:auto;display:inline-block;padding:2px 8px;border-radius:10px;background:'.$scolor.';color:#fff;font-size:12px;line-height:18px">'.$slabel.'</span> ';
                echo '<span class="adfBadge adfValidationBadge" data-note="'.$sid.'" style="display:inline-block;padding:2px 8px;border-radius:10px;background:'.$vcolor.';color:#fff;font-size:12px;line-height:18px">'.$vlabel.'</span> ';
                echo '<span class="adfBadge adfPriorityBadge" data-note="'.$sid.'" style="display:inline-block;padding:2px 8px;border-radius:10px;background:'.$pcolor.';color:#fff;font-size:12px;line-height:18px">'.$plabel.'</span> ';
                if ($is_admin) {
                    echo '<select class="adfNoteProgress" data-note="'.$sid.'"><option value="pending"'.($n['status']==='pending'?' selected':'').'>pending</option><option value="in_progress"'.($n['status']==='in_progress'?' selected':'').'>in_progress</option><option value="done"'.($n['status']==='done'?' selected':'').'>done</option></select> ';
                    echo '<select class="adfNoteValidation" data-note="'.$sid.'"><option value="pending"'.($v==='pending'?' selected':'').'>pending</option><option value="validated"'.($v==='validated'?' selected':'').'>validated</option><option value="rejected"'.($v==='rejected'?' selected':'').'>rejected</option></select> ';
                    echo '<select class="adfNotePriority" data-note="'.$sid.'"><option value="none"'.($n['priority']==='none'?' selected':'').'>none</option><option value="low"'.($n['priority']==='low'?' selected':'').'>low</option><option value="medium"'.($n['priority']==='medium'?' selected':'').'>medium</option><option value="high"'.($n['priority']==='high'?' selected':'').'>high</option></select> ';
                    echo '<button class="button adfNoteSave" data-note="'.$sid.'">Save</button>';
                }
                echo '</div>';
                echo '<div style="border-bottom:1px solid #eaeaea;margin:8px -12px 8px -12px"></div>';
                echo '<div class="adf-thread-body" style="margin-top:8px">'.wp_kses_post($n['content']).'</div>';
                $n_media = [];
                if (!empty($n['media'])) { $nm = json_decode($n['media'], true); if (is_array($nm)) { $n_media = $nm; } }
                if (is_array($n_media) && count($n_media)) {
                    echo '<div class="adf-thread-attachments" style="margin-top:10px;color:#555">Pièces jointes: ';
                    $nlinks = [];
                    foreach ($n_media as $fname) {
                        $url = wp_nonce_url(admin_url('admin-post.php?action=adf_download_note_root_file&note_id=' . intval($n['id']) . '&file=' . rawurlencode($fname)), 'adf_notes');
                        $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                        $icon = ($ext==='pdf'?'📄':(($ext==='doc'||$ext==='docx')?'📝':(($ext==='xls'||$ext==='xlsx')?'📊':(($ext==='jpg'||$ext==='jpeg'||$ext==='png'||$ext==='gif'||$ext==='webp')?'🖼️':'📎'))));
                        $disp = $fname; if (strlen($disp) > 32) { $disp = substr($disp,0,18) . '…' . substr($disp,-10); }
                        $nlinks[] = $icon . ' <a href="'.esc_url($url).'" target="_blank">'.esc_html($disp).'</a>';
                    }
                    echo implode(' · ', $nlinks);
                    echo '</div>';
                }
                echo '<div class="adf-thread-comments" style="margin-top:12px">';
                if (!empty($comments)) {
                    echo '<ul style="margin:0;padding-left:0;list-style:none">';
                    foreach ($comments as $c) {
                        $cauthor = get_the_author_meta('display_name',$c['author']);
                        $time = function_exists('date_i18n') ? date_i18n('H:i', strtotime($c['created_at'])) : esc_html($c['created_at']);
                        echo '<li style="margin:8px 0;padding:8px 0;border-top:1px solid #eaeaea">';
                        echo '<div class="adf-reply-row" style="display:flex;align-items:flex-start;gap:8px">';
                        echo '<div style="font-weight:600;white-space:nowrap">'.esc_html($cauthor).' · '.esc_html($time).'</div>';
                        echo '<div style="flex:1">'.wp_kses_post($c['content']).'</div>';
                        echo '</div>';
                        $atts = [];
                        if (!empty($c['media'])) { $atts = json_decode($c['media'], true); }
                        if (is_array($atts) && count($atts)) {
                            echo '<div style="margin-top:6px;color:#555">Pièces jointes: ';
                            $links = [];
                            foreach ($atts as $fname) {
                                $url = wp_nonce_url(admin_url('admin-post.php?action=adf_download_note_file&comment_id=' . intval($c['id']) . '&note_id=' . intval($n['id']) . '&file=' . rawurlencode($fname)), 'adf_notes');
                                $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                                $icon = ($ext==='pdf'?'📄':(($ext==='doc'||$ext==='docx')?'📝':(($ext==='xls'||$ext==='xlsx')?'📊':(($ext==='jpg'||$ext==='jpeg'||$ext==='png'||$ext==='gif'||$ext==='webp')?'🖼️':'📎'))));
                                $disp = $fname;
                                if (strlen($disp) > 32) { $disp = substr($disp,0,18) . '…' . substr($disp,-10); }
                                $links[] = $icon . ' <a href="'.esc_url($url).'" target="_blank">'.esc_html($disp).'</a>';
                            }
                            echo implode(' · ', $links);
                            echo '</div>';
                        }
                        echo '</li>';
                    }
                    echo '</ul>';
                }
                if (current_user_can('edit_pages')) {
                    $nid = intval($n['id']);
                    $fileId = 'adfCommentFile-' . $nid;
            echo '<div class="adf-thread-add" style="margin-top:8px">';
            echo '<textarea class="adfCommentText" data-note="'.$nid.'" placeholder="Add a reply" style="width:65%;height:40px"></textarea>';
                    echo '<div class="adf-thread-actions" style="margin-top:6px;display:flex;justify-content:flex-end;align-items:center;gap:8px;max-width:65%">';
                    echo '<input type="file" id="'.$fileId.'" class="adfCommentFile" data-note="'.$nid.'" style="display:none" multiple>';
                    echo '<label for="'.$fileId.'" class="button">📎 Attach file</label>';
                    echo '<button class="button button-primary adfCommentAdd" data-note="'.$nid.'">💬 Reply</button>';
                    echo '</div>';
                    echo '<div class="adf-selected-files" data-note="'.$nid.'" style="margin-top:6px"></div>';
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
            }
            echo '</div></div>';
            $nonce = wp_create_nonce('adf_notes');
            echo "<script>(function(){
                function updateNote(id, progress, validation, priority){var fd=new FormData();fd.append('action','adf_update_note');fd.append('note_id',id);fd.append('progress',progress);fd.append('validation',validation);fd.append('priority',priority);fd.append('_wpnonce','{$nonce}');fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(function(){location.reload();});}
                function uploadFiles(files, cb){if(!files||!files.length){cb([]);return;}var tasks=Array.from(files).map(function(f){var fd=new FormData();fd.append('action','adf_upload_note_file');fd.append('file',f);fd.append('_wpnonce','{$nonce}');return fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(resp){return (resp&&resp.success&&resp.data&&resp.data.filename)?resp.data.filename:null;}).catch(function(){return null;});});Promise.all(tasks).then(function(names){cb(names.filter(function(n){return !!n;}));});}
                function icon(ext){ext=String(ext||'').toLowerCase();return ext==='pdf'?'📄':((ext==='doc'||ext==='docx')?'📝':((ext==='xls'||ext==='xlsx')?'📊':((ext==='jpg'||ext==='jpeg'||ext==='png'||ext==='gif'||ext==='webp')?'🖼️':'📎')));}function trunc(n){n=String(n||'');return n.length>32?n.slice(0,18)+'…'+n.slice(-10):n;}
                var selected = {};
                function renderSelected(id){var box=document.querySelector('.adf-selected-files[data-note=\"'+id+'\"]');if(!box)return;var arr=selected[id]||[];var html='';arr.forEach(function(f,i){var e=(f.name.split('.').pop()||'');html+=('<span data-i=\''+i+'\' style=\"display:inline-flex;align-items:center;gap:6px;padding:2px 8px;border:1px solid #ddd;border-radius:12px;margin:3px;\">'+icon(e)+' '+trunc(f.name)+' <a href=\"#\" class=\"adfFileRemove\">✖</a></span>');});box.innerHTML=html;box.querySelectorAll('.adfFileRemove').forEach(function(a){a.addEventListener('click',function(ev){ev.preventDefault();var p=this.parentElement;var idx=p&&p.getAttribute('data-i');if(idx!==null){var arr=selected[id]||[];arr.splice(parseInt(idx,10),1);selected[id]=arr;renderSelected(id);}});});}
                function addComment(id, text, mediaArr){var fd=new FormData();fd.append('action','adf_add_comment');fd.append('note_id',id);fd.append('content',text);if(mediaArr&&mediaArr.length){fd.append('media',JSON.stringify(mediaArr));}fd.append('_wpnonce','{$nonce}');fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(){location.reload();});}
                function statusMeta(v){switch(v){case 'pending': return {text:'Pending',color:'#f39c12'};case 'in_progress': return {text:'In progress',color:'#3498db'};case 'approved': return {text:'Approved',color:'#2ecc71'};case 'done': return {text:'Done',color:'#2ecc71'};case 'rejected': return {text:'Rejected',color:'#e74c3c'};default: return {text:v,color:'#7f8c8d'};}}
                function priorityMeta(v){switch(v){case 'high': return {text:'High',color:'#e74c3c'};case 'medium': return {text:'Medium',color:'#f39c12'};case 'low': return {text:'Low',color:'#3498db'};default: return {text:'None',color:'#7f8c8d'};}}
                document.querySelectorAll('.adfNoteSave').forEach(function(btn){btn.addEventListener('click',function(){var id=this.getAttribute('data-note');var pg=document.querySelector('.adfNoteProgress[data-note=\"'+id+'\"]').value;var vl=document.querySelector('.adfNoteValidation[data-note=\"'+id+'\"]').value;var pr=document.querySelector('.adfNotePriority[data-note=\"'+id+'\"]').value;updateNote(id,pg,vl,pr);});});
                document.querySelectorAll('.adfNoteProgress').forEach(function(sel){sel.addEventListener('change',function(){var id=this.getAttribute('data-note');var b=document.querySelector('.adfStatusBadge[data-note=\"'+id+'\"]');var m=statusMeta(this.value);if(b){b.textContent=m.text;b.style.background=m.color;}});});
                document.querySelectorAll('.adfNoteValidation').forEach(function(sel){sel.addEventListener('change',function(){var id=this.getAttribute('data-note');var b=document.querySelector('.adfValidationBadge[data-note=\"'+id+'\"]');var c=this.value==='validated'?'#2ecc71':(this.value==='rejected'?'#e74c3c':'#f39c12');var t=this.value==='validated'?'Validated':(this.value==='rejected'?'Rejected':'Validation pending');if(b){b.textContent=t;b.style.background=c;}});});
                document.querySelectorAll('.adfNotePriority').forEach(function(sel){sel.addEventListener('change',function(){var id=this.getAttribute('data-note');var b=document.querySelector('.adfPriorityBadge[data-note=\"'+id+'\"]');var m=priorityMeta(this.value);if(b){b.textContent=m.text;b.style.background=m.color;}});});
                document.querySelectorAll('.adfCommentFile').forEach(function(inp){inp.addEventListener('change',function(){var id=this.getAttribute('data-note');var arr=selected[id]||[];var add=Array.from(this.files||[]);var names=arr.map(function(f){return f.name;});add.forEach(function(f){if(names.indexOf(f.name)===-1){arr.push(f);}});selected[id]=arr;this.value='';renderSelected(id);});});
                document.querySelectorAll('.adfCommentAdd').forEach(function(btn){btn.addEventListener('click',function(){var id=this.getAttribute('data-note');var ta=document.querySelector('.adfCommentText[data-note=\"'+id+'\"]');var text=(ta&&ta.value)||'';var files=selected[id]||[];if(files.length){uploadFiles(files,function(names){addComment(id,text,names);});}else{addComment(id,text,[]);} });});
                document.querySelectorAll('.adfCommentText').forEach(function(ta){ta.addEventListener('focus',function(){this.style.height='140px';});ta.addEventListener('blur',function(){if((this.value||'').trim()===''){this.style.height='40px';}});});
            })();</script>";
        } else {
            echo '<p>No notes</p>';
        }
        echo '</div>';
    }

    function register_dash_widgets() {
        wp_add_dashboard_widget('adf_notes_high','ADF: High Priority Notes',[$this,'dash_high']);
        wp_add_dashboard_widget('adf_notes_recent','ADF: Recent Notes',[$this,'dash_recent']);
    }

    function dash_high() {
        $items = $this->get_notes(['priority'=>'high','status'=>'pending','limit'=>5]);
        $this->render_list($items);
    }

    function dash_recent() {
        $items = $this->get_notes(['limit'=>10]);
        $this->render_list($items);
    }

    function render_list($items) {
        echo '<table class="widefat"><thead><tr><th>Title</th><th>Page</th><th>Status</th><th>Priority</th></tr></thead><tbody>';
        if (!empty($items)) {
            foreach ($items as $n) {
                $page = $n['post_id'] ? '<a href="'.esc_url(get_edit_post_link($n['post_id'])).'">'.esc_html(get_the_title($n['post_id'])).'</a>' : 'Global';
                echo '<tr><td>'.esc_html($n['title']).'</td><td>'.$page.'</td><td>'.esc_html($n['status']).'</td><td>'.esc_html($n['priority']).'</td></tr>';
            }
        } else { echo '<tr><td colspan="4">None</td></tr>'; }
        echo '</tbody></table>';
    }

    function valid_progress($s) {
        return in_array($s,['pending','in_progress','done'],true) ? $s : 'pending';
    }
    function valid_validation($s) {
        return in_array($s,['pending','validated','rejected'],true) ? $s : 'pending';
    }
    function valid_priority($p) {
        return in_array($p,['high','medium','low','none'],true) ? $p : 'none';
    }
    function valid_ref($r) {
        $r = preg_replace('/[^a-zA-Z0-9_-]/','',$r);
        if (empty($r)) { $r = wp_generate_password(8,false,false); }
        return $r;
    }

    function ajax_create_note() {
        if (!current_user_can('edit_pages')) wp_send_json_error();
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'adf_notes')) wp_send_json_error();
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;
        if ($post_id === 0) { $post_id = null; }
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $priority_in = isset($_POST['priority']) ? sanitize_text_field($_POST['priority']) : 'none';
        $priority = $this->valid_priority($priority_in);
        $progress_in = isset($_POST['progress']) ? sanitize_text_field($_POST['progress']) : 'pending';
        $progress = $this->valid_progress($progress_in);
        $validation_in = isset($_POST['validation']) ? sanitize_text_field($_POST['validation']) : 'pending';
        $validation = current_user_can('manage_options') ? $this->valid_validation($validation_in) : 'pending';
        $ref_in = isset($_POST['ref']) ? sanitize_text_field($_POST['ref']) : '';
        $ref = $this->valid_ref($ref_in);
        $media = [];
        if (!empty($_POST['media'])) {
            $m = json_decode(stripslashes($_POST['media']), true);
            if (is_array($m)) {
                foreach ($m as $it) { if (is_string($it) && $it!=='') { $media[] = basename($it); } }
            }
        }
        $this->insert_note($post_id,$title,$content,get_current_user_id(),$progress,$validation,$priority,$media,$ref);
        wp_send_json_success();
    }

    function ajax_add_comment() {
        if (!current_user_can('edit_pages')) wp_send_json_error();
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'adf_notes')) wp_send_json_error();
        $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $media = [];
        if (!empty($_POST['media'])) {
            $m = json_decode(stripslashes($_POST['media']), true);
            if (is_array($m)) {
                foreach ($m as $it) {
                    if (is_string($it) && $it !== '') { $media[] = basename($it); }
                }
            }
        }
        $this->insert_comment($note_id,get_current_user_id(),$content,$media);
        wp_send_json_success();
    }

    function ajax_update_note() {
        if (!current_user_can('manage_options')) wp_send_json_error();
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'adf_notes')) wp_send_json_error();
        $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
        $progress_in = isset($_POST['progress']) ? sanitize_text_field($_POST['progress']) : '';
        $validation_in = isset($_POST['validation']) ? sanitize_text_field($_POST['validation']) : '';
        $priority_in = isset($_POST['priority']) ? sanitize_text_field($_POST['priority']) : '';
        $data = [];
        if ($progress_in) { $data['status'] = $this->valid_progress($progress_in); }
        if ($validation_in) { $data['validation_status'] = $this->valid_validation($validation_in); }
        if ($priority_in) { $data['priority'] = $this->valid_priority($priority_in); }
        if (empty($data)) wp_send_json_error();
        $this->update_note($note_id,$data,current_user_can('manage_options') ? get_current_user_id() : null);
        wp_send_json_success();
    }

    function ajax_upload_file() {
        if (!current_user_can('edit_pages')) wp_send_json_error();
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'adf_notes')) wp_send_json_error();
        if (empty($_FILES['file'])) wp_send_json_error();
        $max_bytes = 10 * 1024 * 1024;
        if (!empty($_FILES['file']['size']) && intval($_FILES['file']['size']) > $max_bytes) {
            wp_send_json_error(['message'=>'File too large (max 10MB)']);
        }
        $allowed_mimes = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        $blocked_ext = ['php','phtml','phar','js','html','htm','exe','dll','bat','cmd','sh','com','scr','msi'];
        $check = wp_check_filetype_and_ext($_FILES['file']['tmp_name'], $_FILES['file']['name'], $allowed_mimes);
        $ext = isset($check['ext']) ? strtolower($check['ext']) : '';
        $type = isset($check['type']) ? strtolower($check['type']) : '';
        if (!$ext || !$type) { wp_send_json_error(['message'=>'Unsupported file type']); }
        if (in_array($ext, $blocked_ext, true)) { wp_send_json_error(['message'=>'Blocked file type']); }
        $safe_name = sanitize_file_name($_FILES['file']['name']);
        $base = pathinfo($safe_name, PATHINFO_FILENAME);
        $upload = wp_upload_dir();
        $root = trailingslashit($upload['basedir']) . 'adf-notes';
        if (!is_dir($root)) wp_mkdir_p($root);
        $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
        $ref = isset($_POST['ref']) ? sanitize_text_field($_POST['ref']) : '';
        if ($note_id) {
            global $wpdb; $table = $wpdb->prefix . 'adf_notes';
            $ref_row = $wpdb->get_row($wpdb->prepare("SELECT ref FROM {$table} WHERE id=%d", $note_id), ARRAY_A);
            $ref = $ref_row && !empty($ref_row['ref']) ? $ref_row['ref'] : $ref;
        }
        if (empty($ref)) { $ref = wp_generate_password(8,false,false); }
        $dir = $root . '/' . $ref;
        if (!is_dir($dir)) wp_mkdir_p($dir);
        $name = $base . '.' . $ext;
        $target = $dir . '/' . $name;
        if (file_exists($target)) { wp_send_json_error(['message'=>'File already exists']); }
        if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            wp_send_json_success(['path'=>$target,'filename'=>$name,'ext'=>$ext,'type'=>$type,'size'=>intval($_FILES['file']['size']),'ref'=>$ref]);
        } else {
            wp_send_json_error();
        }
    }

    function download_file() {
        if (!current_user_can('edit_pages')) wp_die('');
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'],'adf_notes')) wp_die('');
        $note_id = isset($_GET['note_id']) ? intval($_GET['note_id']) : 0;
        $comment_id = isset($_GET['comment_id']) ? intval($_GET['comment_id']) : 0;
        $file = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
        if (!$note_id || !$comment_id || !$file) wp_die('');
        global $wpdb;
        $ctable = $wpdb->prefix . 'adf_notes_comments';
        $ntable = $wpdb->prefix . 'adf_notes';
        $crow = $wpdb->get_row($wpdb->prepare("SELECT media FROM {$ctable} WHERE id=%d AND note_id=%d", $comment_id, $note_id), ARRAY_A);
        if (!$crow) wp_die('');
        $nrow = $wpdb->get_row($wpdb->prepare("SELECT ref FROM {$ntable} WHERE id=%d", $note_id), ARRAY_A);
        $media = [];
        if (!empty($crow['media'])) {
            $m = json_decode($crow['media'], true);
            if (is_array($m)) { $media = $m; }
        }
        if (!in_array($file, $media, true)) wp_die('');
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'adf-notes';
        $ref = $nrow && !empty($nrow['ref']) ? $nrow['ref'] : '';
        $path = $dir . '/' . ( $ref ? ($ref . '/') : '' ) . $file;
        if (!file_exists($path)) wp_die('');
        $ft = wp_check_filetype($file);
        $mime = $ft && !empty($ft['type']) ? $ft['type'] : 'application/octet-stream';
        header('Content-Type: '.$mime);
        header('Content-Length: '.filesize($path));
        header('Content-Disposition: attachment; filename="'.$file.'"');
        readfile($path);
        exit;
    }

    function download_note_root_file() {
        if (!current_user_can('edit_pages')) wp_die('');
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'],'adf_notes')) wp_die('');
        $note_id = isset($_GET['note_id']) ? intval($_GET['note_id']) : 0;
        $file = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
        if (!$note_id || !$file) wp_die('');
        global $wpdb;
        $ntable = $wpdb->prefix . 'adf_notes';
        $row = $wpdb->get_row($wpdb->prepare("SELECT media, ref FROM {$ntable} WHERE id=%d", $note_id), ARRAY_A);
        if (!$row) wp_die('');
        $media = [];
        if (!empty($row['media'])) { $m = json_decode($row['media'], true); if (is_array($m)) { $media = $m; } }
        if (!in_array($file, $media, true)) wp_die('');
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'adf-notes';
        $ref = !empty($row['ref']) ? $row['ref'] : '';
        $path = $dir . '/' . ( $ref ? ($ref . '/') : '' ) . $file;
        if (!file_exists($path)) wp_die('');
        $ft = wp_check_filetype($file);
        $mime = $ft && !empty($ft['type']) ? $ft['type'] : 'application/octet-stream';
        header('Content-Type: '.$mime);
        header('Content-Length: '.filesize($path));
        header('Content-Disposition: attachment; filename="'.$file.'"');
        readfile($path);
        exit;
    }


    function insert_note($post_id,$title,$content,$author,$progress,$validation,$priority,$media,$ref) {
        global $wpdb;
        $table = $wpdb->prefix . 'adf_notes';
        $now = current_time('mysql');
        $wpdb->insert($table,[
            'post_id'=>$post_id ?: null,
            'title'=>$title,
            'content'=>$content,
            'author'=>$author,
            'status'=>$progress,
            'validation_status'=>$validation,
            'priority'=>$priority,
            'media'=>json_encode($media),
            'validated_by'=>null,
            'ref'=>$ref,
            'created_at'=>$now,
            'updated_at'=>$now,
        ],['%d','%s','%s','%d','%s','%s','%s','%s','%d','%s','%s','%s']);
    }

    function insert_comment($note_id,$author,$content,$media) {
        global $wpdb;
        $table = $wpdb->prefix . 'adf_notes_comments';
        $now = current_time('mysql');
        $wpdb->insert($table,[
            'note_id'=>$note_id,
            'author'=>$author,
            'content'=>$content,
            'media'=>json_encode($media),
            'created_at'=>$now,
        ],['%d','%d','%s','%s','%s']);
    }

    function update_note($note_id,$data,$validator_user_id=null) {
        global $wpdb;
        $table = $wpdb->prefix . 'adf_notes';
        $data['updated_at'] = current_time('mysql');
        if (!empty($data['validation_status']) && $data['validation_status']==='validated' && $validator_user_id) {
            $data['validated_by'] = $validator_user_id;
        }
        $formats = [];
        foreach ($data as $k=>$v) {
            if ($k==='validated_by') { $formats[] = '%d'; }
            else if ($k==='updated_at') { $formats[] = '%s'; }
            else { $formats[] = '%s'; }
        }
        $wpdb->update($table,$data,['id'=>$note_id],$formats,['%d']);
    }

    function get_notes($args=[]) {
        global $wpdb;
        $table = $wpdb->prefix . 'adf_notes';
        $w = [];$params = [];
        if (!empty($args['post_is_null'])) { $w[] = 'post_id IS NULL'; }
        else if (!empty($args['post_id'])) { $w[] = 'post_id = %d'; $params[] = intval($args['post_id']); }
        if (!empty($args['priority'])) { $w[] = 'priority = %s'; $params[] = sanitize_text_field($args['priority']); }
        if (!empty($args['status'])) { $w[] = 'status = %s'; $params[] = sanitize_text_field($args['status']); }
        if (!empty($args['validation_status'])) { $w[] = 'validation_status = %s'; $params[] = sanitize_text_field($args['validation_status']); }
        $where = count($w) ? ('WHERE ' . implode(' AND ', $w)) : '';
        $limit = isset($args['limit']) ? intval($args['limit']) : 20;
        $params[] = $limit;
        $order = 'ORDER BY created_at DESC';
        if (!empty($args['sort'])) {
            if ($args['sort'] === 'priority') { $order = "ORDER BY FIELD(priority,'high','medium','low','none') ASC, created_at ASC"; }
            else if ($args['sort'] === 'date') { $order = 'ORDER BY created_at DESC'; }
        }
        $sql = $wpdb->prepare("SELECT id, post_id, title, content, author, status, validation_status, priority, media, validated_by, ref, created_at FROM {$table} {$where} {$order} LIMIT %d", ...$params);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    function count_notes($args=[]) {
        global $wpdb;
        $table = $wpdb->prefix . 'adf_notes';
        $w = [];$params = [];
        if (!empty($args['post_is_null'])) { $w[] = 'post_id IS NULL'; }
        else if (!empty($args['post_id'])) { $w[] = 'post_id = %d'; $params[] = intval($args['post_id']); }
        if (!empty($args['priority'])) { $w[] = 'priority = %s'; $params[] = sanitize_text_field($args['priority']); }
        if (!empty($args['status'])) { $w[] = 'status = %s'; $params[] = sanitize_text_field($args['status']); }
        if (!empty($args['validation_status'])) { $w[] = 'validation_status = %s'; $params[] = sanitize_text_field($args['validation_status']); }
        if (!empty($args['exclude_status'])) { $w[] = 'status != %s'; $params[] = sanitize_text_field($args['exclude_status']); }
        $where = count($w) ? ('WHERE ' . implode(' AND ', $w)) : '';
        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} {$where}", ...$params);
        $n = $wpdb->get_var($sql);
        return intval($n);
    }

    function get_pages_counts($args=[]) {
        global $wpdb;
        $table = $wpdb->prefix . 'adf_notes';
        $w = [];$params = [];
        if (!empty($args['priority'])) { $w[] = 'priority = %s'; $params[] = sanitize_text_field($args['priority']); }
        if (!empty($args['status'])) { $w[] = 'status = %s'; $params[] = sanitize_text_field($args['status']); }
        if (!empty($args['exclude_status'])) { $w[] = 'status != %s'; $params[] = sanitize_text_field($args['exclude_status']); }
        $where = count($w) ? ('WHERE ' . implode(' AND ', $w)) : '';
        $sql = $wpdb->prepare("SELECT post_id, COUNT(*) AS c FROM {$table} {$where} GROUP BY post_id", ...$params);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    function ensure_schema() {
        global $wpdb;
        $table = $wpdb->prefix . 'adf_notes';
        $col = $wpdb->get_var($wpdb->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'validation_status'", DB_NAME, $table));
        if (!$col) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN validation_status VARCHAR(20) NOT NULL DEFAULT 'pending'");
            $wpdb->query("UPDATE {$table} SET validation_status='validated', status='done' WHERE status='approved'");
            $wpdb->query("UPDATE {$table} SET validation_status='rejected', status='pending' WHERE status='rejected'");
        }
        $col2 = $wpdb->get_var($wpdb->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'ref'", DB_NAME, $table));
        if (!$col2) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN ref VARCHAR(16) NOT NULL DEFAULT ''");
            $rows = $wpdb->get_results("SELECT id FROM {$table}", ARRAY_A);
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $rid = intval($r['id']);
                    $code = wp_generate_password(8, false, false);
                    $wpdb->update($table, ['ref'=>$code], ['id'=>$rid], ['%s'], ['%d']);
                }
            }
        }
    }

    function get_comments($note_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'adf_notes_comments';
        $sql = $wpdb->prepare("SELECT id, note_id, author, content, media, created_at FROM {$table} WHERE note_id = %d ORDER BY created_at ASC", intval($note_id));
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
}

new ADF_InternalNotes();