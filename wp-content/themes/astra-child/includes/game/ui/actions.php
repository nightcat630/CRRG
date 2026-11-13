<?php
/**
 * 自 includes/drill-economy.php 搬出的函数（2026-09-30）
 */
if (!defined('ABSPATH')) exit;

/* ================= 面板动作分发（AJAX 用） ================= */
function crrg_drill_do_act(&$s, &$pc, $ctx, $uid, $k, $id) {
    /* —— 身体状态：代谢结算 + 睡眠拦截 + 本次动作消耗 —— */
    if (function_exists('crrg_vitals')) {
        crrg_vitals_sync($pc);
        $GLOBALS['crrg_vital_pen'] = crrg_vitals_check_penalty($pc);
        /* 2026-10-14（bus P0）：按钮路径同样给总线上下文（两条路都要能到 ✓） */
        $GLOBALS['crrg_ctx_uid']  = (int) ($pc['uid'] ?? 0);
        $GLOBALS['crrg_ctx_room'] = (string) ($s['room'] ?? '');
        $GLOBALS['crrg_pc_ref']   = &$pc;             // 按钮路径同样给检定上下文 ✓
        $free = ['noop','vitals','repview','worldmap','vitals_sleep','vitals_wake','dorm_rest','dorm_deco','vehicle','heartbeat'];
        if (crrg_vitals_sleeping($pc) && !in_array($k, $free, true)) {
            return ['你正在睡。要起来就说「起床」。'];
        }
        $kind = 'use';
        if (in_array($k, ['travel','depart'], true)) $kind = 'travel';
        elseif ($k === 'chase') $kind = ($id === 'watch') ? 'watch'
                                     : (in_array($id, ['sexton_press','door','break'], true) ? 'dig' : 'use');
        elseif ($k === 'dorm_train') $kind = 'train';
        crrg_vitals_spend($pc, $kind);
    }
    /* 战斗动作统一派发（2026-09-29）：面板/AJAX 侧此前没有 bt_* 分支 →
       「开始模拟对抗」等按钮点了没反应。这里与命令侧同源转 crrg_bt_act()。 */
    if ($k === 'bt_start' && function_exists('crrg_bt_start')) {
        /* 开始对抗：与命令侧一致——扣 2 积分（演习费）后开打（2026-09-29） */
        if (crrg_bt_get()) return ['（已经在对抗里了。）'];
        if ((int) ($pc['credits'] ?? 0) < 2) return [sprintf('（演习费用 2 积分，你只有 %d。）', (int) ($pc['credits'] ?? 0))];
        $pc['credits'] = (int) $pc['credits'] - 2;
        if (function_exists('crrg_drill_pc_save')) crrg_drill_pc_save($uid, $pc);
        return array_merge(['（演习费用 2 积分，已扣，不返还。）'], (array) crrg_bt_start($s['room'] ?? 'b1_train', $uid, 'sim'));
    }
    if (strpos((string) $k, 'bt_') === 0 && function_exists('crrg_bt_act')) {
        return (array) crrg_bt_act((string) $k, (string) $id, $uid);
    }

    /* ── 窗口：动作语义 → 打开对应窗口（2026-09-30）
       按钮侧的 k 名字与指令侧不同，所以在这里做一张映射，覆盖"看状态类"的动作 ✓ ── */
    /* 2026-10-14（手机）：**点图标切页** —— 手机里点某个应用 → 重开窗口到那一页 ✓ */
    /* 手机里的按钮动作（点完**留在手机里** ✓） */
    if (in_array($k, ['phone_like', 'phone_log', 'phone_report', 'ph_game', 'ph_memo'], true)) {
        $lines = [];
        if ($k === 'phone_like' && function_exists('crrg_ph_all')) {
            $d = crrg_ph_all(); $i = max(1, (int) $id);
            if (!empty($d['feed'][$i - 1])) { $d['feed'][$i - 1]['likes'] = (int) ($d['feed'][$i - 1]['likes'] ?? 0) + 1; crrg_ph_save($d); $lines[] = '（点了个赞。）'; }
            else $lines[] = '（没有这条。）';
            if (function_exists('crrg_ui_request_open')) crrg_ui_request_open($s, 'phone:moments');
        } elseif ($k === 'phone_log' && function_exists('crrg_phone_log_lines')) {
            $lines = (array) crrg_phone_log_lines($uid);
            if (function_exists('crrg_ui_request_open')) crrg_ui_request_open($s, 'phone:call');
        } elseif ($k === 'phone_report' && function_exists('crrg_phone_report')) {
            $lines = (array) crrg_phone_report($s, $pc, $ctx, '');
            if (function_exists('crrg_ui_request_open')) crrg_ui_request_open($s, 'phone:report');
        } elseif ($k === 'ph_game' && function_exists('crrg_ph_game')) {
            $lines = (array) crrg_ph_game($pc);
            if (function_exists('crrg_ui_request_open')) crrg_ui_request_open($s, 'phone:game');
        } elseif ($k === 'ph_memo' && function_exists('crrg_ph_memo_add')) {
            $lines = (array) crrg_ph_memo_add($pc, (string) $id);
            if (function_exists('crrg_ui_request_open')) crrg_ui_request_open($s, 'phone:memo');
        }
        return $lines;
    }
    /* 2026-10-14：**商店分类栏／翻页**（点一下就把窗口重开到那一类那一页 ✓） */
    if ($k === 'shop_cat' && function_exists('crrg_ui_request_open')) {
        crrg_ui_request_open($s, 'shop:' . (string) $id);
        return [];
    }
    if ($k === 'phone_page' && function_exists('crrg_ui_request_open')) {
        $page = (string) $id;
        crrg_ui_request_open($s, 'phone:' . $page);
        return ['（' . ($page === '' ? '主屏' : $page) . '）'];
    }
    if (function_exists('crrg_ui_request_open')) {
        $uiMap = ['bag' => 'bag', 'vitals' => 'vitals', 'status' => 'vitals',
                  'missions' => 'missions', 'mission' => 'missions', 'log' => 'missions',
                  'equip' => 'equip', 'wear' => 'equip',            // 装备
                  'board' => 'board', 'duty' => 'board',            // 值班板
                  'career' => 'career', 'promote' => 'career', 'account' => 'career',   // 账户/晋升
                  'dorm' => 'dorm', 'furniture' => 'dorm',                            // 宿舍/家具
                  'team' => 'team', 'party' => 'team',                                // 队伍
                  'vehicle' => 'vehicle', 'car' => 'vehicle',                         // 车辆
                  'codex' => 'codex', 'items2' => 'codex'];                           // 图鉴
        if (isset($uiMap[$k])) crrg_ui_request_open($s, $uiMap[$k]);
    }

    switch ($k) {
        case 'buy':
            return crrg_drill_buy($pc, $id, 1, $s['room']);

        case 'dlg':    // 面板点选话题：id = "<npc_id>|<topic_id>"
            if (!function_exists('crrg_dlg_ask')) return ['对话系统未加载。'];
            [$nid, $tid] = array_pad(explode('|', $id, 2), 2, '');
            [$out, $hit] = crrg_dlg_ask($s, $pc, $uid, $nid, $tid);
            return $out;

        case 'wear':
            return function_exists('crrg_equip_wear') ? crrg_equip_wear($pc, $id) : ['装备系统未加载。'];
        case 'unwear':
            return function_exists('crrg_equip_take_off') ? crrg_equip_take_off($pc, $id) : ['装备系统未加载。'];

        case 'vitals_sleep':
            if (function_exists('crrg_vitals_sleep')) { $h = (int) ($id ?: 8); return crrg_vitals_sleep($pc, $h); }
            return ['身体系统未加载。'];
        case 'vitals_wake':
            return function_exists('crrg_vitals_wake') ? crrg_vitals_wake($pc) : ['没事。'];
        case 'travel_refuel':
            return crrg_travel_refuel($pc, $s['room'], 0);
        case 'travel_service':
            return crrg_travel_service($pc, $s['room'], false);
        case 'travel_service_major':
            return crrg_travel_service($pc, $s['room'], true);
        case 'travel_go':
            /* 2026-10-01 修（读车旅系统时发现 ✓）：**窗口的「出发」按钮原来不过"路段开放"闸** ✗
               —— 命令路径（「去 X」）会查 `crrg_travel_node_open()` ✓，这里却直接发车 ✓
               → 同一件事两条路径、两个结果 ✗（今天第三次遇到"两套真相" ✓）。
               现在这里也过同一道闸 ✓（口径与命令路径一致 ✓） */
            if (function_exists('crrg_travel_node_open') && !crrg_travel_node_open((string) $id)) {
                return ['（这条路还没开放——地图上标着「已通」「部分通」的地方才走得了。）'];
            }
            return crrg_travel_start($pc, $id);
        case 'vitals_heal':
            return function_exists('crrg_vitals_treat') ? crrg_vitals_heal_wound($pc, $id) : ['未加载。'];
        case 'vitals_eat':
            return function_exists('crrg_vitals_do_eat') ? crrg_vitals_do_eat($s, $pc, $id) : ['未加载。'];
        case 'vitals_drink':
            return function_exists('crrg_vitals_do_drink') ? crrg_vitals_do_drink($s, $pc, $id) : ['未加载。'];

        case 'service_area_use':
            return function_exists('crrg_travel_service_area') ? crrg_travel_service_area($pc, $s['room'], $id) : ['未加载。'];

        case 'travel_camp':
            return crrg_travel_camp($pc, crrg_travel_current_node($s), 6);

        case 'fate_radical_treat':
            return function_exists('crrg_fate_treat') ? crrg_fate_treat($pc, $uid) : ['未加载。'];
        case 'giveup':
            return function_exists('crrg_fate_give_up') ? crrg_fate_give_up($pc, $uid) : ['未加载。'];
        case 'samsara':
            if (!function_exists('crrg_fate_reincarnate')) return ['未加载。'];
            $tid = (int) $id;
            if (!$tid) return ['（没指定要复活的卡。）'];
            return crrg_fate_reincarnate($pc, $uid, $tid);

        case 'heartbeat':   // 页面开着时的心跳：代谢由入口结算，这里不回话
            return [];

        case 'vitals':
            return array_merge(['〔身体状态〕'], function_exists('crrg_vitals_lines') ? crrg_vitals_lines($pc) : []);

        case 'worldmap':   // 面板按钮：切换区域图栏位
            $on = !empty($s['ui']['worldmap']);
            $s['ui']['worldmap'] = $on ? 0 : 1;
            return $on ? ['（区域图已收起。）'] : ['（区域图已展开在「区域图」栏位。）'];

        case 'repview':
            return function_exists('crrg_rep_lines') ? crrg_rep_lines($pc, crrg_rep_places()[$id]['n'] ?? $id) : ['声望未加载。'];

        case 'pm_pick':  // 从值班板取下跑腿单（不限进度，只看旗标）
            if (($pc['mission'] ?? '') === '') return ['你手上没有单子。'];
            $mm = crrg_drill_mission($pc['mission']);
            if (!$mm || stripos($mm['name'], '跑腿') === false) return ['值班板上没有跟你手上这单对得上的东西。'];
            if (($s['room'] ?? '') !== 'b2b_hall') return ['值班板在指挥大厅。'];
            $flag = 'pm_picked_' . $mm['name'];
            if (!in_array($flag, (array) ($s['flags'] ?? []), true)) $s['flags'][] = $flag;
            $out = ['你把「' . $mm['name'] . '」的单子从值班板上撕下来，对折塞进兜里。',
                    '（' . ($mm['steps'][1]['text'] ?? '') . '；完事回值班板签字。）'];
            crrg_drill_mission_tick($s, $pc, 'pm_pick', $out);
            return $out;

        case 'deliver':  // 情报处换台灯（面板按钮 k=deliver）
            if (!function_exists('crrg_deliver_lamps')) return ['交付系统未加载。'];
            return crrg_deliver_lamps($s, $pc);

        case 'sell':     // id = "<地点>|<物品>"
            [$w, $iid] = array_pad(explode('|', $id, 2), 2, '');
            if (!function_exists('crrg_trade_sell')) return ['回收系统未加载。'];
            return crrg_trade_sell($pc, $iid, $w, $s);

        case 'unsell':   // id = "deco|<格>" 或 "furn|<家具名>"
            [$kind, $key] = array_pad(explode('|', $id, 2), 2, '');
            if (!function_exists('crrg_trade_unsell_deco')) return ['回收系统未加载。'];
            return $kind === 'furn' ? crrg_trade_unsell_furn($pc, $key) : crrg_trade_unsell_deco($pc, $key);

        case 'world_refresh':   // 仅同步世界时间与天气，不改任何状态
            return [];

        case 'town':     // 建筑功能：id = "<建筑>|<动作>"
            [$b, $a] = array_pad(explode('|', $id, 2), 2, '');
            /* 2026-09-29：面板送的短名与各 handler 期待的物品 id 不一致（prybar vs tool_prybar…），
               于是这些按钮全都落进 default，只回一句房间描写。这里统一转一次。 */
            $alias = [
                'hw'     => ['prybar' => 'tool_prybar', 'rope' => 'tool_rope_nylon', 'ladder' => 'tool_ladder'],
                'photo'  => ['film' => 'doc_film'],
                'vet'    => ['sedative' => 'med_sedative'],
                'tailor' => ['vest' => 'gear_vest'],
            ];
            if (isset($alias[$b][$a])) $a = $alias[$b][$a];
            switch ($b) {
                case 'bank':     return crrg_bank_act($s, $pc, $a);
                case 'post':     return crrg_post_act($s, $pc, $ctx, $a);
                case 'clinic':   return crrg_clinic_act($s, $pc, $a);
                case 'book':     return crrg_book_act($s, $pc, $a);
                case 'photo':    return crrg_photo_act($s, $pc, $a);
                case 'hw':       return crrg_hw_act($s, $pc, $a);
                case 'mill':     return crrg_mill_act($s, $pc, $a);
                case 'club':     return crrg_club_act($s, $pc, $a);
                case 'watch':    return crrg_watch_act($s, $pc, $a);
                case 'temple':   return crrg_temple_act($s, $pc, $ctx, $a);
                case 'townhall': return crrg_townhall_act($s, $pc, $a);
                case 'press':    return crrg_press_act($s, $pc, $a);
                case 'tailor':   return crrg_tailor_act($s, $pc, $a);
                case 'vet':      return crrg_vet_act($s, $pc, $a);
                case 'outfit':   return crrg_outfit_act($s, $pc, $a);
            }
            return ['这里没有这个功能。'];

        case 'noodle':
            if (($s['room'] ?? '') !== 'tw_noodle') return ['（点面得在面馆。）'];
            $menu = ['plain' => ['素面', 6, ['food' => 26, 'water' => 6, 'why' => '素面']],
                     'beef'  => ['牛肉面', 12, ['food' => 42, 'energy' => 4, 'water' => 8, 'why' => '牛肉面']],
                     'soup'  => ['面汤', 0, ['water' => 12, 'food' => 4, 'why' => '面汤']]];
            $pick = $menu[$id] ?? null;
            if (!$pick) return ['（菜单上没有这个。）'];
            [$nm, $cost, $fx] = $pick;
            if ($cost > 0 && (int) $pc['money'] < $cost) return [sprintf('老板娘把手一摊：「%s %d 元，先给钱。」', $nm, $cost)];
            if ($cost > 0) $pc['money'] -= $cost;
            if (!isset($pc['vitals'])) $pc['vitals'] = crrg_vitals_default();
            $pc['vitals']['food']  = min(100, (float) $pc['vitals']['food'] + (float) ($fx['food'] ?? 0));
            $pc['vitals']['water'] = min(100, (float) $pc['vitals']['water'] + (float) ($fx['water'] ?? 0));
            if (!empty($fx['energy'])) $pc['vitals']['energy'] = min(100, (float) $pc['vitals']['energy'] + (float) $fx['energy']);
            $out = [sprintf('你要了%s。%s', $nm, $cost > 0 ? sprintf('（钱 −%d）', $cost) : '（免费）')];
            $out[] = sprintf('（%s：饱食 %d／口渴 %d／精力 %d。）', $fx['why'], (int) $pc['vitals']['food'], (int) $pc['vitals']['water'], (int) $pc['vitals']['energy']);
            if ($nm === '牛肉面') $out[] = '后厨的蒸汽里有人喊了一声"好了"。面是热的，汤也厚。';
            if ($nm === '素面')   $out[] = '老板娘端上来一碗素面，汤清，撒了点葱花。';
            return $out;

        case 'inn_stay':
            if (!function_exists('crrg_town_inn')) return ['住宿未加载。'];
            return crrg_town_inn($s, $pc);
        case 'town_bath':
            if (!function_exists('crrg_town_bath')) return ['澡堂未加载。'];
            return crrg_town_bath($s, $pc);
        case 'town_barber':
            if (!function_exists('crrg_town_barber')) return ['理发店未加载。'];
            return crrg_town_barber($s, $pc);
        case 'town_repair':
            if (!function_exists('crrg_town_repair')) return ['修车铺未加载。'];
            return crrg_town_repair($s, $pc);

        case 'chase':    // 模组动作（追书人）
            if (function_exists('crrg_chase_act')) return crrg_chase_act($s, $pc, $ctx, $uid, $id);
            return ['模组未加载。'];

        case 'store':   // 面板：存入
            if (!in_array($s['room'], ['b1_room', 'b1_dorm', 'b3_warehouse'], true)) return ['存东西去宿舍或物资仓库。'];
            if (crrg_drill_bag_count($pc, $id) <= 0) return ['背包里没有这样东西。'];
            crrg_drill_bag_del_n($pc, $id, 1);
            crrg_drill_stash_add($pc, $id, 1);
            if ($id === 'gear_hazmat') $s['items'] = array_values(array_diff((array) $s['items'], ['T3防护服']));
            return ['已存入' . crrg_drill_item_name($id) . '（柜中 ' . (int) ($pc['stash'][$id] ?? 0) . ' 件）。'];

        case 'fetch':   // 面板：取出
            if (!in_array($s['room'], ['b1_room', 'b1_dorm', 'b3_warehouse'], true)) return ['取东西去宿舍或物资仓库。'];
            if (!crrg_drill_stash_take($pc, $id, 1)) return ['柜子里没有这样东西。'];
            crrg_drill_bag_add_n($pc, $id, 1);
            if ($id === 'gear_hazmat' && !in_array('T3防护服', (array) $s['items'], true)) $s['items'][] = 'T3防护服';
            return ['已取出' . crrg_drill_item_name($id) . '（柜中 ' . (int) ($pc['stash'][$id] ?? 0) . ' 件）。'];

        case 'accept':
            if (function_exists('crrg_rep_mission_gate')) {
                $gm = crrg_drill_mission($id);
                if ($gm) { $g = crrg_rep_mission_gate($pc, $gm); if ($g !== '') return [$g]; }
            }
            $m = crrg_drill_mission($id);
            if (!$m) return ['没有这个合约。'];
            $mi = crrg_drill_mission($id);
            $doneBefore = in_array($id, (array) ($pc['mission_done'] ?? []), true);
            if ($doneBefore) {
                $rep = crrg_drill_mission_repeat_state($pc, $id);
                if ($rep['can']) {
                    /* 可重复：清掉"已完成"标记，重置进度，重新走一遍 */
                    $pc['mission_done'] = array_values(array_diff((array) $pc['mission_done'], [$id]));
                } else {
                    return ['这个合约暂时接不了：' . $rep['why']];
                }
            }
            if (($pc['mission'] ?? '') === $id) {
                return ['这个合约已经在你手上了（第 ' . (int) $pc['mission_step'] . '/' . count($m['steps']) . ' 步），不要重复接。'];
            }
            $inHand = crrg_drill_mission($pc['mission'] ?? '');
            if ((!empty($m['repeat']) || !empty($inHand['coexist'])) && !empty($pc['mission'])) {   // 日常／课目在手 → 并到侧槽
                $cur2 = $pc['mission2']['id'] ?? '';
                if ($cur2 !== '' && $cur2 !== $id) {
                    $mn2 = crrg_drill_mission($cur2);
                    return ['侧槽已占用：' . ($mn2['name'] ?? $cur2) . '。先「交任务」结算，或「放弃合约」。'];
                }
                if (function_exists('crrg_drill_daily_accept')) crrg_drill_daily_accept($pc, $id);
                return ['已接受（侧槽，与手上合约并行）：' . ($m['name'] ?? $id), $m['prompt'] ?? ''];
            }
            if (!empty($pc['mission'])) {
                $cm = crrg_drill_mission($pc['mission']);
                return ['手上还有没交的合约：' . ($cm['name'] ?? $pc['mission']) . '。（日常任务可并行：输入「接受 <日常id>」。）'];
            }
            if (!empty($m['repeat']) && ($pc['mission2']['id'] ?? '') === $id) { $pc['mission2'] = ['id'=>'','step'=>0,'start'=>0]; }
            if (!empty($m['party'])) {   // 人数要求：单独前往或组队
                $team = crrg_drill_team_of($uid);
                $n    = $team ? count($team['members']) : 1;
                $min  = (int) ($m['party']['min'] ?? 1);
                $max  = (int) ($m['party']['max'] ?? 1);
                if ($n < $min) return [sprintf('这个合约需 %d～%d 人同行（你这边 %d 人）——先在指挥大厅组队。', $min, $max, $n)];
                if ($n > $max) return [sprintf('这个合约最多 %d 人（你这边 %d 人）——超出的人得退队。', $max, $n)];
            }
            $need = (int) ($m['min_lv'] ?? 3);
            if (crrg_drill_eff_lv($pc, $ctx) < $need) return [sprintf('资格不够：需 %s 级（你的演习权限 %s）。', crrg_drill_clearance_name($need), crrg_drill_clearance_name(crrg_drill_eff_lv($pc, $ctx)))];
            $pc['mission'] = $id;
            $pc['mission_start'] = function_exists('crrg_vitals_now') ? crrg_vitals_now() : time();
                $pc['mission_start_played'] = function_exists('crrg_played_sec') ? crrg_played_sec($pc) : 0;
            if (function_exists('crrg_seal_on_accept')) crrg_seal_on_accept($s, $pc, $id); $pc['mission_step'] = 0;
            $rm = crrg_drill_rooms()[$m['depart_room'] ?? ''] ?? null;
            $step1 = $m['steps'][0] ?? [];
            $hint = '';
            if (function_exists('crrg_drill_route_hint')) {
                foreach ((array) $m['steps'] as $st) {
                    $target = $st['room'] ?? ($st['hint_room'] ?? '');
                    if ($target !== '') { $hint = '怎么走：' . crrg_drill_route_hint($s['room'] ?? '', $target); break; }
                }
            }
            $intro = (array) ($m['intro'] ?? []);
            $out = ['已接受：' . $m['name'], $m['prompt'] ?? ''];
            foreach ($intro as $ix) $out[] = $ix;                          // 开场（连续任务）
            $out[] = $hint ?: ('第一步：' . ($step1['text'] ?? ''));
            $out[] = '出发点：' . ($rm['t'] ?? ($m['depart_room'] ?? '—')) . '，到那儿「出发」。';
            return $out;

        case 'abandon':
            if (empty($pc['mission']) && !empty($pc['mission2']['id'])) {
                $m2n = crrg_drill_mission($pc['mission2']['id']);
                $pc['mission2'] = ['id' => '', 'step' => 0, 'start' => 0];
                $out = ['已放弃（侧槽）：' . ($m2n['name'] ?? '')];
                break;
            }
            if (empty($pc['mission'])) return ['手上没有合约。'];
            $m = crrg_drill_mission($pc['mission']);
            $loc = (($m['scene'] ?? 'base') === 'mod_wild') ? 'base' : 'base';
            $out = ['已放弃：' . ($m['name'] ?? '合约') . '（进度清空，可再接）。'];
            if (function_exists('crrg_rep_add')) {          // 放人鸽子要掉名声
                $now = crrg_rep_add($pc, $loc, -2, '放弃：' . ($m['name'] ?? '合约'));
                $out[] = sprintf('（%s声望 -2 → %d）', crrg_rep_places()[$loc]['short'] ?? $loc, $now);
            }
            $pc['mission'] = ''; $pc['mission_step'] = 0;
            return $out;

        case 'settle_now':
            $m = crrg_drill_mission($pc['mission'] ?? '');
            if (!$m) return ['手上没有合约。'];
            if (($m['brief_room'] ?? 'b2b_hall') !== $s['room']) return ['结算要找' . ($m['brief_name'] ?? '指挥大厅') . '。'];
            [$mm, $out] = crrg_drill_mission_settle($pc, $ctx, $uid, $s);
            return $out;

        case 'team_new':
            [$tid, $out] = crrg_drill_team_create($uid, '');
            return $out;
        case 'team_join':
            return crrg_drill_team_join($uid, $id);
        case 'team_leave':
            return crrg_drill_team_leave($uid);
        case 'team_disband':
            $t = crrg_drill_teams();
            foreach ($t as $tid => $tt) if (in_array((int) $uid, array_map('intval', $tt['members']), true)) unset($t[$tid]);
            crrg_drill_teams_save($t);
            return ['队伍已解散。'];
        case 'team_share':
            $t = crrg_drill_teams();
            foreach ($t as $tid => &$tt) {
                if (!in_array((int) $uid, array_map('intval', $tt['members']), true)) continue;
                if ((int) $tt['leader'] !== (int) $uid) return ['只有队长能改分红。'];
                if ($id === 'even')  crrg_drill_team_share_reset($tt);
                if ($id === 'lead50') {
                    $n = count($tt['members']);
                    $each = $n > 1 ? (int) floor(50 / ($n - 1)) : 0;
                    $tt['share'] = [];
                    foreach ($tt['members'] as $i => $mm) $tt['share'][(string) $mm] = ($i === 0) ? 100 - $each * (max(1, $n - 1)) : $each;
                }
                crrg_drill_teams_save($t);
                return ['分红已设定：' . ($id === 'even' ? '平均' : '队长 50%') . '。队员不满意可以退队。'];
            }
            return ['你不在任何队伍里。'];

        case 'depart':
            [$m, $out] = crrg_drill_depart($s, $pc, $ctx, $uid, $id === 'car');
            return $out;

        case 'travel':   // 基地车库 ↔ 青石镇
            [$dest, $pay] = array_pad(explode(':', $id, 2), 2, 'credits');
            [$ok, $lines] = crrg_drill_travel($s, $pc, $ctx, $dest, $pay);
            return $lines;

        case 'dorm_rest':
            if (!in_array($s['room'], ['b1_room', 'b1_dorm'], true)) return ['休整要在宿舍里。'];
            return crrg_drill_dorm_rest($pc);
        case 'dorm_train':
            if (!in_array($s['room'], ['b1_room', 'b1_dorm'], true)) return ['锻炼要在宿舍里。'];
            return crrg_drill_dorm_train($pc, $id);
        case 'dorm_deco':
            $d = (array) ($pc['dorm'] ?? []);          // 2026-10-01 修：空档没有 dorm 键时会出 warning ✗
            $out = ['〔宿舍布置〕'];
            $out[] = '家具：' . (($d['furn'] ?? []) ? implode('、', $d['furn']) : '只有配发的床、桌、柜');
            $out[] = '装修：' . (($d['deco'] ?? []) ? implode('　', array_map(fn($k, $v) => $k . '－' . $v, array_keys($d['deco']), $d['deco'])) : '（未装修）');
            if (!empty($d['desc'])) $out[] = '私人说明：' . $d['desc'];
            return $out;

        case 'promote':
            $pr = crrg_drill_promotions();
            $cur = crrg_drill_eff_lv($pc, $ctx);
            $next = null;
            foreach ($pr as $lv => $cost) if ($lv > $cur) { $next = [$lv, $cost]; break; }
            if (!$next) return ['演习权限已到顶。'];
            if ((int) $pc['credits'] < $next[1]) {
                return [sprintf('下一档 %s 需 %d 积分，现有 %d。', crrg_drill_clearance_name($next[0]), $next[1], (int) $pc['credits'])];
            }
            $pc['credits'] -= $next[1];
            $pc['clearance'] = $next[0];
            return [sprintf('已兑换：演习权限升到 %s 级（职务不动），剩余积分 %d。',
                crrg_drill_clearance_name($next[0]), (int) $pc['credits'])];

        case 'theme':   // 界面色调（每人一份）
            $ok = ['red', 'amber', 'green', 'cyan', 'violet'];
            if (!in_array($id, $ok, true)) return ['没有这个色调。'];
            $pc['theme'] = $id;
            $names = ['red' => '河洛红', 'amber' => '琥珀', 'green' => '终端绿', 'cyan' => '荧光青', 'violet' => '紫'];
            return ['色调已切换为「' . $names[$id] . '」，已记在这个账号上。'];

        case 'vehicle':
            $vi = crrg_drill_vehicle_info($pc);
            if (!$vi) return ['你还没有车。商超有自行车（600 元）；车库调剂处可用积分换：越野摩托 120、越野车 300。'];
            return ['车库里有你的' . $vi['name'] . '——' . $vi['note'],
                    $vi['carry'] ? '（出勤可捎 ' . $vi['carry'] . ' 名队员。）' : '（单人使用。）'];
    }
    /* ═══ 面板动作补齐（此前只接了命令侧，点按钮会回「未知操作」） ═══ */
    switch ($k) {
        case 'survey':
            $r = null;
            foreach (['crrg_seal3_survey', 'crrg_seal2_survey', 'crrg_seal_survey', 'crrg_mid_gap_survey'] as $fn) {
                if ($r === null && function_exists($fn)) $r = $fn($s, $pc, $id);
            }
            if ($r === null) return ['（这里没有能翻的东西。）'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'survey', $r);
            return $r;

        case 'mact':   // 当场动作（新任务入口）
            if (!function_exists('crrg_base_mission_acts')) return ['未加载。'];
            $mid = '';
            foreach ([$pc['mission'] ?? '', $pc['mission2']['id'] ?? ''] as $cand) {
                if ($cand !== '' && isset(crrg_base_mission_acts()[$cand])) { $mid = $cand; break; }
            }
            $flag = '';
            foreach ((array) (crrg_base_mission_acts()[$mid] ?? []) as $room2 => $specs2) foreach ((array) $specs2 as $sp2) {
                if ('a' . substr(md5($mid . '|' . $sp2[1]), 0, 10) === $id) $flag = $sp2[1];
            }
            if ($flag === '') {                                  // 兼容旧式 id（mid|flag）
                [$mid2, $flag] = array_pad(explode('|', $id, 2), 2, '');
                if ($flag !== '') { $mid = $mid2; }
            }
            $持有 = (($pc['mission'] ?? '') === $mid) || (($pc['mission2']['id'] ?? '') === $mid);
            if (!$持有 || $flag === '') return ['（这不是你手上的活儿。）'];
            $acts = crrg_base_mission_acts()[$mid][$s['room']] ?? [];
            $hit = null;
            foreach ((array) $acts as $spec) if ($spec[1] === $flag) { $hit = $spec; break; }
            if (!$hit) return ['（这儿做不了这件事。）'];
            if (!empty($hit[3]) && function_exists('crrg_world_clock') && !crrg_world_clock()['night']) return ['（这件事要到夜里才做得成。）'];
            if (!is_array($s['flags'] ?? null)) $s['flags'] = [];
            if (!in_array($hit[1], $s['flags'], true)) $s['flags'][] = $hit[1];
            $out = (array) $hit[2];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'mact', $out);
            return $out;

        case 'nightwatch':
            $out = function_exists('crrg_seal3_nightwatch') ? crrg_seal3_nightwatch($s, $pc) : ['未加载。'];
            if (function_exists('crrg_seal3_conclude')) $out = array_merge((array) $out, (array) crrg_seal3_conclude($s, $pc));
            if (function_exists('crrg_seal3_events')) $out = crrg_seal3_events($s, $pc, $out);
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'nightwatch', $out);
            return $out;

        case 'poison':
            $out = function_exists('crrg_seal3_poison') ? crrg_seal3_poison($s, $pc, $id) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'poison', $out);
            return $out;

        case 'guard':
            $out = function_exists('crrg_seal4_rob') ? crrg_seal4_rob($s, $pc, $id) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'guard', $out);
            return $out;

        case 'accident':
            $out = ($id === '' && function_exists('crrg_seal4_accident')) ? crrg_seal4_accident($s, $pc)
                 : (function_exists('crrg_seal4_accident_handle') ? crrg_seal4_accident_handle($s, $pc, $id) : ['未加载。']);
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'accident', $out);
            return $out;

        case 'craft':
            $out = function_exists('crrg_seal4_craft') ? crrg_seal4_craft($s, $pc, $id) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'craft', $out);
            return $out;

        case 'handover':
            $out = function_exists('crrg_seal4_handover') ? crrg_seal4_handover($s, $pc) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'handover', $out);
            return $out;

        case 'sealpath':
            if (!function_exists('crrg_seal_try')) return ['未加载。'];
            $out = crrg_seal_try($s, $pc, $id, '');
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'sealpath', $out);
            return $out;

        case 'seal5run':
            $out = function_exists('crrg_seal5_run') ? crrg_seal5_run($s, $pc) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'seal5run', $out);
            return $out;

        case 'seal5seal':
        case 'seal5close':
            $out = function_exists('crrg_seal5_close') ? crrg_seal5_close($s, $pc) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'seal5close', $out);
            return $out;

        case 'stagechoice':
            $mid2 = $pc['mission'] ?? '';
            if ($mid2 === 'm_mid_seal2' && function_exists('crrg_seal2_stage_choice'))      $out = crrg_seal2_stage_choice($s, $pc, $id);
            elseif ($mid2 === 'm_mid_seal' && function_exists('crrg_seal_stage_choice'))    $out = crrg_seal_stage_choice($s, $pc, $id);
            else $out = function_exists('crrg_seal2_stage_choice') ? crrg_seal2_stage_choice($s, $pc, $id) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'stage', $out);
            return $out;

        case 'parley':
            if (($pc['mission'] ?? '') === 'm_mid_seal3' && function_exists('crrg_seal3_parley')) {
                $out = crrg_seal3_parley($s, $pc, $id);
                if (function_exists('crrg_seal3_conclude')) $out = array_merge((array) $out, (array) crrg_seal3_conclude($s, $pc));
            } elseif (function_exists('crrg_seal3_parley') && in_array('s3_遇冒充者', (array) ($s['flags'] ?? []), true)) {
                $out = crrg_seal3_parley($s, $pc, $id);
            } elseif (function_exists('crrg_mid_gap_pressure')) {
                $out = crrg_mid_gap_pressure($s, $pc, $id);
            } else { $out = ['（现在没什么好谈的。）']; }
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'parley', $out);
            return $out;

        case 'resolve':
            $out = function_exists('crrg_mid_gap_resolve') ? crrg_mid_gap_resolve($s, $pc, $id) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'resolve', $out);
            return $out;

        case 'takekey':
            $out = function_exists('crrg_mid_gap_take_key') ? crrg_mid_gap_take_key($s, $pc) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'takekey', $out);
            return $out;

        case 'sealroom':
            return function_exists('crrg_seal_room') ? crrg_seal_room($s, $pc) : ['未加载。'];

        case 'appraise':
            $out = function_exists('crrg_appraise') ? crrg_appraise($s, $pc, $id) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'appraise', $out);
            return $out;

        case 'ask':    // 有些面板/通道送 k=ask（内部键）→ 转成命令走同一套逻辑（2026-09-29）
            if (!function_exists('crrg_drill_exec')) return ['对话系统未加载。'];
            return crrg_game_exec($s, $pc, $ctx, $uid, '问 ' . (string) $id);

        case 'ask2':
            $parts = preg_split('/\s+/', $id, 2);
            $r = null;
            foreach (['crrg_seal3_npc_ask', 'crrg_seal2_npc_ask', 'crrg_seal_npc_ask'] as $fn) {
                if ($r === null && function_exists($fn)) $r = $fn($s, $pc, $parts[0] ?? '', $parts[1] ?? '');
            }
            if ($r === null) return ['（没有这个话题。）'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'ask', $r);
            return $r;

        case 'accept_late':
            return function_exists('crrg_chase_act') ? crrg_chase_act($s, $pc, $ctx, $uid, 'accept_late') : ['未加载。'];

        case 'report':
        case 'talk':
        case 'tell_truth':
        case 'tell_lie':
        case 'sexton':
        case 'sexton_bribe':
        case 'sexton_press':
        case 'take_case':
        case 'door':
            return function_exists('crrg_chase_act') ? crrg_chase_act($s, $pc, $ctx, $uid, $k) : ['未加载。'];
    }
    /* 2026-09-29 兜底：有的通道（聊天/投影）把**按钮标签**当 k 发过来 →
       这里把不认识的 k 当作**命令**再跑一次，任何标签都能用。
       （口径：按钮标签必须能当命令跑） */
    if (is_string($k) && trim($k) !== '') {
        /* ① 先在本房间的面板里按"标签"找到那一条 → 用它的 k/id 执行 */
        $me = function_exists('crrg_drill_panels') ? crrg_drill_panels($s, $ctx, $pc, $uid) : [];
        foreach ((array) $me as $pp) {
            foreach ((array) ($pp['items'] ?? []) as $ii) {
                if (trim((string) ($ii['label'] ?? '')) !== trim($k)) continue;
                $ik = (string) ($ii['k'] ?? ''); $iid = (string) ($ii['id'] ?? '');
                if ($ik === '' || $ik === $k || $ik === 'noop' || $ik === 'divider') continue;
                return crrg_drill_do_act($s, $pc, $ctx, $uid, $ik, $iid);
            }
        }
        /* ①.4 2026-10-01（林晓雅报障 ✓）：**服务端也认 `k='cmd'`** ——
           `k='cmd'` 的按钮，`id` 就是一条指令（如「去 货运电梯」）→ 这里直接执行 ✓
           为什么要放服务端 ✗：前端虽有 `cmd` 分支 ✓，但**浏览器缓存旧 JS** 时仍会走 `act('cmd', …)` ✓
           → 旧 JS 就全变成"没接上动作"✗（她遇到的正是这个 ✓）。服务端兜住 → 新旧 JS 都能走 ✓ */
        if ((string) $k === 'cmd') {
            $cmdTxt = trim((string) $id);
            if ($cmdTxt === '') return ['（这条按钮没带指令。）'];
            if (function_exists('crrg_game_exec')) {
                return (array) crrg_game_exec($s, $pc, $ctx, $uid, $cmdTxt);
            }
            if (function_exists('crrg_drill_exec')) {
                $r = crrg_drill_exec($s, $ctx, $cmdTxt, $pc, $uid);
                if (is_array($r)) { $s = $r[0] ?? $s; $pc = $r[2] ?? $pc; return (array) ($r[1] ?? []); }
            }
            return ['（这条按钮没带指令。）'];
        }
        /* ①.5 2026-10-01：**模块钩子** —— 玩家互动模块自己认领它的动作 k ✓
           （返回 null＝不是它的 → 继续往下兜底 ✓ 这样核心不必认识 social ✓） */
        if (function_exists('crrg_soc_do')) {
            $socR = crrg_soc_do($k, (string) $id, $s, $pc, $uid, $ctx);
            if ($socR !== null) return $socR;
        }
        /* ② 再当成命令跑一次（纯英文的"内部键"不试——那是 k，不是命令） */
        if (preg_match('/^[a-z_0-9]+$/i', trim((string) $k))) return ['（这个按钮没接上动作——已记下。）'];
        if (function_exists('crrg_game_exec')) {
            $lines = crrg_game_exec($s, $pc, $ctx, $uid, (string) $k);
            if ($lines) return $lines;
        }
    }
    return ['未知操作。'];
}
