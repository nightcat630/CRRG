<?php
/**
 * CRRG 演习 · 装备维护与消耗（systems/equip.php，R9-①，2026-10-14）
 * ------------------------------------------------------------
 * 委员长："枪械保养值、工具损耗、电池；与检定加成联动（引擎 check.bonus_from）"
 * 口径：
 *   · **保养值/电量**都存在个人档（`$pc['gear'][$id]`），不用新表 ✓
 *   · 用一次就掉；**耐久 <30 → 检定 +1 惩罚骰**；**=0 → 失效**（不是删物品，是"用不了" ✓）
 *   · 保养能修（花时间＋钱）；**不同工具给不同检定加成** → 引擎 `check.bonus_from = 'gear:手电'` ✓
 */
if (!defined('ABSPATH')) exit;

/** 装备档案（哪些物品算"装备"、带什么加成、怎么掉） */
function crrg_gear_defs() {
    return [
        'torch'      => ['n' => '手电', 'dur' => 60, 'wear' => 3, 'bonus' => ['侦查', '夜巡', '潜行'], 'maint' => 20],
        'tool_pry'   => ['n' => '撬棍', 'dur' => 70, 'wear' => 5, 'bonus' => ['机械维修', '力量'], 'maint' => 25],
        'tool_box'   => ['n' => '工具箱', 'dur' => 80, 'wear' => 4, 'bonus' => ['机械维修', '电气维修'], 'maint' => 30],
        'radio'      => ['n' => '对讲机', 'dur' => 40, 'wear' => 2, 'bonus' => ['聆听', '通讯'], 'maint' => 15, 'batt' => true],
        'camera'     => ['n' => '相机', 'dur' => 50, 'wear' => 4, 'bonus' => ['摄影', '侦查'], 'maint' => 20, 'batt' => true],
        'gun'        => ['n' => '配枪', 'dur' => 90, 'wear' => 2, 'bonus' => ['射击'], 'maint' => 60],
    ];
}
function crrg_gear_def($id) { $d = crrg_gear_defs(); return $d[(string) $id] ?? null; }

/** 某件装备的当前状态（没有就按新品算 ✓ 幂等） */
function crrg_gear_state(&$pc, $id) {
    $def = crrg_gear_def($id);
    if (!$def) return null;
    $pc['gear'] = (array) ($pc['gear'] ?? []);
    if (!isset($pc['gear'][$id])) $pc['gear'][$id] = ['dur' => (int) $def['dur'], 'batt' => (!empty($def['batt']) ? 100 : 0)];
    return $pc['gear'][$id];
}

/** 用一次：掉耐久（/电量）；返回 [可用吗, 说明] */
function crrg_gear_use(&$pc, $id, $n = 1) {
    $def = crrg_gear_def($id);
    if (!$def) return [true, ''];
    $st = crrg_gear_state($pc, $id);
    $wear = (int) $def['wear'] * max(1, (int) $n);
    $st['dur'] = max(0, (int) $st['dur'] - $wear);
    if (!empty($def['batt'])) $st['batt'] = max(0, (int) $st['batt'] - 4 * max(1, (int) $n));
    $pc['gear'][$id] = $st;
    $out = [];
    if ($st['dur'] <= 0) return [false, sprintf('（%s 已经报废——这一趟干不了活了。）', $def['n'])];
    if (!empty($def['batt']) && (int) $st['batt'] <= 0) return [false, sprintf('（%s 没电了。）', $def['n'])];
    if ($st['dur'] < 30) $out[] = sprintf('（%s 的状态不太好了。）', $def['n']);
    if (!empty($def['batt']) && (int) $st['batt'] < 20) $out[] = sprintf('（%s 的电量见底。）', $def['n']);
    return [true, implode('', $out)];
}

/** 保养/换电：花时间与钱，修回一档 */
function crrg_gear_maint(&$pc, $id) {
    $def = crrg_gear_def($id);
    if (!$def) return ['（没有这件装备。）'];
    $st = crrg_gear_state($pc, $id);
    $cost = (int) $def['maint'];
    if ((int) ($pc['money'] ?? 0) < $cost) return [sprintf('（保养要 %d 元，你不够。）', $cost)];
    $pc['money'] = (int) $pc['money'] - $cost;
    $st['dur'] = (int) $def['dur'];
    if (!empty($def['batt'])) $st['batt'] = 100;
    $pc['gear'][$id] = $st;
    return [sprintf('（%s 保养好了，花了 %d 元。）', $def['n'], $cost)];
}

/** 这件装备对某个技能有没有加成（引擎 check.bonus_from = 'gear:torch' 用 ✓） */
function crrg_gear_bonus(&$pc, $id, $skill = '') {
    $def = crrg_gear_def($id);
    if (!$def) return 0;
    $st = crrg_gear_state($pc, $id);
    if ((int) $st['dur'] <= 0) return 0;                                 // 报废了没有加成 ✓
    if (!empty($def['batt']) && (int) $st['batt'] <= 0) return 0;
    if ($skill !== '' && !in_array((string) $skill, (array) $def['bonus'], true)) return -1;   // 不相干 → 反而碍手（-1 惩罚骰）
    return (int) $st['dur'] < 30 ? 0 : 1;                                // 好装备 → 奖励骰 ✓
}

/** 一行状态（面板/手机里看得到 ✓） */
function crrg_gear_lines(&$pc) {
    $out = ['〔装备状态〕'];
    $any = false;
    foreach (crrg_gear_defs() as $id => $def) {
        $own = function_exists('crrg_drill_own_count') ? crrg_drill_own_count($pc, $id) : 0;
        if ($own <= 0) continue;
        $st = crrg_gear_state($pc, $id);
        $out[] = sprintf('　%s：保养 %d%%%s', $def['n'], (int) $st['dur'], !empty($def['batt']) ? sprintf('，电量 %d%%', (int) $st['batt']) : '');
        $any = true;
    }
    if (!$any) $out[] = '　（身上没带着需要保养的东西。）';
    return $out;
}
