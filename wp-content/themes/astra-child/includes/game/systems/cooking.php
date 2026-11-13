<?php
/**
 * CRRG 演习 · 烹饪系统（systems/cooking.php，2026-10-14）
 * ------------------------------------------------------------
 * 委员长："加上烹饪系统。"
 *
 * 玩法（照现有系统的语法来，不新造一套）：
 *   · 「做菜」→ 列**你现在能做的**（按手上的食材算，不能做的写缺什么 ✓ 不许"猜谜"）
 *   · 「做菜 热汤面」→ 检查 ①食材（扣掉）②厨具（锅／刀／案板）③火（灶／煤油炉／打火机）④时间（个人钟 ✓ 不碰世界钟）
 *                    → 检定（烹饪，灶边做菜有奖励骰）→ **成／成但有代价／失败推进**
 *   · 成功 → 出正品（走 vitals 效果表 ✓）；失败 → 出「糊了的东西」（**仍能吃、效果差、掉 1 点理智** ✓ 不白费食材 ✓）
 *
 * 纪律：
 *   · **产物用现成物品**（food_* 已有 fx ✓ 一份真相）✗ 不另造"虚拟食物"
 *   · 配方引用的食材**必须真实存在**（门禁 verify-cooking.php 会查 ✓）
 *   · 时间只算个人账 ✓ 不推世界钟 ✗
 */
if (!defined('ABSPATH')) exit;

/** 配方表（`need` 是食材，`tool` 是任一件厨具即可，`fire` 需不需要火） */
function crrg_cook_recipes() {
    return [
        'noodle'  => ['n' => '热汤面', 'need' => ['food_noodle_dry' => 1, 'food_lard' => 1], 'tool' => '铁锅', 'fire' => true,
                      'time' => 25, 'out' => 'food_noodle_bowl', 'text' => ['面下锅，猪油一化，汤色立刻浑了。']],
        'stew'    => ['n' => '炖菜', 'need' => ['food_potato' => 1, 'food_pork_cured' => 1, 'food_salt' => 1], 'tool' => '铁锅', 'fire' => true,
                      'time' => 45, 'out' => 'food_stew', 'text' => ['腌肉先下，土豆后下。锅盖一压，屋里就都是这个味。']],
        'fried'   => ['n' => '蛋炒饭', 'need' => ['food_rice' => 1, 'food_eggs' => 1, 'food_lard' => 1], 'tool' => '铁锅', 'fire' => true,
                      'time' => 30, 'out' => 'food_fried_rice', 'text' => ['饭要隔夜的才散，蛋要往边上推。']],
        'bun'     => ['n' => '馒头', 'need' => ['food_flour' => 1, 'food_yeast' => 1], 'tool' => '案板', 'fire' => true,
                      'time' => 60, 'out' => 'food_bun', 'text' => ['面发起来了，指头一按一个坑。']],
        'dumpling'=> ['n' => '饺子', 'need' => ['food_flour' => 1, 'food_pork_cured' => 1, 'food_cabbage' => 1], 'tool' => '案板', 'fire' => true,
                      'time' => 70, 'out' => 'food_dumpling', 'text' => ['剁馅的声音能传半层楼。']],
        'pancake' => ['n' => '烙饼', 'need' => ['food_flour' => 1, 'food_lard' => 1], 'tool' => '铁锅', 'fire' => true,
                      'time' => 35, 'out' => 'food_pancake', 'text' => ['饼在锅里鼓起来，你就知道可以翻了。']],
        'fishrice'=> ['n' => '咸鱼饭', 'need' => ['food_rice' => 1, 'food_fish_salt' => 1], 'tool' => '铁锅', 'fire' => true,
                      'time' => 40, 'out' => 'food_fish_rice', 'text' => ['咸鱼压饭上蒸，咸味往下走。']],
        'stirfry' => ['n' => '洋葱炒蛋', 'need' => ['food_onion' => 1, 'food_eggs' => 1, 'food_oil' => 1], 'tool' => '铁锅', 'fire' => true,
                      'time' => 20, 'out' => 'food_stew', 'text' => ['洋葱下去先出甜味，蛋最后翻两下就起锅。']],
        'mushsoup'=> ['n' => '香菇汤', 'need' => ['food_dried_mush' => 1, 'food_salt' => 1], 'tool' => '铁锅', 'fire' => true,
                      'time' => 35, 'out' => 'food_stew', 'text' => ['干香菇泡开的水别倒，那是味。']],
        'tea'     => ['n' => '砖茶', 'need' => ['drink_tea_brick' => 1], 'tool' => '', 'fire' => true,
                      'time' => 15, 'out' => 'drink_tea_brick', 'text' => ['茶砖掰一块，熬到发黑。']],
        'milk'    => ['n' => '冲奶粉', 'need' => ['drink_milk_powd' => 1], 'tool' => '水壶', 'fire' => true,
                      'time' => 8, 'out' => 'drink_milk_powd', 'text' => ['热水一冲，结块得搅一搅。']],
        'hotwater'=> ['n' => '烧开水', 'need' => [], 'tool' => '水壶', 'fire' => true,
                      'time' => 12, 'out' => 'drink_boiled', 'text' => ['壶嘴开始响的时候，差不离了。']],
    ];
}

/** 手上的厨具/火（判断能不能做 ✓） */
function crrg_cook_have(&$pc, $kind) {
    $own = function_exists('crrg_drill_own_count') ? 'crrg_drill_own_count' : '';
    $cnt = [];
    foreach ((array) ($pc['items'] ?? []) as $id => $n) $cnt[$id] = (int) $n;
    if ($kind === 'fire') {
        foreach (['smoke_stove', 'smoke_matches', 'tool_lighter', 'smoke_cigar'] as $f) {
            if (!empty($cnt[$f])) return true;
        }
        /* 灶台（食堂）算有火 ✓ */
        return false;
    }
    if ($kind === '') return true;
    $map = ['铁锅' => ['tool_wok', 'tool_mug', 'tool_thermos'], '案板' => ['tool_board'],
            '水壶' => ['tool_flask', 'tool_thermos', 'tool_mug']];
    foreach ((array) ($map[$kind] ?? []) as $id) if (!empty($cnt[$id])) return true;
    return false;
}

/** 列"现在能做什么"（不能做的说缺什么 ✓） */
function crrg_cook_menu(&$s, &$pc) {
    $out = ['〔菜谱〕能做的排前面；缺东西的写在后面。'];
    $have = [];
    foreach (crrg_cook_recipes() as $id => $r) {
        [$ok, $why] = crrg_cook_can($s, $pc, $id);
        $have[] = [$ok, sprintf('　%s%s', $r['n'], $ok ? '' : '　—— ' . $why)];
    }
    usort($have, fn($a, $b) => $b[0] <=> $a[0]);
    foreach ($have as $h) $out[] = $h[1];
    $out[] = '（做：「做菜 <菜名>」。）';
    return $out;
}

/** 能不能做：[bool, 缺什么] */
function crrg_cook_can(&$s, &$pc, $id) {
    $R = crrg_cook_recipes();
    if (!isset($R[$id])) return [false, '没这道'];
    $r = $R[$id];
    $cnt = [];
    foreach ((array) ($pc['items'] ?? []) as $k => $n) $cnt[$k] = (int) $n;
    $miss = [];
    foreach ((array) $r['need'] as $ing => $n) {
        if ((int) ($cnt[$ing] ?? 0) < (int) $n) $miss[] = function_exists('crrg_drill_item_name') ? crrg_drill_item_name($ing) : $ing;
    }
    if ($miss) return [false, '缺 ' . implode('、', $miss)];
    if (!empty($r['tool']) && !crrg_cook_have($pc, (string) $r['tool']) && !crrg_cook_at_kitchen($s)) return [false, '缺' . $r['tool']];
    if (!empty($r['fire']) && !crrg_cook_have($pc, 'fire') && !crrg_cook_at_kitchen($s)) return [false, '没火'];
    return [true, ''];
}

/** 是不是在能做饭的地方（食堂/宿舍灶间 ✓ 有奖励骰） */
function crrg_cook_at_kitchen(&$s) {
    return in_array((string) ($s['room'] ?? ''), ['b1_canteen', 'b2f_barracks', 'b1_kitchen'], true);
}

/** 开火做一道菜（**失败也推进** ✓） */
function crrg_cook_do(&$s, &$pc, $id) {
    $R = crrg_cook_recipes();
    if (!isset($R[$id])) return ['（没这道菜。打「做菜」看菜谱。）'];
    $r = $R[$id];
    [$ok, $why] = crrg_cook_can($s, $pc, $id);
    if (!$ok) return ['（做不了：' . $why . '。）'];

    /* 扣食材（**失败也不退** ✓ 火候就是成本） */
    foreach ((array) $r['need'] as $ing => $n) {
        if (function_exists('crrg_drill_bag_add_n')) crrg_drill_bag_add_n($pc, (string) $ing, -abs((int) $n));
    }
    /* 时间：个人账 ✓ */
    if (function_exists('crrg_vitals_now') && isset($pc['vitals'])) $pc['vitals']['t'] = crrg_vitals_now() - (int) $r['time'] * 60;

    /* 检定：烹饪（在灶边做有奖励骰 ✓） */
    $skill = '烹饪';
    $val = function_exists('crrg_coc_skill_val') ? max(1, crrg_coc_skill_val($pc, $skill)) : 30;
    $bonus = crrg_cook_at_kitchen($s) ? 1 : 0;
    $c = function_exists('crrg_coc_check') ? crrg_coc_check($skill, $val, $bonus) : ['text' => '', 'level' => '普通成功'];
    $rank = function_exists('crrg_coc_level_rank') ? crrg_coc_level_rank($c['level']) : 2;

    $out = ['〔' . $r['n'] . '〕'];
    $out[] = sprintf('（备料到出锅，约 %d 分钟。）', (int) $r['time']);
    foreach ((array) $r['text'] as $t) $out[] = $t;
    if ($c['text'] !== '') $out[] = $c['text'];

    if ($rank >= 2) {
        if (function_exists('crrg_drill_bag_add_n')) crrg_drill_bag_add_n($pc, (string) $r['out'], 1);
        $out[] = '（出锅了：' . (function_exists('crrg_drill_item_name') ? crrg_drill_item_name($r['out']) : $r['out']) . ' ×1。）';
        if ($rank < 3) $out[] = '（火候差一点——能吃，味道一般。）';
    } else {
        if (function_exists('crrg_drill_bag_add_n')) crrg_drill_bag_add_n($pc, 'food_burnt', 1);
        $out[] = '（糊了。锅底一层黑，刮下来还能凑一份。）';
        if (function_exists('crrg_san_loss')) foreach ((array) crrg_san_loss($pc, 1, '把一锅东西做糊了') as $x) $out[] = $x;
    }
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.beat', ['kind' => 'cook', 'recipe' => $id, 'ok' => $rank >= 2, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'cooking']);
    }
    return $out;
}
