<?php
/* Template Name: 管理面板 */

if (!is_user_logged_in()) { wp_redirect(wp_login_url()); exit; }

$user_id = get_current_user_id();
$rank = crrg_get_rank($user_id);
$allowed = in_array($rank, ['advisor', 'deputy', 'chairman']);

if (!$allowed) {
    get_header();
    echo '<div class="gov-main"><div class="gov-content" style="text-align:center;padding:80px 20px;">';
    echo '<div style="font-size:48px;margin-bottom:16px;">🔒</div>';
    echo '<h2 style="color:#C41230;">权限不足，无法访问</h2>';
    echo '<p style="color:#999;">此页面仅限首席顾问及以上级别访问。</p>';
    echo '<a href="/" style="color:#1B3A5C;">返回首页</a>';
    echo '</div></div>';
    get_footer();
    exit;
}

$message = '';
$error = '';

// Handle article review + delete + search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_admin')) {
    $action = $_POST['admin_action'] ?? '';
    $target_id = (int)($_POST['post_id'] ?? 0);
    
    if ($action === 'approve_post') {
        wp_update_post(['ID' => $target_id, 'post_status' => 'publish']);
        $author_id = get_post_field('post_author', $target_id);
        $is_news = get_post_meta($target_id, 'crrg_is_news', true);
        crrg_add_xp($author_id, $is_news ? 10 : 15);
        // 值班加成
        $duty_bonus = 0;
        $today_duty = crrg_get_today_duty();
        $author = get_userdata($author_id);
        if ($author && in_array($author->display_name, $today_duty)) {
            $duty_bonus = $is_news ? 5 : 8;
            crrg_add_xp($author_id, $duty_bonus);
        }
        $msg = '文章已审核通过，作者获得 ' . ($is_news ? 10 : 15) . ' 资历';
        if ($duty_bonus) $msg .= '（含值班加成 +' . $duty_bonus . '）';
        $msg .= '。';
        $message = $msg;
    } elseif ($action === 'reject_post') {
        $reason = sanitize_text_field(wp_unslash($_POST['reject_reason'] ?? ''));
        update_post_meta($target_id, 'crrg_reject_reason', $reason);
        update_post_meta($target_id, 'crrg_rejected_by', $user_id);
        wp_update_post(['ID' => $target_id, 'post_status' => 'draft']);
        // 发送私信通知作者
        $author_id = (int) get_post_field('post_author', $target_id);
        $post_title = get_post_field('post_title', $target_id);
        $reviewer = wp_get_current_user();
        crrg_send_message($user_id, $author_id,
            '文章退回通知：「' . $post_title . '」',
            '您的文章《' . $post_title . '》已被退回。' . "

"
            . '退回原因：' . ($reason ?: '未填写') . "
"
            . '审核人：' . $reviewer->display_name . '（ID: ' . $user_id . '）' . "

"
            . '请修改后重新提交。'
        );
        $message = '文章已退回，已私信通知作者。';
    } elseif ($action === 'delete_post') {
        wp_delete_post($target_id, true);
        $message = '文章已永久删除。';
    } elseif ($action === 'approve_delete') {
        $req_user = get_post_meta($target_id, 'crrg_delete_request', true);
        wp_delete_post($target_id, true);
        $message = '已批准删除申请，文章已删除。';
    } elseif ($action === 'approve_edit') {
        $new_title = get_post_meta($target_id, 'crrg_edit_title', true);
        $new_content = get_post_meta($target_id, 'crrg_edit_content', true);
        wp_update_post(['ID' => $target_id, 'post_title' => $new_title, 'post_content' => $new_content]);
        // 应用标签修改
        $new_tags = get_post_meta($target_id, 'crrg_edit_tags', true);
        if ($new_tags !== '') {
            $tag_names = array_map('trim', explode(',', $new_tags));
            wp_set_post_tags($target_id, array_filter($tag_names), false);
        }
        delete_post_meta($target_id, 'crrg_edit_request');
        delete_post_meta($target_id, 'crrg_edit_title');
        delete_post_meta($target_id, 'crrg_edit_content');
        delete_post_meta($target_id, 'crrg_edit_tags');
        // 应用访问等级
        $new_access = get_post_meta($target_id, 'crrg_edit_access', true);
        if ($new_access) {
            update_post_meta($target_id, 'crrg_access_level', $new_access);
            delete_post_meta($target_id, 'crrg_edit_access');
        }
        // 应用威胁等级 + 地点
        $new_threat = get_post_meta($target_id, 'crrg_edit_threat', true);
        if ($new_threat !== '') { update_post_meta($target_id, 'crrg_threat_level', $new_threat); delete_post_meta($target_id, 'crrg_edit_threat'); }
        $new_location = get_post_meta($target_id, 'crrg_edit_location', true);
        if ($new_location !== '') { update_post_meta($target_id, 'crrg_location', $new_location); delete_post_meta($target_id, 'crrg_edit_location'); }
        $new_lat = get_post_meta($target_id, 'crrg_edit_lat', true);
        if ($new_lat) { update_post_meta($target_id, 'crrg_lat', $new_lat); delete_post_meta($target_id, 'crrg_edit_lat'); }
        $new_lng = get_post_meta($target_id, 'crrg_edit_lng', true);
        if ($new_lng) { update_post_meta($target_id, 'crrg_lng', $new_lng); delete_post_meta($target_id, 'crrg_edit_lng'); }
        // 应用类型修改
        $new_cat = get_post_meta($target_id, 'crrg_edit_category', true);
        if ($new_cat) {
            update_post_meta($target_id, 'crrg_report_type', $new_cat);
            $cat_names = ['artifacts'=>'镇物','events'=>'事件','personnel'=>'人物','organizations'=>'组织','research'=>'研究发现','entities'=>'祂们','esoterica'=>'秘术','outstanding'=>'优秀员工','other'=>'其他'];
            update_post_meta($target_id, 'crrg_report_type_name', $cat_names[$new_cat] ?? '其他');
            delete_post_meta($target_id, 'crrg_edit_category');
        }
        // 应用时间修改
        $new_date = get_post_meta($target_id, 'crrg_edit_date', true);
        if ($new_date !== '') { update_post_meta($target_id, 'crrg_event_date', $new_date); delete_post_meta($target_id, 'crrg_edit_date'); }
        $new_start = get_post_meta($target_id, 'crrg_edit_start', true);
        if ($new_start !== '') { update_post_meta($target_id, 'crrg_event_start', $new_start); delete_post_meta($target_id, 'crrg_edit_start'); }
        $new_end = get_post_meta($target_id, 'crrg_edit_end', true);
        if ($new_end !== '') { update_post_meta($target_id, 'crrg_event_end', $new_end); delete_post_meta($target_id, 'crrg_edit_end'); }
        $message = '修改已批准。';
    } elseif ($action === 'add_announcement') {
        $ann_title = sanitize_text_field($_POST['ann_title'] ?? '');
        $ann_content = wp_kses_post($_POST['ann_content'] ?? '');
        $ann_time = sanitize_text_field($_POST['ann_time'] ?? '');
        if ($ann_title && $ann_content) {
            crrg_add_announcement($ann_title, $ann_content, $ann_time);
            $message = '公告已发布。';
        }
    } elseif ($action === 'delete_announcement') {
        crrg_delete_announcement((int)($_POST['ann_index'] ?? -1));
        $message = '公告已删除。';
    } elseif ($action === 'edit_announcement') {
        $ann_index = (int)($_POST['ann_index'] ?? -1);
        $ann_title = sanitize_text_field($_POST['ann_title'] ?? '');
        $ann_content = wp_kses_post($_POST['ann_content'] ?? '');
        $ann_time = sanitize_text_field($_POST['ann_time'] ?? '');
        crrg_update_announcement($ann_index, $ann_title, $ann_content, $ann_time);
        $message = '公告已更新。';
    } elseif ($action === 'set_alert' && $rank === 'chairman') {
        crrg_set_alert(sanitize_text_field($_POST['alert_title']??''), sanitize_text_field($_POST['alert_content']??''), sanitize_text_field($_POST['alert_color']??'#C41230'));
        $message = '紧急预警已发布。';
    } elseif ($action === 'close_alert' && $rank === 'chairman') {
        crrg_close_alert();
        $message = '紧急预警已关闭。';
    }
}

// POST 后重定向
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($message)) {
    wp_redirect(add_query_arg('msg', urlencode($message), remove_query_arg('msg')));
    exit;
}

// Search
$search_query = sanitize_text_field($_GET['search'] ?? '');
$search_results = [];
if ($search_query) {
    $search_results = get_posts([
        'post_type' => 'post',
        's' => $search_query,
        'posts_per_page' => 20,
        'post_status' => ['publish', 'pending', 'draft'],
    ]);
}

// Handle approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_admin')) {
    $action = $_POST['admin_action'] ?? '';
    $target_id = (int)($_POST['user_id'] ?? 0);
    
    if ($action === 'approve') {
        $app_rank = get_user_meta($target_id, 'crrg_promotion_app', true);
        if ($app_rank) {
            update_user_meta($target_id, 'crrg_rank', $app_rank);
            delete_user_meta($target_id, 'crrg_promotion_app');
            delete_user_meta($target_id, 'crrg_promotion_status');
            $message = '已批准晋升申请。';
        }
    } elseif ($action === 'reject') {
        update_user_meta($target_id, 'crrg_promotion_status', 'rejected');
        $message = '已拒绝晋升申请。';
    } elseif ($action === 'add_xp') {
        $amount = (int)($_POST['xp_amount'] ?? 0);
        if ($amount > 0 && $amount <= 1000) {
            crrg_add_xp($target_id, $amount);
            $message = "已为用户增加 {$amount} 资历。";
        }
    } elseif ($action === 'set_rank') {
        $new_rank = sanitize_text_field($_POST['new_rank'] ?? '');
        if ($new_rank !== 'chairman' || $rank === 'chairman') {
            $valid = false;
            foreach (CRRG_RANKS as $r) { if ($r['id'] === $new_rank) $valid = true; }
            if ($valid) {
                update_user_meta($target_id, 'crrg_rank', $new_rank);
                $rd = crrg_get_rank_data($new_rank);
                update_user_meta($target_id, 'crrg_xp', max(crrg_get_xp($target_id), $rd['xp']));
                $message = "已设置等级为 {$rd['name']}。";
            }
        } else {
            $error = '无权设置委员长。';
        }
    }
}

// Get pending applications
global $wpdb;
$pending_apps = [];
$results = $wpdb->get_results("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'crrg_promotion_status' AND meta_value = 'pending'");
foreach ($results as $r) {
    $app_rank = get_user_meta($r->user_id, 'crrg_promotion_app', true);
    if ($rank === 'deputy' && $app_rank !== 'advisor') continue;
    $app_user = get_userdata($r->user_id);
    if ($app_user) {
        $pending_apps[] = [
            'user' => $app_user,
            'rank' => crrg_get_rank_data($app_rank),
            'current_rank' => crrg_get_rank_data(crrg_get_rank($r->user_id)),
            'xp' => crrg_get_xp($r->user_id),
            'time' => get_user_meta($r->user_id, 'crrg_promotion_time', true),
        ];
    }
}

// 待审核计数
$pending_count = (int) wp_count_posts('post')->pending;

// GET 方式显示消息
if (!empty($_GET['msg'])) {
    $message = sanitize_text_field($_GET['msg']);
}

// User search
$user_search = trim($_GET['user_search'] ?? '');
if ($user_search) {
    $all_users = get_users(['search' => '*' . $user_search . '*', 'search_columns' => ['user_login', 'user_nicename', 'display_name', 'user_email'], 'orderby' => 'ID', 'order' => 'DESC', 'number' => 100]);
} else {
    $all_users = [];
}

get_header();
?>

<div class="gov-main">
    <div class="gov-content">
        <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;">🛡️ 管理面板<?php if ($pending_count > 0): ?> <span style="background:#C41230;color:#fff;padding:2px 10px;border-radius:10px;font-size:13px;vertical-align:middle;"><?php echo $pending_count; ?> 待审</span><?php endif; ?></h1>
        <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">机密 · 仅限首席顾问及以上</div>

        <?php if ($message): ?>
            <div style="background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:12px 16px;border-radius:4px;margin-bottom:16px;"><?php echo esc_html($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:12px 16px;border-radius:4px;margin-bottom:16px;"><?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <!-- Pending Applications -->
        <h3 style="font-size:16px;color:#1B3A5C;margin:24px 0 12px;"> 待审批 (<?php echo count($pending_apps); ?>)</h3>
        <?php if ($pending_apps): ?>
            <?php foreach ($pending_apps as $app): ?>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:16px;margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <strong><?php echo esc_html($app['user']->display_name); ?></strong>
                            <span style="color:#999;font-size:12px;">(<?php echo $app['user']->user_login; ?>)</span><br>
                            <span><?php echo $app['current_rank']['icon'] . ' ' . $app['current_rank']['name']; ?></span>
                            <span style="color:#999;">→</span>
                            <span style="color:#C41230;"><?php echo $app['rank']['icon'] . ' ' . $app['rank']['name']; ?></span>
                            <span style="color:#999;font-size:12px;">· <?php echo $app['xp']; ?>资历</span>
                            <br><span style="font-size:11px;color:#bbb;"><?php echo $app['time']; ?></span>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <form method="post" style="margin:0;">
                                <?php wp_nonce_field('crrg_admin'); ?>
                                <input type="hidden" name="user_id" value="<?php echo $app['user']->ID; ?>">
                                <input type="hidden" name="admin_action" value="approve">
                                <button type="submit" style="background:#16a34a;color:#fff;border:none;padding:6px 16px;border-radius:3px;cursor:pointer;">批准</button>
                            </form>
                            <form method="post" style="margin:0;">
                                <?php wp_nonce_field('crrg_admin'); ?>
                                <input type="hidden" name="user_id" value="<?php echo $app['user']->ID; ?>">
                                <input type="hidden" name="admin_action" value="reject">
                                <button type="submit" style="background:#dc2626;color:#fff;border:none;padding:6px 16px;border-radius:3px;cursor:pointer;">拒绝</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#999;">暂无待审批申请。</p>
        <?php endif; ?>

        <!-- Article Review -->
        <?php $pending_posts = get_posts(['post_type' => 'post', 'post_status' => 'pending', 'posts_per_page' => 20]); ?>
        <h3 style="font-size:16px;color:#1B3A5C;margin:24px 0 12px;"> 文章审核 (<?php echo count($pending_posts); ?>)</h3>
        <?php if ($pending_posts): ?>
            <?php foreach ($pending_posts as $p): $author = get_userdata($p->post_author); ?>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:16px;margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                        <div style="flex:1;">
                            <a href="<?php echo esc_url(get_preview_post_link($p)); ?>" target="_blank" style="color:#1B3A5C;text-decoration:none;font-weight:bold;"><?php echo esc_html($p->post_title); ?></a>
                            <div style="font-size:12px;color:#999;margin:4px 0;">
                                作者：<?php echo $author ? esc_html($author->display_name) : '未知'; ?>
                                · <?php echo get_the_date('Y-m-d H:i', $p); ?>
                            </div>
                            <div style="font-size:13px;color:#666;margin-top:8px;max-height:60px;overflow:hidden;">
                                <?php echo wp_trim_words(strip_tags($p->post_content), 40); ?>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;flex-shrink:0;margin-left:16px;">
                            <form method="post" style="margin:0;">
                                <?php wp_nonce_field('crrg_admin'); ?>
                                <input type="hidden" name="post_id" value="<?php echo $p->ID; ?>">
                                <input type="hidden" name="admin_action" value="approve_post">
                                <button type="submit" style="background:#16a34a;color:#fff;border:none;padding:6px 16px;border-radius:3px;cursor:pointer;">通过</button>
                            </form>
                            <form method="post" style="margin:0;" onsubmit="var r=prompt('请输入退回原因（将通知作者）：');if(!r||r.trim()==='')return false;this.reject_reason.value=r.trim();return true;">
                                <?php wp_nonce_field('crrg_admin'); ?>
                                <input type="hidden" name="post_id" value="<?php echo $p->ID; ?>">
                                <input type="hidden" name="admin_action" value="reject_post">
                                <input type="hidden" name="reject_reason" value="">
                                <button type="submit" style="background:#dc2626;color:#fff;border:none;padding:6px 16px;border-radius:3px;cursor:pointer;">退回</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#999;">暂无待审核文章。</p>
        <?php endif; ?>

        <!-- Delete Requests -->
        <?php
        $del_requests = get_posts(['post_type' => 'post', 'meta_key' => 'crrg_delete_request', 'posts_per_page' => 20, 'post_status' => 'any']);
        ?>
        <h3 style="font-size:16px;color:#1B3A5C;margin:32px 0 12px;"> 删除申请 (<?php echo count($del_requests); ?>)</h3>
        <?php if ($del_requests): ?>
            <?php foreach ($del_requests as $p): $req_user = get_userdata(get_post_meta($p->ID, 'crrg_delete_request', true)); ?>
                <div style="background:#fff;border:1px solid #fcc;border-radius:4px;padding:16px;margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <strong><?php echo esc_html($p->post_title); ?></strong>
                            <span style="font-size:12px;color:#999;">申请人：<?php echo $req_user ? esc_html($req_user->display_name) : '未知'; ?></span>
                        </div>
                        <form method="post" style="margin:0;">
                            <?php wp_nonce_field('crrg_admin'); ?>
                            <input type="hidden" name="post_id" value="<?php echo $p->ID; ?>">
                            <input type="hidden" name="admin_action" value="approve_delete">
                            <button type="submit" style="background:#dc2626;color:#fff;border:none;padding:6px 16px;border-radius:3px;cursor:pointer;">确认删除</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#999;">暂无删除申请。</p>
        <?php endif; ?>

        <!-- Edit Requests -->
        <?php $edit_requests = get_posts(['post_type' => 'post', 'meta_key' => 'crrg_edit_request', 'posts_per_page' => 20, 'post_status' => 'any']); ?>
        <h3 style="font-size:16px;color:#1B3A5C;margin:32px 0 12px;">✏️ 修改申请 (<?php echo count($edit_requests); ?>)</h3>
        <?php if ($edit_requests): ?>
            <?php foreach ($edit_requests as $p): 
                $req_user = get_userdata(get_post_meta($p->ID, 'crrg_edit_request', true));
                $new_title = get_post_meta($p->ID, 'crrg_edit_title', true);
                $new_content = get_post_meta($p->ID, 'crrg_edit_content', true);
            ?>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:16px;margin-bottom:12px;">
                    <div style="margin-bottom:8px;">
                        <strong><?php echo esc_html($p->post_title); ?></strong>
                        <span style="font-size:12px;color:#999;">申请人：<?php echo $req_user ? esc_html($req_user->display_name) : '未知'; ?></span>
                    </div>
                    <?php if ($new_title !== $p->post_title): ?>
                        <div style="font-size:13px;margin-bottom:4px;"><span style="color:#999;">标题：</span><span style="text-decoration:line-through;color:#c00;"><?php echo esc_html($p->post_title); ?></span> → <span style="color:#16a34a;"><?php echo esc_html($new_title); ?></span></div>
                    <?php endif; ?>
                    <div style="font-size:12px;color:#666;max-height:80px;overflow-y:auto;background:#f9f9f9;padding:8px;border-radius:3px;margin:8px 0;">
                        <?php echo nl2br(esc_html(wp_trim_words($new_content, 80))); ?>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <form method="post" style="margin:0;">
                            <?php wp_nonce_field('crrg_admin'); ?>
                            <input type="hidden" name="post_id" value="<?php echo $p->ID; ?>">
                            <input type="hidden" name="admin_action" value="approve_edit">
                            <button type="submit" style="background:#16a34a;color:#fff;border:none;padding:6px 16px;border-radius:3px;cursor:pointer;">批准</button>
                        </form>
                        <form method="post" style="margin:0;">
                            <?php wp_nonce_field('crrg_admin'); ?>
                            <input type="hidden" name="post_id" value="<?php echo $p->ID; ?>">
                            <input type="hidden" name="admin_action" value="reject_edit">
                            <button type="submit" style="background:#dc2626;color:#fff;border:none;padding:6px 16px;border-radius:3px;cursor:pointer;">拒绝</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#999;">暂无修改申请。</p>
        <?php endif; ?>

        <!-- Article Search -->
        <h3 style="font-size:16px;color:#1B3A5C;margin:32px 0 12px;">🔍 搜索文章</h3>
        <form method="get" style="display:flex;gap:8px;margin-bottom:16px;">
            <input type="text" name="search" value="<?php echo esc_attr($search_query); ?>" placeholder="搜索文章标题..." style="flex:1;padding:8px 12px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;">
            <button type="submit" style="background:#1B3A5C;color:#fff;border:none;padding:8px 20px;border-radius:3px;cursor:pointer;">搜索</button>
        </form>
        <?php if ($search_query && $search_results): ?>
            <?php foreach ($search_results as $p): ?>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:12px 16px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <strong><?php echo esc_html($p->post_title); ?></strong>
                        <span style="font-size:11px;color:#999;">[<?php echo $p->post_status; ?>]</span>
                    </div>
                    <form method="post" style="margin:0;" onsubmit="return confirm('确定永久删除？')">
                        <?php wp_nonce_field('crrg_admin'); ?>
                        <input type="hidden" name="post_id" value="<?php echo $p->ID; ?>">
                        <input type="hidden" name="admin_action" value="delete_post">
                        <button type="submit" style="background:#dc2626;color:#fff;border:none;padding:4px 12px;border-radius:3px;cursor:pointer;font-size:12px;">删除</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php elseif ($search_query): ?>
            <p style="color:#999;">未找到相关文章。</p>
        <?php endif; ?>

        <!-- Announcements -->
        <?php $announcements = crrg_get_announcements(); ?>
        <?php if ($rank === "chairman"): $cur_alert = crrg_get_alert(); ?>
        <h3 style="font-size:16px;color:#C41230;margin:32px 0 12px;"> 紧急预警（仅委员长）</h3>
        <?php if ($cur_alert["active"]): ?>
            <div style="background:#fff;border:2px solid #C41230;border-radius:4px;padding:16px;margin-bottom:8px;"><strong><?php echo esc_html($cur_alert["title"]); ?></strong>：<?php echo esc_html($cur_alert["content"]); ?><form method="post" style="margin-top:8px;"><?php wp_nonce_field("crrg_admin"); ?><input type="hidden" name="admin_action" value="close_alert"><button type="submit" style="background:#dc2626;color:#fff;border:none;padding:6px 16px;border-radius:3px;cursor:pointer;">关闭预警</button></form></div>
        <?php endif; ?>
        <form method="post" style="margin-bottom:20px;"><?php wp_nonce_field("crrg_admin"); ?><input type="hidden" name="admin_action" value="set_alert"><div style="margin-bottom:8px;"><input type="text" name="alert_title" placeholder="预警标题" style="width:100%;padding:8px 12px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;"></div><div style="margin-bottom:8px;"><input type="text" name="alert_content" placeholder="预警内容" style="width:100%;padding:8px 12px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;"></div><div style="margin-bottom:8px;"><select name="alert_color" style="padding:8px 12px;border:1px solid #d5d5d5;border-radius:3px;"><option value="#C41230">红色（紧急）</option><option value="#E67E22">橙色（警告）</option><option value="#F1C40F">黄色（注意）</option><option value="#1B3A5C">蓝色（通知）</option></select></div><button type="submit" style="background:#C41230;color:#fff;border:none;padding:8px 24px;border-radius:4px;cursor:pointer;">发布预警</button></form>
        <?php endif; ?>
        <h3 style="font-size:16px;color:#1B3A5C;margin:32px 0 12px;"> <?php $edit_ann = isset($_GET['edit_ann']) ? (int)$_GET['edit_ann'] : null; $edit_ann_data = ($edit_ann !== null && isset($announcements[$edit_ann])) ? $announcements[$edit_ann] : null; echo $edit_ann_data ? '编辑公告' : '发布公告'; ?> (<?php echo count($announcements); ?> 条)</h3>
        <form method="post" style="margin-bottom:20px;">
            <?php wp_nonce_field('crrg_admin'); ?>
            <input type="hidden" name="admin_action" value="<?php echo $edit_ann_data ? 'edit_announcement' : 'add_announcement'; ?>">
            <?php if ($edit_ann_data): ?><input type="hidden" name="ann_index" value="<?php echo $edit_ann; ?>"><?php endif; ?>
            <div style="margin-bottom:12px;"><input type="text" name="ann_title" placeholder="公告标题" value="<?php echo $edit_ann_data ? esc_attr($edit_ann_data['title']) : ''; ?>" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;"></div>
            <div style="margin-bottom:12px;display:flex;gap:8px;align-items:center;">
                <input type="datetime-local" name="ann_time" value="<?php echo $edit_ann_data ? esc_attr(date('Y-m-d\TH:i', strtotime($edit_ann_data['time'] ?? 'now'))) : ''; ?>" style="padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;flex:1;">
                <span style="font-size:11px;color:#999;white-space:nowrap;">留空用当前时间</span>
            </div>
            <div style="margin-bottom:12px;"><?php wp_editor($edit_ann_data ? $edit_ann_data['content'] : '', 'ann_content', ['textarea_name'=>'ann_content','textarea_rows'=>5,'media_buttons'=>false,'teeny'=>true]); ?></div>
            <button type="submit" style="background:#C41230;color:#fff;border:none;padding:8px 24px;border-radius:4px;cursor:pointer;font-size:14px;"><?php echo $edit_ann_data ? '更新公告' : '发布公告'; ?></button>
            <?php if ($edit_ann_data): ?><a href="/admin/" style="color:#999;font-size:13px;text-decoration:none;margin-left:10px;">取消编辑</a><?php endif; ?>
        </form>
        <?php if ($announcements): foreach ($announcements as $i => $ann): ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:12px 16px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
                <div><strong><?php echo esc_html($ann['title']); ?></strong><span style="color:#999;font-size:12px;margin-left:8px;"><?php echo date('n/j H:i', strtotime($ann['time'])); ?></span></div>
                <div style="display:flex;gap:6px;">
                    <a href="/admin/?edit_ann=<?php echo $i; ?>" style="color:#1B3A5C;text-decoration:none;font-size:12px;padding:4px 12px;border:1px solid #d5d5d5;border-radius:3px;">编辑</a>
                    <form method="post" style="margin:0;"><?php wp_nonce_field('crrg_admin'); ?><input type="hidden" name="admin_action" value="delete_announcement"><input type="hidden" name="ann_index" value="<?php echo $i; ?>"><button type="submit" style="background:#dc2626;color:#fff;border:none;padding:4px 12px;border-radius:3px;cursor:pointer;font-size:12px;">删除</button></form>
                </div>
            </div>
        <?php endforeach; else: ?><p style="color:#999;">暂无公告。</p><?php endif; ?>

        <h3 style="font-size:16px;color:#1B3A5C;margin:32px 0 12px;"> 用户管理</h3>
        <form method="get" style="margin-bottom:16px;display:flex;gap:8px;align-items:center;">
            <input type="text" name="user_search" value="<?php echo esc_attr($user_search); ?>" placeholder="搜索用户名、昵称或邮箱..." style="flex:1;max-width:400px;padding:8px 12px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;">
            <button type="submit" style="background:#1B3A5C;color:#fff;border:none;padding:8px 20px;border-radius:4px;cursor:pointer;font-size:14px;">搜索</button>
            <?php if ($user_search): ?>
                <a href="<?php echo esc_url(remove_query_arg('user_search')); ?>" style="color:#999;font-size:13px;text-decoration:none;margin-left:4px;">清除</a>
            <?php endif; ?>
        </form>

        <?php if (!$user_search): ?>
            <p style="color:#999;font-size:13px;">输入用户名、昵称或邮箱进行搜索。</p>
        <?php elseif (empty($all_users)): ?>
            <p style="color:#999;font-size:13px;">未找到匹配「<?php echo esc_html($user_search); ?>」的用户。</p>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f5f5f5;text-align:left;">
                        <th style="padding:8px 12px;border:1px solid #e0e0e0;">用户</th>
                        <th style="padding:8px 12px;border:1px solid #e0e0e0;">等级</th>
                        <th style="padding:8px 12px;border:1px solid #e0e0e0;">资历</th>
                        <th style="padding:8px 12px;border:1px solid #e0e0e0;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_users as $u): 
                        $ur = crrg_get_rank_data(crrg_get_rank($u->ID));
                        $uxp = crrg_get_xp($u->ID);
                    ?>
                    <tr>
                        <td style="padding:8px 12px;border:1px solid #e0e0e0;">
                            <?php echo get_avatar($u->ID, 24, '', '', ['style' => 'border-radius:50%;vertical-align:middle;']); ?>
                            <?php echo esc_html($u->display_name); ?>
                            <span style="color:#999;font-size:11px;">(<?php echo $u->user_login; ?>)</span>
                        </td>
                        <td style="padding:8px 12px;border:1px solid #e0e0e0;"><?php echo $ur['icon'] . ' ' . $ur['name']; ?></td>
                        <td style="padding:8px 12px;border:1px solid #e0e0e0;"><?php echo $uxp; ?></td>
                        <td style="padding:8px 12px;border:1px solid #e0e0e0;">
                            <form method="post" style="display:inline;margin:0;">
                                <?php wp_nonce_field('crrg_admin'); ?>
                                <input type="hidden" name="user_id" value="<?php echo $u->ID; ?>">
                                <input type="hidden" name="admin_action" value="add_xp">
                                <input type="number" name="xp_amount" value="10" min="1" max="1000" style="width:60px;padding:3px 6px;border:1px solid #d5d5d5;border-radius:3px;font-size:12px;">
                                <button type="submit" style="background:#1B3A5C;color:#fff;border:none;padding:4px 10px;border-radius:3px;cursor:pointer;font-size:12px;">加资历</button>
                            </form>
                            <?php if ($rank === 'chairman'): ?>
                            <form method="post" style="display:inline;margin:0 0 0 4px;">
                                <?php wp_nonce_field('crrg_admin'); ?>
                                <input type="hidden" name="user_id" value="<?php echo $u->ID; ?>">
                                <input type="hidden" name="admin_action" value="set_rank">
                                <select name="new_rank" style="padding:3px;border:1px solid #d5d5d5;border-radius:3px;font-size:12px;">
                                    <?php foreach (CRRG_RANKS as $r): ?>
                                        <option value="<?php echo $r['id']; ?>" <?php if ($ur['id'] === $r['id']) echo 'selected'; ?>><?php echo $r['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" style="background:#C41230;color:#fff;border:none;padding:4px 10px;border-radius:3px;cursor:pointer;font-size:12px;">设等级</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
