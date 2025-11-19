<?php
namespace ADF;
if (!defined('ABSPATH')) exit;

class ADF_Updater {
    function __construct() {
        add_filter('pre_set_site_transient_update_plugins', [$this,'check_update']);
        add_filter('plugins_api', [$this,'plugin_info'], 10, 3);
    }
    function endpoint() {
        return 'https://raw.githubusercontent.com/AtomicDesignBelgium/wp-atomic-design-framework/main/update.json';
    }
    function check_update($transient) {
        if (empty($transient->checked)) return $transient;
        $res = wp_remote_get($this->endpoint(), ['timeout'=>10]);
        if (is_wp_error($res)) return $transient;
        $body = wp_remote_retrieve_body($res);
        $data = json_decode($body, true);
        if (empty($data['version']) || empty($data['download_url'])) return $transient;
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $plugin_file = ADF_PATH . 'wp-atomic-design-framework.php';
        $plugin_basename = plugin_basename($plugin_file);
        $plugin_data = get_plugin_data($plugin_file, false, false);
        $current = $plugin_data['Version'] ?? '0.0.0';
        if (version_compare($data['version'], $current, '>')) {
            $obj = new stdClass();
            $obj->slug = $data['slug'] ?? 'wp-atomic-design-framework';
            $obj->new_version = $data['version'];
            $obj->package = $data['download_url'];
            $obj->url = $data['homepage'] ?? '';
            $transient->response[$plugin_basename] = $obj;
        }
        return $transient;
    }
    function plugin_info($res, $action, $args) {
        if ($action !== 'plugin_information') return $res;
        $r = wp_remote_get($this->endpoint(), ['timeout'=>10]);
        if (is_wp_error($r)) return $res;
        $body = wp_remote_retrieve_body($r);
        $data = json_decode($body, true);
        if (!$data) return $res;
        if (isset($args->slug) && isset($data['slug']) && $args->slug !== $data['slug']) return $res;
        $info = new stdClass();
        $info->name = $data['name'] ?? 'WP Atomic Design Framework';
        $info->slug = $data['slug'] ?? 'wp-atomic-design-framework';
        $info->version = $data['version'] ?? '1.0.0';
        $info->download_link = $data['download_url'] ?? '';
        $info->sections = $data['sections'] ?? [];
        return $info;
    }
}
new ADF_Updater();