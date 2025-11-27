<?php
namespace ADF;
if (!defined('ABSPATH')) exit;

class ADF_Notes_Actions {
    static function ajax_create_note() {
        if (!current_user_can('edit_pages')) wp_send_json_error();
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'adf_notes')) wp_send_json_error();
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;
        if ($post_id === 0) { $post_id = null; }
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $priority_in = isset($_POST['priority']) ? sanitize_text_field($_POST['priority']) : 'none';
        $priority = ADF_Notes_Controller::valid_priority($priority_in);
        $progress_in = isset($_POST['progress']) ? sanitize_text_field($_POST['progress']) : 'pending';
        $progress = ADF_Notes_Controller::valid_progress($progress_in);
        $validation_in = isset($_POST['validation']) ? sanitize_text_field($_POST['validation']) : 'pending';
        $validation = current_user_can('manage_options') ? ADF_Notes_Controller::valid_validation($validation_in) : 'pending';
        $ref_in = isset($_POST['ref']) ? sanitize_text_field($_POST['ref']) : '';
        $ref = ADF_Notes_Controller::valid_ref($ref_in);
        $media = [];
        if (!empty($_POST['media'])) {
            $m = json_decode(stripslashes($_POST['media']), true);
            if (is_array($m)) {
                foreach ($m as $it) { if (is_string($it) && $it!=='') { $media[] = basename($it); } }
            }
        }
        ADF_Notes_Controller::insert_note($post_id,$title,$content,get_current_user_id(),$progress,$validation,$priority,$media,$ref);
        wp_send_json_success();
    }

    static function ajax_add_comment() {
        if (!current_user_can('edit_pages')) wp_send_json_error();
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'adf_notes')) wp_send_json_error();
        $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $media = [];
        if (!empty($_POST['media'])) {
            $m = json_decode(stripslashes($_POST['media']), true);
            if (is_array($m)) {
                foreach ($m as $it) { if (is_string($it) && $it !== '') { $media[] = basename($it); } }
            }
        }
        ADF_Notes_Controller::insert_comment($note_id,get_current_user_id(),$content,$media);
        wp_send_json_success();
    }

    static function ajax_update_note() {
        if (!current_user_can('manage_options')) wp_send_json_error();
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'adf_notes')) wp_send_json_error();
        $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
        $progress_in = isset($_POST['progress']) ? sanitize_text_field($_POST['progress']) : '';
        $validation_in = isset($_POST['validation']) ? sanitize_text_field($_POST['validation']) : '';
        $priority_in = isset($_POST['priority']) ? sanitize_text_field($_POST['priority']) : '';
        $data = [];
        if ($progress_in) { $data['status'] = ADF_Notes_Controller::valid_progress($progress_in); }
        if ($validation_in) { $data['validation_status'] = ADF_Notes_Controller::valid_validation($validation_in); }
        if ($priority_in) { $data['priority'] = ADF_Notes_Controller::valid_priority($priority_in); }
        if (empty($data)) wp_send_json_error();
        ADF_Notes_Controller::update_note($note_id,$data,current_user_can('manage_options') ? get_current_user_id() : null);
        wp_send_json_success();
    }

    static function ajax_upload_file() {
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

    static function download_file() {
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
        if (!empty($crow['media'])) { $m = json_decode($crow['media'], true); if (is_array($m)) { $media = $m; } }
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

    static function download_note_root_file() {
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
}

