<?php
namespace ADF;
if (!defined('ABSPATH')) exit;

class ADF_HideAuthor {
    function __construct() {
        add_filter('manage_pages_columns',[$this,'hide'],20);
    }
    function hide($cols){unset($cols['author']);return $cols;}
}
new ADF_HideAuthor();
