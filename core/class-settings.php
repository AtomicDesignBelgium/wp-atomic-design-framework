<?php
if (!defined('ABSPATH')) exit;

function adf_sanitize_options($input) {
    if (!is_array($input)) { $input = []; }
    return [
        'enable_dev_tags' => !empty($input['enable_dev_tags']) ? 1 : 0,
        'hide_author' => !empty($input['hide_author']) ? 1 : 0,
        'hide_commercial_notices' => !empty($input['hide_commercial_notices']) ? 1 : 0,
        'menu_status_indicator' => !empty($input['menu_status_indicator']) ? 1 : 0,
        'breadcrumbs_enable' => !empty($input['breadcrumbs_enable']) ? 1 : 0,
        'breadcrumbs_show_home' => !empty($input['breadcrumbs_show_home']) ? 1 : 0,
        'breadcrumbs_separator' => isset($input['breadcrumbs_separator']) ? sanitize_text_field($input['breadcrumbs_separator']) : '›',
        'breadcrumbs_show_current' => !empty($input['breadcrumbs_show_current']) ? 1 : 0,
        'breadcrumbs_max_depth' => isset($input['breadcrumbs_max_depth']) ? max(1,intval($input['breadcrumbs_max_depth'])) : 5,
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
    add_options_page('WP Atomic Design','WP Atomic Design','manage_options','wp-atomic-design','adf_tools_html');
}
add_action('admin_menu','adf_add_menu');

function adf_redirect_old_dashboard_page() {
    if (!is_admin()) return;
    if (!isset($_GET['page'])) return;
    if (sanitize_text_field($_GET['page']) !== 'wp-atomic-design-dashboard') return;
    if (!current_user_can('manage_options')) return;
    wp_safe_redirect(admin_url('options-general.php?page=wp-atomic-design&tab=dashboard'));
    exit;
}
add_action('admin_init','adf_redirect_old_dashboard_page');

function adf_tools_html() {
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
    $can_settings = current_user_can('manage_options');
    if ($tab === 'settings' && !$can_settings) { $tab = 'dashboard'; }
    if ($tab === 'slugs' && !$can_settings) { $tab = 'dashboard'; }
    if ($tab === 'features' && !$can_settings) { $tab = 'dashboard'; }
    $active_dashboard = ($tab === 'dashboard');
    $active_notes = ($tab === 'notes');
    $active_settings = ($tab === 'settings');
    $active_slugs = ($tab === 'slugs');
    $active_features = ($tab === 'features');
    $dash_url = admin_url('options-general.php?page=wp-atomic-design&tab=dashboard');
    $set_url = admin_url('options-general.php?page=wp-atomic-design&tab=settings');
    $notes_url = admin_url('options-general.php?page=wp-atomic-design&tab=notes');
    $slugs_url = admin_url('options-general.php?page=wp-atomic-design&tab=slugs');
    $features_url = admin_url('options-general.php?page=wp-atomic-design&tab=features');
    echo '<div class="wrap"><h1>WP Atomic Design Framework</h1>';
    echo '<h2 class="nav-tab-wrapper">';
    echo '<a href="'.esc_url($dash_url).'" class="nav-tab'.($active_dashboard?' nav-tab-active':'').'">Dashboard</a>';
    echo '<a href="'.esc_url($notes_url).'" class="nav-tab'.($active_notes?' nav-tab-active':'').'">Internal Notes</a>';
    if ($can_settings) {
        echo '<a href="'.esc_url($set_url).'" class="nav-tab'.($active_settings?' nav-tab-active':'').'">Settings</a>';
        echo '<a href="'.esc_url($slugs_url).'" class="nav-tab'.($active_slugs?' nav-tab-active':'').'">Slugs</a>';
        echo '<a href="'.esc_url($features_url).'" class="nav-tab'.($active_features?' nav-tab-active':'').'">Custom Features</a>';
    }
    echo '</h2>';
    if ($active_dashboard) { adf_dashboard_html(); }
    else if ($active_settings) { adf_settings_html(); }
    else if ($active_notes) { if (class_exists('ADF\\ADF_InternalNotes')) { (new ADF\ADF_InternalNotes())->admin_page(); } }
    else if ($active_slugs) { adf_slugs_html(); }
    else if ($active_features) { adf_features_html(); }
    echo '</div>';
}

function adf_settings_html() {
    $options = adf_get_options(); ?>
    <div class="wrap"><h1>WP Atomic Design Framework</h1>
    <form method="post" action="options.php">
        <?php settings_fields('atomic_design_tools_group'); ?>
        <table class="form-table">
            <tr><th>Enable Dev Tags</th><td><input type="checkbox" name="atomic_design_tools_settings[enable_dev_tags]" value="1" <?php checked(1, isset($options['enable_dev_tags']) ? $options['enable_dev_tags'] : 0); ?>></td></tr>
            <tr><th>Hide Author Column</th><td><input type="checkbox" name="atomic_design_tools_settings[hide_author]" value="1" <?php checked(1, isset($options['hide_author']) ? $options['hide_author'] : 0); ?>></td></tr>
            <tr><th>Disable commercial notices</th><td><input type="checkbox" name="atomic_design_tools_settings[hide_commercial_notices]" value="1" <?php checked(1, isset($options['hide_commercial_notices']) ? $options['hide_commercial_notices'] : 0); ?>></td></tr>
            <tr><th>Front-end menu status dot</th><td><input type="checkbox" name="atomic_design_tools_settings[menu_status_indicator]" value="1" <?php checked(1, isset($options['menu_status_indicator']) ? $options['menu_status_indicator'] : 0); ?>><p class="description">Shows a color dot after page links in the site menus for admins/editors.</p></td></tr>
        </table>
        <?php submit_button(); ?>
    </form></div>
    <div class="wrap" style="margin-top:10px;">
        <a class="button" href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=adf_force_update'),'adf_force_update'); ?>">Check for Updates</a>
    </div>
<?php }

function adf_apply_notice_hide() {
    $o = adf_get_options();
    if (!empty($o['hide_commercial_notices'])) {
        add_action('admin_print_styles', function () {
            echo '<style>.notice-warning,.notice-info,.notice-success{display:none!important}</style>';
        });
    }
}

function adf_features_html() {
    $o = adf_get_options(); ?>
    <div class="wrap"><h1>Custom Features</h1>
    <h2>Breadcrumbs</h2>
    <form method="post" action="options.php">
        <?php settings_fields('atomic_design_tools_group'); ?>
        <table class="form-table">
            <tr><th>Enable Breadcrumbs</th><td><input type="checkbox" name="atomic_design_tools_settings[breadcrumbs_enable]" value="1" <?php checked(1, isset($o['breadcrumbs_enable']) ? $o['breadcrumbs_enable'] : 0); ?>></td></tr>
            <tr><th>Show Home link</th><td><input type="checkbox" name="atomic_design_tools_settings[breadcrumbs_show_home]" value="1" <?php checked(1, isset($o['breadcrumbs_show_home']) ? $o['breadcrumbs_show_home'] : 1); ?>></td></tr>
            <tr><th>Separator</th><td><input type="text" name="atomic_design_tools_settings[breadcrumbs_separator]" value="<?php echo esc_attr(isset($o['breadcrumbs_separator']) ? $o['breadcrumbs_separator'] : '›'); ?>" style="width:120px"></td></tr>
            <tr><th>Show current page</th><td><input type="checkbox" name="atomic_design_tools_settings[breadcrumbs_show_current]" value="1" <?php checked(1, isset($o['breadcrumbs_show_current']) ? $o['breadcrumbs_show_current'] : 1); ?>></td></tr>
            <tr><th>Max depth</th><td><input type="number" name="atomic_design_tools_settings[breadcrumbs_max_depth]" min="1" max="10" value="<?php echo esc_attr(isset($o['breadcrumbs_max_depth']) ? $o['breadcrumbs_max_depth'] : 5); ?>" style="width:80px"></td></tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <p>Use the shortcode <code>[adf_breadcrumbs]</code> or insert the "ADF Breadcrumbs" block/pattern in the editor.</p>
    </div>
<?php }
add_action('admin_init','adf_apply_notice_hide');

function adf_force_update() {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'],'adf_force_update')) return;
    delete_site_transient('update_plugins');
    wp_safe_redirect(admin_url('update-core.php?force-check=1'));
    exit;
}
add_action('admin_post_adf_force_update','adf_force_update');

function adf_dashboard_html() {
    echo '<div class="wrap"><h1>Atomic Design Dashboard</h1>';
    echo '<style>.adf-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.adf-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,.04)}.adf-card h3{margin:0 0 12px;font-size:16px}.adf-chartwrap{position:relative;width:100%;height:240px}.adf-chartwrap.adf-h-200{height:200px}.adf-chartwrap.adf-h-300{height:300px}.adf-chartwrap canvas{position:absolute;inset:0;width:100%;height:100%;display:block}</style>';
    echo '<div class="adf-grid">';
    echo '<div class="adf-card"><h3>Global Progress</h3>'; adf_dash_progress_widget(); echo '</div>';
    echo '<div class="adf-card"><h3>Status Breakdown (Donut)</h3>'; adf_dash_chart_widget(); echo '</div>';
    echo '<div class="adf-card"><h3>Status Breakdown (Bars)</h3>'; adf_dash_bars_widget(); echo '</div>';
    echo '<div class="adf-card"><h3>Blocked / Pending validation</h3>'; adf_dash_blocked_widget(); echo '</div>';
    echo '<div class="adf-card"><h3>Latest modified pages</h3>'; adf_dash_latest_widget(); echo '</div>';
    echo '<div class="adf-card"><h3>Health check (pages without content)</h3>'; adf_dash_health_widget(); echo '</div>';
    echo '</div>';
    echo '</div>';
}

function adf_register_dashboard_widgets() {
    wp_add_dashboard_widget('adf_dash_progress','Atomic Design: Overall Progress','adf_dash_progress_widget');
    wp_add_dashboard_widget('adf_dash_chart','Atomic Design: Status Chart','adf_dash_chart_widget');
    wp_add_dashboard_widget('adf_dash_bars','Atomic Design: Status Bars','adf_dash_bars_widget');
    wp_add_dashboard_widget('adf_dash_blocked','Atomic Design: Blocked / Pending','adf_dash_blocked_widget');
    wp_add_dashboard_widget('adf_dash_latest','Atomic Design: Latest modified pages','adf_dash_latest_widget');
    wp_add_dashboard_widget('adf_dash_health','Atomic Design: Health check (pages without content)','adf_dash_health_widget');
}
add_action('wp_dashboard_setup','adf_register_dashboard_widgets');

function adf_dash_progress_widget() {
    $s = adf_dev_status_stats();
    echo '<div class="adf-chartwrap adf-h-300"><canvas id="adfDashProgress"></canvas></div>';
    adf_chartjs_once();
    $remaining = max(0, intval($s['total']) - intval($s['approved']));
    echo '<script>(function(){
      var ctx=document.getElementById("adfDashProgress").getContext("2d");
      var pct='.intval($s['progress']).';
      var centerText={id:"centerText",afterDraw:function(c){var w=c.width,h=c.height,ctx=c.ctx;ctx.save();ctx.font="bold 18px system-ui";ctx.fillStyle="#333";ctx.textAlign="center";ctx.textBaseline="middle";ctx.fillText(pct+"%", w/2, h/2);ctx.restore();}};
      new Chart(ctx,{type:"doughnut",data:{labels:["Complete","Remaining"],datasets:[{data:['.intval($s['approved']).','.$remaining.'],backgroundColor:["#2ecc71","#e0e0e0"]}]},options:{responsive:true,maintainAspectRatio:false,animation:false,resizeDelay:100,cutout:"70%",plugins:{legend:{position:"bottom"}}},plugins:[centerText]});
    })();</script>';
}

function adf_dash_chart_widget() {
    $s = adf_dev_status_stats();
    echo '<div class="adf-chartwrap adf-h-200"><canvas id="adfDashChart"></canvas></div>';
    adf_chartjs_once();
    echo '<script>(function(){var ctx=document.getElementById("adfDashChart").getContext("2d");new Chart(ctx,{type:"doughnut",data:{labels:["In development","Pending validation","Approved","Blocked","Not started"],datasets:[{data:['.$s['in_dev'].','.$s['pending'].','.$s['approved'].','.$s['blocked'].','.$s['not_started'].'],backgroundColor:["#3498db","#f39c12","#2ecc71","#e74c3c","#bdc3c7"]}]},options:{responsive:true,maintainAspectRatio:false,animation:false,resizeDelay:100,plugins:{legend:{position:"bottom"}}}});})();</script>';
}

function adf_dash_blocked_widget() {
    $list = function($slug,$title){
        $q = new WP_Query(['post_type'=>'page','post_status'=>['publish','pending','draft','future','private'],'posts_per_page'=>10,'orderby'=>'modified','order'=>'DESC','tax_query'=>[[ 'taxonomy'=>'dev_status','field'=>'slug','terms'=>[$slug] ]]]);
        echo '<h3 style="margin:8px 0">'.$title.'</h3><table class="widefat"><thead><tr><th>Page</th><th>Last edit</th><th>Author</th></tr></thead><tbody>';
        if ($q->have_posts()) { while ($q->have_posts()) { $q->the_post();
            $author = get_the_author();
            $modified = get_the_modified_time('Y-m-d H:i');
            echo '<tr><td><a href="'.esc_url(get_edit_post_link()).'">'.esc_html(get_the_title()).'</a></td><td>'.esc_html($modified).'</td><td>'.esc_html($author).'</td></tr>';
        } wp_reset_postdata(); } else { echo '<tr><td colspan="3">None</td></tr>'; }
        echo '</tbody></table>';
    };
    $list('blocked','Blocked (missing content)');
    $list('pending-validation','Pending validation');
}

function adf_dash_latest_widget() {
    $recent = new WP_Query(['post_type'=>'page','post_status'=>['publish','pending','draft','future','private'],'posts_per_page'=>10,'orderby'=>'modified','order'=>'DESC']);
    echo '<table class="widefat"><thead><tr><th>Page</th><th>Status</th><th>Date</th><th>User</th><th>Actions</th></tr></thead><tbody>';
    if ($recent->have_posts()) { while ($recent->have_posts()) { $recent->the_post();
        $author = get_the_author();
        $modified = get_the_modified_time('Y-m-d H:i');
        $terms = wp_get_object_terms(get_the_ID(),'dev_status');
        $status = count($terms) ? esc_html($terms[0]->name) : 'Not started';
        $edit = '<a href="'.esc_url(get_edit_post_link()).'">Edit</a>';
        $quick = '<a href="'.esc_url(admin_url('edit.php?post_type=page')).'">Quick Edit</a>';
        echo '<tr><td><a href="'.esc_url(get_edit_post_link()).'">'.esc_html(get_the_title()).'</a></td><td>'.$status.'</td><td>'.esc_html($modified).'</td><td>'.esc_html($author).'</td><td>'.$edit.' | '.$quick.'</td></tr>';
    } wp_reset_postdata(); } else { echo '<tr><td colspan="5">No recent edits</td></tr>'; }
    echo '</tbody></table>';
}

function adf_dash_health_widget() {
    $q = new WP_Query(['post_type'=>'page','post_status'=>['publish','pending','draft','future','private'],'posts_per_page'=>200,'orderby'=>'modified','order'=>'DESC','fields'=>'ids']);
    $rows = [];
    foreach ($q->posts as $pid) {
        $p = get_post($pid);
        $content = trim(preg_replace('/\s+/',' ',wp_strip_all_tags($p->post_content)));
        if ($content === '') { $rows[] = $p; }
    }
    echo '<table class="widefat"><thead><tr><th>Page</th><th>Last edit</th><th>Author</th></tr></thead><tbody>';
    if (!empty($rows)) { foreach ($rows as $p) {
        $author = get_the_author_meta('display_name',$p->post_author);
        $modified = mysql2date('Y-m-d H:i',$p->post_modified);
        echo '<tr><td><a href="'.esc_url(get_edit_post_link($p->ID)).'">'.esc_html(get_the_title($p->ID)).'</a></td><td>'.esc_html($modified).'</td><td>'.esc_html($author).'</td></tr>';
    }} else { echo '<tr><td colspan="3">All good</td></tr>'; }
    echo '</tbody></table>';
}
function adf_dash_bars_widget() {
    $s = adf_dev_status_stats();
    echo '<div class="adf-chartwrap adf-h-200"><canvas id="adfDashBars"></canvas></div>';
    adf_chartjs_once();
    echo '<script>(function(){var ctx=document.getElementById("adfDashBars").getContext("2d");new Chart(ctx,{type:"bar",data:{labels:["Not started","In development","Pending","Approved","Blocked"],datasets:[{label:"Pages",data:['.$s['not_started'].','.$s['in_dev'].','.$s['pending'].','.$s['approved'].','.$s['blocked'].'],backgroundColor:["#bdc3c7","#3498db","#f39c12","#2ecc71","#e74c3c"]}]},options:{responsive:true,maintainAspectRatio:false,animation:false,resizeDelay:100,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});})();</script>';
}
function adf_slugs_html() {
    if (!current_user_can('manage_options')) return;
    echo '<div class="wrap"><h1>Slugs</h1>';
    echo '<p>Reference list of registered slugs.</p>';
    echo '<p><label>Search: <input type="search" id="adfSlugSearch" placeholder="Filter slugs, labels, meta keys" style="width:320px"></label></p>';
    $pts = get_post_types([], 'objects');
    $txs = get_taxonomies([], 'objects');
    global $wpdb; $meta_rows = $wpdb->get_results("SELECT meta_key, COUNT(*) AS c FROM {$wpdb->postmeta} GROUP BY meta_key ORDER BY c DESC LIMIT 200", ARRAY_A);
    echo '<h2>Post Types</h2><table class="widefat adfSlugsTable" data-kind="pt"><thead><tr><th>Slug</th><th>Label</th></tr></thead><tbody>';
    foreach ($pts as $pt) { echo '<tr><td>'.esc_html($pt->name).'</td><td>'.esc_html($pt->label).'</td></tr>'; }
    echo '</tbody></table>';
    echo '<h2 style="margin-top:16px">Taxonomies</h2><table class="widefat adfSlugsTable" data-kind="tx"><thead><tr><th>Slug</th><th>Label</th></tr></thead><tbody>';
    foreach ($txs as $tx) { echo '<tr><td>'.esc_html($tx->name).'</td><td>'.esc_html($tx->label).'</td></tr>'; }
    echo '</tbody></table>';
    echo '<h2 style="margin-top:16px">Custom Fields (meta_key)</h2><table class="widefat adfSlugsTable" data-kind="meta"><thead><tr><th>Key</th><th>Count</th></tr></thead><tbody>';
    if (is_array($meta_rows)) { foreach ($meta_rows as $r) { echo '<tr><td>'.esc_html($r['meta_key']).'</td><td>'.intval($r['c']).'</td></tr>'; } }
    echo '</tbody></table>';
    echo '<script>(function(){var i=document.getElementById("adfSlugSearch");if(!i)return;function f(){var q=(i.value||"").toLowerCase();document.querySelectorAll("table.adfSlugsTable tbody tr").forEach(function(tr){var t=tr.textContent.toLowerCase();tr.style.display = q && t.indexOf(q)===-1 ? "none" : "";});} i.addEventListener("input", f);})();</script>';
    echo '</div>';
}

function adf_menu_status_badge($title, $item, $args) {
    $o = adf_get_options();
    if (empty($o['menu_status_indicator'])) return $title;
    if (!is_user_logged_in() || !current_user_can('edit_pages')) return $title;
    return $title;
}
add_filter('nav_menu_item_title','adf_menu_status_badge', 10, 3);

function adf_resolve_page_id_from_menu_item($item) {
    if (!is_object($item)) return 0;
    if (!empty($item->object_id) && $item->object === 'page') {
        return intval($item->object_id);
    }
    $url = !empty($item->url) ? $item->url : '';
    if ($url) {
        $maybe = url_to_postid($url);
        if ($maybe) {
            $p = get_post($maybe);
            if ($p && $p->post_type === 'page') { return intval($maybe); }
        }
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            $path = trim($path, '/');
            if ($path !== '') {
                $page = get_page_by_path($path, OBJECT, 'page');
                if ($page && isset($page->ID)) { return intval($page->ID); }
            }
        }
    }
    return 0;
}

function adf_menu_status_class($classes, $item, $args) {
    $o = adf_get_options();
    if (empty($o['menu_status_indicator'])) return $classes;
    if (!is_user_logged_in() || !current_user_can('edit_pages')) return $classes;
    foreach ($classes as $c) { if (strpos($c,'adf-dev-status-') === 0) { return $classes; } }
    $pid = adf_resolve_page_id_from_menu_item($item);
    if (!$pid) return $classes;
    $terms = wp_get_object_terms($pid, 'dev_status');
    if (is_wp_error($terms)) return $classes;
    $slug = (!empty($terms) && isset($terms[0]->slug)) ? $terms[0]->slug : 'empty';
    $classes[] = 'adf-dev-status';
    $classes[] = 'adf-dev-status-' . sanitize_html_class($slug);
    return $classes;
}
add_filter('nav_menu_css_class','adf_menu_status_class', 10, 3);

function adf_menu_status_css() {
    $o = adf_get_options();
    if (empty($o['menu_status_indicator'])) return;
    if (!is_user_logged_in() || !current_user_can('edit_pages')) return;
    echo '<style id="adf-menu-status-css">'
        . '.adf-dev-status a::before{content:"";display:inline-block;width:.6em;height:.6em;border-radius:50%;margin-right:6px;vertical-align:middle;background:#bdc3c7}'
        . '.adf-dev-status-approved a::before{background:#2ecc71}'
        . '.adf-dev-status-pending-validation a::before{background:#f39c12}'
        . '.adf-dev-status-in-development a::before{background:#3498db}'
        . '.adf-dev-status-blocked a::before{background:#e74c3c}'
        . '</style>';
}
add_action('wp_head','adf_menu_status_css');

function adf_menu_status_prepare($items, $args) {
    $o = adf_get_options();
    if (empty($o['menu_status_indicator'])) return $items;
    if (!is_user_logged_in() || !current_user_can('edit_pages')) return $items;
    $parents = [];$slugOwn = [];
    foreach ($items as $it) {
        $parents[$it->ID] = intval($it->menu_item_parent);
        $pid = adf_resolve_page_id_from_menu_item($it);
        if ($pid) {
            $terms = wp_get_object_terms($pid,'dev_status');
            $slugOwn[$it->ID] = (!is_wp_error($terms) && !empty($terms) && isset($terms[0]->slug)) ? $terms[0]->slug : '';
        } else {
            $slugOwn[$it->ID] = '';
        }
    }
    $getDepth = function($id) use ($parents) { $d=0; while (!empty($parents[$id])) { $id=$parents[$id]; $d++; if ($d>20) break; } return $d; };
    $inheritSlug = function($id) use ($parents,$slugOwn) {
        $cur = $id; $s = isset($slugOwn[$cur]) ? $slugOwn[$cur] : '';
        if ($s) return $s;
        while (!empty($parents[$cur])) { $cur = $parents[$cur]; $s = isset($slugOwn[$cur]) ? $slugOwn[$cur] : ''; if ($s) return $s; }
        return 'empty';
    };
    foreach ($items as $i=>$it) {
        $depth = $getDepth($it->ID);
        $slug = isset($slugOwn[$it->ID]) ? $slugOwn[$it->ID] : '';
        if ($depth >= 2) { $slug = $inheritSlug($it->ID); }
        if (!$slug) { $slug = 'empty'; }
        $it->classes[] = 'adf-dev-status';
        $it->classes[] = 'adf-dev-status-' . sanitize_html_class($slug);
        $items[$i] = $it;
    }
    return $items;
}
add_filter('wp_nav_menu_objects','adf_menu_status_prepare', 10, 2);

function adf_breadcrumbs_build($args=[]) {
    $o = adf_get_options();
    $enabled = !empty($o['breadcrumbs_enable']);
    $sep = isset($o['breadcrumbs_separator']) ? $o['breadcrumbs_separator'] : '›';
    $show_home = isset($o['breadcrumbs_show_home']) ? !!$o['breadcrumbs_show_home'] : true;
    $show_current = isset($o['breadcrumbs_show_current']) ? !!$o['breadcrumbs_show_current'] : true;
    $max_depth = isset($o['breadcrumbs_max_depth']) ? intval($o['breadcrumbs_max_depth']) : 5;
    if (isset($args['separator'])) { $sep = sanitize_text_field($args['separator']); }
    if (isset($args['show_home'])) { $show_home = !!$args['show_home']; }
    if (isset($args['show_current'])) { $show_current = !!$args['show_current']; }
    if (isset($args['max_depth'])) { $max_depth = max(1,intval($args['max_depth'])); }
    if (!$enabled) return '';
    $links = [];
    if ($show_home) { $links[] = ['url'=>home_url('/'),'label'=>get_bloginfo('name')]; }
    if (is_page()) {
        $id = get_queried_object_id();
        $anc = get_post_ancestors($id); $anc = array_reverse($anc);
        $count = 0;
        foreach ($anc as $aid) { $links[] = ['url'=>get_permalink($aid),'label'=>get_the_title($aid)]; $count++; if ($count >= $max_depth) break; }
        if ($show_current) { $links[] = ['url'=>'','label'=>get_the_title($id)]; }
    } else if (is_single()) {
        $id = get_queried_object_id();
        $cat = get_the_category($id); $cat = is_array($cat) && !empty($cat) ? $cat[0] : null;
        if ($cat) { $links[] = ['url'=>get_category_link($cat->term_id),'label'=>$cat->name]; }
        if ($show_current) { $links[] = ['url'=>'','label'=>get_the_title($id)]; }
    } else if (is_category()) {
        $term = get_queried_object(); if ($term && isset($term->name)) { $links[] = ['url'=>'','label'=>$term->name]; }
    }
    $html = '<nav class="adf-breadcrumbs" aria-label="Breadcrumbs">';
    $html .= '<span class="adf-bc-inner">';
    for ($i=0; $i<count($links); $i++) {
        $l = $links[$i]; $is_last = ($i === count($links)-1);
        if (!$is_last && !empty($l['url'])) { $html .= '<a href="'.esc_url($l['url']).'">'.esc_html($l['label']).'</a>'; }
        else { $html .= '<span>'.esc_html($l['label']).'</span>'; }
        if (!$is_last) { $html .= ' <span class="adf-bc-sep">'.esc_html($sep).'</span> '; }
    }
    $html .= '</span></nav>';
    return $html;
}

function adf_breadcrumbs_shortcode($atts=[]) { return adf_breadcrumbs_build($atts); }
add_shortcode('adf_breadcrumbs','adf_breadcrumbs_shortcode');

function adf_register_breadcrumbs_pattern() {
    if (!function_exists('register_block_pattern')) return;
    $content = '<!-- wp:shortcode -->[adf_breadcrumbs]<!-- /wp:shortcode -->';
    register_block_pattern('adf/breadcrumbs',[
        'title'=>'ADF Breadcrumbs',
        'description'=>'Breadcrumbs navigation using ADF settings',
        'content'=>$content,
        'categories'=>['widgets','text']
    ]);
}
add_action('init','adf_register_breadcrumbs_pattern');

function adf_current_page_id() {
    // Front-end singular page
    if (!is_admin()) {
        $id = get_queried_object_id();
        $p = $id ? get_post($id) : null;
        if ($p && $p->post_type === 'page') return intval($id);
        return 0;
    }
    // Admin edit screen
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->base === 'post' && $screen->post_type === 'page') {
        $pid = isset($_GET['post']) ? intval($_GET['post']) : 0;
        return $pid;
    }
    return 0;
}

function adf_adminbar_status($wp_admin_bar) {
    if (!is_user_logged_in() || !current_user_can('edit_pages')) return;
    $pid = adf_current_page_id();
    if (!$pid) return;
    $terms = wp_get_object_terms($pid, 'dev_status');
    $label = (!is_wp_error($terms) && !empty($terms)) ? $terms[0]->name : '';
    $node_id = 'adf-dev-status';
    $title = 'Dev Status' . ($label ? ': ' . esc_html($label) : '');
    $wp_admin_bar->add_node(['id'=>$node_id, 'title'=>$title]);
    // Dropdown
    $opts = get_terms(['taxonomy'=>'dev_status','hide_empty'=>false]);
    if (!is_array($opts) || empty($opts)) return;
    $form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="padding:6px 12px">';
    $form .= '<input type="hidden" name="action" value="adf_set_dev_status">';
    $form .= '<input type="hidden" name="post_id" value="' . intval($pid) . '">';
    $form .= '<input type="hidden" name="_wpnonce" value="' . wp_create_nonce('adf_set_dev_status') . '">';
    $form .= '<select name="slug" onchange="this.form.submit()">';
    $form .= '<option value="">— Select —</option>';
    foreach ($opts as $t) { $sel = ($label && $t->name === $label) ? ' selected' : ''; $form .= '<option value="' . esc_attr($t->slug) . '"' . $sel . '>' . esc_html($t->name) . '</option>'; }
    $form .= '</select>';
    $form .= '</form>';
    $wp_admin_bar->add_node(['id'=>$node_id.'-select', 'parent'=>$node_id, 'title'=>$form]);
}
add_action('admin_bar_menu','adf_adminbar_status', 100);

function adf_set_dev_status() {
    if (!current_user_can('edit_pages')) return;
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'adf_set_dev_status')) return;
    $pid = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
    if (!$pid || !$slug) return;
    $term = get_term_by('slug', $slug, 'dev_status');
    if ($term) { wp_set_object_terms($pid, [$term->term_id], 'dev_status', false); }
    wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
    exit;
}
add_action('admin_post_adf_set_dev_status','adf_set_dev_status');
