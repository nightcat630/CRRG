<?php
/* 骨架聚类盘点：把游戏模块里所有函数的"语句骨架"归一化后分组，找出同构可并的组
 * 用法：php tools/scan-skeletons.php [模式]     模式：seal（默认，只看《封条》线）｜ all
 */
$mode = $argv[1] ?? 'seal';
$dir  = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child/includes/';
$files = glob($dir . 'drill*.php');
$rows = [];
foreach ($files as $f) {
    $base = basename($f);
    if ($mode === 'seal' && strpos($base, 'seal') === false) continue;
    $lines = file($f);
    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        if (!preg_match('/^function\s+([a-zA-Z0-9_]+)\s*\(/', $lines[$i], $m)) continue;
        $name = $m[1];
        $start = $i;
        $end = $n - 1;
        for ($j = $i + 1; $j < $n; $j++) if (preg_match('/^function\s+/', $lines[$j])) { $end = $j - 1; break; }
        /* 归一化骨架：去掉字符串/注释，把"已知助手调用"映射成类别词，保留控制流 */
        $body = implode('', array_slice($lines, $start, $end - $start + 1));
        $body = preg_replace('#/\*.*?\*/#s', '', $body);
        $body = preg_replace('#//[^\n]*#', '', $body);
        $body = preg_replace("#'(?:[^'\\\\]|\\\\.)*'#", "S", $body);
        $body = preg_replace('#"(?:[^"\\\\]|\\\\.)*"#', "S", $body);
        $map = [
            'crrg_seal_has' => 'HAS', 'crrg_seal_set' => 'SET', 'crrg_seal_roll' => 'ROLL',
            'crrg_seal_done_msg' => 'DONE', 'crrg_rep_add' => 'REP', 'crrg_seal_use' => 'USE',
            'crrg_seal_choice_engine' => 'CHOICE', 'crrg_seal_survey_engine' => 'SURVEYENG',
            'crrg_seal_ask_engine' => 'ASKENG', 'crrg_seal_paths_full' => 'PATHS',
            'crrg_seal_try' => 'TRY', 'crrg_coc_check' => 'CHECK', 'crrg__' => 'X',
        ];
        foreach ($map as $k => $v) $body = str_replace($k, $v, $body);
        $skeleton = preg_replace('/\s+/', ' ', $body);
        $skeleton = preg_replace('/\b[A-Za-z_][A-Za-z0-9_]*\b/', 'v', $skeleton);   // 变量名归一
        $skeleton = trim($skeleton);
        $rows[] = ['file' => $base, 'name' => $name, 'lines' => $end - $start + 1, 'sk' => $skeleton];
    }
}
/* 分组：同骨架 ≥2 个 */
$g = [];
foreach ($rows as $r) $g[md5($r['sk'])][] = $r;
usort($g, fn($a, $b) => count($b) <=> count($a));
printf("扫描 %d 个函数，骨架分组 %d 组\n\n", count($rows), count($g));
printf("%-4s %-8s %s\n", "组", "成员", "成员与行数");
foreach ($g as $k => $members) {
    if (count($members) < 2) continue;
    $tot = array_sum(array_column($members, 'lines'));
    printf("#%-3d %-8s 合计 %4d 行\n", $k, count($members) . " 个", $tot);
    foreach ($members as $m) printf("      · %-34s %-4d 行  (%s)\n", $m['name'], $m['lines'], $m['file']);
}
echo "\n—— 单例（骨架唯一，暂无可并）——\n";
$solo = array_filter($g, fn($m) => count($m) === 1);
printf("共 %d 个\n", count($solo));
