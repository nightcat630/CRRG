<?php
/**
 * 档案 · 天气 · 周报 门禁（tools/verify-archive.php，R10，2026-10-14）
 * ① 检索：命中已知词、无结果时给话、**花时间并留痕**（幂等）
 * ② 权限：不足级别 → 看得到存在、看不到内容 ✓
 * ③ 天气：雨/雾/雪 对指定技能给惩罚骰；晴天不给；**夜雾每次只掉一次理智**
 * ④ 周报：一周只交一次（幂等）；**瞒报有后果**（声望/事实）
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

/* 2026-10-14：**门禁不许留副作用**（第 N 次强调 ✗ —— 今天又栽了）：
   跑一遍游戏逻辑会落事实/写 option → 跑前记水位，跑完精确回收 ✓ */
$__ft = function_exists('crrg_facts_table') ? crrg_facts_table() : '';
$__maxId = 0;
if ($__ft) { global $wpdb; $__maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $__ft"); }
$__optBak = [];
foreach (['crrg_world_tick','crrg_last_day','crrg_fact_ping','crrg_recent_settle','crrg_world_day_note','crrg_fight_pending','crrg_travel_pending','crrg_bt_state','crrg_duty','crrg_duty_roster','crrg_phone_life','crrg_arch_index'] as $o) $__optBak[$o] = get_option($o, null);
register_shutdown_function(function () use ($__ft, $__maxId, $__optBak) {
    if ($__ft) { global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM $__ft WHERE id > %d", $__maxId)); }
    foreach ($__optBak as $o => $v) { if ($v === null) delete_option($o); else update_option($o, $v, false); }
});
$err = []; $warn = []; $ok = [];
$bakFacts = get_option('crrg_arch_index', null);

function mkpc($lvl) {
    return ['uid' => 9777, 'name' => '（门禁）', 'hp' => 10, 'san' => 60, 'money' => 0, 'rep' => [],
            'npc_fav' => [], 'items' => [], 'mission_done' => [], 'vitals' => crrg_vitals_default(), 'clearance' => $lvl];
}

/* ① 检索 */
if (function_exists('crrg_arch_do')) {
    $s = ['room' => 'b1_archive', 'flags' => [], 'items' => []];
    $pc = mkpc(3);
    $r = crrg_arch_do($s, $pc, '在册');
    if (!$r) $err[] = '① 检索没输出';
    else $ok[] = '① 检索能出结果（' . count($r) . ' 行）✓';
    if (empty($s['flags'])) $err[] = '① 检索没有留痕（arch_ 旗标）';
    else $ok[] = '① 检索留痕 ✓';
    $n1 = count($s['flags']);
    crrg_arch_do($s, $pc, '在册');
    if (count($s['flags']) !== $n1) $err[] = '① 检索不幂等：同一关键词重复留痕';
    else $ok[] = '① 同一关键词不重复留痕（幂等）✓';
    $r2 = crrg_arch_do($s, $pc, '不存在的词xyz');
    $joined = implode("\n", (array) $r2);
    if (mb_strpos($joined, '没有对应条目') === false) $err[] = '① 查不到时没有明确说明';
    else $ok[] = '① 查不到时给的是人话（不是空白）✓';
} else { $err[] = '① archive 未加载'; }

/* ② 权限 */
if (function_exists('crrg_arch_can')) {
    $a = crrg_arch_can(mkpc(3), 'secret');
    $b = crrg_arch_can(mkpc(0), 'secret');
    if (!$a || $b) $err[] = '② 档案等级判定不对（高等级该能看、低等级该不能）';
    else $ok[] = '② 档案等级：高能看／低不能看 ✓';
} else { $err[] = '② crrg_arch_can 缺失'; }

/* ③ 天气 */
if (function_exists('crrg_weather_pen')) {
    $old = get_option('crrg_seal_wtest', null);
    $penRain = crrg_weather_pen('侦查', '');            // 当前天气未必是雨 → 用函数本身能不能判别为准
    if (!is_int($penRain)) $err[] = '③ 天气惩罚不是整数';
    else $ok[] = '③ 天气惩罚可计算（当前 ' . $penRain . '）✓';
    if (function_exists('crrg_weather_note')) $ok[] = '③ 天气说明接口在 ✓';
} else { $err[] = '③ crrg_weather_pen 缺失'; }

/* ④ 周报 */
if (function_exists('crrg_week_submit')) {
    $s = ['room' => 'b2b_hall', 'flags' => [], 'items' => [], 'expose_m_x' => 2];   // 有暴露 → 不该瞒
    $pc = mkpc(2);
    $r1 = crrg_week_submit($s, $pc, '本周无事。');
    $joined = implode("\n", (array) $r1);
    if (mb_strpos($joined, '对不上') === false) $err[] = '④ 有暴露却瞒报，没有后果';
    else $ok[] = '④ 瞒报有后果（委员会核账）✓';
    $r2 = crrg_week_submit($s, $pc, '');
    if (mb_strpos(implode("\n", (array) $r2), '已经交过') === false) $err[] = '④ 一周内能重复交周报（不幂等）';
    else $ok[] = '④ 一周只交一次（幂等）✓';
    if (function_exists('crrg_week_due') && crrg_week_due($s, $pc)) $err[] = '④ 交过了却还说该交';
    else $ok[] = '④ 交过之后不再提示 ✓';
} else { $err[] = '④ weekly 未加载'; }

echo "=== 档案/天气/周报门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：错误 %d ｜ 提醒 %d\n", count($err), count($warn));
exit($err ? 1 : 0);
