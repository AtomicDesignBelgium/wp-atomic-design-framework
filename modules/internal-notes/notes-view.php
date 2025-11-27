<?php
namespace ADF;
if (!defined('ABSPATH')) exit;

class ADF_Notes_View {
    static function box_html($post) {
        wp_nonce_field('adf_notes','adf_notes_nonce');
        echo '<div id="adfNotesBox">';
        echo '<input type="hidden" id="adfNotePostIdBox" value="'.intval($post->ID).'">';
        $notes = ADF_Notes_Controller::get_notes(['post_id'=>$post->ID,'limit'=>5]);
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
        }
    }

    static function admin_page() {
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
            echo '</div>';
            echo '</div>';
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
        $page_rows = ADF_Notes_Controller::get_pages_counts(['exclude_status'=>'done','priority'=>$prf!=='all'?$prf:null,'status'=>$stf!=='all'?$stf:null]);
        $global_count = 0; $page_opts = [];
        foreach ($page_rows as $r) { $pid = isset($r['post_id']) ? intval($r['post_id']) : 0; $cnt = isset($r['c']) ? intval($r['c']) : 0; if ($pid===0) { $global_count = $cnt; } else { $page_opts[] = ['id'=>$pid,'count'=>$cnt,'title'=>get_the_title($pid)]; } }
        echo '<option value="global"'.($fp==='global'?' selected':'').'>Global ('.$global_count.')</option>';
        foreach ($page_opts as $po) { echo '<option value="'.intval($po['id']).'"'.(strval($fp)==strval($po['id'])?' selected':'').'>'.esc_html($po['title']).' ('.intval($po['count']).')</option>'; }
        echo '<option value="all"'.($fp==='all'?' selected':'').'>All pages ('.ADF_Notes_Controller::count_notes(['exclude_status'=>'done','priority'=>$prf!=='all'?$prf:null,'status'=>$stf!=='all'?$stf:null]).')</option>';
        echo '</select></label>';
        echo '</div>';
        echo '<label>Filter by priority: <select name="adf_pr">';
        $totalForPriority = function($val) use ($fp,$stf) {
            $args = ['status'=>$stf!=='all'?$stf:null];
            if ($fp==='global') { $args['post_is_null'] = true; }
            else if (is_numeric($fp)) { $args['post_id'] = intval($fp); }
            if ($val && $val!=='all') { $args['priority'] = $val; }
            return ADF_Notes_Controller::count_notes($args);
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
            return ADF_Notes_Controller::count_notes($args);
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
        $cnt_high = ADF_Notes_Controller::count_notes(array_merge($base,['priority'=>'high']));
        $cnt_medium = ADF_Notes_Controller::count_notes(array_merge($base,['priority'=>'medium']));
        $cnt_low = ADF_Notes_Controller::count_notes(array_merge($base,['priority'=>'low']));
        $cnt_none = ADF_Notes_Controller::count_notes(array_merge($base,['priority'=>'none']));
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

        $args = ['limit'=>20];
        if ($prf!=='all') { $args['priority'] = $prf; }
        if ($stf!=='all') { $args['status'] = $stf; }
        $args['sort'] = ($sf==='date'?'date':'priority');
        if ($fp==='global') { $args['post_is_null'] = true; }
        else if (is_numeric($fp)) { $args['post_id'] = intval($fp); }
        $notes = ADF_Notes_Controller::get_notes($args);
        if (!empty($notes)) {
            $is_admin = current_user_can('manage_options');
            echo '<div class="notes-container"><div class="adf-threads">';
            foreach ($notes as $n) {
                $page = $n['post_id'] ? '<a href="'.esc_url(get_edit_post_link($n['post_id'])).'">'.esc_html(get_the_title($n['post_id'])).'</a>' : 'Global';
                $author = get_the_author_meta('display_name',$n['author']);
                $comments = ADF_Notes_Controller::get_comments($n['id']);
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
        } else {
            echo '<p>No notes</p>';
        }
        echo '</div>';
    }

    static function register_dash_widgets() {
        wp_add_dashboard_widget('adf_notes_high','ADF: High Priority Notes',[self::class,'dash_high']);
        wp_add_dashboard_widget('adf_notes_recent','ADF: Recent Notes',[self::class,'dash_recent']);
    }

    static function dash_high() {
        $items = ADF_Notes_Controller::get_notes(['priority'=>'high','status'=>'pending','limit'=>5]);
        self::render_list($items);
    }

    static function dash_recent() {
        $items = ADF_Notes_Controller::get_notes(['limit'=>10]);
        self::render_list($items);
    }

    static function render_list($items) {
        echo '<table class="widefat"><thead><tr><th>Title</th><th>Page</th><th>Status</th><th>Priority</th></tr></thead><tbody>';
        if (!empty($items)) {
            foreach ($items as $n) {
                $page = $n['post_id'] ? '<a href="'.esc_url(get_edit_post_link($n['post_id'])).'">'.esc_html(get_the_title($n['post_id'])).'</a>' : 'Global';
                echo '<tr><td>'.esc_html($n['title']).'</td><td>'.$page.'</td><td>'.esc_html($n['status']).'</td><td>'.esc_html($n['priority']).'</td></tr>';
            }
        } else { echo '<tr><td colspan="4">None</td></tr>'; }
        echo '</tbody></table>';
    }
}

