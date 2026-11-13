<?php
/* 精确扫描：函数体内调用了「局部闭包名」（$set/$has/$check/$add/$fav…）却没有在该函数内定义它
 *   —— 2026-09-29 实战：合并后残留 $set() → PHP 把未定义变量当 null 调用 → 玩家一问 NPC 就崩 ✗
 * 用法：php tools/scan-stale-closures.php [--fix-hint]
 */
$T = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child/';
$files = array_merge(
    glob($T . 'includes/*.php') ?: [],
    glob($T . 'includes/game/*.php') ?: [],
    glob($T . 'includes/game/*/*.php') ?: [],
    glob($T . 'includes/game/*/*/*.php') ?: []
);
$locals = ['set', 'has', 'check', 'add', 'fav', 'roll', 'out_fn', 'find'];
$hits = 0;
foreach ($files as $f) {
    $lines = file($f);
    $n = count($lines);
    /* 切函数 */
    for ($i = 0; $i < $n; $i++) {
        if (!preg_match('/^function\s+(&?)([a-zA-Z0-9_]+)\s*\(/', $lines[$i], $mm)) continue;
        $end = $n - 1;
        for ($j = $i + 1; $j < $n; $j++) if (preg_match('/^function\s+/', $lines[$j])) { $end = $j - 1; break; }
        $body = implode('', array_slice($lines, $i, $end - $i + 1));
        foreach ($locals as $v) {
            $defPat = '/\$' . $v . '\s*=\s*(function|fn|static function)\b/';
            $defined = (bool) preg_match($defPat, $body);
            /* 调用：$set( 且不是定义行 */
            if (!preg_match('/\$' . $v . '\s*\(/', $body)) continue;
            if ($defined) continue;
            /* 排除参数就叫这个名字的（function f($set)） */
            if (preg_match('/function\s+&?[a-zA-Z0-9_]+\s*\([^)]*\$' . $v . '\b/', $body)) continue;
            /* 排除该名字是"模组内全局函数"的情况：xxx_set( */
            if (preg_match('/[a-zA-Z0-9_]' . $v . '\s*\(/i', $body) && !preg_match('/\$' . $v . '\s*\(/', $body)) continue;
            $hits++;
            printf("✗ %-38s %-28s 调用 \$%s() 但函数内未定义 → 运行到就崩\n", basename($f), $mm[2], $v);
            break;
        }
    }
}
printf("\n总计 %d 处可疑\n", $hits);
