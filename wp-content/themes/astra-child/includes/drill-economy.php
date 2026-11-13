<?php
/**
 * 虚拟演习 · 经济／任务／队伍／宿舍扩展（2026-09-27 二次改版）
 *
 * 与 hub 的分工：
 *   drill-hub.php     —— 场景注册、合约（任务）骨架、基础货币字段
 *   本文件            —— 地点化商店、任务分「基地／NPC」两类、组队与分红、宿舍家具效果、装修、车辆
 *
 * 交互原则：主要走界面面板（page-drill 的按钮 → admin-ajax action=crrg_drill_act），
 *          指令只作备用通道，不再在提示里引导玩家敲「买 X」。
 */
if (!defined('ABSPATH')) exit;

/* ================= 商品（按类别，含效果） =================
   cur: money(钱) / credits(积分)
   fx : san/hp 每次休整恢复；train 锻炼可提升的属性；unlock 解锁；skill 技能加成；dorm 家具名；deco 装修位
*/
function crrg_drill_goods() {
    /* Phase 1：数据已搬到 includes/game/content/shops.php（纯数据）；本函数只做一次缓存转发 */
    static $c = null;
    if ($c !== null) return $c;
    return $c = (function_exists('crrg_game_goods_data') ? crrg_game_goods_data() : []);
}
function crrg_drill_good($id) {
    foreach (crrg_drill_goods() as $cat => $items) if (isset($items[$id])) return $items[$id] + ['cat' => $cat, 'id' => $id];
    return null;
}
function crrg_drill_find_good($q) {
    foreach (crrg_drill_goods() as $cat => $items) {
        foreach ($items as $id => $it) {
            if (mb_strpos($it['n'], $q) !== false || mb_strpos($q, $it['n']) !== false) return $it + ['cat' => $cat, 'id' => $id];
        }
    }
    return null;
}

/* ================= 商店地点 ================= */
function crrg_drill_shops() {
    /* Phase 1：数据已搬到 includes/game/content/shops.php（纯数据） */
    return (function_exists('crrg_game_shops_data') ? crrg_game_shops_data() : []);
}
function crrg_drill_shops_all() {
    $a = crrg_drill_shops();
    if (function_exists('crrg_drill_town_shops')) $a += crrg_drill_town_shops();
    return $a;
}
function crrg_drill_shops_at($room) {
    $out = [];
    foreach (crrg_drill_shops_all() as $id => $s) if ($s['room'] === $room) $out[$id] = $s;
    return $out;
}
/** 某家店实际卖什么：有 items 就按 items 过滤，否则按 cats */
function crrg_drill_shop_goods($shop) {
    $G = crrg_drill_goods();
    $out = [];
    foreach (($shop['cats'] ?? []) as $cat) {
        foreach (($G[$cat] ?? []) as $id => $it) {
            if (!empty($shop['items']) && !in_array($id, $shop['items'], true)) continue;
            $out[$cat][$id] = $it;
        }
    }
    return $out;
}
function crrg_drill_shop_sells($shop, $gid) {
    foreach (crrg_drill_shop_goods($shop) as $items) if (isset($items[$gid])) return true;
    return false;
}
function crrg_drill_shop_unlocked($shop, $pc) {
    if (empty($shop['need'])) return true;
    $unlocks = [];
    foreach ((array) ($pc['dorm']['furn'] ?? []) as $f) {
        foreach (crrg_drill_goods()['家具'] as $g) if (($g['dorm'] ?? '') === $f && !empty($g['unlock'])) $unlocks[] = $g['unlock'];
    }
    return in_array($shop['need'], $unlocks, true);
}

/* ================= 宿舍效果 ================= */
function crrg_drill_dormfx($pc) {
    $fx = ['san' => 0, 'hp' => 0, 'train' => [], 'skill' => [], 'deco' => []];
    $dorm = $pc['dorm'] ?? [];
    foreach ((array) ($dorm['furn'] ?? []) as $f) {
        foreach (crrg_drill_goods()['家具'] as $g) {
            if (($g['dorm'] ?? '') !== $f) continue;
            foreach ((array) ($g['fx'] ?? []) as $k => $v) {
                if ($k === 'san' || $k === 'hp') $fx[$k] += (int) $v;
                elseif ($k === 'train') $fx['train'][] = $v;
                elseif ($k === 'skill') foreach ($v as $sk => $n) $fx['skill'][$sk] = ($fx['skill'][$sk] ?? 0) + (int) $n;
            }
        }
    }
    foreach ((array) ($dorm['deco'] ?? []) as $slot => $name) {
        foreach (crrg_drill_goods()['装修'] as $g) {
            if (($g['n'] ?? '') !== $name) continue;
            foreach ((array) ($g['fx'] ?? []) as $k => $v) if ($k === 'san') $fx['san'] += (int) $v;
            $fx['deco'][$slot] = $v = ($g['deco'][$slot] ?? '');
        }
    }
    return $fx;
}
/** 宿舍休整：按家具效果恢复，一天一次 */
function crrg_drill_dorm_rest(&$pc) {
    $today = current_time('Y-m-d');
    if (($pc['dorm']['last'] ?? '') === $today) return ['今天已经休整过了。'];
    if (function_exists('crrg_vitals_sleeping') && crrg_vitals_sleeping($pc)) return ['你已经在睡了。'];
    $fx = crrg_drill_dormfx($pc);
    $out = array_merge(['你把灯关了，躺下。'],
        (array) crrg_vitals_sleep($pc, 8, 'bed'));                 // 与「睡」同一套机制
    $pc['vitals']['rest_bonus'] = ['san' => max(0, (int) $fx['san'] - 1), 'hp' => max(0, (int) $fx['hp'])];
    $pc['dorm']['last'] = $today;
    $out[] = sprintf('（宿舍条件加成：理智 +%d、生命 +%d——睡完自动结算；条件越好恢复越高。）',
        1 + (int) $pc['vitals']['rest_bonus']['san'], 2 + (int) $pc['vitals']['rest_bonus']['hp']);
    return $out;
}

/** 宿舍锻炼：每完成一次合约得一次机会；D100 需「大于」当前属性才算练成，再掷 D2（1 加、2 不加） */
function crrg_drill_dorm_train(&$pc, $attr = '') {
    $fx = crrg_drill_dormfx($pc);
    if (!$fx['train']) return ['宿舍里没有训练器械（哑铃／跑步机）。'];
    $which = $attr;
    if (!$which || !in_array($which, $fx['train'], true)) $which = $fx['train'][0];
    $tokens = (int) ($pc['train_tokens'] ?? 0);
    if ($tokens <= 0) return ['锻炼机会用完了——每完成一次合约才有一次。'];
    $val  = (int) $pc['attrs'][$which];
    $roll = crrg_coc_roll100();
    $out  = [sprintf('锻炼%s：D100＝%d，须大于 %d 才算练成。', $which, $roll, $val)];
    $pc['train_tokens'] = $tokens - 1;
    if ($roll <= $val) {
        $out[] = '没过。器械白举了一组，属性不变。';
    } else {
        $out[] = '过了。';
        $d2 = mt_rand(1, 2);
        if ($d2 === 1) {
            $pc['attrs'][$which] = min(99, $val + 1);
            $out[] = sprintf('D2＝1，%s +1 → %d。', $which, $pc['attrs'][$which]);
        } else {
            $out[] = 'D2＝2，练到了，但没长进。';
        }
    }
    $out[] = '剩余锻炼机会：' . (int) $pc['train_tokens'] . '（完成合约可再得一次）';
    return $out;
}

/* ================= 队伍与分红 ================= */
const CRRG_DRILL_TEAMS = 'crrg_drill_teams';
function crrg_drill_teams() { $t = get_option(CRRG_DRILL_TEAMS, []); return is_array($t) ? $t : []; }
function crrg_drill_teams_save($t) { update_option(CRRG_DRILL_TEAMS, $t, false); }
function crrg_drill_team_of($uid) {
    foreach (crrg_drill_teams() as $id => $t) if (in_array((int) $uid, array_map('intval', $t['members'] ?? []), true)) return $t + ['id' => $id];
    return null;
}
function crrg_drill_team_create($uid, $name) {
    if (crrg_drill_team_of($uid)) return [null, ['你已经在队伍里了。']];
    $t = crrg_drill_teams();
    $id = 'T' . (count($t) + 1) . '-' . substr((string) time(), -4);
    $t[$id] = ['n' => $name ?: '未命名小队', 'leader' => (int) $uid, 'members' => [(int) $uid],
               'share' => [(string) $uid => 100], 'mission' => '', 'created' => current_time('mysql')];
    crrg_drill_teams_save($t);
    return [$id, ['队伍已建立：' . $t[$id]['n'] . '（队长分红 100%）。到指挥大厅可组队出勤。']];
}
function crrg_drill_team_join($uid, $id) {
    $t = crrg_drill_teams();
    if (!isset($t[$id])) return ['没有这个队伍。'];
    if (crrg_drill_team_of($uid)) return ['你已经在队伍里了。'];
    $t[$id]['members'][] = (int) $uid;
    crrg_drill_team_share_reset($t[$id]);
    crrg_drill_teams_save($t);
    return ['已加入：' . $t[$id]['n'] . '。分红默认平均，队长可调。'];
}
function crrg_drill_team_share_reset(&$team) {
    $n = max(1, count($team['members']));
    $base = (int) floor(100 / $n);
    $team['share'] = [];
    foreach ($team['members'] as $i => $m) $team['share'][(string) $m] = ($i === 0) ? 100 - $base * ($n - 1) : $base;
}
function crrg_drill_team_leave($uid) {
    $t = crrg_drill_teams();
    foreach ($t as $id => &$team) {
        $m = array_map('intval', $team['members']);
        $k = array_search((int) $uid, $m, true);
        if ($k === false) continue;
        array_splice($team['members'], $k, 1);
        if ((int) $team['leader'] === (int) $uid) {
            if (!$team['members']) { unset($t[$id]); crrg_drill_teams_save($t); return ['队长退队，队伍解散。']; }
            $team['leader'] = (int) $team['members'][0];
        }
        crrg_drill_team_share_reset($team);
        crrg_drill_teams_save($t);
        return ['已退队，分红重新平均。'];
    }
    return ['你不在任何队伍里。'];
}
function crrg_drill_team_move($t, $mission, $scene) { $t['mission'] = $mission; $t['scene'] = $scene; crrg_drill_teams_save($t); }

/* ================= 任务分类 ================= */
function crrg_drill_mission_by_kind($kind) {
    $out = [];
    foreach (crrg_drill_missions() as $id => $m) if (($m['kind'] ?? 'base') === $kind) $out[$id] = $m;
    return $out;
}
/** 训练考核：只能做一次 */
function crrg_drill_train_state($pc) {
    return in_array('m_train_01', (array) ($pc['mission_done'] ?? []), true) ? '已完成' : '可接';
}

function crrg_drill_has_vehicle($pc) { return !empty($pc['vehicle']); }
/** 车辆档案：短途（镇上）／长途（能上山）／载人 */
function crrg_drill_vehicle_info($pc) {
    $v = (string) ($pc['vehicle'] ?? '');
    $M = [
        '自行车'   => ['kind' => 'bike', 'verb' => '骑车', 'wild' => false, 'carry' => 0,
                     'note' => '平路代步：镇上往返免费；山路骑不上去。'],
        '越野摩托' => ['kind' => 'moto', 'verb' => '开车', 'wild' => true,  'carry' => 0,
                     'note' => '能上山：镇上与野外出发点都免费。'],
        '越野车'   => ['kind' => 'jeep', 'verb' => '开车', 'wild' => true,  'carry' => 2,
                     'note' => '能上山，还能捎两名队员。'],
    ];
    return $v !== '' ? array_merge(['name' => $v], $M[$v] ?? ['kind' => 'other', 'verb' => '开车', 'wild' => true, 'carry' => 0, 'note' => '']) : null;
}
/** 能否不花钱出勤到某场景（山地只有机动车上得去） */
function crrg_drill_can_drive_to($pc, $scene) {
    $v = crrg_drill_vehicle_info($pc);
    if (!$v) return [false, '没有车'];
    if ($scene === 'mod_wild' && empty($v['wild'])) return [false, $v['name'] . '上不了山路'];
    return [true, $v['name']];
}
/** 出勤车费：无车（或自行车上不了山）时按基地车 10 积分／搭车 100 元收 */
function crrg_drill_travel_fee(&$pc, $reason = '') {
    if ((int) $pc['credits'] >= 10) { $pc['credits'] -= 10; return ['扣 10 积分（基地车）。']; }
    if ((int) $pc['money'] >= 100)   { $pc['money'] -= 100;  return ['扣 100 元（搭车）。']; }
    return null;
}

/* ================= 购买（面板与指令共用） ================= */
function crrg_drill_buy(&$pc, $q, $cnt = 1, $room = '') {
    $cnt = max(1, min(20, (int) $cnt));
    $g = crrg_drill_good($q) ?: crrg_drill_find_good($q);
    if (!$g) return ['没有这件货。'];
    if (function_exists('crrg_world_shop_open') && !crrg_world_shop_open()
        && crrg_drill_cur_scene() === 'town') {          // 只有镇上的店按点打烊；基地里 24 小时
        return ['店门关着。' . crrg_world_line()];
    }
    $shops = crrg_drill_shops_at($room);
    $here  = false;
    foreach ($shops as $sid => $shop) {
        if (!crrg_drill_shop_unlocked($shop, $pc)) continue;
        if (crrg_drill_shop_sells($shop, $g['id'] ?? '')) { $here = true; break; }
    }
    if (!$here) {
        $where = [];
        foreach (crrg_drill_shops_all() as $sid => $shop) {
            if (crrg_drill_shop_sells($shop, $g['id'] ?? '')) $where[] = $shop['n'];
        }
        return ['这里不卖「' . $g['n'] . '」。可去：' . implode('、', array_unique($where))];
    }
    $cost  = (int) $g['p'] * $cnt;
    $field = ($g['cur'] === 'credits') ? 'credits' : 'money';
    if (!isset($pc[$field])) $pc[$field] = 0;
    $rep_loc = (crrg_drill_cur_scene() === 'town') ? 'town_qingshi' : 'base';   // 声望影响当地价格
    if (function_exists('crrg_rep_apply_price')) {
        $cost = crrg_rep_apply_price($pc, $rep_loc, (int) $cost);
    }
    if ((int) $pc[$field] < $cost) {
        return [sprintf('不够：%s 要 %d，你有 %d。', $g['cur'] === 'credits' ? '积分' : '钱', $cost, (int) $pc[$field])];
    }
    $pc[$field] -= $cost;
    if (!empty($g['vehicle'])) {
        $pc['vehicle'] = $g['vehicle'];
        return [sprintf('买下 %s，已停进车库。', $g['vehicle'])];
    }
    if (!empty($g['deco'])) {
        foreach ($g['deco'] as $slot => $desc) $pc['dorm']['deco'][$slot] = $g['n'];
        $pc['dorm']['deco_items'] = array_merge((array) ($pc['dorm']['deco_items'] ?? []), [$g['n']]);   // 登记件数，便于日后拆除退款
        return [sprintf('买下 %s，已装到宿舍（%s）。', $g['n'], implode('、', array_keys($g['deco'])))];
    }
    if (!empty($g['dorm'])) {
        for ($i = 0; $i < $cnt; $i++) $pc['dorm']['furn'][] = $g['dorm'];
        return [sprintf('买下 %s ×%d，已送进宿舍。', $g['n'], $cnt)];
    }
    for ($i = 0; $i < $cnt; $i++) {
        crrg_drill_bag_add($pc, $g['item']);
        if (($g['id'] ?? '') === 'sup') { crrg_drill_bag_add($pc, 'food_ration'); crrg_drill_bag_add($pc, 'tool_flask'); crrg_drill_bag_add($pc, 'med_bandage'); crrg_drill_bag_add($pc, 'tool_battery'); }
    }
    return [sprintf('买下 %s ×%d，收进背包（花 %d %s）。', $g['n'], $cnt, $cost, $g['cur'] === 'credits' ? '积分' : '元')];
}

/* ================= 出勤（含队伍与车辆） ================= */
function crrg_drill_depart(&$s, &$pc, $ctx, $uid, $by_car = false) {
    if (empty($pc['mission'])) return [null, ['没有在手的合约。']];
    $m = crrg_drill_mission($pc['mission']);
    if (!$m) { $pc['mission'] = ''; return [null, ['合约数据异常，已清空。']]; }
    if (($m['depart_room'] ?? '') !== $s['room']) {
        return [null, ['出发点在' . (crrg_drill_rooms()[$m['depart_room']]['t'] ?? '指定位置') . '。']];
    }
    [$ok, $who] = crrg_drill_can_drive_to($pc, $m['scene'] ?? 'base');
    $fee = [];
    if (!$ok) {
        $f = crrg_drill_travel_fee($pc, $who);
        if ($f === null) return [null, ['出勤要车费（基地车 10 积分／搭车 100 元），你两样都不够。',
                                        '（' . $who . '——想去野外得换辆车。）']];
        $fee = array_merge(['（' . $who . '：' . $f[0] . '）'], []);
    }
    $pc['base_room'] = $s['room'];
    $pc['scene']     = $m['scene'];
    crrg_drill_set_scene($m['scene']);
    $s['room']       = $m['entry_room'];
    $s['steps']      = (int) $s['steps'] + ($by_car ? 0 : 1);
    $sc = crrg_drill_scene($m['scene']);
    $vi = crrg_drill_vehicle_info($pc);
    $how = $ok && $vi ? '（' . $vi['verb'] . '到出发点）' : '';
    $out = array_merge(['〔出勤〕' . ($sc['name'] ?? $m['scene']) . $how], $fee);
    $out = array_merge($out, crrg_drill_room_lines($s, $ctx));
    crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '抵达这里。', 'move');
    return [$m, $out];
}

