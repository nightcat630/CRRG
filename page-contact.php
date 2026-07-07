<?php
/* Template Name: 联系我们 */

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_contact')) {
    $name = sanitize_text_field($_POST['name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $subject = sanitize_text_field($_POST['subject'] ?? '');
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    
    if ($name && $email && $subject && $message) {
        $to = get_option('admin_email');
        $body = "姓名：{$name}\n邮箱：{$email}\n主题：{$subject}\n\n{$message}";
        $headers = ['Content-Type: text/plain; charset=UTF-8', "From: {$name} <{$email}>"];
        wp_mail($to, '[CRRG联系] ' . $subject, $body, $headers);
        $sent = true;
    } else {
        $error = '请填写所有必填字段';
    }
}

get_header();
?>
<div class="gov-main">
    <div class="gov-content">
        <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;">📬 联系我们</h1>
        <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">中央重生抵御小组 · 举报与联络</div>

        <?php if ($sent): ?>
            <div style="background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:20px;border-radius:4px;text-align:center;">
                <div style="font-size:48px;">✅</div>
                <h3>消息已发送</h3>
                <p>我们将尽快回复您的来信。</p>
            </div>
        <?php else: ?>
            <?php if ($error): ?><div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:12px 16px;border-radius:4px;margin-bottom:16px;"><?php echo esc_html($error); ?></div><?php endif; ?>
            <form method="post">
                <?php wp_nonce_field('crrg_contact'); ?>
                <div style="margin-bottom:16px;"><label style="display:block;font-weight:bold;margin-bottom:6px;">您的姓名 *</label><input type="text" name="name" required style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;"></div>
                <div style="margin-bottom:16px;"><label style="display:block;font-weight:bold;margin-bottom:6px;">邮箱地址 *</label><input type="email" name="email" required style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;"></div>
                <div style="margin-bottom:16px;"><label style="display:block;font-weight:bold;margin-bottom:6px;">主题 *</label><input type="text" name="subject" required style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;" placeholder="举报、建议或其他"></div>
                <div style="margin-bottom:16px;"><label style="display:block;font-weight:bold;margin-bottom:6px;">内容 *</label><textarea name="message" rows="8" required style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;font-family:inherit;resize:vertical;"></textarea></div>
                <button type="submit" style="background:#C41230;color:#fff;border:none;padding:12px 36px;border-radius:4px;font-size:15px;cursor:pointer;font-weight:bold;">发送</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php get_footer(); ?>
