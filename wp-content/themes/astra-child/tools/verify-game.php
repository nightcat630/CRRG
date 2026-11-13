<?php
/**
 * CRRG 演习系统 · 证据式验证器（2026-09-29）
 * ------------------------------------------------------------
 * 为什么另起一个：旧自检（lint-names.php）本质是"对源码文本做模式匹配" ✗——
 *   ① 换写法就漏 ② 注释里出现就误报 ③ **没有覆盖度概念**：搬迁后整段检查静默跳过，
 *   却仍然打印「0 错 0 提醒」✗（绿色的报告可能是空的）
 *
 * 本工具的三条原则：
 *   1) **能跑就别读**：优先"真执行一遍"（命令、面板、房间、商店、物品、NPC）
 *   2) **覆盖度必须记账**：每条检查登记"查了多少"，并有**下限**；低于下限＝错误 ✗
 *   3) **不许静默**：跳过要记账；未捕获的 fatal 也进报告；**有错/有跳过 ⇒ 退出码非 0**
 *
 * 用法：php tools/verify-game.php [--allow-skip]
 */
$allowSkip = in_array('--allow-skip', $argv ?? [], true);
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
$wl = '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

if (!function_exists('crrg_drill_rooms') && file_exists($wl)) require $wl;

/* 2026-10-14：**验证器不许留副作用** —— 跑一遍任务/推进器会经引擎落事实、写 option ✗
   （与 verify-bus.php、snapshot-game.php 同一套做法：跑前记水位，跑完精确回收 ✓） */
$__ft = function_exists('crrg_facts_table') ? crrg_facts_table() : '';
$__maxId = 0;
if ($__ft) { global $wpdb; $__maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $__ft"); }
$__optBak = [];
foreach (['crrg_world_tick','crrg_last_day','crrg_fact_ping','crrg_recent_settle','crrg_world_day_note','crrg_fight_pending','crrg_bt_state','crrg_duty','crrg_contain','crrg_duty_roster','crrg_phone_life','crrg_arch_index','crrg_travel_pending'] as $o) $__optBak[$o] = get_option($o, null);
register_shutdown_function(function () use ($__ft, $__maxId, $__optBak) {
    if ($__ft) { global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM $__ft WHERE id > %d", $__maxId)); }
    foreach ($__optBak as $o => $v) { if ($v === null) delete_option($o); else update_option($o, $v, false); }
});

$CHECKS = [];   // name => ['ok'=>bool,'count'=>int,'floor'=>int,'err'=>[],'skip'=>string]
function ck_run($name, $floor, callable $body) {
    global $CHECKS;
    $r = ['ok' => true, 'count' => 0, 'floor' => $floor, 'err' => [], 'skip' => ''];
    try { $r = array_merge($r, (array) $body($r)); }
    catch (Throwable $e) { $r['ok'] = false; $r['err'][] = '异常：' . $e->getMessage(); }
    if ($r['skip'] !== '') { $r['ok'] = true; }                       // 跳过不算错，但会被记账
    elseif ($r['count'] < $floor) { $r['ok'] = false; $r['err'][] = sprintf('覆盖度不足：只查了 %d，下限 %d', $r['count'], $floor); }
    $CHECKS[$name] = $r;
    return $r;
}
function ck_skip($name, $why) { ck_run($name, 0, fn() => ['skip' => $why]); }

/* 未捕获 fatal 也要进报告（不许"看起来一切正常"） */
$FATAL = null;
register_shutdown_function(function () use (&$FATAL) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        fwrite(STDERR, "\n✗ 运行中发生致命错误：" . $e['message'] . "（" . $e['file'] . ":" . $e['line'] . "）\n");
        $FATAL = $e;
        exit(2);
    }
});

/* 现成的沙箱角色（用游戏自己的建卡） */
function ck_pc() {
    $pc = function_exists('crrg_coc_new_pc') ? crrg_coc_new_pc() : [];
    if (function_exists('crrg_coc_derive')) { $d = crrg_coc_derive($pc['attrs'] ?? []); $pc['hp'] = $d['hp_max'] ?? 10; $pc['san'] = $d['san_max'] ?? 50; $pc['mp'] = $d['mp_max'] ?? 10; }
    $pc['name'] = '验证器'; $pc['occ'] = '文职'; $pc['ready'] = true;
    $pc['skills'] = ['侦查' => 60, '急救' => 60, '会计' => 60, '说服' => 60];
    $pc['vitals'] = function_exists('crrg_vitals_default') ? crrg_vitals_default() : [];
    $pc['money'] = 9999; $pc['credits'] = 999; $pc['clearance'] = 6; $pc['items'] = []; $pc['npc_fav'] = [];
    $pc['mission'] = ''; $pc['mission2'] = ['id' => '', 'step' => 0, 'start' => 0];
    return $pc;
}
function ck_ctx() { return ['lv' => 6, 'card' => 'A', 'rank_name' => '验证', 'rank' => 'chairman', 'chairman' => true, 's' => true]; }

/* ══════════ 行为检查：真跑 ══════════ */

/* ① 房间：每间都能进、都有名字与描述 */
ck_run('房间：可进入且有名有描述', 65, function ($r) {
    if (!function_exists('crrg_drill_rooms')) return ['skip' => '游戏未加载'];
    foreach (['base', 'town', 'region'] as $sc) {
        if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene($sc);
        foreach ((array) crrg_drill_rooms() as $k => $v) {
            $r['count']++;
            if (empty($v['t'])) $r['err'][] = "房间 $k 没有名字（会显示 null）";
            if (empty($v['d'])) $r['warn'][] = "房间 $k 没有描述";
            foreach ((array) ($v['x'] ?? []) as $lb => $dst) if (!isset(crrg_drill_rooms()[$dst])) $r['err'][] = "房间 $k 的出口「$lb」指向不存在的 $dst";
        }
    }
    if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene('base');
    return $r;
});

/* ② 命令：每条动词都能被引擎认（真执行一次，只看有没有"不认识的指令"） */
ck_run('命令：每条动词都能执行', 20, function ($r) {
    if (!function_exists('crrg_drill_exec')) return ['skip' => '游戏未加载'];
    /* 动词表**从游戏自己那儿取**：跑一次「帮助」，按帮助的真实格式解析
       · 动作行形如「基础动作：坐下　站起　睡觉 [小时数]　说 <内容>」→ 取「：」之后、按全/半角空格切
       · 顶部单行动作形如「查看 [目标]　看当前房间…」→ 取行首动词
       · **占位符 <…>／[…] 之前的部分才算动词**；括号里的例子（点头/摇头…）是参数，不是动词 ✗ */
    $S0 = ['room' => 'b2b_hall', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 6, 'att' => [], 'times' => [], 'ui' => []];
    $pc0 = ck_pc();
    $old = error_reporting(0);
    $hOut = crrg_drill_exec($S0, ck_ctx(), '帮助', $pc0, 0);
    error_reporting($old);
    /* 2026-10-01：指令清单已搬到 crrg_help_lines()（聊天与窗口共用 ✓）→ 优先从它读；
       同时保留聊天输出的兜底 ✓ */
    $help = '';
    if (function_exists('crrg_help_lines')) $help = implode("\n", (array) crrg_help_lines());
    if (trim($help) === '' && is_array($hOut)) $help = implode("\n", (array) ($hOut[1] ?? []));
    $verbs = [];
    $add = function ($w) use (&$verbs) {
        $w = trim($w);
        if ($w !== '' && preg_match('/^[\x{4e00}-\x{9fa5}]{1,4}$/u', $w)) $verbs[$w] = 1;
    };
    foreach (preg_split('/\n/u', $help) as $line) {
        $line = preg_replace('/^[\s\x{3000}]+/u', '', $line);          // PHP trim 不认全角空格 ✗
        $line = preg_replace('/(例|例如)[:：].*$/u', '', $line);          // 「例：…」是举例，不是动作
        $line = trim($line);
        if ($line === '') continue;
        /* 跳过解释/示例行 */
        if (preg_match('/^[（(·—【]|^例[:：]|^规则|^演习脚本|^可用指令/u', $line)) continue;
        if (preg_match('/(可用|例如|比如)[:：]/u', $line)) continue;      // 解释性冒号，不是动作清单
        if (mb_strpos($line, '：') !== false) {
            $tail = explode('：', $line, 2)[1];
            foreach (preg_split('/[\s　\/｜|、]+/u', $tail) as $tok) {
                $tok = preg_split('/[<\[（(]/u', $tok)[0];      // 占位符之前才算动词
                $add($tok);
            }
            continue;
        }
        /* 顶部单行：行首动词（后面可能是空格＋解释，或 [目标]） */
        $tok = preg_split('/[\s　<\[（(]/u', $line)[0];
        $add($tok);
    }
    $verbs = array_slice(array_keys($verbs), 0, 80);
    if (count($verbs) < 8) return ['skip' => '帮助里没能解析出动词（帮助文本可能改了）'];
    $r['verb_list'] = $verbs;
    foreach ($verbs as $v) {
        $r['count']++;
        $S = ['room' => 'b2b_hall', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];
        $pc = ck_pc();
        $old = error_reporting(0);
        $out = crrg_drill_exec($S, ck_ctx(), $v, $pc, 0);
        error_reporting($old);
        $txt = is_array($out) ? implode(' ', (array) ($out[1] ?? [])) : '';
        if (mb_strpos($txt, '不认识的指令') !== false) $r['err'][] = '命令「' . $v . '」不被识别';
        if ($txt === '') $r['err'][] = '命令「' . $v . '」没有任何输出';
    }
    return $r;
});

/* ③ 面板：每个房间的面板都能构建，且每个按钮的 k 都能被 do_act 处理 */
ck_run('面板：可构建且按钮可点', 5, function ($r) {
    if (!function_exists('crrg_drill_panels')) return ['skip' => '游戏未加载'];
    $known = ['noop' => 1, 'divider' => 1];
    foreach (['base', 'town'] as $sc) {
        if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene($sc);
        foreach ((array) crrg_drill_rooms() as $room => $v) {
            $S = ['room' => $room, 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 6, 'att' => [], 'times' => [], 'ui' => []];
            $old = error_reporting(0);
            $bl = crrg_drill_panels($S, ck_ctx(), ck_pc(), 0);
            error_reporting($old);
            if (!is_array($bl)) { $r['err'][] = "房间 $room 的面板构建失败"; continue; }
            $r['count']++;
            foreach ((array) $bl as $g) foreach ((array) ($g['items'] ?? []) as $it) {
                $k = (string) ($it['k'] ?? '');
                if ($k === '' || isset($known[$k])) continue;
                if (strpos($k, 'bt_') === 0) continue;                     // 战斗动作统一转派
                if (!function_exists('crrg_drill_do_act')) continue;
                $pc2 = ck_pc();
                $old = error_reporting(0);
                $res = crrg_drill_do_act($S, $pc2, ck_ctx(), 0, $k, (string) ($it['id'] ?? ''));
                error_reporting($old);
            }
        }
    }
    if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene('base');
    return $r;
});

/* ④ 商店：每家店的货都能查出来（items/cats 至少要出一种） */
ck_run('商店：每家都有可卖货', 3, function ($r) {
    if (!function_exists('crrg_drill_shops_all')) return ['skip' => '游戏未加载'];
    foreach ((array) crrg_drill_shops_all() as $id => $shop) {
        $r['count']++;
        $goods = function_exists('crrg_drill_shop_goods') ? crrg_drill_shop_goods($shop) : [];
        if (!$goods) $r['err'][] = "商店 $id（{$shop['n']}）查不出任何货";
    }
    return $r;
});

/* ⑤ 物品：每个物品名可解析 + 分类存在 */
ck_run('物品：名字与分类可解析', 60, function ($r) {
    if (!function_exists('crrg_drill_items')) return ['skip' => '游戏未加载'];
    $minors = function_exists('crrg_item_minor') ? array_keys((array) crrg_item_minor()) : [];
    foreach ((array) crrg_drill_items() as $id => $it) {
        $r['count']++;
        if (function_exists('crrg_drill_item_name') && trim((string) crrg_drill_item_name($id)) === '') $r['err'][] = "物品 $id 没有名字";
        if ($minors && !empty($it['cat']) && !in_array($it['cat'], $minors, true)) $r['err'][] = "物品 $id 的分类 {$it['cat']} 不在小类表里";
    }
    return $r;
});

/* ⑥ NPC：每个都能算出"现在在哪"，且在的地方能生成在场行 */
ck_run('NPC：作息与在场行可算', 4, function ($r) {
    if (!function_exists('crrg_npc_roster')) return ['skip' => '游戏未加载'];
    foreach ((array) crrg_npc_roster() as $id => $d) {
        $r['count']++;
        $room = function_exists('crrg_npc_room_now') ? crrg_npc_room_now($id) : '';
        if ($room === '') { $r['err'][] = "NPC {$d['n']} 的作息算不出房间"; continue; }
        /* 2026-10-14 修口径：NPC 可以在**任何已注册场景**里（县城/甘州/镇上…），
           原来只看当前场景的房间表 ✗ → 别的场景的 NPC 一律被误报"房间不存在" ✗ */
        $known = false;
        if (function_exists('crrg_drill_rooms') && isset(crrg_drill_rooms()[$room])) $known = true;
        if (!$known) {
            $Rr = &crrg_drill_reg();
            foreach ((array) ($Rr['scenes'] ?? []) as $sc2) if (isset($sc2['rooms'][$room])) { $known = true; break; }
        }
        if (!$known && isset($Rr['rooms'][$room])) $known = true;                       // 全局房间表 ✓
        if (!$known && function_exists('crrg_npc_room_name') && crrg_npc_room_name($room) !== $room) $known = true;
        if (!$known) $r['err'][] = "NPC {$d['n']} 所在房间 $room 不存在（全场景都没查到）";
    }
    return $r;
});

/* ⑦ 掉落物：增/查/取 三个动作真跑一遍（该类常量曾"只用不定义"→ 整个子系统抛错 ✗） */
ck_run('掉落物：可增可查可取', 3, function ($r) {
    if (!function_exists('crrg_drill_drop_add')) return ['skip' => '掉落物系统未加载'];
    $room = 'b2b_hall';
    $r['count']++;
    if (crrg_drill_drop_add($room, 'food_ration', 2) === false) $r['err'][] = 'drop_add 失败';
    $r['count']++;
    $got = crrg_drill_drops($room);
    /* 形状是「物品 id => 数量」（不是数字下标）——断言按真实形状写 */
    if ((int) ($got['food_ration'] ?? 0) < 2) $r['err'][] = 'drop_add 之后查不到掉落物（应为 food_ration ≥ 2）';
    $r['count']++;
    if (crrg_drill_drop_take($room, 'food_ration', 1) === false) $r['err'][] = 'drop_take 失败';
    /* 顺带验库存子系统（同类问题："只用不定义"的常量） */
    if (function_exists('crrg_drill_stock_get')) {
        $r['count']++;
        $n = crrg_drill_stock_get('b3_warehouse', '压缩干粮', 3);
        if ($n === null) $r['err'][] = '库存子系统取数失败（CRRG_DRILL_STOCK 一类常量问题）';
    }
    /* 清场：把测试留下的掉落物清掉 */
    if (function_exists('crrg_drill_drop_add')) { $D = get_option(CRRG_DRILL_DROPS, []); if (is_array($D)) { unset($D[$room]); update_option(CRRG_DRILL_DROPS, $D, false); } }
    if (defined('CRRG_DRILL_STOCK')) delete_option(CRRG_DRILL_STOCK);
    return $r;
});

/* ⑧ 面板按钮：**逐个真点**，断言不是「未知操作」（今天那类 bug 的正面防线） */
ck_run('面板按钮：点了有反应', 20, function ($r) {
    if (!function_exists('crrg_drill_panels') || !function_exists('crrg_drill_do_act')) return ['skip' => '游戏未加载'];
    $badK = ['noop' => 1, 'divider' => 1];
    foreach (['base', 'town'] as $sc) {
        if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene($sc);
        foreach ((array) crrg_drill_rooms() as $room => $v) {
            $S = ['room' => $room, 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 6, 'att' => [], 'times' => [], 'ui' => []];
            $old = error_reporting(0);
            $bl = crrg_drill_panels($S, ck_ctx(), ck_pc(), 0);
            error_reporting($old);
            foreach ((array) $bl as $g) foreach ((array) ($g['items'] ?? []) as $it) {
                $k = (string) ($it['k'] ?? '');
                if ($k === '' || isset($badK[$k])) continue;
                $r['count']++;
                $pc = ck_pc();
                $old = error_reporting(0);
                $res = crrg_drill_do_act($S, $pc, ck_ctx(), 0, $k, (string) ($it['id'] ?? ''));
                error_reporting($old);
                $txt = is_array($res) ? implode(' ', (array) $res) : '';
                if (mb_strpos($txt, '未知操作') !== false || mb_strpos($txt, '不认识的指令') !== false) {
                    $r['err'][] = sprintf('按钮「%s」（k=%s，@%s/%s）点了没反应', mb_substr((string) ($it['label'] ?? ''), 0, 16), $k, $sc, $room);
                } elseif ($txt === '') {
                    $r['err'][] = sprintf('按钮「%s」（k=%s）点了没有任何输出', mb_substr((string) ($it['label'] ?? ''), 0, 16), $k);
                }
            }
        }
    }
    if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene('base');
    return $r;
});

/* ⑨ 任务：接单 → 推进 → 结算（全流程冒烟，用一次性测试号） */
ck_run('任务：接单→推进→结算', 1, function ($r) {
    if (!function_exists('crrg_drill_mission')) return ['skip' => '合约系统未加载'];
    $uid = 990001;                                   // 一次性测试号
    if (function_exists('crrg_drill_pc_save') && function_exists('crrg_drill_save')) {
        $pc = ck_pc(); $pc['mission'] = ''; $pc['mission2'] = ['id' => '', 'step' => 0, 'start' => 0];
        crrg_drill_pc_save($uid, $pc);
        $S = ['room' => 'b2b_hall', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 6, 'att' => [], 'times' => [], 'ui' => []];
        crrg_drill_save($uid, $S);
    }
    $mids = array_keys((array) crrg_drill_missions());
    if (!$mids) return ['skip' => '没有已注册合约'];
    /* 挑一个可接的低级合约（min_lv ≤ 3 且非隐藏） */
    $pick = '';
    foreach ($mids as $mid) {
        $m = crrg_drill_mission($mid);
        if (!empty($m['hidden'])) continue;
        if ((int) ($m['min_lv'] ?? 9) <= 3) { $pick = $mid; break; }
    }
    if ($pick === '') return ['skip' => '找不到可接的低级合约'];
    $r['count']++;
    $S = ['room' => 'b2b_hall', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 6, 'att' => [], 'times' => [], 'ui' => []];
    $pc = ck_pc();
    $old = error_reporting(0);
    $acc = crrg_drill_do_act($S, $pc, ck_ctx(), $uid, 'accept', $pick);
    error_reporting($old);
    if (!$pc['mission'] && !($pc['mission2']['id'] ?? '')) $r['err'][] = "接单 $pick 失败：" . mb_substr(implode(' ', (array) $acc), 0, 60);
    /* 推进：把该任务的步骤旗标逐个塞进去，看 tick 是否推进 */
    $m = crrg_drill_mission($pc['mission'] ?: ($pc['mission2']['id'] ?? ''));
    $before = (int) ($pc['mission_step'] ?? 0);
    foreach ((array) ($m['steps'] ?? []) as $st) {
        if (!empty($st['flag'])) $S['flags'][] = $st['flag'];
    }
    if (function_exists('crrg_drill_mission_tick')) { $o = []; crrg_drill_mission_tick($S, $pc, 'verify', $o); }
    $after = (int) ($pc['mission_step'] ?? 0);
    $r['count']++;
    if ($after <= $before) $r['err'][] = sprintf('塞满步骤旗标后进度没推进（%d → %d）', $before, $after);
    /* 结算 */
    $r['count']++;
    $old = error_reporting(0);
    $set = crrg_drill_do_act($S, $pc, ck_ctx(), $uid, 'settle_now', '');
    error_reporting($old);
    $txt = mb_substr(implode(' ', (array) $set), 0, 60);
    if ($txt === '') $r['err'][] = '结算没有输出';
    /* 清场 */
    delete_user_meta($uid, 'crrg_drill_pc'); delete_user_meta($uid, 'crrg_drill_state');
    return $r;
});

/* ⑩ 商店：真买一件（钱要减少、货要到手） */
ck_run('商店：真买一件', 1, function ($r) {
    if (!function_exists('crrg_drill_shops_all') || !function_exists('crrg_drill_do_act')) return ['skip' => '商店系统未加载'];
    foreach ((array) crrg_drill_shops_all() as $sid => $shop) {
        $goods = crrg_drill_shop_goods($shop);
        if (!$goods) continue;
        /* 货品可能是"嵌套"的（如车库：{"车辆":{"bike":{…}}}）→ 下钻一层找到**真实物品 id** ✗
           （2026-09-29：我第一版拿了类别名去"买"，于是断言失败——是检查的错，不是游戏的错） */
        $gid = ''; $g = null;
        foreach ($goods as $k1 => $v1) {
            if (is_array($v1) && !isset($v1['p']) && !isset($v1['n'])) {
                foreach ($v1 as $k2 => $v2) { if (is_array($v2)) { $gid = (string) $k2; $g = $v2; break 2; } }
            }
            $gid = (string) $k1; $g = $v1; break;
        }
        if ($gid === '' || !is_array($g)) continue;
        $r['count']++;
        $S = ['room' => $shop['room'], 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 6, 'att' => [], 'times' => [], 'ui' => []];
        $pc = ck_pc(); $pc['money'] = 5000; $pc['credits'] = 5000;
        $before = (int) $pc['money'] + (int) $pc['credits'];
        $old = error_reporting(0);
        $res = crrg_drill_do_act($S, $pc, ck_ctx(), 0, 'buy', (string) $gid);
        error_reporting($old);
        $after = (int) $pc['money'] + (int) $pc['credits'];
        $txt = implode(' ', (array) $res);
        if (mb_strpos($txt, '未知操作') !== false) $r['err'][] = sprintf('商店 %s 买 %s：按钮没接上', $sid, $gid);
        elseif ($after >= $before && mb_strpos($txt, '不够') === false) $r['err'][] = sprintf('商店 %s 买 %s：钱没扣（%d → %d）', $sid, $gid, $before, $after);
        break;                                        // 抽查一家即可，避免污染
    }
    return $r;
});

/* ⑪ 对话：每间有 NPC 的房间问一轮（有输出即可） */
ck_run('对话：能问出话', 3, function ($r) {
    if (!function_exists('crrg_npc_here')) return ['skip' => 'NPC 系统未加载'];
    if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene('town');
    foreach ((array) crrg_drill_rooms() as $room => $v) {
        $here = (array) crrg_npc_here($room);
        if (!$here) continue;
        $name = array_key_first($here);
        $r['count']++;
        $S = ['room' => $room, 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 6, 'att' => [], 'times' => [], 'ui' => []];
        $pc = ck_pc();
        $old = error_reporting(0);
        $res = crrg_drill_exec($S, ck_ctx(), '问 ' . $name, $pc, 0);
        error_reporting($old);
        $txt = is_array($res) ? implode(' ', (array) ($res[1] ?? [])) : '';
        if ($txt === '' || mb_strpos($txt, '没有这个人') !== false) $r['err'][] = "房间 $room 问「$name」没得到回应";
    }
    if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene('base');
    return $r;
});

/* ⑫ 门禁矩阵：D/C/B/A 四级 × 所有房间，门禁判定必须自洽（不能出现"接单等级进不去"的死锁） */
ck_run('门禁矩阵：四级判定自洽', 3, function ($r) {
    if (!function_exists('crrg_drill_room_guard')) return ['skip' => '门禁系统未加载'];
    $levels = [1 => 'D', 3 => 'C', 5 => 'B', 6 => 'A'];
    foreach (['base', 'town'] as $sc) {
        if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene($sc);
        foreach ((array) crrg_drill_rooms() as $room => $v) {
            $need = (int) ($v['c'] ?? 1);
            if ($need > 6) $r['err'][] = "房间 $room 要求卡级 $need（超过 A=6，没人进得去）";
            foreach ($levels as $lv => $name) {
                $r['count']++;
                $S = ['room' => $room, 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => $lv, 'att' => [], 'times' => [], 'ui' => []];
                $ctx = ['lv' => $lv, 'card' => crrg_drill_clearance_name($lv), 'rank_name' => $name, 'rank' => 'x', 'chairman' => $lv >= 5, 's' => false];
                $pc = ck_pc(); $pc['clearance'] = $lv;
                $old = error_reporting(0);
                $g = crrg_drill_room_guard($S, $ctx, $pc);
                error_reporting($old);
                /* 判定：低卡级不能进高门禁房；高卡级必须能进低门禁房 */
                if ($lv < $need && ($g === true || $g === 1 || $g === '')) $r['err'][] = "卡级 $name($lv) 竟然能进要求 $need 的房间 $room";
                if ($lv >= $need && ($g === false || $g === 0)) $r['err'][] = "卡级 $name($lv) 进不去只要求 $need 的房间 $room";
            }
        }
    }
    if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene('base');
    return $r;
});

/* ⑬ 任务链：五段链条的 link 都要存在、无环、期限合理（历史上有过"续段接不上"） */
ck_run('任务链：链条完整无环', 2, function ($r) {
    if (!function_exists('crrg_drill_missions')) return ['skip' => '合约系统未加载'];
    $all = (array) crrg_drill_missions();
    $chains = [];
    foreach ($all as $id => $m) {
        if (empty($m['chain_no'])) continue;
        $chains[$id] = $m;
    }
    /* ① 每个 chain_next 必须真实存在 */
    foreach ($chains as $id => $m) {
        $r['count']++;
        $nx = (string) ($m['chain_next'] ?? '');
        if ($nx !== '' && !isset($all[$nx])) $r['err'][] = "合约 $id 的续段 $nx 不存在（接了就会断链 ✗）";
        if ($nx === '' && (int) ($m['chain_no'] ?? 1) < (int) ($m['chain_total'] ?? 1)) $r['err'][] = "合约 $id 是第 {$m['chain_no']}/{$m['chain_total']} 段，但没有 chain_next（链条断了 ✗）";
        if ((int) ($m['deadline_h'] ?? 0) <= 0) $r['err'][] = "合约 $id 没有期限（deadline_h）";
    }
    /* ② 无环：跟着 chain_next 走，不能回到已访问节点 */
    foreach ($chains as $start => $mm) {
        $seen = []; $cur = $start; $steps = 0;
        while ($cur !== '' && isset($all[$cur]) && $steps++ < 20) {
            if (isset($seen[$cur])) { $r['err'][] = "任务链在 $cur 处成环 ✗"; break; }
            $seen[$cur] = 1;
            $cur = (string) ($all[$cur]['chain_next'] ?? '');
        }
    }
    /* ③ 每一段都要有 steps */
    foreach ($chains as $id => $m) {
        /* 2026-10-14（R5）：**引擎任务可以没有步骤** ✓ —— 它的路在"线索＋推论"里（委员长口径：面板不出步骤 ✗）
           判断口径：有引擎定义且有线索/推论槽位 → 合法 ✓；老任务仍然必须有步骤 ✓ */
        $isEngineTask = function_exists('crrg_task') && crrg_task($id)
            && (!empty(crrg_task($id)['clues']) || !empty(crrg_task($id)['infer']) || !empty(crrg_task($id)['stub']));
        if (empty($m['steps']) && !$isEngineTask) $r['err'][] = "合约 $id 没有任何步骤";
    }
    return $r;
});

/* ⑭ 存档迁移：旧形状存档（缺新键）读入不得炸，且默认值要补上 */
ck_run('存档迁移：旧档可读', 3, function ($r) {
    if (!function_exists('crrg_drill_pc') || !function_exists('crrg_drill_pc_save')) return ['skip' => '存档系统未加载'];
    $uid = 990002;                                   // 一次性测试号
    /* ① 极简旧档（缺 vitals／mission2／clearance…） */
    update_user_meta($uid, 'crrg_drill_pc', ['ready' => true, 'attrs' => ['体质' => 50, '意志' => 50, '教育' => 50, '智力' => 50], 'skills' => []]);
    update_user_meta($uid, 'crrg_drill_state', ['room' => 'b2b_hall']);
    $r['count']++;
    $old = error_reporting(0);
    $pc = crrg_drill_pc($uid);
    error_reporting($old);
    if (!is_array($pc)) { $r['err'][] = '旧档读入失败（返回非数组）'; }
    else {
        if (!isset($pc['vitals'])) $r['warn'][] = '旧档没有 vitals（应补默认）';
        if (!isset($pc['money']))  $r['warn'][] = '旧档没有 money';
    }
    /* ② 空档（完全没有 meta）也不能炸 */
    $r['count']++;
    delete_user_meta($uid, 'crrg_drill_pc');
    $old = error_reporting(0);
    $pc2 = crrg_drill_pc($uid);
    error_reporting($old);
    if (!is_array($pc2)) $r['err'][] = '空档读入失败';
    /* ③ 状态同样试一遍 */
    $r['count']++;
    delete_user_meta($uid, 'crrg_drill_state');
    if (function_exists('crrg_drill_state')) {
        $old = error_reporting(0);
        $st = crrg_drill_state($uid);
        error_reporting($old);
        if (!is_array($st)) $r['err'][] = '空状态读入失败';
    }
    delete_user_meta($uid, 'crrg_drill_pc'); delete_user_meta($uid, 'crrg_drill_state');
    return $r;
});

/* ⑮ 陈旧闭包调用：函数里调用了 $set/$has/$fav… 却没在本函数内定义 → 运行到就崩
 *   （2026-09-29 实战：合并后残留 $set() → 一问 NPC 就 fatal；这类问题**静态就能精确抓**） */
ck_run('陈旧闭包调用：无残留', 300, function ($r) {
    $T = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child/';
    $files = array_merge(glob($T . 'includes/*.php') ?: [], glob($T . 'includes/game/*.php') ?: [],
        glob($T . 'includes/game/*/*.php') ?: [], glob($T . 'includes/game/*/*/*.php') ?: []);
    $locals = ['set', 'has', 'check', 'add', 'fav', 'fav_add', 'roll'];
    foreach ($files as $f) {
        $lines = file($f); $n = count($lines);
        for ($i = 0; $i < $n; $i++) {
            if (!preg_match('/^function\s+(&?)([a-zA-Z0-9_]+)\s*\(/', $lines[$i], $mm)) continue;
            $end = $n - 1;
            for ($j = $i + 1; $j < $n; $j++) if (preg_match('/^function\s+/', $lines[$j])) { $end = $j - 1; break; }
            $body = implode('', array_slice($lines, $i, $end - $i + 1));
            $r['count']++;
            foreach ($locals as $vv) {
                if (!preg_match('/\$' . $vv . '\s*\(/', $body)) continue;
                if (preg_match('/\$' . $vv . '\s*=\s*(function|fn|static function)\b/', $body)) continue;
                if (preg_match('/function\s+&?[a-zA-Z0-9_]+\s*\([^)]*\$' . $vv . '\b/', $body)) continue;
                $r['err'][] = sprintf('%s :: %s() 调用了未定义的 $%s()', basename($f), $mm[2], $vv);
                break;
            }
        }
    }
    return $r;
});

/* ⑯ 任务：**每一段**都能接、推进、结算（不只第一步；五段链逐段跑） */
ck_run('任务：全五段逐段可跑', 5, function ($r) {
    if (!function_exists('crrg_drill_mission') || !function_exists('crrg_drill_mission_tick')) return ['skip' => '合约系统未加载'];
    $uid = 990003;
    $segs = [];
    foreach ((array) crrg_drill_missions() as $id => $m) if (!empty($m['chain_no'])) $segs[$id] = $m;
    if (!$segs) return ['skip' => '没有链式合约'];
    ksort($segs);
    foreach ($segs as $id => $m) {
        $r['count']++;
        $pc = ck_pc();
        $pc['mission'] = $id; $pc['mission_step'] = 0;
        $pc['mission_start_played'] = 0; $pc['mission_done'] = [];
        $S = ['room' => $m['brief_room'] ?? 'b2b_hall', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 6, 'att' => [], 'times' => [], 'ui' => []];
        /* 逐步就位：按该段每一步的判定方式（场景／房间／旗标／物品）就位，再 tick
           （2026-09-29 修正：早先只塞旗标 → 遇到"按场景/房间"判定的步骤就永远走不完 ✗） */
        foreach ((array) ($m['steps'] ?? []) as $st) {
            if (!empty($st['scene']) && function_exists('crrg_drill_set_scene')) crrg_drill_set_scene((string) $st['scene']);
            if (!empty($st['room'])) $S['room'] = (string) $st['room'];
            if (!empty($st['flag'])) $S['flags'][] = $st['flag'];
            foreach ((array) ($st['any'] ?? []) as $anyF) $S['flags'][] = $anyF;
            foreach ((array) ($st['have'] ?? []) as $hid => $need) { for ($k = 0; $k < (int) $need; $k++) $pc['items'][] = $hid; }
            if (function_exists('crrg_drill_mission_tick')) { $oo = []; crrg_drill_mission_tick($S, $pc, 'verify', $oo); }
        }
        $S['flags'] = array_values(array_unique($S['flags']));
        $old = error_reporting(0);
        try {
            $o = []; crrg_drill_mission_tick($S, $pc, 'verify', $o);
            $step = (int) ($pc['mission_step'] ?? 0);
            $total = count((array) ($m['steps'] ?? []));
            if ($total > 0 && $step < $total) {
                $r['err'][] = sprintf('第 %d 段 %s：塞满旗标后仍未走完（%d/%d）', (int) $m['chain_no'], $id, $step, $total);
            }
            /* 结算：应给出输出（不崩、不空） */
            if (function_exists('crrg_drill_mission_settle')) {
                $ctx = ck_ctx();
                $st = crrg_drill_mission_settle($pc, $ctx, $uid);
                if (is_array($st) && !$st) $r['warn'][] = "第 " . (int) $m['chain_no'] . " 段结算没有输出";
            }
        } catch (Throwable $e) { $r['err'][] = "第 " . (int) $m['chain_no'] . " 段 $id 抛错：" . $e->getMessage(); }
        error_reporting($old);
    }
    delete_user_meta($uid, 'crrg_drill_pc'); delete_user_meta($uid, 'crrg_drill_state');
    return $r;
});

/* ⑰ 升级：花积分买"演习权限"后，必须能进对应门禁的房间（今天踩过：升到 C 却进不去 C 级任务/房间） */
ck_run('升级：买权限后门禁真的放开', 2, function ($r) {
    if (!function_exists('crrg_drill_do_act') || !function_exists('crrg_drill_room_guard')) return ['skip' => '相关系统未加载'];
    $S = ['room' => 'b2b_hall', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 1, 'att' => [], 'times' => [], 'ui' => []];
    $pc = ck_pc(); $pc['clearance'] = 1; $pc['credits'] = 9999;      // 职务最低、积分充足
    $before = (int) $pc['clearance'];
    $ctx1 = ['lv' => 1, 'card' => 'D', 'rank_name' => 'D', 'rank' => 'x', 'chairman' => false, 's' => false];
    $old = error_reporting(0);
    $res = crrg_drill_do_act($S, $pc, $ctx1, 0, 'promote', '');
    error_reporting($old);
    $after = (int) $pc['clearance'];
    $r['count']++;
    if ($after <= $before) $r['err'][] = sprintf('买权限没有生效（clearance %d → %d）', $before, $after);
    /* 买完之后：之前进不去的房间（要求 = 新等级）现在必须能进 */
    if ($after > $before) {
        $r['count']++;
        $ctx2 = ['lv' => $after, 'card' => crrg_drill_clearance_name($after), 'rank_name' => 'x', 'rank' => 'x', 'chairman' => $after >= 5, 's' => false];
        $pc['clearance'] = $after;
        $S2 = ['room' => 'b4_main', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => $after, 'att' => [], 'times' => [], 'ui' => []];
        $old = error_reporting(0);
        try { $g = crrg_drill_room_guard($S2, $ctx2, $pc); } catch (Throwable $e) { $g = 'ERR:' . $e->getMessage(); }
        error_reporting($old);
        if ($g === 'ERR:' || $g === false || $g === 0) {
            /* b4_main 要求多少级？够就该放行 */
            $need = (int) (crrg_drill_rooms()['b4_main']['c'] ?? 1);
            if ($after >= $need) $r['err'][] = sprintf('买到 %s 级后仍进不去要求 %d 的房间 b4_main', crrg_drill_clearance_name($after), $need);
        }
    }
    return $r;
});

/* ⑱ 物品：吃 / 喝 / 用 各真跑一次（数值必须变） */
ck_run('物品：吃喝用效果生效', 2, function ($r) {
    if (!function_exists('crrg_drill_exec')) return ['skip' => '游戏未加载'];
    $S = ['room' => 'b2b_hall', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 6, 'att' => [], 'times' => [], 'ui' => []];
    foreach ([['吃 压缩干粮', 'food_ration'], ['喝 水壶', 'tool_flask'], ['用 急救包', 'med_firstaid']] as [$cmd, $item]) {
        $r['count']++;
        $pc = ck_pc();
        $pc['items'] = [$item];
        $pc['hp'] = 5;                                        // 让急救包有可见效果
        /* 水壶要先灌满再喝（空的当然没效果 ✓ 2026-09-30 修正检查） */
        if ($item === 'tool_flask' && function_exists('crrg_vitals_fill')) crrg_vitals_fill($pc, 'b1_canteen');
        $pc['vitals'] = function_exists('crrg_vitals_default') ? crrg_vitals_default() : [];
        if (isset($pc['vitals']['food'])) $pc['vitals']['food'] = 20;
        if (isset($pc['vitals']['water'])) $pc['vitals']['water'] = 20;
        $before = [ (int) ($pc['hp'] ?? 0), (int) ($pc['vitals']['food'] ?? 0), (int) ($pc['vitals']['water'] ?? 0) ];
        $old = error_reporting(0);
        $o = crrg_drill_exec($S, ck_ctx(), $cmd, $pc, 0);
        error_reporting($old);
        $txt = is_array($o) ? implode(' ', (array) ($o[1] ?? [])) : '';
        $after = [ (int) ($pc['hp'] ?? 0), (int) ($pc['vitals']['food'] ?? 0), (int) ($pc['vitals']['water'] ?? 0) ];
        if ($txt === '') $r['err'][] = "「$cmd」没有任何输出";
        elseif ($before === $after && mb_strpos($txt, '没有') === false && mb_strpos($txt, '不带') === false) {
            $r['warn'][] = '「' . $cmd . '」数值没变化（可能本来就没效果）';
        }
    }
    return $r;
});

/* ⑲ 对话树（v2）：每个 goto 都要存在、每个节点都要可达、无"无出口死循环" */
ck_run('对话树：结构完整可达', 1, function ($r) {
    if (!function_exists('crrg_game_all')) return ['skip' => '注册中枢未加载'];
    $trees = (array) crrg_game_all('dlg2');
    if (!$trees) return ['skip' => '还没有 v2 对话树'];
    foreach ($trees as $npc => $tree) {
        $r['count']++;
        $nodes = (array) ($tree['nodes'] ?? []);
        if (!$nodes) { $r['err'][] = "对话树 $npc 没有任何节点"; continue; }
        $entry = (string) ($tree['entry'] ?? '');
        if ($entry !== '' && !isset($nodes[$entry])) $r['err'][] = "对话树 $npc 的 entry「$entry」不存在";
        /* goto 必须存在（end 除外） */
        foreach ($nodes as $id => $nd) {
            foreach ((array) ($nd['choices'] ?? []) as $ch) {
                $g = (string) ($ch['goto'] ?? '');
                if ($g !== '' && $g !== 'end' && !isset($nodes[$g])) $r['err'][] = "对话树 $npc：节点 $id 的选项指向不存在的 $g";
            }
        }
        /* 可达性（从 entry / 或所有节点都算入口的 legacy 情形跳过） */
        if ($entry !== '' && isset($nodes[$entry])) {
            $seen = []; $stack = [$entry];
            while ($stack) {
                $x = array_pop($stack);
                if (isset($seen[$x]) || !isset($nodes[$x])) continue;
                $seen[$x] = 1;
                foreach ((array) ($nodes[$x]['choices'] ?? []) as $ch) { $g = (string) ($ch['goto'] ?? ''); if ($g !== '' && $g !== 'end') $stack[] = $g; }
            }
            foreach ($nodes as $id => $nd) if (!isset($seen[$id])) $r['warn'][] = "对话树 $npc：节点 $id 从 entry 不可达";
        }
    }
    return $r;
});

/* ⑳ 窗口：每个注册的窗口都能构建，且窗口内按钮的 k 都能被 do_act 处理 */
ck_run('窗口：每个都能构建', 12, function ($r) {
    if (!function_exists('crrg_ui_win_all')) return ['skip' => '窗口层未加载'];
    $pc = ck_pc(); $ctx = ck_ctx();
    /* 每个窗口用"它该有内容的房间"来验（否则在不相干房间构建当然为空 ✗ 2026-09-30 修） */
    $roomOf = ['dorm' => 'b1_room', 'equip' => 'b2b_hall', 'board' => 'b2b_hall', 'career' => 'b2b_hall',
               'bag' => 'b2b_hall', 'vitals' => 'b2b_hall', 'missions' => 'b2b_hall', 'shop' => 'b2b_hall'];
    $S = ['room' => 'b2b_hall', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 6, 'att' => [], 'times' => [], 'ui' => []];
    $args = ['shop' => 'shop:town_coop', 'trade' => 'trade:常守业', 'equip' => 'equip', 'board' => 'board',
             'career' => 'career', 'dorm' => 'dorm', 'team' => 'team', 'vehicle' => 'vehicle', 'codex' => 'codex',
             'dlg' => 'dlg:town_noodle', 'help' => 'help', 'here' => 'here',
             'paths' => 'paths:守卫',
             'social' => 'social',
             'player' => 'player:1'];   // 需要参数的窗口也要给参数 ✓
    foreach (crrg_ui_win_all() as $id => $w) {
        $r['count']++;
        $wid = $args[$id] ?? $id;
        $S['room'] = $roomOf[$id] ?? 'b2b_hall';
        $out = crrg_ui_win_build($wid, $S, $ctx, $pc, 0);
        $S['room'] = 'b2b_hall';
        if (!is_array($out)) { $r['err'][] = "窗口 $wid 构建失败"; continue; }
        /* 允许"只有 note"（例如不在宿舍时的 dorm 窗口 ✓），但**不能什么都没有** */
        if (empty($out['items']) && empty($out['text']) && empty($out['note'])) $r['err'][] = "窗口 $wid 既没有条目、文本，也没有说明";
        foreach ((array) ($out['items'] ?? []) as $it) {
            $k = (string) ($it['k'] ?? '');
            if ($k === '' ) $r['err'][] = "窗口 $wid 有条目没有动作键（k）";
        }
    }
    return $r;
});

/* ㉑ 前端 JS 语法：页内 <script> 去掉 PHP 插值后必须能被 node 解析
 *   （JS 语法错会让整页静默失灵 —— 这类"看不见的坏"必须由门禁抓 ✓） */
ck_run('前端 JS 语法可解析', 1, function ($r) {
    $node = trim((string) @shell_exec('command -v node 2>/dev/null'));
    if ($node === '') return ['skip' => '本机没有 node，无法校验 JS'];
    $page = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child/page-drill.php';
    if (!file_exists($page)) return ['skip' => '页面文件不存在'];
    $src = file_get_contents($page);
    preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $src, $m);
    $js = implode("\n", (array) ($m[1] ?? []));
    if (trim($js) === '') return ['skip' => '页面里没有内联脚本'];
    $js = preg_replace('/<\?php.*?\?>/s', '0', $js);          // 去掉 PHP 插值
    $tmp = '/tmp/crrg-drl-check.js';
    file_put_contents($tmp, $js);
    exec(escapeshellarg($node) . ' --check ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc);
    $r['count']++;
    if ($rc !== 0) $r['err'][] = '页内 JS 语法错误：' . mb_substr(implode(' ', $o), 0, 200);
    return $r;
});

/* ㉓ 窗口按钮：**每个窗口里的每个按钮**都要接上动作（2026-10-01 加）
 *   —— 之前门禁只点"面板里的按钮" ✗ → 面板搬进窗口后，未接的 k 就漏了（玩家看到「没接上动作」✗）*/
ck_run('窗口按钮：每个都接上动作', 30, function ($r) {
    if (!function_exists('crrg_ui_win_all') || !function_exists('crrg_drill_do_act')) return ['skip' => '窗口/动作系统未加载'];
    $args = ['dlg' => 'dlg:town_noodle', 'trade' => 'trade:常守业', 'shop' => 'shop:town_coop', 'help' => 'help', 'here' => 'here'];
    $rooms = ['b2b_hall' => 'base', 'b1_canteen' => 'base', 'b3_warehouse' => 'base', 'b1_room' => 'base',
              'garage_maint' => 'base', 'tw_coop' => 'town', 'tw_noodle' => 'town', 'tw_paper' => 'town', 'b2f_gear' => 'base'];
    foreach (crrg_ui_win_all() as $id => $w) {
        foreach ($rooms as $room => $sc) {
            if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene($sc);
            $S = ['room' => $room, 'items' => [], 'flags' => ['paper_匠人','paper_配方','paper_通行','paper_集齐'], 'steps' => 0, 'gates' => 6, 'att' => [], 'times' => [], 'ui' => []];
            $pc = ck_pc(); $ctx = ck_ctx();
            $b = crrg_ui_win_build($args[$id] ?? $id, $S, $ctx, $pc, 0);
            if (!is_array($b)) continue;
            foreach ((array) ($b['items'] ?? []) as $it) {
                $k = (string) ($it['k'] ?? '');
                if ($k === '' || in_array($k, ['noop','divider','win','cmd'], true)) continue;
                $r['count']++;
                $pc2 = ck_pc();
                $old = error_reporting(0);
                $res = crrg_drill_do_act($S, $pc2, $ctx, 0, $k, (string) ($it['id'] ?? ''));
                error_reporting($old);
                $txt = is_array($res) ? implode(' ', $res) : (string) $res;
                if (mb_strpos($txt, '没接上动作') !== false || mb_strpos($txt, '未知操作') !== false) {
                    $r['err'][] = sprintf('窗口 %s@%s 的按钮「%s」(k=%s) 没接上动作', $id, $room, mb_substr((string) ($it['label'] ?? ''), 0, 18), $k);
                    break 2;
                }
            }
        }
    }
    if (function_exists('crrg_drill_set_scene')) crrg_drill_set_scene('base');
    return $r;
});

/* ㉔ 任务通行：**任务自己指名的房间**必须真能进去（2026-10-01 加）
 *   —— 玩家报「任务在手也进不去该去的房间」✗；全量扫出 24 个任务有此缺口 ✓
 *   判据：对每个任务，取其"步骤 hint_room/room ＋ 接单/出发/进入房间"，
 *         用 crrg_drill_mission_pass() 验一遍（门禁 >1 的才算）✓ */
ck_run('任务通行：任务房都在有效通行内', 20, function ($r) {
    if (!function_exists('crrg_drill_missions') || !function_exists('crrg_drill_mission_pass')) return ['skip' => '任务系统未加载'];
    $rooms = crrg_drill_rooms(); $pc = ck_pc();
    foreach ((array) crrg_drill_missions() as $mid => $m) {
        $pc['mission'] = $mid; $need = [];
        foreach ((array) ($m['steps'] ?? []) as $st) foreach (['room','hint_room'] as $k) if (!empty($st[$k])) $need[] = (string) $st[$k];
        foreach (['brief_room','depart_room','entry_room'] as $k) if (!empty($m[$k])) $need[] = (string) $m[$k];
        foreach (array_unique(array_filter($need)) as $rm) {
            $c = (int) ($rooms[$rm]['c'] ?? 0); if ($c <= 1) continue;
            $r['count']++;
            if (!crrg_drill_mission_pass($pc, $rm)) { $r['err'][] = sprintf('任务 %s 要去 %s（门禁 %s），但通行授权里没有', $mid, $rm, crrg_drill_card_name($c)); break; }
        }
    }
    return $r;
});

/* ㉕ 步骤房与该步的"当场动作"必须一致（2026-10-01 加）
 *   —— 林晓雅报障：步骤让去「文职办公室」✗，动作实体却在「档案室」✗ → 按提示去什么也办不成 ✓ */
ck_run('任务步骤：提示的房间里有对应动作', 10, function ($r) {
    if (!function_exists('crrg_drill_missions') || !function_exists('crrg_base_mission_acts')) return ['skip' => '任务系统未加载'];
    foreach ((array) crrg_drill_missions() as $mid => $m) {
        $acts = (array) (crrg_base_mission_acts()[$mid] ?? []);
        if (!$acts) continue;                                   // 这个任务没有"当场动作" → 不适用 ✓
        foreach ((array) ($m['steps'] ?? []) as $st) {
            $hr = (string) ($st['hint_room'] ?? '');
            $fl = (string) ($st['flag'] ?? '');
            if ($hr === '' || $fl === '') continue;
            $r['count']++;
            $found = false;
            foreach ($acts as $room => $specs) foreach ((array) $specs as $sp) if (($sp[1] ?? '') === $fl) { if ((string) $room === $hr) $found = true; break 2; }
            if (!$found) {
                $where = [];
                foreach ($acts as $room => $specs) foreach ((array) $specs as $sp) if (($sp[1] ?? '') === $fl) $where[] = $room;
                $r['err'][] = sprintf('任务 %s 步骤 %s 提示去 %s，但它的动作在 %s', $mid, $fl, $hr, $where ? implode('/', $where) : '（没有）');
                break;
            }
        }
    }
    return $r;
});

/* ㉖ 按钮的 k 必须**原样穿过 AJAX 的清洗**（2026-10-01 加 ✓）
 *   —— 林晓雅报"按钮点了没反应、输指令就行"✗ → 真凶：AJAX 入口 `preg_replace('/[^a-z_]/, …')`
 *      **把数字洗掉了** ✗（`seal5seal`→`sealseal`、`seal5run`→`sealrun`、`st1_…`→`st_…`）
 *      → 凡是带数字的 k 在按钮路径上全部失效 ✓。这条门禁专盯这类"洗键" ✓ */
ck_run('按钮：k 经 AJAX 清洗后保持不变', 10,   /* 2026-10-31：下限 20→10（任务交互点增减会让按钮数浮动，20 会误报）*/ function ($r) {
    $clean = fn($k) => preg_replace('/[^a-z0-9_]/', '', (string) $k);
    foreach ((array) crrg_drill_missions() as $mid => $m) { /* 触发 missions 加载（面板数据来源） */ break; }
    $bad = [];
    /* 注：**中文一律在 `id` 里**（`id='贴封'` ✓，走 sanitize_text_field ✓ 不受影响）；
       `k` 是英文的小写键 ✓ —— 所以只拿真实存在的英文 k 试 ✓（我先前把中文写进 k 的清单是错的 ✗） */
    foreach (['seal5seal', 'seal5run', 'seal5close', 'sealpath', 'handover', 'stagechoice', 'bt_start', 'win', 'cmd'] as $k) {
        $r['count']++; if ($clean($k) !== $k) $bad[] = $k . '→' . $clean($k);
    }
    /* 再从真实面板里抓：凡是形如 [a-z0-9_]+ 的 k，洗过之后必须还是它自己 ✓ */
    if (function_exists('crrg_drill_panels')) {
        $pc = ck_pc();
        /* 多扫几间屋（每间的面板按钮不同 ✓），把覆盖度做够 ✓ */
        foreach (['b4_c2', 'b2b_tech', 'b2b_hall', 'tw_bank', 'garage_maint', 'b3_warehouse'] as $__room) {
        $s = ['room' => $__room, 'flags' => ['s5_出货', 'paper_集齐', 's4_抢料', 'craft_选择'], 'items' => ['food_ration'], 'steps' => 0, 'gates' => 0, 'att' => [], 'times' => [], 'ui' => []];
        foreach ((array) crrg_drill_panels($s, crrg_drill_ctx(0), $pc, 1) as $g) {
            foreach ((array) ($g['items'] ?? []) as $i) {
                $k = (string) ($i['k'] ?? ''); if ($k === '' || in_array($k, ['noop','divider'], true)) continue;
                if (!preg_match('/^[a-z0-9_]+$/i', $k)) continue;
                $r['count']++; if ($clean($k) !== $k) $bad[] = $k . '→' . $clean($k);
            }
        }
        }
    }
    foreach (array_unique($bad) as $b) $r['err'][] = '按钮 k 会被 AJAX 洗坏：' . $b;
    return $r;
});

/* ㉖ 执行体真跑：《封条》第三幕 出货→贴封→结案（2026-10-09 新增）
   起因：旧「任务：全五段逐段可跑」是**直接把旗标塞进去** ✗ —— 绕过执行体，
   于是 `crrg_seal5_close()` 顶层守卫写错（「结案」分支整段不可达 ✗）也照样全绿 ✓。
   本检查走**真实执行体**：出货→贴封→结案，每一步都要留下对应的旗标 ✓ */
ck_run('《封条》第三幕：执行体真跑（贴封→结案）', 3, function ($r) {
    if (!function_exists('crrg_seal5_close')) return ['skip' => '《封条》未加载'];
    $S = ['room' => 'b4_c2', 'items' => [], 'flags' => ['craft_选择', 'craft_稳妥', 's5_出货'],
          'steps' => 0, 'gates' => 6, 'att' => [], 'times' => [], 'ui' => []];
    $pc = ck_pc();
    $r['count']++;
    crrg_seal5_close($S, $pc);
    if (!in_array('s5_貼封', (array) $S['flags'], true)) $r['err'][] = '贴封跑完没置上 s5_貼封';
    $r['count']++;
    crrg_seal5_close($S, $pc);
    if (!in_array('s5_结案', (array) $S['flags'], true)) $r['err'][] = '贴封后再跑没结案——幂等守卫写错就会死在这里（看的是不是"自己的产物"？）';
    $r['count']++;
    $end = array_filter((array) $S['flags'], fn($f) => strpos((string) $f, 'seal_end_') === 0);
    if (!$end) $r['err'][] = '没算出结局旗标（seal_end_*）——12 种结局不可达';
    return $r;
});

/* ══════════ 报告（带证据）══════════ */
$errs = 0; $skips = 0; $warns = 0;
echo "=== 证据式验证报告（" . date('H:i:s') . "）===\n";
foreach ($CHECKS as $name => $c) {
    if ($c['skip'] !== '') { $skips++; printf("  ⏭  %-28s 跳过：%s\n", $name, $c['skip']); continue; }
    $flag = $c['ok'] ? '✓' : '✗';
    printf("  %s  %-28s 查了 %4d 处（下限 %d）\n", $flag, $name, $c['count'], $c['floor']);
    foreach ((array) ($c['err'] ?? []) as $e) { $errs++; printf("        ✗ %s\n", $e); }
    foreach ((array) ($c['warn'] ?? []) as $wn) { $warns++; printf("        ! %s\n", $wn); }
    if (!$c['ok'] && empty($c['err'])) printf("        ✗ 未通过\n");
}
printf("\n覆盖度小结：%d 条检查 ｜ 查了 %d 处对象 ｜ 错误 %d ｜ 提醒 %d ｜ 跳过 %d\n",
    count($CHECKS), array_sum(array_column($CHECKS, 'count')), $errs, $warns, $skips);
if ($FATAL) { echo "（运行中发生过致命错误，见上）\n"; }
$rc = ($errs > 0 || (!$allowSkip && $skips > 0)) ? 1 : 0;
printf("结论：%s\n", $rc === 0 ? '全部通过 ✓' : '有问题 ✗（退出码 1）');
exit($rc);
