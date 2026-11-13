<?php
/**
 * CRRG 演习系统 · 窗口层（2026-09-30）
 * ------------------------------------------------------------
 * 目标（委员长提出）：把"一长串卡片按钮"逐步换成**可拖动、可关闭的窗口**；
 *   由**指令**或**按钮**打开；交易这类还能**从对话里打开**。
 *
 * 分工：
 *   · 服务端只负责"窗口里有什么"（**数据**）：title / note / text / items[]（每个 item 就是原来那种按钮）
 *   · 前端 `page-drill.php` 里的窗口管理器负责"长什么样"（拖动、关闭、叠放）
 *   · 因此**老的按钮机制完全不用改**：窗口里的按钮仍走 `k` / `id` → 同一个 do_act ✓
 *
 * 打开方式（三种，都走同一个通道）：
 *   ① 玩家输入指令：`背包` / `身体` / `合约` / `商店` → 动作里置 `$s['ui_open'] = '窗口id'`
 *   ② 点按钮：`k = 'win'`、`id = 窗口id`（可带参数，如 `shop:tw_coop`）
 *   ③ 对话效果：`'open' => 'trade:常守业'` → 对话层把 ui_open 置上
 */
if (!defined('ABSPATH')) exit;

/** 注册一个窗口（类型 'window' 必须在 crrg_game_types() 里登记过 ✓） */
function crrg_ui_win_register($id, $builder, $opts = []) {
    if (!function_exists('crrg_game_register')) return false;
    return crrg_game_register('window', $id, [
        'builder' => $builder,
        'title'   => (string) ($opts['title'] ?? $id),
        'order'   => (int) ($opts['order'] ?? 50),
        'w'       => (int) ($opts['w'] ?? 340),
    ]);
}
function crrg_ui_win_all() { return function_exists('crrg_game_all') ? (array) crrg_game_all('window') : []; }

/**
 * 构建窗口内容。
 * @param string $id    'bag' | 'vitals' | 'missions' | 'shop:tw_coop' | 'trade:常守业'
 * @return array|null   ['id','title','note','text'=>[…],'items'=>[['label','k','id','off']]]
 */
function crrg_ui_win_build($id, &$s, &$ctx, &$pc, $uid = 0) {
    $arg = '';
    if (strpos($id, ':') !== false) { [$id, $arg] = explode(':', $id, 2); }
    $w = crrg_ui_win_all()[$id] ?? null;
    if (!$w) return null;
    $fn = $w['builder'] ?? '';
    if ($fn === '' || !is_callable($fn)) return null;
    $out = (array) $fn($s, $ctx, $pc, $uid, $arg);
    if (!$out) return null;
    return array_merge(['id' => $id, 'arg' => (string) $arg, 'title' => (string) ($w['title'] ?? $id), 'w' => (int) ($w['w'] ?? 340)], $out);
}

/** 取窗口内容并清掉"待打开"标记（前端用）
 *  2026-09-30：**先看全局通道** —— 因为 exec 里有些 case 会重写 `$s`（连 $s['ui_open'] 一起丢 ✗） */
function crrg_ui_take_open(&$s) {
    /* 2026-10-01 加保险：**两个通道一起清** ✓（原来走全局那条就直接返回 ✗ → 状态里的标记留着 ✓
       一旦被存进档，之后每次动作都会重弹 ✓ —— 玩家报的"按别的键又弹出上一个窗口"就是这个 ✓） */
    $out = '';
    $g = (string) ($GLOBALS['crrg_ui_open'] ?? '');
    if ($g !== '') { $out = $g; $GLOBALS['crrg_ui_open'] = ''; }
    if (isset($s['ui_open'])) { if ($out === '') $out = (string) $s['ui_open']; unset($s['ui_open']); }
    return $out;
}
/** 请求**关闭**某窗口（2026-10-01）：对话说到"结束"时用 ✓
 *  与开窗对称：写 $s 与 $GLOBALS 两个通道 ✓ */
function crrg_ui_request_close(&$s, $winId) {
    $winId = (string) $winId;
    $s['ui_close'] = $winId;
    $GLOBALS['crrg_ui_close'] = $winId;
    /* 保险：同一次响应里不要再"打开"同一个窗 ✓ */
    unset($s['ui_open']);
    $GLOBALS['crrg_ui_open'] = '';
}
/** 取"待关闭"标记（前端用 ✓） */
function crrg_ui_take_close(&$s) {
    $g = (string) ($GLOBALS['crrg_ui_close'] ?? '');
    if ($g !== '') { $GLOBALS['crrg_ui_close'] = ''; return $g; }
    $o = (string) ($s['ui_close'] ?? '');
    if ($o !== '') { unset($s['ui_close']); }
    return $o;
}

/** 动作里请求打开某窗口（指令／按钮／对话都用它）
 *  同时写**两个通道**：$s（正常路径 ✓）与 $GLOBALS（对"$s 被重写"免疫 ✓） */
function crrg_ui_request_open(&$s, $winId) {
    $winId = (string) $winId;
    $s['ui_open'] = $winId;
    $GLOBALS['crrg_ui_open'] = $winId;
}

/* ══════════ 五个窗口 ══════════ */

/* ① 背包 */
crrg_ui_win_register('bag', function (&$s, &$ctx, &$pc, $uid, $arg) {
    $items = (array) ($pc['items'] ?? []);
    $rows = [];
    $cnt = [];
    foreach ($items as $it) $cnt[$it] = ($cnt[$it] ?? 0) + 1;
    if (!$cnt) return ['text' => ['（身上空空。）'], 'items' => []];
    foreach ($cnt as $it => $n) {
        $nm = function_exists('crrg_drill_item_name') ? crrg_drill_item_name($it) : $it;
        $rows[] = ['label' => $nm . ' ×' . $n, 'k' => 'noop', 'id' => ''];
        /* 2026-10-01 修：这两个按钮原来带 k=use/drop ✗ —— do_act 里没有对应 case → 点了回「没接上动作」✗
           改成**直接携带真实指令**（k=cmd ✓）→ 点它等于敲「用 X」「放下 X」✓ */
        $rows[] = ['label' => '　用「' . $nm . '」', 'k' => 'cmd', 'id' => '用 ' . $nm];
        $rows[] = ['label' => '　放下',           'k' => 'cmd', 'id' => '放下 ' . $nm];
    }
    return ['note' => '先看，再用／放下。', 'text' => [], 'items' => array_slice($rows, 0, 60)];
}, ['title' => '背包', 'order' => 10]);

/* ② 身体状态 */
crrg_ui_win_register('vitals', function (&$s, &$ctx, &$pc, $uid, $arg) {
    $txt = function_exists('crrg_vitals_lines') ? (array) crrg_vitals_lines($pc) : [];
    /* 2026-10-14（madness）：理智与症状一起显示（面板可见 ✓） */
    if (function_exists('crrg_mad_panel')) $txt = array_merge($txt, [''], (array) crrg_mad_panel($pc));
    return ['note' => '吃、喝、睡都在这里。', 'text' => $txt, 'items' => [
        ['label' => '喝口水（有水壶就喝壶里的）', 'k' => 'cmd', 'id' => '喝'],
        ['label' => '打盹 2 小时', 'k' => 'cmd', 'id' => '睡觉 2'],
        ['label' => '睡 8 小时',   'k' => 'cmd', 'id' => '睡觉 8'],
    ]];
}, ['title' => '身体状态', 'order' => 11]);

/* ③ 合约／任务 */
crrg_ui_win_register('missions', function (&$s, &$ctx, &$pc, $uid, $arg) {
    $rows = [];
    $cur = (string) (($pc['mission'] ?? '') ?: ($pc['mission2']['id'] ?? ''));
    if ($cur !== '' && function_exists('crrg_drill_mission')) {
        $m = crrg_drill_mission($cur);
        $rows[] = ['label' => '在手：' . (string) ($m['name'] ?? $cur), 'k' => 'noop', 'id' => ''];
        foreach ((array) ($m['steps'] ?? []) as $i => $st) {
            /* 2026-10-01 修：打勾原来只看**进度序号**（$i < mission_step ✗）→ 玩家**跳着做**时，
               已完成的步骤不会打勾（"我做了怎么没勾上"✗）。
               现在改为：**进度已过 或 该步自身条件已满足**（复用游戏自己的 crrg_drill_step_done ✓ 一份真相 ✓） */
            $done = ($i < (int) ($pc['mission_step'] ?? 0));
            if (!$done && function_exists('crrg_drill_step_done')) {
                $done = (bool) crrg_drill_step_done($st, $s, $pc, '');
            }
            $rows[] = ['label' => ($done ? '　✓ ' : '　· ') . (string) ($st['text'] ?? ''), 'k' => 'noop', 'id' => ''];
        }
        $rows[] = ['label' => '交单／结算', 'k' => 'settle_now', 'id' => ''];
        $rows[] = ['label' => '放弃合约', 'k' => 'abandon', 'id' => ''];
        /* 出勤（出发）：原先在面板单独一块，2026-09-30 并入本窗口 ✓ */
        $mRoom = (string) ($m['depart_room'] ?? '');
        if ($mRoom !== '' && $mRoom === (string) ($s['room'] ?? '')) {
            $rows[] = ['label' => '出发 · ' . (string) ($m['name'] ?? ''), 'k' => 'depart', 'id' => ''];
            $rows[] = ['label' => '出勤（需车费 10 积分／100 元）', 'k' => 'depart', 'id' => ''];
        }
    } else {
        $rows[] = ['label' => '（手上没有合约。值班板能接。）', 'k' => 'noop', 'id' => ''];
    }
    return ['note' => '日常与值班板合约都从这里接。', 'text' => [], 'items' => $rows];
}, ['title' => '合约', 'order' => 12]);

/* ④ 商店（基地/镇上通用） */
crrg_ui_win_register('shop', function (&$s, &$ctx, &$pc, $uid, $arg) {
    /* 2026-09-30：改为调用**共用** crrg_shop_block()（与面板逐字同源 ✓）
       · 没给 id：本间**只有一家店就直接展开**（与面板体验一致 ✓ 不是"先列店再点"的两步 ✗）
       · 多家店：列出店名，点进去看 ✓ */
    if (!function_exists('crrg_drill_shops_at') || !function_exists('crrg_shop_block')) return ['text' => ['（商店系统未加载。）'], 'items' => []];
    $room = (string) ($s['room'] ?? '');
    $here = (array) crrg_drill_shops_at($room);
    /* 2026-10-14：arg 支持 `店id` 或 `店id:大类:页`（分类栏/翻页用 ✓） */
    $parts = explode(':', (string) $arg);
    $sidA  = (string) ($parts[0] ?? '');
    $flt   = isset($parts[1]) ? implode(':', array_slice($parts, 1)) : '';
    if ($sidA !== '' && isset($here[$sidA])) {
        $b = crrg_shop_block($sidA, $here[$sidA], $s, $ctx, $pc, $uid, $flt);
        return ['note' => (string) ($b['note'] ?? ''), 'text' => [], 'items' => (array) ($b['items'] ?? [])];
    }
    if (count($here) === 1) {                       // 只有一家 → 直达 ✓
        $sid = array_key_first($here);
        $b = crrg_shop_block($sid, $here[$sid], $s, $ctx, $pc, $uid, $flt);
        return ['note' => (string) ($b['note'] ?? ''), 'text' => [], 'items' => (array) ($b['items'] ?? [])];
    }
    if (!$here) return ['note' => '这间没有店。', 'text' => [], 'items' => []];
    $rows = [];
    foreach ($here as $sid => $sp) {
        $bb = crrg_shop_block($sid, $sp, $s, $ctx, $pc, $uid);
        $cnt = 0; foreach ((array) ($bb['items'] ?? []) as $it) if (($it['k'] ?? '') !== 'divider') $cnt++;
        $rows[] = ['label' => (string) ($sp['n'] ?? $sid) . '（' . $cnt . ' 件）', 'k' => 'win', 'id' => 'shop:' . $sid];
    }
    return ['note' => '这间有几家店，点进去看。', 'text' => [], 'items' => $rows];
}, ['title' => '商店', 'order' => 20, 'w' => 380]);

/* ⑤ 与 NPC 的交易（**从对话里打开** ✓） */
crrg_ui_win_register('trade', function (&$s, &$ctx, &$pc, $uid, $arg) {
    $npc = trim((string) $arg);
    if ($npc === '') return null;
    $fav = (int) ($pc['npc_fav'][$npc] ?? 0);
    $goods = [
        '常守业' => [
            'doc_notepad' => ['n' => '一叠草纸', 'p' => 20],
            'food_rice'   => ['n' => '一小袋米', 'p' => 15],
        ],
    ][$npc] ?? [];
    $rows = [];
    foreach ($goods as $gid => $g) $rows[] = ['label' => $g['n'] . '　' . (int) $g['p'] . ' 元', 'k' => 'buy', 'id' => (string) $gid];
    if (!$rows) $rows[] = ['label' => '（他没什么可卖的。）', 'k' => 'noop', 'id' => ''];
    return [
        'note'  => '和 ' . $npc . ' 交易（好感 ' . $fav . '）——价由他定，人情也是价。',
        'text'  => [],
        'items' => $rows,
    ];
}, ['title' => '交易', 'order' => 30, 'w' => 360]);

/* ══════════ 第二批：装备 / 值班板 / 账户（复用现有面板构建结果，保证与旧面板一致 ✓） ══════════ */

/** 从现有面板里挑出某个区块（按标题包含某词）→ 转成窗口内容（避免重复实现 ✓） */
function crrg_ui_from_panels(&$s, &$ctx, &$pc, $uid, array $match) {
    if (!function_exists('crrg_drill_panels')) return null;
    $bl = (array) crrg_drill_panels($s, $ctx, $pc, $uid);
    $items = []; $note = ''; $text = [];
    foreach ($bl as $g) {
        $title = (string) ($g['title'] ?? '');
        foreach ($match as $kw) if (mb_strpos($title, $kw) !== false) {
            $note = (string) ($g['note'] ?? $note);
            foreach ((array) ($g['items'] ?? []) as $it) {
                if (in_array((string) ($it['k'] ?? ''), ['divider'], true)) { $items[] = ['label' => '— ' . ($it['label'] ?? '') . ' —', 'k' => 'noop', 'id' => '']; continue; }
                $items[] = ['label' => (string) ($it['label'] ?? ''), 'k' => (string) ($it['k'] ?? 'noop'), 'id' => (string) ($it['id'] ?? '')];
            }
            break;
        }
    }
    return $items ? ['note' => $note, 'text' => $text, 'items' => $items] : null;
}

/** 装备：穿在身上的 + 可脱下的 */
crrg_ui_win_register('equip', function (&$s, &$ctx, &$pc, $uid, $arg) {
    /* 直读装备系统（2026-09-30；面板区块已摘除 ✓） */
    if (!function_exists('crrg_equip_slots')) return ['text' => ['（装备系统未加载。）'], 'items' => []];
    $eq = (array) ($pc['equip'] ?? []);
    $rows = [];
    foreach (crrg_equip_slots() as $sl => $n) {
        if (empty($eq[$sl])) continue;
        $rows[] = ['label' => '脱下 · ' . $n . '（' . crrg_drill_item_name($eq[$sl]) . '）', 'k' => 'unwear', 'id' => (string) $sl];
    }
    $armor = function_exists('crrg_equip_armor') ? (int) crrg_equip_armor($pc) : 0;
    return $rows
        ? ['note' => '护甲合计 ' . $armor . '；也可以直接说「穿戴 X」「脱下 X」。', 'text' => [], 'items' => $rows]
        : ['note' => '护甲合计 ' . $armor, 'text' => ['（身上没有穿戴。）'], 'items' => []];
}, ['title' => '装备', 'order' => 13]);

/** 值班板：任务清单（长了就得用窗口 ✓） */
crrg_ui_win_register('board', function (&$s, &$ctx, &$pc, $uid, $arg) {
    /* 直读 crrg_board_items()（与面板同源 ✓ 2026-09-30） */
    if (!function_exists('crrg_board_items')) return ['text' => ['（值班板系统未加载。）'], 'items' => []];
    $items = (array) crrg_board_items($s, $ctx, $pc, $uid);
    /* 值班员的入口提示（2026-09-30 自面板「指挥大厅 · 值班员」块并入 ✓ 否则摘面板会丢这两行 ✗） */
    if (($s['room'] ?? '') === 'b2b_hall') {
        $done_train = in_array('m_train_01', (array) ($pc['mission_done'] ?? []), true);
        $heldNow = ($pc['mission'] ?? '') !== '' || ($pc['mission2']['id'] ?? '') !== '';
        $pre = [];
        if (!$done_train && !$heldNow) $pre[] = ['label' => '接：新手训练考核（正式合约，有奖励）', 'k' => 'accept', 'id' => 'm_train_01'];
        $lim = 0;
        foreach ((array) crrg_drill_missions() as $mid2 => $m2) {
            if (!empty($m2['repeat']) && (int) ($m2['min_lv'] ?? 3) <= crrg_drill_eff_lv($pc, $ctx) && !in_array($mid2, (array) ($pc['mission_done'] ?? []), true)) $lim++;
        }
        if ($lim) $pre[] = ['label' => '今日日常合约：' . $lim . ' 条可做（见值班板）', 'k' => 'noop', 'id' => '', 'off' => true];
        if ($heldNow) $pre[] = ['label' => '交任务 · 结算（与「交报告」等价）', 'k' => 'settle_now', 'id' => ''];
        $items = array_merge($pre, $items);
    }
    if (!$items) return ['note' => '贴在指挥大厅东侧墙上。', 'text' => ['（现在没有可接的。）'], 'items' => []];
    return ['note' => '贴在指挥大厅东侧墙上，值班员在这登记。', 'text' => [], 'items' => $items];
}, ['title' => '值班板', 'order' => 14, 'w' => 420]);

/** 账户：卡级／积分／钱／声望／晋升／车辆 */
crrg_ui_win_register('career', function (&$s, &$ctx, &$pc, $uid, $arg) {
    /* 直读卡级/积分/钱/声望（2026-09-30；面板区块已摘除 ✓） */
    $lv   = function_exists('crrg_drill_eff_lv') ? crrg_drill_eff_lv($pc, $ctx) : 0;
    $name = function_exists('crrg_drill_clearance_name') ? crrg_drill_clearance_name($lv) : (string) $lv;
    $rows = [['label' => sprintf('演习权限 %s ｜ 积分 %d ｜ 钱 %d', $name, (int) ($pc['credits'] ?? 0), (int) ($pc['money'] ?? 0)), 'k' => 'noop', 'id' => '']];
    if (function_exists('crrg_rep_places') && function_exists('crrg_rep_of')) {
        foreach ((array) crrg_rep_places() as $pid => $pp) {
            $v = (int) crrg_rep_of($pc, (string) $pid);
            $tier = function_exists('crrg_rep_tier') ? (array) crrg_rep_tier($pc, (string) $pid) : [];
            $rows[] = ['label' => sprintf('%s：%d（%s）', (string) ($pp['short'] ?? $pid), $v,
                (string) ($tier['n'] ?? '')), 'k' => 'repview', 'id' => (string) $pid];
        }
    }
    $rows[] = ['label' => '演习权限晋升', 'k' => 'promote', 'id' => ''];
    $rows[] = ['label' => '我的车辆', 'k' => 'vehicle', 'id' => ''];
    return ['note' => sprintf('积分 %d ｜ 钱 %d 元 ｜ 演习权限 %s', (int) ($pc['credits'] ?? 0), (int) ($pc['money'] ?? 0), $name), 'text' => [], 'items' => $rows];
}, ['title' => '账户', 'order' => 15]);

/* ══════════ 第三批：宿舍 / 队伍 / 车辆 / 图鉴 ══════════ */

crrg_ui_win_register('dorm', function (&$s, &$ctx, &$pc, $uid, $arg) {
    /* 直读宿舍三块（2026-09-30 抽块后）：宿舍 / 拆下退掉 / 储物柜 —— 与面板同源 ✓ */
    $items = []; $note = '家具与装修都在这儿；拆下按半价退回。';
    foreach (['crrg_dorm_block', 'crrg_dorm_unsell_block', 'crrg_stash_block'] as $fn) {
        if (!function_exists($fn)) continue;
        $b = $fn($s, $ctx, $pc, $uid);
        if (is_array($b) && !empty($b['items'])) { foreach ((array) $b['items'] as $it) $items[] = $it; if (!empty($b['note'])) $note = (string) $b['note']; }
    }
    if (!$items) return ['note' => '（这里不是宿舍，或还没添置东西。）', 'text' => [], 'items' => []];
    return ['note' => $note, 'text' => [], 'items' => $items];
}, ['title' => '宿舍', 'order' => 16, 'w' => 380]);

crrg_ui_win_register('team', function (&$s, &$ctx, &$pc, $uid, $arg) {
    /* 直读 crrg_team_block()（2026-09-30 抽块后 ✓ 与面板同源） */
    if (!function_exists('crrg_team_block')) return ['text' => ['（队伍系统未加载。）'], 'items' => []];
    $b = crrg_team_block($s, $ctx, $pc, $uid);
    if (!is_array($b)) return ['note' => '（还没有队伍：面板上可以创建或加入。）', 'text' => [], 'items' => []];
    return ['note' => (string) ($b['note'] ?? ''), 'text' => [], 'items' => (array) ($b['items'] ?? [])];
}, ['title' => '队伍', 'order' => 17, 'w' => 380]);

crrg_ui_win_register('vehicle', function (&$s, &$ctx, &$pc, $uid, $arg) {
    /* 直读 crrg_vehicle_block()（2026-09-30 抽块后 ✓ 与面板同源）
       ＋ 并入原面板「出行 · 去地表」块（车库 → 青石镇 三个选项 ✓ 2026-09-30） */
    $rows = [];
    $note = '';
    if (function_exists('crrg_vehicle_block')) {
        $b = crrg_vehicle_block($s, $ctx, $pc, $uid);
        if (is_array($b)) { $rows = (array) ($b['items'] ?? []); $note = (string) ($b['note'] ?? ''); }
    }
    if ((string) ($s['room'] ?? '') === 'garage_maint' && function_exists('crrg_drill_cur_scene') && crrg_drill_cur_scene() === 'base') {
        $act = function ($label, $k, $id = '', $extra = []) { return ['label' => $label, 'k' => $k, 'id' => $id] + $extra; };
        if ($rows) $rows[] = ['label' => '出行', 'k' => 'divider', 'id' => ''];
        if (function_exists('crrg_drill_has_vehicle') && crrg_drill_has_vehicle($pc)) {
            $rows[] = $act('自驾 ' . ($pc['vehicle'] ?? '') . ' 去青石镇（免费）', 'travel', 'town:car');
        }
        $rows[] = $act('坐基地车去青石镇（10 积分）', 'travel', 'town:credits');
        $rows[] = $act('搭车去青石镇（100 元）', 'travel', 'town:money');
        if ($note === '') $note = '土路上公路，往东约二十公里是青石镇。';
    }
    if (!$rows) return ['note' => '（这里看不到车：在车库／加油站／修车铺，或出勤途中。）', 'text' => [], 'items' => []];
    return ['note' => $note, 'text' => [], 'items' => $rows];
}, ['title' => '车辆', 'order' => 18]);

/** 图鉴：按大／小类浏览物品（长清单 → 本来就该用窗口 ✓）
 *  参数：`codex:<小类id>` 只看某一类；不带参数＝列全部小类（带件数） */
crrg_ui_win_register('codex', function (&$s, &$ctx, &$pc, $uid, $arg) {
    if (!function_exists('crrg_drill_items') || !function_exists('crrg_item_minor')) return null;
    $items = (array) crrg_drill_items();
    $minor = (array) crrg_item_minor();
    $byCat = [];
    foreach ($items as $id => $it) $byCat[(string) ($it['cat'] ?? '')][] = $id;
    $arg = trim((string) $arg);
    if ($arg !== '' && isset($minor[$arg])) {                       // 看某一类
        $rows = [];
        foreach ((array) ($byCat[$arg] ?? []) as $id) {
            $nm = function_exists('crrg_drill_item_name') ? crrg_drill_item_name($id) : $id;
            $rows[] = ['label' => $nm . '　' . (string) ($items[$id]['d'] ?? ''), 'k' => 'noop', 'id' => ''];
        }
        return ['note' => '分类：' . (string) ($minor[$arg]['n'] ?? $arg) . '（' . count($rows) . ' 件）', 'text' => [], 'items' => $rows ?: [['label' => '（这一类还没有东西。）', 'k' => 'noop', 'id' => '']]];
    }
    $rows = [];
    foreach ($minor as $cid => $m) {
        $n = count((array) ($byCat[$cid] ?? []));
        if ($n === 0) continue;
        $rows[] = ['label' => (string) ($m['n'] ?? $cid) . '（' . $n . '）', 'k' => 'win', 'id' => 'codex:' . $cid];
    }
    return ['note' => '点一类进去看；共 ' . count($items) . ' 件。', 'text' => [], 'items' => $rows];
}, ['title' => '图鉴', 'order' => 19, 'w' => 400]);

/* 储物柜：独立窗口（宿舍/营房/仓库共用一份柜子 ✓ 2026-09-30 加） */
crrg_ui_win_register('stash', function (&$s, &$ctx, &$pc, $uid, $arg) {
    if (!function_exists('crrg_stash_block')) return ['text' => ['（储物系统未加载。）'], 'items' => []];
    $b = crrg_stash_block($s, $ctx, $pc, $uid);
    if (!is_array($b)) return ['note' => '（这里没有柜子：宿舍、营房、物资仓库有。）', 'text' => [], 'items' => []];
    return ['note' => (string) ($b['note'] ?? ''), 'text' => [], 'items' => (array) ($b['items'] ?? [])];
}, ['title' => '储物柜', 'order' => 21, 'w' => 380]);

/* 区域图：窗口版（与面板那块内容一致 ✓ 2026-09-30 建，随后摘除面板块） */
crrg_ui_win_register('map', function (&$s, &$ctx, &$pc, $uid, $arg) {
    if (!function_exists('crrg_worldmap_svg')) return ['text' => ['（地图系统未加载。）'], 'items' => []];
    $on = !empty($s['ui']['worldmap']);
    return [
        'note'  => $on ? '已展开在右侧。点「收起」或再输一次「大地图」。' : '画出来的地图：等距圆环 + 路网 + 分类标记。',
        'text'  => [],
        'items' => [
            ['label' => $on ? '收起区域图' : '展开区域图', 'k' => 'worldmap', 'id' => ''],
            ['label' => '（文字版：输「大地图 文字」）', 'k' => 'noop', 'id' => '', 'off' => true],
        ],
    ];
}, ['title' => '区域图', 'order' => 22, 'w' => 360]);

/** 商店块：面板与窗口**共用**（2026-09-30 自 drill-economy 逐字抽出；含小类/价格/买得起标记 ✓）
 *  返回面板块数组 ['title','note','locked','items'] */
function crrg_shop_block($sid, array $shop, &$s, &$ctx, &$pc, $uid = 0, $filter = '') {
    /* 2026-10-14（委员长："东西越来越多，加个分类栏"）：
       · **分类栏**：按物品**大类**（taxonomy 的 major ✓ 与物品表同源）——「全部 / 食品 / 饮品 / 医疗 / 工具 …」
       · **分页**：一页 15 件（货架 40 件一屏放不下 ✗）
       · `$filter` 形如 `'食品:2'`（大类:页码）；空＝全部第一页 ✓ **老调用不传＝行为不变** ✓
       纪律：分类与分页**只影响显示**，不许改价格/货品集合 ✗（门禁 verify-shop 会验"翻页并起来＝全部件" ✓） */
    $act = function ($label, $k, $id = '', $extra = []) { return ['label' => $label, 'k' => $k, 'id' => $id] + $extra; };
    [$fcat, $fpage] = array_pad(explode(':', (string) $filter, 2), 2, '');
    $fcat = trim((string) $fcat); $fpage = max(1, (int) $fpage);

    $goods = (array) crrg_drill_shop_goods($shop);
    $ok = crrg_drill_shop_unlocked($shop, $pc);

    /* ① 把货品摊平（保留原来的分组名，用于显示） */
    $flat = [];
    foreach ($goods as $cat => $list) {
        foreach ((array) $list as $gid => $g) {
            $iid = (string) ($g['item'] ?? $gid);
            $maj = '';
            if (function_exists('crrg_item_classify')) {
                $cid = crrg_item_classify($iid);
                $maj = (string) (crrg_item_taxonomy()[$cid]['major'] ?? '');
            }
            $flat[] = ['gid' => $gid, 'g' => (array) $g, 'iid' => $iid, 'cat' => (string) $cat, 'maj' => $maj];
        }
    }
    $count = count($flat);

    /* ② 分类栏（按大类计数）✓ */
    $majs = [];
    foreach ($flat as $r) $majs[$r['maj'] ?: 'misc'] = (int) ($majs[$r['maj'] ?: 'misc'] ?? 0) + 1;
    $names = [];
    if (function_exists('crrg_game_tax_major_data')) foreach ((array) crrg_game_tax_major_data() as $mk => $md) $names[$mk] = (string) ($md['n'] ?? $mk);
    $items = [];
    $bar = [];
    $bar[] = $act(sprintf('全部 %d', $count), 'shop_cat', $sid . '::');
    arsort($majs);
    foreach ($majs as $mk => $n2) {
        $nm = $names[$mk] ?? (string) $mk;
        $bar[] = $act(sprintf('%s %s %d', $fcat === $nm ? '▣' : '▢', $nm, $n2), 'shop_cat', $sid . ':' . $nm . ':1');
    }
    $items[] = $act('── 分类 ──', 'divider', '');
    foreach ($bar as $b2) $items[] = $b2;

    /* ③ 过滤 ＋ 分页（只影响显示 ✓） */
    $sel = $flat;
    if ($fcat !== '' && $fcat !== '全部') {
        $sel = array_values(array_filter($flat, fn($r) => (($names[$r['maj']] ?? $r['maj']) === $fcat)));
    }
    $per = 15;
    $pages = max(1, (int) ceil(count($sel) / $per));
    if ($fpage > $pages) $fpage = $pages;
    $slice = array_slice($sel, ($fpage - 1) * $per, $per);

    $items[] = $act(sprintf('── %s（%d 件，第 %d/%d 页）──', $fcat !== '' && $fcat !== '全部' ? $fcat : '全部', count($sel), $fpage, $pages), 'divider', '');
    $lastCat = null;
    foreach ($slice as $r) {
        $g = $r['g']; $gid = $r['gid']; $iid = $r['iid'];
        if ($lastCat !== $r['cat']) { $items[] = $act('· ' . $r['cat'], 'divider', ''); $lastCat = $r['cat']; }
        $sub = '';
        if (function_exists('crrg_item_classify')) {
            $cid = crrg_item_classify($iid);
            $sub = crrg_item_taxonomy()[$cid]['n'] ?? '';
        }
        $items[] = $act(sprintf('%s%s　%s %s', $g['n'], $sub ? '（' . $sub . '）' : '',
                                $g['p'], ($g['cur'] ?? 'money') === 'credits' ? '积分' : '元'),
                        'buy', $gid, ['note' => ($g['note'] ?? $g['d'] ?? ''), 'cat' => $r['cat'],
                                      'afford' => ((int) ($pc[($g['cur'] ?? 'money') === 'credits' ? 'credits' : 'money'] ?? 0) >= $g['p'])]);
    }
    /* ④ 翻页（按钮 ✓） */
    if ($pages > 1) {
        $items[] = $act('── 翻页 ──', 'divider', '');
        if ($fpage > 1) $items[] = $act(sprintf('← 上一页（%d/%d）', $fpage - 1, $pages), 'shop_cat', $sid . ':' . $fcat . ':' . ($fpage - 1));
        if ($fpage < $pages) $items[] = $act(sprintf('下一页 →（%d/%d）', $fpage + 1, $pages), 'shop_cat', $sid . ':' . $fcat . ':' . ($fpage + 1));
    }
    return ['title' => '商店 · ' . $shop['n'] . ($count ? '（' . $count . ' 件）' : ''),
            'note' => $shop['keep'] . ($ok ? '' : '（未解锁：' . ($shop['need'] ?? '') . '）'),
            'locked' => !$ok, 'items' => $items];
}

/* 回收／拆除：窗口版（与面板两块内容一致 ✓ 2026-09-30 建，随后摘面板块） */
crrg_ui_win_register('sell', function (&$s, &$ctx, &$pc, $uid, $arg) {
    if (!function_exists('crrg_trade_offers')) return ['text' => ['（回收系统未加载。）'], 'items' => []];
    $room = (string) ($s['room'] ?? '');
    $map  = ['tw_junk' => ['junk', '废品收购站（回收 40%）', '老板按斤论堆，只给原价四成。关键物不收。'],
             'b3_warehouse' => ['logistics', '后勤回收窗口（回收 60%）', '仓管登记后折价回收，只收商超系统内的东西。']];
    if (!isset($map[$room])) return ['note' => '（这里不收东西：镇上废品收购站、基地仓库后勤回收窗口。）', 'text' => [], 'items' => []];
    [$kind, $title, $note] = $map[$room];
    $it = (array) crrg_trade_offers($pc, $kind);
    return ['note' => $note, 'text' => [], 'items' => $it ?: [['label' => '（没什么可卖的）', 'k' => 'noop', 'id' => '', 'off' => true]]];
}, ['title' => '回收', 'order' => 23, 'w' => 360]);

/* 指令一览：与聊天「帮助」**同源**（读 crrg_help_lines ✓ 2026-10-01）
 * 窗口里把"窗口名"做成**可点按钮** → 于是这扇窗同时是启动器 ✓ */
crrg_ui_win_register('help', function (&$s, &$ctx, &$pc, $uid, $arg) {
    if (!function_exists('crrg_help_lines')) return ['text' => ['（帮助未加载）'], 'items' => []];
    $lines = (array) crrg_help_lines();
    $launch = ['互动'=>'here','背包'=>'bag','身体'=>'vitals','合约'=>'missions','值班板'=>'board','商店'=>'shop','图鉴'=>'codex', '手机'=>'phone',
               '宿舍'=>'dorm','储物柜'=>'stash','队伍'=>'team','车辆'=>'vehicle','回收'=>'sell','大地图'=>'map'];
    $text = []; $items = []; $seen = [];
    foreach ($lines as $l) {
        $t = trim((string) $l);
        if ($t === '') { $text[] = ''; continue; }
        /* 清单行的形如「  背包　　身上东西，能「用」「放下」」→ 取**首个词**判断是不是窗口名 ✓
           （2026-10-01 修：原来按整行匹配 ✗ → 一个按钮都出不来） */
        $parts = preg_split('/[\s\x{3000}]+/u', $t);
        $first = (string) ($parts[0] ?? '');
        if ($first !== '' && isset($launch[$first]) && !isset($seen[$first])) {
            $seen[$first] = 1;
            $rest = trim(mb_substr($t, mb_strlen($first)));
            $rest = trim(preg_replace('/^[\s\x{3000}]+/u', '', $rest));
            $items[] = ['label' => $first . '　—— ' . ($rest !== '' ? $rest : '点这里打开') . '（点开）', 'k' => 'win', 'id' => $launch[$first]];
            continue;
        }
        $text[] = (string) $l;
    }
    return ['note' => '指令一览。列表里的按钮可以直接点开对应窗口；其余是文字说明。', 'text' => $text, 'items' => $items];
}, ['title' => '指令一览', 'order' => 5, 'w' => 460]);

/* 「互动」窗口：**当下这里能做什么**（2026-10-01 采纳玩家建议）
 *  内容**自动汇总**，不手工维护 ✓：
 *   ① 本房间面板里剩下的可点条目（原样搬过来，带各自的 k/id ✓）
 *   ② 在场的人 → 「问 X」（k=cmd ✓）
 *   ③ 此刻**确实有内容**的窗口（逐个试构建，有内容才列 ✓ 不用人工维护 ✓）
 *   ④ 出口 → 「去 X」（k=cmd ✓）
 */
crrg_ui_win_register('here', function (&$s, &$ctx, &$pc, $uid, $arg) {
    $room = (string) ($s['room'] ?? '');
    $items = []; $text = [];
    /* ① 面板里剩下的条目（这些本来就是"本房间可交互的东西" ✓） */
    if (function_exists('crrg_drill_panels')) {
        foreach ((array) crrg_drill_panels($s, $ctx, $pc, $uid) as $g) {
            $n = count((array) ($g['items'] ?? []));
            if ($n === 0) continue;
            $items[] = ['label' => (string) ($g['title'] ?? ''), 'k' => 'divider', 'id' => ''];
            foreach ((array) $g['items'] as $it) {
                $k = (string) ($it['k'] ?? '');
                if ($k === 'divider') continue;
                $items[] = ['label' => (string) ($it['label'] ?? ''), 'k' => $k, 'id' => (string) ($it['id'] ?? '')];
            }
        }
    }
    /* ② 在场的人 */
    if (function_exists('crrg_npc_here')) {
        $here = (array) crrg_npc_here($room);
        if ($here) {
            $items[] = ['label' => '这里的人', 'k' => 'divider', 'id' => ''];
            foreach ($here as $id => $np) {
                $nm = (string) ($np['n'] ?? $id);
                $items[] = ['label' => '问 ' . $nm . '（看看他想聊什么）', 'k' => 'cmd', 'id' => '问 ' . $nm];
            }
        }
    }
    /* ③ 此刻有内容的窗口（自动挑 ✓） */
    $cand = ['bag' => '背包', 'vitals' => '身体状态', 'missions' => '合约', 'shop' => '商店', 'sell' => '回收',
             'vehicle' => '车辆与出行', 'dorm' => '宿舍', 'stash' => '储物柜', 'team' => '队伍', 'board' => '值班板'];
    $win = [];
    foreach ($cand as $id => $label) {
        if (!function_exists('crrg_ui_win_build')) continue;
        $b = crrg_ui_win_build($id, $s, $ctx, $pc, $uid);
        if (!is_array($b)) continue;
        $has = false;
        foreach ((array) ($b['items'] ?? []) as $it) if (($it['k'] ?? '') !== 'divider') { $has = true; break; }
        if ($has) $win[] = ['label' => '打开「' . $label . '」窗口', 'k' => 'win', 'id' => $id];
    }
    if ($win) { $items[] = ['label' => '可以打开的窗口', 'k' => 'divider', 'id' => '']; $items = array_merge($items, $win); }
    /* ④ 出口 */
    $rooms = function_exists('crrg_drill_rooms') ? (array) crrg_drill_rooms() : [];
    $ex = (array) ($rooms[$room]['x'] ?? []);
    if ($ex) {
        $items[] = ['label' => '可以去的地方', 'k' => 'divider', 'id' => ''];
        foreach ($ex as $lb => $dst) $items[] = ['label' => '去 ' . $lb, 'k' => 'cmd', 'id' => '去 ' . $lb];
    }
    $text[] = '（这里＝「当下能做什么」。下面每一条都可以点。）';
    if (!$items) $text[] = '（这一处眼下没有什么可做的——换「查看」或「去」别处看看。）';
    return ['note' => '当前位置：' . ($rooms[$room]['t'] ?? $room), 'text' => $text, 'items' => $items];
}, ['title' => '互动', 'order' => 4, 'w' => 420]);

/* 对话窗口（**辐射 2 式**，2026-10-01）：上面是对方说的话，下面是"我要说的话"（编号 ＋ [标签] ✓）
 *   · 只读预览（crrg_dlg2_peek ✓ 不触发效果 ✗）→ 点选项＝把「问 X n」当指令发出去 ✓
 *   · 每次只显示**当前这句**（不是流水账 ✓）——正是辐射 2 的手感 ✓ */
crrg_ui_win_register('dlg', function (&$s, &$ctx, &$pc, $uid, $arg) {
    $npc = trim((string) $arg);
    if ($npc === '' || !function_exists('crrg_dlg2_peek')) return null;
    $p = crrg_dlg2_peek($s, $pc, $npc);
    if (!is_array($p)) return null;
    /* 2026-10-22：**后端直接给立绘 id**（前端不用再猜名字 ✓）
       规则：npc_xxx → xxx；中文名 → 查名册反查 id ✓ */
    $faceId = '';
    $rawNpc = trim((string) $npc);
    if (strpos($rawNpc, 'npc_') === 0) $faceId = substr($rawNpc, 4);
    elseif ($rawNpc !== '') {
        if (function_exists('crrg_actor_roster')) {
            foreach ((array) crrg_actor_roster() as $fid => $fd) {
                if ((string) ($fd['n'] ?? '') === $rawNpc) { $faceId = (string) $fid; break; }
            }
        }
    }
    if ($faceId !== '' && !file_exists(get_stylesheet_directory() . '/assets/faces/' . $faceId . '.png')) $faceId = '';
    $text = [];
    $text[] = '『' . (string) $p['name'] . '』';
    foreach ((array) ($p['text'] ?? []) as $l) $text[] = $l;
    $items = [];
    $n = 0;
    foreach ((array) ($p['choices'] ?? []) as $ch) {
        $n++;
        $tag = (string) ($ch['tag'] ?? '');
        $items[] = ['label' => $n . '. ' . ($tag !== '' ? '[' . $tag . '] ' : '') . (string) $ch['label'],
                    'k' => 'cmd', 'id' => (string) $ch['cmd']];
    }
    return ['note' => '选一句你要说的话（点条目即可）', 'face' => $faceId, 'text' => $text, 'items' => $items];
}, ['title' => '对话', 'order' => 6, 'w' => 430]);

/* 现场处置窗口（2026-10-01，委员长要求：**别再让玩家敲指令** ✗ —— 直接弹窗列全部法子 ✓）
 *   · 每条法子＝一个按钮（点了就办 ✓，内部仍走「换法 <节点> <序号>」✓ 玩家不用打）
 *   · **正办**置顶并标注 ✓；每条都写明代价（技能／钱／生命／隐患 ✓） */
crrg_ui_win_register('paths', function (&$s, &$ctx, &$pc, $uid, $arg) {
    $node = trim((string) $arg);
    if ($node === '' || !function_exists('crrg_seal_paths_full')) return null;
    $paths = (array) (crrg_seal_paths_full()[$node] ?? []);
    if (!$paths) return null;
    $def  = function_exists('crrg_seal_node_default') ? crrg_seal_node_default($node) : '';
    $sp   = function_exists('crrg_seal_spot_text') ? (string) crrg_seal_spot_text($node) : '';
    $text = [];
    if ($def !== '') {                                   // 把"正办"排到最前 ✓
        $first = null; $rest = [];
        foreach ($paths as $i => $p) { if (!$first && (string) $p['n'] === $def) { $first = [$i, $p]; continue; } $rest[] = [$i, $p]; }
        if ($first) $paths = array_merge([$first], $rest);
    }
    $items = []; $i = 0;
    foreach ($paths as $pair) {
        $idx = is_array($pair) ? $pair[0] : $i; $p = is_array($pair) ? $pair[1] : $pair; $i++;
        $cost = [];
        if (!empty($p['skill'])) $cost[] = (string) $p['skill'] . (!empty($p['pen']) ? '（+1 惩罚骰）' : '');
        if (!empty($p['npc']))   $cost[] = '找 ' . $p['npc'] . '（好感 ≥' . (int) ($p['rep'] ?? 0) . '）';
        if (!empty($p['cost']))  $cost[] = (int) $p['cost'] . ' 元';
        if (!empty($p['hp']))    $cost[] = '生命 −' . (int) $p['hp'];
        if (($p['type'] ?? '') === 'brute') $cost[] = '留隐患';
        if (($p['type'] ?? '') === 'fight') $cost[] = '打一场';
        $tag = ((string) $p['n'] === $def) ? '【正办】' : '';
        $items[] = ['label' => $tag . (string) $p['n'] . '　——　' . ($cost ? implode(' ｜ ', $cost) : '不需代价'),
                    'k' => 'cmd', 'id' => '换法 ' . $node . ' ' . ($idx + 1)];
    }
    return ['note' => '〔' . $node . '〕' . ($sp !== '' ? $sp : '') . '　——　选一条办（点即可）',
            'text' => $text, 'items' => $items];
}, ['title' => '现场处置', 'order' => 7, 'w' => 460]);

/* 人情往来（2026-10-01 从「身体／状态」里拆出来 ✓ 委员长：单独一个指令与窗口 ✓）
 *   · 数据仍来自 crrg_npc_fav_lines() ✓（一份真相 ✓）
 *   · 列表按好感从高到低 ✓；并提示"谁能帮上什么忙"由剧情文本给 ✓ */
crrg_ui_win_register('favor', function (&$s, &$ctx, &$pc, $uid, $arg) {
    if (!function_exists('crrg_npc_fav_lines')) return null;
    $lines = (array) crrg_npc_fav_lines($pc);
    if (!$lines) return ['note' => '（还没跟谁打过交道。）', 'text' => [], 'items' => []];
    $fav = (array) ($pc['npc_fav'] ?? []);
    arsort($fav);
    $items = [];
    foreach ($fav as $who => $v) {
        $v = (int) $v;
        $tier = function_exists('crrg_npc_fav_tier') ? crrg_npc_fav_tier($v) : '';
        $items[] = ['label' => (string) $who . '　' . ($v >= 0 ? '+' : '') . $v . ($tier !== '' ? '（' . $tier . '）' : ''),
                    'k' => 'noop', 'id' => ''];
    }
    /* 2026-10-01：把"今天能帮忙的人"做成按钮 ✓（可重复 +1／日 ✓ 玩家报"一次性跑腿做完就刷不了"✓） */
    $room = (string) ($s['room'] ?? '');
    if (function_exists('crrg_npc_present') && function_exists('crrg_npc_help_done_today')) {
        $can = [];
        foreach (array_keys((array) crrg_npc_present($room, $s)) as $nm) {
            if (!crrg_npc_help_done_today($pc, $nm)) $can[] = (string) $nm;
        }
        if ($can) {
            $items[] = ['label' => '今天能帮忙的人（各 +1 好感，一天一次）', 'k' => 'divider', 'id' => ''];
            foreach ($can as $nm) $items[] = ['label' => '帮他搭把手：' . $nm, 'k' => 'cmd', 'id' => '帮忙 ' . $nm];
        }
    }
    return ['note' => '人情（好感）——找人帮忙／交易时按它算；下面可点"帮忙"（一天一次 ✓）。', 'text' => $lines, 'items' => $items];
}, ['title' => '人情往来', 'order' => 13, 'w' => 380]);

/* 可查的账（2026-10-01，委员长指出"查账查串了"✗）：
 *   同一个柜台（信用社）上原来摆着**两条线各一个"查账"** ✗：
 *     · 封条·第三幕 →「调查 流水」（田小满／材料资金）
 *     · 追书人     →「bank|record」（李道生账户／代签）
 *   玩家说一句"查旧账"，游戏替她挑了一个 → 她以为查出的是徒弟的账，其实是另一条线 ✓
 *   现在给一个**上级入口**：这里**分别列清**有几本账、各属哪条线 ✓，点进去各查各的 ✓ */
crrg_ui_win_register('ledgers', function (&$s, &$ctx, &$pc, $uid, $arg) {
    $room = (string) ($s['room'] ?? '');
    if ($room !== 'tw_bank') {
        /* 不在信用社 → 不返回空（门禁要求"每个窗口都能构建" ✓），给一句说明 ✓ */
        return ['note' => '（账在镇上的信用社柜台——到了那儿再说「查账」。）', 'text' => [], 'items' => []];
    }
    $inHand = [(string) ($pc['mission'] ?? ''), (string) ($pc['mission2']['id'] ?? '')];
    $flags  = (array) ($s['flags'] ?? []);
    $items  = [];
    /* ① 信用社流水（封条线） */
    if (in_array('m_mid_seal3', $inHand, true)) {
        $done = in_array('s3_暗账', $flags, true);
        $items[] = ['label' => ($done ? '〔已查〕' : '') . '信用社流水　——　材料资金的往来（封条线·第三幕）',
                    'k' => $done ? 'noop' : 'cmd', 'id' => $done ? '' : '调查 流水'];
    }
    /* ② 个人账户往来（追书人线） */
    $got = in_array('线索_旧账', $flags, true);
    $items[] = ['label' => ($got ? '〔已查〕' : '') . '个人账户往来　——　需镇府证明（追书人线·李道生）',
                'k' => $got ? 'noop' : 'bank', 'id' => $got ? '' : 'record'];
    /* ③ 柜台业务（同柜台的日常） */
    $items[] = ['label' => '存取款（同柜台的日常业务）', 'k' => 'divider', 'id' => ''];
    $items[] = ['label' => '　存款（100 起）', 'k' => 'bank', 'id' => 'deposit'];
    $items[] = ['label' => '　取款（100 起）', 'k' => 'bank', 'id' => 'withdraw'];
    return ['note' => '信用社柜台　｜　这里有几本账，各属哪条线——分别列清，点进去各查各的。',
            'text' => [], 'items' => $items];
}, ['title' => '可查的账', 'order' => 21, 'w' => 430]);
