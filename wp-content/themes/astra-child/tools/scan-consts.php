<?php
/* 常量体检：找出"被使用但从未定义"的 CRRG_* 常量（运行到就 fatal）
 * ------------------------------------------------------------------
 * 2026-09-30 修正两处（原版太弱，会误报 ✗）：
 *   ① 范围：只扫主题 ✗ → 扫**全站**（主题 + mu-plugins + wp-config + 插件）✓
 *   ② 定义形式：只认 `define('X'` ✗ → 同时认 `const X =` 与 `defined('X')` ✓
 * 用法：php tools/scan-consts.php
 */
$root = '/www/wwwroot/crrg_moebius_com_114/';
$files = [];
foreach ([$root . 'wp-config.php', $root . 'wp-content/themes', $root . 'wp-content/mu-plugins', $root . 'wp-content/plugins'] as $p) {
    if (is_file($p)) { $files[] = $p; continue; }
    if (!is_dir($p)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $path = $f->getPathname();
        if (substr($path, -4) !== '.php') continue;
        if (strpos($path, '/node_modules/') !== false || strpos($path, '/.git/') !== false) continue;
        $files[] = $path;
    }
}
$used = []; $defined = [];
foreach ($files as $f) {
    $s = (string) @file_get_contents($f);
    if ($s === '') continue;
    $s = preg_replace('#/\*.*?\*/#s', '', $s);
    $s = preg_replace('#//[^\n]*#', '', $s);
    foreach (array_unique((array) (preg_match_all('/\b(CRRG_[A-Z0-9_]{2,})\b/', $s, $m) ? $m[1] : [])) as $c) {
        $used[$c][basename($f)] = 1;
    }
    foreach ((array) (preg_match_all("/define\\s*\\(\\s*['\"](CRRG_[A-Z0-9_]{2,})['\"]/", $s, $m2) ? $m2[1] : []) as $c) $defined[$c] = basename($f);
    foreach ((array) (preg_match_all("/\\bconst\\s+(CRRG_[A-Z0-9_]{2,})\\s*=/", $s, $m3) ? $m3[1] : []) as $c) $defined[$c] = basename($f);
    foreach ((array) (preg_match_all("/defined\\s*\\(\\s*['\"](CRRG_[A-Z0-9_]{2,})['\"]/", $s, $m4) ? $m4[1] : []) as $c) $defined[$c] = basename($f);
}
$miss = array_diff(array_keys($used), array_keys($defined));
printf("扫描 %d 个 php 文件 ｜ 用到 CRRG_* 常量 %d 个 ｜ 已定义 %d 个\n", count($files), count($used), count($defined));
if (!$miss) { echo "未定义的常量：无 ✓\n"; exit(0); }
echo "✗ 未定义的常量（运行到会 fatal）：\n";
foreach ($miss as $c) printf("  %-24s 用到 %d 处：%s\n", $c, count($used[$c]), implode('、', array_slice(array_keys($used[$c]), 0, 4)));
exit(1);
