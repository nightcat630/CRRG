<?php
/* Template Name: 个人资料 */

if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}

$user = wp_get_current_user();
$message = '';
$error = '';

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['avatar']['tmp_name'])) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    
    $mimes = ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];
    $overrides = ['test_form' => false, 'mimes' => $mimes];
    $file = wp_handle_upload($_FILES['avatar'], $overrides);
    
    if (isset($file['error'])) {
        $error = '头像上传失败：' . $file['error'];
    } else {
        update_user_meta($user->ID, 'custom_avatar', $file['url']);
        $message = '头像已更新';
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_profile_update')) {
    $userdata = ['ID' => $user->ID];
    
    $nickname = sanitize_text_field($_POST['nickname'] ?? '');
    if ($nickname) $userdata['nickname'] = $nickname;
    
    $display_name = sanitize_text_field($_POST['display_name'] ?? '');
    if ($display_name) $userdata['display_name'] = $display_name;
    
    $email = sanitize_email($_POST['email'] ?? '');
    if ($email && $email !== $user->user_email) {
        if (email_exists($email) && email_exists($email) !== $user->ID) {
            $error = $error ?: '该邮箱已被其他用户使用';
        } else {
            $userdata['user_email'] = $email;
        }
    }
    
    $pass1 = $_POST['pass1'] ?? '';
    $pass2 = $_POST['pass2'] ?? '';
    if ($pass1 && $pass1 === $pass2) {
        wp_set_password($pass1, $user->ID);
        $message = $message ?: '密码已更新，请重新登录';
    } elseif ($pass1) {
        $error = $error ?: '两次密码不一致';
    }
    
    if (!$error) {
        $uid = wp_update_user($userdata);
        if (is_wp_error($uid)) {
            $error = $uid->get_error_message();
        } else {
            if (!$message) $message = '个人资料已更新';
        }
    }
}

$custom_avatar = get_user_meta($user->ID, 'custom_avatar', true);
$avatar_url = $custom_avatar ?: get_avatar_url($user->ID, ['size' => 120]);

get_header();
?>

<div class="gov-main">
    <div class="gov-content">
        <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;">修改个人资料</h1>
        <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">中央重生抵御小组 · 账户管理</div>

        <?php if ($message): ?>
            <div style="background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:12px 16px;border-radius:4px;margin-bottom:16px;"><?php echo esc_html($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:12px 16px;border-radius:4px;margin-bottom:16px;"><?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <div style="display:flex;gap:24px;align-items:flex-start;">
            <div style="text-align:center;flex-shrink:0;">
                <img src="<?php echo esc_url($avatar_url); ?>" style="width:120px;height:120px;border-radius:4px;border:2px solid #e0e0e0;object-fit:cover;">
                <form method="post" enctype="multipart/form-data" style="margin-top:10px;">
                    <label style="display:inline-block;background:#f0f0f0;color:#555;padding:6px 12px;border-radius:3px;font-size:12px;cursor:pointer;border:1px solid #d5d5d5;">
                        上传头像
                        <input type="file" name="avatar" accept="image/*" style="display:none;" onchange="this.form.submit()">
                    </label>
                </form>
                <p style="font-size:11px;color:#bbb;margin-top:6px;">支持 JPG/PNG/GIF</p>
            </div>

            <form method="post" style="flex:1;">
                <?php wp_nonce_field('crrg_profile_update'); ?>
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="padding:10px 12px 10px 0;color:#666;width:100px;font-size:14px;">用户名</td>
                        <td style="padding:10px 0;"><input type="text" value="<?php echo esc_attr($user->user_login); ?>" disabled style="width:100%;padding:8px 12px;border:1px solid #e0e0e0;border-radius:3px;background:#f5f5f5;font-size:14px;"></td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px 10px 0;color:#666;font-size:14px;">昵称</td>
                        <td style="padding:10px 0;"><input type="text" name="nickname" value="<?php echo esc_attr($user->nickname); ?>" style="width:100%;padding:8px 12px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;"></td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px 10px 0;color:#666;font-size:14px;">显示名称</td>
                        <td style="padding:10px 0;"><input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" style="width:100%;padding:8px 12px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;"></td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px 10px 0;color:#666;font-size:14px;">邮箱</td>
                        <td style="padding:10px 0;"><input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" style="width:100%;padding:8px 12px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;"></td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px 10px 0;color:#666;font-size:14px;">新密码</td>
                        <td style="padding:10px 0;"><input type="password" name="pass1" placeholder="留空则不修改" style="width:100%;padding:8px 12px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;"></td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px 10px 0;color:#666;font-size:14px;">确认密码</td>
                        <td style="padding:10px 0;"><input type="password" name="pass2" placeholder="留空则不修改" style="width:100%;padding:8px 12px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;"></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="padding:16px 0 0;">
                            <button type="submit" style="background:#C41230;color:#fff;border:none;padding:10px 32px;border-radius:3px;font-size:15px;cursor:pointer;font-weight:bold;">保存修改</button>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
    </div>
    <div class="gov-sidebar">
        <div class="widget">
            <div class="widget-title">账户信息</div>
            <div style="font-size:13px;line-height:2;">
                <div>注册时间：<?php echo date('Y-m-d', strtotime($user->user_registered)); ?></div>
                <div>用户角色：<?php echo implode(', ', $user->roles); ?></div>
                <?php $rank = crrg_get_rank_data(crrg_get_rank($user->ID)); $xp = crrg_get_xp($user->ID); ?>
                <div>当前等级：<?php echo $rank['icon'] . ' ' . $rank['name']; ?></div>
                <div>资历：<?php echo $xp; ?></div>
                <?php $next = null; foreach (CRRG_RANKS as $r) { if ($xp < $r['xp'] && !$next) $next = $r; } ?>
                <?php if ($next): ?>
                <div style="margin-top:4px;color:#999;">下一级：<?php echo $next['name']; ?>（还需 <?php echo $next['xp'] - $xp; ?> 资历）</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
