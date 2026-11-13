<?php
/* 把 `case 'help'` 里的指令清单**逐字**搬成 crrg_help_lines()，两处共用（聊天 + 窗口）
 * 用法：php tools/extract-help.php [--apply]
 */
$apply = in_array('--apply', $argv ?? [], true);
$T = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child/';
$file = $T . 'includes/game/core/drill-part-exec.php';
$src = file_get_contents($file);
$i = strpos($src, "        case 'help':\n            \$out = [");
if ($i === false) { fwrite(STDERR, "✗ 找不到 case 'help' 的数组起点\n"); exit(1); }
$open = strpos($src, '[', $i);
/* 括号配平（引号感知 ✓）定位数组结尾 */
$depth = 0; $inStr = false; $q = ''; $end = $open;
for ($k = $open; $k < strlen($src); $k++) {
    $c = $src[$k];
    if ($inStr) { if ($c === '\\') { $k++; continue; } if ($c === $q) $inStr = false; continue; }
    if ($c === "'" || $c === '"') { $inStr = true; $q = $c; continue; }
    if ($c === '[') $depth++;
    elseif ($c === ']') { $depth--; if ($depth === 0) { $end = $k; break; } }
}
$literal = substr($src, $open, $end - $open + 1);
printf("取到指令清单：%d 字节（%d 行）\n", strlen($literal), substr_count($literal, "\n"));
if (!$apply) { echo "（试跑；加 --apply 才写）\n"; echo substr($literal, 0, 200), "…\n"; exit(0); }

$bak = '/root/backups/quarantine-20260929/drill-part-exec.pre-help-' . date('His') . '.php';
copy($file, $bak);
/* ① 写共用函数 */
$fn = "<?php\n/**\n * 指令清单（**唯一来源**）：聊天里的「帮助」与输入框旁的「帮助」窗口共用（2026-10-01）\n"
    . " * 说明：改动这里，两处同时生效；验证器会**从本函数抠动词去真跑**，写错一个就会红 ✓\n */\n"
    . "if (!defined('ABSPATH')) exit;\n\n"
    . "function crrg_help_lines() {\n    return " . $literal . ";\n}\n";
file_put_contents($T . 'includes/game/ui/help.php', $fn);
/* ② 原地改成调用 */
$new = substr($src, 0, $i)
     . "        case 'help':\n            \$out = function_exists('crrg_help_lines') ? crrg_help_lines() : ['（帮助未加载）'];\n            break;\n"
     . substr($src, strpos($src, "            break;", $end) + strlen("            break;\n"));
file_put_contents($file, $new);

exec('php -l ' . escapeshellarg($file) . ' 2>&1', $o1, $rc1);
exec('php -l ' . escapeshellarg($T . 'includes/game/ui/help.php') . ' 2>&1', $o2, $rc2);
if ($rc1 !== 0 || $rc2 !== 0) {
    copy($bak, $file); @unlink($T . 'includes/game/ui/help.php');
    printf("✗ 语法错，已回滚：\n%s\n%s\n", implode("\n", $o1), implode("\n", $o2));
    exit(1);
}
printf("✓ 已抽出 ui/help.php（备份 %s）\n", basename($bak));
