<?php
/**
 * CRRG 演习 · 任务引擎（engine/task.php，Phase 1，2026-10-14）
 * ------------------------------------------------------------
 * 一句话：**把"任务"从一堆 room/flag 步骤，升格成一个有生命周期的对象**——
 *   触发 → 阶段 → 线索 → 结论 → 多解法 → 结算 → 善后（回收对话/NPC 复原/写公共事实）。
 *
 * 设计原则（与 14/15 号文一致）：
 *   ① **不另起一套**：仍注册进老的 mission 系统（`crrg_drill_register_mission`）→ 老界面/老结算照旧能用 ✓
 *      旧字段（name/level/steps/reward…）原样透传；引擎字段放独立的 'task' 注册表 ✓
 *   ② **一份真相**：任务的进度只看**个人档的旗标**（`stage_<任务>_<阶段>`）＋ 公共事实表（bus/facts）✓
 *   ③ **不推世界钟**：任务的时间压力一律"挂世界钟 + 惰性结算" ✓ 玩家路径永远不写世界钟 ✗
 *   ④ **善后必须写**：每个任务都要有 `cleanup`（撤对话注入 / NPC 复原 / 线索归档）✓ 门禁会查 ✓
 *
 * 作者视角的模板流程见 engine/README.md；骨架文件 missions/task-template.php。
 */
if (!defined('ABSPATH')) exit;

/** 引擎注册表 */
function &crrg_task_reg() {
    $r = &crrg_drill_reg();
    if (!isset($r['task'])) $r['task'] = [];
    return $r['task'];
}

/**
 * 定义一个任务（**模板化的唯一入口**）
 * @param string $id  任务 id（建议 m_<线>_<序>，例：m_water2）
 * @param array  $def 见 engine/README.md 的字段表
 */
function crrg_task_define($id, array $def) {
    /* ① 老系统要的字段原样透传（兼容：老面板、老结算、老门禁都还能用） */
    $legacy = [];
    foreach (['name','level','kind','min_lv','scene','brief_room','brief_name','depart_room','entry_room',
              'deadline_h','prompt','steps','reward','rep','settle_note','pass_rooms','hidden','chain_no',
              'chain_total','chain_next','need_chain','min_rep','repeat'] as $k) {
        if (array_key_exists($k, $def)) $legacy[$k] = $def[$k];
    }
    if (!array_key_exists('steps', $legacy)) $legacy['steps'] = [];      // 引擎任务可以没有步骤 ✓（防 count(null) 抛错）
    if (function_exists('crrg_drill_register_mission')) crrg_drill_register_mission($id, $legacy);

    /* ② 引擎字段 */
    $eng = [
        'id'       => $id,
        'trigger'  => $def['trigger']  ?? [],      // 怎么被拉起来
        'stages'   => $def['stages']   ?? [],      // 阶段：id => 进入条件/说明
        'clues'    => $def['clues']    ?? [],      // 线索表（clues.php 会自动登记进笔记台账）
        'concl'    => $def['concl']    ?? [],      // 结论层（治"反网状"）
        'paths'    => $def['paths']    ?? [],      // 多解法
        'actors'   => $def['actors']   ?? [],      // 参与的 NPC（含临时位置/时段）
        'newcomers'=> $def['newcomers']?? [],      // 任务专用新人物（结束即回收）
        'dlg'      => $def['dlg']      ?? [],      // 对话注入（老人物插节点/分阶段）
        'clocks'   => $def['clocks']   ?? [],      // 威胁钟（挂世界钟）
        'beats'    => $def['beats']    ?? [],      // 世界自走节拍
        'outcome'  => $def['outcome']  ?? [],      // 结算产出（个人/公共/NPC/世界）
        'cleanup'  => $def['cleanup']  ?? true,    // 善后（false＝明确声明"不需要"，门禁放行）
        'settle_flag' => $def['settle_flag'] ?? '',  // 幕尾抉择写下的旗标（没它不能交报告 ✓）
        'infer'    => $def['infer']    ?? [],      // 推论槽位（R5：一个槽位两种读法）
        'stub'     => !empty($def['stub']),        // 桩：内容待填（门禁放行，但在提醒里点名 ✓）
        /* 2026-10-22：**任务交互点**（委员长："没接这个任务，为什么会有选项"）
           ents => ['房间' => [ ['n'=>名字,'c'=>指令,'why'=>说明], … ], …]
           **只有任务进行中才出现** ✓ 没接＝看不见 ✗（不再由房间无条件挂 ✓） */
        'ents'     => $def['ents']     ?? [],
    ];
    $R = &crrg_task_reg();
    $R[$id] = $eng;

    /* ③ **自动装配**：作者只写声明，装配交给引擎（一处维护 ✓） */
    if (function_exists('crrg_clues_install')) crrg_clues_install($id);
    foreach ((array) $eng['newcomers'] as $npcId => $d) {
        if (function_exists('crrg_dlg_newcomer')) crrg_dlg_newcomer($id, (string) $npcId, (array) $d + ['why' => '任务：' . ($legacy['name'] ?? $id)]);
    }
    foreach ((array) $eng['dlg'] as $npc => $list) {
        foreach ((array) $list as $k => $def) {
            if (function_exists('crrg_dlg_inject')) crrg_dlg_inject($id, (string) $npc, (array) $def + ['id' => (string) $k]);
        }
    }
    /* 推论槽位：定义即登记（笔记里玩家做了选择才出现 ✓） */
    foreach ((array) $eng['infer'] as $slot => $def2) {
        if (function_exists('crrg_infer_def')) crrg_infer_def($id, (string) $slot, (array) $def2);
    }
    foreach ((array) $eng['actors'] as $npc => $d) {
        $d = (array) $d;
        if (!empty($d['room']) && function_exists('crrg_actor_override')) {
            crrg_actor_override($id, (string) $npc, ['room' => (string) $d['room'], 'phases' => (array) ($d['phases'] ?? []),
                'why' => '任务：' . ($legacy['name'] ?? $id)]);
        }
    }
    return true;
}

function crrg_task($id) { $R = &crrg_task_reg(); return $R[$id] ?? null; }
function crrg_tasks()   { $R = &crrg_task_reg(); return $R; }

/* ══════════ 阶段 ══════════ */

/** 阶段旗标名（一份真相：个人档旗标） */
function crrg_task_flag($id, $stage) { return 'stage_' . $id . '_' . $stage; }

/** 当前阶段：从高到低找第一个已置位的（阶段单调 ✓ 与存档护栏同源） */
function crrg_task_stage(&$s, $id) {
    $t = crrg_task($id);
    if (!$t) return '';
    $order = array_keys($t['stages']);
    $order = array_reverse($order);
    foreach ($order as $st) {
        if (in_array(crrg_task_flag($id, $st), (array) ($s['flags'] ?? []), true)) return $st;
    }
    return '';
}

/** 进入阶段（幂等；只能前进不能后退 —— 用 stages 的顺序防倒退） */
function crrg_task_stage_set(&$s, &$pc, $id, $stage) {
    $t = crrg_task($id);
    if (!$t) return false;
    $flags = (array) ($s['flags'] ?? []);
    $order = array_keys($t['stages']);
    $cur = crrg_task_stage($s, $id);
    $ci = $cur === '' ? -1 : array_search($cur, $order, true);
    $ni = array_search($stage, $order, true);
    if ($ni === false) return false;
    if ($ci !== false && $ni <= $ci) return false;                 // 倒退 → 拒绝 ✓
    $f = crrg_task_flag($id, $stage);
    if (!in_array($f, $flags, true)) {
        $s['flags'][] = $f;
        if (function_exists('crrg_evt_emit')) {
            crrg_evt_emit('mission.beat', ['kind' => 'stage', 'task' => $id, 'stage' => $stage, 'uid' => (int) ($pc['uid'] ?? 0)],
                          ['src' => 'engine.task']);
        }
    }
    return true;
}

/* ══════════ 生命周期 ══════════ */

/** 接单：置第一阶段 + 发事件 */
function crrg_task_begin(&$s, &$pc, $id) {
    $t = crrg_task($id);
    if (!$t) return false;
    $first = array_key_first($t['stages']);
    if ($first) crrg_task_stage_set($s, $pc, $id, $first);
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.accept', ['task' => $id, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'engine.task']);
    }
    return true;
}

/** 收尾：写结算产出 + 善后回收（由 outcome.php 实现细节） */
function crrg_task_finish(&$s, &$pc, $id, $result = 'done') {
    if (function_exists('crrg_task_outcome_apply')) crrg_task_outcome_apply($s, $pc, $id, $result);
    if (function_exists('crrg_task_cleanup'))      crrg_task_cleanup($s, $pc, $id, $result);
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.settle', ['task' => $id, 'result' => $result, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'engine.task']);
    }
    return true;
}

/** 每次动作顺手跑一遍：触发检查 + 节拍判定（幂等、便宜）——由 mission_tick 调 */
function crrg_task_pump(&$s, &$pc, &$out = null) {
    foreach (crrg_tasks() as $id => $t) {
        /* 触发：房间/事实/夜间 三类（够用；要更多条件就扩 crrg_task_trigger_ok） */
        if (crrg_task_stage($s, $id) === '' && crrg_task_trigger_ok($s, $pc, $t)) {
            crrg_task_begin($s, $pc, $id);
            if (function_exists('crrg_evt_emit')) {
                crrg_evt_emit('mission.beat', ['kind' => 'trigger', 'task' => $id, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'engine.task']);
            }
        }
        if (function_exists('crrg_task_beats_run')) crrg_task_beats_run($s, $pc, $id);
    }
    /* 2026-10-14（P2.5）：把打完之后"待落账"的收进个人档（幂等 ✓） */
    if (function_exists('crrg_task_fight_collect') && is_array($out)) {
        foreach ((array) crrg_task_fight_collect($s, $pc) as $x) $out[] = (string) $x;
    }
    /* 2026-10-14（P1.9）：行程到达落账 + 期限到点（都是幂等 ✓） */
    if (is_array($out)) {
        if (function_exists('crrg_task_travel_collect')) foreach ((array) crrg_task_travel_collect($s, $pc) as $x) $out[] = (string) $x;
        if (function_exists('crrg_task_expire_check')) {
            foreach (crrg_tasks() as $id => $t2) foreach ((array) crrg_task_expire_check($s, $pc, $id) as $x) $out[] = (string) $x;
        }
    }
}

/** 触发判定（声明式） */
function crrg_task_trigger_ok(&$s, &$pc, array $t) {
    $tr = (array) ($t['trigger'] ?? []);
    if (!$tr) return false;
    $flags = (array) ($s['flags'] ?? []);
    $room  = (string) ($s['room'] ?? '');
    if (!empty($tr['room']) && $room !== (string) $tr['room']) return false;
    if (!empty($tr['rooms']) && !in_array($room, (array) $tr['rooms'], true)) return false;
    if (!empty($tr['night'])) {
        $c = function_exists('crrg_world_clock') ? crrg_world_clock() : null;
        if (!$c || empty($c['night'])) return false;
    }
    foreach ((array) ($tr['flags'] ?? []) as $f) if (!in_array($f, $flags, true)) return false;
    foreach ((array) ($tr['not_flags'] ?? []) as $f) if (in_array($f, $flags, true)) return false;
    if (!empty($tr['fact']) && function_exists('crrg_fact_count')) {
        if (crrg_fact_count((string) $tr['fact'], $tr['scope'] ?? 'base') <= 0) return false;
    }
    /* 2026-10-14（接口补齐）：
       ① `cal_ev` —— **日程到点自动拉起任务**（例会/演习日/发薪…＋可限定小时窗口）
       ② `dorm`   —— **宿舍/私室内触发**
       ③ `node`   —— **到达某地图节点触发**（行程到达时落 `at_node_<目的地>` 旗标 ✓） */
    if (!empty($tr['cal_ev']) && function_exists('crrg_cal_on')) {
        $ev = (string) $tr['cal_ev'];
        if (!crrg_cal_on($ev)) return false;
        if (!empty($tr['hour']) && function_exists('crrg_cal_hour')) {
            $h = (int) crrg_cal_hour();
            $a = (int) ($tr['hour'][0] ?? 0); $b = (int) ($tr['hour'][1] ?? 24);
            if ($h < $a || $h >= $b) return false;
        }
    }
    if (!empty($tr['dorm'])) {
        $rm = (string) ($s['room'] ?? '');
        if (!in_array($rm, ['b1_room', 'b1_dorm'], true)) return false;
    }
    if (!empty($tr['node'])) {
        $f2 = 'at_node_' . preg_replace('/[^a-z0-9_]/i', '_', (string) $tr['node']);
        if (!in_array($f2, $flags, true)) return false;
    }
    /* 2026-10-14（links2）：**日程到点** 与 **宿舍内** 两类触发 ✓ */
    if (!empty($tr['event']) && function_exists('crrg_l2_event_ok') && !crrg_l2_event_ok($s, $pc, (string) $tr['event'])) return false;
    if (!empty($tr['at_dorm']) && function_exists('crrg_l2_at_dorm') && !crrg_l2_at_dorm($s)) return false;
    if (!empty($tr['min_lv'])) {
        $lv = (int) ($pc['clearance'] ?? 0);
        if ($lv < (int) $tr['min_lv']) return false;
    }
    if (empty($tr['room']) && empty($tr['rooms']) && empty($tr['fact']) && empty($tr['flags'])
        && empty($tr['night']) && empty($tr['cal_ev']) && empty($tr['dorm']) && empty($tr['node'])) {
        return false;                                                   // 空条件＝永不触发 ✓（防手滑）
    }
    return true;
}

/* ══════════ 任务交互点（2026-10-22 补：没接任务就不该能交互）══════════ */

/** 这个任务对这张卡**是否进行中**：已开始（有阶段旗标）且未完成 ✓ */
function crrg_task_active(&$s, &$pc, $id) {
    $flags = (array) ($s['flags'] ?? []);
    if (in_array('done_' . $id, $flags, true)) return false;        // 已完成 ✗
    foreach ($flags as $f) {
        if (strpos((string) $f, 'stage_' . $id . '_') === 0) return true;
    }
    return false;
}

/**
 * 该房间**此刻**生效的任务交互点（模组/房间渲染时调它 ✓ 不要再把交互点写死在房间里 ✗）
 * @return array 形如 [ ['n'=>…, 'c'=>…, 'why'=>…, 'task'=>任务id] … ]
 */
function crrg_task_ents(&$s, &$pc, $room) {
    $room = (string) $room;
    $out = [];
    foreach (crrg_tasks() as $id => $t) {
        if (!crrg_task_active($s, $pc, $id)) continue;                 // ★ 门控：没接就不出来 ✓
        foreach ((array) ($t['ents'][$room] ?? []) as $e) {
            $e = (array) $e;
            $e['task'] = $id;
            $out[] = $e;
        }
    }
    return $out;
}

/**
 * 任务调查点（2026-10-22 补）
 * 房间交互点里写的 `'c' => '调查 卧室'`，由这里处理：
 *   命中 → 回 `text`（人话，有信息）→ 按 `clues` 给线索 → 可选 `stage` 推进
 * @return array|null 没命中返回 null（让别的处理器接手）
 */
function crrg_task_survey(&$s, &$pc, $arg) {
    $arg = trim((string) $arg);
    if ($arg === '') return null;
    $room = (string) ($s['room'] ?? '');
    foreach (crrg_tasks() as $id => $t) {
        if (!crrg_task_active($s, $pc, $id)) continue;
        foreach ((array) ($t['ents'][$room] ?? []) as $e) {
            $e = (array) $e;
            $cmd = (string) ($e['c'] ?? '');
            if (strpos($cmd, '调查 ') !== 0) continue;
            /* 2026-10-22 修：**不能用 substr**（按字节切，中文会切坏 ✗ "调查 卧室" → "查 卧室"） */
            $key = trim(preg_replace('/^调查\s*/u', '', $cmd));
            if ($key === '' || mb_strpos($arg, $key) === false) continue;
            $out = ['（' . (string) ($e['n'] ?? $key) . '）'];
            foreach ((array) ($e['text'] ?? []) as $line) $out[] = (string) $line;
            /* 给线索 */
            foreach ((array) ($e['clues'] ?? []) as $c) {
                if (function_exists('crrg_clue_gain')) {
                    $r = crrg_clue_gain($s, $pc, $id, (string) $c);
                    if ($r) {
                        $ttl = (string) ((crrg_task($id)['clues'][$c]['title'] ?? '') ?: $c);
                        $out[] = '（记下了：' . $ttl . '）';
                    }
                }
            }
            /* 推阶段 */
            if (!empty($e['stage'])) crrg_task_stage_set($s, $pc, $id, (string) $e['stage']);
            return $out;
        }
    }
    return null;
}

/**
 * 案情会（2026-10-22）
 * ------------------------------------------------------------
 * 一次会议两种开法，共用同一套判定：
 *   · **单人**：与在场 NPC 会商 → 按"问话"算（没有分工，用你自己的技能）
 *   · **多人**：在场的队友（`crrg_players_here`）各按 `crrg_team` 的分工贡献一次
 *       - 问话 → 社交类 +1 奖励骰
 *       - 把风 → 暴露 -1
 *       - 带路 → 侦查 +1
 *       - 搬运 → 力量 +1
 *   汇总：**成功数 ≥ 2** 算"会开成了"（拿到全部线索）✓ 否则只拿到第一条（不堵死，下次还能再开）
 *
 * @param int $which 第几次会议（1/2/3）
 */
function crrg_task_meeting(&$s, &$pc, $which = 1) {
    $which = (int) $which;
    $room  = (string) ($s['room'] ?? '');
    $out   = [];

    /* ① 找这一场会议的定义（任务里 ents 的 c 以「开会」开头的） */
    /* 2026-10-22：**按当前阶段取这一场会**（三场会都在同一间屋，不能总是开第一场 ✗） */
    $def = null; $tid = ''; $curStage = '';
    foreach (crrg_tasks() as $id => $t) {
        if (!crrg_task_active($s, $pc, $id)) continue;
        $curStage = (string) crrg_task_stage($s, $id);
        foreach ((array) ($t['ents'][$room] ?? []) as $e) {
            $e = (array) $e;
            if (mb_strpos((string) ($e['c'] ?? ''), '开会') === false) continue;
            /* 有 stage 的只认本阶段 ✓ 没写 stage 的当通用 */
            if (!empty($e['stage']) && (string) $e['stage'] !== $curStage) continue;
            $def = $e; $tid = $id; break 2;
        }
    }
    if (!$def) return ['（这会儿没有要开的会。会上要说的东西还没到手。）'];

    $title = (string) ($def['n'] ?? ('第' . $which . '次会议'));
    $out[] = '〔' . $title . '〕';

    /* ② 在场的人：队友（多人）＋ NPC（单人也有） */
    $uid  = (int) ($pc['uid'] ?? 0);
    $mate = function_exists('crrg_players_here') ? (array) crrg_players_here($room, $uid) : [];
    $mate = array_values(array_filter(array_map('intval', $mate), fn($x) => $x > 0 && $x !== $uid));
    $solo = count($mate) === 0;

    $who = (array) ($def['who'] ?? []);
    if ($who) $out[] = '在座：' . implode('、', array_map('strval', $who)) . '。';
    foreach ((array) ($def['text'] ?? []) as $line) $out[] = (string) $line;
    if ($solo) {
        $out[] = '屋里只有你和所里的人。材料摊在桌上，一条一条过。';
    } else {
        $out[] = sprintf('屋里坐了 %d 个人（连你）。谁看哪一段，得先分。', count($mate) + 1);
    }

    /* ③ 判定：单人一次；多人每人一次（按分工加值） */
    $succ = 0; $rolls = [];
    $roles = function_exists('crrg_team_roles') ? (array) crrg_team_roles() : [];
    $who = $solo ? [(int) ($pc['uid'] ?? 0)] : array_merge([(int) ($pc['uid'] ?? 0)], $mate);
    foreach ($who as $i => $u) {
        $bonus = 0; $label = '你自己';
        if (!$solo) {
            foreach ($roles as $rk => $cn) {
                if (function_exists('crrg_team_has') && crrg_team_has($pc, $rk)) {
                    if ($i > 0) { $bonus = 1; $label = $cn . '的那位'; break; }
                }
            }
        }
        $roll = function_exists('crrg_coc_roll100') ? crrg_coc_roll100() : mt_rand(1, 100);
        $bar  = $solo ? 60 : 55;                       /* 会议不是硬仗，门槛给得宽 */
        $ok   = ($roll <= $bar) || ($bonus && $roll <= $bar + 15);
        if ($ok) $succ++;
        $rolls[] = [$label, $roll, $ok];
        $out[] = sprintf('　· %s：D100＝%d，%s', $label, $roll, $ok ? '说到点子上' : '说了，但没人接');
    }

    /* ④ 结果：≥2 成功拿全部；否则只拿第一条 */
    $clues = (array) ($def['clues'] ?? []);
    $need = $solo ? 1 : 2;                 /* 单人只要一次说到点子上 ✓ 多人要两个人接上 */
    if ($succ >= $need) {
        $out[] = '── 会开成了。几段材料对上了，有人当场把话说漏。';
        $tp = (array) (crrg_task($tid)['clues'] ?? []);
        foreach ($clues as $c) {
            if (function_exists('crrg_clue_gain')) {
                crrg_clue_gain($s, $pc, $tid, (string) $c);
                $out[] = '（记下：' . (string) ($tp[$c]['title'] ?? $c) . '）';
            }
        }
        if (!empty($def['stage'])) crrg_task_stage_set($s, $pc, $tid, (string) $def['stage']);
    } else {
        $out[] = '── 会没开成。各说各的，最后只落到一条能用的。';
        if ($clues && function_exists('crrg_clue_gain')) {
            $tp = (array) (crrg_task($tid)['clues'] ?? []);
            crrg_clue_gain($s, $pc, $tid, (string) $clues[0]);
            $out[] = '（记下：' . (string) ($tp[$clues[0]]['title'] ?? $clues[0]) . '）';
        }
        $out[] = '（材料还在。明天可以再开一次。）';
    }
    return $out;
}

/**
 * 任务内人物问话（2026-10-22 补）
 * 任务 newcomeers 里的人不在对话表里 → 「问 侏儒」会落到"这儿没有这个人" ✗
 * 这里：按**姓名**在同房间的任务新人物里找 → 有 nodes 就出第一条（或按关键词选）→ 给线索
 * @return array|null 未命中返回 null
 */
function crrg_task_ask(&$s, &$pc, $who) {
    $who = trim((string) $who);
    if ($who === '') return null;
    $room = (string) ($s['room'] ?? '');
    foreach (crrg_tasks() as $id => $t) {
        if (!crrg_task_active($s, $pc, $id)) continue;
        foreach ((array) ($t['newcomers'] ?? []) as $nid => $d) {
            $d = (array) $d;
            $nm = (string) ($d['name'] ?? '');
            if ($nm === '' || mb_strpos($nm, $who) === false) continue;
            if (($d['room'] ?? '') !== $room) continue;          /* 必须同房间 ✓ */
            $nodes = (array) ($d['nodes'] ?? []);
            if (!$nodes) return ['（' . $nm . '不接话。）'];
            $out = ['〔' . $nm . '〕'];
            foreach ($nodes as $k => $nd) {
                $nd = (array) $nd;
                $out[] = '　· ' . (string) ($nd['label'] ?? $k);
            }
            $out[] = '（用「问 ' . $nm . ' <话题>」接着问。）';
            return $out;
        }
    }
    return null;
}

/** 任务人物的**具体话题**（带话题参数时走这里） */
function crrg_task_ask_topic(&$s, &$pc, $who, $topic) {
    $who = trim((string) $who); $topic = trim((string) $topic);
    if ($who === '' || $topic === '') return null;
    $room = (string) ($s['room'] ?? '');
    foreach (crrg_tasks() as $id => $t) {
        if (!crrg_task_active($s, $pc, $id)) continue;
        foreach ((array) ($t['newcomers'] ?? []) as $nid => $d) {
            $d = (array) $d;
            $nm = (string) ($d['name'] ?? '');
            if ($nm === '' || mb_strpos($nm, $who) === false) continue;
            if (($d['room'] ?? '') !== $room) continue;
            foreach ((array) ($d['nodes'] ?? []) as $k => $nd) {
                $nd = (array) $nd;
                $lbl = (string) ($nd['label'] ?? '');
                if ($lbl !== '' && (mb_strpos($lbl, $topic) !== false || mb_strpos($topic, $lbl) !== false)) {
                    $out = [];
                    foreach ((array) ($nd['text'] ?? []) as $line) $out[] = (string) $line;
                    if (!empty($nd['stage'])) crrg_task_stage_set($s, $pc, $id, (string) $nd['stage']);
                    /* 2026-10-22：**effect 要真落地**（clues/flags） */
                    $ef = (array) ($nd['effect'] ?? []);
                    $tp = (array) (($t['clues'] ?? []));
                    foreach ((array) ($ef['clues'] ?? []) as $c) {
                        if (function_exists('crrg_clue_gain')) {
                            crrg_clue_gain($s, $pc, $id, (string) $c);
                            $out[] = '（记下：' . (string) ($tp[$c]['title'] ?? $c) . '）';
                        }
                    }
                    foreach ((array) ($ef['flags'] ?? []) as $fl) {
                        if ($fl !== '' && !in_array($fl, (array) $s['flags'], true)) $s['flags'][] = (string) $fl;
                    }
                    return $out ?: ['（他没接话。）'];
                }
            }
            return ['（他没听懂你要问什么。）'];
        }
    }
    return null;
}

/**
 * 任务内**收容**（2026-10-22）
 * 交互点写 `'c' => '收容 传送门'`、条目带 `'contain' => 'O-10-02-001'`：
 *   → 写旗标 `contained_<编号>`、落一条 `contain_log`（有系统就用系统 ✓）、输出验收文案
 * 结算时 `outcome.archive` 才真正入档（这里只记账 ✓）
 */
function crrg_task_contain(&$s, &$pc, $who) {
    $who = trim((string) $who);
    if ($who === '') return null;
    $room = (string) ($s['room'] ?? '');
    foreach (crrg_tasks() as $id => $t) {
        if (!crrg_task_active($s, $pc, $id)) continue;
        foreach ((array) ($t['ents'][$room] ?? []) as $e) {
            $e = (array) $e;
            if (mb_strpos((string) ($e['c'] ?? ''), '收容') === false) continue;
            if ($who !== '' && mb_strpos((string) ($e['n'] ?? ''), $who) === false && mb_strpos((string) ($e['c'] ?? ''), $who) === false) continue;
            $code = (string) ($e['contain'] ?? '');
            $out = ['（' . (string) ($e['n'] ?? $who) . '）'];
            foreach ((array) ($e['text'] ?? []) as $line) $out[] = (string) $line;
            if ($code !== '') {
                $fl = 'contained_' . $code;
                if (!in_array($fl, (array) $s['flags'], true)) {
                    $s['flags'][] = $fl;
                    if (function_exists('crrg_fact_add')) {
                        crrg_fact_add('base.contain.' . $code, ['by' => (int) ($pc['uid'] ?? 0), 'task' => $id], 'base', (int) ($pc['uid'] ?? 0));
                    }
                    $out[] = '（已收容：' . $code . '。回去交报告时一并入册。）';
                } else {
                    $out[] = '（这件已经收容过了。）';
                }
            }
            if (!empty($e['stage'])) crrg_task_stage_set($s, $pc, $id, (string) $e['stage']);
            return $out;
        }
    }
    return null;
}

/**
 * 交报告 · 结局分支（2026-10-29）
 * ------------------------------------------------------------
 * 按**轨道**分结局（推论槽位累积出来的 track_<任务>_ren|gang）：
 *   · ren  （人说）多  → 你写的是"人干的"：镇子自己烂掉了，不是别的东西
 *   · gang（岗位说）多 → 你写的是"位置有问题"：地底下那扇门，比人重要
 *   · 平局             → 你两份都写，谁也得罪不了，谁也没定下来
 * 交完置 `settle_flag`，调 `crrg_task_finish()` 走 outcome/archive/cleanup。
 */
function crrg_task_report(&$s, &$pc, $id = '') {
    $id = $id !== '' ? $id : '';
    if ($id === '') {
        foreach (crrg_tasks() as $k => $t) if (crrg_task_active($s, $pc, $k)) { $id = $k; break; }
    }
    if ($id === '' || !crrg_task($id)) return ['（没有要交的报告。）'];
    if (!crrg_task_active($s, $pc, $id)) return ['（这单还没开始，或者已经结过了。）'];

    $ren  = (int) ($pc['track_' . $id . '_ren'] ?? 0);
    $gang = (int) ($pc['track_' . $id . '_gang'] ?? 0);
    $out = [];

    if ($ren > $gang) {
        $out[] = '〔结案报告〕你把报告写成了一件事：**这里有人借着一场病，做了十年不该做的事**。';
        $out[] = '李丹的名字出现在第三页；赵二狗在附页签了字，手有点抖。';
        $out[] = '镇上有人不服，但没人敢在纸上写不服。';
        $out[] = '（结局：人说）';
        $s['flags'][] = 'gt_end_ren';
    } elseif ($gang > $ren) {
        $out[] = '〔结案报告〕你把报告写成了一份**位置说明**：坡下那扇门，比上面所有人都重要。';
        $out[] = '报告上交以后没有回音，三天后有人来把场地整平了，铁门和台阶一起填掉。';
        $out[] = '镇上照旧过日子，只是东街再没人提那盏灯。';
        $out[] = '（结局：岗位说）';
        $s['flags'][] = 'gt_end_gang';
    } else {
        $out[] = '〔结案报告〕两种说法你都写了，各占一半，结论栏空着。';
        $out[] = '上面把报告退回来一次，让你"再想想"。你交上去的还是同一份。';
        $out[] = '这件事就这么挂着——挂着的案子，谁也说不清是谁的。';
        $out[] = '（结局：悬而未决）';
        $s['flags'][] = 'gt_end_open';
    }

    $out[] = '────────────────';
    $out[] = '〔后日谈〕';
    $out[] = '青石镇下了半个月的雨。卫生院后院那两间平房锁上了，钥匙不知道在谁手里。';
    $out[] = '叶敏的取药单没人来认领，压在派出所的抽屉最底下。';
    $out[] = '废品站的孙德发说，那几天夜里再没见过推板车的人。';
    $out[] = '（有人说案子结了，有人说没结。你要哪一句，自己去问。）';

    $s['flags'][] = 'settle_' . $id;
    if (function_exists('crrg_task_finish')) crrg_task_finish($s, $pc, $id, 'done');
    return $out;
}
