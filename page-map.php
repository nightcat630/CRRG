<?php
/* Template Name: 事件地图 */
get_header();

$location_posts = get_posts([
    'post_type' => 'post', 'post_status' => 'publish',
    'posts_per_page' => 50,
    'meta_query' => [
        ['key' => 'crrg_report_type_name', 'value' => '事件'],
        crrg_get_access_meta_query(),
    ],
]);

// 地点坐标映射
$coord_map = [
    '广西横州市云表镇' => [22.68, 109.27],
    '新疆克拉玛依' => [45.58, 84.89],
    '中原地区（传说）' => [34.75, 113.66],
    '山西太行山区' => [36.5, 113.2],
    '上海' => [31.23, 121.47],
    '沈阳' => [41.8, 123.43],
];

$observe_time = $_GET['t'] ?? ''; $now = $observe_time ?: current_time('Y-m-d H:i:s');

$markers = [];
$visible_ids = [];
foreach ($location_posts as $p) {
    $loc = get_post_meta($p->ID, 'crrg_location', true);
    $threat = get_post_meta($p->ID, 'crrg_threat_level', true);
    $lat = get_post_meta($p->ID, 'crrg_lat', true);
    $lng = get_post_meta($p->ID, 'crrg_lng', true);
    // 时间范围过滤
    $start = get_post_meta($p->ID, 'crrg_event_start', true);
    $end = get_post_meta($p->ID, 'crrg_event_end', true);
    $visible = true;
    if ($start || $end) {
        if ($start && $end) $visible = ($now >= $start && $now <= $end);
        elseif ($start) $visible = ($now >= $start);
        elseif ($end) $visible = ($now <= $end);
    }
    if ($lat && $lng && $visible) {
        $visible_ids[] = $p->ID;
        $markers[] = [
            'lat' => (float)$lat, 'lng' => (float)$lng,
            'title' => $p->post_title,
            'loc' => $loc,
            'threat' => $threat,
            'url' => get_permalink($p),
            'date' => get_the_date('Y-m-d', $p),
        ];
    }
}
?>
<div class="gov-main">
<div class="gov-content">
    <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;">🗺️ 事件态势图</h1>
    <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">
        中央重生抵御小组 · 地理信息
        <form method="get" style="display:inline;float:right;">
            <span style="font-size:12px;color:#666;">观测时间：</span>
            <input type="datetime-local" name="t" value="<?php echo $observe_time ? esc_attr(date('Y-m-d\TH:i', strtotime($observe_time))) : esc_attr(date('Y-m-d\TH:i')); ?>" style="padding:4px 8px;border:1px solid #d5d5d5;border-radius:3px;font-size:12px;">
            <button type="submit" style="padding:4px 12px;background:#1B3A5C;color:#fff;border:none;border-radius:3px;font-size:12px;cursor:pointer;">观测</button>
            <?php if ($observe_time): ?><a href="?" style="font-size:11px;color:#C41230;text-decoration:none;margin-left:4px;">重置</a><?php endif; ?>
        </form>
    </div>

    <div id="china-map" style="width:100%;height:500px;border:1px solid #e0e0e0;border-radius:4px;background:#f0f2f5;"></div>
    <link rel="stylesheet" href="/wp-content/themes/astra-child/assets/leaflet/leaflet.css" />
    <script src="/wp-content/themes/astra-child/assets/leaflet/leaflet.js"></script>
    <script>
    var map = L.map('china-map').setView([35, 105], 4);
    L.tileLayer('/tile-proxy.php?z={z}&x={x}&y={y}', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 10,
    }).addTo(map);

    var threatColors = {ren:'#16a34a',gui:'#8B5CF6',mo:'#C41230',shen:'#F0A500'};
    var markers = <?php echo json_encode($markers, JSON_UNESCAPED_UNICODE); ?>;
    markers.forEach(function(m){
        var color = threatColors[m.threat] || '#999';
        var icon = L.divIcon({
            className: 'threat-marker',
            html: '<div style="width:14px;height:14px;background:'+color+';border:2px solid #fff;border-radius:50%;box-shadow:0 0 6px '+color+';"></div>',
            iconSize: [18,18],
        });
        L.marker([m.lat, m.lng], {icon:icon}).addTo(map)
            .bindPopup('<strong>'+m.title+'</strong><br>📍 '+m.loc+'<br>'+m.date+'<br><a href="'+m.url+'">查看报告 →</a>');
    });
    </script>

    <?php if ($location_posts): ?>
    <div style="margin-top:20px;">
        <h3 style="font-size:15px;color:#1B3A5C;margin-bottom:10px;">📋 地点清单</h3>
        <?php foreach ($location_posts as $p):
            if (!in_array($p->ID, $visible_ids)) continue;
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
    </div>
    <?php endif; ?>
</div>
<div class="gov-sidebar">
    <div class="widget">
        <div class="widget-title">📊 威胁分布</div>
        <?php
        $counts = ['ren'=>0,'gui'=>0,'mo'=>0,'shen'=>0,'none'=>0];
        foreach ($location_posts as $ap) {
            if (!in_array($ap->ID, $visible_ids)) continue;
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
