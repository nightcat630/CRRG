<?php
/* 搬一个**整函数**到目标文件（带守卫＋原子化 ✓ 2026-09-30）
 *   守卫：源文件里该函数必须**恰好 1 次**；搬完源里 0 次、目标里 1 次，否则**不落盘** ✓
 * 用法：php tools/move-func.php <源文件> <函数名> <目标文件> [--apply]
 */
$src0 = $argv[1] ?? ''; $fn = $argv[2] ?? ''; $dst0 = $argv[3] ?? '';
$apply = in_array('--apply', $argv ?? [], true);
$T = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child/';
$srcF = $T . $src0; $dstF = $T . $dst0;
if (!file_exists($srcF)) { fwrite(STDERR, "✗ 源文件不存在\n"); exit(1); }
$src = file_get_contents($srcF);
$n = preg_match_all('/^function\s+&?' . preg_quote($fn, '/') . '\s*\(/m', $src);
printf("源文件 %s 里 %s 出现 %d 次\n", basename($src0), $fn, $n);
if ($n !== 1) { fwrite(STDERR, "✗ 守卫未过：应恰好 1 次\n"); exit(1); }
/* 取块：从前面的注释行起，到下一个顶层 function 之前 */
$i = strpos($src, 'function ' . $fn . '(');
if ($i === false) { $i = strpos($src, 'function &' . $fn . '('); }
$start = $i;
while (true) {
    $ls = strrpos(substr($src, 0, $start - 1), "\n");
    if ($ls === false) break;
    $prev = trim(substr($src, $ls + 1, $start - $ls - 1));
    if ($prev !== '' && (strpos($prev, '/*') === 0 || strpos($prev, '*') === 0 || strpos($prev, '//') === 0)) $start = $ls + 1;
    else break;
}
$e = strpos($src, "\nfunction ", $i + 10);
$end = ($e === false) ? strlen($src) : $e + 1;
$block = substr($src, $start, $end - $start);
printf("块：%d 行\n", substr_count($block, "\n"));
if (!$apply) { echo "（试跑；加 --apply 才写）\n"; exit(0); }
$bak = '/root/backups/quarantine-20260929/' . basename($src0) . '.pre-move-' . $fn . '-' . date('His') . '.php';
copy($srcF, $bak);
$head = file_exists($dstF) ? '' : "<?php\n/**\n * 自 " . $src0 . " 搬出的函数（2026-09-30）\n */\nif (!defined('ABSPATH')) exit;\n\n";
$newSrc = substr($src, 0, $start) . substr($src, $end);
$newSrc = preg_replace("/\n{3,}/", "\n\n", $newSrc);
$newDst = (file_exists($dstF) ? file_get_contents($dstF) : $head) . $block;
$t1 = $srcF . '.tmp'; $t2 = $dstF . '.tmp';
file_put_contents($t1, $newSrc); file_put_contents($t2, $newDst);
exec('php -l ' . escapeshellarg($t1) . ' 2>&1', $o1, $rc1);
exec('php -l ' . escapeshellarg($t2) . ' 2>&1', $o2, $rc2);
/* 守卫二：搬完源里 0 次、目标里 1 次 */
$c1 = preg_match_all('/^function\s+&?' . preg_quote($fn, '/') . '\s*\(/m', $newSrc);
$c2 = preg_match_all('/^function\s+&?' . preg_quote($fn, '/') . '\s*\(/m', $newDst);
if ($rc1 !== 0 || $rc2 !== 0 || $c1 !== 0 || $c2 !== 1) {
    @unlink($t1); @unlink($t2);
    printf("✗ 校验未过（语法 %d/%d ｜ 源 %d 目标 %d）—— 未写任何文件\n%s\n%s\n", $rc1, $rc2, $c1, $c2, implode("\n", $o1), implode("\n", $o2));
    exit(1);
}
file_put_contents($dstF, $newDst);
file_put_contents($srcF, $newSrc);
@unlink($t1); @unlink($t2);
printf("✓ 已搬 %s → %s（备份 %s）\n", $fn, $dst0, basename($bak));
