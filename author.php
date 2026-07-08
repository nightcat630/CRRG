<?php
/* 作者个人主页 */
get_header();

$author = get_queried_object();
if (!$author || !($author instanceof WP_User)) {
    echo '<div class="gov-main"><div class="gov-content"><p>用户不存在</p></div></div>';
    get_footer();
    return;
}

$user_id = $author->ID;
$xp = crrg_get_xp($user_id);
$rank_id = crrg_get_rank($user_id);
$rank = crrg_get_rank_data($rank_id);
$avatar = get_avatar_url($user_id, ['size' => 120]);
$joined = strtotime($author->user_registered);

// 报告
$reports = get_posts([
    'post_type' => 'post', 'post_status' => 'publish',
    'author' => $user_id, 'posts_per_page' => 20,
]);
$report_count = count($reports);

// 收藏
$favs = get_user_meta($user_id, 'crrg_favorites', true) ?: [];
$fav_count = count($favs);

// 评论数
$comment_count = get_comments(['user_id' => $user_id, 'count' => true]);

// 等级颜色
$rank_colors = ['chairman' => '#C41230', 'deputy' => '#F0A500', 'advisor' => '#8B5CF6', 'chief' => '#1B3A5C', 'tl' => '#0EA5E9', 'operative' => '#16A34A', 'observer' => '#999'];
$rank_color = $rank_colors[$rank_id] ?? '#999';
?>

<div class="gov-main">
<div class="gov-content">

    <div style="display:flex;gap:24px;align-items:flex-start;margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid #e0e0e0;">
        <img src="<?php echo esc_url($avatar); ?>" alt="" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid <?php echo $rank_color; ?>;flex-shrink:0;" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect fill=%22%23ddd%22 width=%22100%22 height=%22100%22/><text x=%2250%22 y=%2260%22 text-anchor=%22middle%22 fill=%22%23999%22 font-size=%2240%22>👤</text></svg>'">
        <div style="flex:1;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                <h1 style="font-size:22px;color:#1B3A5C;margin:0;font-weight:bold;"><?php echo esc_html($author->display_name); ?></h1>
                <span style="background:<?php echo $rank_color; ?>;color:#fff;padding:3px 12px;border-radius:3px;font-size:13px;font-weight:600;"><?php echo $rank['icon']; ?> <?php echo esc_html($rank['name']); ?></span>
            </div>
            <div style="font-size:13px;color:#999;margin-bottom:10px;line-height:1.8;">
                📊 资历：<strong style="color:#1B3A5C;"><?php echo $xp; ?></strong>
                &nbsp;·&nbsp; 📄 报告：<strong style="color:#1B3A5C;"><?php echo $report_count; ?></strong>
                &nbsp;·&nbsp; ⭐ 收藏：<strong style="color:#1B3A5C;"><?php echo $fav_count; ?></strong>
                &nbsp;·&nbsp; 💬 评论：<strong style="color:#1B3A5C;"><?php echo $comment_count; ?></strong>
                <br>🕐 入职：<?php echo date('Y年m月d日', $joined); ?> · 编号 CRRG-<?php echo str_pad($user_id, 4, '0', STR_PAD_LEFT); ?>
            </div>
        </div>
    </div>

    <?php if ($reports): ?>
        <h2 style="font-size:16px;color:#1B3A5C;margin:0 0 14px;padding-bottom:8px;border-bottom:2px solid #C41230;">📄 发布的报告 (<?php echo $report_count; ?>)</h2>
        <?php foreach ($reports as $p):
            $thumb = ''; preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/', $p->post_content, $m);
            if ($m) $thumb = $m[1];
            $tags = wp_get_post_tags($p->ID);
            $comments = get_comments_number($p->ID);
        ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:14px 16px;margin-bottom:8px;display:flex;gap:14px;transition:box-shadow 0.2s;" onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.06)'" onmouseout="this.style.boxShadow='none'">
                <?php if ($thumb): ?>
                    <img src="<?php echo esc_url($thumb); ?>" style="width:140px;height:88px;object-fit:cover;border-radius:3px;flex-shrink:0;" alt="">
                <?php endif; ?>
                <div style="flex:1;min-width:0;">
                    <a href="<?php echo get_permalink($p); ?>" style="font-size:15px;font-weight:bold;color:#1B3A5C;text-decoration:none;"><?php echo esc_html($p->post_title); ?></a>
                    <div style="font-size:12px;color:#999;margin:4px 0;"><?php echo get_the_date('Y-m-d', $p); ?> · 💬 <?php echo $comments; ?></div>
                    <?php if ($tags): ?>
                        <div style="margin-bottom:4px;">
                            <?php foreach ($tags as $tag): ?>
                                <span style="display:inline-block;background:#f0f0f0;color:#666;padding:1px 8px;border-radius:2px;font-size:11px;margin-right:4px;"><?php echo esc_html($tag->name); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div style="font-size:13px;color:#666;line-height:1.5;"><?php echo wp_trim_words(strip_tags($p->post_content), 40); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="color:#999;text-align:center;padding:40px;">暂无发布的报告</p>
    <?php endif; ?>

</div>

<div class="gov-sidebar">
    <div class="widget">
        <div class="widget-title">📊 统计数据</div>
        <div style="font-size:13px;color:#666;line-height:2;">
            <div>等级：<?php echo $rank['icon']; ?> <?php echo esc_html($rank['name']); ?></div>
            <div>资历：<?php echo $xp; ?></div>
            <div>报告：<?php echo $report_count; ?> 篇</div>
            <div>收藏：<?php echo $fav_count; ?> 个</div>
            <div>评论：<?php echo $comment_count; ?> 条</div>
            <div>入职：<?php echo date('Y-m-d', $joined); ?></div>
        </div>
    </div>
    <div class="widget">
        <div class="widget-title">📌 最新通知</div>
        <ul style="list-style:none;padding:0;margin:0;font-size:13px;line-height:2;">
            <?php $anns = crrg_get_announcements(); if ($anns): foreach (array_slice($anns,0,4) as $ann): ?>
                <li>· <a href="/notices/" style="color:#333;text-decoration:none;"><?php echo esc_html($ann['title']); ?></a></li>
            <?php endforeach; else: ?><li>· 暂无通知</li><?php endif; ?>
        </ul>
    </div>
</div>
</div>

<?php get_footer(); ?>
