<?php
/**
 * CRRG 演习 · 引擎与各系统的对接层（engine/links.php，P1.9，2026-10-14）
 * ------------------------------------------------------------
 * 委员长："任务引擎要有**现在以及以后应该有的一切**，比如车旅系统还有别的东西，把地基打好。"
 * → 这一层就是"**任务 ↔ 各系统**"的唯一接口面板：需要什么条件、付出什么代价、拿到什么产出，
 *    一律写成声明，由这里统一转交给对应系统 ✓（作者不直接调各系统的函数 ✗ —— 否则又回到"互相调函数"的老路）
 *
 * 能接的（现在就有了）：
 *   条件 need ：flags / not_flags / item / money / rep / fav / lv / vitals / hour / weather / travel_node / players_here / tasks
 *   代价 cost ：minutes / expose / flag / san / hp / energy / food / water / money / items / rep / fav / sleep
 *   产出 gain ：clues / stage / items / money / rep / fav / flags / fact / sleep / vitals
 *   行程 travel：`dest` 字段 → 交给车旅系统，**到达时回来落账** ✓（同战斗那套做法）
 *   期限 expire：`on_expire` → 期限到点的后果 ✓（不再只是显示一个数字 ✗）
 *   多人 players：`players_here`（谁在这间屋）✓ —— 多人协同的基本件
 *
 * 纪律：**键名写错＝静默失效** ✗（踩过"写了没做"的坑 ✓）→ `tools/verify-task.php` 会按白名单校验键名 ✓
 */
if (!defined('ABSPATH')) exit;

/* ══════════ 一、条件 ══════════ */

/** 条件求值：返回 [bool, 不满足时的玩家可见说明] */
function crrg_task_need_ok(&$s, &$pc, array $need, $path = []) {
    $flags = (array) ($s['flags'] ?? []);
    foreach ((array) ($need['flags'] ?? []) as $f) {
        if (!in_array($f, $flags, true)) return [false, '（还差一步：' . ($path['need_hint'] ?? '有些事没办完') . '）'];
    }
    foreach ((array) ($need['not_flags'] ?? []) as $f) {
        if (in_array($f, $flags, true)) return [false, '（这条路已经走不通了。）'];
    }
    if (!empty($need['item'])) {
        $own = function_exists('crrg_drill_own_count') ? crrg_drill_own_count($pc, (string) $need['item']) : 0;
        if ($own < (int) ($need['n'] ?? 1)) {
            $nm = function_exists('crrg_drill_item_name') ? crrg_drill_item_name((string) $need['item']) : $need['item'];
            return [false, '（手上没有' . $nm . '。）'];
        }
    }
    if (!empty($need['money']) && (int) ($pc['money'] ?? 0) < (int) $need['money']) {
        return [false, sprintf('（要 %d 元，你在 %d 元。）', (int) $need['money'], (int) ($pc['money'] ?? 0))];
    }
    if (!empty($need['rep'])) {                                   // ['rep' => ['base', 5]]
        $loc = (string) $need['rep'][0]; $min = (int) ($need['rep'][1] ?? 0);
        $cur = function_exists('crrg_rep_of') ? crrg_rep_of($pc, $loc) : 0;
        if ($cur < $min) return [false, sprintf('（那边的人还不认你——声望 %d，至少要 %d。）', $cur, $min)];
    }
    if (!empty($need['lv']) && (int) ($pc['clearance'] ?? 0) < (int) $need['lv']) {
        return [false, sprintf('（权限不够：要 %d 级。）', (int) $need['lv'])];
    }
    if (!empty($need['who'])) {
        $fav = (int) ($pc['npc_fav'][$need['who']] ?? 0);
        if ($fav < (int) ($need['fav'] ?? 0)) {
            $nm = function_exists('crrg_npc_name') ? crrg_npc_name($need['who']) : $need['who'];
            return [false, sprintf('（%s 现在不肯帮——人情 %d，至少要 %d。跑腿能涨。）', $nm, $fav, (int) ($need['fav'] ?? 0))];
        }
    }
    if (!empty($need['vitals'])) {                                // ['vitals' => ['energy', 30]]
        $k = (string) $need['vitals'][0]; $min = (float) ($need['vitals'][1] ?? 0);
        $v = (float) ($pc['vitals'][$k] ?? 0);
        if ($v < $min) {
            $cn = ['energy' => '精力', 'food' => '饱食', 'water' => '口渴', 'rest' => '休息'][$k] ?? $k;
            return [false, sprintf('（%s不够：%d／需要 %d。先吃点、喝点或睡一觉。）', $cn, (int) $v, (int) $min)];
        }
    }
    if (!empty($need['hour'])) {                                  // [起, 止] 世界小时（可跨夜：如 [22, 5]）
        $h = (int) (crrg_task_fact_hour());
        $a = (int) $need['hour'][0]; $b = (int) $need['hour'][1];
        $ok = ($a <= $b) ? ($h >= $a && $h < $b) : ($h >= $a || $h < $b);
        if (!$ok) return [false, sprintf('（这个点不合适——现在是%s。）', crrg_task_phase_cn())];
    }
    if (!empty($need['weather'])) {
        $w = function_exists('crrg_world_weather') ? (string) crrg_world_weather('base') : '';
        if ($w !== '' && $w !== (string) $need['weather']) return [false, '（眼下的天气不对。）'];
    }
    if (!empty($need['travel_node'])) {                            // 人得站在某张地图节点上（车旅/野外）
        $here = (string) ($GLOBALS['crrg_travel_node'] ?? '');
        if ($here !== (string) $need['travel_node']) return [false, '（得先到那个地方去。）'];
    }
    if (!empty($need['players_here'])) {                           // 多人：这一间屋里还得有别人
        $n = crrg_players_here((string) ($s['room'] ?? ''), (int) ($pc['uid'] ?? 0));
        if (count($n) < (int) $need['players_here']) return [false, '（这事一个人办不了——得有别人在场。）'];
    }
    /* 2026-10-14（P1.9c）：**值班表/编制** —— 任务可以直接问「这个岗今天空着吗」「今天是不是我值」 */
    if (!empty($need['duty_vacant']) && function_exists('crrg_duty_vacant')) {
        if (!crrg_duty_vacant((string) $need['duty_vacant'])) return [false, '（那个岗今天有人。表上写着名字。）'];
    }
    if (!empty($need['duty_is']) && function_exists('crrg_duty_is')) {
        $post = (string) ($need['duty_is'][0] ?? ''); $uid = (int) ($need['duty_is'][1] ?? ($pc['uid'] ?? 0));
        if (!crrg_duty_is($post, $uid)) return [false, '（今天这个岗不是你——表上没有你的名字。）'];
    }
    if (!empty($need['roster_has']) && function_exists('crrg_duty_roster_has')) {
        if (!crrg_duty_roster_has((string) $need['roster_has'])) return [false, '（编制里没有这个人。）'];
    }
    if (!empty($need['san_ok']) && function_exists('crrg_san_of')) {   // 2026-10-14（madness）：别疯着去
        $needSan = (int) $need['san_ok'];
        if (crrg_san_of($pc) < $needSan) return [false, sprintf('（脑子不够清楚——现在的状态，这种事做不了。理智低于 %d。）', $needSan)];
        if (((int) ($pc['mad']['temp_until'] ?? 0)) > 0) return [false, '（你现在不完全归自己管——等这阵过去。）'];
    }
    if (!empty($need['signal']) && function_exists('crrg_signal_at')) {   // 2026-10-14（R3）：要有信号
        $sig = crrg_signal_at((string) ($s['room'] ?? ''));
        if ($sig < (int) $need['signal']) return [false, '（这里没信号——打不出去。）'];
    }
    if (!empty($need['contain_level']) && function_exists('crrg_contain_rank_ok')) {   // 2026-10-14（R4）：收容权限
        [$cok, $cwhy] = crrg_contain_rank_ok($pc, (string) $need['contain_level']);
        if (!$cok) return [false, $cwhy];
    }
    /* 2026-10-14（infer）：**按轨道开结局** —— 一路把它读成"人说"还是"岗位说"，决定这一条能不能走 ✓ */
    if (!empty($need['infer_lean']) && function_exists('crrg_infer_lean_chain')) {
        $spec = (array) $need['infer_lean'];
        $tasks = (array) ($spec[0] ?? []); $want = (string) ($spec[1] ?? '');
        $have = crrg_infer_lean_chain($pc, $tasks);
        if ($have !== $want) {
            $cn = ['ren' => '人说', 'gangwei' => '岗位说', 'mixed' => '两种说法混着', '' => '还没想通'][$have] ?? $have;
            return [false, sprintf('（你一路把这件事读成了「%s」——这一条现在走不通。）', $cn)];
        }
    }
    /* 2026-10-14（R9）：分工要在场（派一个不在的人不算 ✓） */
    if (!empty($need['team_role']) && function_exists('crrg_team_has')) {
        $role = (string) $need['team_role'];
        $cn = function_exists('crrg_team_roles') ? (crrg_team_roles()[$role] ?? $role) : $role;
        if (!crrg_team_has($pc, $role)) return [false, sprintf('（这件事得有个人%s——先把人叫上，分工一下。）', $cn)];
    }
    /* 装备得还能用（报废的不许硬上 ✓） */
    if (!empty($need['gear_ok']) && function_exists('crrg_gear_state')) {
        $st = crrg_gear_state($pc, (string) $need['gear_ok']);
        if (!$st || (int) $st['dur'] <= 0) return [false, '（要用的东西已经报废了——先保养。）'];
        if (isset($st['batt']) && (int) $st['batt'] <= 0) return [false, '（没电。充上再说。）'];
    }
    if (!empty($need['no_trace']) && function_exists('crrg_trace_count')) {   // 2026-10-14：要求现场干净（善后系统）
        $n = 0;
        if (function_exists('crrg_drill_rooms')) foreach (array_keys((array) crrg_drill_rooms()) as $rm) $n += (int) crrg_trace_count($s, (string) $rm);
        if ($n > 0) return [false, sprintf('（现场还留着 %d 处痕迹——先清干净再来。）', $n)];
    }
    /* 2026-10-14（接口补齐）：**交易型解法**的前提 —— 本屋有店（有店才能"花这个钱"） */
    if (!empty($need['shop_at']) && function_exists('crrg_drill_shops_at')) {
        $here = array_keys((array) crrg_drill_shops_at((string) ($s['room'] ?? '')));
        $want = (string) $need['shop_at'];
        if ($want !== 'any' && !in_array($want, $here, true)) return [false, '（这屋里没有那家店。）'];
        if ($want === 'any' && !$here) return [false, '（这屋里没有店。）'];
    }
    /* 2026-10-14：**人在路上**（行程型解法/对话的前提） */
    if (!empty($need['traveling']) && empty($pc['travel'])) return [false, '（得先上路——这不是站在这儿能办的。）'];
    if (!empty($need['tasks'])) {                                  // 任务链：前置任务已完成
        $done = (array) ($pc['mission_done'] ?? []);
        foreach ((array) $need['tasks'] as $tid) {
            if (!in_array((string) $tid, $done, true)) return [false, '（上头的事还没办完，这条轮不到。）'];
        }
    }
    return [true, ''];
}

/* ══════════ 二、代价与产出 ══════════ */

/** 代价：一律"只算个人账" ✓（绝不碰世界钟 ✗） */
function crrg_task_cost_apply(&$s, &$pc, $task, array $cost) {
    $out = [];
    if (!empty($cost['minutes']) && function_exists('crrg_vitals_now')) {
        $pc['vitals']['t'] = crrg_vitals_now() - (int) $cost['minutes'] * 60;   // 个人时间账
        $out[] = sprintf('（耗了约 %d 分钟。）', (int) $cost['minutes']);
    }
    if (!empty($cost['energy']) || !empty($cost['food']) || !empty($cost['water'])) {
        foreach (['energy' => 'energy', 'food' => 'food', 'water' => 'water'] as $k => $vk) {
            if (empty($cost[$k])) continue;
            $pc['vitals'][$vk] = max(0, (float) ($pc['vitals'][$vk] ?? 0) - (float) $cost[$k]);
        }
        $out[] = '（这一趟下来，你有点乏。）';
    }
    if (!empty($cost['sleep'])) {                                   // 睡过去：休息点往前压（同睡眠那套）
        $pc['vitals']['rest_until'] = 0;
        $out[] = '（你找了个地方眯了一会儿。）';
    }
    if (!empty($cost['money'])) { $pc['money'] = max(0, (int) ($pc['money'] ?? 0) - (int) $cost['money']); $out[] = sprintf('（花了 %d 元。）', (int) $cost['money']); }
    if (!empty($cost['items'])) {
        foreach ((array) $cost['items'] as $it => $n) {
            if (function_exists('crrg_drill_bag_add_n')) crrg_drill_bag_add_n($pc, (string) $it, -abs((int) $n));
        }
    }
    if (!empty($cost['rep'])) {                                     // ['rep' => ['base', -1]]
        if (function_exists('crrg_rep_add')) crrg_rep_add($pc, (string) $cost['rep'][0], (int) ($cost['rep'][1] ?? 0), '任务代价');
    }
    if (!empty($cost['fav'])) {                                     // ['fav' => ['zhou', -1]]
        if (function_exists('crrg_npc_fav_add')) crrg_npc_fav_add($pc, (string) $cost['fav'][0], (int) ($cost['fav'][1] ?? 0));
    }
    if (!empty($cost['san'])) { $pc['san'] = max(0, (int) ($pc['san'] ?? 0) - (int) $cost['san']); $out[] = '（心里那根弦又紧了一下。）'; }
    if (!empty($cost['hp']))  { $pc['hp']  = max(1, (int) ($pc['hp'] ?? 1) - (int) $cost['hp']); }
    if (!empty($cost['expose'])) { $s['expose_' . $task] = (int) ($s['expose_' . $task] ?? 0) + (int) $cost['expose']; $out[] = '（有人注意到你了。）'; }
    if (!empty($cost['flag']) && !in_array((string) $cost['flag'], (array) $s['flags'], true)) $s['flags'][] = (string) $cost['flag'];
    /* 2026-10-14（自查补口）：**留痕** —— 与善后系统（R9 的清理）对上 ✓ */
    if (!empty($cost['trace']) && function_exists('crrg_trace_add')) {
        $tn = max(1, (int) $cost['trace']);
        $room = (string) ($s['room'] ?? '');
        for ($i = 0; $i < $tn; $i++) crrg_trace_add($s, $room, (string) ($cost['trace_what'] ?? '痕迹'), $task);
        $out[] = '（现场留下了东西。）';
    }
    if (!empty($cost['contain_log']) && function_exists('crrg_contain_log_add')) {
        crrg_contain_log_add($pc, (string) $cost['contain_log'], (int) ($pc['uid'] ?? 0));
    }
    return $out;
}

/** 产出（个人侧）：物品/钱/声望/人情/状态/旗标 */
function crrg_task_gain_apply(&$s, &$pc, $task, array $gain, $uid = 0) {
    $out = [];
    foreach ((array) ($gain['items'] ?? []) as $it => $n) {
        if (function_exists('crrg_drill_bag_add_n')) crrg_drill_bag_add_n($pc, (string) $it, (int) $n);
        $nm = function_exists('crrg_drill_item_name') ? crrg_drill_item_name((string) $it) : $it;
        $out[] = sprintf('（得到：%s×%d。）', $nm, (int) $n);
    }
    if (!empty($gain['money'])) { $pc['money'] = (int) ($pc['money'] ?? 0) + (int) $gain['money']; $out[] = sprintf('（%+d 元。）', (int) $gain['money']); }
    if (!empty($gain['rep']))   { if (function_exists('crrg_rep_add')) crrg_rep_add($pc, (string) $gain['rep'][0], (int) ($gain['rep'][1] ?? 0), '任务产出'); }
    if (!empty($gain['fav']))   { if (function_exists('crrg_npc_fav_add')) crrg_npc_fav_add($pc, (string) $gain['fav'][0], (int) ($gain['fav'][1] ?? 0)); }
    foreach ((array) ($gain['flags'] ?? []) as $f) if (!in_array((string) $f, (array) $s['flags'], true)) $s['flags'][] = (string) $f;
    if (!empty($gain['vitals'])) {
        foreach ((array) $gain['vitals'] as $k => $dv) $pc['vitals'][$k] = min(100, max(0, (float) ($pc['vitals'][$k] ?? 0) + (float) $dv));
    }
    if (!empty($gain['sleep'])) { $pc['vitals']['rest_until'] = 0; $out[] = '（你睡了一觉。）'; }
    /* 2026-10-14（自查补口）：**把线索藏进日常** —— 任务可以往手机里塞消息 ✓（玩家自己翻出来 ✓） */
    if (!empty($gain['phone_post']) && function_exists('crrg_ph_post')) {
        $pp = (array) $gain['phone_post'];
        $kind = (string) ($pp['kind'] ?? 'group_chat');
        crrg_ph_post($kind, ['who' => (string) ($pp['who'] ?? '（有人发来一条）'), 'text' => (string) ($pp['text'] ?? '')]);
        $out[] = '（手机响了一下。）';
    }
    /* 2026-10-14（P1.9c）：往值班表里写一行（谁、哪一天、为什么）✓ 只追加版本 ✓ */
    /* 2026-10-14（R4）：往监护日志里写一行（任务产出）✓ 只追加 ✓ */
    if (!empty($gain['contain_log']) && function_exists('crrg_contain_log_add')) {
        $cl = (array) $gain['contain_log'];
        crrg_contain_log_add(['target' => (string) ($cl['target'] ?? '(任务)'), 'op' => (string) ($cl['op'] ?? '任务'),
                              'note' => (string) ($cl['note'] ?? '')], (int) ($pc['uid'] ?? 0));
        $out[] = '（监护日志上添了一行。）';
    }
    if (!empty($gain['duty_row']) && function_exists('crrg_duty_set')) {
        $dr = (array) $gain['duty_row'];
        [$ok, $msg] = crrg_duty_set((string) ($dr['post'] ?? ''), (string) ($dr['who'] ?? ''),
            $dr['day'] ?? null, (string) ($dr['why'] ?? '任务写入'), (int) ($dr['uid'] ?? 0), (int) ($pc['uid'] ?? 0));
        if ($ok) $out[] = '（值班表上添了一行。）';
    }
    return $out;
}

/* ══════════ 三、车旅对接（同战斗那套：入口带上下文，到达回来落账）══════════ */

/** 出发：把任务上下文塞进行程 ✓ */
function crrg_task_travel_start(&$s, &$pc, $task, $pid, array $path) {
    if (!function_exists('crrg_travel_start')) return ['（车旅系统没加载。）'];
    $dest = (string) ($path['dest'] ?? '');
    if ($dest === '') return ['（这条解法没写目的地。）'];
    $out = (array) crrg_travel_start($pc, $dest);
    if (!empty($pc['travel'])) {
        $pc['travel']['ctx'] = ['task' => (string) $task, 'path' => (string) $pid, 'uid' => (int) ($pc['uid'] ?? 0), 'at' => time()];
    }
    $out[] = '（这趟差事和任务连着——到了我会接着写。）';
    return $out;
}

/** 行程里挂的"待落账"收进个人档（由 pump 每次动作调 ✓ 幂等） */
function crrg_task_travel_collect(&$s, &$pc) {
    $pend = (array) get_option('crrg_travel_pending', []);
    if (!$pend) return [];
    $uid = (int) ($pc['uid'] ?? 0);
    $keep = []; $out = [];
    foreach ($pend as $p) {
        if ((int) ($p['uid'] ?? 0) !== $uid) { $keep[] = $p; continue; }
        $task = (string) $p['task']; $pid = (string) $p['path'];
        $flag = 'arrived_' . $task . '_' . $pid;
        if (in_array($flag, (array) $s['flags'], true)) continue;      // 幂等
        $s['flags'][] = $flag;
        /* 到达落"在哪个节点"（`trigger.node` 靠它 ✓ 任务因此能"到达某地才发生"） */
        $dest = (string) ($path['dest'] ?? '');
        if ($dest !== '') $s['flags'][] = 'at_node_' . preg_replace('/[^a-z0-9_]/i', '_', $dest);
        $path = function_exists('crrg_task_path') ? crrg_task_path($task, $pid) : null;
        $gain = (array) ($path['gain'] ?? []);
        foreach ((array) ($gain['clues'] ?? []) as $ck) {
            if (function_exists('crrg_clue_gain') && crrg_clue_gain($s, $pc, $task, (string) $ck)) {
                $d = (array) (crrg_task($task)['clues'][$ck] ?? []);
                $out[] = '〔线索〕' . ($d['title'] ?? $ck) . '　' . (string) ($d['point'] ?? '');
            }
        }
        if (!empty($gain['stage']) && function_exists('crrg_task_stage_set')) crrg_task_stage_set($s, $pc, $task, (string) $gain['stage']);
        foreach ((array) ($gain['narrate'] ?? []) as $x) $out[] = (string) $x;
        if (function_exists('crrg_evt_emit')) {
            crrg_evt_emit('mission.beat', ['kind' => 'travel', 'task' => $task, 'path' => $pid, 'uid' => $uid], ['src' => 'engine.links']);
        }
    }
    if ($keep !== $pend) update_option('crrg_travel_pending', $keep, false);
    return $out;
}

/* ══════════ 四、期限到点（on_expire）══════════ */

function crrg_task_expire_check(&$s, &$pc, $task) {
    $t = crrg_task($task);
    $m = function_exists('crrg_drill_mission') ? crrg_drill_mission($task) : null;
    $dl = (int) (($m['deadline_h'] ?? 0));
    if (!$t || $dl <= 0) return [];
    if (crrg_task_stage($s, $task) === '') return [];                   // 没接就不算
    if (in_array('expired_' . $task, (array) $s['flags'], true)) return [];   // 幂等
    $start = 0;
    foreach ((array) ($s['flaglog'] ?? []) as $f => $at) {
        if (strpos((string) $f, 'stage_' . $task . '_') === 0) { $start = (int) $at; break; }
    }
    $start = $start ?: (int) ($pc['mission_start_played'] ?? 0);
    if ($start <= 0) return [];
    $played = (time() - $start) / 3600.0 * 48;                           // 在线时长 × 48（与身体那套同口径）
    if ($played < $dl) return [];
    $s['flags'][] = 'expired_' . $task;
    $do = (array) ($t['outcome']['on_expire'] ?? []);
    $out = [];
    foreach ((array) ($do['narrate'] ?? []) as $x) $out[] = (string) $x;
    if (!empty($do['fact']) && function_exists('crrg_fact_propose')) {
        $f = (array) $do['fact'];
        crrg_fact_propose((string) $f[0], $f[1] ?? true, ['mode' => $f[2] ?? 'once', 'scope' => $f[3] ?? 'base'], (int) ($pc['uid'] ?? 0));
    }
    if (!empty($do['stage']) && function_exists('crrg_task_stage_set')) crrg_task_stage_set($s, $pc, $task, (string) $do['stage']);
    return $out;
}

/* ══════════ 五、多人：谁在这间屋 ══════════ */

function crrg_players_here($room, $exceptUid = 0) {
    $out = [];
    if ($room === '') return $out;
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s LIMIT 200", 'crrg_drill_meta'), ARRAY_A);
    $now = time();
    foreach ((array) $rows as $r) {
        $uid = (int) $r['user_id'];
        if ($uid === (int) $exceptUid) continue;
        $m = maybe_unserialize($r['meta_value']);
        if (!is_array($m)) continue;
        if ((string) ($m['room'] ?? '') !== (string) $room) continue;
        if (($now - (int) ($m['seen'] ?? 0)) > 900) continue;             // 15 分钟内活动过才算在场 ✓
        $out[] = $uid;
    }
    return $out;
}

/* ══════════ 六、小工具 ══════════ */
function crrg_task_fact_hour() {
    if (function_exists('crrg_world_clock')) { $c = crrg_world_clock(); return (int) ($c['h'] ?? 0); }
    return (int) date('G');
}
function crrg_task_phase_cn() {
    if (function_exists('crrg_world_clock')) { $c = crrg_world_clock(); return (string) ($c['phase'] ?? '白天'); }
    return '白天';
}
