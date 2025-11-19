<?php
namespace ADF;
if (!defined('ABSPATH')) exit;

class ADF_DevStatus {
    function __construct() {
        add_action('init', [$this,'register']);
        add_action('init', [$this,'ensure_terms']);
        $opt = function_exists('adf_get_options') ? adf_get_options() : [];
        $enabled = !empty($opt['enable_dev_tags']);
        if ($enabled) {
            add_action('add_meta_boxes', [$this,'add_box']);
            add_action('save_post', [$this,'save_box']);
            add_action('restrict_manage_posts', [$this,'add_list_filter']);
            add_action('pre_get_posts', [$this,'apply_list_filter']);
            foreach ($this->tracked_types() as $pt) {
                add_filter('bulk_actions-edit-' . $pt, [$this,'register_bulk_actions']);
                add_filter('handle_bulk_actions-edit-' . $pt, [$this,'handle_bulk_action'], 10, 3);
            }
            add_action('admin_notices', [$this,'bulk_action_notice']);
        }
    }
    function tracked_types() {
        return ['page'];
    }
    function lang() {
        $l = get_locale();
        return (strpos($l,'fr') === 0) ? 'fr' : 'en';
    }
    function labels_map() {
        return [
            'approved' => ['fr'=>'🟩 Validé','en'=>'🟩 Approved'],
            'pending-validation' => ['fr'=>'🟧 Pour validation','en'=>'🟧 Pending validation'],
            'in-development' => ['fr'=>'🟦 En cours de développement','en'=>'🟦 In development'],
            'empty' => ['fr'=>'⬜ Non commencé','en'=>'⬜ Not started'],
            'blocked' => ['fr'=>'🟥 Bloqué (contenu manquant)','en'=>'🟥 Blocked (missing content)'],
        ];
    }
    function label_for($slug) {
        $m = $this->labels_map();
        $lang = $this->lang();
        return isset($m[$slug][$lang]) ? $m[$slug][$lang] : $slug;
    }
    function ui_title() {
        return $this->lang() === 'fr' ? 'Statut de dev' : 'Dev Status';
    }
    function register() {
        register_taxonomy('dev_status',$this->tracked_types(),[
            'public'=>false,
            'show_ui'=>false,
            'hierarchical'=>false,
            'show_admin_column'=>true,
            'show_in_rest'=>true,
            'labels'=>[
                'name'=>$this->ui_title(),
                'singular_name'=>$this->ui_title(),
                'menu_name'=>$this->ui_title(),
            ],
        ]);
    }
    function terms_list() {
        return [
            ['slug'=>'approved'],
            ['slug'=>'pending-validation'],
            ['slug'=>'in-development'],
            ['slug'=>'empty'],
            ['slug'=>'blocked'],
        ];
    }
    function ensure_terms() {
        foreach ($this->terms_list() as $t) {
            $slug = $t['slug'];
            $label = $this->label_for($slug);
            $exists = term_exists($slug, 'dev_status');
            if (!$exists) {
                wp_insert_term($label, 'dev_status', ['slug'=>$slug]);
            } else {
                $term_id = is_array($exists) ? $exists['term_id'] : $exists;
                wp_update_term($term_id, 'dev_status', ['name'=>$label]);
            }
        }
    }
    function add_box() {
        foreach ($this->tracked_types() as $pt) {
            add_meta_box('adf_dev_status',$this->ui_title(),[$this,'box_html'],$pt,'side');
        }
    }
    function box_html($post) {
        wp_nonce_field('adf_dev_status','adf_dev_status_nonce');
        $current = wp_get_object_terms($post->ID,'dev_status',['fields'=>'ids']);
        $selected = is_array($current) && count($current) ? intval($current[0]) : 0;
        $terms = get_terms(['taxonomy'=>'dev_status','hide_empty'=>false]);
        echo '<select name="adf_dev_status" style="width:100%">';
        echo '<option value="0">' . ($this->lang()==='fr' ? '— Sélectionner —' : '— Select —') . '</option>';
        foreach ($terms as $term) {
            $sel = $selected === intval($term->term_id) ? ' selected' : '';
            echo '<option value="'.intval($term->term_id).'"'.$sel.'>'.esc_html($term->name).'</option>';
        }
        echo '</select>';
    }
    function save_box($post_id) {
        if (!isset($_POST['adf_dev_status_nonce'])) return;
        if (!wp_verify_nonce($_POST['adf_dev_status_nonce'],'adf_dev_status')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        $post = get_post($post_id);
        $types = $this->tracked_types();
        if (!$post || !in_array($post->post_type,$types, true)) return;
        if (!current_user_can('edit_post',$post_id)) return;
        $term_id = isset($_POST['adf_dev_status']) ? intval($_POST['adf_dev_status']) : -1;
        if ($term_id > 0) {
            wp_set_object_terms($post_id, [$term_id], 'dev_status', false);
        } else {
            $current = wp_get_object_terms($post_id,'dev_status',['fields'=>'ids']);
            if (empty($current)) {
                $t = get_term_by('slug','empty','dev_status');
                wp_set_object_terms($post_id, $t ? [$t->term_id] : [], 'dev_status', false);
            } else {
                if ($term_id === 0) { wp_set_object_terms($post_id, [], 'dev_status', false); }
            }
        }
    }
    function add_list_filter($post_type) {
        if (!in_array($post_type,$this->tracked_types(), true)) return;
        $selected = isset($_GET['adf_dev_status_filter']) ? sanitize_text_field($_GET['adf_dev_status_filter']) : '';
        $terms = get_terms(['taxonomy'=>'dev_status','hide_empty'=>false]);
        echo '<select name="adf_dev_status_filter" class="postform">';
        echo '<option value="">' . ($this->lang()==='fr' ? 'Statut de dev — Tous' : 'Dev Status — All') . '</option>';
        foreach ($terms as $t) {
            $sel = ($selected === $t->slug) ? ' selected' : '';
            echo '<option value="'.esc_attr($t->slug).'"'.$sel.'>'.esc_html($t->name).'</option>';
        }
        echo '</select>';
    }
    function apply_list_filter($query) {
        if (!is_admin() || !$query->is_main_query()) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : ($screen ? $screen->post_type : '');
        if (!in_array($post_type,$this->tracked_types(), true)) return;
        $slug = isset($_GET['adf_dev_status_filter']) ? sanitize_text_field($_GET['adf_dev_status_filter']) : '';
        if ($slug) {
            $query->set('tax_query', [
                [
                    'taxonomy'=>'dev_status',
                    'field'=>'slug',
                    'terms'=>[$slug],
                ]
            ]);
        }
    }
    function register_bulk_actions($actions) {
        $prefix = ($this->lang()==='fr' ? 'Définir Statut: ' : 'Set Dev Status: ');
        $actions['adf_bulk_set_dev_status_approved'] = $prefix . $this->label_for('approved');
        $actions['adf_bulk_set_dev_status_pending_validation'] = $prefix . $this->label_for('pending-validation');
        $actions['adf_bulk_set_dev_status_in_development'] = $prefix . $this->label_for('in-development');
        $actions['adf_bulk_set_dev_status_empty'] = $prefix . $this->label_for('empty');
        $actions['adf_bulk_set_dev_status_blocked'] = $prefix . $this->label_for('blocked');
        return $actions;
    }
    function handle_bulk_action($redirect_to, $doaction, $post_ids) {
        $map = [
            'adf_bulk_set_dev_status_approved' => 'approved',
            'adf_bulk_set_dev_status_pending_validation' => 'pending-validation',
            'adf_bulk_set_dev_status_in_development' => 'in-development',
            'adf_bulk_set_dev_status_empty' => 'empty',
            'adf_bulk_set_dev_status_blocked' => 'blocked',
        ];
        if (!isset($map[$doaction])) return $redirect_to;
        $slug = $map[$doaction];
        $term = get_term_by('slug', $slug, 'dev_status');
        $count = 0;
        foreach ($post_ids as $pid) {
            if (!current_user_can('edit_post', $pid)) continue;
            wp_set_object_terms($pid, $term ? [$term->term_id] : [], 'dev_status', false);
            $count++;
        }
        $redirect_to = add_query_arg(['adf_bulk_set_dev_status' => $count], $redirect_to);
        return $redirect_to;
    }
    function bulk_action_notice() {
        if (!isset($_REQUEST['adf_bulk_set_dev_status'])) return;
        $count = intval($_REQUEST['adf_bulk_set_dev_status']);
        $msg = ($this->lang()==='fr') ? 'Statut de dev mis à jour pour ' : 'Dev Status updated for ';
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg . $count) . ' ' . ($this->lang()==='fr' ? 'élément(s).' : 'item(s).') . '</p></div>';
    }
}
new ADF_DevStatus();
