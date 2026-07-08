<?php
/* Template Name: 事件地图 */
get_header();

// 获取有地点的报告
$location_posts = get_posts([
    'post_type' => 'post', 'post_status' => 'publish',
    'meta_key' => 'crrg_location', 'posts_per_page' => 50,
    'meta_query' => [crrg_get_access_meta_query()],
]);
?>
<div class="gov-main">
<div class="gov-content">
    <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;">🗺️ 事件态势图</h1>
    <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">中央重生抵御小组 · 地理信息</div>

    <?php if ($location_posts): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;">
        <?php foreach ($location_posts as $p):
            $loc = get_post_meta($p->ID, 'crrg_location', true);
            $coords = get_post_meta($p->ID, 'crrg_coords', true);
            $threat = get_post_meta($p->ID, 'crrg_threat_level', true);
            $colors = ['ren'=>'#16a34a','gui'=>'#8B5CF6','mo'=>'#C41230','shen'=>'#F0A500'];
            $dot = $colors[$threat] ?? '#999';
        ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-left:4px solid <?php echo $dot; ?>;border-radius:4px;padding:12px 16px;">
                <div style="font-size:14px;font-weight:600;color:#1B3A5C;">📍 <?php echo esc_html($loc); ?></div>
                <a href="<?php echo get_permalink($p); ?>" style="font-size:13px;color:#555;text-decoration:none;"><?php echo esc_html($p->post_title); ?></a>
                <div style="font-size:11px;color:#999;margin-top:2px;"><?php echo get_the_date('Y-m-d', $p); ?></div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align:center;padding:60px;color:#999;">
            <div style="font-size:48px;margin-bottom:12px;">🗺️</div>
            <p>暂无标注地点的报告</p>
            <p style="font-size:12px;">提交报告时可添加地点信息，将在此处显示</p>
        </div>
    <?php endif; ?>
</div>
<div class="gov-sidebar">
    <div class="widget">
        <div class="widget-title">📊 威胁分布</div>
        <?php
        $all_posts = get_posts(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>100,'meta_query'=>[crrg_get_access_meta_query()]]);
        $counts = ['ren'=>0,'gui'=>0,'mo'=>0,'shen'=>0,'none'=>0];
        foreach ($all_posts as $ap) {
            $t = get_post_meta($ap->ID, 'crrg_threat_level', true);
            if ($t && isset($counts[$t])) $counts[$t]++; else $counts['none']++;
        }
        ?>
        <div style="font-size:13px;line-height:2.2;">
            <div>👤 人：<?php echo $counts['ren']; ?> 件</div>
            <div>👻 鬼：<?php echo $counts['gui']; ?> 件</div>
            <div>👿 魔：<?php echo $counts['mo']; ?> 件</div>
            <div>👼 神：<?php echo $counts['shen']; ?> 件</div>
            <div style="color:#999;">未评级：<?php echo $counts['none']; ?> 件</div>
        </div>
    </div>
</div>
</div>
<?php get_footer(); ?>
