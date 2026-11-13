<?php
/**
 * CRRG 演习 · 战斗接入（engine/fight.php，P2.5，2026-10-14）
 * ------------------------------------------------------------
 * 治 15 号文实测到的那条：**战斗是孤岛** —— 结束只写 `over=true` ＋ 一行 log ✗
 *   （不给线索、不写旗标、不改局面、不掉落 ✓ 等于白打）
 *
 * 现在两头接上：
 *   入口：`crrg_task_try()` 遇到 `type => 'fight'` 的解法 → `crrg_task_fight_start()`
 *        —— 把**任务上下文**塞进战斗状态（`$c['ctx']`），并持久化 ✓（跨请求不丢 ✓）
 *   出口：战斗判定 `over` 那一瞬 → `crrg_task_fight_end($c)`
 *        —— 按上下文**落四样**：① 胜负旗标 ② 公共事实 ③ 局面变化 ④ 资源代价 ＋ 应用解法里的 `on_win`/`on_lose`
 *
 * 纪律（写进门禁）：
 *   · `fight` 解法**必须**写 `on_win` 与 `on_lose` ✓（赢要改变什么、输要留下什么，都得写清 ✗ 不许"打完没事"）
 *   · 同一任务里，每个对抗点**必须还有 ≥1 条非战斗解法** ✓（防"只能打"✗）
 *   · 本引擎的对抗默认**非致命**（`nonlethal`）—— 最低到昏迷，不扣理智；
 *     输了干什么由内容侧用 `on_lose` 决定（例：这条线是**被排进班表** ✓）
 */
if (!defined('ABSPATH')) exit;

/** 把一次战斗的上下文塞进战斗状态（幂等；同样上下文不重复塞 ✓） */
function crrg_task_fight_start(&$s, &$pc, $task, $pathId, array $path) {
    if (!function_exists('crrg_bt_start')) return ['（战斗系统没加载。）'];
    $uid  = (int) ($pc['uid'] ?? 0);
    $room = (string) ($s['room'] ?? 'b1_train');
    /* 对手：解法里写 npcs（模板 id 数组）；不写就用房内默认两组 */
    $npcs = (array) ($path['npcs'] ?? []);
    $out = (array) crrg_bt_start($room, $uid, 'task', $npcs ?: null);
    $c = function_exists('crrg_bt_get') ? crrg_bt_get() : null;
    if (is_array($c)) {
        $c['ctx'] = [
            'task' => (string) $task, 'path' => (string) $pathId, 'uid' => $uid,
            'nonlethal' => !isset($path['nonlethal']) || !empty($path['nonlethal']),
            'at' => time(),
        ];
        if (function_exists('crrg_bt_save')) crrg_bt_save($c);
    }
    $out[] = '（这一场和任务连着——打完我会接着往下写。）';
    return $out;
}

/**
 * 战斗结束落账（在战斗 `over` 那一刻由 drill-combat.php 调用 ✓ 幂等：看完就是一次）
 * @return array 给玩家看的追加行
 */
function crrg_task_fight_end(array $c) {
    $ctx = (array) ($c['ctx'] ?? []);
    if (empty($ctx['task']) || empty($ctx['path'])) return [];
    if (!empty($ctx['done'])) return [];                       // 幂等：已落过账 ✓
    $task = (string) $ctx['task']; $pid = (string) $ctx['path'];
    $uid  = (int) ($ctx['uid'] ?? 0);
    $path = function_exists('crrg_task_path') ? crrg_task_path($task, $pid) : null;
    if (!$path) return [];

    /* 判胜负：对手全倒＝赢；我方全倒＝输（与战斗自身的判定口径一致 ✓） */
    $npcLeft = 0; $pcUp = 0;
    foreach ((array) ($c['actors'] ?? []) as $k => $a) {
        $active = function_exists('crrg_bt_active') ? crrg_bt_active($a) : true;
        if (strpos((string) $k, 'npc:') === 0 && $active) $npcLeft++;
        if (strpos((string) $k, 'pc:') === 0 && !array_intersect(['昏迷', '死亡'], (array) ($a['state'] ?? []))) $pcUp++;
    }
    $win = ($npcLeft === 0 && $pcUp > 0);

    /* 战斗出口**拿不到个人档**（$s/$pc 在 exec 那一层）✓
       → 这里只做"与玩家无关"的部分（公共事实／战斗日志／挂待落账 ✓），
         个人档由 crrg_task_fight_collect() 在玩家下次动作时收 ✓ 幂等 ✓ */
    $key = $win ? 'on_win' : 'on_lose';
    $do  = (array) ($path[$key] ?? []);
    $out = [];

    /* ① 公共事实：这一场打过了（阈值型；两次以上才算"大家都打过"✓） */
    if (function_exists('crrg_fact_propose')) {
        crrg_fact_propose('base.fight.' . $task . '.' . $pid, ['win' => $win], ['mode' => 'threshold', 'need' => 2, 'scope' => 'base'], $uid);
    }
    /* ② 局面变化：动静（暴露）＋ 这一场被记进值班室 ✓ */
    if (function_exists('crrg_fact_add')) {
        crrg_fact_add('base.fight_log.' . $task, ['path' => $pid, 'win' => $win, 'at' => crrg_fact_now()], 'base', $uid);
    }
    /* ③ 把"待落账"挂到战斗记录里，等玩家下一次动作由 pump 收进个人档（那里才有 &$s ✓） */
    $pend = (array) get_option('crrg_fight_pending', []);
    $pend[] = ['task' => $task, 'path' => $pid, 'uid' => $uid, 'win' => $win, 'do' => $do, 'at' => time()];
    update_option('crrg_fight_pending', array_slice($pend, -20), false);

    $out[] = $win ? '（这一场你站到了最后。）' : '（你被打倒在原地。）';
    foreach ((array) ($do['narrate'] ?? []) as $x) $out[] = (string) $x;
    return $out;
}

/**
 * 把"待落账"收进个人档（由 crrg_task_pump 每次动作调用 ✓ 幂等）
 * 这一步才动得了个人档：写 win/lose 旗标、应用 on_win/on_lose 的 clues/stage/fact/flags/expose、
 * 以及**这条线的核心后果**（例：`enlist` 被排进班表 ✓）
 */
function crrg_task_fight_collect(&$s, &$pc) {
    $pend = (array) get_option('crrg_fight_pending', []);
    if (!$pend) return [];
    $uid = (int) ($pc['uid'] ?? 0);
    $keep = []; $out = [];
    foreach ($pend as $p) {
        if ((int) ($p['uid'] ?? 0) !== $uid) { $keep[] = $p; continue; }   // 不是我的，留着 ✓
        $task = (string) ($p['task'] ?? ''); $pid = (string) ($p['path'] ?? '');
        $win  = !empty($p['win']);
        $flag = 'fight_' . $task . '_' . $pid . '_' . ($win ? 'win' : 'lose');
        if (in_array($flag, (array) ($s['flags'] ?? []), true)) continue;   // 幂等 ✓
        $s['flags'][] = $flag;
        $do = (array) ($p['do'] ?? []);
        if (!$do && function_exists('crrg_task_path')) {
            $pa = crrg_task_path($task, $pid); $do = (array) ($pa[$win ? 'on_win' : 'on_lose'] ?? []);
        }
        foreach ((array) ($do['clues'] ?? []) as $ck) {
            if (function_exists('crrg_clue_gain') && crrg_clue_gain($s, $pc, $task, (string) $ck)) {
                $d = (array) (crrg_task($task)['clues'][$ck] ?? []);
                $out[] = '〔线索〕' . ($d['title'] ?? $ck) . '　' . (string) ($d['point'] ?? '');
            }
        }
        if (!empty($do['stage']) && function_exists('crrg_task_stage_set')) {
            crrg_task_stage_set($s, $pc, $task, (string) $do['stage']);
        }
        foreach ((array) ($do['flags'] ?? []) as $f) if (!in_array($f, (array) $s['flags'], true)) $s['flags'][] = (string) $f;
        if (!empty($do['expose'])) { $s['expose_' . $task] = (int) ($s['expose_' . $task] ?? 0) + (int) $do['expose']; }
        if (!empty($do['san'])) $pc['san'] = max(0, (int) ($pc['san'] ?? 0) - (int) $do['san']);
        if (!empty($do['hp']))  $pc['hp']  = max(1, (int) ($pc['hp'] ?? 1) - (int) $do['hp']);
        if (!empty($do['fact']) && function_exists('crrg_fact_propose')) {
            $f = (array) $do['fact'];
            crrg_fact_propose((string) $f[0], $f[1] ?? true,
                ['mode' => $f[2] ?? 'once', 'scope' => $f[3] ?? 'base', 'need' => $f[4] ?? 2], $uid);
        }
        foreach ((array) ($do['narrate'] ?? []) as $x) $out[] = (string) $x;
        if ($win && empty($do['narrate']) && empty($do['clues'])) $out[] = '（这一场结束了。）';
        if (function_exists('crrg_evt_emit')) {
            crrg_evt_emit('mission.beat', ['kind' => 'fight', 'task' => $task, 'path' => $pid, 'win' => $win, 'uid' => $uid], ['src' => 'engine.fight']);
        }
    }
    if ($keep !== $pend) update_option('crrg_fight_pending', $keep, false);
    return $out;
}
