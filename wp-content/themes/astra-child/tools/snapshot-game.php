<?php
/* 全游戏行为快照（2026-09-29）：覆盖 seal / seal2 / seal3 / seal4 / seal5 / 对话 / 日常动作 / 追逐
 * 用法：php tools/snapshot-game.php out.json
 * 特性：固定随机种子 + 归一化骰点 → 同一份代码两次运行必须完全一致（可作行为基线）
 */
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
/* ── 钉钟（2026-09-29）：快照必须可复现。世界钟按 48 倍速走，昼夜会影响用例结果，
   所以每次跑之前把游戏时间钉到**同一时刻**（写 option：real=now, game=固定值）。
   注意：正确的钉法是直接写 option；crrg_world_set_origin() 的第一个参数是**现实锚点**，钉不了游戏时刻。 ── */
/* ── 跑完必须还原（2026-10-09 修）：原来只钉**不还** ✗ → 全站世界钟被留在测试值上，
   自检次次报「世界钟锚点被拨前」✓；还原时按"从锚点起算"把 game 补齐（否则还原本身＝拨钟 ✗） ── */
if (function_exists('crrg_world_clock') && defined('CRRG_CLOCK_OPT')) {
    $__clockSaved = get_option(CRRG_CLOCK_OPT, null);
    $pinGame = strtotime('2026-09-29 10:00:00');          // 固定：第 3 天 · 上午（避开时段边界）
    update_option(CRRG_CLOCK_OPT, ['real' => time(), 'game' => $pinGame,
        'day0' => $pinGame - 2 * 86400, 'day0_day' => 3], false);
    register_shutdown_function(function () use ($__clockSaved) {
        if (!is_array($__clockSaved) || empty($__clockSaved['game'])) return;
        $scale = defined('CRRG_TIME_SCALE') ? (int) CRRG_TIME_SCALE : 48;
        $o = $__clockSaved;
        $o['game'] = (int) $o['game'] + (int) round((time() - (int) ($o['real'] ?? time())) * $scale);
        $o['real'] = time();
        update_option(CRRG_CLOCK_OPT, $o, false);
    });
}

/* 2026-10-14：**工具不许留副作用** —— 任务引擎/总线会落事实与 option ✗
   → 跑前记水位，跑后精确回收（同一套做法见 verify-bus.php ✓） */
$__ft = function_exists('crrg_facts_table') ? crrg_facts_table() : '';
$__maxId = 0;
if ($__ft) { global $wpdb; $__maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $__ft"); }
$__opts = ['crrg_world_tick', 'crrg_last_day', 'crrg_fact_ping', 'crrg_recent_settle', 'crrg_world_day_note', 'crrg_fight_pending', 'crrg_travel_pending', 'crrg_bt_state', 'crrg_duty','crrg_contain', 'crrg_duty_roster','crrg_phone_life','crrg_arch_index'];
$__optBak = [];
foreach ($__opts as $o) $__optBak[$o] = get_option($o, null);
register_shutdown_function(function () use ($__ft, $__maxId, $__optBak) {
    if ($__ft) { global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM $__ft WHERE id > %d", $__maxId)); }
    foreach ($__optBak as $o => $v) { if ($v === null) delete_option($o); else update_option($o, $v, false); }
});

$outFile = $argv[1] ?? '/tmp/snap-game.json';
function sn($v) {
    if (is_array($v)) { $o = []; foreach ($v as $k => $x) $o[$k] = sn($x); return $o; }
    if (!is_string($v)) return $v;
    return preg_replace(['/D100＝\\d+/u', '/（技能 \\d+%）/u'], ['D100＝X', '（技能 N%）'], $v);
}
function mkpc($fav = [], $rep = []) {
    return ['attrs' => ['体质' => 50, '意志' => 50, '教育' => 50, '智力' => 50, '敏捷' => 60, '力量' => 60],
            'skills' => ['侦查' => 60, '会计' => 60, '急救' => 60, '图书馆使用' => 60, '科学' => 60, '格斗' => 60, '说服' => 60],
            'san' => 50, 'hp' => 12, 'mp' => 10, 'name' => '自检', 'occ' => '文职', 'ready' => true,
            'npc_fav' => $fav, 'items' => [], 'money' => 500, 'credits' => 50, 'vitals' => crrg_vitals_default(),
            'mission' => '', 'mission2' => ['id' => '', 'step' => 0, 'start' => 0], 'rep' => $rep];
}
$snap = [];
$states = [
    '干净' => [],
    '料齐' => ['s4_抢料', 's4_料完整', 'craft_选择'],
    '料缺' => ['s4_抢料', 's4_料_缺'],
    '已贴封' => ['s5_出货', 's5_贴封'],
    '已结案' => ['s5_出货', 's5_贴封', 's5_结案'],
    '第三段完' => ['s3_邻居', 's3_徒弟账', 's3_摊牌', 's3_暗账', 's3_结论'],
];
/* ── seal4 ── */
if (function_exists('crrg_seal4_rob')) {
    foreach (['' => '问', '硬碰' => '硬碰', '让步' => '让步', '开车跑' => '开车跑'] as $mode => $tag) {
        foreach (['干净', '料齐'] as $st) {
            mt_srand(20260929);
            $S = ['room' => 'tw_hillpath', 'items' => [], 'flags' => $states[$st], 'steps' => 0, 'gates' => 0];
            $P = mkpc();
            $r = crrg_seal4_rob($S, $P, $mode);
            $snap["seal4_rob|$tag|$st"] = ['out' => sn($r), 'flags' => $S['flags'], 'hp' => $P['hp']];
        }
    }
    foreach (['' => '无', '补件' => '补件', '封闭' => '封闭', '上报' => '上报'] as $m => $tag) {
        foreach (['干净', '料缺'] as $st) {
            mt_srand(20260929);
            $S = ['room' => 'b4_main', 'items' => [], 'flags' => array_merge($states[$st], ['s4_事故定档']), 'steps' => 0, 'gates' => 0];
            $P = mkpc();
            $r = $m === '' ? crrg_seal4_accident($S, $P) : crrg_seal4_accident_handle($S, $P, $m);
            $snap["seal4_acc|$tag|$st"] = ['out' => sn($r), 'flags' => $S['flags']];
        }
    }
    foreach (['' => '问', '稳妥' => '稳妥', '激进' => '激进', '放弃' => '放弃'] as $k => $tag) {
        mt_srand(20260929);
        $S = ['room' => 'b2b_tech', 'items' => [], 'flags' => ['s4_料完整', 's4_事故处理'], 'steps' => 0, 'gates' => 0];
        $P = mkpc();
        $r = crrg_seal4_craft($S, $P, $k);
        $snap["seal4_craft|$tag"] = ['out' => sn($r), 'flags' => $S['flags']];
    }
    mt_srand(20260929);
    $S = ['room' => 'b2b_tech', 'items' => [], 'flags' => ['s4_料完整'], 'steps' => 0, 'gates' => 0]; $P = mkpc();
    $snap['seal4_handover'] = ['out' => sn(crrg_seal4_handover($S, $P)), 'flags' => $S['flags']];
}
/* ── seal5 ── */
if (function_exists('crrg_seal5_run')) {
    foreach (['干净', '料齐'] as $st) {
        mt_srand(20260929);
        $S = ['room' => 'b2b_tech', 'items' => [], 'flags' => $states[$st], 'steps' => 0, 'gates' => 0]; $P = mkpc();
        $snap["seal5_run|$st"] = ['out' => sn(crrg_seal5_run($S, $P)), 'flags' => $S['flags']];
    }
    foreach ([false, true] as $force) {
        mt_srand(20260929);
        $S = ['room' => 'b2b_hall', 'items' => [], 'flags' => $states['已贴封'], 'steps' => 0, 'gates' => 0]; $P = mkpc();
        $snap['seal5_close|' . ($force ? 'force' : 'normal')] = ['out' => sn(crrg_seal5_close($S, $P, $force)), 'flags' => $S['flags']];
    }
    $snap['seal5_endings'] = ['out' => sn(crrg_seal5_endings())];
}
/* ── 对话：每个 NPC 的前 3 个话题 ── */
if (function_exists('crrg_dlg_all')) {
    foreach ((array) crrg_dlg_all() as $npcId => $d) {
        $tps = array_slice(array_keys((array) ($d['topics'] ?? [])), 0, 3);
        foreach ($tps as $tp) {
            mt_srand(20260929);
            $S = ['room' => $d['room'] ?? 'x', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 0];
            $P = mkpc();
            $r = crrg_dlg_ask($S, $P, 1, $npcId, (string) $tp);
            $snap["dlg|$npcId|$tp"] = ['out' => sn(is_array($r) ? ($r[0] ?? $r) : $r), 'flags' => $S['flags']];
        }
    }
}
/* ── 日常动作：base 动作表前 8 条 ── */
if (function_exists('crrg_base_mission_acts') && function_exists('crrg_drill_do_act')) {
    $n = 0;
    foreach ((array) crrg_base_mission_acts() as $mid => $rooms2) {
        foreach ((array) $rooms2 as $room2 => $specs) {
            foreach ((array) $specs as $sp) {
                if ($n++ >= 8) break 3;
                mt_srand(20260929);
                $S = ['room' => $room2, 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 0];
                $P = mkpc(); $P['mission'] = $mid;
                $ctx = ['lv' => 3, 'card' => 'C', 'rank_name' => 'x', 'rank' => 'observer', 'chairman' => false, 's' => false];
                $r = crrg_drill_do_act($S, $P, $ctx, 1, 'mact', 'a' . substr(md5($mid . '|' . $sp[1]), 0, 10));
                $snap["mact|$mid|" . $sp[1]] = ['out' => sn($r), 'flags' => $S['flags']];
            }
        }
    }
}
file_put_contents($outFile, json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
printf("全游戏快照 %d 条 → %s\n", count($snap), $outFile);
/* 自检：跑的过程中若出现 PHP 警告/错误，说明有代码被改坏（快照"一致"可能掩盖它） */
$errs = error_get_last();
if ($errs && in_array($errs['type'], [E_WARNING, E_NOTICE, E_DEPRECATED], true)) {
    fwrite(STDERR, "⚠ 运行期出现 PHP 警告：" . $errs['message'] . "（" . basename($errs['file']) . ":" . $errs['line'] . "）\n");
    exit(2);
}
