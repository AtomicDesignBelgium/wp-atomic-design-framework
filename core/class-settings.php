<?php
if (!defined('ABSPATH')) exit;

function adf_register_settings() {
    register_setting('atomic_design_tools_group','atomic_design_tools_settings');
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
            <tr><th>Enable Dev Tags</th><td><input type="checkbox" name="atomic_design_tools_settings[enable_dev_tags]" value="1" <?php checked(1, @$options['enable_dev_tags']); ?>></td></tr>
            <tr><th>Hide Author Column</th><td><input type="checkbox" name="atomic_design_tools_settings[hide_author]" value="1" <?php checked(1, @$options['hide_author']); ?>></td></tr>
        </table>
        <?php submit_button(); ?>
    </form></div>
<?php }