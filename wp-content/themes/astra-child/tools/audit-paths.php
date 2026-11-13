<?php
/* 过法合理性审计（2026-10-01）：把每个节点的过法按"代价种类"列出来，
 * 标出**明显不合理**的（文书/调查类节点却带"硬来 −1 生命"、或到处都要花钱 ✗）
 * 用法：php tools/audit-paths.php
 */
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

do_action('init');

/* 文书/室内类关键词（这类节点不该"硬来掉血"） */
$desk = ['账', '定档', '补件', '封闭', '校对', '手续', '通行', '结案', '台账', '盘点', '比对', '档案', '报告', '登记', '收料人', '生面孔', '病历', '体检', '随访'];
$danger = ['护料', '收货点', '朱砂土', '痕迹', '车辙', '山路人影', '老陵', '土', '值守', '巡检', '抢料', '守夜', '开工', '贴封', '修复', '替厨', '工地'];
$rows = [];
foreach (crrg_seal_paths_full() as $nd => $ps) {
    $hp = 0; $pay = 0; $fight = 0; $check = 0; $brute = 0;
    foreach ((array) $ps as $p) {
        if (!empty($p['hp'])) $hp++;
        if (!empty($p['cost'])) $pay++;
        if (($p['type'] ?? '') === 'fight') $fight++;
        if (($p['type'] ?? '') === 'brute') $brute++;
        if (($p['type'] ?? '') === 'check') $check++;
    }
    $isDesk = false; foreach ($desk as $k) if (mb_strpos((string) $nd, $k) !== false) { $isDesk = true; break; }
    $isDanger = false; foreach ($danger as $k) if (mb_strpos((string) $nd, $k) !== false) { $isDanger = true; break; }
    $verdict = '';
    if ($isDesk && $hp > 0) { $verdict = '✗ 文书类却"动手掉血"'; }                 // 2026-10-01：判"带掉血"而不是"有硬来" ✓
    elseif ($isDesk && $brute > 0) { $verdict = '✓ 文书类改成"留隐患"（不掉血），合理'; }
    elseif ($isDesk && $pay > 0) { $verdict = '△ 文书类还要花钱（可议）'; }
    elseif ($isDanger && $hp > 0) { $verdict = '✓ 危险类，掉血合理'; }
    $rows[] = [$nd, count((array) $ps), $brute, $hp, $pay, $fight, $check, $isDesk ? '文书' : ($isDanger ? '行动' : '—'), $verdict];
}
printf("%-12s %4s %5s %5s %5s %6s %6s %6s  %s\n", '节点', '过法', '硬来', '掉血', '花钱', '战斗', '检定', '种类', '判定');
foreach ($rows as $r) printf("%-12s %4d %5d %5d %5d %6d %6d %6s  %s\n", $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7], $r[8]);
$bad = array_filter($rows, fn($r) => strpos($r[8], '✗') === 0);
printf("\n合计 %d 个节点 ｜ 明显不合理 %d 个\n", count($rows), count($bad));
foreach ($bad as $r) printf("  ✗ %s（过法 %d 条，其中硬来 %d 条带掉血 %d）\n", $r[0], $r[1], $r[2], $r[3]);
