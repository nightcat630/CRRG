<?php
// 每日人设更新 — 只追加"入职以来经历"，不动其他板块
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

$users = [
    958 => "赵明远", 959 => "陈思言", 960 => "林正则",
    961 => "王守一", 962 => "周知行", 966 => "孙亦然",
    967 => "江晓", 968 => "何若风", 969 => "沈微", 970 => "白露",
];

$dir = '/root/重生抵御小组网站/员工人设/';
$now = date('Y-m-d');

foreach ($users as $uid => $name) {
    // 找对应人设文件
    $file = null;
    foreach (glob($dir . '*.md') as $f) {
        if (strpos(file_get_contents($f), $name) !== false) { $file = $f; break; }
    }
    if (!$file) continue;

    // 读取今日论坛活动
    $today_posts = get_posts([
        'post_type' => ['topic', 'reply'],
        'post_author' => $uid,
        'posts_per_page' => 20,
        'date_query' => ['after' => '24 hours ago'],
        'orderby' => 'ID', 'order' => 'ASC',
    ]);

    if (empty($today_posts)) continue;

    // 提取关键事件
    $events = [];
    foreach ($today_posts as $p) {
        $is_topic = $p->post_type === 'topic';
        $prefix = $is_topic ? "发布话题「{$p->post_title}」" : "在讨论中回复";
        $events[] = "- **{$now}** {$prefix}";
    }

    // 追加到人设文件
    $content = file_get_contents($file);
    $entry = "\n" . implode("\n", $events);
    
    // 检查今天是否已经更新过
    if (strpos($content, "- **{$now}**") !== false) continue;

    file_put_contents($file, $content . $entry);
    echo "{$name} ✅ ".count($events)."条\n";
}
echo "完成\n";
