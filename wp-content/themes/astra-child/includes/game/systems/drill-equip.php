<?php
/**
 * 装备与部位（2026-09-28）
 * 部位：头 / 身 / 腿 / 臂 / 左手 / 右手（照委员长给的划分）
 * 规则：
 *   1. 只有标了 slot 的东西才穿得上，其余一律回绝（不能把罐头套在头上）。
 *   2. 一个部位一件；连体装备（如防护服）可占多个部位。
 *   3. 穿戴前先在背包里，脱下后回背包。
 *   4. 护甲＝各部位之和（上限 3）；部分装备带代价（头盔压侦查）。
 *   5. 旧存档的 worn 平铺清单会自动迁移到部位表，不丢东西。
 */
if (!defined('ABSPATH')) exit;

function crrg_equip_slots() {
    return ['head' => '头', 'torso' => '身', 'legs' => '腿', 'arms' => '臂',
            'hand_l' => '左手', 'hand_r' => '右手'];
}
/** 物品 → 可穿部位（没登记＝不能穿戴） */
function crrg_equip_item_slots($id) {
    $M = [
        'gear_hazmat'      => ['torso', 'legs'],   // 连体防护服
        'gear_vest'      => ['torso'],           // 帆布护具
        'gear_helmet'    => ['head'],            // 防护头盔
        'apron'     => ['torso'],           // 皮围裙（备用）
        'glove'     => ['hand_l'],          // 劳保手套
        'tool_flashlight'=> ['hand_l'],
        'tool_lamp'      => ['hand_l'],
        'tool_rope_nylon'      => ['hand_l'],
        'tool_prybar'    => ['hand_l'],
        'tool_ladder'    => ['hand_l'],
        'weap_pistol'    => ['hand_r'],
        'weap_rifle'     => ['hand_r'],
        'weap_shotgun'      => ['hand_r'],
        'weap_baton'     => ['hand_r'],
        'weap_knife'     => ['hand_r'],
    ];
    return $M[$id] ?? [];
}
/** 每件装备的属性：护甲、检定代价 */
function crrg_equip_item_stats($id) {
    $M = [
        'gear_hazmat'   => ['armor' => 1, 'why' => '连体防护服'],
        'gear_vest'   => ['armor' => 1, 'why' => '帆布护具'],
        'gear_helmet' => ['armor' => 1, 'why' => '防护头盔', 'penalty' => ['侦查']],
    ];
    return $M[$id] ?? [];
}
/** 取当前部位表（含旧存档迁移） */
function crrg_equip_of(&$pc) {
    $eq = (array) ($pc['equip'] ?? []);
    foreach (crrg_equip_slots() as $sl => $n) if (!isset($eq[$sl])) $eq[$sl] = '';
    /* 只对老存档做一次迁移：靠 equip_init 标记，避免"全空 → 从 worn 复原"的回头路 */
    if (empty($pc['equip_init']) && !array_filter($eq) && !empty($pc['worn'])) {
        foreach ((array) $pc['worn'] as $w) {
            $id = null;
            if ($w === 'T3 防护服' || $w === 'T3防护服') $id = 'gear_hazmat';
            elseif ($w === '帆布护具') $id = 'gear_vest';
            elseif (array_key_exists($w, crrg_equip_item_slots($w))) $id = $w;
            if (!$id) continue;
            foreach (crrg_equip_item_slots($id) as $sl) if (empty($eq[$sl])) $eq[$sl] = $id;
        }
    }
    $pc['equip'] = $eq;
    $pc['equip_init'] = 1;
    /* 反向同步 worn：给旧代码（护甲、UI）看 */
    $worn = [];
    foreach ($eq as $sl => $id) if ($id) { $n = crrg_drill_item_name($id); if (!in_array($n, $worn, true)) $worn[] = $n; }
    $pc['worn'] = $worn;
    return $eq;
}
/** 穿戴 */
function crrg_equip_wear(&$pc, $q) {
    $id = null;
    foreach ((array) $pc['items'] as $it) {
        if (mb_strpos(crrg_drill_item_name($it), $q) !== false || $it === $q) { $id = $it; break; }
    }
    if (!$id) return ['背包里没有「' . $q . '」。'];
    $slots = crrg_equip_item_slots($id);
    $name  = crrg_drill_item_name($id);
    if (!$slots) return ['这东西穿戴不上——不是穿在身上的东西。'];
    $eq = crrg_equip_of($pc);
    foreach ($slots as $sl) {
        if (!empty($eq[$sl])) {
            return [sprintf('%s这一位已经占着%s了。先「脱下 %s」。',
                crrg_equip_slots()[$sl], crrg_drill_item_name($eq[$sl]), crrg_drill_item_name($eq[$sl]))];
        }
    }
    crrg_drill_bag_del_n($pc, $id, 1);
    foreach ($slots as $sl) $eq[$sl] = $id;
    $pc['equip'] = $eq;
    $pc['equip_init'] = 1;
    crrg_equip_of($pc);
    $where = implode('、', array_map(fn($s) => crrg_equip_slots()[$s], $slots));
    $st = crrg_equip_item_stats($id);
    $extra = !empty($st['armor']) ? sprintf('（护甲 +%d）', $st['armor']) : '';
    if (!empty($st['penalty'])) $extra .= '（' . implode('、', $st['penalty']) . '检定有惩罚骰）';
    return [sprintf('你穿上%s，占%s。%s', $name, $where, $extra)];
}
/** 脱下：可以给物品名，也可以给部位名 */
function crrg_equip_take_off(&$pc, $q) {
    $eq = crrg_equip_of($pc);
    $slot = null;
    foreach (crrg_equip_slots() as $sl => $n) if (mb_strpos($n, $q) !== false || mb_strpos($q, $n) !== false) { $slot = $sl; break; }
    if ($slot === null) {
        foreach ($eq as $sl => $id) {
            if ($id && mb_strpos(crrg_drill_item_name($id), $q) !== false) { $slot = $sl; break; }
        }
    }
    if ($slot === null || empty($eq[$slot])) return ['这个部位上没东西可脱。'];
    $id = $eq[$slot];
    foreach ($eq as $sl => $v) if ($v === $id) $eq[$sl] = '';      // 连体的整件脱掉
    $pc['equip'] = $eq;
    $pc['equip_init'] = 1;
    crrg_drill_bag_add_n($pc, $id, 1);
    crrg_equip_of($pc);
    return [sprintf('你脱下%s，收进背包。', crrg_drill_item_name($id))];
}
/** 护甲合计（上限 3） */
function crrg_equip_armor($pc) {
    $eq = crrg_equip_of($pc);
    $a = 0; $seen = [];
    foreach ($eq as $sl => $id) {
        if (!$id || in_array($id, $seen, true)) continue;
        $seen[] = $id;
        $a += (int) (crrg_equip_item_stats($id)['armor'] ?? 0);
    }
    return min(3, $a);
}
/** 装备带来的检定修正 [奖励, 惩罚, 说明] */
function crrg_equip_mod($pc, $skill) {
    $b = 0; $p = 0; $why = [];
    foreach (crrg_equip_of($pc) as $sl => $id) {
        if (!$id) continue;
        foreach ((array) (crrg_equip_item_stats($id)['penalty'] ?? []) as $sk) {
            if ($sk === $skill) { $p++; $why[] = crrg_drill_item_name($id) . '挡着 −1'; }
        }
    }
    return [$b, $p, $why];
}
/** 给 UI 的装备栏文本 */
function crrg_equip_lines($pc) {
    $eq = crrg_equip_of($pc);
    $out = [];
    foreach (crrg_equip_slots() as $sl => $n) {
        $out[] = $n . '：' . (!empty($eq[$sl]) ? crrg_drill_item_name($eq[$sl]) : '—');
    }
    $out[] = '护甲合计：' . crrg_equip_armor($pc);
    return $out;
}
/** 当前主手武器（战斗取值用） */
function crrg_equip_weapon($pc) {
    $eq = crrg_equip_of($pc);
    $w = $eq['hand_r'] ?? '';
    return ($w && !empty(crrg_bt_weapons()[$w])) ? $w : '';
}
