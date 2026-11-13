<?php
/**
 * PHP 字符串卫生门禁（tools/verify-phpstr.php，2026-10-14）
 * ------------------------------------------------------------
 * 背景：PHP 变量名**允许高位字节**，所以字符串里 `"$lv（"` 会被解析成变量名 `$lv（…` ✗
 *   —— 今天为这个栽了 **三次**（`$n」`、`$bad」`、`$lv（`）。做成门禁，一次根治 ✓
 * 规则：双引号/单引号字符串里，`$var` 后面**紧跟非 ASCII 字符** → 必须写成 `{$var}` ✓
 * 做法：先把注释整段去掉，再扫 ✓（注释里写了不算错 ✓）
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
$err = []; $warn = []; $ok = [];
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($THEME . '/includes', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    if (strpos($f->getPathname(), '.bak') !== false || strpos($f->getPathname(), '.removed') !== false) continue;
    $files[] = $f->getPathname();
}
$files[] = $THEME . '/functions.php';
$files[] = $THEME . '/tools/verify-task.php';

foreach ($files as $file) {
    $src = file_get_contents($file);
    /* 去注释：块注释 → 空白；行注释整行去掉（保留行数以便报行号 ✓） */
    $lines = explode("\n", $src);
    $noComment = [];
    $inBlock = false;
    foreach ($lines as $l) {
        $l2 = $l;
        if ($inBlock) {
            $pos = strpos($l2, '*/');
            if ($pos === false) { $noComment[] = ''; continue; }
            $l2 = substr($l2, $pos + 2); $inBlock = false;
        }
        if (($p = strpos($l2, '/*')) !== false) {
            if (strpos($l2, '*/', $p) === false) $inBlock = true;
            $l2 = substr($l2, 0, $p);
        }
        $l2 = preg_replace('~//.*$~u', '', $l2);
        $l2 = preg_replace('/#.*$/', '', $l2);
        $noComment[] = $l2;
    }
    foreach ($noComment as $i => $line) {
        if ($line === '') continue;
        foreach (preg_match_all('/\$[A-Za-z_][A-Za-z0-9_]*[^\x00-\x7F]/u', $line, $m, PREG_OFFSET_CAPTURE) ? $m[0] : [] as $hit) {
            $txt = $hit[0]; $off = $hit[1];
            if ($off > 0 && substr($line, $off - 1, 1) === '{') continue;      // {$var} 是对的 ✓
            $err[] = sprintf('%s:%d  「%s」 → 变量后面紧跟非 ASCII，会被吃进变量名 ✗ 请写 {$var}',
                str_replace($THEME . '/', '', $file), $i + 1, mb_substr($txt, 0, 12));
        }
    }
}
if (!$err) $ok[] = sprintf('扫了 %d 个文件：字符串里没有"变量被中文吃掉"的写法 ✓', count($files));
echo "=== PHP 字符串卫生门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach (array_slice($err, 0, 12) as $l) echo "  ✗ $l\n";
if (count($err) > 12) echo '  ✗ …共 ' . count($err) . " 处\n";
printf("\n小结：错误 %d ｜ 提醒 %d\n", count($err), count($warn));
exit($err ? 1 : 0);
