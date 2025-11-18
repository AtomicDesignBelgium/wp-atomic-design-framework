<?php
if (!defined('ABSPATH')) exit;

function adf_get_options() {
    $o = get_option('atomic_design_tools_settings', []);
    if (!is_array($o)) { $o = []; }
    $o['enable_dev_tags'] = !empty($o['enable_dev_tags']) ? 1 : 0;
    $o['hide_author'] = !empty($o['hide_author']) ? 1 : 0;
    return $o;
}
