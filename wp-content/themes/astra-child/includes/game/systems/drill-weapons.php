<?php
/**
 * 武器类型层（2026-09-28）
 * 口径（委员长定）：
 *   1. **万物皆可为兵**——只要是个实体物件，就能拿来打，只是效率不同。
 *   2. 先用**基础武器类型**归类（徒手／钝器轻／钝器重／刃器轻／刃器重／投掷／零星／枪械），
 *      后续任何新物品都往这些类型里塞；一个物品可以有别的用途，同时也能当某类武器。
 *   3. 遇到实在没有的类型，再新增类型。
 */
if (!defined('ABSPATH')) exit;

/** 基础武器类型 */
function crrg_wtype() {
    return [
        'fist'      => ['n' => '徒手',     'skill' => '格斗', 'dmg' => '1d3',  'range' => 2,  'melee' => true],
        'claw'      => ['n' => '利爪',     'skill' => '格斗', 'dmg' => '1d6',  'range' => 2,  'melee' => true],
        'blunt_l'   => ['n' => '钝器·轻',  'skill' => '格斗', 'dmg' => '1d6',  'range' => 2,  'melee' => true],
        'blunt_h'   => ['n' => '钝器·重',  'skill' => '格斗', 'dmg' => '1d8',  'range' => 2,  'melee' => true, 'need_str' => 50],
        'blade_l'   => ['n' => '刃器·轻',  'skill' => '格斗', 'dmg' => '1d4+2','range' => 2,  'melee' => true, 'impale' => true],
        'blade_h'   => ['n' => '刃器·重',  'skill' => '格斗', 'dmg' => '1d8+1','range' => 2,  'melee' => true, 'need_str' => 60, 'impale' => true],
        'throw'     => ['n' => '投掷物',   'skill' => '投掷', 'dmg' => '1d3',  'range' => 10, 'melee' => false],
        'imp'       => ['n' => '零星物件', 'skill' => '格斗', 'dmg' => '1d3',  'range' => 2,  'melee' => true],
        'imp_soft'  => ['n' => '软性物件', 'skill' => '格斗', 'dmg' => '1d2',  'range' => 2,  'melee' => true],
        'weap_pistol'    => ['n' => '手枪',     'skill' => '手枪',       'dmg' => '1d10', 'range' => 15, 'melee' => false, 'jam' => true],
        'weap_shotgun'      => ['n' => '霰弹枪',   'skill' => '步枪霰弹枪', 'dmg' => '2d6',  'range' => 20, 'melee' => false, 'jam' => true],
        'weap_rifle'     => ['n' => '步枪',     'skill' => '步枪霰弹枪', 'dmg' => '2d6',  'range' => 90, 'melee' => false, 'jam' => true, 'ap' => 1],
        'weap_spear'     => ['n' => '矛／军刺', 'skill' => '格斗', 'dmg' => '1d8+1','range' => 3,  'melee' => true, 'impale' => true, 'need_str' => 45],
    ];
}
/** 物品 → 武器类型（没列到的实体物件一律算「零星物件」） */
function crrg_item_wclass($id) {
    $M = [
        /* 枪械 */
        'weap_pistol' => 'weap_pistol', 'weap_rifle' => 'weap_rifle', 'weap_shotgun' => 'weap_shotgun',
        /* 钝器·重 */
        'tool_shovel' => 'blunt_h', 'tool_steelbar' => 'blunt_h', 'tool_prybar' => 'blunt_h',
        /* 钝器·轻 */
        'weap_baton' => 'blunt_l', 'tool_flashlight' => 'blunt_l', 'tool_lamp' => 'blunt_l', 'tool_thermos' => 'blunt_l',
        'tool_tape' => 'blunt_l', 'gear_helmet' => 'blunt_l',
        /* 刃器·轻 */
        'weap_knife' => 'blade_l', 'steel_knife' => 'blade_l',
        /* 穿刺类 */
        'weap_spear' => 'weap_spear', 'bayonet' => 'weap_spear',
        /* 钢钎算重型钝器，但也能捅：单独给类型 */
        'tool_steelbar' => 'weap_spear',
        /* 投掷物 */
        'tool_stone' => 'throw', 'tool_nails' => 'throw', 'tool_lighter' => 'throw',
        /* 软性物件（打了不疼，但能糊脸） */
        'food_ration' => 'imp_soft', 'can' => 'imp_soft', 'bread' => 'imp_soft', 'cloth' => 'imp_soft',
        'paper' => 'imp_soft', 'doc_photos' => 'imp_soft', 'notepad' => 'imp_soft', 'doc_pencil' => 'imp_soft',
        'smoke_cig' => 'imp_soft', 'food_instant' => 'imp_soft', 'food_eggs' => 'imp_soft',
        /* 零星物件（有分量，砸得疼） */
        'doc_camera' => 'imp', 'doc_recorder' => 'imp', 'tool_mug' => 'imp', 'tool_battery' => 'imp', 'tape_roll' => 'imp',
        'doc_film' => 'imp', 'med_sedative' => 'imp', 'sigil' => 'imp', 'shoebox' => 'imp', 'film_box' => 'imp',
    ];
    if (isset($M[$id])) return $M[$id];
    $items = crrg_drill_items();
    if (!isset($items[$id])) return null;
    if (empty($items[$id]['take'])) return null;          // 拿不起来的（如固定装置）不算武器
    if (!empty($items[$id]['no_weapon'])) return null;    // 明确标注不可当武器
    return 'imp';                                          // 其余实体物件：零星物件
}
/** 全武器表：以物品 id 为键（兼容旧的 pistol/rifle/shot/baton/knife/claw/fist） */
function crrg_bt_weapons_full() {
    $W = [];
    foreach (crrg_wtype() as $cls => $a) {
        $W[$cls] = ['n' => $a['n'], 'skill' => $a['skill'], 'dmg' => $a['dmg'], 'range' => $a['range'],
                    'melee' => $a['melee'], 'cls' => $cls] + $a;
    }
    foreach (crrg_drill_items() as $id => $it) {
        $cls = crrg_item_wclass($id);
        if (!$cls || !isset(crrg_wtype()[$cls])) continue;
        $a = crrg_wtype()[$cls];
        $W[$id] = ['n' => $it['n'], 'skill' => $a['skill'], 'dmg' => $a['dmg'], 'range' => $a['range'],
                   'melee' => $a['melee'], 'cls' => $cls, 'item' => $id, 'need_str' => $a['need_str'] ?? 0,
                   'jam' => !empty($a['jam']), 'impale' => !empty($a['impale']), 'ap' => (int) ($a['ap'] ?? 0)];
    }
    return $W;
}
/** 一个角色身上所有能当武器用的东西（装备栏 + 背包），用于「换武器」列表 */
function crrg_weapons_carried($pc) {
    $out = [];
    $eq  = crrg_equip_of($pc);
    if (!empty($eq['hand_r'])) $out[$eq['hand_r']] = true;
    foreach (crrg_drill_bag_counts($pc) as $id => $n) if ($n > 0) $out[$id] = true;
    $W = crrg_bt_weapons_full();
    $list = [];
    foreach (array_keys($out) as $id) if (isset($W[$id])) $list[$id] = $W[$id];
    return $list;
}
