<?php
/* Template Name: 晋升申请 */

if (!is_user_logged_in()) { wp_redirect(wp_login_url()); exit; }

$user_id = get_current_user_id();
$user = wp_get_current_user();
$current_rank = crrg_get_rank($user_id);
$current_data = crrg_get_rank_data($current_rank);
$xp = crrg_get_xp($user_id);
$message = '';
$error = '';

// Find eligible ranks to apply for
$eligible = [];
$current_index = array_search($current_rank, array_column(CRRG_RANKS, 'id'));
foreach (CRRG_RANKS as $i => $r) {
    if (!empty($r['apply']) && $xp >= $r['xp'] && $i > $current_index && $r['id'] !== 'chairman') {
        $pending = get_user_meta($user_id, 'crrg_promotion_app', true);
        if ($pending !== $r['id']) {
            $eligible[] = $r;
        }
    }
}

// Also show existing application
$pending_rank = get_user_meta($user_id, 'crrg_promotion_app', true);
$pending_status = get_user_meta($user_id, 'crrg_promotion_status', true);
$pending_data = null;
if ($pending_rank) {
    $pending_data = crrg_get_rank_data($pending_rank);
}

// Handle application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_apply_promotion')) {
    $apply_rank = sanitize_text_field($_POST['apply_rank'] ?? '');
    $valid = false;
    foreach ($eligible as $e) { if ($e['id'] === $apply_rank) $valid = true; }
    
    if ($valid) {
        update_user_meta($user_id, 'crrg_promotion_app', $apply_rank);
        update_user_meta($user_id, 'crrg_promotion_status', 'pending');
        update_user_meta($user_id, 'crrg_promotion_time', current_time('mysql'));
        $message = '晋升申请已提交，请等待上级审批。';
        $pending_rank = $apply_rank;
        $pending_status = 'pending';
        $pending_data = crrg_get_rank_data($apply_rank);
        $eligible = [];
    } else {
        $error = '无效的申请。';
    }
}

// Cancel application
if (isset($_GET['cancel']) && wp_verify_nonce($_GET['_nonce'] ?? '', 'crrg_cancel_app')) {
    delete_user_meta($user_id, 'crrg_promotion_app');
    delete_user_meta($user_id, 'crrg_promotion_status');
    delete_user_meta($user_id, 'crrg_promotion_time');
    $message = '申请已取消。';
    $pending_rank = null;
    $pending_status = null;
    $pending_data = null;
}

// Handle approval/rejection (for higher ranks)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_review_promotion')) {
    $app_user_id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action_type'] ?? '';
    
    // Check if current user can review
    $reviewer_rank = crrg_get_rank($user_id);
    $can_review = ($reviewer_rank === 'deputy' || $reviewer_rank === 'chairman');
    
    // Deputy can only approve advisor; chairman can approve both
    $app_rank = get_user_meta($app_user_id, 'crrg_promotion_app', true);
    if ($reviewer_rank === 'deputy' && $app_rank !== 'advisor') $can_review = false;
    
    if ($can_review) {
        if ($action === 'approve') {
            update_user_meta($app_user_id, 'crrg_rank', $app_rank);
            delete_user_meta($app_user_id, 'crrg_promotion_app');
            delete_user_meta($app_user_id, 'crrg_promotion_status');
            $message = '已批准晋升申请。';
        } elseif ($action === 'reject') {
            update_user_meta($app_user_id, 'crrg_promotion_status', 'rejected');
            update_user_meta($app_user_id, 'crrg_promotion_reject_reason', sanitize_text_field($_POST['reason'] ?? ''));
            $message = '已拒绝晋升申请。';
        }
    }
}

// Get pending applications for review
$pending_apps = [];
if (in_array(crrg_get_rank($user_id), ['deputy', 'chairman'])) {
    global $wpdb;
    $results = $wpdb->get_results("
        SELECT user_id, meta_value as rank_applied 
        FROM {$wpdb->usermeta} 
        WHERE meta_key = 'crrg_promotion_status' AND meta_value = 'pending'
    ");
    foreach ($results as $r) {
        $app_rank = get_user_meta($r->user_id, 'crrg_promotion_app', true);
        if (crrg_get_rank($user_id) === 'deputy' && $app_rank !== 'advisor') continue;
        $app_user = get_userdata($r->user_id);
        $pending_apps[] = [
            'user' => $app_user,
            'rank' => crrg_get_rank_data($app_rank),
            'time' => get_user_meta($r->user_id, 'crrg_promotion_time', true),
        ];
    }
}

get_header();
?>

<div class="gov-main">
    <div class="gov-content">
        <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;">晋升申请</h1>
        <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">中央重生抵御小组 · 人事管理系统</div>

        <?php if ($message): ?>
            <div style="background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:12px 16px;border-radius:4px;margin-bottom:16px;"><?php echo esc_html($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:12px 16px;border-radius:4px;margin-bottom:16px;"><?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <!-- Current status -->
        <div style="background:#f8f9fa;border:1px solid #e0e0e0;border-radius:4px;padding:16px;margin-bottom:24px;">
            <div style="font-size:14px;">
                当前等级：<strong><?php echo $current_data['icon'] . ' ' . $current_data['name']; ?></strong>
                &nbsp;|&nbsp; 经验值：<strong><?php echo $xp; ?></strong>
            </div>
        </div>

        <?php if ($pending_data): ?>
            <!-- Pending application -->
            <div style="background:#fff8e1;border:1px solid #ffcc02;border-radius:4px;padding:16px;margin-bottom:20px;">
                <strong>📋 待审批申请</strong><br>
                申请等级：<?php echo $pending_data['icon'] . ' ' . $pending_data['name']; ?><br>
                状态：<?php echo $pending_status === 'pending' ? '⏳ 等待审批' : '❌ 已拒绝'; ?><br>
                <?php if ($pending_status === 'rejected'): ?>
                    拒绝原因：<?php echo esc_html(get_user_meta($user_id, 'crrg_promotion_reject_reason', true) ?: '未提供'); ?><br>
                <?php endif; ?>
                <a href="?cancel=1&_nonce=<?php echo wp_create_nonce('crrg_cancel_app'); ?>" style="color:#c00;font-size:12px;margin-top:8px;display:inline-block;">取消申请</a>
            </div>
        <?php elseif ($eligible): ?>
            <!-- Apply form -->
            <div style="margin-bottom:20px;">
                <h3 style="font-size:16px;color:#1B3A5C;margin-bottom:12px;">可申请的等级</h3>
                <?php foreach ($eligible as $r): ?>
                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:16px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <strong><?php echo $r['icon'] . ' ' . $r['name']; ?></strong>
                            <span style="color:#999;font-size:12px;margin-left:8px;">需要 <?php echo $r['xp']; ?> 经验</span>
                        </div>
                        <form method="post" style="margin:0;">
                            <?php wp_nonce_field('crrg_apply_promotion'); ?>
                            <input type="hidden" name="apply_rank" value="<?php echo $r['id']; ?>">
                            <button type="submit" style="background:#C41230;color:#fff;border:none;padding:8px 20px;border-radius:3px;cursor:pointer;font-size:13px;">申请晋升</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color:#999;">暂无可申请的等级。达到更高等级所需经验值后可在此申请。</p>
        <?php endif; ?>

        <!-- Review queue -->
        <?php if (!empty($pending_apps)): ?>
            <div style="margin-top:32px;border-top:1px solid #eee;padding-top:20px;">
                <h3 style="font-size:16px;color:#1B3A5C;margin-bottom:12px;">📋 待审批列表（<?php echo count($pending_apps); ?>）</h3>
                <?php foreach ($pending_apps as $app): ?>
                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:16px;margin-bottom:12px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <strong><?php echo esc_html($app['user']->display_name); ?></strong>
                                <span style="color:#999;">申请晋升为 <?php echo $app['rank']['icon'] . ' ' . $app['rank']['name']; ?></span>
                                <br><span style="font-size:11px;color:#bbb;"><?php echo $app['time']; ?></span>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <form method="post" style="margin:0;display:inline;">
                                    <?php wp_nonce_field('crrg_review_promotion'); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $app['user']->ID; ?>">
                                    <input type="hidden" name="action_type" value="approve">
                                    <button type="submit" style="background:#16a34a;color:#fff;border:none;padding:6px 16px;border-radius:3px;cursor:pointer;font-size:12px;">批准</button>
                                </form>
                                <form method="post" style="margin:0;display:inline;">
                                    <?php wp_nonce_field('crrg_review_promotion'); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $app['user']->ID; ?>">
                                    <input type="hidden" name="action_type" value="reject">
                                    <input type="text" name="reason" placeholder="拒绝原因" style="padding:4px 8px;border:1px solid #d5d5d5;border-radius:3px;font-size:12px;width:100px;">
                                    <button type="submit" style="background:#dc2626;color:#fff;border:none;padding:6px 12px;border-radius:3px;cursor:pointer;font-size:12px;margin-left:4px;">拒绝</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="gov-sidebar">
        <div class="widget">
            <div class="widget-title">等级说明</div>
            <div style="font-size:12px;line-height:2.2;">
                <?php foreach (CRRG_RANKS as $r): ?>
                    <div><?php echo $r['icon'] . ' ' . $r['name']; ?> — <?php echo $r['xp']; ?>exp<?php echo !empty($r['apply']) ? ' (需申请)' : ''; ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
