<?php
if (!defined('ABSPATH')) exit;

function adf_get_options() {
    $o = get_option('atomic_design_tools_settings', []);
    if (!is_array($o)) { $o = []; }
    $o['enable_dev_tags'] = !empty($o['enable_dev_tags']) ? 1 : 0;
    $o['hide_author'] = !empty($o['hide_author']) ? 1 : 0;
    $o['hide_commercial_notices'] = !empty($o['hide_commercial_notices']) ? 1 : 0;
    $o['menu_status_indicator'] = !empty($o['menu_status_indicator']) ? 1 : 0;
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
    $c = wp_count_posts('page');
    $counts = [
        'publish' => isset($c->publish) ? intval($c->publish) : 0,
        'pending' => isset($c->pending) ? intval($c->pending) : 0,
        'draft'   => isset($c->draft) ? intval($c->draft) : 0,
        'future'  => isset($c->future) ? intval($c->future) : 0,
        'private' => isset($c->private) ? intval($c->private) : 0,
    ];
    $total = 0;
    foreach (['publish','pending','draft','future','private'] as $st) { $total += intval($counts[$st]); }
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
