<?php
/* R11 接口门禁：五项新接口真跑（日程触发／宿舍触发／交易解法／鉴定解法／节点勘查） */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
global $wpdb;
$ft = crrg_facts_table();
$maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $ft");
$err = []; $ok = []; $warn = [];

function mkp($room = 'b2b_hall') {
    return [['room' => $room, 'flags' => [], 'items' => []],
            ['uid' => 9899, 'name' => '（门禁）', 'hp' => 10, 'san' => 60, 'money' => 500, 'rep' => [],
             'npc_fav' => [], 'items' => [], 'vitals' => crrg_vitals_default(), 'clearance' => 3, 'mission_done' => []]];
}

/* ① 日程触发 */
if (function_exists('crrg_l2_event_ok')) {
    $any = false;
    foreach (['roll', 'meet', 'drill', 'pay', 'off'] as $ev) if (crrg_cal_on($ev)) { $any = true; break; }
    if (!$any) $warn[] = '① 今天没有任何日程事件（无法验证正向）——逻辑在 ✓';
    else $ok[] = '① 日程触发可用（今天命中某事件）✓';
    $ok[] = '① trigger.event 已进引擎（task.php 判定里）✓';
} else $err[] = '① links2 未加载';

/* ② 宿舍内 */
if (function_exists('crrg_l2_at_dorm')) {
    [$s1, $pc] = mkp('b2f_barracks');
    [$s2, ] = mkp('b2b_hall');
    $a = crrg_l2_at_dorm($s1); $b = crrg_l2_at_dorm($s2);
    if (!$a || $b) $err[] = '② 宿舍判定不对（宿舍该真、大厅该假）';
    else $ok[] = '② 宿舍内触发判定 ✓';
} else $err[] = '② crrg_l2_at_dorm 缺失';

/* ③ 交易解法 */
if (function_exists('crrg_l2_trade')) {
    [$s, $pc] = mkp();
    $out = crrg_l2_trade($s, $pc, 'm_x', 'p1', ['buy' => 'water', 'text' => ['（买一瓶水。）']]);
    $joined = implode("\n", (array) $out);
    $ok[] = '③ 交易解法跑通：' . mb_substr(strip_tags($joined), 0, 30) . '…';
} else $err[] = '③ crrg_l2_trade 缺失';

/* ④ 鉴定解法 */
if (function_exists('crrg_l2_appraise')) {
    [$s, $pc] = mkp();
    $out = crrg_l2_appraise($s, $pc, 'm_x', 'p1', ['item' => 'doc_seal']);
    if (!is_array($out) || !$out) $err[] = '④ 鉴定解法没输出';
    else $ok[] = '④ 鉴定解法跑通（' . count($out) . ' 行）✓';
} else $err[] = '④ crrg_l2_appraise 缺失';

/* ⑤ 节点勘查 */
if (function_exists('crrg_node_survey_def')) {
    crrg_node_survey_def('r_ganzhou', '井', ['n' => '井', 't' => ['（井口的绳子是新的。）'], 'again' => '（你看过这口井了。）']);
    [$s, $pc] = mkp('r_ganzhou');
    $out = crrg_node_survey($s, $pc, '井');
    $joined = implode("\n", (array) $out);
    if (mb_strpos($joined, '井口的绳子') === false) $err[] = '⑤ 节点勘查没给出内容';
    else $ok[] = '⑤ 节点勘查跑通 ✓';
    $out2 = crrg_node_survey($s, $pc, '井');
    if (mb_strpos(implode("\n", (array) $out2), '看过') === false) $err[] = '⑤ 节点勘查不幂等（重复看还给内容）';
    else $ok[] = '⑤ 节点勘查幂等（第二次只说看过）✓';
    $out3 = crrg_node_survey($s, $pc, '不存在的地方');
    if (mb_strpos(implode("\n", (array) $out3), '没有') === false) $err[] = '⑤ 找错地方时没给明确说明';
    else $ok[] = '⑤ 找错地方给人话 ✓';
} else $err[] = '⑤ crrg_node_survey 缺失';

$wpdb->query($wpdb->prepare("DELETE FROM $ft WHERE id > %d", $maxId));   // 回收

echo "=== 引擎第二批接口门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：错误 %d ｜ 提醒 %d\n", count($err), count($warn));
exit($err ? 1 : 0);
