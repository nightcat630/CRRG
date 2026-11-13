<?php
/* 对话 v2 迁移器：把"纯话题"的旧 NPC 机械转成 v2 节点树（2026-09-30）
 *   · 只处理 topics 里**没有** need/closed/skill/dyn/fail/rep 的 NPC（有闭包的必须手改 ✗）
 *   · 文本用 var_export **原样**写入 → 零手抄 ✓
 *   · 结构：entry 节点（列话题为选项）→ 每个话题一个节点（播文本 + 回 entry）→ 'end' 收场
 *   · 保留：once（一次性）／q（关键词，玩家仍可「问 NPC 关键词」✓）
 * 用法：php tools/dlg-migrate.php [--apply]
 */
$apply = in_array('--apply', $argv ?? [], true);
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

do_action('init');
$T = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child/';

$all = function_exists('crrg_dlg_all') ? (array) crrg_dlg_all() : [];
$trees = [];
foreach ($all as $id => $d) {
    $topics = (array) ($d['topics'] ?? []);
    if (!$topics) continue;
    $complex = false;
    foreach ($topics as $t) foreach (['need','closed','skill','dyn','fail','rep'] as $k) if (isset($t[$k])) $complex = true;
    if ($complex) continue;                                     // 有闭包 → 跳过（手改 ✓）
    $nodes = [];
    $choices = [];
    foreach ($topics as $i => $t) {
        $nid = 't' . ($i + 1);
        $nd = ['text' => array_values((array) ($t['text'] ?? []))];
        if (!empty($t['once'])) $nd['once'] = true;
        $nd['choices'] = [['label' => '（先问别的）', 'goto' => '__entry']];
        $nodes[$nid] = $nd;
        $c = ['label' => (string) ($t['label'] ?? $t['id'] ?? $nid), 'goto' => $nid];
        if (!empty($t['q'])) $c['kw'] = array_values((array) $t['q']);
        $choices[] = $c;
    }
    $choices[] = ['label' => '（不问了）', 'goto' => 'end'];
    $nodes['entry'] = [
        'text'    => ['（' . (string) ($d['name'] ?? $id) . ' 看着你，等你开口。）'],
        'choices' => $choices,
    ];
    $trees[$id] = ['name' => (string) ($d['name'] ?? $id), 'entry' => 'entry', 'nodes' => $nodes];
}
printf("可机械迁移的 NPC：%d 个（%s）\n", count($trees), implode('、', array_map(fn($t) => $t['name'], $trees)));
if (!$apply) { echo "（试跑；加 --apply 才写）\n"; exit(0); }

$out = "<?php\n/**\n * 对话 v2 · 由旧「纯话题」机械迁移的树（2026-09-30，tools/dlg-migrate.php 生成）\n"
     . " * 说明：文本 var_export 原样保留 ✓；结构＝entry 列话题 → 话题节点（可一次性）→ 回 entry／收场 ✓\n"
     . " *      含 need/closed/skill/dyn 的 NPC **不在**本文件（需手改 ✓）\n */\nif (!defined('ABSPATH')) exit;\n\n"
     . "add_action('init', function () {\n    if (!function_exists('crrg_dlg2_register')) return;\n";
foreach ($trees as $id => $tree) {
    $out .= "\n    /* " . $tree['name'] . " */\n";
    $out .= "    \$t = " . var_export(['name' => $tree['name'], 'entry' => $tree['entry'], 'nodes' => $tree['nodes']], true) . ";\n";
    $out .= "    /* 把 '__entry' 换回 'entry'（生成时占位） */\n";
    $out .= "    foreach (\$t['nodes'] as &\$nd) foreach (\$nd['choices'] as &\$ch) if ((\$ch['goto'] ?? '') === '__entry') \$ch['goto'] = 'entry';\n";
    $out .= "    unset(\$nd, \$ch);\n";
    $out .= "    crrg_dlg2_register('" . $id . "', \$t);\n";
}
$out .= "}, 24);\n";
$path = $T . 'includes/game/content/dlg/town-v2-auto.php';
file_put_contents($path, $out);
exec('php -l ' . escapeshellarg($path) . ' 2>&1', $o, $rc);
printf("写出 %s（%d 行）｜语法：%s\n", basename($path), count(file($path)), implode(' ', $o));
if ($rc !== 0) exit(1);
