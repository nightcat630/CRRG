<?php
/**
 * 副本模组：NPC 宿舍（2026-10-22，委员长："给所有 NPC 生成各自真的宿舍房子"）
 * ------------------------------------------------------------
 * 规则：
 *   ① 每个基地 NPC 有**一间真房**（`dorm_<id>`，scenes=dorms）——能走进去，不是虚拟状态；
 *   ② 他"在宿舍休息"的时段，`拜访 <名字>` → **直接把你送进他的房间** ✓
 *   ③ 进屋后 `at_dorm = npc:<id>`、`room = dorm_<id>` → 与他**同场景** ✓ → 才能「问」✓
 *   ④ 不在宿舍时段 → 拜访只告诉你他在哪，不传送 ✓
 */
if (!defined('ABSPATH')) exit;

/** 基地 6 人的宿舍布置（id => [名字, 房间描述, 屋里的一件东西]） */
function crrg_dorm_rooms_data() {
    return [
        'kang' => ['康明远', '一床一桌，桌上一盏台灯、一本排班簿。墙上钉着自己画的班次表，用红笔改过三次。', ['n' => '排班簿', 'd' => '最近一页只有一行：夜里两点，起来看表。']],
        'zhou' => ['周德海', '屋里堆着纸箱，按编号码好。床底下塞着一双旧胶鞋，鞋底磨平了。', ['n' => '纸箱', 'd' => '每个箱子上都写着年份，最早那箱写着 1998。']],
        'chen' => ['陈连生', '厨房味很重。窗台上摆着几个腌菜坛子，坛口压着石头。', ['n' => '腌菜坛', 'd' => '坛沿的水是新的——他每天都在添。']],
        'han' => ['韩雪', '桌上两台显示器，线绕得很整。墙角一台旧示波器，屏幕还亮着。', ['n' => '示波器', 'd' => '波形是平的。旁边贴着纸条：别关，等它自己变。']],
        'bai' => ['白淑英', '收拾得最干净的一间。床头挂着听诊器，桌上摊着一本翻旧的手册。', ['n' => '手册', 'd' => '夹着几张便签，字很小：谁该换药、谁该复诊。']],
        'wei' => ['魏长庚', '墙上挂着一排工具，按大小排。地上摆着拆开的装备箱。', ['n' => '工具墙', 'd' => '每件工具的把手上都缠着胶布，写着编号。']],
    ];
}

/** 这个 NPC 现在是不是"在宿舍"（可拜访） */
function crrg_dorm_is_home($id) {
    $at = function_exists('crrg_actor_where') ? (string) crrg_actor_where((string) $id) : '';
    return in_array($at, ['b1_dorm', 'b2f_barracks'], true) || strpos($at, 'dorm_') === 0;
}

/** 他宿舍的房间键 */
function crrg_dorm_room_key($id) { return 'dorm_' . (string) $id; }

/** 拜访：在宿舍时段 → 直接送进去 ✓ 返回 [ok, 提示行] */
function crrg_dorm_enter(&$s, $id) {
    $id = (string) $id;
    if (!crrg_dorm_is_home($id)) return [false, ''];
    $room = crrg_dorm_room_key($id);
    $rooms = function_exists('crrg_drill_rooms') ? (array) crrg_drill_rooms() : [];
    if (!isset($rooms[$room])) return [false, ''];
    $s['room'] = $room;
    $s['at_dorm'] = 'npc:' . $id;
    $nm = $rooms[$room]['t'];
    return [true, '（门开了。' . $nm . '里有人应了一声——你进去了。）'];
}
