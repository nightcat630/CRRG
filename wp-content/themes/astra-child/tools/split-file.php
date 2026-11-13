<?php
/* 通用"按函数搬迁"工具：把源文件里的若干**整函数**原样搬到目标文件，并从源文件删除
 *   · 内容一字不动（只换位置）；目标文件不存在则新建（含头部）
 *   · 搬迁后：php -l 两个文件；失败自动回滚（源文件恢复、目标文件删除/还原）
 * 用法：php tools/split-file.php <任务名> [--apply]
 * 任务在下方 $JOBS 里定义；函数名支持通配（如 crrg_note_*）
 */
$job   = $argv[1] ?? '';
$apply = in_array('--apply', $argv ?? [], true);
$T = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child/';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';


$JOBS = [
    'hub-notes' => [
        'src' => 'includes/drill-hub.php',
        'out' => 'includes/game/systems/notes.php',
        'fns' => ['crrg_notes_reg', 'crrg_note_register', 'crrg_notes_of', 'crrg_note_lines'],
        'title' => '笔记系统（自 drill-hub.php 搬出）',
    ],
    'hub-looks' => [
        'src' => 'includes/drill-hub.php',
        'out' => 'includes/game/systems/looks.php',
        'fns' => ['crrg_drill_look_hooks', 'crrg_drill_add_look_hook', 'crrg_drill_run_look_hooks'],
        'title' => '观察钩子（自 drill-hub.php 搬出）',
    ],
];
if (!isset($JOBS[$job])) { fwrite(STDERR, "未知任务「$job」。可选：" . implode('、', array_keys($JOBS)) . "\n"); exit(1); }
$J = $JOBS[$job];
$srcFile = $T . $J['src'];
$outPath = $T . $J['out'];
$src = file_get_contents($srcFile);

/* ① 定位每个函数的完整块 */
$blocks = [];
foreach ($J['fns'] as $fn) {
    $L = strpos($src, "function $fn(");
    if ($L === false) { fwrite(STDERR, "✗ 找不到 $fn\n"); exit(1); }
    /* 向前吃掉紧邻的注释/空行 */
    $ls = strrpos(substr($src, 0, $L), "\n");
    while ($ls !== false) {
        $prevStart = strrpos(substr($src, 0, $ls), "\n");
        $prevLine = ($prevStart === false) ? substr($src, 0, $ls) : substr($src, $prevStart + 1, $ls - $prevStart - 1);
        if (preg_match('/^\s*(\/\*|\*|\/\/)/', $prevLine)) { $ls = $prevStart; } else break;
    }
    $start = ($ls === false) ? $L : $ls + 1;
    $E = strpos($src, "\nfunction ", $L + 10);
    if ($E === false) $E = strlen($src);
    $blocks[$fn] = substr($src, $start, $E - $start);
    printf("  %-34s %4d 行\n", $fn, substr_count($blocks[$fn], "\n"));
}
if (!$apply) { echo "（试跑；加 --apply 才写）\n"; exit(0); }

/* ② 备份 → 目标文件（追加或新建）→ 源文件删除这些块 */
$bak = '/root/backups/quarantine-20260929/' . basename($J['src']) . '.pre-' . $job . '-' . date('His') . '.php';
copy($srcFile, $bak);
$chunk = "";
foreach ($J['fns'] as $fn) $chunk .= $blocks[$fn];
$head = file_exists($outPath) ? "" : ("<?php\n/**\n * " . $J['title'] . "\n */\nif (!defined('ABSPATH')) exit;\n\n");
$newOut = (file_exists($outPath) ? file_get_contents($outPath) : $head) . $chunk;
/* 删除：**按函数区间**逐个删（2026-09-29 事故：按"含前置注释的整块文本"删，
   而源文件那处的注释文本不同 → str_replace 未命中 → 函数**重复声明** → 站点 500 ✗） */
$newSrc = $src;
foreach ($J['fns'] as $fn) {
    $L2 = strpos($newSrc, "function $fn(");
    if ($L2 === false) continue;
    $st = $L2;
    /* 向前吃掉紧邻的注释行（按行判断，不依赖文本一致） */
    while (true) {
        $ls = strrpos(substr($newSrc, 0, $st - 1), "\n");
        if ($ls === false) break;
        $prev = trim(substr($newSrc, $ls + 1, $st - $ls - 1));
        if ($prev !== '' && (strpos($prev, '/*') === 0 || strpos($prev, '*') === 0 || strpos($prev, '//') === 0)) $st = $ls + 1;
        else break;
    }
    $E2 = strpos($newSrc, "\nfunction ", $L2 + 10);
    if ($E2 === false) $E2 = strlen($newSrc);
    $newSrc = substr($newSrc, 0, $st) . substr($newSrc, $E2);
}
$newSrc = preg_replace("/\n{3,}/", "\n\n", $newSrc);

/* ②b 守卫：每个函数在源里应已消失、在目标里恰好一次（否则中止，不落盘） */
foreach ($J['fns'] as $fn) {
    $inSrc = substr_count($newSrc, "function $fn(");
    $inOut = substr_count($newOut, "function $fn(");
    if ($inSrc !== 0 || $inOut !== 1) {
        fwrite(STDERR, sprintf("✗ 守卫未过：%s 在源里 %d 次、目标里 %d 次（应为 0／1）——未写任何文件\n", $fn, $inSrc, $inOut));
        exit(1);
    }
}
echo "守卫通过：每个函数在源里已移除、在目标里恰好一次 ✓\n";

/* ③ 原子化：先写临时文件校验语法，全过才落盘 */
$tS = $srcFile . '.tmp'; $tO = $outPath . '.tmp';
file_put_contents($tS, $newSrc); file_put_contents($tO, $newOut);
exec('php -l ' . escapeshellarg($tS) . ' 2>&1', $o1, $rc1);
exec('php -l ' . escapeshellarg($tO) . ' 2>&1', $o2, $rc2);
if ($rc1 !== 0 || $rc2 !== 0) {
    @unlink($tS); @unlink($tO);
    printf("✗ 校验未过，**未写任何文件**：\n%s\n%s\n", implode("\n", $o1), implode("\n", $o2));
    exit(1);
}
file_put_contents($outPath, $newOut);
file_put_contents($srcFile, $newSrc);
@unlink($tS); @unlink($tO);
printf("✓ 已把 %d 个函数搬到 %s（源文件 %d → %d 行）\n", count($J['fns']), $J['out'],
    substr_count($src, "\n") + 1, substr_count($newSrc, "\n") + 1);
