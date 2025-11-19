<?php
if (!defined('ABSPATH')) exit;

function adf_get_options() {
    $o = get_option('atomic_design_tools_settings', []);
    if (!is_array($o)) { $o = []; }
    $o['enable_dev_tags'] = !empty($o['enable_dev_tags']) ? 1 : 0;
    $o['hide_author'] = !empty($o['hide_author']) ? 1 : 0;
    return $o;
}

function adf_chartjs_once() {
    static $done = false;
    if ($done) return;
    $done = true;
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
}

function adf_dev_status_stats() {
    $opt = adf_get_options();
    $counts = wp_count_posts('page');
    $total = 0;
    foreach (['publish','pending','draft','future','private'] as $st) { $total += isset($counts->$st) ? intval($counts->$st) : 0; }
    // Always compute stats; UI can be disabled while taxonomy remains registered
    $base = [
        'post_type'=>'page',
        'post_status'=>['publish','pending','draft','future','private'],
        'posts_per_page'=>-1,
        'fields'=>'ids',
        'no_found_rows'=>true,
    ];
    $countq = function($tax_query) use ($base) {
        $q = new WP_Query($base + ['tax_query'=>$tax_query]);
        return intval(is_array($q->posts) ? count($q->posts) : 0);
    };
    $approved = $countq([[ 'taxonomy'=>'dev_status','field'=>'slug','terms'=>['approved'] ]]);
    $pending = $countq([[ 'taxonomy'=>'dev_status','field'=>'slug','terms'=>['pending-validation'] ]]);
    $in_dev = $countq([[ 'taxonomy'=>'dev_status','field'=>'slug','terms'=>['in-development'] ]]);
    $blocked = $countq([[ 'taxonomy'=>'dev_status','field'=>'slug','terms'=>['blocked'] ]]);
    $empty = $countq([[ 'taxonomy'=>'dev_status','field'=>'slug','terms'=>['empty'] ]]);
    $no_term = $countq([[ 'taxonomy'=>'dev_status','operator'=>'NOT EXISTS' ]]);
    $not_started = $empty + $no_term;
    $progress = ($total > 0) ? round(($approved / $total) * 100) : 0;
    return compact('total','approved','pending','in_dev','blocked','not_started','progress');
}
