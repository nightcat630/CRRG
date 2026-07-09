<?php
/* Template Name: 公告页 */
get_header();
$announcements = crrg_get_announcements();
?>
<div class="gov-main">
<div class="gov-content">
    <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;">📢 通知公告</h1>
    <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">中央重生抵御小组 · 官方通知</div>
    <?php if ($announcements): foreach ($announcements as $ann): ?>
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:16px 20px;margin-bottom:12px;">
            <h3 style="font-size:16px;color:#1B3A5C;margin:0 0 8px;"><?php echo esc_html($ann['title']); ?></h3>
            <div style="font-size:12px;color:#999;margin-bottom:8px;"><?php echo date('Y年n月j日 H:i', strtotime($ann['time'])); ?></div>
            <div style="font-size:14px;color:#333;line-height:1.8;"><?php echo wpautop($ann['content']); ?></div>
        </div>
    <?php endforeach; else: ?>
        <p style="color:#999;text-align:center;padding:60px;">暂无公告</p>
    <?php endif; ?>
</div>
<div class="gov-sidebar">
        <?php echo crrg_announcement_carousel(); ?>
    <div class="widget">
        <div class="widget-title">📌 最新通知</div>
        <ul style="list-style:none;padding:0;margin:0;font-size:13px;line-height:2;">
            <?php if ($announcements): foreach (array_slice($announcements,0,5) as $ann): ?>
                <li>· <?php echo esc_html($ann['title']); ?></li>
            <?php endforeach; else: ?><li>· 暂无通知</li><?php endif; ?>
        </ul>
    </div>
</div>
</div>
<?php get_footer(); ?>
