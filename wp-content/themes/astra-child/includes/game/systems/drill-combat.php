<?php
/**
 * 虚拟演习 · 战斗引擎（CoC 7e + 本组房规）  v2 · 2026-09-27
 *
 * 覆盖：米制坐标／敏捷固定序／近战闪避或反击对抗／战技（撞倒·缴械·擒抱·推开）／
 *      火器按射程判难度／掩体按来袭方位／背后奖励骰／护甲（防护服）／武器切换／
 *      重伤·濒死·昏迷·死亡与每轮体质检定／急救与稳定／魔法框架／性格模板 AI／
 *      场景互动物（推倒·关灯·断电·点燃·开阀）／多人同场（队伍全员）
 *
 * 房规（详见 世界观/03-战斗系统设计.md）：贴身射击 +1；训练场非致命且对手软弹减半；
 *      掩体须在来袭方向 ±60°；抢救成功即「稳定」，此后不再做濒死判定。
 */
if (!defined('ABSPATH')) exit;

const CRRG_BT_OPT = 'crrg_drill_combat';

/* ================= 武器与法术 ================= */
/** 还能行动吗（模拟里的「训练停机」、昏迷、死亡都算出局） */
function crrg_bt_active($a) {
    if (($a['hp'] ?? 0) <= 0) return false;
    foreach (['训练停机', '昏迷', '死亡'] as $s) if (in_array($s, (array) ($a['state'] ?? []), true)) return false;
    return true;
}

function crrg_bt_weapons() {
    if (function_exists('crrg_bt_weapons_full')) {
        $w = crrg_bt_weapons_full();
        if (function_exists('crrg_drill_chase_weapons')) $w = crrg_drill_chase_weapons($w);
        return $w;
    }
    $w = crrg_bt_weapons_base();
    if (function_exists('crrg_drill_chase_weapons')) $w = crrg_drill_chase_weapons($w);
    return $w;
}
function crrg_bt_weapons_base() {
    return [
        'weap_pistol' => ['n' => '手枪',   'skill' => '手枪',       'dmg' => '1d10', 'range' => 15, 'melee' => false],
        'weap_rifle'  => ['n' => '步枪',   'skill' => '步枪霰弹枪', 'dmg' => '2d6',  'range' => 90, 'melee' => false],
        'weap_shotgun'   => ['n' => '霰弹枪', 'skill' => '步枪霰弹枪', 'dmg' => '2d6',  'range' => 20, 'melee' => false],
        'weap_baton'  => ['n' => '警棍',   'skill' => '格斗',       'dmg' => '1d6',  'range' => 2,  'melee' => true],
        'weap_knife'  => ['n' => '匕首',   'skill' => '格斗',       'dmg' => '1d4+2','range' => 2,  'melee' => true],
        'claw'   => ['n' => '爪',     'skill' => '格斗',       'dmg' => '1d6',  'range' => 2,  'melee' => true],
        'fist'   => ['n' => '徒手',   'skill' => '格斗',       'dmg' => '1d3',  'range' => 2,  'melee' => true],
    ];
}
function crrg_bt_persona($id) {
    return [
        'aggressive' => ['n' => '莽撞', 'keep' => 2,  'flee' => 0.0,  'prefer' => 'melee'],
        'cautious'   => ['n' => '谨慎', 'keep' => 6,  'flee' => 0.25, 'prefer' => 'cover'],
        'ranged'     => ['n' => '远程', 'keep' => 12, 'flee' => 0.3,  'prefer' => 'shoot'],
        'coward'     => ['n' => '逃兵', 'keep' => 15, 'flee' => 0.5,  'prefer' => 'shoot'],
        'beast'      => ['n' => '兽性', 'keep' => 1,  'flee' => 0.0,  'prefer' => 'melee'],
    ][$id] ?? ['n' => '普通', 'keep' => 6, 'flee' => 0.25, 'prefer' => 'shoot'];
}
/* 法术表：待编目。字段＝{名称, 魔法消耗, 检定技能, 伤害, 射程} */
function crrg_drill_spells() {
    return [
        'sim_bolt' => ['n' => '模拟术式', 'mp' => 2, 'skill' => '意志', 'dmg' => '1d6', 'range' => 15,
                       'sim' => true, 'd' => '演习场用的拟真术式，不涉及真实秘术。'],
    ];
}
/* ================= 敌人模板库 ================= */
function crrg_bt_npc_library() {
    $base = crrg_bt_npc_library_base();
    if (function_exists('crrg_drill_extra_npcs')) $base += crrg_drill_extra_npcs();
    return $base;
}
function crrg_bt_npc_library_base() {
    return [
        'spar_aggressive' => ['n' => '陪练员·甲', 'persona' => 'aggressive', 'hp' => 11, 'armor' => 0,
            'skills' => ['格斗' => 50, '闪避' => 35, '手枪' => 35], 'weapon' => 'weap_baton'],
        'spar_cautious'   => ['n' => '陪练员·乙', 'persona' => 'cautious', 'hp' => 10, 'armor' => 0,
            'skills' => ['格斗' => 40, '闪避' => 40, '手枪' => 45], 'weapon' => 'weap_pistol'],
        'hound'  => ['n' => '犬形眷属', 'persona' => 'beast', 'hp' => 9, 'armor' => 0,
            'skills' => ['格斗' => 55, '闪避' => 30, '聆听' => 60, '追踪' => 50], 'weapon' => 'claw'],
        'thief'  => ['n' => '盗掘者', 'persona' => 'coward', 'hp' => 11, 'armor' => 0,
            'skills' => ['格斗' => 35, '闪避' => 45, '手枪' => 40, '潜行' => 50], 'weapon' => 'weap_knife'],
        'cultist' => ['n' => '玉罗玄教术士', 'persona' => 'ranged', 'hp' => 12, 'armor' => 0,
            'skills' => ['格斗' => 30, '闪避' => 35, '克苏鲁神话' => 45, '意志' => 60, '手枪' => 30],
            'weapon' => 'weap_knife', 'spell' => 'sim_bolt'],
        'guard'  => ['n' => '武装值守', 'persona' => 'ranged', 'hp' => 12, 'armor' => 1,
            'skills' => ['格斗' => 45, '闪避' => 30, '步枪霰弹枪' => 50], 'weapon' => 'weap_shotgun'],
        'specimen' => ['n' => '收容失效体', 'persona' => 'aggressive', 'hp' => 16, 'armor' => 2,
            'skills' => ['格斗' => 60, '闪避' => 20], 'weapon' => 'claw'],
    ];
}
/** 按模板造对手（用于副本开局） */
function crrg_bt_npc_from($tpl_id, $i, $x, $y, $facing = '西') {
    $L = crrg_bt_npc_library();
    $t = $L[$tpl_id] ?? $L['guard'];
    $T = crrg_bt_persona($t['persona']);
    return ['k' => 'npc', 'n' => $i, 'name' => $t['n'] . ($i > 0 ? '·' . chr(65 + $i) : ''),
            'persona' => $t['persona'], 'hp' => $t['hp'], 'hp_max' => $t['hp'], 'db' => $t['db'] ?? '0',
            'build' => 0, 'skills' => $t['skills'], 'weapon' => $t['weapon'], 'armor' => $t['armor'] ?? 0,
            'state' => [], 'x' => $x, 'y' => $y, 'facing' => $facing, 'spell' => $t['spell'] ?? '',
            'keep' => $T['keep'], 'flee' => $T['flee'], 'prefer' => $T['prefer'],
            'half_fire' => !empty($t['half_fire']),
            'defense' => $t['persona'] === 'aggressive' ? 'fightback' : 'dodge'];
}

/* ================= 场地与道具 ================= */
function crrg_bt_arena($room) {
    $a = ['b1_train' => [14, 10], 'b2f_sim' => [16, 12], 'b2b_hall' => [20, 14],
          'b1_canteen' => [12, 9], 'b3_warehouse' => [24, 14], 'b4_c2' => [8, 26],
          'w_hollow' => [20, 16]];
    [$w, $h] = $a[$room] ?? [12, 9];
    return ['w' => $w, 'h' => $h];
}
function crrg_bt_props($room) {
    $def = [
        'b1_train' => [
            ['t' => '训练掩体·左', 'x' => 4,  'y' => 4,  'cover' => 1, 'hp' => 12, 'type' => 'cover'],
            ['t' => '训练掩体·右', 'x' => 9,  'y' => 6,  'cover' => 1, 'hp' => 12, 'type' => 'cover'],
            ['t' => '器材架',     'x' => 11, 'y' => 2,  'cover' => 2, 'hp' => 10, 'type' => 'push'],
            ['t' => '照明开关',   'x' => 13, 'y' => 9,  'cover' => 0, 'hp' => 6,  'type' => 'tool_lamp'],
            ['t' => '电源闸箱',   'x' => 1,  'y' => 1,  'cover' => 0, 'hp' => 6,  'type' => 'breaker'],
            ['t' => '油桶',       'x' => 6,  'y' => 8,  'cover' => 1, 'hp' => 8,  'type' => 'food_oil'],
            ['t' => '消防栓',     'x' => 2,  'y' => 8,  'cover' => 0, 'hp' => 8,  'type' => 'hydrant'],
        ],
        'b2f_sim' => [
            ['t' => '战术隔板', 'x' => 5,  'y' => 5, 'cover' => 2, 'hp' => 14, 'type' => 'cover'],
            ['t' => '货架',     'x' => 12, 'y' => 8, 'cover' => 1, 'hp' => 10, 'type' => 'push'],
            ['t' => '照明开关', 'x' => 15, 'y' => 11, 'cover' => 0, 'hp' => 6, 'type' => 'tool_lamp'],
        ],
        'w_hollow' => [
            ['t' => '倒木', 'x' => 6,  'y' => 5, 'cover' => 1, 'hp' => 12, 'type' => 'cover'],
            ['t' => '土坎', 'x' => 12, 'y' => 9, 'cover' => 1, 'hp' => 20, 'type' => 'cover'],
        ],
    ];
    return $def[$room] ?? [['t' => '掩体', 'x' => 4, 'y' => 4, 'cover' => 1, 'hp' => 10, 'type' => 'cover']];
}

/* ================= 状态 ================= */
function crrg_bt_get() {
    $c = get_option(CRRG_BT_OPT, null);
    if (!is_array($c)) return null;
    /* 自愈：打完了、或场上没人了，就不要再留着这条记录（否则面板会一直挂着空战斗） */
    $actors = (array) ($c['actors'] ?? []);
    $hasPc  = false; $hasNpc = false;
    foreach ($actors as $k => $a) {
        if (!crrg_bt_active($a)) continue;
        if (($a['k'] ?? '') === 'pc') $hasPc = true;
        if (($a['k'] ?? '') === 'npc') $hasNpc = true;
    }
    /* 2026-09-29 修：原先「没有可行动的对手」或「over」就直接复位 → 玩家看不到结束语、也不知道怎么结束。
       现在：仍保留记录并置 over，让面板显示「模拟结束／复位场地」；只有在没有玩家角色时才自动清理。 */
    if (empty($actors) || !$hasPc) { crrg_bt_clear(); return null; }
    if (!$hasNpc && empty($c['over'])) {
        $c['over'] = true;
        $c['log'][] = '对手全部失去战斗能力——对抗结束。按「复位场地」清场。';
        update_option(CRRG_BT_OPT, $c, false);
    }
    return $c;
    return $c;
}
function crrg_bt_save($c) { update_option(CRRG_BT_OPT, $c, false); }
function crrg_bt_clear() {
    $c = get_option(CRRG_BT_OPT, null);
    if (is_array($c)) {
        $sum = ['room' => $c['room'] ?? '', 'sim' => !empty($c['sim']), 'ts' => time(), 'pc' => []];
        foreach ((array) ($c['actors'] ?? []) as $k => $a) {
            if (strpos($k, 'pc:') !== 0) continue;
            $uid = (int) substr($k, 3);
            $sum['pc'][$uid] = ['hp' => (int) $a['hp'], 'hp_max' => (int) $a['hp_max'],
                                'state' => array_values((array) ($a['state'] ?? [])), 'name' => $a['name'] ?? ''];
        }
        if ($sum['pc']) update_option('crrg_bt_last', $sum, false);
    }
    delete_option(CRRG_BT_OPT);
}

/* ================= 方位 ================= */
function crrg_bt_bearing_deg($from, $to) { return (int) round(rad2deg(atan2($to['y'] - $from['y'], $to['x'] - $from['x']))); }
function crrg_bt_bearing($from, $to) {
    $deg = crrg_bt_bearing_deg($from, $to);
    $names = ['东', '东北', '北', '西北', '西', '西南', '南', '东南'];
    return $names[(int) round(((($deg % 360) + 360) % 360) / 45) % 8];
}
function crrg_bt_relative($from, $to) {
    if (!isset($from['facing'])) return ['方位' => crrg_bt_bearing($from, $to), 'relative' => '正面'];
    $face = ['东' => 0, '东北' => 45, '北' => 90, '西北' => 135, '西' => 180, '西南' => 225, '南' => 270, '东南' => 315];
    $diff = abs(((crrg_bt_bearing_deg($from, $to) - ($face[$from['facing']] ?? 0) + 540) % 360) - 180);
    return ['方位' => crrg_bt_bearing($from, $to),
            'relative' => $diff <= 45 ? '正面' : ($diff <= 135 ? '侧翼' : '背后')];
}
function crrg_bt_dist($a, $b) { return round(sqrt(pow($a['x'] - $b['x'], 2) + pow($a['y'] - $b['y'], 2)), 1); }
/** 位移统一出口：把坐标钳在场地内（否则会跑出地图、从示意图上消失） */
function crrg_bt_clamp(&$a, $room) {
    $ar = crrg_bt_arena($room);
    $a['x'] = max(0, min($ar['w'] - 1, round((float) $a['x'], 1)));
    $a['y'] = max(0, min($ar['h'] - 1, round((float) $a['y'], 1)));
    return $a;
}
function crrg_bt_shift(&$a, $room, $dx, $dy) {
    $a['x'] = (float) $a['x'] + $dx;
    $a['y'] = (float) $a['y'] + $dy;
    return crrg_bt_clamp($a, $room);
}
function crrg_bt_cover($c, $att, $tgt) {
    $best = 0; $name = '';
    foreach ($c['props'] as $p) {
        if (($p['hp'] ?? 1) <= 0 || empty($p['cover'])) continue;
        if (crrg_bt_dist($p, $tgt) > 1.8) continue;
        $diff = abs(((crrg_bt_bearing_deg($tgt, $p) - (crrg_bt_bearing_deg($tgt, $att) + 180) + 540) % 360) - 180);
        if ($diff <= 60 && $p['cover'] > $best) { $best = (int) $p['cover']; $name = $p['t']; }
    }
    return ['cover' => $best, 'name' => $name];
}

/* ================= 参与者 ================= */
function crrg_bt_pc_actor($uid, $x = 2, $y = 4) {
    $pc = crrg_drill_pc_defaults(crrg_drill_pc($uid));
    $pc['attrs'] = array_merge(crrg_coc_default_pc()['attrs'], (array) ($pc['attrs'] ?? []));   // 属性缺项兜底
    $d  = crrg_coc_derive($pc['attrs']);
    $wp = function_exists('crrg_equip_weapon') ? crrg_equip_weapon($pc) : '';
    if (!$wp) { $wp = 'fist'; }
    if ($wp === 'fist' && function_exists('crrg_equip_weapon')) {          // 没装武器就沿用背包里的
        foreach (['weap_pistol', 'weap_rifle', 'weap_shotgun', 'weap_baton'] as $w) if (crrg_drill_bag_count($pc, $w) > 0) { $wp = $w; break; }
    }
    $armor = function_exists('crrg_equip_armor') ? crrg_equip_armor($pc) : 0;
    $hp = (int) $pc['hp'];
    if ($hp <= 0 || $hp > $d['hp_max']) $hp = $d['hp_max'];
    return ['k' => 'pc', 'uid' => (int) $uid, 'name' => $pc['name'] ?: ('#' . $uid),
            'hp' => $hp, 'hp_max' => $d['hp_max'], 'db' => $d['db'], 'build' => $d['build'],
            'skills' => $pc['skills'] ?: crrg_coc_skill_base($pc['attrs']), 'attrs' => $pc['attrs'], 'weapon' => $wp,
            'armor' => $armor, 'state' => [], 'x' => $x, 'y' => $y, 'facing' => '东',
            'mp' => (int) $pc['mp'], 'dex' => (int) ($pc['attrs']['敏捷'] ?? 50), 'defense' => 'dodge'];
}
/** 把同在场地里的玩家都拉进战斗（队伍全员同场） */
function crrg_bt_sync_players(&$c) {
    $added = [];
    foreach (get_users(['fields' => ['ID', 'display_name']]) as $u) {
        if ($u->ID == 0) continue;
        $st = get_user_meta($u->ID, CRRG_DRILL_META, true);
        if (!is_array($st) || ($st['room'] ?? '') !== $c['room']) continue;
        if (time() - (int) ($st['seen'] ?? 0) > 900) continue;   // 15 分钟没动过 → 视为不在场
        $key = 'pc:' . $u->ID;
        if (isset($c['actors'][$key]) || in_array('死亡', (array) ($c['actors'][$key]['state'] ?? []), true)) continue;
        $c['actors'][$key] = crrg_bt_pc_actor($u->ID, 2, 4 + count($added));
        $added[] = $u->display_name;
    }
    if ($added) { $c['log'][] = '加入场次：' . implode('、', $added); $c = crrg_bt_reorder($c); }
    return $added;
}
/* ================= 开局 ================= */
function crrg_bt_start($room, $uid, $scenario = 'sim', $npcs = null) {
    if (crrg_bt_get()) return ['这里已经有战斗在进行。'];
    $ar = crrg_bt_arena($room);
    if ($npcs === null) {
        $npcs = [
            crrg_bt_npc_from('spar_aggressive', 0, $ar['w'] - 2, 3, '西'),
            crrg_bt_npc_from('spar_cautious', 1, $ar['w'] - 3, $ar['h'] - 3, '西'),
        ];
    }
    $actors = [];
    foreach ($npcs as $i => $n) $actors['npc:' . $i] = $n;
    $actors['pc:' . $uid] = crrg_bt_pc_actor($uid, 2, (int) floor($ar['h'] / 2));
    $c = ['id' => 'CBT' . substr((string) time(), -5), 'room' => $room, 'round' => 1, 'turn' => 0,
          'sim' => ($scenario === 'sim'), 'actors' => $actors, 'props' => crrg_bt_props($room),
          'env' => ['light' => '明', 'wet' => false, 'fire' => []], 'over' => false,
          'log' => [($scenario === 'sim' ? '〔对抗模拟开始〕非致命模式：生命最低降到 1，不扣理智。' : '〔遭遇开始〕')]];
    crrg_bt_sync_players($c);
    $c = crrg_bt_reorder($c);
    crrg_bt_save($c);
    return [$scenario === 'sim' ? '对抗模拟已开始。你是 ◎，对手按性格模板行动。'
                                : '遭遇开始。按敏捷固定序行动。'];
}
/** 先攻：敏捷固定序 */
function crrg_bt_reorder($c) {
    $order = [];
    foreach ($c['actors'] as $k => $a) {
        if (!crrg_bt_active($a)) continue;
        $order[] = ['key' => $k, 'dex' => (int) ($a['dex'] ?? 55)];
    }
    usort($order, fn($x, $y) => $y['dex'] <=> $x['dex']);
    $c['order'] = array_column($order, 'key');
    return $c;
}

/* ================= 攻击 ================= */
function crrg_bt_range_label($wkey, $dist) {
    $w = crrg_bt_weapons()[$wkey] ?? crrg_bt_weapons()['fist'];
    if ($w['melee']) return $dist <= 2 ? '近战距离' : '太远（近战需 ≤2m）';
    if ($dist <= $w['range'])     return '正常';
    if ($dist <= $w['range'] * 2) return '困难';
    if ($dist <= $w['range'] * 4) return '极难';
    return '超出射程';
}
function crrg_bt_hurt(&$c, &$T, $dmg, $w, $A = null, $ap = 0) {
    $lines = [];
    $arm = max(0, (int) $T['armor'] - (int) $ap);            // 穿甲先扣护甲
    if ($ap > 0) $lines[] = '穿甲 ' . $ap . '：护甲从 ' . (int) $T['armor'] . ' 降到 ' . $arm . '。';
    $dmg = max(0, $dmg - $arm);
    if ($arm > 0) $lines[] = '护甲吸收 ' . $arm . ' 点。';
    $T['hp'] -= $dmg;
    if ($dmg <= 0) {
        $lines[] = '伤害被护甲吃干净，' . $T['name'] . '没受伤（剩余 ' . max(0, $T['hp']) . '／' . $T['hp_max'] . '）。';
        return $lines;
    }
    $lines[] = sprintf('命中：%s 造成 %d 点伤害（%s 剩余 %d／%d）', $w, $dmg, $T['name'], max(0, $T['hp']), $T['hp_max']);
    $simPC = !empty($c['sim']) && $T['k'] === 'pc';
    if ($dmg >= (int) ceil($T['hp_max'] / 2) && !in_array('重伤', (array) $T['state'], true) && !$simPC) {
        $T['state'][] = '重伤'; $T['state'][] = '倒地';
        $lines[] = '重伤！' . $T['name'] . '倒地，每轮须过体质检定，失败昏迷。';
    }
    if ($T['hp'] <= 0) {
        if (!empty($c['sim'])) {
            if (!in_array('训练停机', (array) $T['state'], true)) $T['state'][] = '训练停机';
            $T['hp'] = 1;
            $lines[] = '（模拟：生命降到 1，' . $T['name'] . '停机。）';
        }
        else { $T['state'][] = '濒死'; $lines[] = '濒死！每轮末体质检定，失败即死亡；急救可稳定。'; }
    }
    return $lines;
}
function crrg_bt_attack(&$c, $akey, $tkey, $kind = 'fire', $maneuver = '') {
    $A = &$c['actors'][$akey]; $T = &$c['actors'][$tkey];
    $lines = [];
    $dist = crrg_bt_dist($A, $T);
    $rel  = crrg_bt_relative($A, $T);
    $w    = crrg_bt_weapons()[$A['weapon']] ?? crrg_bt_weapons()['fist'];
    if ($kind === 'melee' && $dist > 2) return ["距离 {$dist} 米，近战够不着（需 ≤2 米）。"];
    if ($kind === 'fire') {
        $rl = crrg_bt_range_label($A['weapon'], $dist);
        if ($rl === '超出射程') return ["距离 {$dist} 米，超出{$w['n']}射程（{$w['range']} 米）。"];
    }
    $bonus = 0; $penalty = 0; $notes = [];
    if (($rel['relative'] ?? '正面') === '背后') { $bonus++; $notes[] = '背后 +1'; }
    if ($kind === 'fire' && $dist <= 2) { $bonus++; $notes[] = '贴身射击 +1'; }
    if ($kind === 'fire') {
        $rl = crrg_bt_range_label($A['weapon'], $dist);
        if ($rl === '困难') { $penalty++; $notes[] = '远距 困难 −1'; }
        if ($rl === '极难') { $penalty += 2; $notes[] = '远距 极难 −2'; }
    }
    if (empty($c['sim']) && function_exists('crrg_world_mod')) {   // 天气／夜色
        $wil = ($A['k'] === 'pc' && !empty($A['uid'])) ? crrg_drill_pc($A['uid']) : null;
        [$wb, $wp, $ww] = crrg_world_mod($c['room'], $w['skill'], $wil);
        if ($wb) { $bonus += $wb; }
        if ($wp) { $penalty += $wp; }
        foreach ($ww as $t) $notes[] = $t;
    }
    $cv = crrg_bt_cover($c, $A, $T);
    if ($cv['cover'] > 0) { $penalty += $cv['cover']; $notes[] = '掩体（' . $cv['name'] . '）−' . $cv['cover']; }
    if (in_array('倒地', (array) $T['state'], true)) { $bonus++; $notes[] = '目标倒地 +1'; }
    if (in_array('被钉住', (array) $T['state'], true)) { $bonus++; $notes[] = '目标被钉住 +1'; }
    if (($c['env']['light'] ?? '明') === '暗' && !crrg_bt_has_light($A)) { $penalty++; $notes[] = '黑暗 −1'; }
    if (in_array('被擒抱', (array) $T['state'], true) && $kind === 'melee') { $bonus++; $notes[] = '目标被制 +1'; }
    if (in_array('倒地', (array) $A['state'], true) && $kind === 'fire') { $penalty++; $notes[] = '自己倒地 −1'; }

    if ($A['k'] === 'pc' && !empty($A['uid']) && function_exists('crrg_equip_mod')) {
        [$eb, $ep, $ew] = crrg_equip_mod(crrg_drill_pc($A['uid']), $w['skill']);
        if ($eb) $bonus += $eb; if ($ep) { $penalty += $ep; foreach ($ew as $t) $notes[] = $t; }
    }
    $need = (int) ($w['need_str'] ?? 0);
    if ($need > 0) {
        $val = $A['k'] === 'pc' && !empty($A['uid'])
             ? (int) (crrg_drill_pc_defaults(crrg_drill_pc($A['uid']))['attrs']['力量'] ?? 0)
             : (int) (crrg_coc_who_val($A, '力量'));
        if ($val < $need) { $penalty++; $notes[] = '抡不动（力量需 ' . $need . '）−1'; }
    }
    /* 2026-10-31：秘术·借手 —— 近身打人时一次性 +1 奖励骰（用掉即销 ✓） */
    if ($kind === 'melee' && $A['k'] === 'pc' && ($T['k'] ?? 'npc') === 'npc'
        && function_exists('crrg_miracle_borrow_bonus') && (int) crrg_miracle_borrow_bonus($A['uid'] ?? 0)) {
        $bonus++; $notes[] = '借手 +1';
    }
    $sk = $A['skills'][$w['skill']] ?? 25;
    $r  = crrg_coc_check($w['skill'], (int) $sk, $bonus, $penalty);
    $isCrit = ($r['level'] === 'crit');
    $lines[] = $r['text'] . ($notes ? '　［' . implode('，', $notes) . '］' : '');
    $lines[] = sprintf('距离 %s 米，方位 %s（%s）', $dist, $rel['方位'], $rel['relative']);
    if ($kind === 'fire' && $r['level'] === 'fumble') {
        $A['state'][] = '卡壳';
        $lines[] = '大失败：卡壳，本轮起不能再用这把枪（可换武器）。';
        return $lines;
    }
    if (crrg_coc_level_rank($r['level']) < 2) { $lines[] = '没打中。'; return $lines; }

    if ($kind === 'melee' && !$isCrit) {
        // 防守方选择闪避或反击；平级归属于谁，见 crrg_bt_defense_block()
        // （大成功不在此列：这一下无法防守，直接结算伤害）
        $fb = ($T['defense'] ?? 'dodge') === 'fightback';
        $dsk = $fb ? '格斗' : '闪避';
        $rd  = crrg_coc_check($dsk, (int) ($T['skills'][$dsk] ?? 25));
        $lines[] = '对方' . ($fb ? '反击' : '闪避') . '：' . $rd['text'];
        $same = crrg_coc_level_rank($rd['level']) === crrg_coc_level_rank($r['level']);
        if ($fb && crrg_coc_level_rank($rd['level']) > crrg_coc_level_rank($r['level'])) {
            $lines[] = $T['name'] . '反手打回来——';
            $tw = crrg_bt_weapons()[$T['weapon']];
            $tvs = (int) ($T['skills'][$tw['skill']] ?? 25);
            $rr = crrg_coc_check($tw['skill'], $tvs);
            $lines[] = '（反击）' . $rr['text'];
            if (crrg_coc_level_rank($rr['level']) >= 2) {
                $dmg = crrg_coc_dice($tw['dmg']) + ($T['db'] === '1D4' ? crrg_coc_dice('1d4') : 0);
                if (!empty($c['sim']) && $T['k'] === 'npc' && $A['k'] === 'pc') $dmg = max(1, (int) ceil($dmg / 2));
                $lines = array_merge($lines, crrg_bt_hurt($c, $A, $dmg, $tw['n'], $T));
            }
            return $lines;
        }
        if (crrg_bt_defense_block($r['level'], $rd['level'], $fb)) {
            $lines[] = $fb ? '反击失败，你这一下还是打实了。' : '被挡住了／躲开了。';
            if ($fb) $lines[] = '（反击须高于攻击方等级，平级算攻击方得手。）';
            else     $lines[] = '（闪避平级即可挡下。）';
            if (!$fb) return $lines;
        }
    }
    /* 贯穿与要害：穿刺武器极难成功＝贯穿；大成功＝命中要害（伤害取满，且不走闪避） */
    $imp = !empty($w['impale']) || !empty($w['cls'] === 'weap_spear');
    $dbMax = ($A['db'] === '1D4') ? 4 : 0;
    if ($isCrit) {
        $maxv = crrg_coc_max_of($w['dmg']);
        $dmg  = $maxv * ($imp ? 2 : 1) + $dbMax;
        $lines[] = sprintf('*大成功*：%s 打中要害，伤害取满 %d%s。', $w['n'], $maxv, $imp ? '（×2，穿刺）' : '');
        if (!empty($w['ap'])) { $w['ap'] = (int) $w['ap'] + 1; }
    } elseif ($imp && $r['level'] === 'extreme') {
        $maxv = crrg_coc_max_of($w['dmg']);
        $extraRoll = crrg_coc_dice($w['dmg']);
        $dmg = $maxv + $extraRoll + ($A['db'] === '1D4' ? crrg_coc_dice('1d4') : 0);
        $lines[] = sprintf('*贯穿*：%s 取满 %d ＋ 再掷 %d（%s）。', $w['n'], $maxv, $extraRoll, $w['dmg']);
        if (!in_array('被钉住', (array) $T['state'], true)) {
            $T['state'][] = '被钉住';
            $T['pinned'] = 1;
            $lines[] = $T['name'] . '被钉住了（这一轮动不了，挨打更容易）。';
        }
    } else {
        $dmg = crrg_coc_dice($w['dmg']) + ($A['db'] === '1D4' ? crrg_coc_dice('1d4') : 0);
    }
    /* 生物特性：枪械伤害减半 */
    if (!empty($T['half_fire']) && in_array($w['cls'] ?? '', ['weap_pistol', 'weap_shotgun', 'weap_rifle'], true)) {
        $dmg = max(1, (int) floor($dmg / 2));
        $lines[] = '（它对枪弹不敏感：枪械伤害减半）';
    }
    if (!empty($c['sim']) && $A['k'] === 'npc' && $T['k'] === 'pc') $dmg = max(1, (int) ceil($dmg / 2));
    return array_merge($lines, crrg_bt_hurt($c, $T, $dmg, $w['n'], $A, (int) ($w['ap'] ?? 0)));
}
/** 防守判定（照搬 CoC 7e）：
 *  闪避——等级 ≥ 攻击方 即挡下（平级防守方赢）
 *  反击——等级必须 > 攻击方 才能反打；平级由攻击方得手 */
function crrg_bt_defense_block($levelA, $levelD, $fightback) {
    $a = crrg_coc_level_rank($levelA);
    $d = crrg_coc_level_rank($levelD);
    return $fightback ? ($d > $a) : ($d >= $a);
}
/** 取骰式的最大可能值（供贯穿用） */
function crrg_coc_max_of($expr) {
    $n = 0;
    foreach (preg_split('/\s*\+\s*/', (string) $expr) as $part) {
        if (preg_match('/^(\d+)d(\d+)$/i', $part, $m)) $n += (int) $m[1] * (int) $m[2];
        elseif (preg_match('/^\d+$/', $part)) $n += (int) $part;
    }
    return $n;
}
function crrg_bt_has_light($a) {
    if ($a['k'] === 'npc') return !empty($a['light']);
    $pc = crrg_drill_pc($a['uid']);
    foreach (array_merge((array) ($pc['items'] ?? []), (array) ($pc['worn'] ?? [])) as $i) {
        if (in_array($i, ['tool_lamp', 'tool_flashlight'], true)) return true;
    }
    return false;
}

/* ================= 战技 ================= */
function crrg_bt_maneuver(&$c, $akey, $tkey, $kind = '撞倒') {
    $A = &$c['actors'][$akey]; $T = &$c['actors'][$tkey];
    $lines = ['战技·' . $kind . '：格斗对抗。'];
    if (crrg_bt_dist($A, $T) > 2) return ['距离 ' . crrg_bt_dist($A, $T) . ' 米，够不着（战技需 ≤2 米）。'];
    $ra = crrg_coc_check('格斗', (int) ($A['skills']['格斗'] ?? 25));
    $useFb = ($T['defense'] ?? 'dodge') === 'fightback';
    $rt = crrg_coc_check($useFb ? '格斗' : '闪避', (int) ($T['skills'][$useFb ? '格斗' : '闪避'] ?? 25));
    $lines[] = $ra['text']; $lines[] = '对方' . ($useFb ? '反击' : '闪避') . '：' . $rt['text'];
    $sameLv = crrg_coc_level_rank($ra['level']) === crrg_coc_level_rank($rt['level']);
    if (crrg_bt_defense_block($ra['level'], $rt['level'], $useFb)) {
        $lines[] = '没得手（' . ($useFb ? '对方反击更高' : '对方闪避不差于你') . '）。';
        return $lines;
    }
    if ($useFb && $sameLv) $lines[] = '（平级：反击须更高，战技由发起方得手。）';
    switch ($kind) {
        case '缴械':
            $T['weapon'] = 'fist';
            $lines[] = $T['name'] . '的武器被打脱手（改用徒手）。';
            break;
        case '擒抱':
            $T['state'][] = '被擒抱'; $A['state'][] = '擒抱中';
            $lines[] = $T['name'] . '被制住：移动受限，被打时 +1 奖励骰。';
            break;
        case '推开':
            $deg = crrg_bt_bearing_deg($A, $T);
            crrg_bt_shift($T, $c['room'], round(cos(deg2rad($deg)) * 3, 1), round(sin(deg2rad($deg)) * 3, 1));
            $lines[] = $T['name'] . '被推出去 3 米。';
            break;
        default:
            $T['state'][] = '倒地';
            $lines[] = $T['name'] . '被撞倒（倒地：被打 +1）。';
    }
    return $lines;
}

/* ================= 急救与稳定 ================= */
function crrg_bt_firstaid(&$c, $akey, $tkey, $uid) {
    $A = &$c['actors'][$akey]; $T = &$c['actors'][$tkey];
    if (crrg_bt_dist($A, $T) > 2) return ['距离太远，先靠近（≤2 米）再做急救。'];
    if (!empty($T['firstaid_done'])) return [$T['name'] . '已经处理过了。'];
    $sk = max((int) ($A['skills']['急救'] ?? 30), (int) ($A['skills']['医学'] ?? 0));
    $name = ((int) ($A['skills']['医学'] ?? 0) > (int) ($A['skills']['急救'] ?? 0)) ? '医学' : '急救';
    $r = crrg_coc_check($name, $sk);
    $lines = ['急救' . $T['name'] . '：' . $r['text']];
    if ($r['level'] === 'fumble') {
        $T['hp'] = max(0, $T['hp'] - 1);
        $lines[] = '大失败：处理不当，' . $T['name'] . '再掉 1 点生命。';
        return $lines;
    }
    if (crrg_coc_level_rank($r['level']) >= 2) {
        $T['firstaid_done'] = true;
        $T['state'] = array_values(array_diff((array) $T['state'], ['濒死']));
        $T['state'][] = '稳定';
        $gain = 1;
        $T['hp'] = min((int) $T['hp_max'], (int) $T['hp'] + $gain);
        if (!empty($c['sim'])) $T['hp'] = max(1, (int) $T['hp']);
        $lines[] = $T['name'] . '已稳定（不再做濒死判定），生命 +' . $gain . ' → ' . $T['hp'] . '。';
    } else {
        $lines[] = '没处理好，伤情照旧。';
    }
    return $lines;
}

/* ================= 场景互动物 ================= */
function crrg_bt_prop_act(&$c, $akey, $pidx) {
    $A = &$c['actors'][$akey];
    $p = &$c['props'][$pidx];
    if (!$p) return ['没有这件东西。'];
    $d = crrg_bt_dist($A, $p);
    if (($p['hp'] ?? 1) <= 0 && $p['type'] !== 'tool_lamp' && $p['type'] !== 'breaker') return [$p['t'] . '已经毁了。'];
    if ($d > 4) return ['离' . $p['t'] . '还有 ' . $d . ' 米，得先靠近（≤4 米）。'];
    $lines = [];
    switch ($p['type']) {
        case 'push':
            $r = crrg_coc_check('力量', max(1, crrg_coc_who_val($A, '力量')));
            $lines[] = '推倒' . $p['t'] . '：' . $r['text'];
            if (crrg_coc_level_rank($r['level']) >= 2) {
                $p['hp'] = 0; $p['cover'] = 0;
                foreach ($c['actors'] as $k => &$t) {
                    if (crrg_bt_dist($t, $p) <= 1.6) {
                        $lines[] = $t['name'] . '被砸中——';
                        $lines = array_merge($lines, crrg_bt_hurt($c, $t, crrg_coc_dice('1d6'), $p['t'] . '倒下'));
                        if (!in_array('倒地', (array) $t['state'], true)) { $t['state'][] = '倒地'; $lines[] = $t['name'] . '倒地。'; }
                    }
                }
            } else { $lines[] = '没推动。'; }
            break;
        case 'tool_lamp':
            $c['env']['light'] = (($c['env']['light'] ?? '明') === '明') ? '暗' : '明';
            $lines[] = '你按了' . $p['t'] . '：场地照明 → ' . $c['env']['light'] . '。';
            if ($c['env']['light'] === '暗') $lines[] = '（黑暗：无光源者所有攻击 −1 惩罚骰。）';
            break;
        case 'breaker':
            $lines[] = '你拉下电闸：照明断电，电子设备停摆。';
            $c['env']['light'] = '暗';
            $c['env']['power_off'] = true;
            break;
        case 'food_oil':
            $c['env']['fire'][] = ['x' => $p['x'], 'y' => $p['y'], 'rounds' => 3];
            $p['hp'] = 0;
            $lines[] = '油桶被点着：火焰区（半径 2 米）持续 3 轮，每轮 2D6。';
            break;
        case 'hydrant':
            foreach ((array) $c['env']['fire'] as $k => $f) if (crrg_bt_dist($f, $p) <= 6) unset($c['env']['fire'][$k]);
            $c['env']['wet'] = true;
            $lines[] = '你开了消防栓：火焰熄灭，地面湿滑（跑动需敏捷检定，失败摔倒）。';
            break;
        default:
            $lines[] = $p['t'] . '没什么可操作的。';
    }
    return $lines;
}

/* ================= NPC 回合 ================= */
function crrg_bt_npc_turn(&$c, $nkey) {
    $N = &$c['actors'][$nkey];
    $lines = ['— ' . $N['name'] . '（' . crrg_bt_persona($N['persona'] ?? 'cautious')['n'] . '）行动 —'];
    $bestKey = null; $best = 1e9;
    foreach ($c['actors'] as $k => $a) {
        if ($a['k'] !== 'pc' || ($a['hp'] ?? 0) <= 0) continue;
        $d = crrg_bt_dist($N, $a);
        if ($d < $best) { $best = $d; $bestKey = $k; }
    }
    if (!$bestKey) { $lines[] = '没有可以攻击的目标。'; return $lines; }
    $ratio = $N['hp_max'] > 0 ? $N['hp'] / $N['hp_max'] : 1;
    if ($N['flee'] > 0 && $ratio <= $N['flee']) { $lines[] = $N['name'] . '退了：伤重脱离接触。'; unset($c['actors'][$nkey]); return $lines; }
    $dist = crrg_bt_dist($N, $c['actors'][$bestKey]);
    $w = crrg_bt_weapons()[$N['weapon']];
    if ($N['prefer'] === 'cover' && !in_array('掩体', $N['state'], true)) {
        $N['state'][] = '掩体'; $lines[] = $N['name'] . '就近靠到掩体后（被打 −1）。'; return $lines;
    }
    if (!empty($N['spell']) && $dist <= 15 && $dist > 6 && mt_rand(1, 100) <= 45) {
        $sp = crrg_drill_spells()[$N['spell']] ?? null;
        if ($sp) {
            $r = crrg_coc_check($sp['skill'], max(1, crrg_coc_who_val($N, $sp['skill'])));
            $lines[] = $N['name'] . '念咒：' . $r['text'];
            if (crrg_coc_level_rank($r['level']) >= 2) {
                $lines = array_merge($lines, crrg_bt_hurt($c, $c['actors'][$bestKey], crrg_coc_dice($sp['dmg']), $sp['n']));
            } else { $lines[] = '术式没成形。'; }
            return $lines;
        }
    }
    if ($w['melee'] && $dist > 2) {
        $step = min(3, $dist - 2);
        $deg = crrg_bt_bearing_deg($N, $c['actors'][$bestKey]);
        crrg_bt_shift($N, $c['room'], round(cos(deg2rad($deg)) * $step, 1), round(sin(deg2rad($deg)) * $step, 1));
        $lines[] = sprintf('%s推进 %s 米，距离缩到 %s 米。', $N['name'], $step, crrg_bt_dist($N, $c['actors'][$bestKey]));
        return $lines;
    }
    if (!$w['melee'] && $dist < $N['keep'] - 2) {
        $deg = crrg_bt_bearing_deg($N, $c['actors'][$bestKey]) + 180;
        crrg_bt_shift($N, $c['room'], round(cos(deg2rad($deg)) * 2, 1), round(sin(deg2rad($deg)) * 2, 1));
        $lines[] = sprintf('%s后撤到 %s 米，保持射距。', $N['name'], crrg_bt_dist($N, $c['actors'][$bestKey]));
    }
    if (in_array('卡壳', (array) $N['state'], true)) { $N['weapon'] = 'weap_baton'; $N['state'] = array_values(array_diff($N['state'], ['卡壳'])); $lines[] = $N['name'] . '换了根棍子。'; }
    if ($w['melee'] && mt_rand(1, 100) <= 30) {
        $lines = array_merge($lines, crrg_bt_maneuver($c, $nkey, $bestKey, ['撞倒', '擒抱', '推开'][mt_rand(0, 2)]));
    } else {
        $lines = array_merge($lines, crrg_bt_attack($c, $nkey, $bestKey, $w['melee'] ? 'melee' : 'fire'));
    }
    return $lines;
}

/* ================= 回合推进 ================= */
function crrg_bt_advance(&$c) {
    $lines = []; $n = count($c['order']);
    for ($guard = 0; $guard < 60; $guard++) {
        $standing = array_filter($c['actors'], fn($a) => $a['k'] === 'pc' && ($a['hp'] ?? 0) > 0
            && !array_intersect(['昏迷', '死亡'], (array) $a['state']));
        if (!$standing) {
            $c['over'] = true;
            $lines[] = '场上队员全部失去意识，本轮中止（「结束／撤退」离场，或等人来救）。';
            return $lines;
        }
        $c['turn'] = (int) $c['turn'] + 1;
        if ($c['turn'] >= $n) { $c['turn'] = 0; $c['round']++; $lines[] = '── 第 ' . $c['round'] . ' 轮 ──'; $lines = array_merge($lines, crrg_bt_round_end($c)); }
        $key = $c['order'][$c['turn']] ?? null;
        if (!$key || !isset($c['actors'][$key]) || ($c['actors'][$key]['hp'] ?? 0) <= 0) continue;
        if (in_array('昏迷', (array) $c['actors'][$key]['state'], true)) continue;
        if (!empty($c['actors'][$key]['pinned'])) {                  // 被钉住：这一轮动不了
            $c['actors'][$key]['pinned'] = 0;
            $c['actors'][$key]['state'] = array_values(array_diff((array) $c['actors'][$key]['state'], ['被钉住']));
            $lines[] = $c['actors'][$key]['name'] . '把自己从钉住的地方挣脱出来。';
        }
        if ((int) ($c['actors'][$key]['stun'] ?? 0) > 0) {           // 昏睡：跳过行动
            $c['actors'][$key]['stun'] = (int) $c['actors'][$key]['stun'] - 1;
            $lines[] = $c['actors'][$key]['name'] . '还昏着（剩 ' . $c['actors'][$key]['stun'] . ' 轮）。';
            if ((int) $c['actors'][$key]['stun'] <= 0) {
                $c['actors'][$key]['state'] = array_values(array_diff((array) $c['actors'][$key]['state'], ['昏睡']));
                $lines[] = $c['actors'][$key]['name'] . '醒了。';
            }
            continue;
        }
        if ($c['actors'][$key]['k'] === 'npc') { $lines = array_merge($lines, crrg_bt_npc_turn($c, $key)); continue; }
        return $lines;
    }
    return $lines;
}
function crrg_bt_round_end(&$c) {
    $out = [];
    foreach ($c['actors'] as $k => &$a) {
        if (!crrg_bt_active($a)) continue;
        $con = $a['k'] === 'pc' ? max(1, crrg_coc_who_val(crrg_bt_pc_actor($a['uid']), '体质')) : 55;
        if (in_array('重伤', (array) $a['state'], true) && !in_array('稳定', (array) $a['state'], true)) {
            $r = crrg_coc_check('体质', $con);
            $out[] = $a['name'] . '（重伤）' . $r['text'];
            if (crrg_coc_level_rank($r['level']) < 2 && !in_array('昏迷', (array) $a['state'], true)) { $a['state'][] = '昏迷'; $out[] = $a['name'] . '昏迷了。'; }
        }
        if (in_array('濒死', (array) $a['state'], true) && !in_array('稳定', (array) $a['state'], true)) {
            $r = crrg_coc_check('体质', $con);
            $out[] = $a['name'] . '（濒死）' . $r['text'];
            if (crrg_coc_level_rank($r['level']) < 2) { $a['hp'] = 0; $a['state'][] = '死亡'; $out[] = $a['name'] . '没能撑住。'; }
        }
    }
    foreach ((array) ($c['env']['fire'] ?? []) as $i => &$f) {
        foreach ($c['actors'] as $k => &$a) {
            if (crrg_bt_dist($f, $a) > 2) continue;
            $out[] = $a['name'] . '在火里——';
            $out = array_merge($out, crrg_bt_hurt($c, $a, crrg_coc_dice('2d6'), '火焰'));
        }
        $f['rounds'] = (int) $f['rounds'] - 1;
        if ($f['rounds'] <= 0) { unset($c['env']['fire'][$i]); $out[] = '火焰熄了。'; }
    }
    return $out;
}

/* ================= 视图与面板 ================= */
function crrg_bt_map($c, $uid) {
    $ar = crrg_bt_arena($c['room']);
    $W = (int) $ar['w']; $H = (int) $ar['h'];
    $g = array_fill(0, $H, array_fill(0, $W, '·'));
    foreach ($c['props'] as $p) {
        if (($p['hp'] ?? 1) <= 0 && !in_array($p['type'], ['tool_lamp', 'breaker'], true)) continue;
        $x = max(0, min($W - 1, (int) round($p['x']))); $y = max(0, min($H - 1, (int) round($p['y'])));
        $ch = ['tool_lamp' => '◉', 'breaker' => '⌁', 'food_oil' => '⛽', 'hydrant' => '≋'][$p['type']] ?? ($p['cover'] >= 2 ? '▩' : '▢');
        $g[$H - 1 - $y][$x] = $ch;
    }
    foreach ((array) ($c['env']['fire'] ?? []) as $f) {
        $x = max(0, min($W - 1, (int) round($f['x']))); $y = max(0, min($H - 1, (int) round($f['y'])));
        $g[$H - 1 - $y][$x] = '炎';
    }
    $letters = ['A', 'B', 'C', 'D', 'E']; $i = 0;
    foreach ($c['actors'] as $k => $a) {
        $x = max(0, min($W - 1, (int) round($a['x']))); $y = max(0, min($H - 1, (int) round($a['y'])));
        if ($a['k'] === 'pc') { $g[$H - 1 - $y][$x] = ((int) $a['uid'] === (int) $uid) ? '◎' : '◍'; continue; }
        $g[$H - 1 - $y][$x] = $letters[$i] ?? '?'; $i++;
    }
    /* 横轴：每 5 米一个刻度，每 10 米标米数 */
    $ticks = []; $labs = [];
    for ($x = 0; $x < $W; $x++) { $ticks[$x] = ($x % 5 === 0) ? '┊' : '　'; $labs[$x] = '　'; }
    for ($m = 0; $m < $W; $m += 10) {
        $t = (string) $m;
        for ($i = 0, $L = strlen($t); $i < $L; $i++) if ($m + $i < $W) $labs[$m + $i] = $t[$i];
    }
    $out = [];
    $out[] = '　　　' . implode('', $ticks);
    $out[] = '　　　' . implode('', $labs) . '　← 东西（米）';
    foreach ($g as $ry => $row) {
        $yy = $H - 1 - $ry;
        $out[] = sprintf('%4d　', $yy) . implode('', $row) . '　│';
    }
    $out[] = '　　　' . implode('', $ticks);
    $out[] = '每格 1 米；左边数字＝距南（米），上边＝距西（米）。◎你　◍队友　A/B 对手　▢半掩体　▩全掩体　◉灯　⌁电闸　⛽油桶　≋消防栓　炎火场';
    $out[] = '环境：照明 ' . ($c['env']['light'] ?? '明') . (($c['env']['wet'] ?? false) ? '　地面湿滑' : '') . '　火场 ' . count((array) ($c['env']['fire'] ?? [])) . ' 处';
    return $out;
}
/* 战术图：结构化格子（前端用 CSS 网格渲染，不依赖字符宽度） */
function crrg_bt_grid($c, $uid) {
    $ar = crrg_bt_arena($c['room']);
    $marks = [];
    foreach ($c['props'] as $pi => $p) {
        if (($p['hp'] ?? 1) <= 0 && !in_array($p['type'], ['tool_lamp', 'breaker'], true)) continue;
        $cls = ['tool_lamp' => 'tool_lamp', 'breaker' => 'breaker', 'food_oil' => 'food_oil', 'hydrant' => 'hydrant'][$p['type']]
             ?? ($p['cover'] >= 2 ? 'cover2' : 'cover');
        $marks[] = ['x' => (int) round($p['x']), 'y' => (int) round($p['y']), 'cls' => $cls,
                    'k' => 'prop', 'id' => 'p' . $pi, 'lab' => '', 't' => $p['t']];
    }
    foreach ((array) ($c['env']['fire'] ?? []) as $fi => $f) {
        $marks[] = ['x' => (int) round($f['x']), 'y' => (int) round($f['y']), 'cls' => 'fire',
                    'k' => 'fire', 'id' => 'f' . $fi, 'lab' => '', 't' => '火场'];
    }
    $letters = ['A', 'B', 'C', 'D', 'E']; $i = 0;
    foreach ($c['actors'] as $k => $a) {
        $isMe   = $a['k'] === 'pc' && (int) $a['uid'] === (int) $uid;
        $isAlly = $a['k'] === 'pc' && !$isMe;
        $cls = $isMe ? 'me' : ($isAlly ? 'ally' : 'npc');
        $lab = $isMe ? '你' : ($isAlly ? mb_substr($a['name'], 0, 1) : ($letters[$i++] ?? '?'));
        $marks[] = ['x' => (int) round($a['x']), 'y' => (int) round($a['y']), 'cls' => $cls, 'k' => 'actor',
                    'id' => $k, 'lab' => $lab,
                    't' => $a['name'] . '　' . max(0, $a['hp']) . '/' . $a['hp_max']
                         . ($a['state'] ? '　[' . implode('、', array_unique($a['state'])) . ']' : '')];
    }
    /* 绘图侧兜底：越界坐标钳回场内；同一格的多个标记合并（避免互相盖住） */
    $W = (int) $ar['w']; $H = (int) $ar['h'];
    $cells = [];
    foreach ($marks as $mk) {
        $x = max(0, min($W - 1, (int) $mk['x']));
        $y = max(0, min($H - 1, (int) $mk['y']));
        $mk['x'] = $x; $mk['y'] = $y;
        $cells[$x . ',' . $y][] = $mk;
    }
    $marks = [];
    foreach ($cells as $list) {
        $acts = array_values(array_filter($list, fn($m) => $m['k'] === 'actor'));
        if ($acts) {
            $prio = ['me' => 3, 'ally' => 2, 'npc' => 1];
            usort($acts, fn($p, $q) => ($prio[$p['cls']] ?? 0) <=> ($prio[$q['cls']] ?? 0));   // 自己画在最上面
            $top = $acts[count($acts) - 1];
            $tip = implode(' ／ ', array_map(fn($m) => $m['t'], $acts));
            $others = array_filter($list, fn($m) => $m['k'] !== 'actor');
            if ($others) $tip .= '　（同格还有：' . implode('、', array_map(fn($m) => $m['t'], $others)) . '）';
            $top['t'] = $tip;
            if (count($acts) > 1) $top['lab'] = ($top['lab'] ?: '•') . '＋';
            $marks[] = $top;
            continue;
        }
        $top = $list[0];
        if (count($list) > 1) $top['t'] = implode('、', array_map(fn($m) => $m['t'], $list));
        $marks[] = $top;
    }
    return ['w' => $W, 'h' => $H, 'marks' => $marks];
}

function crrg_bt_view($room, $uid) {
    $c = crrg_bt_get();
    if (!$c || $c['room'] !== $room) return null;
    $me = 'pc:' . $uid;
    if (!isset($c['actors'][$me])) { crrg_bt_sync_players($c); crrg_bt_save($c); }
    $mine = $c['actors'][$me] ?? null;
    $items = []; $list = [];
    foreach ($c['actors'] as $k => $a) {
        if ($k === $me) continue;
        $rel = crrg_bt_relative($mine ?? $a, $a);
        $cv  = crrg_bt_cover($c, $mine ?? $a, $a);
        $list[] = sprintf('%s%s　%s 米　%s%s　掩体 %s　%d/%d%s',
            $k === 'pc:' . ($a['uid'] ?? 0) && $a['k'] === 'pc' ? '◍' : '',
            $a['name'], crrg_bt_dist($mine ?? $a, $a),
            $rel['方位'], $rel['relative'] === '正面' ? '' : '（' . $rel['relative'] . '）',
            $cv['cover'] ? '有' : '无', max(0, $a['hp']), $a['hp_max'],
            $a['state'] ? '　[' . implode('、', array_unique($a['state'])) . ']' : '');
    }
    /* 目标焦点 */
    $targets = [];
    foreach ($c['actors'] as $k => $a) {
        if ($k === $me || ($a['hp'] ?? 0) <= 0 || in_array('死亡', (array) $a['state'], true)) continue;
        $targets[$k] = $a;
    }
    $focus = $mine['focus'] ?? '';
    if (!isset($targets[$focus]) && $focus !== 'self') $focus = array_key_first($targets) ?: 'self';

    $g = [];
    /* 组 0：目标 */
    $row = [];
    $letters = ['A', 'B', 'C', 'D', 'E']; $i = 0;
    foreach ($targets as $k => $a) {
        $tag = $letters[$i] ?? '?'; $i++;
        $mark = '';
    foreach (['训练停机' => '（停机）', '昏迷' => '（昏迷）', '死亡' => '（阵亡）', '倒地' => '（倒地）'] as $st => $tx)
        if (in_array($st, (array) ($a['state'] ?? []), true)) { $mark = $tx; break; }
    $row[] = ['label' => $tag . ' ' . $a['name'] . ' ' . max(0, $a['hp']) . '/' . $a['hp_max'] . $mark,
                  'k' => 'bt_focus', 'id' => $k, 'on' => ($focus === $k)];
    }
    $row[] = ['label' => '自己', 'k' => 'bt_focus', 'id' => 'self', 'on' => ($focus === 'self')];
    $g[] = ['title' => '目标', 'zone' => 'actions', 'items' => $row];

    /* 组 1：对当前目标的动作 */
    $acts = [];
    if ($focus === 'self') {
        if (empty($mine['firstaid_done'])) $acts[] = ['label' => '急救自己', 'k' => 'bt_firstaid', 'id' => $me];
    } elseif (isset($targets[$focus])) {
        $tgt = $targets[$focus];
        $w = crrg_bt_weapons()[$mine['weapon']] ?? crrg_bt_weapons()['fist'];
        $dist = crrg_bt_dist($mine, $tgt);
        $acts[] = ['label' => '射击（' . $w['n'] . '）', 'k' => 'bt_fire', 'id' => $focus,
                   'off' => (crrg_bt_range_label($mine['weapon'], $dist) === '超出射程')];
        $acts[] = ['label' => '近战', 'k' => 'bt_melee', 'id' => $focus, 'off' => ($dist > 2)];
        foreach (['撞倒', '缴械', '擒抱', '推开'] as $m) $acts[] = ['label' => $m, 'k' => 'bt_maneuver', 'id' => $focus . '|' . $m, 'off' => ($dist > 2)];
        $acts[] = ['label' => '施法', 'k' => 'bt_cast', 'id' => $focus];
        if (empty($tgt['firstaid_done'])) $acts[] = ['label' => '急救', 'k' => 'bt_firstaid', 'id' => $focus];
    }
    $g[] = ['title' => '动作' . ($focus === 'self' ? '（自己）' : ''), 'zone' => 'actions', 'items' => $acts];

    /* 组 2：移动（方向 ＋ 可调步幅） */
    $stride = (int) ($mine['stride'] ?? 3);
    $move = [['label' => '步幅 ' . $stride . ' 米', 'k' => 'bt_stride', 'id' => 'cycle', 'on' => true]];
    foreach (['北', '东北', '东', '东南', '南', '西南', '西', '西北'] as $d) $move[] = ['label' => $d, 'k' => 'bt_move', 'id' => $d];
    $move[] = ['label' => '贴掩体', 'k' => 'bt_cover', 'id' => ''];
    $g[] = ['title' => '移动', 'zone' => 'tools', 'items' => $move];

    /* 组 3：姿态与武器（武器全部列出，未持有的置灰） */
    $stance = [['label' => '闪避', 'k' => 'bt_defense', 'id' => 'dodge', 'on' => (($mine['defense'] ?? 'dodge') === 'dodge')],
               ['label' => '反击', 'k' => 'bt_defense', 'id' => 'fightback', 'on' => (($mine['defense'] ?? '') === 'fightback')]];
    $pc_items = (array) (crrg_drill_pc($uid)['items'] ?? []);
    /* 身上一切可当武器的东西（装备栏 + 背包）——万物皆可为兵 */
    $carried = function_exists('crrg_weapons_carried') ? crrg_weapons_carried(crrg_drill_pc($uid)) : [];
    $carried['fist'] = ['n' => '徒手', 'dmg' => '1d3'];
    foreach ($carried as $wid => $wi) {
        $cur = ($wid === ($mine['weapon'] ?? ''));
        $stance[] = ['label' => ($cur ? '✔ ' : '') . $wi['n'] . '（' . $wi['dmg'] . '）', 'k' => 'bt_weapon', 'id' => $wid,
                     'on' => $cur, 'off' => $cur];
    }
    $g[] = ['title' => '姿态与武器', 'zone' => 'tools', 'items' => $stance];

    /* 组 4：场景（只列近处的） */
    $props = [];
    foreach ($c['props'] as $pi => $p) {
        $label = ['push' => '推倒', 'tool_lamp' => '关／开灯', 'breaker' => '电闸', 'food_oil' => '点燃', 'hydrant' => '开阀'][$p['type']] ?? null;
        if (!$label) continue;
        if (($p['hp'] ?? 1) <= 0 && !in_array($p['type'], ['tool_lamp', 'breaker'], true)) continue;
        $d = crrg_bt_dist($mine, $p);
        if ($d > 5) continue;
        $props[] = ['label' => $label . ' ' . $p['t'], 'k' => 'bt_prop', 'id' => (string) $pi];
    }
    if ($props) $g[] = ['title' => '场景', 'zone' => 'tools', 'items' => $props];

    /* 组 5：其他 */
    $g[] = ['title' => '其他', 'zone' => 'tools', 'items' => [
        ['label' => '待机', 'k' => 'bt_wait', 'id' => ''],
        ['label' => '撤退／结束模拟', 'k' => 'bt_flee', 'id' => ''],
    ]];
        /* 结束态：只给结果与复位，不再列动作（2026-09-29） */
    if (!empty($c['over'])) {
        return ['title' => '战斗 · 已结束', 'grid' => crrg_bt_grid($c, $uid), 'me' => [0, 0], 'list' => $list,
                'groups' => [['title' => '结算', 'zone' => 'tools', 'items' => [
                    ['label' => '复位场地（结束模拟）', 'k' => 'bt_end', 'id' => ''],
                ]]],
                'log' => array_slice((array) $c['log'], -4), 'sim' => !empty($c['sim']),
                'note' => (!empty($c['sim']) ? '对手全部失去战斗能力，对抗结束。' : '对手全部失去战斗能力，遭遇结束。') . '按「复位场地」清场。',
                'focus' => '—'];
    }
return ['title' => '战斗 · 第 ' . $c['round'] . ' 轮', 'grid' => crrg_bt_grid($c, $uid),
            'me' => $mine ? [(int) round($mine['x']), (int) round($mine['y'])] : [0, 0],
            'list' => $list,
            'groups' => $g, 'log' => array_slice((array) $c['log'], -4), 'sim' => !empty($c['sim']),
            'focus' => $focus === 'self' ? '自己' : ($targets[$focus]['name'] ?? '—')];
}

/* ================= 动作入口 ================= */
function crrg_bt_act($k, $id, $uid) {
    $c = crrg_bt_get();
    if (!$c) return ['当前没有战斗。'];
    if ($k === 'bt_end') { crrg_bt_clear(); return ['场地已复位，对抗结束。']; }   // 复位不受在场限制
    /* 2026-09-29：已结束的对抗不再接受行动（此前打到对面停机还能继续打） */
    if (!empty($c['over']) && !in_array($k, ['bt_end', 'bt_flee'], true)) {
        return ['对抗已经结束（对手全部失去战斗能力）。按「复位场地」清场。'];
    }
    crrg_bt_sync_players($c);
    $me = 'pc:' . $uid;
    if (!isset($c['actors'][$me])) return ['你不在场。'];
    if (in_array('死亡', (array) $c['actors'][$me]['state'], true)) return ['你已经倒下，只能等人来救。'];
    if (in_array('昏迷', (array) $c['actors'][$me]['state'], true) && !in_array($k, ['bt_end', 'bt_flee'], true)) {
        return ['你处于昏迷状态，本轮无法行动（可「结束模拟」或等救援）。'];
    }
    $lines = [];
    switch ($k) {
        case 'bt_fire':  $lines = crrg_bt_attack($c, $me, $id, 'fire'); break;
        case 'bt_melee': $lines = crrg_bt_attack($c, $me, $id, 'melee'); break;
        case 'bt_maneuver':
            [$tgt, $kind] = array_pad(explode('|', $id, 2), 2, '撞倒');
            $lines = crrg_bt_maneuver($c, $me, $tgt, $kind);
            break;
        case 'bt_firstaid': $lines = crrg_bt_firstaid($c, $me, $id, $uid); break;
        case 'bt_defense':
            $c['actors'][$me]['defense'] = ($id === 'fightback') ? 'fightback' : 'dodge';
            $lines[] = '姿态已切换：' . ($id === 'fightback' ? '反击（近战被打时反打）' : '闪避');
            break;
        case 'bt_weapon':
            $own = array_keys(function_exists('crrg_weapons_carried') ? crrg_weapons_carried(crrg_drill_pc($uid)) : []);
            $own[] = 'fist';
            if (in_array($id, $own, true)) {
                $c['actors'][$me]['weapon'] = $id;
                $c['actors'][$me]['state'] = array_values(array_diff((array) $c['actors'][$me]['state'], ['卡壳']));
                $lines[] = '你换成' . crrg_bt_weapons()[$id]['n'] . '。';
            } else { $lines[] = '身上没有这件武器。'; }
            break;
        case 'bt_prop': $lines = crrg_bt_prop_act($c, $me, (int) $id); break;
        case 'bt_stride':
            $A = &$c['actors'][$me];
            $opts = [1, 2, 3, 5];
            $cur = (int) ($A['stride'] ?? 3);
            $i = (array_search($cur, $opts, true) + 1) % count($opts);
            $A['stride'] = $opts[$i];
            $lines[] = '步幅改为 ' . $A['stride'] . ' 米。';
            break;
        case 'bt_move':
            $A = &$c['actors'][$me];
            if (in_array('被擒抱', (array) $A['state'], true)) { $lines[] = '被制住，动弹不得（先挣脱）。'; break; }
            $step = (int) ($A['stride'] ?? 3);
            if (($c['env']['wet'] ?? false)) {
                $r = crrg_coc_check('敏捷', max(1, crrg_coc_who_val($A, '敏捷')));
                $lines[] = '地面湿滑：' . $r['text'];
                if (crrg_coc_level_rank($r['level']) < 2) { $A['state'][] = '倒地'; $lines[] = '你滑倒了（倒地）。'; break; }
            }
            $dirs = ['北' => [0, 1], '东北' => [0.707, 0.707], '东' => [1, 0], '东南' => [0.707, -0.707],
                     '南' => [0, -1], '西南' => [-0.707, -0.707], '西' => [-1, 0], '西北' => [-0.707, 0.707]];
            [$ux, $uy] = $dirs[$id] ?? [0, 0];
            $ar = crrg_bt_arena($c['room']);
            $A['x'] = max(0, min($ar['w'] - 1, round($A['x'] + $ux * $step, 1)));
            $A['y'] = max(0, min($ar['h'] - 1, round($A['y'] + $uy * $step, 1)));
            $A['facing'] = $id;
            $lines[] = sprintf('你向%s移动 %d 米，现在位于 (%s, %s)。', $id, $step, $A['x'], $A['y']);
            break;
        case 'bt_cover':
            $A = &$c['actors'][$me];
            $near = null; $best = 99;
            foreach ($c['props'] as $p) { $d = crrg_bt_dist($A, $p); if ($d < $best) { $best = $d; $near = $p; } }
            if ($near && $best <= 4) {
                crrg_bt_shift($A, $c['room'], $near['x'] + 0.5 - $A['x'], $near['y'] + 0.5 - $A['y']);
                if (!in_array('掩体', (array) $A['state'], true)) $A['state'][] = '掩体';
                $lines[] = '你贴着' . $near['t'] . '蹲下（掩体加成生效）。';
            } else { $lines[] = '附近没有可利用的掩体。'; }
            break;
        case 'bt_cast':
            $sp = crrg_drill_spells()['sim_bolt'];
            $A = &$c['actors'][$me];
            if (($A['mp'] ?? 0) < $sp['mp']) { $lines[] = '魔法值不够（需 ' . $sp['mp'] . '）。'; break; }
            $A['mp'] -= $sp['mp'];
            $r = crrg_coc_check($sp['skill'], max(1, crrg_coc_who_val($A, $sp['skill'])));
            $lines[] = '施放' . $sp['n'] . '：' . $r['text'];
            if ($r['level'] === 'fumble') { $A['hp'] = max(1, $A['hp'] - 1); $lines[] = '反噬：你受到 1 点伤害。'; break; }
            if (crrg_coc_level_rank($r['level']) >= 2) {
                $lines = array_merge($lines, crrg_bt_hurt($c, $c['actors'][$id], crrg_coc_dice($sp['dmg']), $sp['n'], $A));
            } else { $lines[] = '术式散了，没有效果。'; }
            break;
        case 'bt_item':    // 用道具：id = "<物品>|<目标键>"（目标可省，取当前焦点）
            [$it, $tgt] = array_pad(explode('|', $id, 2), 2, '');
            if ($tgt === '' ) $tgt = $c['actors'][$me]['focus'] ?? '';
            $A = &$c['actors'][$me];
            if (crrg_drill_bag_count(crrg_drill_pc($uid), $it) <= 0 && $it !== 'med_bandage') {
                return ['你身上没有这件东西。'];
            }
            if ($it === 'med_sedative') {
                if (!isset($c['actors'][$tgt])) return ['没有可以下针的对象。'];
                $pc = crrg_drill_pc($uid);
                $pc['items'] = (array) $pc['items'];
                crrg_drill_bag_del_n($pc, 'med_sedative', 1);
                crrg_drill_pc_save($uid, $pc);
                $r = crrg_coc_check('急救', max(1, crrg_coc_skill_val($pc, '急救')));
                $lines[] = '扎针：' . $r['text'];
                if (crrg_coc_level_rank($r['level']) >= 2) {
                    $rounds = mt_rand(2, 4);
                    $c['actors'][$tgt]['state'][] = '昏睡';
                    $c['actors'][$tgt]['stun'] = $rounds;
                    $lines[] = $c['actors'][$tgt]['name'] . '腿一软倒下去，' . $rounds . ' 轮里起不来。';
                } else {
                    $lines[] = '针头偏了，药水推空——它扭了一下头， 什么也没发生。';
                }
                break;
            }
            if ($it === 'med_stabilizer') {
                $pc = crrg_drill_pc($uid); crrg_drill_bag_del_n($pc, 'med_stabilizer', 1); crrg_drill_pc_save($uid, $pc);
                $gain = crrg_coc_dice('1d4');
                $pc2 = crrg_drill_pc_defaults($pc);
                $d = crrg_coc_derive($pc2['attrs']);
                $pc2['san'] = min($d['san_max'], (int) $pc2['san'] + $gain);
                crrg_drill_pc_save($uid, $pc2);
                $lines[] = sprintf('你把药片压在舌下。手没那么抖了（理智 +%d → %d）。', $gain, $pc2['san']);
                break;
            }
            if ($it === 'med_bandage') {
                $d = crrg_coc_derive(crrg_drill_pc_defaults(crrg_drill_pc($uid))['attrs']);
                $A['hp'] = min($A['hp_max'], (int) $A['hp'] + 2);
                $lines[] = sprintf('你单手缠了两圈绷带（生命 +2 → %d／%d）。', $A['hp'], $A['hp_max']);
                break;
            }
            $lines[] = '这东西在打斗里用不上。';
            break;

        case 'bt_focus':
            $c['actors'][$me]['focus'] = $id;
            $lines[] = '目标已选定。';
            break;
        case 'bt_wait': $lines[] = '你按兵不动。'; break;
        case 'bt_flee':
            unset($c['actors'][$me]);
            $left = array_filter($c['actors'], fn($a) => $a['k'] === 'pc');
            if (!$left) { crrg_bt_clear(); return ['你脱离对抗，退到门外。']; }
            crrg_bt_save($c);
            return ['你脱离对抗，退到门外。'];
        case 'bt_end':
            crrg_bt_clear();
            return ['对抗结束，场地复位。'];
    }
    $npcLeft  = array_filter($c['actors'], fn($a) => $a['k'] === 'npc' && crrg_bt_active($a));
    $npcAll   = array_filter($c['actors'], fn($a) => $a['k'] === 'npc');
    if (empty($c['actors']) || count($npcAll) === 0) {
        $c['over'] = true; $c['log'][] = '场上没有对手了，对抗结束。';
    } elseif (count($npcLeft) === 0 && !$c['over']) {
        /* 2026-09-29 修：对手全部「训练停机」/昏迷时也该结束（此前只看"场上有没有对手"） */
        $c['over'] = true;
        $c['log'][] = '对手全部失去战斗能力——对抗结束。按「复位场地」清场。';
        $lines[] = '对手全部失去战斗能力——对抗结束。按「复位场地」清场。';
    }
    $standing = array_filter($c['actors'], fn($a) => $a['k'] === 'pc'
        && !array_intersect(['昏迷', '死亡'], (array) $a['state']));
    if (!$standing && !$c['over']) {
        $c['over'] = true; $c['log'][] = '场上队员全部失去意识，模拟中止。';
        $lines[] = '场上队员全部失去意识，模拟中止（「结束模拟」复位）。';
    }
    $c['log'] = array_merge((array) $c['log'], $lines);
    /* 2026-10-14（P2.5）：**战斗结束 → 交给任务引擎落账**（孤岛问题在这里接上 ✓ 一次且仅一次 ✓）
       —— 只处理"带任务上下文"的战斗（$c['ctx']）；普通对抗模拟不受影响 ✓ */
    if (!empty($c['over']) && !empty($c['ctx']['task']) && empty($c['ctx']['done'])
        && function_exists('crrg_task_fight_end')) {
        foreach ((array) crrg_task_fight_end($c) as $x) { $lines[] = $x; }
        $c['ctx']['done'] = true;
    }
    // 界面类操作不消耗行动（房规）：选目标、调步幅、切换姿态、换武器
    $free = ['bt_focus', 'bt_stride', 'bt_defense', 'bt_weapon'];
    if (!$c['over'] && !in_array($k, $free, true)) $lines = array_merge($lines, crrg_bt_advance($c));
    crrg_bt_save($c);
    return $lines;
}
