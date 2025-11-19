<?php
if (!defined('ABSPATH')) exit;

function adf_sanitize_options($input) {
    if (!is_array($input)) { $input = []; }
    return [
        'enable_dev_tags' => !empty($input['enable_dev_tags']) ? 1 : 0,
        'hide_author' => !empty($input['hide_author']) ? 1 : 0,
    ];
}

function adf_register_settings() {
    register_setting('atomic_design_tools_group','atomic_design_tools_settings',[
        'type'=>'array',
        'sanitize_callback'=>'adf_sanitize_options',
        'default'=>[],
    ]);
}
add_action('admin_init','adf_register_settings');

function adf_add_menu() {
    add_options_page('WP Atomic Design','WP Atomic Design','manage_options','wp-atomic-design','adf_settings_html');
}
add_action('admin_menu','adf_add_menu');

function adf_settings_html() {
    $options = adf_get_options(); ?>
    <div class="wrap"><h1>WP Atomic Design Framework</h1>
    <form method="post" action="options.php">
        <?php settings_fields('atomic_design_tools_group'); ?>
        <table class="form-table">
            <tr><th>Enable Dev Tags</th><td><input type="checkbox" name="atomic_design_tools_settings[enable_dev_tags]" value="1" <?php checked(1, isset($options['enable_dev_tags']) ? $options['enable_dev_tags'] : 0); ?>></td></tr>
            <tr><th>Hide Author Column</th><td><input type="checkbox" name="atomic_design_tools_settings[hide_author]" value="1" <?php checked(1, isset($options['hide_author']) ? $options['hide_author'] : 0); ?>></td></tr>
        </table>
        <?php submit_button(); ?>
    </form></div>
    <div class="wrap" style="margin-top:10px;">
        <a class="button" href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=adf_force_update'),'adf_force_update'); ?>">Check for Updates</a>
    </div>
<?php }

function adf_force_update() {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'],'adf_force_update')) return;
    delete_site_transient('update_plugins');
    wp_safe_redirect(admin_url('update-core.php?force-check=1'));
    exit;
}
add_action('admin_post_adf_force_update','adf_force_update');