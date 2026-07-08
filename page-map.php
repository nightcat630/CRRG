<?php
/* Template Name: 事件地图 */
get_header();

$location_posts = get_posts([
    'post_type' => 'post', 'post_status' => 'publish',
    'meta_key' => 'crrg_location', 'posts_per_page' => 50,
    'meta_query' => [crrg_get_access_meta_query()],
]);

$coord_map = [
    '广西横州市云表镇' => [72, 78, '广西'],
    '新疆克拉玛依' => [22, 22, '新疆'],
    '中原地区（传说）' => [58, 48, '中原'],
    '全球范围' => [30, 65, '全球'],
    '宇宙学范畴' => [35, 55, ''],
    '不明' => [55, 62, ''],
    '未知城市' => [62, 55, ''],
];
?>
<div class="gov-main">
<div class="gov-content">
    <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;">🗺️ 事件态势图</h1>
    <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">中央重生抵御小组 · 地理信息</div>

    <div style="position:relative;max-width:600px;margin:0 auto 20px;background:#f8f9fa;border:1px solid #e0e0e0;border-radius:4px;padding:10px;">
        <svg viewBox="0 0 100 100" style="width:100%;height:auto;">
            <!-- 简化中国轮廓 -->
            <path d="M85 20 L88 22 L90 28 L88 32 L92 38 L90 45 L95 48 L98 52 L96 58 L90 60 L88 55 L85 52 L82 58 L78 60 L75 65 L72 70 L68 72 L65 75 L60 73 L55 78 L50 80 L45 78 L42 75 L38 78 L35 75 L32 72 L28 68 L25 65 L22 62 L20 58 L18 52 L15 48 L12 42 L10 38 L8 35 L10 30 L15 28 L20 25 L25 22 L30 20 L35 18 L40 20 L45 22 L50 20 L55 18 L60 20 L65 22 L70 20 L75 18 L80 17 Z"
                fill="#e8e8e8" stroke="#bbb" stroke-width="0.5"/>
            <!-- 南海诸岛示意 -->
            <rect x="78" y="85" width="8" height="6" rx="1" fill="#e8e8e8" stroke="#bbb" stroke-width="0.3"/>
            
            <?php foreach ($location_posts as $p):
                $loc = get_post_meta($p->ID, 'crrg_location', true);
                $threat = get_post_meta($p->ID, 'crrg_threat_level', true);
                $coord = $coord_map[$loc] ?? [50, 50, ''];
                $colors = ['ren'=>'#16a34a','gui'=>'#8B5CF6','mo'=>'#C41230','shen'=>'#F0A500'];
                $color = $colors[$threat] ?? '#999';
                $x = $coord[0]; $y = $coord[1];
            ?>
                <circle cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" r="1.5" fill="<?php echo $color; ?>" stroke="#fff" stroke-width="0.3">
                    <title><?php echo esc_html($p->post_title); ?> — <?php echo esc_html($loc); ?></title>
                </circle>
                <circle cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" r="2.5" fill="none" stroke="<?php echo $color; ?>" stroke-width="0.2" opacity="0.4">
                    <animate attributeName="r" from="2.5" to="4.5" dur="2s" repeatCount="indefinite"/>
                    <animate attributeName="opacity" from="0.4" to="0" dur="2s" repeatCount="indefinite"/>
                </circle>
            <?php endforeach; ?>
        </svg>
        <div style="display:flex;justify-content:center;gap:16px;margin-top:8px;font-size:11px;color:#999;">
            <span>🟢 人</span><span>🟣 鬼</span><span>🔴 魔</span><span>🟡 神</span>
        </div>
    </div>

    <?php if ($location_posts): ?>
    <h3 style="font-size:15px;color:#1B3A5C;margin-bottom:10px;">📋 地点清单</h3>
    <?php foreach ($location_posts as $p):
        $loc = get_post_meta($p->ID, 'crrg_location', true);
        $threat = get_post_meta($p->ID, 'crrg_threat_level', true);
        $colors = ['ren'=>'#16a34a','gui'=>'#8B5CF6','mo'=>'#C41230','shen'=>'#F0A500'];
        $dot = $colors[$threat] ?? '#999';
    ?>
        <div style="background:#fff;border:1px solid #e0e0e0;border-left:4px solid <?php echo $dot; ?>;border-radius:4px;padding:10px 14px;margin-bottom:6px;">
            <span style="font-size:13px;font-weight:600;color:#1B3A5C;">📍 <?php echo esc_html($loc); ?></span>
            <a href="<?php echo get_permalink($p); ?>" style="font-size:13px;color:#555;text-decoration:none;margin-left:8px;"><?php echo esc_html($p->post_title); ?></a>
            <span style="float:right;font-size:11px;color:#999;"><?php echo get_the_date('Y-m-d', $p); ?></span>
        </div>
    <?php endforeach; ?>
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
