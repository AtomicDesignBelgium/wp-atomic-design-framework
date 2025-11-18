<?php
if (!defined('ABSPATH')) exit;

class ADF_DevStatus {
    function __construct() {
        add_action('init', [$this,'register']);
    }
    function register() {
        register_taxonomy('dev_status',['page'],[
            'public'=>false,'show_ui'=>false,'hierarchical'=>false
        ]);
    }
}
new ADF_DevStatus();
