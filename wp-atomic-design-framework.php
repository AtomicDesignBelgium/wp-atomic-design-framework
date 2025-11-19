<?php
/**
 * Plugin Name: WP Atomic Design Framework
 * Plugin URI: https://github.com/AtomicDesignBelgium/wp-atomic-design-framework.git
 * Description: Modular framework plugin for Atomic Design tools.
 * Author: Bernard Coubeaux / Atomic Design Belgium SRL
 * Version: 1.0.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Update URI: https://raw.githubusercontent.com/AtomicDesignBelgium/wp-atomic-design-framework/main/update.json
 */

if (!defined('ABSPATH')) exit;

define('ADF_PATH', plugin_dir_path(__FILE__));
define('ADF_URL', plugin_dir_url(__FILE__));

require_once ADF_PATH . 'includes/helpers.php';
require_once ADF_PATH . 'core/class-settings.php';
require_once ADF_PATH . 'core/class-updater.php';

$options = adf_get_options();

if (!empty($options['enable_dev_tags'])) {
    require_once ADF_PATH . 'modules/dev-status/class-devstatus.php';
}

if (!empty($options['hide_author'])) {
    require_once ADF_PATH . 'modules/author-column/class-author.php';
}
