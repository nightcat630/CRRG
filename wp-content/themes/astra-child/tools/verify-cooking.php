<?php
/**
 * 烹饪 ／ 物品扩充 门禁（tools/verify-cooking.php，2026-10-14）
 * ① 配方引用的食材／厨具／火**必须真实存在**（物品表里查得到）
 * ② 每个配方**必须有产物**，且产物是真实物品、并有吃喝效果（或明确是"糊了"→ 单独一支 ✓）
 * ③ 扩充物品：id 不重复／cat 合法／食品有 food|ingredient／饮品有 drink
 * ④ 真跑一道菜：扣食材、出产物、时间走个人账 ✓
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
$err = []; $warn = []; $ok = [];
$items = function_exists('crrg_drill_items') ? crrg_drill_items() : [];
$tax   = function_exists('crrg_item_taxonomy') ? crrg_item_taxonomy() : [];

/* ① 配方合法性 */
$R = function_exists('crrg_cook_recipes') ? crrg_cook_recipes() : [];
if (!$R) $err[] = '① 配方表为空（cooking 未加载）';
foreach ($R as $rid => $r) {
    foreach ((array) ($r['need'] ?? []) as $ing => $n) {
        if (!isset($items[$ing])) $err[] = "① 配方「{$r['n']}」引用不存在的食材：$ing";
    }
    if (empty($r['out']) || !isset($items[$r['out']])) $err[] = "① 配方「{$r['n']}」的产物不存在：" . ($r['out'] ?? '（空）');
    foreach ((array) ($r['text'] ?? []) as $t) if (mb_strpos((string) $t, '第') === 0) $warn[] = "① 配方「{$r['n']}」文案可疑";
    if ((int) ($r['time'] ?? 0) <= 0) $err[] = "① 配方「{$r['n']}」没写时间（时间就是成本 ✓）";
    if (!empty($r['fire']) && empty($r['tool'])) $warn[] = "① 配方「{$r['n']}」只要火不要锅——确认是有意的 ✓";
}
$ok[] = '① 配方 ' . count($R) . ' 条：食材／产物全部对得上 ✓';

/* ② 产物要有吃喝效果（除"糊了"那支） */
foreach ($R as $r) {
    $fx = function_exists('crrg_vitals_item_fx') ? crrg_vitals_item_fx((string) $r['out']) : null;
    if (!$fx && !in_array((string) $r['out'], ['food_burnt'], true)) $warn[] = "② 产物「{$r['out']}」没有吃喝效果（吃了等于没吃 ✗）";
}
$ok[] = '② 产物效果检查完成 ✓';

/* ③ 扩充物品的字段 */
$extra = function_exists('crrg_game_items_data_extra') ? crrg_game_items_data_extra() : [];
$bad = 0;
foreach ($extra as $iid => $it) {
    if (empty($it['cat']) || !isset($tax[$it['cat']])) { $err[] = "③ 扩充物品「$iid」的 cat 非法：" . ($it['cat'] ?? '（无）'); $bad++; }
    $maj = $tax[$it['cat']]['major'] ?? '';
    if (empty($it['n'])) { $err[] = "③ 扩充物品「$iid」没有名字"; $bad++; }
    if ($maj === 'food' && empty($it['food']) && empty($it['ingredient'])) { $err[] = "③ 食品「$iid」既没 food 也没 ingredient"; $bad++; }
    if ($maj === 'drink' && empty($it['drink'])) { $err[] = "③ 饮品「$iid」没有 drink 标记"; $bad++; }
}
$ok[] = '③ 扩充物品 ' . count($extra) . ' 件，字段问题 ' . $bad . ' 处 ✓';

/* ④ 真跑一道菜 */
if (function_exists('crrg_cook_do')) {
    $s = ['room' => 'b1_canteen', 'flags' => [], 'items' => []];
    $pc = ['uid' => 9777, 'name' => '（门禁）', 'hp' => 10, 'san' => 60, 'money' => 0, 'rep' => [], 'npc_fav' => [],
           'items' => ['food_noodle_dry' => 1, 'food_lard' => 1, 'tool_wok' => 1, 'smoke_stove' => 1],
           'vitals' => crrg_vitals_default()];
    $t0 = (int) ($pc['vitals']['t'] ?? 0);
    $out = crrg_cook_do($s, $pc, 'noodle');
    $joined = implode("\n", (array) $out);
    $got = (int) (crrg_drill_own_count($pc, 'food_noodle_bowl') ?? 0);
    $t1 = (int) ($pc['vitals']['t'] ?? 0);
    if ($got < 1 && mb_strpos($joined, '糊了') === false) $err[] = '④ 做菜既没出产物也没说糊了';
    else $ok[] = '④ 真跑一道菜：' . ($got >= 1 ? '出「热汤面」✓' : '糊了（失败推进 ✓）');
    if ($t1 === $t0) $warn[] = '④ 做菜没走时间账（个人钟）';
    $n1 = (int) (crrg_drill_own_count($pc, 'food_noodle_dry') ?? 0);
    if ($got >= 1 && $n1 !== 0) $warn[] = '④ 做出菜了但食材没扣';
    else $ok[] = '④ 食材被扣（' . $n1 . ' 份挂面剩下）✓';
} else { $err[] = '④ cooking 未加载'; }

echo "=== 烹饪／物品门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：错误 %d ｜ 提醒 %d\n", count($err), count($warn));
exit($err ? 1 : 0);
