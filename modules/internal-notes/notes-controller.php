<?php
namespace ADF;
if (!defined('ABSPATH')) exit;

class ADF_Notes_Controller {
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

    static function ensure_schema() {
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

    static function valid_progress($s) {
        return in_array($s,['pending','in_progress','done'],true) ? $s : 'pending';
    }
    static function valid_validation($s) {
        return in_array($s,['pending','validated','rejected'],true) ? $s : 'pending';
    }
    static function valid_priority($p) {
        return in_array($p,['high','medium','low','none'],true) ? $p : 'none';
    }
    static function valid_ref($r) {
        $r = preg_replace('/[^a-zA-Z0-9_-]/','',$r);
        if (empty($r)) { $r = wp_generate_password(8,false,false); }
        return $r;
    }

    static function insert_note($post_id,$title,$content,$author,$progress,$validation,$priority,$media,$ref) {
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

    static function insert_comment($note_id,$author,$content,$media) {
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

    static function update_note($note_id,$data,$validator_user_id=null) {
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

    static function get_notes($args=[]) {
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

    static function count_notes($args=[]) {
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

    static function get_pages_counts($args=[]) {
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

    static function get_comments($note_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'adf_notes_comments';
        $sql = $wpdb->prepare("SELECT id, note_id, author, content, media, created_at FROM {$table} WHERE note_id = %d ORDER BY created_at ASC", intval($note_id));
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
}

