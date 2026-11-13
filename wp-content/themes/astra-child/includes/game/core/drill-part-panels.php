<?php
/**
 * CRRG 演习系统 · drill.php 拆分块[panels]（2026-09-29 从 drill.php 原样搬出，仅位置变化）
 * 内容：见下方各函数。搬出后由 drill.php 在顶部 require，保证声明顺序与拆分前一致。
 */
if (!defined('ABSPATH')) exit;

function crrg_drill_panel_groups() {
    return [
        'scene' => ['n' => '现场与任务', 'open' => 1],
        'trade' => ['n' => '交易与服务', 'open' => 1],
        'body'  => ['n' => '身体与装备', 'open' => 0],
        'world' => ['n' => '地图与情报', 'open' => 0],
        'acct'  => ['n' => '权限与账户', 'open' => 0],
    ];
}

function crrg_drill_panel_group($p) {
    $t = (string) ($p['title'] ?? '');
    $g = 'scene';
    $map = [
        'acct'  => ['账户', '轮回', '队伍', '权限', '演习权限'],
        'body'  => ['装备', '身体状态', '状态', '医疗室', '心理干预'],
        'world' => ['区域图', '地方声望', '笔记', '线索'],
        'trade' => ['商店', '储物柜', '个人仓库', '仓库', '回收', '服务区', '加油', '修车', '寄卖', '车辆与行程', '补给'],
        'scene' => ['在手', '值班板', '来找的人', '快速反应', '任务', '现场', '目标', '门禁', '换武器', '扎营'],
    ];
    $hitGroup = null;
    foreach ($map as $k => $keys) {
        foreach ($keys as $k2) if (mb_strpos($t, $k2) !== false) { $hitGroup = $k; break 2; }
    }
    if ($hitGroup) $g = $hitGroup;
    $p['g'] = $g;
    return $p;
}


function crrg_drill_status_line($s, $ctx, $pc = null) {
    $rooms = crrg_drill_rooms();
    $base = sprintf('身份 %s ｜ 卡级 %s ｜ 位置 %s',
        $ctx['rank_name'], $ctx['card'], $rooms[$s['room']]['t'] ?? '（位置未知）');
    if (is_array($pc) && !empty($pc['attrs']) && function_exists('crrg_vitals')) {
        $vv = crrg_vitals($pc);
        $base .= sprintf(' ｜ 精力 %d ｜ 饱食 %d ｜ 口渴 %d', (int) $vv['energy'], (int) $vv['food'], (int) $vv['water']);
    }
    if (is_array($pc) && !empty($pc['attrs'])) {
        $base .= sprintf(' ｜ %s ｜ %s ｜ 生命 %d ｜ 理智 %d ｜ 积分 %d ／ %d元',
            $pc['name'] ?: '未登记', $pc['posture'] ?? '站立', (int) $pc['hp'], (int) $pc['san'],
            (int) ($pc['credits'] ?? 0), (int) ($pc['money'] ?? 0));
    }
    return $base;
}

/* ---------------- 指令执行（纯函数，便于测试） ---------------- */

function crrg_drill_ajax_cmd() {
    if (!is_user_logged_in()) wp_send_json_error(['msg' => '未登录'], 403);
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_drill')) wp_send_json_error(['msg' => '安全校验失败，请刷新页面'], 403);
    $uid = get_current_user_id();
    $ctx = crrg_drill_ctx($uid);
    $s   = crrg_drill_state($uid);
    $pc  = crrg_drill_pc($uid);
    [$s, $lines, $pc] = crrg_drill_exec($s, $ctx, wp_unslash($_POST['cmd'] ?? ''), $pc, $uid);
    /* ── 窗口：① k='win' 直接取；② 动作/对话请求打开（ui_open）；③ 都不涉及则不带 ── */
    $winId = '';
    if ($k === 'win' && $id !== '') { $winId = $id; $lines = []; }
    elseif (function_exists('crrg_ui_take_open')) { $winId = crrg_ui_take_open($s); }
    $win = ($winId !== '' && function_exists('crrg_ui_win_build')) ? crrg_ui_win_build($winId, $s, $ctx, $pc, $uid) : null;
    /* 2026-10-01：待**关闭**的窗口（对话结束 ✓） */
    $closeWin = function_exists('crrg_ui_take_close') ? crrg_ui_take_close($s) : '';

    $view = crrg_drill_view($s, $ctx, $uid, $pc);
    $s['feed_room'] = $s['room'];
    $s['feed_seen'] = crrg_drill_feed_last($s['room']);
    crrg_drill_save($uid, $s);
    crrg_drill_pc_save($uid, $pc);
    wp_send_json_success(['lines' => $lines,
                          'status' => crrg_drill_status_line($s, $ctx, $pc),
                          'win'    => $win,
                          'close'  => $closeWin,
                          'view'   => $view]);
}

add_action('wp_ajax_crrg_drill_state', 'crrg_drill_ajax_state');

function crrg_drill_ajax_state() {
    if (!is_user_logged_in()) wp_send_json_error(['msg' => '未登录'], 403);
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_drill')) wp_send_json_error(['msg' => '安全校验失败'], 403);
    $uid = get_current_user_id();
    $ctx = crrg_drill_ctx($uid);
    $s   = crrg_drill_state($uid);
    $pc  = crrg_drill_pc($uid);
    /* 2026-09-30 修：**刷新面板时也结算一次代谢**（原来只在"动作"里结算 ✗）
       —— 否则睡够了、或离线回来，光看面板还是**旧数**，玩家会以为"睡了半天没涨精力" ✗ */
    $syncMsgs = [];
    if (function_exists('crrg_vitals_sync') && function_exists('crrg_vitals')) {
        $syncMsgs = (array) crrg_vitals_sync($pc);
        if (function_exists('crrg_drill_pc_save')) crrg_drill_pc_save($uid, $pc);
    }
    $view = crrg_drill_view($s, $ctx, $uid, $pc);
    $s['feed_room'] = $s['room'];
    $s['feed_seen'] = crrg_drill_feed_last($s['room']);
    crrg_drill_save($uid, $s);
    wp_send_json_success([
        'lines'  => array_merge((array) $syncMsgs, crrg_drill_room_lines($s, $ctx), ['输入「帮助」看指令。']),
        'status' => crrg_drill_status_line($s, $ctx, $pc),
        'view'   => $view,
    ]);
}

/* ==================================================================
 * 交互视图（2026-09-27 增补）
 * 左栏需要：当前地点可交互的物品 / NPC / 在线玩家
 * 右栏需要：以文字与符号表示当前所处与四周可达地点（方位图）
 * 坐标依《02-基地内部结构.md》：东西 0→200（西→东），南北 0→80（南→北）
 * ================================================================== */

function crrg_drill_ajax_act() {
    if (!is_user_logged_in()) wp_send_json_error(['msg' => '未登录'], 403);
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_drill')) wp_send_json_error(['msg' => '安全校验失败，请刷新页面'], 403);
    $uid = get_current_user_id();
    $ctx = crrg_drill_ctx($uid);
    $s   = crrg_drill_state($uid);
    $pc  = crrg_drill_pc_defaults(crrg_drill_pc($uid));
    if (function_exists('crrg_travel_ensure_region')) crrg_travel_ensure_region();
    crrg_drill_set_scene($pc['scene'] ?? 'base');
    crrg_drill_apply_clearance($ctx, $pc);
    if (empty($pc['ready'])) wp_send_json_error(['msg' => '请先完成车卡'], 403);

    /* 2026-10-01 修（林晓雅报"终幕按钮点了没反应，输指令就行"✓）：
       原来 `/[^a-z_]/` **把数字也洗掉了** ✗ —— 于是所有**带数字的按钮 k** 在 AJAX 路径上全废 ✗：
         `seal5seal` → `sealseal` ✗（没这个 case → 「没接上动作」）
         `seal5run`  → `sealrun`  ✗（所以她"按钮没用、输指令才行"✓）
         `贴封`（中文）→ 洗成**空串** ✗（→ 「未知操作。」）
       k 的命名规范本来就允许数字（`seal5seal` / `st1_接班` / `s4_抢料` …）→ 这里**必须放行数字** ✓ */
    $k  = preg_replace('/[^a-z0-9_]/', '', (string) ($_POST['k'] ?? ''));
    $id = sanitize_text_field(wp_unslash($_POST['id'] ?? ''));
    /* 先推进任务进度，再执行动作——否则「交任务」会用旧进度判定而误报"目标还没走完" */
    if (function_exists('crrg_drill_mission_tick')) {
        $pre = [];
        crrg_drill_mission_tick($s, $pc, $id ?: $k, $pre);
        crrg_drill_save($uid, $s); crrg_drill_pc_save($uid, $pc);
    }
    if ($k === 'bt_start') {
        $lines = crrg_bt_start($s['room'], $uid, 'sim');
        $s['fight_active'] = 1;                      // 开打：这一场记着，打完扣一次精力
    } elseif (strpos($k, 'bt_') === 0 && function_exists('crrg_bt_act')) {
        $lines = crrg_bt_act($k, $id, $uid);
        if (in_array($k, ['bt_end', 'bt_flee'], true)) {   // 脱战后才回到房间描述，战斗中不再复读
            $lines[] = '';
            $lines = array_merge($lines, crrg_drill_room_lines($s, $ctx));
        }
        if (!crrg_bt_get() && in_array($s['room'], ['b1_train', 'b2f_sim'], true)) {   // 演习打完 → 记一笔
            if (!in_array('模拟_完成', (array) ($s['flags'] ?? []), true)) $s['flags'][] = '模拟_完成';
        }
        if (!crrg_bt_get() && !empty($s['fight_active'])) {           // 一场战斗打完 → 一次扣精力
            $s['fight_active'] = 0;
            if (function_exists('crrg_vitals_spend')) {
                foreach (crrg_vitals_spend($pc, 'fight') as $x) $lines[] = $x;
            }
        }
        if (!crrg_bt_get() && function_exists('crrg_fate')) {          // 战斗结果回写（生命/理智/殉职）
            $last = get_option('crrg_bt_last', null);
            if (is_array($last) && time() - (int) ($last['ts'] ?? 0) < 120 && isset($last['pc'][$uid])) {
                delete_option('crrg_bt_last');
                $r = $last['pc'][$uid];
                $pc['hp'] = (int) $r['hp'];
                if (!$last['sim']) {
                    if (in_array('死亡', (array) $r['state'], true)) {
                        foreach (crrg_fate_die($pc, $uid, '战斗（未能稳定）', $last['room']) as $x) $lines[] = $x;
                    } elseif ((int) $pc['hp'] <= 0) {
                        $lines[] = '（你倒在原地，生命 0——需要急救与休养，暂时别再动。）';
                    }
                    if ((int) $pc['san'] <= 0) {
                        foreach (crrg_fate_go_insane($pc, $uid, '战斗中理智归零') as $x) $lines[] = $x;
                    }
                }
                crrg_drill_pc_save($uid, $pc);
            }
        }
        if (!crrg_bt_get() && function_exists('crrg_drill_on_fight_end')) {   // 战斗收场 → 通知模组收尾
            $s['flags'] = (array) ($s['flags'] ?? []);
            $extra = crrg_drill_on_fight_end($s, $uid);
            if ($extra) $lines = array_merge($lines, (array) $extra);
        }
    } else {
        $lines = crrg_drill_do_act($s, $pc, $ctx, $uid, $k, $id);
    }
    if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, $id ?: $k, $lines);

    /* ── 窗口：① k='win' 直接取；② 动作/对话请求打开（ui_open）；③ 都不涉及则不带 ── */
    $winId = '';
    if ($k === 'win' && $id !== '') { $winId = $id; $lines = []; }
    elseif (function_exists('crrg_ui_take_open')) { $winId = crrg_ui_take_open($s); }
    $win = ($winId !== '' && function_exists('crrg_ui_win_build')) ? crrg_ui_win_build($winId, $s, $ctx, $pc, $uid) : null;
    /* 2026-10-01：待**关闭**的窗口（对话结束 ✓） */
    $closeWin = function_exists('crrg_ui_take_close') ? crrg_ui_take_close($s) : '';

    $view = crrg_drill_view($s, $ctx, $uid, $pc);
    $s['feed_room'] = $s['room'];
    $s['feed_seen'] = crrg_drill_feed_last($s['room']);
    crrg_drill_save($uid, $s);
    crrg_drill_pc_save($uid, $pc);
    wp_send_json_success(['lines' => $lines,
                          'status' => crrg_drill_status_line($s, $ctx, $pc),
                          'win'    => $win,
                          'close'  => $closeWin,
                          'view'   => $view]);
}

