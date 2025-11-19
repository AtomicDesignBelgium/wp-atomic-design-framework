<?php
if (!defined('ABSPATH')) exit;

function adf_sanitize_options($input) {
    if (!is_array($input)) { $input = []; }
    return [
        'enable_dev_tags' => !empty($input['enable_dev_tags']) ? 1 : 0,
        'hide_author' => !empty($input['hide_author']) ? 1 : 0,
        'hide_commercial_notices' => !empty($input['hide_commercial_notices']) ? 1 : 0,
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
    $active_dashboard = ($tab === 'dashboard');
    $active_notes = ($tab === 'notes');
    $active_settings = ($tab === 'settings');
    $dash_url = admin_url('options-general.php?page=wp-atomic-design&tab=dashboard');
    $set_url = admin_url('options-general.php?page=wp-atomic-design&tab=settings');
    $notes_url = admin_url('options-general.php?page=wp-atomic-design&tab=notes');
    echo '<div class="wrap"><h1>WP Atomic Design Framework</h1>';
    echo '<h2 class="nav-tab-wrapper">';
    echo '<a href="'.esc_url($dash_url).'" class="nav-tab'.($active_dashboard?' nav-tab-active':'').'">Dashboard</a>';
    echo '<a href="'.esc_url($notes_url).'" class="nav-tab'.($active_notes?' nav-tab-active':'').'">Internal Notes</a>';
    if ($can_settings) {
        echo '<a href="'.esc_url($set_url).'" class="nav-tab'.($active_settings?' nav-tab-active':'').'">Settings</a>';
    }
    echo '</h2>';
    if ($active_dashboard) { adf_dashboard_html(); }
    else if ($active_settings) { adf_settings_html(); }
    else if ($active_notes) { if (class_exists('ADF\\ADF_InternalNotes')) { (new ADF\ADF_InternalNotes())->admin_page(); } }
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