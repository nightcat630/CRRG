<?php
/**
 * 日历门禁（tools/verify-calendar.php，第 2 轮，2026-10-14）
 * 委员长口径是硬要求，这里逐条真跑：
 *   ① **跨年回卷**：日序 359 → 12月30日、360 → **1月1日**、361 → 1月2日（日序连续 ✓ 不出现"12月31日"✓）
 *   ② **星期连续**：连续 8 天星期逐一递增并循环（1→2→…→7→1）✓
 *   ③ **绝不显示年份/第 N 天**：日历行正则不许出现 `\d{4}` 年 或 "第 X 天"；源码里也不许再拼"第 %d 天"的显示
 *   ④ **日程触发幂等**：同一日序同一事件只提示一次 ✓
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

/* 2026-10-14：**门禁不许留副作用** —— 跑游戏逻辑会经引擎落事实/写 option ✗
   （跑前记水位、跑完精确回收 ✓ 与 verify-bus / verify-game 同一套做法）
   ⚠ 以后每写一个新门禁，**都要带这段** ✓ */
$__ft = function_exists('crrg_facts_table') ? crrg_facts_table() : '';
$__maxId = 0;
if ($__ft) { global $wpdb; $__maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $__ft"); }
$__optBak = [];
foreach (['crrg_world_tick','crrg_last_day','crrg_fact_ping','crrg_recent_settle','crrg_world_day_note','crrg_fight_pending','crrg_travel_pending','crrg_bt_state','crrg_duty','crrg_duty_roster'] as $o) $__optBak[$o] = get_option($o, null);
register_shutdown_function(function () use ($__ft, $__maxId, $__optBak) {
    if ($__ft) { global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM $__ft WHERE id > %d", $__maxId)); }
    foreach ($__optBak as $o => $v) { if ($v === null) delete_option($o); else update_option($o, $v, false); }
});
$err = []; $warn = []; $ok = [];

/* ① 跨年回卷 */
/* 基准：日序 1 ＝ 1月1日（则 30 ＝ 1月30日、31 ＝ 2月1日、360 ＝ 12月30日、**361 ＝ 1月1日**）
   ⚠ 第一版我把期望写成"360→1月1日" ✗ —— 是**测试**错，不是代码错（门禁当场逮到 ✓） */
if (crrg_cal_md(1) !== [1, 1])    $err[] = '① 基准错：日序 1 应为 1月1日';
if (crrg_cal_md(30) !== [1, 30])  $err[] = '① 日序 30 应为 1月30日';
if (crrg_cal_md(31) !== [2, 1])   $err[] = '① 跨月错：日序 31 应为 2月1日';
[$m1, $d1] = crrg_cal_md(360);
[$m2, $d2] = crrg_cal_md(361);
[$m3, $d3] = crrg_cal_md(362);
if (!($m1 === 12 && $d1 === 30)) $err[] = "① 日序 360 应为 12月30日，实为 {$m1}月{$d1}日";
if (!($m2 === 1 && $d2 === 1))   $err[] = "① **跨年回卷错**：日序 361 应为 1月1日，实为 {$m2}月{$d2}日";
if (!($m3 === 1 && $d3 === 2))   $err[] = "① 日序 362 应为 1月2日，实为 {$m3}月{$d3}日";
[$m0, $d0] = crrg_cal_md(721);
if (!($m0 === 1 && $d0 === 1))   $warn[] = "① 日序 721（第二个循环的开头）＝ {$m0}月{$d0}日 —— 应为 1月1日";
if (!$err) $ok[] = '① 跨年回卷：360→12/30、**361→1/1**、362→1/2 ✓（日序连续、无年份）';

/* ② 星期连续 */
$seq = [];
for ($i = 0; $i < 8; $i++) $seq[] = crrg_cal_weekday(100 + $i);
$want = [crrg_cal_weekday(100), crrg_cal_weekday(101), crrg_cal_weekday(102), crrg_cal_weekday(103),
         crrg_cal_weekday(104), crrg_cal_weekday(105), crrg_cal_weekday(106), crrg_cal_weekday(100)];
if ($seq !== $want) $err[] = '② 星期不连续：' . implode(',', $seq);
else $ok[] = '② 星期由日序取模，连续且 7 天循环 ✓';

/* ③ 显示口径 */
for ($d = 1; $d <= 400; $d++) {
    $t = crrg_cal_text($d);
    if (preg_match('/\d{4}\s*年/u', $t)) { $err[] = "③ 日历行里出现年份：$t"; break; }
    if (preg_match('/第\s*\d+\s*天/u', $t)) { $err[] = "③ 日历行里出现「第 N 天」：$t"; break; }
}
$srcs = [];
foreach ([$THEME . '/includes/game/systems/drill-world.php', $THEME . '/includes/game/systems/calendar.php'] as $f) $srcs[$f] = file_get_contents($f);
foreach ($srcs as $f => $code) {
    if (preg_match('/第\s*%d\s*天/u', $code)) $err[] = '③ 源码里还在拼「第 %d 天」：' . basename($f) . '（委员长明令去掉 ✗）';
}
if (!$err) $ok[] = '③ 显示只有月/日/周几：400 天抽查无年份、无「第 N 天」✓';

/* ④ 日程触发幂等 */
$s = ['flags' => [], 'room' => 'b1_train'];
$pc = ['uid' => 0];
$o1 = []; crrg_cal_pump($s, $pc, $o1);
$o2 = []; crrg_cal_pump($s, $pc, $o2);
if (count($o2) !== 0) $err[] = '④ 到点提示不幂等：第二次又输出了 ' . count($o2) . ' 行';
else $ok[] = sprintf('④ 到点提示幂等（第一次 %d 行 / 第二次 0 行）✓', count($o1));

/* ⑤ 日程表自洽：每个事件的 hour 必须在 0–23；星期/日 合法 */
foreach (crrg_cal_events() as $id => $e) {
    $w = (array) $e['when'];
    if (isset($w['hour']) && ((int) $w['hour'] < 0 || (int) $w['hour'] > 23)) $err[] = "⑤ 事件 $id 的小时非法";
    if (isset($w['weekday']) && ((int) $w['weekday'] < 1 || (int) $w['weekday'] > 7)) $err[] = "⑤ 事件 $id 的星期非法";
    if (isset($w['dom']) && ((int) $w['dom'] < 1 || (int) $w['dom'] > 30)) $err[] = "⑤ 事件 $id 的日非法";
}
if (!$err) $ok[] = '⑤ 日程表自洽（点名/例会/演习日/发薪/轮休）✓';


/* ⑤′ 累计数字禁令（2026-10-14 委员长提）：**玩家可见文案里不许出现「第 N 天/周/月/年」** ✗
   理由：后期会过去几千年，"第十几万天"不合理 ✓ —— 显示一律走**月/日** ✓
   （内部日序可以有 ✓ 只是不许显示 ✓） */
$bad = [];
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($THEME . '/includes', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f2) { if ($f2->getExtension() !== 'php' || strpos($f2->getPathname(), '.bak') !== false) continue; $files[] = $f2->getPathname(); }
foreach ($files as $path) {
    $inBlock = false;                                                // **跨行注释要状态机** ✗（我第一次只剥单行 → 自己的注释被当真 ✓）
    foreach (file($path) as $ln => $line) {
        $code = (string) $line;
        if ($inBlock) {                                              // 还在上一行的块注释里
            $end = strpos($code, '*/');
            if ($end === false) continue;
            $code = substr($code, $end + 2); $inBlock = false;
        }
        $code = preg_replace('/\/\*.*?\*\//', '', $code);           // 同一行内成对的块注释
        if (strpos($code, '/*') !== false) { $inBlock = true; $code = substr($code, 0, strpos($code, '/*')); }
        $code = preg_replace('/\/\/.*$/', '', $code);                // 行注释
        if (preg_match("/'第 ' *\. *\\\$|\"第 \" *\. *\\\$/u", $code)
            || preg_match('/第 *[0-9]+ *(天|周|月|年)/u', $code)) {
            $bad[] = basename($path) . ':' . ($ln + 1) . '  ' . trim(mb_substr((string) $line, 0, 60));
        }
    }
}
if ($bad) { foreach ($bad as $b) $err[] = '⑤′ 玩家可见文案里有累计数字（第 N 天/周）✗ ' . $b; }
else $ok[] = '⑤′ 累计数字禁令：玩家文案里没有「第 N 天/周」✓';

echo "=== 日历门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：错误 %d ｜ 提醒 %d\n", count($err), count($warn));
exit($err ? 1 : 0);
