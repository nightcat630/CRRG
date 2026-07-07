<?php
/* Template Name: 通知列表 */

get_header();
$announcements = crrg_get_announcements();
?>
<div class="gov-main">
    <div class="gov-content">
        <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;">📢 最新通知</h1>
        <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">中央重生抵御小组 · 公告板</div>

        <?php if ($announcements): foreach ($announcements as $ann): ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:16px;margin-bottom:12px;">
                <h3 style="font-size:16px;color:#1B3A5C;margin:0 0 8px;"><?php echo esc_html($ann['title']); ?></h3>
                <div style="font-size:12px;color:#999;margin-bottom:8px;"><?php echo $ann['time']; ?></div>
                <div style="font-size:14px;color:#333;line-height:1.8;"><?php echo wpautop($ann['content']); ?></div>
            </div>
        <?php endforeach; else: ?>
            <p style="color:#999;text-align:center;padding:40px;">暂无公告</p>
        <?php endif; ?>
    </div>
</div>
<?php get_footer(); ?>
