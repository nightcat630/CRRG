<?php
/**
 * 自 includes/drill-hub.php 搬出的函数（2026-09-30）
 */
if (!defined('ABSPATH')) exit;

function crrg_drill_mission($id) { $m = crrg_drill_missions(); return $m[$id] ?? null; }
/** 跨场景查任务（连续任务的下一段可能注册在别的场景，如镇上） */
function crrg_drill_mission_any($id) {
    $r = &crrg_drill_reg();
    if (isset($r['missions'][$id])) return $r['missions'][$id];
    $m = crrg_drill_mission($id);
    return $m;
}
/** 「任务」只列在手的合约（可接的在值班板／NPC 面板上点） */
function crrg_drill_mission_lines($pc) {
    $out = [];
    $cur = $pc['mission'] ?? '';
    if ($cur && ($m = crrg_drill_mission($cur))) {
        $out[] = '在手：' . $m['name'];
        if (!empty($m['prompt'])) $out[] = '　' . $m['prompt'];
        $dep = $m['depart_room'] ?? '';
        if ($dep) $out[] = '出发点：' . (crrg_drill_rooms()[$dep]['t'] ?? '—');
        $out[] = '　（线索与情报用「笔记 ' . $m['name'] . '」看；交代与结算在界面上点。）';
    } else {
        $out[] = '手上没有合约。';
        $out[] = '可接的合约在「值班板」（指挥大厅／作战值班室）或找 NPC 说话——界面上点。';
    }
    $done = (array) ($pc['mission_done'] ?? []);
    if ($done) {
        $names = [];
        foreach ($done as $id) {
            $m = crrg_drill_mission($id);
            $nm = $m ? $m['name'] : $id;
            if (function_exists('crrg_retired_id') && crrg_retired_id($id)) $nm .= '（已封存）';   // 已收官内容 ✓
            $names[] = $nm;
        }
        $out[] = '已完成：' . implode('、', $names);
    }
    return $out;
}
/** 每步进度推进：按房间或按指令触发 */
function crrg_drill_mission_tick(&$s, &$pc, $cmd, &$out) {
    /* 2026-10-14（bus P0）：**世界推进器搭在"每次动作"上** ✓ —— 惰性补算，不轮询 ✗
       P0 还没有订阅者，所以这里只推进游标、不改任何局面 ✓（行为与改造前一致 ✓） */
    if (function_exists('crrg_tick')) crrg_tick();
    /* 2026-10-14（engine）：任务引擎每次动作跑一遍 —— 触发检查 + 节拍判定（幂等、便宜 ✓） */
    if (function_exists('crrg_task_pump')) crrg_task_pump($s, $pc, $out);
    if (function_exists('crrg_mad_tick')) crrg_mad_tick($pc);
    if (function_exists('crrg_weather_san_tick')) foreach ((array) crrg_weather_san_tick($pc, $s) as $x) $out[] = (string) $x;   // 夜雾（R10）   // 理智：临时疯狂计时/换日复位
    /* 2026-10-14：**手上的活要是已收官的内容 → 自动归档**（清指针、留旗标、给文案 ✓ 幂等 ✓）
       背景：委员长把《封条》整条下线了；正在跑的玩家不能就这么卡住 ✗ */
    if (function_exists('crrg_retired_id')) {
        foreach (['mission', 'mission2'] as $slot) {
            if ($slot === 'mission') {
                $mid = (string) ($pc['mission'] ?? '');
            } else {
                $mid = (string) ($pc['mission2']['id'] ?? '');
            }
            if ($mid === '' || !crrg_retired_id($mid)) continue;
            $flag = 'retired_' . $mid;
            if (!in_array($flag, (array) $s['flags'], true)) {
                $s['flags'][] = $flag;                                  // 幂等标记 ✓
                $out[] = '（值班室把这份单子收回去了：这条线按旧档封存，不再派。）';
                $out[] = '（你做过的记录都在档案里，没作废。）';
            }
            if ($slot === 'mission') { $pc['mission'] = ''; $pc['mission_step'] = 0; $pc['mission_start'] = 0; }
            else { $pc['mission2'] = ['id' => '', 'step' => 0, 'start' => 0]; }
        }
    }
    if (function_exists('crrg_cal_pump')) crrg_cal_pump($s, $pc, $out);   // 日历：到点提示（幂等 ✓ 不另起定时任务）
    /* 2026-10-01（玩家报"五条线全齐却打不上勾、决断说没凑齐"✗，死锁 ✓）：
       **每一步都顺手"补发该发的步骤旗标"** ✓ —— 例：第二幕「五条线凑齐」原来只挂在"调查"钩子上 ✗，
       线是细问来的就永远不发 ✓ → 凑齐了也不算 ✓。这类"凑够就成立"的判定应当**每次动作都查** ✓（幂等、便宜 ✓） */
    if (($pc['mission'] ?? '') === 'm_mid_seal2' && function_exists('crrg_seal2_check_complete')) {
        foreach ((array) crrg_seal2_check_complete($s, $pc) as $x) $out[] = (string) $x;
    }
    /* 《封条》：库存按游戏日衰减 + 阈值事件 */
    if (function_exists('crrg_seal_daily_decay')) {
        crrg_seal_daily_decay($pc);
        if (function_exists('crrg_seal3_events') && is_array($out)) $out = crrg_seal3_events($s, $pc, $out);
    }
    /* 《封条》：硬来／绕路的后续检测 */
    if (function_exists('crrg_seal_pending_check') && is_array($out)) $out = crrg_seal_pending_check($s, $pc, $out);
    /* 期限：逾期自动退回 */
    if ($id2 = ($pc['mission'] ?? '')) {
        $dl = crrg_drill_mission_deadline($pc, $id2);
        if ($dl) { $pc['mission'] = ''; $pc['mission_step'] = 0; if (!is_array($s['flags'] ?? null)) $s['flags'] = []; $s['flags'][] = '结局_逾期'; if (is_array($out)) $out[] = $dl; return; }
    }
    foreach ([1, 2] as $slot) {
        $id = $slot === 1 ? ($pc['mission'] ?? '') : ($pc['mission2']['id'] ?? '');
        if (!$id || !($m = crrg_drill_mission($id))) continue;
        if (function_exists('crrg_drill_evidence_tick')) crrg_drill_evidence_tick($s, $m);
        $i = (int) ($slot === 1 ? ($pc['mission_step'] ?? 0) : ($pc['mission2']['step'] ?? 0));
        $cur = $i; $total = count($m['steps']);
        for ($guard = 0; $guard < $total; $guard++) {   // 条件满足就连推，但**不向玩家报进度**
            if ($i >= $total) break;
            if (!crrg_drill_step_done($m['steps'][$i], $s, $pc, $cmd)) break;
            $i++;
        }
        if ($i !== $cur) {
            if ($slot === 1) $pc['mission_step'] = $i;
            else { crrg_drill_mission2($pc); $pc['mission2']['step'] = $i; }
        }
    }
}
/** 日常工作槽位：接受一条日常（与主线并存） */
function crrg_drill_daily_accept(&$pc, $id) {
    crrg_drill_mission2($pc);
    $pc['mission2'] = ['id' => $id, 'step' => 0, 'start' => function_exists('crrg_vitals_now') ? crrg_vitals_now() : time()];
    $pc['mission_start_played'] = crrg_played_sec($pc);      // 期限按在线时长算
    return true;
}

/** 任务期限检查（在 tick 里调用）：超时返回提示行 */
function crrg_drill_mission_deadline(&$pc, $id) {
    $m = crrg_drill_mission($id);
    if (empty($m['deadline_h'])) return null;
    /* 2026-09-29 改：期限按**在线时长**算（离线暂停）——世界钟是全局的，不替个人停 */
    $deadH = (int) $m['deadline_h'];
    if (empty($pc['mission_start_played'])) $pc['mission_start_played'] = crrg_played_sec($pc);   // 旧合约迁移：从现在起算
    $playedH = (crrg_played_sec($pc) - (int) $pc['mission_start_played']) * 48 / 3600.0;
    if ($playedH <= $deadH) return null;
    return sprintf('（%s 已逾期——单子被自动退回。注：计时按你**在线**的时长算，离线不计。）', $m['name']);
}
function crrg_drill_mission_settle(&$pc, &$ctx, $uid = 0, &$s = null) {
    /* 2026-10-14（engine）：结算走引擎（个人/公共/NPC/世界产出 + 善后回收）——
       老任务没有引擎定义时直接跳过 ✓ 老线零影响 ✓ */
    if (is_array($s) && function_exists('crrg_task') && crrg_task((string) ($pc['mission'] ?? ''))) {
        crrg_task_finish($s, $pc, (string) $pc['mission'], 'done');
    }
    /* 谁走完了就结谁：主线没走完而日常走完了 → 先结日常 */
    $mainId = $pc['mission'] ?? '';
    $mainDone = false;
    if ($mainId && ($mm = crrg_drill_mission($mainId))) $mainDone = (int) ($pc['mission_step'] ?? 0) >= count($mm['steps']);
    if (!$mainDone && ($pc['mission2']['id'] ?? '') !== '') {
        $m2 = crrg_drill_mission($pc['mission2']['id']);
        if ($m2 && (int) ($pc['mission2']['step'] ?? 0) >= count($m2['steps'])) {
            $pc['mission'] = $pc['mission2']['id'];
            $pc['mission_step'] = (int) $pc['mission2']['step'];
            $pc['mission_start'] = (int) ($pc['mission2']['start'] ?? 0);
            $pc['mission2'] = ['id' => '', 'step' => 0, 'start' => 0];
        }
    }
    $id = $pc['mission'] ?? '';
    if (!$id || !($m = crrg_drill_mission($id))) return [null, ['当前没有可结算的合约。']];
    /* 2026-10-14（R5）：**引擎任务没有 steps，就不能靠"步数=0"混结算** ✗
       → 作者在任务里写 settle_flag（幕尾抉择写下的那个旗标），没它就交不了 ✓ 老任务零影响 ✓ */
    if (function_exists('crrg_task') && ($tEngine = crrg_task($id)) && !empty($tEngine['settle_flag'])
        && !in_array((string) $tEngine['settle_flag'], (array) ($s['flags'] ?? []), true)) {
        return [null, ['（还交不了——这件事你自己还没想清楚。）']];
    }
    if ((int) $pc['mission_step'] < count($m['steps'])) {
        /* 2026-10-01 改进：**把第一个没走完的步骤报出来** —— 原来只说"目标还没走完" ✗，
           玩家（尤其镇上）不知道还缺什么，只能来回跑（今天的反馈就是这么来的 ✗） */
        /* 2026-10-01 修（林晓雅报障"窗口打勾、结算说缺——两套数据"✓）：
           原来从 `mission_step` 往后**照序号全列** ✗ → 把**已完成**（旗标在）的步骤也列成"缺"✗
           现在：**只列真正没完成的**（按各自的完成条件判 ✓ 与窗口同一判据 ✓） */
        $miss = [];
        /* 本函数签名里没有 `$s` ✗ → 用 `$uid` 自己取一份（只为**显示**判据 ✓；
           真正的闸门仍是 `mission_step` ✓ 不靠它放行 ✓） */
        $sF = ($uid && function_exists('crrg_drill_state')) ? (array) crrg_drill_state($uid) : [];
        foreach ((array) $m['steps'] as $i => $st) {
            if ($i < (int) $pc['mission_step']) continue;                       // 进度已过 → 算完成 ✓
            if (function_exists('crrg_drill_step_done') && crrg_drill_step_done($st, $sF, $pc, '')) continue;  // 条件已满足 → 也算完成 ✓
            $miss[] = ['text' => (string) ($st['text'] ?? ''), 'flag' => (string) ($st['flag'] ?? '')];
        }
        $tip = [];
        foreach (array_slice($miss, 0, 3) as $x) {
            $tip[] = $x['text'] . ($x['flag'] !== '' ? '（缺：' . $x['flag'] . '）' : '');
        }
        return [null, array_merge(
            [sprintf('目标还没走完，结算不了（进度 %d／%d）。', (int) $pc['mission_step'], count($m['steps']))],
            ['还差：'],
            array_map(fn($t) => '　· ' . $t, $tip),
            ['（在「合约」窗口能看全部步骤与打勾情况。）']
        )];
    }
    if (!empty($m['require_flags'])) {          // 前置旗标没齐就不给结（如：还没向委托人交代）
        $st = $uid ? crrg_drill_state($uid) : [];
        foreach ((array) $m['require_flags'] as $f) {
            if (!in_array($f, (array) ($st['flags'] ?? []), true)) {
                return [null, ['这单还收不了尾：先把结果跟当事人交代清楚。']];
            }
        }
    }
    $cr = (int) ($m['reward']['credits'] ?? 0);
    $mo = (int) ($m['reward']['money'] ?? 0);
    $lines = [sprintf('合约「%s」结算完成。', $m['name'])];
    /* 可重复日常：结算时掷一次随机结果（文本／额外奖励／旗标与效果） */
    $randEff = [];
    if (!empty($m['random']) && function_exists('crrg_daily_outcome')) {
        $st = $uid ? crrg_drill_state($uid) : [];
        $oc = crrg_daily_outcome($m['random'], $pc, $st);
        foreach ((array) $oc['lines'] as $x) $lines[] = $x;
        $cr += (int) ($oc['reward']['credits'] ?? 0);
        $mo += (int) ($oc['reward']['money'] ?? 0);
        $randEff = (array) $oc['effects'];
        if ($uid && $randEff) { crrg_daily_apply_effects($pc, $st, $randEff, $lines); crrg_drill_save($uid, $st); }
    }
    /* 声望：任务定义里写了 rep 就按它，没写按类型给默认值 */
    if (function_exists('crrg_rep_add')) {
        $reps = (array) ($m['rep'] ?? []);
        if (!$reps) {
            if (($m['scene'] ?? 'base') === 'mod_wild')      $reps = ['base' => 3];
            elseif (($m['kind'] ?? '') === 'npc')            $reps = ['base' => 2];
            elseif (($m['kind'] ?? '') === 'base')           $reps = ['base' => 1];
        }
        foreach ($reps as $loc => $dv) {
            if (!$dv) continue;
            $now = crrg_rep_add($pc, $loc, (int) $dv, '完成：' . $m['name']);
            $pn  = crrg_rep_places()[$loc]['short'] ?? $loc;
            $lines[] = sprintf('（%s声望 %+d → %d）', $pn, (int) $dv, $now);
        }
    }
    $team  = $uid ? crrg_drill_team_of($uid) : null;
    $split = [];
    if ($team && count($team['members']) > 1) {
        foreach ($team['members'] as $mb) {
            $pct = (int) ($team['share'][(string) $mb] ?? 0);
            if ($pct > 0) $split[] = [(int) $mb, $pct];
        }
    }
    if ($split) {
        foreach ($split as [$mb, $pct]) {
            $mine = (int) $mb === (int) $uid;
            $mpc  = $mine ? $pc : crrg_drill_pc_defaults(crrg_drill_pc($mb));
            $mpc['credits'] += (int) floor($cr * $pct / 100);
            $mpc['money']   += (int) floor($mo * $pct / 100);
            if ($mine) { $pc['credits'] = $mpc['credits']; $pc['money'] = $mpc['money']; }
            else crrg_drill_pc_save($mb, $mpc);
            $u = get_userdata($mb);
            $lines[] = sprintf('  分红 %d%% → %s：+%d 积分、+%d 元', $pct,
                $u ? $u->display_name : ('#' . $mb), (int) floor($cr * $pct / 100), (int) floor($mo * $pct / 100));
        }
    } else {
        $pc['credits'] = (int) ($pc['credits'] ?? 0) + $cr;      // 2026-10-01：空档也要能结算（原来会出未定义键 warning ✗）
        $pc['money']   = (int) ($pc['money']   ?? 0) + $mo;
        $lines[] = $cr > 0 ? sprintf('+%d 积分、+%d 元。', $cr, $mo) : sprintf('+%d 元（跑腿委托不计积分）。', $mo);
    }
    if (!empty($m['bonus_flags'])) {              // 额外条件加成（如样本已寄回）
        $st2 = $uid ? crrg_drill_state($uid) : [];
        foreach ((array) $m['bonus_flags'] as $f => $b) {
            if (in_array($f, (array) ($st2['flags'] ?? []), true)) {
                $cr += (int) ($b['credits'] ?? 0); $mo += (int) ($b['money'] ?? 0);
                $lines[] = sprintf('（%s：额外 +%d 积分、+%d 元。）', $b['why'] ?? $f, (int) ($b['credits'] ?? 0), (int) ($b['money'] ?? 0));
            }
        }
    }
    if (!in_array($id, (array) $pc['mission_done'], true)) $pc['mission_done'][] = $id;
    /* 跑腿委托：给该 NPC +1 好感（2026-09-29；此前跑腿不加好感，"找人办事"的门槛等于无路可提） */
    if (function_exists('crrg_npc_by_task')) {
        $npcWho = crrg_npc_by_task($id);
        if ($npcWho !== '') { $nv = crrg_npc_fav_add($pc, $npcWho, 1); $lines[] = sprintf('（%s 好感 +1 → %d：%s）', $npcWho, $nv, crrg_npc_fav_tier($nv)); }
    }
    if (function_exists('crrg_drill_mission_repeat_mark')) crrg_drill_mission_repeat_mark($pc, $id);
    $pc['mission'] = '';
    $pc['mission_step'] = 0;
    $pc['train_tokens'] = min(9, (int) ($pc['train_tokens'] ?? 0) + 1);
    $lines[] = sprintf('当前：积分 %d ／ 钱 %d ｜ 锻炼机会 +1（现有 %d 次）',
        $pc['credits'], $pc['money'], (int) $pc['train_tokens']);
    if (!empty($m['settle_note'])) $lines[] = $m['settle_note'];
    /* 连续任务：结算后自动接过下一段（2026-09-29，不再让玩家一遍遍接单） */
    $chainNext = (string) ($m['chain_next'] ?? '');
    if ($chainNext !== '' && ($nm = crrg_drill_mission_any($chainNext))) {
        /* 演习权限：与接单处同一口径（花积分兑换的 clearance 与职务卡级取大者） */
        $lv = max((int) ($pc['clearance'] ?? 0), $uid && function_exists('crrg_drill_ctx') ? (int) crrg_drill_ctx($uid)['lv'] : 0);
        if ($lv >= (int) ($nm['min_lv'] ?? 3)) {
            $pc['mission'] = $chainNext;
            $pc['mission_step'] = 0;
            $pc['mission_start'] = function_exists('crrg_vitals_now') ? crrg_vitals_now() : time();
            $pc['mission_start_played'] = crrg_played_sec($pc);   // 期限按在线时长算（2026-09-29）
            if (function_exists('crrg_seal_on_accept')) { $ss = []; crrg_seal_on_accept($ss, $pc, $chainNext); }
            $lines[] = '（事还没完——下一段已经挂到你名下，直接接着做。查看面板上的「在手的合约」。）';
        } else {
            $lines[] = sprintf('（后面还有一段，需要 %s 级才能接。手上这段算交付。）', crrg_drill_clearance_name((int) ($nm['min_lv'] ?? 3)));
        }
    }

    return [$m, $lines];
}

/** 实据合并：模组可用 'evidence' 列出一组标记，命中任一即视为「拿到实据」 */
function crrg_drill_evidence_tick(&$s, $m) {
    if (empty($m['evidence'])) return;
    foreach ((array) $m['evidence'] as $f) {
        if (in_array($f, (array) ($s['flags'] ?? []), true)) {
            if (!in_array('线索_实据', (array) $s['flags'], true)) $s['flags'][] = '线索_实据';
            return;
        }
    }
}

/* ================= 宿舍 ================= */
/* ── 可重复任务（2026-09-28）：'repeat' => 'daily'（每游戏日一次）或 'cooldown_h' => N（游戏小时） ── */
/** 日常任务的第二槽位（允许"一边做主线一边做日常"） */
function crrg_drill_mission2(&$pc) {
    if (!isset($pc['mission2']) || !is_array($pc['mission2'])) $pc['mission2'] = ['id' => '', 'step' => 0, 'start' => 0];
    return $pc['mission2'];
}
function crrg_drill_mission_repeat_state($pc, $id) {
    $m = crrg_drill_mission($id);
    if (!$m || empty($m['repeat'])) return ['can' => false, 'why' => '这个合约是一次性的。'];
    $log = (array) ($pc['mission_log'] ?? []);
    $last = (int) ($log[$id] ?? 0);
    if ($last <= 0) return ['can' => true, 'why' => ''];
    $now = function_exists('crrg_vitals_now') ? crrg_vitals_now() : time();
    if (($m['repeat'] ?? '') === 'daily') {
        $d1 = function_exists('crrg_wdate') ? crrg_wdate('Ymd', $last) : date('Ymd', $last);
        $d2 = function_exists('crrg_wdate') ? crrg_wdate('Ymd', $now) : date('Ymd', $now);
        if ($d1 !== $d2) return ['can' => true, 'why' => ''];
        $left = max(0, 86400 - (($now - $last) % 86400));
        return ['can' => false, 'why' => sprintf('今天的份已经做过了（约 %.1f 游戏小时后可再接）。', $left / 3600)];
    }
    $cd = (int) ($m['cooldown_h'] ?? 0) * 3600;
    if ($now - $last >= $cd) return ['can' => true, 'why' => ''];
    return ['can' => false, 'why' => sprintf('冷却中（还剩约 %.1f 游戏小时）。', ($cd - ($now - $last)) / 3600)];
}
function crrg_drill_mission_repeat_mark(&$pc, $id) {
    $m = crrg_drill_mission($id);
    if (!$m || empty($m['repeat'])) return;
    if (!isset($pc['mission_log']) || !is_array($pc['mission_log'])) $pc['mission_log'] = [];
    $pc['mission_log'][$id] = function_exists('crrg_vitals_now') ? crrg_vitals_now() : time();
}

/* ── 路线指引：从 from 到 to 的最短路径（用房间出口图算），给玩家"怎么走" ── */
function crrg_drill_step_done($st, $s, $pc, $cmd) {
    if (!empty($st['night']) && function_exists('crrg_world_clock') && !crrg_world_clock()['night']) {
        return false;                       // 限定夜间的步骤：白天不推进
    }
    if (!empty($st['have'])) {                       // 例：['have' => ['lamp_cold' => 2]]
        foreach ((array) $st['have'] as $id => $need) {
            if (crrg_drill_own_count($pc, $id) < (int) $need) return false;
        }
        return true;
    }
    if (!empty($st['room']) && ($s['room'] ?? '') === $st['room']) return true;
    /* 场景级判定（2026-09-29）：'进镇'这类步骤不该绑死某一个房间——
       「出发」落在主街东段、「长途」落在镇口，两条路都得算到达 */
    if (!empty($st['scene']) && function_exists('crrg_drill_cur_scene') && crrg_drill_cur_scene() === $st['scene']) return true;
    if (!empty($st['cmd'])  && mb_strpos((string) $cmd, $st['cmd']) === 0) return true;
    if (!empty($st['flag']) && in_array($st['flag'], (array) ($s['flags'] ?? []), true)) return true;
    if (!empty($st['any'])) {
        foreach ((array) $st['any'] as $f) if (in_array($f, (array) ($s['flags'] ?? []), true)) return true;
    }
    return false;
}

/** 给已注册场景里的某个房间追加一个出口（不重定义整间房） */
