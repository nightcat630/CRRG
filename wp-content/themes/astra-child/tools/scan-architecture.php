<?php
/* 架构现状盘点：游戏文件的分类、每类"注册入口"、以及"加一样东西要动几个文件"
 * 用法：php tools/scan-architecture.php
 */
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

$T = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child/';
$files = glob($T . 'includes/drill*.php');
$page  = glob($T . 'page-drill.php');

/* ① 按文件名前缀粗分（现状） */
$cats = ['drill-part-' => [], 'drill-mission-' => [], 'drill-mod-' => [], 'drill-' => [], 'page-' => []];
$other = [];
foreach (array_merge($files, $page) as $f) {
    $b = basename($f);
    $hit = false;
    foreach ($cats as $pre => $_) { if (strpos($b, $pre) === 0) { $cats[$pre][] = $b; $hit = true; break; } }
    if (!$hit) $other[] = $b;
}
printf("=== 现状：游戏文件（%d 个） ===\n", count($files) + count($page));
foreach ($cats as $pre => $list) if ($list) printf("  %-16s %2d 个：%s\n", $pre . '*', count($list), implode('、', $list));
echo "\n=== 各类内容注册在哪些文件（加一样东西要动几处） ===\n";

/* ② 注册入口 → 出现在哪些文件 */
$entries = [
    '合约/任务'   => 'crrg_drill_register_mission|crrg_drill_register',
    '场景/房间'   => 'crrg_drill_scene_extend|crrg_drill_scene_add_exit|register_scene',
    '物品/图纸'   => 'crrg_drill_register_item|crrg_item_register|crrg_drill_items',
    '商店/货品'   => 'crrg_drill_register_shop|crrg_drill_town_shops',
    'NPC 对话'    => 'crrg_dlg_register|crrg_dlg_patch',
    '面板区块'    => 'crrg_drill_panel_block|crrg_drill_panels_register',
    '世界地图'    => 'crrg_worldmap_',
    '观察/外观'   => 'crrg_drill_look_hooks|crrg_art_',
];
foreach ($entries as $label => $pat) {
    $where = [];
    foreach (array_merge($files, $page) as $f) {
        if (preg_match('/' . $pat . '/', file_get_contents($f))) $where[] = basename($f);
    }
    printf("  %-12s → %2d 个文件：%s\n", $label, count($where), implode('、', array_slice($where, 0, 8)) . (count($where) > 8 ? ' …' : ''));
}

/* ③ 现状里数据表与逻辑混装的文件（数组字面量行数占比高） */
echo "\n=== 逻辑与数据混装的文件（数组字面量行数占比高） ===\n";
foreach (array_merge($files, $page) as $f) {
    $src = file_get_contents($f);
    $total = substr_count($src, "\n") + 1;
    if ($total < 80) continue;
    /* 粗略：统计 'xxx' => 出现的行数 */
    $dataLines = preg_match_all("/^\s*'[^']+'\s*=>/m", $src);
    $pct = round($dataLines / max(1, $total) * 100);
    if ($pct >= 25) printf("  %-34s %4d 行，其中数据行 %3d（%d%%）\n", basename($f), $total, $dataLines, $pct);
}
