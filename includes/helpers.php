<?php
if (!defined('ABSPATH')) exit;

function adf_get_options() {
    return get_option('atomic_design_tools_settings', []);
}
