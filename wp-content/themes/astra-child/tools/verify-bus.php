<?php
/**
 * CRRG 演习 · 总线门禁（tools/verify-bus.php，Phase 0，2026-10-14）
 * ------------------------------------------------------------
 * 查五件事（任何一条红都别交 ✓）：
 *   ① 订阅完整性：发出去的事件名必须有订阅者；订阅的名字必须在 crrg_evt_known() 名单里
 *   ② 事实表只追加：源码里搜不到对 crrg_facts 的 UPDATE / DELETE
 *   ③ 推进器幂等：同一时刻重跑不再落格；格子数按时间差算得对
 *   ④ **悬空调用**：function_exists('crrg_xxx') 里的名字在主题里到底有没有定义
 *      （2026-10-14 就是被这个坑了：crrg_seal_pending_check 被调用、没有定义、静默跳过 ✗）
 *   ⑤ 改写"个人档的单调幂等"：pending_ 每个节点最多兑现一次（有 pendingpaid_ 配对）
 *
 * 用法：php tools/verify-bus.php
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
$wl = '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
require $wl;

$err = []; $warn = []; $ok = [];
function scan_src($dir) {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        if (strpos($f->getPathname(), '.bak') !== false) continue;
        $out[$f->getPathname()] = file_get_contents($f->getPathname());
    }
    return $out;
}
$SRC = scan_src($THEME . '/includes') + [$THEME . '/functions.php' => file_get_contents($THEME . '/functions.php')];

/* ① 订阅完整性 */
$known = function_exists('crrg_evt_known') ? array_keys(crrg_evt_known()) : [];
$emit = []; $on = [];
foreach ($SRC as $file => $code) {
    if (preg_match_all("/crrg_evt_emit\(\s*'([a-z0-9_.]+)'/i", $code, $m)) {
        foreach ($m[1] as $n) $emit[$n][] = basename($file);
    }
    if (preg_match_all("/crrg_evt_on\(\s*'([a-z0-9_.]+)'/i", $code, $m)) {
        foreach ($m[1] as $n) $on[$n][] = basename($file);
    }
}
foreach ($emit as $n => $where) {
    if (empty($on[$n])) $err[] = "① 事件「{$n}」发了但**没人订阅**（" . implode(',', array_unique($where)) . "）→ 空转";
}
foreach ($on as $n => $where) {
    if (!in_array($n, $known, true)) $err[] = "① 订阅了未登记的事件「{$n}」（" . implode(',', array_unique($where)) . "）→ 先去 crrg_evt_known() 登记";
}
foreach ($known as $n) {
    if (empty($on[$n])) $warn[] = "① 名单里的事件「{$n}」还没有订阅者（可以先留着，但别发它）";
}
$ok[] = sprintf('① 订阅完整性：事件 %d 个（发出 %d / 订阅 %d）', count($known), count($emit), count($on));

/* ② 事实表只追加 */
$bad = [];
foreach ($SRC as $file => $code) {
    if (preg_match_all("/\\b(?:UPDATE|DELETE\\s+FROM)\\s+\\S*crrg_facts/i", $code, $m)) $bad[] = basename($file) . ':' . implode('/', $m[1]);
}
if ($bad) $err[] = '② 事实表被改写：' . implode('；', $bad) . ' → 只许 INSERT（要撤回就追加相反事实）';
$ok[] = '② 事实表只追加：源码里 UPDATE/DELETE 0 处';

/* ③ 推进器幂等 */
if (function_exists('crrg_tick') && function_exists('crrg_tick_set') && function_exists('crrg_fact_now')) {
    /* ⚠ 工具不许留副作用（2026-10-14 自查）：跑推进器会经订阅者落事实/写 option ✗
       → 测前记最大 id 与相关 option，测后**精确回收** ✓ */
    global $wpdb;
    $ft   = crrg_facts_table();
    $maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $ft");
    $bakOpts = [];
    foreach (['crrg_world_tick', 'crrg_last_day', 'crrg_fact_ping', 'crrg_world_day_note', 'crrg_recent_settle', 'crrg_fight_pending', 'crrg_travel_pending', 'crrg_bt_state', 'crrg_duty','crrg_contain', 'crrg_duty_roster','crrg_phone_life','crrg_arch_index'] as $o) $bakOpts[$o] = get_option($o, null);

    $T = 1800000000;                        // 固定测试时刻（不碰世界钟 ✓）
    crrg_tick_set($T);
    $r1 = crrg_tick($T + 3 * 1800);          // 应正好 3 格
    $r2 = crrg_tick($T + 3 * 1800);          // 同一时刻重跑 → 必须 0 格
    if ((int) $r1['slots'] !== 3) $err[] = "③ 推进器格数不对：期望 3，得 {$r1['slots']}";
    if ((int) $r2['slots'] !== 0) $err[] = "③ 推进器不幂等：同一时刻重跑又落了 {$r2['slots']} 格";

    $wpdb->query($wpdb->prepare("DELETE FROM $ft WHERE id > %d", $maxId));   // 回收测试事实 ✓
    foreach ($bakOpts as $o => $v) { if ($v === null) delete_option($o); else update_option($o, $v, false); }
    $ok[] = '③ 推进器幂等：3 格 → 同刻重跑 0 格 ✓（测试副作用已回收）';
} else {
    $err[] = '③ 总线未加载（crrg_tick 不存在）→ 检查 functions.php 挂载';
}

/* ④ 悬空调用（function_exists('crrg_xxx') 里没有定义的） */
$defined = [];
foreach ($SRC as $code) {
    if (preg_match_all("/function\s+(crrg_[a-z0-9_]+)\s*\(/i", $code, $m)) foreach ($m[1] as $n) $defined[strtolower($n)] = 1;
}
$dangle = [];
foreach ($SRC as $file => $code) {
    if (preg_match_all("/function_exists\(\s*'(crrg_[a-z0-9_]+)'/i", $code, $m)) {
        foreach ($m[1] as $n) {
            if (!isset($defined[strtolower($n)])) $dangle[$n][] = basename($file);
        }
    }
}
ksort($dangle);
foreach ($dangle as $n => $where) {
    $warn[] = sprintf('④ 悬空调用：%s() 被 function_exists 挡着调用，但主题里**找不到定义**（%s）→ 空头承诺？',
        $n, implode(',', array_unique($where)));
}
$ok[] = sprintf('④ 悬空调用扫描：定义 %d 个函数，悬空 %d 个', count($defined), count($dangle));

/* ⑤ pending 与 pendingpaid 配对（隐患兑现闭环） */
$hasPaid = false;
foreach ($SRC as $code) if (strpos($code, 'pendingpaid_') !== false) $hasPaid = true;
if (!$hasPaid) $err[] = '⑤ 有 pending_ 但全库没有 pendingpaid_ → 隐患不会兑现（空头承诺）';
else $ok[] = '⑤ 隐患兑现闭环：pending_ / pendingpaid_ 配对存在 ✓';

/* ── 报告 ── */
echo "=== 总线门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：错误 %d ｜ 提醒 %d\n", count($err), count($warn));
exit($err ? 1 : 0);
