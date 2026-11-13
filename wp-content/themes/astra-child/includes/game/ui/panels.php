<?php
/**
 * 自 includes/drill-economy.php 搬出的函数（2026-09-30）
 */
if (!defined('ABSPATH')) exit;

/* ================= 面板数据（前端只渲染按钮） ================= */
function crrg_drill_panels($s, $ctx, $pc, $uid) {
    $room = $s['room'];
    $P = [];
    $act = function ($label, $k, $id = '', $extra = []) { return ['label' => $label, 'k' => $k, 'id' => $id] + $extra; };

    /* 在手的合约：结算／放弃（放在最前，避免找不到） */
    if (function_exists('crrg_drill_mission2')) {
        $m2 = crrg_drill_mission2($pc);
        if (!empty($m2['id']) && ($mDef2 = crrg_drill_mission_any($m2['id']))) {
            $st2 = (int) ($m2['step'] ?? 0);
            $P[] = ['title' => '在手（日常）· ' . $mDef2['name'],
                    /* 2026-10-14（R5）：**引擎任务不显示步骤清单**（委员长：不要"任务步骤"✗）——
                       玩家靠**笔记里的线索与推断**自己找路 ✓ 老任务照旧显示 ✓ */
                    'note' => (empty($mDef2['steps'])
                        ? '（线索与推断都在「笔记」里——自己去翻。）'
                        : sprintf('%d/%d 步 ｜ %s', $st2, count($mDef2['steps']),
                            ($mDef2['steps'][$st2]['text'] ?? '下一步：回值班板交单'))),
                    'items' => [['label' => '交日常（在值班板）', 'k' => 'settle_now', 'id' => 'daily']]];
        }
    }
    $held = !empty($pc['mission']) ? crrg_drill_mission_any($pc['mission']) : null;
    if ($held) {
        $step = (int) ($pc['mission_step'] ?? 0);
        $total = count($held['steps']);
        $mi = [];
        $need_ok = true;
        foreach ((array) ($held['require_flags'] ?? []) as $f) if (!in_array($f, (array) ($s['flags'] ?? []), true)) { $need_ok = false; break; }
        if ($step >= $total && !$need_ok) {
            $mi[] = ['label' => '（还差一步：先与当事人交代）', 'k' => 'noop', 'id' => '', 'off' => true];
        } elseif ($step >= $total) {
            if (($held['brief_room'] ?? 'b2b_hall') === $room) {
                $mi[] = $act('交任务 · 结算（' . $held['name'] . '）', 'settle_now');
            } else {
                $mi[] = ['label' => '去' . ($held['brief_name'] ?? '指挥大厅') . '结算', 'k' => 'noop', 'id' => '', 'off' => true];
            }
        } else {
            // 不显示进度数字，只留一句「进行中」
            $mi[] = ['label' => '进行中', 'k' => 'noop', 'id' => '', 'off' => true];
        }
        $mi[] = $act('放弃合约', 'abandon');
        /* 2026-09-30：显示行**已摘除** → 由 `missions` 窗口承载；$held/$mi 后面仍在用，不能整块删 ✗ */
    }

    /* 装备栏：能穿的穿上，穿着的可以脱 */
    if (function_exists('crrg_equip_of')) {
        $ei = [];
        $eq = crrg_equip_of($pc);
        foreach (crrg_drill_bag_counts($pc) as $id => $n) {
            if ($n <= 0) continue;
            $slots = crrg_equip_item_slots($id);
            if (!$slots) continue;
            $free = true;
            foreach ($slots as $sl) if (!empty($eq[$sl])) $free = false;
            $ei[] = ['label' => ($free ? '穿戴 · ' : '（部位占用）') . crrg_drill_item_name($id)
                              . '（' . implode('/', array_map(fn($x) => crrg_equip_slots()[$x], $slots)) . '）',
                     'k' => 'wear', 'id' => $id, 'off' => !$free];
        }
        foreach (crrg_equip_slots() as $sl => $n) {
            if (empty($eq[$sl])) continue;
            $ei[] = ['label' => '脱下 · ' . $n . '（' . crrg_drill_item_name($eq[$sl]) . '）', 'k' => 'unwear', 'id' => $sl];
        }
        /* 装备：已由 `equip` 窗口接管（指令「装备栏」✓） */
    }

    /* 情报处换台灯（委托的交付环节） */
    if ($room === 'b2b_intel' && function_exists('crrg_drill_own_count')) {
        $have = crrg_drill_own_count($pc, 'lamp_cold');
        $done = in_array('lamp_delivered', (array) ($s['flags'] ?? []), true);
        $P[] = ['title' => '换台灯' . ($done ? '（已完成）' : '（现有 ' . $have . '/2）'),
                'note' => $done ? '两个位置都换好了。' : '冷白台灯在基地商超（190 元一盏）；买错了可拆下退掉。',
                'items' => $done
                    ? [['label' => '已交付', 'k' => 'noop', 'id' => '', 'off' => true]]
                    : [['label' => '把两盏台灯换上', 'k' => 'deliver', 'id' => '', 'off' => ($have < 2)]]];
    }

    /* 2026-09-30：车辆与行程区块**已摘除（显示）** → 由 `vehicle` 窗口承载（指令「车辆」✓ 已验 12/12）
       —— 数据仍由 crrg_vehicle_block() 提供（窗口在读 ✓），只去掉面板这处显示 ✓ */
    /* 高速服务区 */
    if (function_exists('crrg_travel_is_service_area') && crrg_travel_is_service_area($room)) {
        $P[] = ['title' => '服务区', 'note' => '加油站、便利店、热水；能眯一会儿，但不顶睡。',
                'items' => [
                    ['label' => '加油（服务区油价 +10%）', 'k' => 'travel_refuel', 'id' => ''],
                    ['label' => '接热水（免费，口渴 +20）', 'k' => 'service_area_use', 'id' => 'hotwater'],
                    ['label' => '买矿泉水（5 元）', 'k' => 'service_area_use', 'id' => 'shop_water'],
                    ['label' => '买罐装咖啡（15 元）', 'k' => 'service_area_use', 'id' => 'shop_coffee'],
                    ['label' => '买压缩干粮（38 元）', 'k' => 'service_area_use', 'id' => 'shop_ration'],
                    ['label' => '在车里眯一小时（精力 +5）', 'k' => 'service_area_use', 'id' => 'nap'],
                ]];
    }

    /* 露营（野外节点） */
    if (function_exists('crrg_travel_camp_spots')) {
        $spots = crrg_travel_camp_spots();
        $here = $s['room'] ?? '';
        $inWild = (strpos($here, 'w_') === 0);
        if ($inWild && crrg_drill_bag_count($pc, 'tool_tent') > 0) {
            $P[] = ['title' => '扎营', 'note' => '山里的空地，可以睡一晚（比床上差，但比赶夜路强）。',
                    'items' => [['label' => '扎营（6 游戏小时）', 'k' => 'travel_camp', 'id' => '']]];
        }
    }

    /* 新任务的"当场动作"（她报的：没有入口） */
    if (function_exists('crrg_base_mission_acts') && (($pc['mission'] ?? '') !== '' || ($pc['mission2']['id'] ?? '') !== '')) {
        $mid = ($pc['mission'] ?? '') !== '' ? $pc['mission'] : ($pc['mission2']['id'] ?? '');
        /* 2026-09-29 修：侧槽的合约也要能在面板上操作（此前只看主槽，
           主槽若是"没有动作表"的任务，侧槽的动作就一个都不显示 → 玩家打不了勾） */
        $slotIds = [];
        if (($pc['mission'] ?? '') !== '') $slotIds[] = $pc['mission'];
        if (($pc['mission2']['id'] ?? '') !== '') $slotIds[] = $pc['mission2']['id'];
        $acts = [];
        foreach ($slotIds as $sid) {
            foreach ((array) (crrg_base_mission_acts()[$sid][$room] ?? []) as $spec2) { $spec2['__mid'] = $sid; $acts[] = $spec2; }
        }
        $items = [];
        $night = function_exists('crrg_world_clock') ? crrg_world_clock()['night'] : false;
        foreach ((array) $acts as $spec) {
            [$label, $flag] = [$spec[0], $spec[1]];
            if (in_array($flag, (array) ($s['flags'] ?? []), true)) continue;
            $needNight = !empty($spec[3]);
            $specMid = $spec['__mid'] ?? $mid;
            $items[] = ['label' => $label . ($needNight && !$night ? '（需夜间）' : ''),
                        'k' => 'mact', 'id' => 'a' . substr(md5($specMid . '|' . $flag), 0, 10),   // 无语义短码：不露内部编号
                        'off' => ($needNight && !$night)];
        }
        if ($items) {
            $P[] = ['title' => '当场可做的事', 'note' => '这一步就在这儿办。', 'items' => $items];
        } else {
            /* 本房间的动作都做完了 → 指明下一步该去哪、怎么走 */
            $pick = '';
            foreach ($slotIds as $sid) if (crrg_base_mission_acts()[$sid] ?? null) { $pick = $sid; break; }
            if ($pick === '') $pick = $mid;
            $mm = crrg_drill_mission_any($pick);
            $st = (int) ($pick === ($pc['mission'] ?? '') ? ($pc['mission_step'] ?? 0) : ($pc['mission2']['step'] ?? 0));
            $nxt = $mm['steps'][$st] ?? null;
            if ($nxt) {
                $target = $nxt['room'] ?? ($nxt['hint_room'] ?? '');
                $hint = $target ? ('怎么走：' . crrg_drill_route_hint($s['room'] ?? '', $target)) : '';
                $P[] = ['title' => '下一步', 'note' => ($nxt['text'] ?? '') . ($hint ? '　' . $hint : ''),
                        'items' => []];
            }
        }
    }

    /* 中型《封条》· 多过法（换法） */
    if (function_exists('crrg_seal_paths')) {
        $nodeByRoom = [
            'b3_warehouse' => ['封条', '库房盘点'], 'b4_obs' => ['台账'], 'b2b_tech' => ['配方', '配方比样', '开工', '贴封', '旧料新料', '事故补件'],
            'b1_archive' => ['采购', '当年事故', '结案校对'], 'tw_farm' => ['电瓶', '同一个人', '镇上问价'],
            'tw_clinic' => ['中毒'], 'tw_townhall' => ['手续', '镇文书绕过', '暗账复核'],
            'tw_junk' => ['铅', '生面孔'], 'tw_hillpath' => ['痕迹', '车辙', '山路人影', '收货点'],
            'tw_homes' => ['邻居', '徒弟去向', '徒弟娘亲'], 'tw_bank' => ['流水', '暗账复核'],
            'b4_main' => ['门', '门况复核', '廊门测量', '守卫'],   /* 2026-10-22：'事故定档' 属已作废的《封条》任务 → 摘掉 ✓ */ 'b4_c2' => ['私补'], /* 同上：'贴封'/'事故补件' 摘掉 ✓ */
            'tw_paper' => ['纸人', '旧库管', '收料人', '配方试贴', '回访常守业'],
            'b1_med' => ['病历', '体检随访'], 'b1_cmd_hall' => ['立卷'],
            'b2b_duty' => ['值班记录'], 'b2b_hall' => ['立卷', '结案校对', '善后核清单'],
            'tw_main_e' => ['收料人', '冒充者车'], 'tw_main_c' => ['冒充者车'],
            'b4_guard' => ['善后核清单'],
        ];
        /* 节点 → 归属任务（2026-09-29 修：原先只判"做完没有"，没判"任务在不在手上"，
           导致未接的内容也摆出入口） */
        $nodeMission = [
            /* 第一幕（基地） */
            '封条' => ['m_mid_seal'], '台账' => ['m_mid_seal'], '配方' => ['m_mid_seal'], '采购' => ['m_mid_seal'],
            '门' => ['m_mid_seal'], '私补' => ['m_mid_seal'], '立卷' => ['m_mid_seal'], '门况复核' => ['m_mid_seal'],
            '配方比样' => ['m_mid_seal'], '库房盘点' => ['m_mid_seal'], '廊门测量' => ['m_mid_seal'],
            '值班记录' => ['m_mid_seal'], '守卫' => ['m_mid_seal'], '当年事故' => ['m_mid_seal'],
            /* 第二幕（镇上） */
            '纸人' => ['m_mid_seal2'], '电瓶' => ['m_mid_seal2'], '中毒' => ['m_mid_seal2'], '手续' => ['m_mid_seal2'],
            '铅' => ['m_mid_seal2'], '痕迹' => ['m_mid_seal2'], '旧库管' => ['m_mid_seal2'], '收料人' => ['m_mid_seal2', 'm_mid_seal3'],
            '镇文书绕过' => ['m_mid_seal2'], '同一个人' => ['m_mid_seal2'], '镇上问价' => ['m_mid_seal2'],
            '配方试贴' => ['m_mid_seal2'], '回访常守业' => ['m_mid_seal2', 'm_mid_seal3'],
            /* 第三轮（徒弟与冒充者） */
            '邻居' => ['m_mid_seal3'], '流水' => ['m_mid_seal3'], '车辙' => ['m_mid_seal3'], '山路人影' => ['m_mid_seal3'],
            '徒弟去向' => ['m_mid_seal3'], '徒弟娘亲' => ['m_mid_seal3'], '生面孔' => ['m_mid_seal3'],
            '冒充者车' => ['m_mid_seal3'], '暗账复核' => ['m_mid_seal3'], '收货点' => ['m_mid_seal3'],
            /* 第四轮 / 第三幕 */
            '护料' => ['m_mid_seal4'], '事故定档' => ['m_mid_seal4'], '事故补件' => ['m_mid_seal4'], '事故封闭' => ['m_mid_seal4'],
            '开工' => ['m_mid_seal5'], '贴封' => ['m_mid_seal5'], '旧料新料' => ['m_mid_seal5'], '结案校对' => ['m_mid_seal5'],
            /* 日常 / 回声 */
            '值守' => ['m_dy_watch'], '巡检' => ['m_dy_patrol'], '修复' => ['m_dy_repair'], '替厨' => ['m_dy_kitchen'],
            '体检随访' => ['m_hook_med'], '善后核清单' => ['m_hook_after'], '病历' => ['m_mid_seal', 'm_hook_med'],
        ];
        $inHand = [];
        if (!empty($pc['mission'])) $inHand[] = $pc['mission'];
        if (!empty($pc['mission2']['id'])) $inHand[] = $pc['mission2']['id'];
        $it = [];
        foreach (($nodeByRoom[$room] ?? []) as $nd) {
            if (function_exists('crrg_seal_path_done') && crrg_seal_path_done($s, $nd)) continue;
            $allow = $nodeMission[$nd] ?? [];
            if (!$allow || !array_intersect($allow, $inHand)) continue;      // 没接这个任务 → 不显示
            if (in_array($nd, ['封条', '台账', '配方', '采购', '门', '私补'], true) === false && !$allow) continue;
            $it[] = ['label' => '换个法子查「' . $nd . '」', 'k' => 'sealpath', 'id' => $nd];
        }
        if ($it) $P[] = ['title' => '别的法子', 'note' => '同一件事不止一条路：检定／花钱／找人／硬来／绕路。', 'items' => $it];
    }

    /* 中型《封条》第五轮（第三幕） */
    if (function_exists('crrg_seal5_stage_lines') && ($pc['mission'] ?? '') === 'm_mid_seal5') {
        $it = [];
        $f = (array) ($s['flags'] ?? []);
        if ($room === 'b2b_tech' && !in_array('s5_出货', $f, true)) $it[] = ['label' => '开工（按选定的工艺出货）', 'k' => 'seal5run', 'id' => ''];
        if ($room === 'b4_c2' && in_array('s5_出货', $f, true) && !in_array('s5_貼封', $f, true)) $it[] = ['label' => '贴封：把 C2 西头重新贴上', 'k' => 'seal5seal', 'id' => ''];
        if ($room === 'b2b_hall' && in_array('s5_貼封', $f, true) && !in_array('s5_结案', $f, true)) $it[] = ['label' => '写结案报告', 'k' => 'seal5close', 'id' => ''];
        $lines = crrg_seal5_stage_lines($s, $pc);
        if ($it) $P[] = ['title' => '《封条》· 第三幕', 'note' => implode('　', $lines), 'items' => $it];
    }

    /* 中型《封条》第四轮 */
    if (function_exists('crrg_seal4_stage_lines') && ($pc['mission'] ?? '') === 'm_mid_seal4') {
        $it = [];
        $f = (array) ($s['flags'] ?? []);
        if (!in_array('s4_抢料', $f, true) && function_exists('crrg_seal4_rob'))
            foreach (['硬碰' => '动手护料（格斗）', '让步' => '分一半料出去', '开车跑' => '上车就跑（需车辆）'] as $k => $n)
                $it[] = ['label' => '护料 · ' . $n, 'k' => 'guard', 'id' => $k];
        if (in_array($room, ['b4_main', 'b4_c2'], true)) {
            $it[] = ['label' => '看看那扇门（事故定档）', 'k' => 'accident', 'id' => ''];
            if (in_array('s4_事故分档', $f, true) && !in_array('s4_事故处理', $f, true))
                foreach (['补件' => '用新料补上（需料完整）', '封闭' => '整段封死', '上报' => '写事故报告'] as $k => $n)
                    $it[] = ['label' => '事故处置 · ' . $n, 'k' => 'accident', 'id' => $k];
        }
        if ($room === 'b2b_tech') $it[] = ['label' => '交接材料（登记入库）', 'k' => 'handover', 'id' => ''];
        if ($room === 'b2b_tech' && in_array('s4_交接', $f, true) && !in_array('craft_选择', $f, true))
            foreach (['稳妥' => '稳妥工艺（韩雪 3 天）', '激进' => '激进工艺（周德海的土法）', '放弃' => '不自产，走特批'] as $k => $n)
                $it[] = ['label' => '工艺 · ' . $n, 'k' => 'craft', 'id' => $k];
        $lines = crrg_seal4_stage_lines($s, $pc);
        if ($it) $P[] = ['title' => '《封条》· 第四轮', 'note' => implode('　', $lines), 'items' => $it];
    }

    /* 中型《封条》第三轮：徒弟／冒充者／暗账／守夜／中毒 */
    if (function_exists('crrg_seal3_stage_lines') && ($pc['mission'] ?? '') === 'm_mid_seal3') {
        $it = [];
        $s3 = [
            'tw_homes' => ['敲邻居的门', '邻居'],
            'tw_bank'  => ['查今年的流水', '流水'],
            'tw_hillpath' => ['看新压的车辙', '车辙'],
        ];
        if (isset($s3[$room])) $it[] = ['label' => $s3[$room][0], 'k' => 'survey', 'id' => $s3[$room][1]];
        $a3 = ['tw_junk' => ['孙德发', '徒弟'], 'tw_paper' => ['常守业', '徒弟'],
               'tw_main_c' => ['冒充者', '身份']];
        if (isset($a3[$room])) $it[] = ['label' => '问' . $a3[$room][0] . '：' . $a3[$room][1], 'k' => 'ask2', 'id' => $a3[$room][0] . ' ' . $a3[$room][1]];   // 标签改成"人话"，别再像命令
        if ($room === 'tw_hillpath') $it[] = ['label' => '守夜（入夜后可做）', 'k' => 'nightwatch', 'id' => ''];
        if ($room === 'tw_main_c' && in_array('s3_遇冒充者', (array)($s['flags'] ?? []), true))
            foreach (['报案' => '交给派出所', '对峙' => '当面拆穿（恐吓检定）', '假料' => '卖他一批假料'] as $k => $n)
                $it[] = ['label' => '交涉 · ' . $n, 'k' => 'parley', 'id' => $k];
        if ($room === 'tw_paper' && in_array('s3_中毒事件', (array)($s['flags'] ?? []), true))
            foreach (['送医' => '送卫生院（50 元，声望 +3）', '先问料' => '先问配方（声望 −2）', '带回基地' => '扶上车带回基地（声望 −1）'] as $k => $n)
                $it[] = ['label' => '处置中毒 · ' . $n, 'k' => 'poison', 'id' => $k];
        $lines = crrg_seal3_stage_lines($s, $pc);
        $P[] = ['title' => '《封条》· 第三轮', 'note' => implode('　', $lines), 'items' => $it];
    }

    /* 中型《封条》第二幕：镇上五条线 */
    if (function_exists('crrg_seal2_stage_lines') && ($pc['mission'] ?? '') === 'm_mid_seal2') {
        $it = [];
        $spots2 = [
            'tw_paper' => ['翻柜台下的配方', '配方'], 'tw_paper_more' => null,
            'tw_farm' => ['看那堆旧电瓶', '电瓶'], 'tw_clinic' => ['让他伸出手（看中毒）', '中毒'],
            'tw_tomb' => ['看坡上的红土', '土'], 'tw_townhall' => ['问采买手续', '手续'],
            'tw_junk' => ['翻今年的旧货账', '铅'], 'tw_hillpath' => ['看夜里留下的痕迹', '痕迹'],
        ];
        if (isset($spots2[$room]) && $spots2[$room]) $it[] = ['label' => $spots2[$room][0], 'k' => 'survey', 'id' => $spots2[$room][1]];
        $asks2 = ['tw_paper' => ['常守业', '匠人'], 'tw_junk' => ['孙德发', '生面孔'],
                  'tw_townhall' => ['镇文书', '绕过'], 'tw_farm' => ['赵铁', '同一个人']];
        if (isset($asks2[$room])) $it[] = ['label' => '问' . $asks2[$room][0] . '：' . $asks2[$room][1], 'k' => 'ask2', 'id' => $asks2[$room][0] . ' ' . $asks2[$room][1]];   // 同上
        if ($room === 'tw_paper') $it[] = ['label' => '问常守业：旧库管那件事', 'k' => 'ask2', 'id' => '常守业 旧库管'];
        if ($room === 'tw_paper') $it[] = ['label' => '问常守业：里屋那个走了的徒弟', 'k' => 'ask2', 'id' => '常守业 徒弟'];
        if ($room === 'tw_townhall') {
            if (!in_array('paper_路线_就地', (array) ($s['flags'] ?? []), true) && !in_array('paper_路线_带回', (array) ($s['flags'] ?? []), true)
                && (in_array('paper_集齐', (array) ($s['flags'] ?? []), true) || count(array_intersect(
                    ['paper_配方','paper_铅箔','paper_中毒','paper_朱砂土','paper_通行'], (array) ($s['flags'] ?? []))) >= 4)) {
                $it[] = ['label' => '第二幕决断：配方留给镇上', 'k' => 'stagechoice', 'id' => '就地'];
                $it[] = ['label' => '第二幕决断：配方带回基地', 'k' => 'stagechoice', 'id' => '带回'];
            } else {
                $it[] = ['label' => '（材料还没凑齐，别急着定第二幕的走向）', 'k' => 'noop', 'id' => '', 'off' => true];
            }
        }
        $lines = crrg_seal2_stage_lines($s, $pc);
        $P[] = ['title' => '《封条》· 第二幕', 'note' => implode('　', $lines), 'items' => $it];
    }

    /* 中型《封条》第一幕：调查点／问人／决断 */
    if (function_exists('crrg_seal_stage_lines') && ($pc['mission'] ?? '') === 'm_mid_seal') {
        $it = [];
        $spots = [
            'b3_warehouse' => ['盘封条库存', '封条'],
            'b4_main'      => ['调查：收容廊门况', '封条'],
            'b4_c2'        => ['调查：C2 西头那扇门', '门'],
            'b4_obs'       => ['调查：封条领用台账', '台账'],
            'b2b_tech'     => ['调查：研发室废纸篓', '配方'],
            'b1_archive'   => ['调查：三年前的采购档', '采购'],
        ];
        if (isset($spots[$room])) $it[] = ['label' => $spots[$room][0], 'k' => 'survey', 'id' => $spots[$room][1]];
        $asks = ['b3_warehouse' => ['周德海', '就地取材'], 'b2b_tech' => ['韩雪', '配方'],
                 'b4_main' => ['守卫', '哪几扇'], 'b1_med' => ['白大夫', '当年']];
        if (isset($asks[$room])) $it[] = ['label' => '细问 ' . $asks[$room][0] . '：' . $asks[$room][1], 'k' => 'ask2', 'id' => $asks[$room][0] . ' ' . $asks[$room][1]];
        if ($room === 'b2b_hall') {
            /* 2026-09-29 修：功课没做完就不该出现"上报／压下"（原先一接单就能拍板） */
            $has = fn($f) => in_array($f, (array) ($s['flags'] ?? []), true);
            $four = ['seal_库存盘点', 'seal_门况普查', 'seal_私补痕迹', 'seal_用量台账', 'seal_自制配方'];
            $clues = count(array_intersect($four, (array) ($s['flags'] ?? [])));
            if (($clues >= 4 || $has('seal_来源指向镇上')) && !$has('seal_路线_上报') && !$has('seal_路线_压下')) {
                $it[] = ['label' => '第一幕决断：上报', 'k' => 'stagechoice', 'id' => '上报'];
                $it[] = ['label' => '第一幕决断：压下（领一张封条先补）', 'k' => 'stagechoice', 'id' => '压下'];
            } else {
                $it[] = ['label' => sprintf('（还查不动这件事——线索 %d／4。先去把那几个地方看完。）', $clues),
                         'k' => 'noop', 'id' => '', 'off' => true];
            }
        }
        $lines = crrg_seal_stage_lines($s, $pc);
        $P[] = ['title' => '《封条》· 第一幕', 'note' => implode('　', $lines), 'items' => $it];
    }

    /* 中型任务《清单上少了一件》：调查点与处置 */
    if (function_exists('crrg_mid_gap_survey') && in_array('m_mid_gap', (array) ($pc['mission_done'] ?? []), true) === false
        && ($pc['mission'] ?? '') === 'm_mid_gap') {
        $it = [];
        if ($room === 'b3_warehouse') $it[] = ['label' => '调查：对账（账本）', 'k' => 'survey', 'id' => '账'];
        if ($room === 'b2b_duty')     $it[] = ['label' => '调查：看排班表改动', 'k' => 'survey', 'id' => '排班'];
        if ($room === 'b2b_tech')     $it[] = ['label' => '调查：翻拆件记录', 'k' => 'survey', 'id' => '拆件'];
        if ($room === 'b2b_tech')     $it[] = ['label' => '取件：柜子里的油纸包', 'k' => 'takekey', 'id' => ''];
        if ($room === 'b1_med')       $it[] = ['label' => '旁证：翻病历登记本', 'k' => 'survey', 'id' => '病历'];
        if ($room === 'b4_main')      $it[] = ['label' => '旁证：问收容廊守卫', 'k' => 'survey', 'id' => '守卫'];
        if ($room === 'b1_archive')   $it[] = ['label' => '旁证：翻上月夜班签字联', 'k' => 'survey', 'id' => '记录'];
        if ($room === 'b2b_intel')    $it[] = ['label' => '旁证：让情报室叠表比对', 'k' => 'survey', 'id' => '比对'];
        if ($room === 'garage_maint') $it[] = ['label' => '验证：核出车登记簿', 'k' => 'survey', 'id' => '里程'];
        if (in_array($room, ['b3_warehouse','b1_cor_m','b1_cor_w'], true) && !in_array('阻挠_已处理', (array)($s['flags'] ?? []), true))
            $it[] = ['label' => '有人来找你说话（交涉）', 'k' => 'parley', 'id' => ''];
        if ($room === 'b2b_hall') {
            foreach (crrg_mid_gap_choices($pc) as $k => $c) $it[] = ['label' => '处置 ' . $k . '：' . $c['n'], 'k' => 'resolve', 'id' => $k];
        }
        if ($it) $P[] = ['title' => '《清单上少了一件》', 'note' => '三条线索＋一件关键物，凑齐后回值班板处置。', 'items' => $it];
    }

    /* 鉴定台（站点） */
    if (function_exists('crrg_appraise_sites')) {
        $site = crrg_appraise_sites()[$room] ?? null;
        if ($site) {
            $items = [];
            foreach ((array) crrg_drill_bag_counts($pc) as $iid => $n) {
                if (!crrg_appraise_needed($pc, $iid)) continue;
                if (!crrg_appraise_site_for($iid, $room)) continue;
                $items[] = ['label' => '鉴定 · ' . crrg_appraise_display($pc, $iid) . ($n > 1 ? '×' . $n : ''),
                            'k' => 'appraise', 'id' => $iid];
            }
            $note = $site['n'] . '｜支持：' . implode('、', array_map(fn($k) => crrg_appraise_kinds()[$k]['n'], $site['kinds']))
                  . ($site['fee'] ? '｜费用 ' . $site['fee'] . ($site['cur'] === 'credits' ? ' 积分' : ' 元') : '｜内部免费');
            $P[] = ['title' => '鉴定台', 'note' => $note,
                    'items' => $items ?: [['label' => '（背的东西都看过了）', 'k' => 'noop', 'id' => '', 'off' => true]]];
        }
    }
    /* 封门（有封条且已鉴定时） */
    if (function_exists('crrg_seal_room') && crrg_drill_bag_count($pc, 'key_sigil') > 0
        && !crrg_appraise_needed($pc, 'key_sigil')) {
        $P[] = ['title' => '封存', 'note' => '把这张封条贴在门框上，这间就算封了。',
                'items' => [['label' => '封门（消耗镇物封条 ×1）', 'k' => 'sealroom', 'id' => '']]];
    }

    /* 医疗室：激进治疗 / 放弃治疗 */
    if ($room === 'b1_med' && function_exists('crrg_fate_insane') && crrg_fate_insane($pc)) {
        $left = crrg_fate_insanity_window_left($pc);
        $P[] = ['title' => '医疗室 · 心理干预', 'note' => sprintf('窗口还剩 %.1f 游戏小时。激进治疗 %d 元＋住院三日，理智回 1D10，留一处永久后遗症。', $left, crrg_fate_treat_fee($pc)),
                'items' => [['label' => sprintf('接受激进治疗（%d 元）', crrg_fate_treat_fee($pc)), 'k' => 'fate_radical_treat', 'id' => ''],
                            ['label' => '放弃治疗（永久失能）', 'k' => 'giveup', 'id' => '']]];
    }
    /* 轮回（S 级） */
    if (function_exists('crrg_fate_is_s') && crrg_fate_is_s($uid)) {
        $list = crrg_fate_sealed_list(); $items = [];
        foreach ($list as $tid => $x) $items[] = ['label' => sprintf('轮回 · 复活 #%d %s', $tid, $x['name']), 'k' => 'samsara', 'id' => (string) $tid];
        $P[] = ['title' => '轮回（S 级权限）', 'note' => '完美复活一张封存的卡；代价：你降为 A 级、各地点声望减半。',
                'items' => $items ?: [['label' => '（暂无封存卡）', 'k' => 'noop', 'id' => '', 'off' => true]]];
    }

    /* 身体状态 */
    if (function_exists('crrg_vitals_lines')) {
        $vv = crrg_vitals($pc);
        $it = [];
        $it[] = ['label' => '看详细（状态）', 'k' => 'vitals', 'id' => ''];
        if (crrg_vitals_sleeping($pc)) {
            $it[] = ['label' => '起床', 'k' => 'vitals_wake', 'id' => ''];
        } else {
            $it[] = ['label' => '打盹 2 小时', 'k' => 'vitals_sleep', 'id' => '2'];
            $it[] = ['label' => '睡 8 小时', 'k' => 'vitals_sleep', 'id' => '8'];
        }
        if (crrg_drill_bag_count($pc, 'drink_coffee') > 0)  $it[] = ['label' => '喝罐装咖啡（精力 +25）', 'k' => 'vitals_drink', 'id' => 'drink_coffee'];
        if (crrg_drill_bag_count($pc, 'drink_mineral') > 0) $it[] = ['label' => '喝矿泉水（口渴 +35）', 'k' => 'vitals_drink', 'id' => 'drink_mineral'];
        if (crrg_drill_bag_count($pc, 'food_ration') > 0)  $it[] = ['label' => '啃压缩干粮（饱食 +22）', 'k' => 'vitals_eat', 'id' => 'food_ration'];
        if (crrg_drill_bag_count($pc, 'med_firstaid') > 0) $it[] = ['label' => '用急救包治伤（急救，+1D6）', 'k' => 'vitals_heal', 'id' => 'med_firstaid'];
        if (crrg_drill_bag_count($pc, 'med_bandage') > 0)  $it[] = ['label' => '用绷带包扎（急救，+1D3）', 'k' => 'vitals_heal', 'id' => 'med_bandage'];
        /* 身体状态：已由 `vitals` 窗口接管（指令「身体」✓） */
    }

    /* 2026-09-30：区域图区块**已摘除** → 由 `map` 窗口承载（指令「大地图」✓） */

    /* 地方声望 */
    if (function_exists('crrg_rep_lines')) {
        $it = [];
        foreach (crrg_rep_places() as $k => $pp) {
            $t = crrg_rep_tier($pc, $k);
            $it[] = ['label' => sprintf('%s：%d（%s）', $pp['short'], crrg_rep_of($pc, $k), $t['n']),
                     'k' => 'repview', 'id' => $k];
        }
        /* 地方声望：已由 `career` 窗口接管 ✓ */
    }

    /* 值班板取件（基地内跑腿单的第一步） */
    if ($room === 'b2b_hall' && ($pc['mission'] ?? '') !== '') {
        $mm = crrg_drill_mission($pc['mission']);
        if ($mm && stripos($mm['name'], '跑腿') !== false) {
            $flag = 'pm_picked_' . $mm['name'];
            $got  = in_array($flag, (array) ($s['flags'] ?? []), true);
            $P[] = ['title' => '值班板 · 取件',
                    'note' => $got ? '单子已经在你兜里了。' : '板上那张单子对得上你手上的活儿。',
                    'items' => $got
                        ? [['label' => '已取下：' . $mm['name'], 'k' => 'noop', 'id' => '', 'off' => true]]
                        : [['label' => '从值班板取下单子', 'k' => 'pm_pick', 'id' => '']]];
        }
    }

    /* 回收与拆除（买错了有出路，但只能低价出手） */
    if (function_exists('crrg_trade_offers')) {
        if ($room === 'tw_junk') {
            $it = crrg_trade_offers($pc, 'junk');
            /* 2026-09-30：显示行**已摘除** → 由 `sell` 窗口承载（指令「回收」✓）；$it 保留给后面用 ✓ */
        }
        if ($room === 'b3_warehouse') {
            $it = crrg_trade_offers($pc, 'logistics');
            /* 2026-09-30：显示行**已摘除** → 由 `sell` 窗口承载（指令「回收」✓）；$it 保留 ✓ */
        }
        /* 宿舍·拆下退掉：已由 `dorm` 窗口接管 ✓ */
    }

    /* 储物柜：已由 `stash` 窗口接管（指令「储物柜」✓） */
    /* 商店：已由 `shop` 窗口接管（指令「商店」✓ 本间只有一家店时**直达** ✓ 2026-09-30 摘除） */

    /* 旧脚本与合约的入口提示（2026-09-29）：把「领任务／交报告」这两个旧名字引导到合约系统 */
    if ($room === 'b2b_hall') {
        $done_train = in_array('m_train_01', (array) ($pc['mission_done'] ?? []), true);
        $heldNow = ($pc['mission'] ?? '') !== '' || ($pc['mission2']['id'] ?? '') !== '';
        $it = [];
        if (!$done_train && !$heldNow) $it[] = ['label' => '接：新手训练考核（正式合约，有奖励）', 'k' => 'accept', 'id' => 'm_train_01'];
        $lim = 0; foreach (crrg_drill_missions() as $mid2 => $m2) { if (!empty($m2['repeat']) && (int) ($m2['min_lv'] ?? 3) <= crrg_drill_eff_lv($pc, $ctx) && !in_array($mid2, (array) ($pc['mission_done'] ?? []), true)) $lim++; }
        if ($lim) $it[] = ['label' => '今日日常合约：' . $lim . ' 条可做（见值班板）', 'k' => 'noop', 'id' => '', 'off' => true];
        if ($heldNow) $it[] = ['label' => '交任务 · 结算（与「交报告」等价）', 'k' => 'settle_now', 'id' => ''];
        /* 2026-09-30：显示行**已摘除** → 由 `board` 窗口承载；$it/$lim 保留给后面用 ✓ */
    }

    /* 任务：基地任务（值班板）／NPC 任务（人在的地方） */
    /* 2026-09-30：值班板区块**已摘除** → 由 `board` 窗口承载（指令「值班板」✓） */
    /* NPC 任务：由 NPC 实体挂出，见 crrg_drill_npc_missions() */
    foreach (crrg_drill_npc_missions() as $room_key => $list) {
        if ($room_key !== $room) continue;
        foreach ($list as $npc => $ids) {
            $items = [];
            foreach ($ids as $id) {
                $m = crrg_drill_mission($id);
                if (!$m) continue;
                $done = in_array($id, (array) ($pc['mission_done'] ?? []), true);
                $items[] = $act(sprintf('%s · %s（%d 积分 / %d 元）', $m['name'], '私下委托',
                                        $m['reward']['credits'] ?? 0, $m['reward']['money'] ?? 0),
                                'accept', $id, ['note' => $m['prompt'] ?? '', 'done' => $done]);
            }
            $P[] = ['title' => '找 ' . $npc . ' 说话', 'note' => '个人委托：在这里接单，取件／送达，最后回**值班板**签字结算。',
                    'items' => $items];
        }
    }

    if ($b = (function_exists('crrg_team_block') ? crrg_team_block($s, $ctx, $pc, $uid) : null)) $P[] = $b;
    /* 2026-09-30：出勤区块**已摘除** → 由 `missions` 窗口承载（出发／车费 两行 ✓） */

    /* 宿舍 */
    /* 宿舍：区块构建搬到 game/systems/dorm.php（面板与窗口共用 ✓ 2026-09-30） */
    /* 宿舍：已由 `dorm` 窗口接管（指令「宿舍」✓） */
    /* 对话：本房间里可谈的人，按已解锁话题出按钮（不问不说） */
    if (function_exists('crrg_dlg_npcs_at')) {
        foreach (crrg_dlg_npcs_at($room) as $nid => $d) {
            $ti = crrg_dlg_panel_items($s, $nid, $pc);
            if (!$ti) continue;
            $P[] = ['title' => '问 ' . $d['name'] . '（' . count($ti) . ' 个话题）',
                    'note' => '想不起来问什么，就先把知道的说给他听。',
                    'items' => $ti];
        }
    }

    /* 模组动作（追书人等）：按房间与进度给按钮 */
    if (function_exists('crrg_chase_panels')) {
        $ci = crrg_chase_panels($s, $pc, $room);
        if ($ci) $P[] = ['title' => '现场动作', 'note' => '这桩事能做的下一步。', 'items' => $ci];
    }

    /* 建筑功能（能挣钱、治病、查档、换情报的） */
    $fn = [];
    $mk = function ($label, $id) { return ['label' => $label, 'k' => 'town', 'id' => $id]; };
    if ($room === 'tw_bank') {
        $fn[] = $mk('存款（100 起）', 'bank|deposit');
        $fn[] = $mk('取款（100 起）', 'bank|withdraw');
        $fn[] = $mk('个人账户往来 · 查旧账（追书人线，需镇府证明）', 'bank|record');   // 2026-10-01：标明属于哪条线，免得与封条线的「流水」串 ✓
    }
    if ($room === 'tw_post') {
        $fn[] = $mk('汇款回基地', 'post|remit');
        $fn[] = $mk('寄样本回基地（30 元）', 'post|mail_sample');
        $fn[] = $mk('打电话求援（20 积分）', 'post|call_base');
        $fn[] = $mk('发电报（20 元）', 'post|telegram');
    }
    if ($room === 'tw_clinic') {
        $fn[] = $mk('门诊处置（50 元）', 'clinic|treat');
        $fn[] = $mk('处置重伤（300 元）', 'clinic|major');
        $fn[] = $mk('留观一天（200 元）', 'clinic|rest');
    }
    if ($room === 'tw_book') {
        $fn[] = $mk('买书研读（120 元）', 'book|read');
        $fn[] = $mk('翻镇志', 'book|gazetteer');
    }
    if ($room === 'tw_photo') {
        $fn[] = $mk('买胶卷（40 元）', 'photo|film');
        $fn[] = $mk('冲洗照片（20 元）', 'photo|develop');
    }
    if ($room === 'tw_hardware') {
        $fn[] = $mk('买撬棍（90 元）', 'hw|prybar');
        $fn[] = $mk('买绳子（60 元）', 'hw|rope');
        $fn[] = $mk('买绳梯（120 元）', 'hw|ladder');
    }
    if ($room === 'tw_press')  $fn[] = $mk('把照片卖给记者（300 元/张）', 'press|sell');
    if ($room === 'tw_tailor') {
        $fn[] = $mk('做一件帆布护具（80 元）', 'tailor|vest');
        $fn[] = $mk('改工装：整洁（40 元）', 'outfit|work');
        $fn[] = $mk('改工装：便装（40 元）', 'outfit|casual');
        $fn[] = $mk('改工装：油污旧衣（40 元）', 'outfit|dirty');
    }
    if ($room === 'tw_vet')    $fn[] = $mk('买兽用镇静剂（150 元）', 'vet|sedative');
    if ($room === 'tw_mill')   $fn[] = $mk('帮工半日', 'mill|work');
    if ($room === 'tw_club') {
        $fn[] = $mk('打两圈（押 50）', 'club|gamble');
        $fn[] = $mk('听闲话', 'club|gossip');
    }
    if ($room === 'tw_watch') {
        $fn[] = $mk('看巡逻表', 'watch|roster');
        $fn[] = $mk('报案（换取夜巡）', 'watch|report');
        $fn[] = $mk('借手电（押 20）', 'watch|borrow');
    }
    if ($room === 'tw_temple') {
        $fn[] = $mk('撬锁进正厅', 'temple|break');
        $fn[] = $mk('翻族谱', 'temple|clan');
        $fn[] = $mk('看供桌', 'temple|relic');
    }
    if ($room === 'tw_townhall') {
        $fn[] = $mk('开一张证明', 'townhall|cert');
        $fn[] = $mk('查地籍图', 'townhall|land');
    }
    if ($fn) $P[] = ['title' => '这里能做的事', 'note' => '功能性动作；次数按日计。', 'items' => $fn];

    /* 镇上生活服务 */
    $svc = [];
    if ($room === 'tw_inn')     $svc[] = $act('住一晚（80 元）', 'inn_stay');
    /* 面馆（2026-09-29 补）：墙上写着价目，之前却没有任何"点面"的动作 ✗ */
    if ($room === 'tw_noodle') {
        $svc[] = $act('来一碗素面（6 元）', 'noodle', 'plain');
        $svc[] = $act('来一碗牛肉面（12 元）', 'noodle', 'beef');
        $svc[] = $act('打一壶面汤（免费）', 'noodle', 'soup');
    }
    if ($room === 'tw_bath')    $svc[] = $act('泡个澡（15 元）', 'town_bath');
    if ($room === 'tw_barber')  $svc[] = $act('理个发（10 元）', 'town_barber');
    if ($room === 'tw_repair' && crrg_drill_has_vehicle($pc)) $svc[] = $act('拾掇一下车（30 元）', 'town_repair');
    if ($svc) $P[] = ['title' => '镇上的服务', 'note' => '各一次，按日计。', 'items' => $svc];

    /* 出行：车库 ↔ 青石镇 */
    if ($room === 'garage_maint' && crrg_drill_cur_scene() === 'base') {
        $ti = [];
        if (crrg_drill_has_vehicle($pc)) $ti[] = $act('自驾 ' . $pc['vehicle'] . ' 去青石镇（免费）', 'travel', 'town:car');
        $ti[] = $act('坐基地车去青石镇（10 积分）', 'travel', 'town:credits');
        $ti[] = $act('搭车去青石镇（100 元）', 'travel', 'town:money');
        /* 2026-09-30：显示行**已摘除** → 由 `vehicle` 窗口承载（出行三选项已并入 ✓）；$ti 保留 ✓ */
    }
    if ($room === 'tw_station' && crrg_drill_cur_scene() === 'town') {
        $ti = [];
        if (crrg_drill_has_vehicle($pc)) $ti[] = $act('自驾回基地（免费）', 'travel', 'base:car');
        $ti[] = $act('坐基地车回基地（10 积分）', 'travel', 'base:credits');
        $ti[] = $act('搭车回基地（100 元）', 'travel', 'base:money');
        $P[] = ['title' => '出行 · 回基地', 'note' => '班次一天四趟；班车与搭车都在售票窗口谈。', 'items' => $ti];
    }

    /* 训练室：对抗模拟 */
    if ($room === 'b1_train' && function_exists('crrg_bt_get')) {
        $bt = crrg_bt_get();
        if ($bt && $bt['room'] === $room) {
            $P[] = ['title' => '对抗模拟 · 进行中', 'note' => '战场在下方面板，动作按钮随回合刷新。',
                    'items' => [$act('结束模拟（场地复位）', 'bt_end')]];
        } else {
            $P[] = ['title' => '对抗模拟舱', 'note' => '非致命：生命最低降到 1，不扣理智。米制坐标、敏捷固定序、完整重伤亡规则。',
                    'items' => [$act('开始模拟对抗（1 对 2）', 'bt_start')]];
        }
    }

    /* 通用 */
    /* 账户：已由 `career` 窗口接管（指令「账户」「晋升」✓） */
    return $P;
}

