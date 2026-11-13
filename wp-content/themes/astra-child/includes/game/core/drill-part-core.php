<?php
/*CRRG 演习系统 · drill.php 拆分块[core]（2026-09-29 从 drill.php 原样搬出，仅位置变化）
 * 搬出后由 drill.php 在顶部 require，保证声明顺序与拆分前一致。
 */
if (!defined('ABSPATH')) exit;

function crrg_drill_gates() {
    return [
        /* 负四层收容层：整层涉密 */
        'b4_buffer' => 5, 'b4_obs' => 5, 'b4_main' => 5, 'b4_guard' => 5,
        'b4_c1' => 5, 'b4_c2' => 5, 'b4_c3' => 5, 'b4_transfer' => 5,
        /* 涉密要害 */
        'b3_armory' => 5,        // 武器库
        'b1_archive' => 5,       // 档案室（双人双锁）
        'b2d_iso' => 5,          // 隔离实验室
        'b2d_bio' => 5,          // 生物实验室
        'b2d_eso' => 5,          // 秘术研究室
        'freight_lift' => 5,     // 直通负四层的货运电梯
        /* 仅高层可开 */
        'garage_seal' => 6,      // 封存隔间
        'b1_chief_office' => 6,  // 组长办公室
        'b1_evac_e' => 6, 'b4_evac' => 6, 'ext_east' => 6, 'ext_south' => 6, 'garage_exit' => 6,  // 应急撤离与山体出口
    ];
}
/** 见习通行：新手训练考核在手（未交付）时，负四层这三处对本人开放（仍须着 T3 防护服） */

function crrg_drill_trainee_rooms() {
    return ['b4_buffer', 'b4_obs', 'b4_main', 'b4_c2'];
}

function crrg_drill_trainee_pass($pc, $target) {
    $m = $pc['mission'] ?? '';
    $done = (array) ($pc['mission_done'] ?? []);
    if ($m !== 'm_train_01' || in_array('m_train_01', $done, true)) return false;
    return in_array($target, crrg_drill_trainee_rooms(), true);
}
/** 任务授权通行（2026-09-29）：在手合约本身要求进入的房间，即使卡级不够也放行
 *  例：中型《封条》第一幕要求 C 级接单，但调查点在 B4（需 B 级）——按任务授权放行。 */

function crrg_drill_gate_of($room) {
    $g = crrg_drill_gates();
    return (int) ($g[$room] ?? 1);   // 未列入 = 自由通行
}

/* ---------------- 卡级 ---------------- */
/** 有效卡级（2026-09-29 统一）：职务卡级与"花积分买的演习权限"取大者。
 *  以前有的地方只看职务卡级，导致买了演习权限却仍被判定"资格不足"。 */

function crrg_drill_eff_card($pc, $ctx = null) {
    $lv = crrg_drill_eff_lv($pc, $ctx);
    return crrg_drill_clearance_name($lv ?: 1);
}

function crrg_drill_card($rank_id) {
    $n = ['observer' => 1, 'operative' => 2, 'tl' => 3, 'chief' => 4,
          'advisor' => 5, 'deputy' => 6, 'chairman' => 7][$rank_id] ?? 1;
    if ($n >= 6) return ['A', 6];
    if ($n >= 5) return ['B', 5];
    if ($n >= 3) return ['C', 3];
    return ['D', 1];
}

function crrg_drill_ctx($user_id) {
    $rank = function_exists('crrg_get_rank') ? crrg_get_rank($user_id) : 'observer';
    [$card, $lv] = crrg_drill_card($rank);
    $rd = function_exists('crrg_get_rank_data') ? crrg_get_rank_data($rank) : ['name' => '观察员'];
    $isS = function_exists('crrg_fate_is_s') ? crrg_fate_is_s($user_id) : false;
    if ($isS) { $card = 'S'; $lv = 7; $rd['name'] = ($rd['name'] ?? '观察员') . '（轮回）'; }
    return ['rank' => $rank, 'rank_name' => $rd['name'] ?? '观察员',
            'card' => $card, 'lv' => $lv, 'chairman' => ($rank === 'chairman'), 's' => $isS];
}

/** 槽位自愈（2026-09-29）：主槽／侧槽若挂着"档案里不存在的合约"（旧版本遗留、被改名、被删除），
 *  会导致面板渲染中断、玩家连别的动作都点不到。这里直接撤掉并说明。 */

function crrg_drill_slot_sanitize(&$pc) {
    $out = [];
    foreach ([['mission', 'mission_step'], ['mission2', null]] as [$key, $stepKey]) {
        if ($key === 'mission2') {
            $mid = $pc['mission2']['id'] ?? '';
            if ($mid === '' ) continue;
            if (!crrg_drill_mission_any($mid)) {
                $pc['mission2'] = ['id' => '', 'step' => 0, 'start' => 0];
                $out[] = sprintf('（侧槽里的「%s」已经不在档案里了——已撤掉。）', $mid);
            }
            continue;
        }
        $mid = $pc['mission'] ?? '';
        if ($mid === '') continue;
        if (!crrg_drill_mission_any($mid)) {
            $pc['mission'] = ''; $pc['mission_step'] = 0;
            $out[] = sprintf('（你名下的合约「%s」在档案里已经找不到了——已撤掉，不影响你接新的。）', $mid);
        }
    }
    return $out;
}
/** 房间自愈（2026-09-29）：当前位置若不在当前场景的房间表里（旅行/剧本切换打架），
 *  自动纠正——先按其它场景找同名房间并切场景，找不到就落回本场景入口，避免"卡在不存在的房间"。 */

function crrg_drill_room_guard(&$s, $pc = null) {
    $room = (string) ($s['room'] ?? '');
    if ($room === '') return [];
    if (isset(crrg_drill_rooms()[$room])) return [];                 // 正常
    // 跨场景找
    foreach (array_keys((array) (crrg_drill_reg()['scenes'] ?? [])) as $sc) {
        crrg_drill_set_scene($sc);
        if (isset(crrg_drill_scene_rooms()[$room])) {
            if ($pc !== null) $pc['scene'] = $sc;
            return [sprintf('（你从别处回到了%s。）', crrg_drill_rooms()[$room]['t'] ?? $room)];
        }
    }
    // 找不到：落回本场景入口
    $fallback = crrg_drill_cur_scene() === 'base' ? 'b1_tunnel' : (array_key_first((array) crrg_drill_rooms()) ?: 'b1_tunnel');
    $old = $room;
    $s['room'] = $fallback;
    if ($pc !== null) $pc['scene'] = crrg_drill_cur_scene();
    return [sprintf('（「%s」不通——你退回到了%s。）', $old, crrg_drill_rooms()[$fallback]['t'] ?? $fallback)];
}
/* ---------------- 房间图（依结构描述逐间建） ---------------- */

function crrg_drill_rooms() {
    static $cache = [];
    $sc = crrg_drill_cur_scene();
    if (isset($cache[$sc])) return $cache[$sc];
    /* Phase 1：基础表已搬到 includes/game/content/rooms.php（纯数据），此处只读 */
    $r = crrg_game_rooms_base();;
    foreach (crrg_drill_scene_rooms() as $k => $v) $r[$k] = $v;   // 副本模组注册的房间
    $cache[$sc] = $r;
    return $r;
}

/** 位置与场景自愈同步：房间键能反推场景；实在对不上就退回该场景入口，绝不空指针 */

function crrg_drill_sync_room(&$s, &$pc) {
    $rooms = crrg_drill_rooms();
    if (isset($rooms[$s['room'] ?? ''])) return;
    $room = (string) ($s['room'] ?? '');
    $guess = 'base';
    if (strpos($room, 'tw_') === 0) $guess = 'town';
    elseif (strpos($room, 'w_') === 0) $guess = 'mod_wild';
    crrg_drill_set_scene($guess);
    $pc['scene'] = $guess;
    $rooms = crrg_drill_rooms();
    if (!isset($rooms[$room])) {
        $entry = array_key_first(crrg_drill_scene_rooms());
        if (!$entry || !isset($rooms[$entry])) $entry = 'b1_tunnel';
        $room = $entry;
    }
    $s['room'] = $room;
}

/* ---------------- 状态 ---------------- */

function crrg_drill_state($user_id) {
    $s = get_user_meta($user_id, CRRG_DRILL_META, true);
    if (!is_array($s) || empty($s['room'])) {
        $s = ['room' => 'b1_tunnel', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 0];
    }
    $s += ['items' => [], 'flags' => [], 'steps' => 0, 'gates' => 0];
    return $s;
}

function crrg_drill_save($user_id, $s) {
    $s['seen'] = time();   // 用于判断「是否还在场」：战斗同场只拉最近 15 分钟内活动过的人
    /* 2026-10-01 修（玩家报"按别的键又弹出上一个窗口"✗）：
       **开窗/关窗标记绝不能进存档** ✗ —— 一旦存进去，之后每次动作或自动刷新
       都会被当成"要开窗"→ 上一个窗口反复弹出来 ✓
       （它们是**一次性的界面指令**，只应活在当次请求里 ✓） */
    unset($s['ui_open'], $s['ui_close']);
    /* 2026-10-01 加**防误清护栏** ✓（当天出过事故 ✗：一个手拼的状态被存进来 → 玩家 100 个旗标只剩 1 个 ✓）
       · 旧档有 ≥8 个旗标，而新档**一个都不剩**（或掉了 ≥60% 且是新档为旧档子集）→ 视为**误清** ✗
       · 处置：**保留旧旗标** ✓，并在新档记一笔 `save_guard` 供日后查 ✓（宁可不改，也不能误删进度 ✓） */
    $old0 = get_user_meta($user_id, CRRG_DRILL_META, true);
    if (is_array($old0) && !empty($old0['flags'])) {
        $of = array_values(array_unique((array) $old0['flags']));
        $nf = array_values(array_unique((array) ($s['flags'] ?? [])));
        $lostAll = ($nf === [] && count($of) >= 8);
        $subset  = (count(array_diff($nf, $of)) === 0);
        $bigLoss = ($subset && count($of) >= 8 && count($nf) <= count($of) * 0.4);
        if ($lostAll || $bigLoss) {
            $s['flags'] = $of;                                        // 保留旧旗标 ✓
            $s['save_guard'] = ['t' => time(), 'kept' => count($of), 'attempted' => count($nf),
                                'who' => (function_exists('wp_debug_backtrace_summary') ? 'guard' : 'guard')];
        }
        /* 2026-10-01 追加（林晓雅报"做过的步骤被打回去"✓）：
           **进度类旗标应当单调** ✓ —— 一旦完成，不该因为并发写档／回档而消失 ✗
           （她在手机上，页面每 30 秒自动刷新 ✓：两个请求各自读档→改→写档，
             后写的会把先写的覆盖 ✗ → 已完成的步骤就"变回没做"✓）
           只对**进度类前缀**生效 ✓；**刻意会被消耗的**（如 `paper_*` 材料 ✓）不在此列 ✗ —— 那种该消失就消失 ✓ */
        /* 2026-10-14：加 pendingpaid_（隐患已兑现的标记）—— 必须是单调的，否则同一笔隐患会被反复兑现 ✗ */
        $mono = ['st1_', 'st2_', 'st3_', 'seal_', 's3_', 's4_', 's5_', 'craft_', 'acc_', 'hook_', 'night_', 'route_', 'pendingpaid_', 'fight_', 'enlist_'];
        $keep = [];
        foreach ($of as $f) {
            if (in_array($f, $nf, true)) continue;                     // 新档还有 → 不用管
            foreach ($mono as $p) if (strpos((string) $f, $p) === 0) { $keep[] = $f; break; }
        }
        if ($keep) {
            $s['flags'] = array_values(array_unique(array_merge($nf, $keep)));
            $s['save_guard'] = ['t' => time(), 'kept' => count($keep), 'list' => array_slice($keep, 0, 8),
                                'why' => '进度旗标单调：已完成的步骤不因写档覆盖而回退'];
        }
    }
    $old = get_user_meta($user_id, CRRG_DRILL_META, true);
    /* 2026-10-14（bus P0）：**位置变化在存档出口发一次事件** ✓
       为什么埋在这儿：所有位移（按钮、指令、上车落地、回宿舍…）最后都要过 save ✓
       → 入口处埋要埋十几处还会漏 ✗；出口埋一次，两条路自动覆盖 ✓（幂等：只在真变了才发） */
    if (function_exists('crrg_evt_emit')) {
        $r0 = (string) ($old['room'] ?? '');
        $r1 = (string) ($s['room'] ?? '');
        if ($r1 !== '' && $r0 !== '' && $r0 !== $r1) {
            $pc0 = function_exists('get_user_meta') ? get_user_meta($user_id, 'crrg_drill_pc', true) : [];
            crrg_evt_emit('player.enter', [
                'uid' => (int) $user_id, 'room' => $r1, 'from' => $r0,
                'name' => (string) (is_array($pc0) ? ($pc0['name'] ?? '') : ''),
            ], ['src' => 'save']);
        }
    }
    if (is_array($old) && isset($old['flags'])) {          // 给新增旗标盖时间戳，供「笔记」用
        $log = (array) ($s['flaglog'] ?? ($old['flaglog'] ?? []));
        foreach (array_diff((array) $s['flags'], (array) $old['flags']) as $f) $log[$f] = time();
        $s['flaglog'] = $log;
    }
    update_user_meta($user_id, CRRG_DRILL_META, $s);
}

/* 角色卡（CoC 7e）：存在 user_meta crrg_drill_pc */
const CRRG_DRILL_PC_META = 'crrg_drill_pc';

function crrg_drill_pc($user_id) {
    /* 读卡时把老物品 id 迁到新命名（命名规范 v1，2026-09-28） */
    $pc = get_user_meta($user_id, CRRG_DRILL_PC_META, true);
    if (!is_array($pc) || empty($pc['attrs'])) return crrg_coc_new_pc();
    $pc = $pc + ['items' => [], 'worn' => [], 'posture' => '站立', 'ready' => false, 'stage' => 'attrs'];
    if (function_exists('crrg_drill_items_migrate')) crrg_drill_items_migrate($pc);   // 老物品 id → 新命名
    return $pc;
}

function crrg_drill_pc_save($user_id, $pc) {
    /* 存档快照：保存前把背包与装备记一份，滚动保留 12 份——东西被覆盖时能倒查 */
    $old = get_user_meta($user_id, CRRG_DRILL_PC_META, true);
    if (is_array($old) && (isset($old['items']) || isset($old['equip']))) {
        $log = get_user_meta($user_id, 'crrg_drill_pc_log', true);
        if (!is_array($log)) $log = [];
        $log[] = ['t' => time(), 'items' => (array) ($old['items'] ?? []), 'worn' => (array) ($old['worn'] ?? []),
                  'equip' => (array) ($old['equip'] ?? []), 'money' => (int) ($old['money'] ?? 0)];
        if (count($log) > 12) $log = array_slice($log, -12);
        update_user_meta($user_id, 'crrg_drill_pc_log', $log);
    }
    update_user_meta($user_id, CRRG_DRILL_PC_META, $pc);
}
/** 玩家身上的物品 id 归一化（现场物件 / 背包 / 部位装备 / 旧 worn 字段都算）
 *  2026-09-29：原先只看 items/worn，穿戴后装备存在 $pc['equip'] 的槽位里 → 判定失败 */

function crrg_drill_carried_ids($s, $pc) {
    $out = [];
    foreach ((array) ($s['items'] ?? []) as $it) $out[] = is_array($it) ? ($it['id'] ?? '') : $it;
    foreach ((array) ($pc['items'] ?? []) as $k => $it) $out[] = is_array($it) ? ($it['id'] ?? '') : $it;
    foreach ((array) ($pc['equip'] ?? []) as $slot => $it) $out[] = is_array($it) ? ($it['id'] ?? ($it['item'] ?? '')) : $it;
    foreach ((array) ($pc['worn'] ?? []) as $it) $out[] = is_array($it) ? ($it['id'] ?? '') : $it;
    return array_values(array_filter($out, 'strlen'));
}
/** 是否具备 T3 防护服（任何一处带着即算） */

function crrg_drill_has_suit($s, $pc) {
    foreach (crrg_drill_carried_ids($s, $pc) as $it) {
        if ($it === 'T3防护服' || $it === 'gear_hazmat') return true;
    }
    return false;
}
/** 角色卡文本行（供「身份」「角色卡」用） */

function crrg_drill_pc_lines($pc, $full = false) {
    $l = crrg_coc_sheet_lines($pc);
    if (!$full) return $l;
    $l[] = '';
    $l[] = '技能：';
    $sk = $pc['skills'] ?: [];
    arsort($sk);
    $row = [];
    foreach ($sk as $k => $v) { $row[] = sprintf('%s %d%%', $k, $v); }
    $l[] = '　' . implode('　', array_slice($row, 0, 14));
    if (count($row) > 14) $l[] = '　' . implode('　', array_slice($row, 14));
    return $l;
}


function crrg_drill_score($s) {
    $w = ['briefing' => 10, 'gear' => 20, 'gates' => 20, 'record' => 30, 'done' => 20];
    $sum = 0;
    foreach ((array) ($s['flags'] ?? []) as $f) $sum += $w[$f] ?? 0;
    return $sum;
}

/* ---------------- 输出 ---------------- */

function crrg_drill_match_exit($exits, $arg) {
    foreach ($exits as $k => $to) if ($k === $arg) return $to;
    foreach ($exits as $k => $to) if (mb_strpos($k, $arg) !== false || mb_strpos($arg, $k) !== false) return $to;
    return null;
}

function crrg_drill_flag_name($f) {
    return ['briefing' => '领任务', 'gear' => '领装备', 'gates' => '过闸门',
            'record' => 'C2 取样', 'done' => '交报告'][$f] ?? $f;
}

function crrg_drill_card_name($lv) {
    return [1 => 'D', 3 => 'C', 5 => 'B', 6 => 'A', 7 => 'S'][$lv] ?? 'D';
}

