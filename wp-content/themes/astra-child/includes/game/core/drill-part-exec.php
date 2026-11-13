<?php
/**
 * CRRG 演习系统 · drill.php 拆分块[exec]（2026-09-29 从 drill.php 原样搬出，仅位置变化）
 * 内容：见下方各函数。搬出后由 drill.php 在顶部 require，保证声明顺序与拆分前一致。
 */
if (!defined('ABSPATH')) exit;

function crrg_drill_exec($s, $ctx, $raw, &$pc = null, $uid = 0) {
    $cmd = trim(preg_replace('/\s+/u', ' ', (string) $raw));
    if ($pc === null) $pc = crrg_coc_default_pc();   // 无角色卡上下文（脚本测试）时用中位卡
    $pc = crrg_drill_pc_defaults($pc);
    if (function_exists('crrg_drill_items_migrate')) crrg_drill_items_migrate($pc);
    if (function_exists('crrg_travel_ensure_region')) crrg_travel_ensure_region();
    crrg_drill_set_scene($pc['scene'] ?? 'base');
    crrg_drill_apply_clearance($ctx, $pc);
    crrg_drill_sync_room($s, $pc);
    /* —— 身体状态：先按流逝的游戏时间结算，再算这次动作的消耗 —— */
    $vital_msgs = [];
    $GLOBALS['crrg_vital_pen'] = 0;
    /* 2026-10-14（bus P0）：给事件总线留一个"这是谁、在哪儿"的上下文（只读、当次请求有效） */
    $GLOBALS['crrg_ctx_uid']  = (int) ($pc['uid'] ?? 0);
    $GLOBALS['crrg_ctx_room'] = (string) ($s['room'] ?? '');
    $GLOBALS['crrg_pc_ref']   = &$pc;                 // 供检定读症状（同一套惩罚骰口径 ✓）
    if (function_exists('crrg_vitals')) {
        $vital_msgs = crrg_vitals_sync($pc);
        $GLOBALS['crrg_vital_pen'] = crrg_vitals_check_penalty($pc);
        if (preg_match('/^(睡|睡觉|打盹|躺|休息)/u', $cmd)) {
            /* 睡／休息：不扣精力，由对应动作处理 */
        } elseif (preg_match('/^(起床|醒来|状态|身体|精力|饱食|口渴|背包|装备栏|装备|穿戴|脱下|合约|值班板|宿舍|队伍|车辆|储物柜|图鉴|商店|回收|互动|人情|关系|好感|声望|笔记|身份|角色卡|现场处置|大地图|帮助|help)/u', $cmd)) {
            /* 2026-10-01：**"看"类一律不挡** ✓ —— 睡着时也该能看状态/窗口
               （原来只有几个词 ✓ → 新窗口（人情等）一律被"你正在睡"挡掉 ✗） */
            crrg_vitals_spend($pc, 'look');          // 查看类不耗，仅结算代谢
        } elseif (crrg_vitals_sleeping($pc)) {
            return [$s, ['你正在睡。要起来就说「起床」。'], $pc];
        } else {
            $vital_msgs = array_merge($vital_msgs, crrg_vitals_spend($pc, crrg_vitals_kind_of($cmd)));
        }
    }
    /* 累计在线时长（离线不计）——任务期限与库存衰减按它算 */
    if (function_exists('crrg_played_sec')) crrg_played_sec($pc);

    /* 槽位自愈（防"挂了不存在的合约"把面板卡死） */
    foreach (crrg_drill_slot_sanitize($pc) as $sx) { $guardMsgs[] = $sx; }

    /* 房间自愈（防"卡在不存在的房间"） */
    if (function_exists('crrg_drill_room_guard')) {
        foreach (crrg_drill_room_guard($s, $pc) as $gx) { $guardMsgs[] = $gx; }
    }

    /* 每日补货（基地配发物） */
    if (function_exists('crrg_drill_stock_refill')) crrg_drill_stock_refill();

    /* 行动即推进任务（房间/夜间/旗标类步骤不会因为"只是走过去"而卡住） */
    if (function_exists('crrg_drill_mission_tick') && !empty($pc['mission'])) {
        $pre = [];
        crrg_drill_mission_tick($s, $pc, $cmd, $pre);
        if ($pre) $pre_mission_lines = $pre;   // 由下面的输出合并
    }
    if (empty($pc['ready'])) {                        // 首次进入 → 先车卡
        $co = crrg_drill_create($pc, $uid, $cmd);
        if (!empty($pc['ready']) && function_exists('crrg_fate_apply_legacy')) {
            foreach (crrg_fate_apply_legacy($pc, $uid) as $x) $co[] = $x;
            crrg_drill_pc_save($uid, $pc);
        }
        return [$s, $co, $pc];
    }
    /* —— 长途行程推进（在途时其它事都做不了）—— */
    if (function_exists('crrg_travel_sync') && !empty($pc['travel'])) {
        $tv = crrg_travel_sync($pc, $s);
        if (preg_match('/^(车况|载具|状态|身体|人情|关系|好感|长途|帮助|扎营|加油|保养|说|喊|emote|动作|吃|喝|查看|看|事件|选择|搭车)/u', $cmd)) { /* 允许 */ }
        elseif (preg_match('/^(睡|睡觉|打盹|眯一会)/u', $cmd) && crrg_travel_seat($pc) === 'passenger') { /* 乘客可以睡 */ }
        elseif (preg_match('/^(睡|睡觉|打盹|眯一会)/u', $cmd)) { $tv[] = '（你在开车——停车才能睡。）'; return [$s, $tv, $pc]; }
        else {
            $tv[] = '（在路上——到地方再说别的。）';
            return [$s, $tv, $pc];
        }
    }
    /* —— 命运闸门：封存／失能／濒死 —— */
    if (function_exists('crrg_fate')) {
        if (function_exists('crrg_fate_check_expire')) { $ex = crrg_fate_check_expire($pc, $uid); if ($ex) return [$s, $ex, $pc]; }
        $f = crrg_fate($pc);
        if (!empty($f['sealed'])) {
            /* 封存的卡不能行动；但 S 级调用「轮回」属于站点级操作，允许 */
            if (function_exists('crrg_fate_is_s') && crrg_fate_is_s($uid)
                && preg_match('/^(轮回|复活|状态|身体|声望|笔记|大地图|帮助)/u', $cmd)) {
                /* 放行 */
            } else {
                $why = $f['reason'] ?: '封存';
                return [$s, ['【' . $why . '】这张卡已经封存，无法再行动。',
                             '（' . ($pc['name'] ?? '') . '的行事到此为止。要接着玩，请新建角色；'
                             . '或由 S 级权限者调动「轮回」复活这张卡。）'], $pc];
            }
        }
        if (function_exists('crrg_fate_insane') && crrg_fate_insane($pc)) {
            $left = crrg_fate_insanity_window_left($pc);
            if (!preg_match('/^(去|查看|看|状态|身体|治疗|激进治疗|放弃治疗|帮助|travel|回基地|声望|背包|笔记|装备栏|大地图|轮回|复活)/u', $cmd)) {
                return [$s, ['【失能】你现在说不出成句的话，也做不成成件的事。',
                             sprintf('（还剩 %.1f 游戏小时：回基地医疗室「激进治疗」，或「放弃治疗」。）', $left)], $pc];
            }
        }
        if ((int) $pc['hp'] <= 0 && empty($f['sealed'])
            && !preg_match('/^(状态|身体|急救|用药|吃|喝|去|回|返回|医疗|治疗|帮助|背包|装备栏)/u', $cmd)) {
            return [$s, ['（你倒在原地，动不了——先急救／回医疗室。）'], $pc];
        }
        if ((int) $pc['san'] <= 0 && empty($f['sealed']) && !crrg_fate_insane($pc)
            && !preg_match('/^(轮回|复活)/u', $cmd) && function_exists('crrg_fate_go_insane')) {
            $g = crrg_fate_go_insane($pc, $uid, '理智归零');
            if ($g) return [$s, $g, $pc];
        }
    }
    if ($cmd === '') return [$s, ['（没有输入。输入「帮助」看指令。）'], $pc];
    /* 2026-09-29：分隔符容错——全角空格、制表符、连续空格都当分隔；
       并处理"动词与人名连写"（如「细问韩雪 配方」），以及中文顿号/冒号/逗号做分隔 */
    $cmd = str_replace(['　', "\t"], ' ', (string) $cmd);
    $cmd = preg_replace('/\s+/u', ' ', trim($cmd));
    /* 「调查：X」「细问：X」这类"动词后紧跟中文标点"的写法（2026-09-29） */
    $cmd = preg_replace('/^(细问|追问|询问|问|调查|查一查|查看|看|换法|用|去|找|聊|交谈|细查|检查)[：:，,、]/u', '$1 ', $cmd);
    $cmd = preg_replace('/\s+/u', ' ', trim($cmd));
    if (preg_match('/^(细问|追问|问|聊|交谈)(?=[^\s])/u', $cmd, $m0) && !preg_match('/^(问价|问候)/u', $cmd)) {
        $cmd = $m0[1] . ' ' . mb_substr($cmd, mb_strlen($m0[1]));
    }
    $parts = explode(' ', $cmd, 2);
    $v = $parts[0];
    $arg = trim($parts[1] ?? '');
    $out = [];

    if (preg_match('/^(帮助|help|\?)$/ui', $v)) $verb = 'help';
    elseif (preg_match('/^(查看|看|look|l)$/ui', $v)) $verb = 'look';
    elseif (preg_match('/^(问|询问|打听|搭话)$/ui', $v)) { $verb = 'ask'; $arg = trim($arg); }
    elseif (preg_match('/^(穿戴|穿上|装备|戴上)$/ui', $v)) { $verb = 'wear'; $arg = trim($arg); }
    elseif (preg_match('/^(脱下|卸下|摘掉|去掉)$/ui', $v)) { $verb = 'unwear'; $arg = trim($arg); }
    elseif (preg_match('/^(装备栏|身上|穿戴栏)$/ui', $v)) { $verb = 'equiplist'; }
    elseif (preg_match('/^(交付|交付台灯|送台灯)$/ui', $v)) { $verb = 'deliver'; }
    elseif (preg_match('/^(笔记|记录|线索)$/ui', $v)) { $verb = 'notes'; $arg = trim($arg); }
    elseif (preg_match('/^(声望|口碑|名声)$/ui', $v)) { $verb = 'rep'; $arg = trim($arg); }
    elseif (preg_match('/^(大地图|区域图|外区图|地图全景)$/ui', $v)) { $verb = 'worldmap'; $arg = trim($arg); }
    elseif (preg_match('/^(睡|睡觉|打盹|躺下|休息)$/ui', $v)) { $verb = 'vitals_sleep'; $arg = trim($arg); }
    elseif (preg_match('/^(起床|醒来)$/ui', $v)) { $verb = 'vitals_wake'; }
    elseif (preg_match('/^(状态|身体|精力|饱食|口渴)$/ui', $v)) { $verb = 'vitals'; }
    elseif (preg_match('/^(分类|物品分类|图鉴)$/ui', $v)) { $verb = 'itemcat'; $arg = trim($arg); }
    elseif (preg_match('/^(鉴定|送检|看看这东西)$/ui', $v)) { $verb = 'appraise'; $arg = trim($arg); }
    elseif (preg_match('/^(封门|贴封条|封存)$/ui', $v)) { $verb = 'sealroom'; }
    elseif (preg_match('/^(调查|查|翻)$/ui', $v)) { $verb = 'survey'; $arg = trim($arg); }
    elseif (preg_match('/^(取件|取核心件|拿核心件)$/ui', $v)) { $verb = 'takekey'; }
    elseif (preg_match('/^(处置|了结|结案)$/ui', $v)) { $verb = 'resolve'; $arg = trim($arg); }
    elseif (preg_match('/^(交涉|谈|谈谈)$/ui', $v)) { $verb = 'parley'; $arg = trim($arg); }
    elseif (preg_match('/^(细问|追问)$/ui', $v)) { $verb = 'ask2'; $arg = trim($arg); }
    elseif (preg_match('/^(决断|定案)$/ui', $v)) { $verb = 'stagechoice'; $arg = trim($arg); }
    elseif (preg_match('/^(守夜|蹲守|夜守)$/ui', $v)) { $verb = 'nightwatch'; }
    elseif (preg_match('/^(护料|看料|保住料)$/ui', $v)) { $verb = 'guard'; $arg = trim($arg); }
    elseif (preg_match('/^(事故|事故处置)$/ui', $v)) { $verb = 'accident'; $arg = trim($arg); }
    elseif (preg_match('/^(工艺|方案)$/ui', $v)) { $verb = 'craft'; $arg = trim($arg); }
    elseif (preg_match('/^(交接|交材料|登记材料)$/ui', $v)) { $verb = 'handover'; }
    elseif (preg_match('/^(开工|出货|做封条)$/ui', $v)) { $verb = 'seal5run'; }
    elseif (preg_match('/^(换法|另法|换个法子)$/ui', $v)) { $verb = 'sealpath'; $arg = trim($arg); }
    /* 2026-10-01：**正办**入口 —— 「处理 <节点>」走该节点的首选过法 ✓（玩家反馈：只有换法没有正办 ✗） */
    elseif (preg_match('/^(处理|办|解决|处置|正经办)$/ui', $v)) { $verb = 'sealhandle'; $arg = trim($arg); }
    /* 2026-10-01：玩家互动 —— 留话（窗口里提示过这个写法 ✓，但当时**忘挂指令** ✗ → 回"不认识的指令"✓） */
    elseif (preg_match('/^(留话|留言|捎话)$/ui', $v)) { $verb = 'socnote'; $arg = trim($arg); }
    /* 2026-10-01：人情单独一个指令与窗口 ✓（原混在「身体／状态」里 ✗ 委员长要求拆出） */
    elseif (preg_match('/^(人情|关系|好感)$/ui', $v)) { $verb = 'favor'; $arg = trim($arg); }
    /* 2026-10-01：宿舍独立化 ✓ 自己的（私人）／拜访别人的（玩家或 NPC ✓） */
    elseif (preg_match('/^(帮忙|搭把手|帮个忙|搭手)$/ui', $v)) { $verb = 'helpnpc'; $arg = trim($arg); }
    /* 2026-10-01：**上级入口**——"可查的账"（委员长：查账查串了 ✓） */
    elseif (preg_match('/^(查账|查旧账|账本|对账|柜台)$/ui', $v)) { $verb = 'ledgers'; $arg = trim($arg); }
    elseif (preg_match('/^(进宿舍|回宿舍|回房)$/ui', $v)) { $verb = 'dormin'; $arg = ''; }
    elseif (preg_match('/^(拜访|敲门|探访)$/ui', $v)) { $verb = 'dormvisit'; $arg = trim($arg); }
    elseif (preg_match('/^(出去|出宿舍|离开宿舍|出门)$/ui', $v)) { $verb = 'dormout'; $arg = ''; }
    elseif (preg_match('/^(贴封|贴封条到C2|重贴)$/ui', $v)) { $verb = 'seal5seal'; }
    elseif (preg_match('/^(结案报告|封条结案)$/ui', $v)) { $verb = 'seal5close'; }
    elseif (preg_match('/^(处置中毒|救人)$/ui', $v)) { $verb = 'poison'; $arg = trim($arg); }
    elseif (preg_match('/^(灌水|接水|装水|打水)$/ui', $v)) { $verb = 'fill'; }
    elseif (preg_match('/^(激进治疗|治疗)$/ui', $v)) { $verb = 'fate_radical_treat'; }
    elseif (preg_match('/^(放弃治疗|放弃)$/ui', $v)) { $verb = 'giveup'; }
    elseif (preg_match('/^(轮回|复活)$/ui', $v)) { $verb = 'samsara'; $arg = trim($arg); }
    elseif (preg_match('/^(长途|出车|开车去|前往)$/ui', $v)) { $verb = 'travel_go'; $arg = trim($arg); }
    elseif (preg_match('/^(手机|掏手机|开手机)$/ui', $v)) { $verb = 'phone'; }
    elseif (preg_match('/^(打电话|呼叫|拨)$/ui', $v)) { $verb = 'call'; }
    elseif (preg_match('/^(远程报告|发报告|远程交报告)$/ui', $v)) { $verb = 'phone_report'; }
    elseif (preg_match('/^(通话记录|电话记录)$/ui', $v)) { $verb = 'phonelog'; }
    elseif (preg_match('/^(治疗|心理分析|谈谈|看心理)$/ui', $v)) { $verb = 'therapy'; }
    elseif (preg_match('/^(休养|静养|躺几天)$/ui', $v)) { $verb = 'rest_san'; }
    elseif (preg_match('/^(理智|神志|精神状态)$/ui', $v)) { $verb = 'mind'; }
    elseif (preg_match('/^(手机|手机屏)$/ui', $v)) { $verb = 'phone'; }
    elseif (preg_match('/^(报到|签到)$/ui', $v)) { $verb = 'roll_checkin'; }
    elseif (preg_match('/^(点名|点名册)$/ui', $v)) { $verb = 'roll_roster'; }
    elseif (preg_match('/^(开会|会议|案情会)$/ui', $v)) { $verb = 'meeting'; }
    elseif (preg_match('/^(训练|锻炼|练)$/ui', $v)) { $verb = $arg === '' ? 'train_menu' : 'train_do'; }
    elseif (preg_match('/^(做菜|下厨|做饭)$/ui', $v)) { $verb = $arg === '' ? 'cook_menu' : 'cook_do'; }
    elseif (preg_match('/^(菜谱|会做什么)$/ui', $v)) { $verb = 'cook_menu'; }
    elseif (preg_match('/^(档案|查档|翻档)$/ui', $v)) { $verb = 'arch_search'; }
    elseif (preg_match('/^(勘查|察看一下|四下了看)$/ui', $v)) { $verb = 'survey_node'; }
    elseif (preg_match('/^(调阅|细看)$/ui', $v)) { $verb = 'arch_open'; }
    elseif (preg_match('/^(周报|交周报)$/ui', $v)) { $verb = 'weekly'; }
    elseif (preg_match('/^(群聊|群里)$/ui', $v)) { $verb = 'ph_group'; }
    elseif (preg_match('/^发言$/ui', $v)) { $verb = 'ph_say'; }
    elseif (preg_match('/^(动态|朋友圈)$/ui', $v)) { $verb = 'ph_feed'; }
    elseif (preg_match('/^点赞$/ui', $v)) { $verb = 'ph_like'; }
    elseif (preg_match('/^(论坛|镇上论坛)$/ui', $v)) { $verb = 'ph_forum'; }
    elseif (preg_match('/^(备忘录|备忘)$/ui', $v)) { $verb = 'ph_memo'; }
    elseif (preg_match('/^(记|记一笔)$/ui', $v)) { $verb = 'ph_memo_add'; }
    elseif (preg_match('/^(收音机|广播)$/ui', $v)) { $verb = 'ph_radio'; }
    elseif (preg_match('/^(翻牌|小游戏)$/ui', $v)) { $verb = 'ph_game'; }
    elseif (preg_match('/^(时间|几点)$/ui', $v)) { $verb = 'ph_clock'; }
    elseif (preg_match('/^(装备)$/ui', $v)) { $verb = 'gear'; }
    elseif (preg_match('/^(保养|维护)$/ui', $v)) { $verb = 'gear_maint'; }
    elseif (preg_match('/^(关系|关系网)$/ui', $v)) { $verb = 'npc_net'; }
    elseif (preg_match('/^(分工|指派)$/ui', $v)) { $verb = 'assign'; }
    elseif (preg_match('/^(提示档|帮助档)$/ui', $v)) { $verb = 'hint_level'; }
    elseif (preg_match('/^(提示|给点提示|卡住了)$/ui', $v)) { $verb = 'hint_now'; }
    elseif (preg_match('/^(痕迹|现场)$/ui', $v)) { $verb = 'trace'; }
    elseif (preg_match('/^(清理|善后)$/ui', $v)) { $verb = 'trace_clean'; }
    elseif (preg_match('/^(任务状态|调试任务)$/ui', $v)) { $verb = 'task_state'; }
    elseif (preg_match('/^(推论|推断|推一下)$/ui', $v)) { $verb = 'infer'; }
    elseif (preg_match('/^(秘术|秘法|法术|方术|术目)$/ui', $v)) { $verb = 'miracle'; $arg = trim($arg); }
    elseif (preg_match('/^(学秘术|修秘术|习秘术|学法术)$/ui', $v)) { $verb = 'miracle_learn'; $arg = trim($arg); }
    elseif (preg_match('/^(施术|施法|行术|念术|用秘术)$/ui', $v)) { $verb = 'miracle_cast'; $arg = trim($arg); }
    elseif (preg_match('/^(收容|交接|操作)$/ui', $v)) { $verb = 'contain'; }
    elseif (preg_match('/^(小队|带人)$/ui', $v)) { $verb = 'squad'; }
    elseif (preg_match('/^(日程|今天|今日|日历)$/ui', $v)) { $verb = 'calendar'; }
    elseif (preg_match('/^(车况|载具|里程)$/ui', $v)) { $verb = 'veh'; }
    elseif (preg_match('/^(加油|加满)$/ui', $v)) { $verb = 'travel_refuel'; $arg = trim($arg); }
    elseif (preg_match('/^(保养|维护)$/ui', $v)) { $verb = 'travel_service'; }
    elseif (preg_match('/^(大修)$/ui', $v)) { $verb = 'travel_service_major'; }
    elseif (preg_match('/^(扎营|露营|宿营)$/ui', $v)) { $verb = 'travel_camp'; $arg = trim($arg); }
    elseif (preg_match('/^(治伤|包扎|急救|处理伤口)$/ui', $v)) { $verb = 'vitals_heal'; $arg = trim($arg); }
    elseif (preg_match('/^(露营点|营地)$/ui', $v)) { $verb = 'campspots'; }
    elseif (preg_match('/^(搭车|蹭车|搭个车)$/ui', $v)) { $verb = 'ride'; $arg = trim($arg); }
    elseif (preg_match('/^(事件|选择)$/ui', $v)) { $verb = 'tve'; $arg = trim($arg); }
    elseif (preg_match('/^(找回|恢复物品)$/ui', $v)) { $verb = 'recover'; $arg = trim($arg); }
    elseif (preg_match('/^(存档记录|快照)$/ui', $v)) { $verb = 'snaplog'; }
    elseif (preg_match('/^(翻找|查阅|推门|敲门|端详|细看)$/ui', $v)) { $verb = 'look'; $arg = trim($v . ' ' . $arg); }
    elseif (preg_match('/^(去|走|前往|移动|go)$/ui', $v)) $verb = 'go';
    elseif (preg_match('/^(地图|map)$/ui', $v)) $verb = 'map';
    elseif (preg_match('/^(身份|状态|status|我)$/ui', $v)) $verb = 'status';
    elseif (preg_match('/^(重置|重开|reset)$/ui', $v)) $verb = 'reset';
    elseif (preg_match('/^(领任务)$/ui', $v)) $verb = 'brief';
    elseif (preg_match('/^(取|拿|take|取防护服)$/ui', $v)) { $verb = 'take'; $arg = $arg ?: '防护服'; }
    elseif (preg_match('/^(刷卡|开门|过闸)$/ui', $v)) $verb = 'swipe';
    elseif (preg_match('/^(取样|记录|sample)$/ui', $v)) $verb = 'key_sample';
    elseif (preg_match('/^(交报告|报告|report)$/ui', $v)) $verb = 'report';
    elseif (preg_match('/^(测试|认知测试)$/ui', $v)) $verb = 'test';
    elseif (preg_match('/^(激活|绞碎)$/ui', $v)) $verb = 'activate';
    elseif (preg_match('/^(坐下|坐|歇|歇会儿)$/ui', $v)) $verb = 'sit';
    elseif (preg_match('/^(站起|起来|起身)$/ui', $v)) $verb = 'stand';
    elseif (preg_match('/^(睡觉|睡|躺下|躺)$/ui', $v)) $verb = 'vitals_sleep';
    elseif (preg_match('/^(醒来|醒|起床)$/ui', $v)) $verb = 'vitals_wake';
    elseif (preg_match('/^(吃)$/ui', $v)) $verb = 'vitals_eat';
    elseif (preg_match('/^(喝)$/ui', $v)) $verb = 'vitals_drink';
    elseif (preg_match('/^(说|喊|叫)$/ui', $v)) $verb = 'say';
    elseif (preg_match('/^(表情|emote)$/ui', $v)) $verb = 'emote';
    elseif (preg_match('/^(动作|act)$/ui', $v)) $verb = 'act';
    elseif (preg_match('/^(检定|掷|roll)$/ui', $v)) $verb = 'roll';
    elseif (preg_match('/^(背包|物品|身上)$/ui', $v)) $verb = 'bag';
    elseif (preg_match('/^(装备|穿|穿戴)$/ui', $v)) { $verb = 'equip'; $arg = $arg ?: '防护服'; }
    elseif (preg_match('/^(使用|用)$/ui', $v)) $verb = 'use';
    elseif (preg_match('/^(放下|丢弃|扔)$/ui', $v)) $verb = 'drop';
    elseif (preg_match('/^(存入|存放)$/ui', $v)) $verb = 'store';
    elseif (preg_match('/^(取出|拿回)$/ui', $v)) $verb = 'fetch';
    elseif (preg_match('/^(搜索|搜)$/ui', $v)) $verb = 'search';
    elseif (preg_match('/^(角色卡|卡|属性)$/ui', $v)) $verb = 'card';
    elseif (preg_match('/^(开始模拟|模拟对抗|开打)$/ui', $v)) $verb = 'simstart';
    elseif (preg_match('/^(调阅)$/ui', $v)) { $verb = 'archive'; $arg = $arg ?: '卷宗'; }
    elseif (preg_match('/^(钱包|账户|钱)$/ui', $v)) $verb = 'wallet';
    elseif (preg_match('/^(任务|合约)$/ui', $v)) $verb = 'missions';
    elseif (preg_match('/^(出发|出勤|进入副本)$/ui', $v)) $verb = 'depart';
    elseif (preg_match('/^(撤离|返回|回基地)$/ui', $v)) $verb = 'retreat';
    elseif (preg_match('/^(交任务|结算)$/ui', $v)) $verb = 'settle';
    elseif (preg_match('/^(商店|购买|网购)$/ui', $v)) { $verb = 'shop'; if ($v === '网购') $arg = '网购'; }
    elseif (preg_match('/^(宿舍|我的房间)$/ui', $v)) $verb = 'dorm';
    /* 窗口化（2026-09-30）：补三个此前没有的入口 —— 值班板／队伍／车辆 */
    elseif (preg_match('/^(值班板|任务板|出勤板)$/ui', $v)) $verb = 'board';
    elseif (preg_match('/^(队伍|组队|队友)$/ui', $v)) $verb = 'team';
    elseif (preg_match('/^(车辆|我的车|车况)$/ui', $v)) $verb = 'vehicle';
    elseif (preg_match('/^(储物柜|柜子|仓库柜)$/ui', $v)) $verb = 'stash';
    elseif (preg_match('/^(回收|卖出|卖掉|收购)$/ui', $v)) $verb = 'sell';
    /* 2026-10-01 采纳玩家建议：**二级入口**——「互动」列出当下能做什么（每条都可点 ✓） */
    elseif (preg_match('/^(互动|这里|能做什么|做什么|附近)$/ui', $v)) $verb = 'here';
    elseif (preg_match('/^拜访\s*(\S+)$/ui', $cmd, $m2)) { $verb = 'visit'; $arg = $m2[1]; }
    elseif (preg_match('/^(晋升|换权限)$/ui', $v)) $verb = 'promote';
    elseif (preg_match('/^接受\s*(\S+)$/ui', $cmd, $m2)) { $verb = 'accept'; $arg = $m2[1]; }
    elseif (preg_match('/^布置\s*(.+)$/ui', $cmd, $m2)) { $verb = 'decor'; $arg = trim($m2[1]); }
    elseif (preg_match('/^买\s+(.+)$/ui', $cmd, $m2)) { $verb = 'buy'; $arg = trim($m2[1]); }
    else $verb = 'unknown';

    $rooms = crrg_drill_rooms();          // 场景同步之后才取，避免拿到旧场景的房间表
    if (!isset($rooms[$s['room']])) { crrg_drill_sync_room($s, $pc); $rooms = crrg_drill_rooms(); }
    $rm = $rooms[$s['room']] ?? ['t' => '未知地点', 'f' => '—', 'c' => 1, 'd' => '（数据缺失）', 'x' => []];


    /* ── 窗口化：动词 → 窗口（**第一处**）──
       注意：有些 case 会**提前 return** ✗，所以函数末尾那处轮不到它们 →
       这里也设一次（双保险 ✓；后面若被 mission_tick 重写，末尾那处会再补一次 ✓） */
    $uiWinMap = ['bag' => 'bag', 'vitals' => 'vitals', 'equip' => 'equip', 'equiplist' => 'equip',
                 'itemcat' => 'codex', 'missions' => 'missions', 'career' => 'career',
                 'board' => 'board', 'team' => 'team', 'vehicle' => 'vehicle',
                 'dorm' => 'dorm', 'shop' => 'shop', 'stash' => 'stash',
                 'rep' => 'career', 'wallet' => 'career', 'worldmap' => 'map', 'sell' => 'sell', 'here' => 'here'];
    if (function_exists('crrg_ui_request_open') && isset($uiWinMap[$verb])) crrg_ui_request_open($s, $uiWinMap[$verb]);

    switch ($verb) {
        case 'help':
            $out = function_exists('crrg_help_lines') ? crrg_help_lines() : ['（帮助未加载）'];
            break;

        case 'wear':
            if ($arg === '') {
                /* 2026-10-01：不带参数 → **直接开「装备」窗口** ✓（此前只回一句提示 ✗，玩家不知道怎么开 ✓） */
                if (function_exists('crrg_ui_request_open')) crrg_ui_request_open($s, 'equip');
                $out = ['（「装备」窗口已打开：按部位列着能穿的东西。）', '要直接穿某件也可以：「穿戴 防护服」。'];
                break;
            }
            $out = crrg_equip_wear($pc, $arg);
            break;

        case 'unwear':
            if ($arg === '') {
                if (function_exists('crrg_ui_request_open')) crrg_ui_request_open($s, 'equip');
                $out = ['（「装备」窗口已打开：穿着的都能从这儿脱。）', '要直接脱也可以：「脱下 右手」。'];
                break;
            }
            $out = crrg_equip_take_off($pc, $arg);
            break;

        case 'recover':   // 从快照里找回丢失的物件
            $log = get_user_meta($uid, 'crrg_drill_pc_log', true);
            if (!is_array($log) || !$log) { $out = ['没有可用的存档快照。']; break; }
            [$cur, $equip] = [crrg_drill_bag_counts($pc), crrg_equip_of($pc)];
            $have = $cur;
            foreach ($equip as $sl => $id) if ($id) $have[$id] = (int) ($have[$id] ?? 0) + 1;
            $best = end($log);
            $want = [];
            foreach ((array) $best['items'] as $it) $want[$it] = (int) ($want[$it] ?? 0) + 1;
            foreach ((array) $best['equip'] as $id) if ($id) $want[$id] = (int) ($want[$id] ?? 0) + 1;
            $add = []; $list = [];
            foreach ($want as $id => $n) {
                $d = $n - (int) ($have[$id] ?? 0);
                if ($d <= 0) continue;
                crrg_drill_bag_add_n($pc, $id, $d);
                $add[$id] = $d; $list[] = crrg_drill_item_name($id) . ($d > 1 ? '×' . $d : '');
            }
            $out = $list
                ? array_merge(['〔物品找回〕对照最近一次快照，补回：' . implode('、', $list)], ['（装备栏需要的话自己再穿一次。）'])
                : ['对照最近一次快照，没有缺的东西。'];
            break;

        case 'snaplog':
            $log = get_user_meta($uid, 'crrg_drill_pc_log', true);
            if (!is_array($log) || !$log) { $out = ['还没有存档快照。']; break; }
            $out = ['〔存档快照（最近 ' . count($log) . ' 份）〕'];
            foreach (array_slice($log, -5) as $r) {
                $ids = [];
                foreach ((array) $r['items'] as $i) $ids[] = crrg_drill_item_name($i);
                $out[] = date('H:i', (int) $r['t']) . '　' . (count($ids) . ' 件：' . implode('、', array_slice($ids, 0, 6))
                        . (count($ids) > 6 ? '…' : ''));
            }
            break;

        case 'vitals_sleep':
            if (!function_exists('crrg_vitals_sleep')) { $out = ['身体系统未加载。']; break; }
            if (function_exists('crrg_travel_in_transit') && crrg_travel_in_transit($pc)) {      // 在途：乘客可睡到抵达
                if (crrg_travel_seat($pc) !== 'passenger') { $out = ['（你在开车——停车才能睡。）']; break; }
                $out = crrg_travel_passenger_sleep($pc);
                break;
            }
            if (crrg_vitals_sleeping($pc)) { $out = ['你已经在睡了。要说「起床」才起得来。']; break; }
            $hrs = 8;
            if (preg_match('/(\d+)\s*(小时|h)/u', $arg, $m)) $hrs = (int) $m[1];
            elseif (preg_match('/^\s*(\d+)\s*$/', $arg, $m)) $hrs = (int) $m[1];      // 「睡觉 2」＝ 2 游戏小时
            elseif (mb_strpos($arg, '打盹') !== false || mb_strpos($v, '打盹') !== false) $hrs = 2;
            $out = crrg_vitals_sleep($pc, $hrs);
            break;

        case 'vitals_wake':
            $out = function_exists('crrg_vitals_wake') ? crrg_vitals_wake($pc) : ['没事。'];
            break;

        case 'travel_go':
            if ($arg === '') {
                $out = ['〔可去的地方〕'];
                foreach (crrg_travel_destinations() as $id => $n2) {
                    if ($id === 'base') continue;
                    $open = crrg_travel_node_open($id);
                    $rt = crrg_travel_route_of($pc, $id);
                    $cls = $rt['cls'] ?? 'flat';
                    $est = crrg_travel_estimate(crrg_travel_mode($pc), (int) ($rt['km'] ?? $n2['road_km']), $cls, $pc);
                    $out[] = sprintf('　%s（%s）%d km ｜ %s ｜ 约 %.1f 游戏小时 ｜ 油 %.1f 升 ｜ %s',
                        $n2['n'], $n2['t'], (int) ($rt['km'] ?? $n2['road_km']),
                        $cls === 'hw' ? '高速（' . ($rt['via'] ?? '') . '）' : ($cls === 'dirt' ? '土路' : '平路'),
                        $est['hours'], $est['fuel'], $open ? '可去' : '（未开通）');
                }
                $out[] = '（用法：「长途 青石镇」。）';
                break;
            }
            $hit = '';
            foreach (crrg_travel_destinations() as $id => $n2) if ($id !== 'base' && (mb_strpos($n2['n'], $arg) !== false || $arg === $id)) { $hit = $id; break; }
            if ($hit === '') { $out = ['地图上没这个地名（打「长途」看清单）。']; break; }
            if (!crrg_travel_node_open($hit)) {
                $n2 = crrg_travel_destinations()[$hit];
                $cls = crrg_travel_road_class($n2['via'] ?? '', (int) ($n2['road_km'] ?? 0), (int) ($n2['km'] ?? 0));
                $est = crrg_travel_estimate(crrg_travel_mode($pc), (int) $n2['road_km'], $cls, $pc);
                $out = [sprintf('%s 还没开通（路况 %s，%d km，约 %.1f 游戏小时、%.1f 升油）。',
                        $n2['n'], $cls === 'dirt' ? '土路' : '平路', (int) $n2['road_km'], $est['hours'], $est['fuel']),
                        '（等后续更新开通；现在能走的，是地图上标着「已通」「部分通」的地方。）'];
                break;
            }
            $out = array_merge($out, crrg_travel_start($pc, $hit));
            break;

        case 'ride':
            if ($arg === '') { $out = ['（用法：「搭车 <账号ID或名字>」。）']; break; }
            $host_uid = 0;
            if (preg_match('/^\d+$/', $arg)) $host_uid = (int) $arg;
            else { $u = get_user_by('display_name', $arg) ?: get_user_by('login', $arg); if ($u) $host_uid = (int) $u->ID; }
            if (!$host_uid) { $out = ['（没找到这个人。）']; break; }
            $out = crrg_travel_ride_with($pc, $uid, $host_uid);
            break;

        case 'tve':
            if (empty($pc['travel']['ev'])) { $out = ['（路上没什么事。）']; break; }
            if ($arg === '') { $out = crrg_travel_event_show($pc['travel']['ev']); break; }
            $idx = (int) preg_replace('/\D/', '', $arg);
            if (!$idx) { $out = ['（用法：「事件 1」。）']; break; }
            $out = crrg_travel_event_choose($pc, $s, $pc['travel']['ev'], $idx);
            break;

        case 'veh':
            $out = array_merge(['〔载具与行程〕'], crrg_travel_status_lines($pc));
            break;

        case 'travel_refuel':
            $l = 0;
            if (preg_match('/(\d+)/', $arg, $mm)) $l = (int) $mm[1];
            $out = crrg_travel_refuel($pc, $s['room'], $l);
            break;

        case 'travel_service':
            $out = crrg_travel_service($pc, $s['room'], false);
            break;

        case 'travel_service_major':
            $out = crrg_travel_service($pc, $s['room'], true);
            break;

        case 'travel_camp':
            $node = $arg !== '' ? $arg : crrg_travel_current_node($s);
            $out = crrg_travel_camp($pc, $node, 6);
            break;

        case 'campspots':
            $out = ['〔可扎营的地方〕'];
            foreach (crrg_travel_camp_spots() as $id => $nm) $out[] = '　' . $nm;
            $out[] = '（要在那里、且有帐篷。）';
            break;

        case 'fate_radical_treat':
            if ($s['room'] !== 'b1_med') { $out = ['激进治疗只能在基地医疗室做。']; break; }
            $out = function_exists('crrg_fate_treat') ? crrg_fate_treat($pc, $uid) : ['未加载。'];
            break;

        case 'giveup':
            if ($s['room'] !== 'b1_med') { $out = ['放弃治疗要在基地医疗室办手续。']; break; }
            $out = function_exists('crrg_fate_give_up') ? crrg_fate_give_up($pc, $uid) : ['未加载。'];
            break;

        case 'samsara':
            if (!function_exists('crrg_fate_reincarnate')) { $out = ['命运模块未加载。']; break; }
            if (!crrg_fate_is_s($uid)) { $out = ['只有 S 级权限能调动「轮回」。']; break; }
            $list = crrg_fate_sealed_list();
            if ($arg === '') {
                $out = ['〔轮回 · 可复活的封存卡〕'];
                foreach ($list as $tid => $x) {
                    $out[] = sprintf('　#%d %s ｜ %s ｜ %s', $tid, $x['name'], $x['reason'], $x['cause']);
                }
                if (!$list) $out[] = '　（没有封存的卡。）';
                $out[] = '（用法：「轮回 账号ID」。代价：你降为 A 级、各地点声望减半。）';
                break;
            }
            $tid = (int) preg_replace('/\D/', '', $arg);
            if (!$tid || !isset($list[$tid])) { $out = ['（没找到那张封存卡——用「轮回」先看列表。）']; break; }
            $out = crrg_fate_reincarnate($pc, $uid, $tid);
            break;

        case 'vitals_heal':
            $it = '';
            if (mb_strpos($arg, '急救') !== false) $it = 'med_firstaid';
            elseif (mb_strpos($arg, '绷带') !== false) $it = 'med_bandage';
            $out = function_exists('crrg_vitals_treat') ? crrg_vitals_heal_wound($pc, $it) : ['未加载。'];
            break;

        case 'fill':
            $out = function_exists('crrg_vitals_fill') ? crrg_vitals_fill($pc, $s['room']) : ['未加载。'];
            break;

        case 'survey':
            /* 2026-10-22：**任务调查点优先** ✓（新引擎的 ents → text/clues） */
            $rTask = function_exists('crrg_task_survey') ? crrg_task_survey($s, $pc, $arg) : null;
            if ($rTask !== null) { $out = $rTask; break; }
            /* 2026-10-01（委员长要求 ✓）：**别再让玩家敲"换法"** ——
               「调查 <节点>」直接把「现场处置」窗弹出来（列出全部可行法子 ✓ 点一条即办 ✓） */
            if (function_exists('crrg_seal_path_node')) {
                $nodeWin = (string) crrg_seal_path_node($arg, $s['room'] ?? '');
                if ($nodeWin !== '' && function_exists('crrg_seal_node_room') && crrg_seal_node_room($nodeWin) === ($s['room'] ?? '')) {
                    $pth = function_exists('crrg_seal_paths_full') ? (array) (crrg_seal_paths_full()[$nodeWin] ?? []) : [];
                    if ($pth) $GLOBALS['crrg_ui_open'] = 'paths:' . $nodeWin;
                }
            }
            /* 直接对「别的法子」里的节点用「调查」也认（2026-09-29）：
               这些节点本来只有换法，玩家打「调查 当年事故」会得到空回复 → 误以为"查不了" */
            if (function_exists('crrg_seal_path_node') && function_exists('crrg_seal_try')) {
                $nodeTry = crrg_seal_path_node($arg, $s['room'] ?? '');
                if ($nodeTry !== '' && crrg_seal_node_room($nodeTry) === ($s['room'] ?? '')
                    && !crrg_seal_path_done($s, $nodeTry)) {
                    $rTry = crrg_seal_try($s, $pc, $nodeTry, '');
                    if (!empty($rTry)) {
                        $out = array_merge(['（这条没有直接查法——按别的法子来：）'], (array) $rTry);
                        if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'survey', $out);
                        break;
                    }
                }
            }
            /* 命中的是"别的法子"节点：没做过 → 走换法；做过了 → 明说（2026-09-29） */
            if (function_exists('crrg_seal_path_node') && function_exists('crrg_seal_done_msg')) {
                $nodeHit = crrg_seal_path_node($arg, $s['room'] ?? '');
                if ($nodeHit !== '' && crrg_seal_node_room($nodeHit) === ($s['room'] ?? '')
                    && crrg_seal_path_done($s, $nodeHit)) {
                    $out = crrg_seal_done_msg($s, $pc, $s['room'] ?? '', crrg_seal_path_done($s, $nodeHit), $arg);
                    if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'survey', $out);
                    break;
                }
            }
            /* 先看：这个词在这间能不能对上（2026-09-29）——对不上就列出这间能办的，
               免得玩家照着面板上的长描述打字，只得到"这里没有能翻的东西" */
            if (function_exists('crrg_seal_paths_full')) {
                $roomNow = $s['room'] ?? '';
                $scopeF = [];
                foreach ([$pc['mission'] ?? '', $pc['mission2']['id'] ?? ''] as $midS) {
                    $mmS = crrg_drill_mission_any($midS);
                    foreach ((array) ($mmS['steps'] ?? []) as $stS) if (!empty($stS['flag'])) $scopeF[$stS['flag']] = 1;
                }
                $hereNodes = [];
                foreach (crrg_seal_paths_full() as $ndS => $psS) {
                    if (crrg_seal_node_room($ndS) !== $roomNow) continue;
                    $flS = '';
                    foreach ($psS as $pS) { $flS = (string) ($pS['flag'] ?? ''); break; }
                    if ($scopeF && $flS !== '' && !isset($scopeF[$flS]) && !crrg_seal_path_done($s, $ndS)) continue;
                    if (crrg_seal_path_done($s, $ndS)) continue;
                    $hereNodes[] = $ndS;
                }
                $hereNodes = function_exists('crrg_seal_nodes_dedup') ? crrg_seal_nodes_dedup($hereNodes) : $hereNodes;
                $argT = trim((string) $arg);
                $matchHere = false;
                foreach ($hereNodes as $ndS) if ($argT !== '' && (mb_strpos($ndS, $argT) !== false || mb_strpos($argT, $ndS) !== false)) { $matchHere = true; break; }
                if ($hereNodes && !$matchHere) {
                    $out = ['（「' . $argT . '」这间对不上——照面板上的描述打字也不行，得用关键词。）',
                            '（这间能办的：' . implode('、', $hereNodes) . '——用「调查 <关键词>」。）'];
                    break;
                }
            }
            /* 只有《封条》链上的任务在手时才开放这些调查点 */
            if (function_exists('crrg_seal_path_node') && crrg_seal_path_node($arg) !== '' && function_exists('crrg_seal_chain_in_hand') && !crrg_seal_chain_in_hand($pc)) {
                $out = ['（这条路子不是现在能碰的——先去值班板接《封条》那条线。）'];
                break;
            }
            $r = function_exists('crrg_seal3_survey') ? crrg_seal3_survey($s, $pc, $arg) : null;
            if ($r === null && function_exists('crrg_seal2_survey')) $r = crrg_seal2_survey($s, $pc, $arg);
            if ($r === null && function_exists('crrg_seal_survey')) $r = crrg_seal_survey($s, $pc, $arg);
            if ($r === null && function_exists('crrg_mid_gap_survey')) $r = crrg_mid_gap_survey($s, $pc, $arg);
            if ($r === null) {
                $out = ['（这里没有能翻的东西。试试对着账本／排班表／拆件记录「调查」。）'];
            } else { $out = $r; if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'survey', $out); }
            break;

        case 'takekey':
            $out = function_exists('crrg_mid_gap_take_key') ? crrg_mid_gap_take_key($s, $pc) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'takekey', $out);
            break;

        case 'ask2':   /* 《封条》的 NPC 问答：问 周德海 就地取材 */
            /* 2026-09-29 修：按作息表校验"人在不在场"——不能对着空房间问人 */
            if (function_exists('crrg_npc_at_room') && function_exists('crrg_npc_roster')) {
                $whoAsk = trim(explode(' ', trim($arg))[0] ?? '');
                $whoAsk = str_replace(['白大夫', '老周', '小韩'], ['白淑英', '周德海', '韩雪'], $whoAsk);
                $npcIdx = [];
                foreach ((array) crrg_npc_roster() as $nk => $nv) {
                    if (!is_array($nv)) continue;
                    $nmv = (string) ($nv['n'] ?? '');
                    if ($nmv !== '') $npcIdx[$nmv] = $nv;
                    foreach ((array) ($nv['alias'] ?? []) as $av) if (is_string($av) && $av !== '') $npcIdx[$av] = $nv;
                }
                if ($whoAsk !== '' && isset($npcIdx[$whoAsk])) {
                    /* 统一"谁在场"：基地按作息＋镇上固定（2026-09-29） */
                    $npcHere = [];
                    if (function_exists('crrg_npc_here')) { foreach ((array) crrg_npc_here($s['room'] ?? '') as $nmH => $dH) $npcHere[] = (string) $nmH; }
                    else { foreach ((array) crrg_npc_at_room($s['room'] ?? '') as $nd) $npcHere[] = (string) ($nd['n'] ?? ''); }
                    $npcReal = (string) ($npcIdx[$whoAsk]['n'] ?? $whoAsk);
                    if (!in_array($npcReal, $npcHere, true)) {
                        $phQ = function_exists('crrg_npc_phase') ? crrg_npc_phase() : '';
                        $whereQ = (string) ($npcIdx[$whoAsk]['sched'][$phQ] ?? '');
                        $wnQ = $whereQ !== '' ? crrg_npc_room_name($whereQ) : '（不在基地）';
                        $hintQ = $whereQ !== '' ? ('怎么走：' . crrg_drill_route_hint($s['room'] ?? '', $whereQ)) : '';
                        $out = [sprintf('（%s现在不在%s——这个点（%s）通常在%s。）', $npcReal,
                                crrg_drill_rooms()[$s['room'] ?? '']['t'] ?? '这儿', $phQ, $wnQ)];
                        if ($hintQ) $out[] = $hintQ;
                        break;
                    }
                }
            }
            $argN = preg_replace('/[：:，,、\/]+/u', ' ', (string) $arg);       // 中文标点当分隔（2026-09-29）
            $argN = trim(preg_replace('/\s+/u', ' ', $argN));
            $parts = preg_split('/\s+/', $argN, 2);
            $rA = null;
            foreach (['crrg_seal3_npc_ask', 'crrg_seal2_npc_ask', 'crrg_seal_npc_ask'] as $fnA) {
                if ($rA === null && function_exists($fnA)) $rA = $fnA($s, $pc, $parts[0] ?? '', $parts[1] ?? '');
            }
            $out = ($rA === null) ? ['（没有这个话题。）'] : $rA;
            if ($rA !== null && function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'ask', $out);
            break;

        case 'guard':
            $out = function_exists('crrg_seal4_rob') ? crrg_seal4_rob($s, $pc, $arg) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'guard', $out);
            break;

        case 'accident':
            $out = ($arg === '') && function_exists('crrg_seal4_accident') ? crrg_seal4_accident($s, $pc)
                 : (function_exists('crrg_seal4_accident_handle') ? crrg_seal4_accident_handle($s, $pc, $arg) : ['未加载。']);
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'accident', $out);
            break;

        case 'craft':
            $out = function_exists('crrg_seal4_craft') ? crrg_seal4_craft($s, $pc, $arg) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'craft', $out);
            break;

        case 'socnote':            // 留一句话给对方（玩家互动模块 ✓）
            if (!function_exists('crrg_soc_do')) { $out = ['（互动模块未加载。）']; break; }
            $rNote = crrg_soc_do('soc_note', (string) $arg, $s, $pc, $uid, $ctx);
            $out = ($rNote === null) ? ['（格式：留话 <编号>|<内容>。编号在别人的互动窗口里。）'] : (array) $rNote;
            break;

        case 'sealhandle':            // 正办：走节点的首选过法 ✓
            if (!function_exists('crrg_seal_try') || !function_exists('crrg_seal_node_default')) { $out = ['未加载。']; break; }
            $nodeH = trim((string) $arg);
            if ($nodeH === '') { $out = ['（要指定节点：处理 手续。可用节点见「换法 ?」。）']; break; }
            $defH = crrg_seal_node_default($nodeH);
            if ($defH === '') { $out = ['（「' . $nodeH . '」没有可用法子——名字对不对？见「换法 ?」。）']; break; }
            $out = array_merge(['（正办：' . $nodeH . ' → 「' . $defH . '」）'], (array) crrg_seal_try($s, $pc, $nodeH, $defH));

            break;

        case 'sealpath':
            if (!function_exists('crrg_seal_try')) { $out = ['未加载。']; break; }
            $parts = preg_split('/\s+/', $arg, 2);
            $node = crrg_seal_path_node($parts[0] ?? '');
            if ($node === '') { $out = ['（要指定节点：换法 手续 2。可用节点见「换法 ?」）']; break; }
            $out = crrg_seal_try($s, $pc, $node, $parts[1] ?? '');
            /* 选了"动手（战斗）"→ 真开一场 ✓（非演习＝有后果 ✓） */
            if (!empty($s['seal_fight']) && function_exists('crrg_bt_start')) {
                $nodeF = (string) $s['seal_fight']; unset($s['seal_fight']);
                $out = array_merge((array) $out, (array) crrg_bt_start($s['room'] ?? '', $uid, 'seal'));
            }
            /* 2026-10-01：选了"动手（战斗）"→ 这里真开一场 ✓（非演习 = 有后果 ✓） */
            if (!empty($s['seal_fight']) && function_exists('crrg_bt_start')) {
                $nodeF = (string) $s['seal_fight']; unset($s['seal_fight']);
                $out = array_merge((array) $out, (array) crrg_bt_start($s['room'] ?? '', $uid, 'seal'));
            }
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'sealpath', $out);
            break;

        case 'seal5run':
            $out = function_exists('crrg_seal5_run') ? crrg_seal5_run($s, $pc) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'seal5run', $out);
            break;
        case 'seal5seal':
            $out = function_exists('crrg_seal5_close') ? crrg_seal5_close($s, $pc) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'seal5seal', $out);
            break;
        case 'seal5close':
            $out = function_exists('crrg_seal5_close') ? crrg_seal5_close($s, $pc) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'seal5close', $out);
            break;

        case 'handover':
            $out = function_exists('crrg_seal4_handover') ? crrg_seal4_handover($s, $pc) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'handover', $out);
            break;

        case 'nightwatch':
            $out = function_exists('crrg_seal3_nightwatch') ? crrg_seal3_nightwatch($s, $pc) : ['未加载。'];
            if (function_exists('crrg_seal3_conclude')) $out = array_merge((array) $out, (array) crrg_seal3_conclude($s, $pc));
            if (function_exists('crrg_seal3_events')) $out = crrg_seal3_events($s, $pc, $out);
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'nightwatch', $out);
            break;

        case 'poison':
            $out = function_exists('crrg_seal3_poison') ? crrg_seal3_poison($s, $pc, $arg) : ['未加载。'];
            if (function_exists('crrg_seal3_conclude')) $out = array_merge((array) $out, (array) crrg_seal3_conclude($s, $pc));
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'poison', $out);
            break;

        case 'stagechoice':
            /* 决断的前置（2026-09-29）：功课没做完不许拍板 */
            if (($pc['mission'] ?? '') === 'm_mid_seal') {
                $four = ['seal_库存盘点','seal_门况普查','seal_私补痕迹','seal_用量台账','seal_自制配方'];
                if (count(array_intersect($four, (array) ($s['flags'] ?? []))) < 4 && !in_array('seal_来源指向镇上', (array) ($s['flags'] ?? []), true)) {
                    $out = ['你还没把该查的查完——现在拍板就是拍脑袋。（线索还差 ' . (4 - count(array_intersect($four, (array) ($s['flags'] ?? [])))) . ' 条。）'];
                    break;
                }
            }
            $mid = $pc['mission'] ?? '';
            if ($mid === 'm_mid_seal2' && function_exists('crrg_seal2_stage_choice')) {
                $out = crrg_seal2_stage_choice($s, $pc, $arg);
            } elseif ($mid === 'm_mid_seal' && function_exists('crrg_seal_stage_choice')) {
                $out = crrg_seal_stage_choice($s, $pc, $arg);
            } else {
                $out = function_exists('crrg_seal2_stage_choice') ? crrg_seal2_stage_choice($s, $pc, $arg) : ['未加载。'];
            }
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'stage', $out);
            break;

        case 'parley':
            if (($pc['mission'] ?? '') === 'm_mid_seal3' && function_exists('crrg_seal3_parley')) {
                $out = crrg_seal3_parley($s, $pc, $arg);
                if (function_exists('crrg_seal3_conclude')) $out = array_merge((array) $out, (array) crrg_seal3_conclude($s, $pc));
                if (function_exists('crrg_seal3_events')) $out = crrg_seal3_events($s, $pc, $out);
                if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'parley', $out);
                break;
            }
            // 落到《清单上少了一件》的周德海交涉
            $out = function_exists('crrg_mid_gap_pressure') ? crrg_mid_gap_pressure($s, $pc, $arg) : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'parley', $out);
            break;

        case 'resolve':
            $out = function_exists('crrg_mid_gap_resolve') ? crrg_mid_gap_resolve($s, $pc, $arg !== '' ? $arg : '?') : ['未加载。'];
            if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, 'resolve', $out);
            break;

        case 'appraise':
            $out = function_exists('crrg_appraise') ? crrg_appraise($s, $pc, $arg) : ['鉴定模块未加载。'];
            break;

        case 'sealroom':
            $out = function_exists('crrg_seal_room') ? crrg_seal_room($s, $pc) : ['未加载。'];
            break;

        case 'itemcat':
            if (!function_exists('crrg_item_cat_tree')) { $out = ['分类表未加载。']; break; }
            if ($arg === '') {
                $tree = crrg_item_cat_tree();
                $out = ['〔物品分类〕（空分类也列出来——先把架子搭好）'];
                foreach ($tree as $mid => $m) {
                    $out[] = sprintf('■ %s（%s）— 共 %d 件', $m['n'], $mid, $m['n_items']);
                    foreach ($m['subs'] as $sid => $s) {
                        $cnt = count($s['items']);
                        $out[] = sprintf('　%s　%s%s', $cnt ? '·' : '○', $s['n'],
                            $cnt ? sprintf('（%d 件：%s）', $cnt, implode('、', array_map('crrg_drill_item_name', array_slice($s['items'], 0, 5)))) : '（暂无）');
                    }
                }
                $out[] = '（看某类：`分类 弹药`。○ 表示这一类还没有物品。）';
                break;
            }
            $tree = crrg_item_cat_tree(); $hit = '';
            foreach ($tree as $mid => $m) {
                if (mb_strpos($m['n'], $arg) !== false || $arg === $mid) { $hit = ['major', $mid]; break; }
                foreach ($m['subs'] as $sid => $s) if (mb_strpos($s['n'], $arg) !== false || $arg === $sid) { $hit = ['minor', $sid]; break 2; }
            }
            if (!$hit) { $out = ['没有叫「' . $arg . '」的分类（打「分类」看全部）。']; break; }
            if ($hit[0] === 'major') {
                $m = $tree[$hit[1]];
                $out = [sprintf('〔%s〕%s ｜ 共 %d 件', $m['n'], $hit[1], $m['n_items'])];
                foreach ($m['subs'] as $sid => $s) {
                    $out[] = sprintf('　%s（%d 件）', $s['n'], count($s['items']));
                    foreach ($s['items'] as $iid) $out[] = '　　· ' . crrg_drill_item_name($iid);
                }
            } else {
                $ids = [];
                foreach ($tree as $m) foreach ($m['subs'] as $sid => $s) if ($sid === $hit[1]) $ids = $s['items'];
                $out = [sprintf('〔%s〕%s ｜ %d 件', crrg_item_taxonomy()[$hit[1]]['n'], $hit[1], count($ids))];
                foreach ($ids as $iid) $out[] = '　· ' . crrg_drill_item_name($iid);
                if (!$ids) $out[] = '　（这一类还没有物品。）';
            }
            break;

        case 'vitals':
            if (function_exists('crrg_ui_request_open')) crrg_ui_request_open($s, 'vitals');
            $out = array_merge(['〔身体状态〕'], function_exists('crrg_vitals_lines') ? crrg_vitals_lines($pc) : []);
            /* 2026-10-01：人情已拆到「人情」窗口 ✓（这边不再重复显示 ✓ 委员长要求） */
            break;

        case 'ledgers':
            if (function_exists('crrg_ui_request_open')) crrg_ui_request_open($s, 'ledgers');
            $out = ['（「可查的账」窗口已打开：这里有几本账、各属哪条线，点进去各查各的。）'];
            break;

        case 'helpnpc':
            if (!function_exists('crrg_npc_help')) { $out = ['未加载。']; break; }
            $out = crrg_npc_help($s, $pc, (string) $arg);
            break;

        case 'dormin':
            if (!function_exists('crrg_dorm_enter_self')) { $out = ['未加载。']; break; }
            $out = crrg_dorm_enter_self($s, $pc);
            break;

        case 'dormvisit':
            /* 2026-10-22：**NPC 在宿舍 → 直接送进他的屋**（同场景＝能对话 ✓）
               不在宿舍时段 → 只告诉他人在哪，不传送 ✓ */
            if (function_exists('crrg_actor_roster')) {
                foreach ((array) crrg_actor_roster() as $nid => $nd) {
                    $nm = (string) ($nd['n'] ?? '');
                    if ($nm === '' || mb_strpos($nm, (string) $arg) === false) continue;
                    if (function_exists('crrg_dorm_enter')) {
                        [$ok, $line] = crrg_dorm_enter($s, (string) $nid);
                        if ($ok) { $out = [$line, '（' . $nm . '在屋里。要问他什么，直接「问 ' . $nm . '」。）']; break 2; }
                    }
                    $at = function_exists('crrg_actor_where') ? (string) crrg_actor_where((string) $nid) : '';
                    $rn = function_exists('crrg_drill_rooms') ? (string) (crrg_drill_rooms()[$at]['t'] ?? $at) : $at;
                    $out = ['（' . $nm . '不在宿舍。这个点他在「' . $rn . '」。）'];
                    break 2;
                }
            }
            if (!function_exists('crrg_dorm_visit')) { $out = ['未加载。']; break; }
            $pc['__uid'] = (int) $uid;
            $out = crrg_dorm_visit($s, $pc, (string) $arg);
            break;

        case 'dormout':
            if (!function_exists('crrg_dorm_leave')) { $out = ['未加载。']; break; }
            $out = crrg_dorm_leave($s);
            break;

        case 'favor':
            if (function_exists('crrg_ui_request_open')) crrg_ui_request_open($s, 'favor');
            $out = array_merge(['〔人情〕'], function_exists('crrg_npc_fav_lines') ? (array) crrg_npc_fav_lines($pc) : []);
            break;

        case 'worldmap':
            if (!function_exists('crrg_worldmap_svg')) { $out = ['大地图未加载。']; break; }
            if ($arg === '' ) {
                $on = !empty($s['ui']['worldmap']);
                $s['ui']['worldmap'] = $on ? 0 : 1;
                $out = $on ? ['（区域图已收起。）'] : ['（区域图已展开在「区域图」栏位——基地为原点，等距圆环 20–120 km，路网按 已通／部分通／规划 三种线型绘制。）'];
            } elseif ($arg === '文字' || $arg === '文字版') {
                $s['ui']['worldmap'] = 0;
                $out = crrg_worldmap_lines('');
            } else {
                $out = crrg_worldmap_lines($arg);
            }
            break;

        case 'rep':
            $out = function_exists('crrg_rep_lines') ? crrg_rep_lines($pc, $arg) : ['声望系统未加载。'];
            break;

        /* 2026-10-14（madness）：**恢复途径**（CoC：每周一次心理分析；休养按天）✓ */
        case 'therapy':
            $out = function_exists('crrg_san_therapy') ? crrg_san_therapy($pc) : ['未加载。'];
            break;

        case 'rest_san':
            $days = max(1, min(14, (int) ($arg === '' ? 1 : $arg)));
            $out = function_exists('crrg_san_rest') ? crrg_san_rest($pc, $days) : ['未加载。'];
            break;

        case 'calendar':
            $out = function_exists('crrg_cal_today_lines') ? crrg_cal_today_lines() : ['未加载。'];
            break;

        case 'mind':
            $out = function_exists('crrg_mad_panel') ? crrg_mad_panel($pc) : ['未加载。'];
            break;

        /* 2026-10-14（R5）：**推论**——「推论 涂改 名录」看推法；「推论 涂改 名录 1」把它定下来 ✓ */
        case 'infer':
            $w = preg_split('/\s+/u', trim((string) $arg));
            $tid = (string) ($pc['mission'] ?? '');
            /* 2026-10-22：**引擎任务的推论**（新引擎不写 $pc['mission'] ✗ 用阶段旗标 ✓） */
            if ($tid === '' && function_exists('crrg_task_active')) {
                foreach (crrg_tasks() as $tidTmp => $tTmp) {
                    if (crrg_task_active($s, $pc, $tidTmp)) { $tid = (string) $tidTmp; break; }
                }
            }
            if (!function_exists('crrg_infer_slots') || !crrg_infer_slots($tid)) { $out = ['（现在手上没有要推的事。）']; break; }
            if (count($w) < 2) { $out = ['用法：「推论 <线索一> <线索二>」，定了就再补一个序号，例如「推论 涂改 名录 1」。']; break; }
            if (count($w) >= 3 && preg_match('/^[12]$/', (string) $w[2])) {
                $out = crrg_infer_choose($s, $pc, $tid, (string) $w[0], (string) $w[1], (string) $w[2]);
            } else {
                $out = crrg_infer_try($s, $pc, $tid, (string) $w[0], (string) $w[1]);
            }
            break;

        /* 2026-10-14（R3 手机）：窗口 + 呼叫 + 远程交报告 + 通话记录 */
        case 'phone':
            if (function_exists('crrg_ui_request_open')) { crrg_ui_request_open($s, 'phone'); $out = ['（手机打开了。）']; }
            else $out = function_exists('crrg_phone_log_lines') ? crrg_phone_log_lines($uid) : ['未加载。'];
            break;

        case 'call':
            $out = function_exists('crrg_phone_call') ? crrg_phone_call($s, $pc, $arg) : ['未加载。'];
            break;

        case 'phone_report':   /* 2026-10-14：**不能叫 report** —— 已有同名 case（交报告），重名会让这条永不执行 ✗（lint 逮到 ✓） */
            /* 写法：「远程报告」＝对在手任务；「远程报告 <任务> <内容>」＝指定任务与正文 */
            $rp = preg_split('/\s+/u', trim((string) $arg), 2);
            $rq = (string) ($rp[0] ?? ''); $rb = (string) ($rp[1] ?? '');
            $out = function_exists('crrg_phone_report') ? crrg_phone_report($s, $pc, $ctx, $rq, $rb) : ['未加载。'];
            break;

        case 'phonelog':
            $out = function_exists('crrg_phone_log_lines') ? crrg_phone_log_lines($uid) : ['未加载。'];
            break;

        /* 2026-10-31：秘术（《怪胎》六条）—— 列／学／施 */
        case 'miracle':
            $out = function_exists('crrg_miracle_lines') ? crrg_miracle_lines($pc) : ['（秘术未加载。）'];
            break;

        case 'miracle_learn':
            $out = function_exists('crrg_miracle_learn') ? crrg_miracle_learn($s, $pc, (string) $arg, $ctx) : ['（秘术未加载。）'];
            break;

        case 'miracle_cast':
            $out = function_exists('crrg_miracle_cast') ? crrg_miracle_cast($s, $pc, (string) $arg) : ['（秘术未加载。）'];
            break;

        /* 2026-10-14（R4）：收容流程与小队 */
        case 'contain':
            /* 2026-10-22：**任务内收容优先**（传送门/迷魂花）→ 不是任务对象再走旧流程 */
            if (function_exists('crrg_task_contain')) {
                $rT = crrg_task_contain($s, $pc, (string) $arg);
                if ($rT !== null) { $out = $rT; break; }
            }
            $acts = ['取样','试验','转移','封存','等级评定'];
            $a = trim((string) $arg);
            if ($a === '') { $out = function_exists('crrg_contain_panel') ? crrg_contain_panel($pc) : ['未加载。']; break; }
            $parts = preg_split('/\s+/u', $a);
            $op = (string) ($parts[0] ?? '');
            if (!array_key_exists($op, crrg_contain_ops())) { $out = ['（可做的：' . implode('、', $acts) . '。例：收容 取样）']; break; }
            $sol = (int) (($parts[1] ?? '0')); $tgt = (string) ($parts[2] ?? '');
            if ($sol <= 0) {   /* 不写解法 → 列出两条解法与代价（多解法 ✓） */
                $d = crrg_contain_ops()[$op];
                $out = [sprintf('〔%s〕%s', $op, (string) $d['d'])];
                $i = 1; foreach ($d['sols'] as $x) { $out[] = sprintf('　%d）%s　%s', $i++, (string) $x['n'], (string) ($x['d'] ?? '')); }
                $out[] = '（用法：收容 ' . $op . ' 1 <对象名>。代价：时间与暴露——高级别还要审批。）';
                break;
            }
            $out = function_exists('crrg_contain_do') ? crrg_contain_do($s, $pc, $op, $sol - 1, $tgt) : ['未加载。'];
            break;

        case 'squad':
            $a = trim((string) $arg);
            if ($a === '' || $a === '状态') { $out = function_exists('crrg_squad_state') ? crrg_squad_state($pc) : ['未加载。']; break; }
            if (preg_match('/^(招|组|带)\s*(\d)?/u', $a, $m)) { $out = crrg_squad_form($pc, (int) ($m[2] ?? 3)); break; }
            $out = function_exists('crrg_squad_order') ? crrg_squad_order($s, $pc, $a) : ['未加载。'];
            break;

        /* 2026-10-14（phone-life）：**手机·非业务功能** —— 群聊／动态／论坛／备忘录／收音机／小游戏／时钟 */
        case 'ph_group':
            $txt = [];
            foreach (['group_duty' => '值班群', 'group_logi' => '后勤群', 'group_chat' => '闲人群'] as $g => $nm) {
                $txt[] = '〔' . $nm . '〕';
                foreach (crrg_ph_group($g, 5) as $r) $txt[] = sprintf('　%s：%s', (string) ($r['who'] ?? ''), (string) ($r['text'] ?? ''));
            }
            $txt[] = '（要说话：「发言 值班 内容」。）';
            $out = $txt;
            break;

        case 'ph_say':
            $p = preg_split('/\s+/u', trim((string) $arg), 2);
            $map = ['值班' => 'group_duty', '后勤' => 'group_logi', '闲聊' => 'group_chat', '闲人' => 'group_chat'];
            $out = function_exists('crrg_ph_say') ? crrg_ph_say($pc, $map[$p[0] ?? ''] ?? 'group_chat', $p[1] ?? '') : ['未加载。'];
            break;

        case 'ph_feed':
            $txt = ['〔动态〕'];
            foreach (crrg_ph_feed(6) as $i => $r) {
                $txt[] = sprintf('　%d．%s：%s　（赞 %d）', $i + 1, (string) ($r['who'] ?? ''), (string) ($r['text'] ?? ''), (int) ($r['likes'] ?? 0));
            }
            $txt[] = '（点赞：「点赞 <序号>」。）';
            $out = $txt;
            break;

        case 'ph_like':
            $i = max(1, (int) $arg);
            $d = crrg_ph_all();
            if (!empty($d['feed'][$i - 1])) { $d['feed'][$i - 1]['likes'] = (int) ($d['feed'][$i - 1]['likes'] ?? 0) + 1; crrg_ph_save($d); $out = ['（点了个赞。）']; }
            else $out = ['（没有这条。）'];
            break;

        case 'ph_forum':
            $txt = ['〔本地论坛〕'];
            foreach (crrg_ph_forum(5) as $i => $r) {
                $txt[] = sprintf('　%d．%s　—　%s', $i + 1, (string) ($r['title'] ?? ''), (string) ($r['who'] ?? ''));
                $txt[] = '　　　' . (string) ($r['text'] ?? '');
            }
            $out = $txt;
            break;

        case 'ph_memo':
            $m = crrg_ph_memo($pc);
            $txt = ['〔备忘录〕'];
            if (!$m) $txt[] = '　（空的。）';
            foreach (array_slice($m, 0, 8) as $r) $txt[] = sprintf('　· %s%s', (string) ($r['text'] ?? ''), ($r['by'] ?? 'me') !== 'me' ? '　' . (string) $r['by'] : '');
            $txt[] = '（记一条：「记 <内容>」。）';
            $out = $txt;
            break;

        case 'ph_memo_add':
            $out = function_exists('crrg_ph_memo_add') ? crrg_ph_memo_add($pc, $arg) : ['未加载。'];
            break;

        case 'ph_radio':
            $out = [function_exists('crrg_ph_radio_now') ? crrg_ph_radio_now() : '（没有声音。）'];
            break;

        case 'ph_game':
            $out = function_exists('crrg_ph_game') ? crrg_ph_game($pc) : ['未加载。'];
            break;

        case 'ph_clock':
            $out = function_exists('crrg_ph_clock') ? crrg_ph_clock() : ['未加载。'];
            break;

        /* ══ 2026-10-14（R9）小件六件：装备／关系网／分工／提示档／作者工具／善后 ══ */
        case 'gear':
            $out = function_exists('crrg_gear_lines') ? crrg_gear_lines($pc) : ['未加载。'];
            break;

        case 'gear_maint':
            $gid = '';
            foreach ((array) (function_exists('crrg_gear_defs') ? crrg_gear_defs() : []) as $id => $d) {
                if ($arg !== '' && mb_strpos((string) $arg, (string) $d['n']) !== false) { $gid = $id; break; }
            }
            $out = $gid ? (array) crrg_gear_maint($pc, $gid) : ['（保养哪一件？例：「保养 手电」。）'];
            break;

        case 'npc_net':
            $out = function_exists('crrg_npc_net_lines')
                ? crrg_npc_net_lines($arg !== '' ? (function_exists('crrg_npc_name') ? crrg_npc_name($arg) : $arg) : '')
                : ['未加载。'];
            break;

        /* 2026-10-14：**改名叫 assign** —— 原来叫 'team' 与已有的"队伍/车辆面板"撞名 ✗（lint 逮到） */
        case 'assign':
            if ($arg === '' || mb_strpos($arg, '撤') !== false) { $out = crrg_team_clear($pc); break; }
            $roleMap = ['带路' => 'guide', '问话' => 'talk', '把风' => 'lookout', '搬运' => 'carry'];
            $role = '';
            foreach ($roleMap as $cn => $r) if (mb_strpos($arg, $cn) !== false) { $role = $r; break; }
            if ($role === '') { $out = array_merge(['（分工有四种：带路／问话／把风／搬运。例：「分工 把风」。）'], crrg_team_lines($pc)); break; }
            $where = crrg_players_here((string) ($s['room'] ?? ''), (int) ($pc['uid'] ?? 0));
            if (!$where) { $out = ['（这一间没有别人——先把人叫过来。）']; break; }
            $out = crrg_team_assign($pc, $role, (int) $where[0]);
            break;

        case 'hint_level':
            $out = function_exists('crrg_hint_set') ? crrg_hint_set($pc, $arg === '' ? '轻' : $arg) : ['未加载。'];
            break;

        case 'hint_now':
            $out = function_exists('crrg_hint_now') ? crrg_hint_now($s, $pc, (string) ($pc['mission'] ?? '')) : ['未加载。'];
            break;

        case 'trace':
            $room = (string) ($s['room'] ?? '');
            $list = function_exists('crrg_trace_list') ? crrg_trace_list($s, $room) : [];
            $out = ['〔这间屋留下的痕〕'];
            if (!$list) { $out[] = '　（没有。）'; }
            foreach ($list as $r) $out[] = '　· ' . (string) $r['t'];
            $out[] = '（要清：「清理 <关键词>」。）';
            break;

        case 'trace_clean':
            $out = function_exists('crrg_trace_clean') ? crrg_trace_clean($s, $pc, (string) ($s['room'] ?? ''), $arg) : ['未加载。'];
            break;

        case 'task_state':
            /* 作者工具（GM 视角）——只有委员长能看 ✓ */
            $uidNow = (int) ($pc['uid'] ?? 0);
            /* 权限：用 WordPress 原生（委员长是站点管理员 ✓；另外 uid=1 也算）
               —— 2026-10-14：第一版我调了个**根本不存在的 crrg_is_admin()** ✗（空头调用那种坑）→ 改原生 ✓ */
            $admins = ($uidNow === 1) || (function_exists('user_can') && user_can($uidNow, 'manage_options'));
            if (!$admins) { $out = ['（这条是给管理员看的。）']; break; }
            $tid = trim((string) $arg);
            if ($tid === '') $tid = (string) ($pc['mission'] ?? '');
            $t = function_exists('crrg_task') ? crrg_task($tid) : null;
            if (!$t) { $out = ['（没有这个引擎任务：' . $tid . '。手上有：' . (string) ($pc['mission'] ?? '无') . '）']; break; }
            $out = ['〔任务状态 · ' . $tid . '〕'];
            $out[] = '　阶段：' . (function_exists('crrg_task_stage') ? (crrg_task_stage($s, $tid) ?: '（未进入）') : '—');
            $got = []; $miss = [];
            foreach ((array) ($t['clues'] ?? []) as $k => $d) {
                if (function_exists('crrg_clue_has') && crrg_clue_has($s, $tid, $k)) $got[] = (string) ($d['title'] ?? $k);
                else $miss[] = (string) ($d['title'] ?? $k);
            }
            $out[] = '　线索已得 ' . count($got) . '／' . (count($got) + count($miss)) . '：' . (implode('、', array_slice($got, 0, 6)) ?: '—');
            $out[] = '　未得：' . (implode('、', array_slice($miss, 0, 6)) ?: '—');
            $out[] = '　待落账：战斗 ' . count((array) get_option('crrg_fight_pending', [])) . '｜行程 ' . count((array) get_option('crrg_travel_pending', []));
            $out[] = '　暴露 ' . (int) ($s['expose_' . $tid] ?? 0) . '｜痕迹 ' . array_sum(array_map(fn($k) => count((array) $s[$k]), array_filter(array_keys($s), fn($k) => strpos((string) $k, 'trace_') === 0)));
            if (function_exists('crrg_mad_panel')) $out = array_merge($out, crrg_mad_panel($pc));
            if (function_exists('crrg_team_lines')) $out = array_merge($out, crrg_team_lines($pc));
            break;

        /* 2026-10-14（R10）：档案检索 ＋ 周报 */
        case 'survey_node':
            $out = function_exists('crrg_node_survey') ? crrg_node_survey($s, $pc, $arg) : ['未加载。'];
            break;

        case 'arch_search':                                    // 2026-10-14：**不与已有的 case 'archive' 重名** ✗（lint 逮到 ✓）
            $out = function_exists('crrg_arch_do') ? crrg_arch_do($s, $pc, $arg) : ['未加载。'];
            break;
        case 'arch_open':
            $p = preg_split('/\s+/u', trim((string) $arg), 2);
            $out = function_exists('crrg_arch_open') ? crrg_arch_open($s, $pc, (int) ($p[0] ?? 0), (string) ($p[1] ?? '')) : ['未加载。'];
            break;
        case 'weekly':
            if (function_exists('crrg_week_due') && !crrg_week_due($s, $pc)) { $out = ['（这一周的报表已经交过了。）']; break; }
            $out = function_exists('crrg_week_submit') ? crrg_week_submit($s, $pc, $arg) : ['未加载。'];
            break;

        /* 2026-10-14（烹饪）：做菜／看菜谱 */
        case 'cook_menu':
            $out = function_exists('crrg_cook_menu') ? crrg_cook_menu($s, $pc) : ['未加载。'];
            break;
        case 'cook_do':
            $q = trim((string) $arg);
            $rid = '';
            foreach ((array) crrg_cook_recipes() as $k => $r) {
                if ($k === $q || $r['n'] === $q || ($q !== '' && mb_strpos((string) $r['n'], $q) !== false)) { $rid = $k; break; }
            }
            $out = $rid === ''
                ? array_merge(['（没有这道。手上能做的：）'], (array) crrg_cook_menu($s, $pc))
                : (array) crrg_cook_do($s, $pc, $rid);
            break;

        /* 2026-10-14（训练）：练一项／列能练的 */
        case 'train_menu':
            $out = function_exists('crrg_train_menu') ? crrg_train_menu($s, $pc) : ['未加载。'];
            break;
        case 'train_do':
            $q = trim((string) $arg);
            $out = function_exists('crrg_train_do') ? crrg_train_do($s, $pc, $q) : ['未加载。'];
            break;

        /* 2026-10-19（点名）：报到＋点名册 */
        case 'roll_checkin':
            $out = function_exists('crrg_rc_checkin') ? crrg_rc_checkin($s, $pc) : ['未加载。'];
            break;
        case 'roll_roster':
            $out = function_exists('crrg_rc_roster') ? crrg_rc_roster($s, $pc) : ['未加载。'];
            break;

        /* 2026-10-22：案情会（单人＝与 NPC 会商／多人＝队友各出一次） */
        case 'meeting':
            $out = function_exists('crrg_task_meeting') ? crrg_task_meeting($s, $pc, 1) : ['未加载。'];
            break;

        case 'notes':
            $out = function_exists('crrg_note_lines') ? crrg_note_lines($s, $pc, $arg) : ['笔记系统未加载。'];
            break;

        case 'deliver':
            $out = crrg_deliver_lamps($s, $pc);
            break;

        case 'equiplist':
            $out = array_merge(['〔身上〕'], crrg_equip_lines($pc));
            break;

        case 'ask':    // 对话：问 <人> <话题>（先试《封条》话题表，再退回普通对话）
            /* 2026-09-29：按钮标签写成「问镇文书：绕过」时走的就是这个入口；
               《封条》链上的话题在 ask2 那套表里，这里先让它兜一次。 */
            $argQ = preg_replace('/[：:，,、\/]+/u', ' ', (string) $arg);
            $argQ = trim(preg_replace('/\s+/u', ' ', $argQ));
            $pq = preg_split('/\s+/', $argQ, 2);
            /* 2026-10-22（委员长："问 韩雪 还是回'已经问过了'"）：
               《封条》任务已作废 → **「问」不再走封条问答** ✓ 一律走普通对话 ✓
               封条话题（若将来恢复）走「细问 / 追问」那条（case ask2）✓ */
            /* 2026-10-22：**任务内人物优先**（newcomers 不在对话表里 ✗） */
            $partsT = preg_split('/\s+/u', $argQ, 2);
            $whoT = $partsT[0] ?? ''; $topicT = $partsT[1] ?? '';
            if (function_exists('crrg_task_ask_topic') && $topicT !== '') {
                $rT = crrg_task_ask_topic($s, $pc, $whoT, $topicT);
                if ($rT !== null) { $out = $rT; break; }
            }
            if (function_exists('crrg_task_ask')) {
                $rT2 = crrg_task_ask($s, $pc, $whoT);
                if ($rT2 !== null) { $out = $rT2; break; }
            }

            /* —— 普通对话 —— */
            if (!function_exists('crrg_dlg_ask')) { $out = ['对话系统未加载。']; break; }
            $here = crrg_dlg_npcs_at($s['room']);
            /* 2026-10-22（委员长："必须和目标人物处在同一场景才能对话"）：
               **严格同房间** ✓ 不再给"远隔一层"的豁免 ✗
               宿舍是**真房间**（dorm_<id>）→ 拜访把你送进去 → 同在屋里才能问 ✓ */
            if (!$here) { $out = ['这里没有可以搭话的人。']; break; }
            $parts = preg_split('/\s+/u', $argQ, 2);
            $whoQ = $parts[0] ?? ''; $topicQ = $parts[1] ?? '';
            $npcId = '';
            foreach ($here as $nid => $nd) {
                $nmQ = (string) ($nd['name'] ?? '');
                $alQ = (array) ($nd['alias'] ?? []);
                if ($whoQ === '' || $whoQ === $nmQ || in_array($whoQ, $alQ, true) || mb_strpos($nmQ, $whoQ) !== false) { $npcId = $nid; break; }
            }
            if ($npcId === '') {
                $names = [];
                foreach ($here as $nd) $names[] = (string) ($nd['name'] ?? '');
                $out = ['（这儿没有这个人。在的是：' . implode('、', $names) . '。）'];
                break;
            }
            /* 2026-09-29：对话优先走 v2（新树）；旧 NPC 由 v2 内部委托旧实现（逐字节一致 ✓） */
            $rr = function_exists('crrg_dlg2_ask') ? crrg_dlg2_ask($s, $pc, $uid, $npcId, $topicQ)
                                                   : crrg_dlg_ask($s, $pc, $uid, $npcId, $topicQ);
            $out = is_array($rr) ? (array) ($rr[0] ?? $rr) : (array) $rr;
            /* 2026-10-01：对话也开窗（辐射 2 式）——只有该 NPC 有 v2 树时才开 ✓
               （《封条》问话是线性文本 ✗，开窗会是空的 → 那种仍走文字 ✓） */
            if (function_exists('crrg_dlg2_tree') && function_exists('crrg_ui_request_open') && crrg_dlg2_tree($npcId)) {
                $t2 = crrg_dlg2_tree($npcId);
                if (empty($t2['legacy_list']) || !empty($t2['nodes'])) {
                    /* 2026-10-01：对话**结束**（引擎已清掉该 NPC 的节点状态 ✗）→ 请求**关窗** ✓
                       注意：**第一次**问时状态本来就是空的 ✗ → 必须"玩家做了选择（$topicQ 非空 ✓）
                       且状态为空"才算结束 ✓（否则一进门就把窗关了 ✗） */
                    if (function_exists('crrg_ui_request_close') && $topicQ !== '' && empty($s['dlg2'][$npcId])) {
                        crrg_ui_request_close($s, 'dlg:' . $npcId);
                    } else {
                        crrg_ui_request_open($s, 'dlg:' . $npcId);
                    }
                }
            }
            break;


        case 'look':
            $out = ($arg === '') ? crrg_drill_room_lines($s, $ctx, $pc) : crrg_drill_look_at($s, $ctx, $arg);
            if ($arg !== '' && function_exists('crrg_drill_run_look_hooks')) {   // 模组观察钩子（按注册顺序）
                $flags = (array) ($s['flags'] ?? []);
                $extra = [];
                if (crrg_drill_run_look_hooks($s, $pc, $arg, $extra, $flags)) { $out = $extra; $s['flags'] = $flags; }
            }
            if ($arg !== '' && mb_strpos($arg, '时间车') !== false && $s['room'] === 'garage_seal'
                && !in_array('san_timecar', (array) $s['flags'], true)) {
                $s['flags'][] = 'san_timecar';
                $out[] = '';
                $out[] = crrg_coc_san_check($pc, '1/1d3', '看到时间车原型机');
            }
            break;

        case 'status':
            $out = [crrg_drill_status_line($s, $ctx)];
            if ($s['flags']) $out[] = '已完成环节：' . implode('、', array_map('crrg_drill_flag_name', $s['flags']));
            /* 2026-10-01：人情已拆到「人情」窗口 ✓（这边不再重复显示 ✓） */
            break;

        case 'map':
            $out = crrg_drill_map_lines($s);
            break;

        case 'go':
            if (function_exists('crrg_dorm_where') && crrg_dorm_where($s) !== '') {
                crrg_dorm_leave($s);           // 2026-10-01：在宿舍里直接「去 X」＝先出门 ✓（不会卡在宿舍 ✓）
            }
            $exits = array_filter((array) ($rm['x'] ?? []), fn($k) => $k !== '刷卡', ARRAY_FILTER_USE_KEY);
            if ($arg === '') { $out = ['去 哪儿？可往：' . implode('、', array_keys($exits) ?: ['—'])]; break; }
            $target = crrg_drill_match_exit($exits, $arg);
            if (!$target) { $out = ['这里没有通往「' . $arg . '」的路。可往：' . implode('、', array_keys($exits ?: ['—']))]; break; }
            $dst = $rooms[$target];
            $need = crrg_drill_gate_of($target);
            $trainee = ($need > 1 && crrg_drill_eff_lv($pc, $ctx) < $need && (crrg_drill_trainee_pass($pc, $target) || crrg_drill_mission_pass($pc, $target)));
            if (crrg_drill_eff_lv($pc, $ctx) < $need && !$trainee) {
                $hint = ($need >= 5 && function_exists('crrg_drill_trainee_rooms') && in_array($target, crrg_drill_trainee_rooms(), true))
                      ? (crrg_drill_mission_pass($pc, $target)
                            ? '　（任务授权：本次随本合约入场，任务结束后恢复门禁。）'
                            : '　（持「新手训练考核」并着 T3 防护服可随考核入场。）') : '';
                $out = ['门禁指示灯红。' . $dst['t'] . '需要 ' . crrg_drill_card_name($need) . ' 级授权，你的卡是 ' . $ctx['card'] . ' 级。' . $hint];
                break;
            }
            $trainee_note = $trainee ? ['（见习通行：本次随新手训练考核入场，考核交报告后失效。）'] : [];
            if ($target === 'b4_buffer' && !crrg_drill_has_suit($s, $pc)) {
                $out = ['专用楼梯下到一半，门禁不放行。广播提示：进入高危收容区须着 T3 级防护服——你身上没有。'];
                break;
            }
            $old_room = $s['room'];
            $s['room'] = $target;
            $s['steps'] = (int) $s['steps'] + 1;
            $out = array_merge(crrg_drill_room_lines($s, $ctx, $pc), $trainee_note);
            crrg_drill_feed_add($target, $pc['name'], $pc['name'] . '走了进来。', 'move');
            crrg_drill_feed_add($old_room, $pc['name'], $pc['name'] . '往' . $dst['t'] . '去了。', 'move');
            if ($target === 'b4_c3' && !in_array('san_c3', (array) $s['flags'], true)) {
                $s['flags'][] = 'san_c3';
                $out[] = '';
                $out[] = crrg_coc_san_check($pc, '1/1d4', '进入 C3 专用收容廊');
            }
            break;

        case 'brief':
            if ($s['room'] !== 'b2b_hall') { $out = ['领任务在负二层指挥大厅。']; break; }
            /* 2026-09-29：旧版演示脚本已并入合约系统——这个入口直接给正式合约 */
            $done_train = in_array('m_train_01', (array) ($pc['mission_done'] ?? []), true);
            $held = ($pc['mission'] ?? '') !== '' || ($pc['mission2']['id'] ?? '') !== '';
            if (!$done_train && !$held) {
                $pc['mission'] = 'm_train_01';
                $pc['mission_step'] = 0;
                $pc['mission_start'] = function_exists('crrg_vitals_now') ? crrg_vitals_now() : time();
                $m = crrg_drill_mission('m_train_01');
                $step1 = $m['steps'][0] ?? [];
                $hint = (!empty($step1['hint_room']) && function_exists('crrg_drill_route_hint'))
                      ? ('怎么走：' . crrg_drill_route_hint($s['room'], $step1['hint_room'])) : '';
                $out = ['值班员从追踪屏下调出一页脚本，盖了章递给你。',
                        '「正式合约：新手训练考核（C2 巡检修习）。」',
                        '  1) 到装备整备室领 T3 级防护服',
                        '  2) 下负四层，过缓冲区三道气密闸门',
                        '  3) 在 C2 收容廊中段取样并记录读数',
                        '  4) 回指挥大厅交报告',
                        $hint ?: '（第一步：到装备整备室领 T3 防护服。）',
                        '（完成有 8 积分 / 400 元。）'];
                break;
            }
            $out = ['（「新手训练考核」' . ($done_train ? '你已完成' : '已在手上') . '。今日合约在值班板；日常那几条可以天天做。）',
                    '（旧版纸面脚本只作流程演示，计奖走合约。）'];
            break;

        case 'take':
            // ① 眼前的可捡物（有存量才拿；没存量的继续往下找）
            $emptyEnt = '';
            foreach ((crrg_drill_ents()[$s['room']] ?? []) as $e) {
                if (empty($e['take'])) continue;
                $pn = crrg_drill_item_name($e['take']);
                if (mb_strpos($e['n'], $arg) === false && mb_strpos($arg, $e['n']) === false
                    && mb_strpos($pn, $arg) === false && mb_strpos($arg, $pn) === false) continue;
                $left = crrg_drill_stock_get($s['room'], $e['n'], crrg_drill_ent_stock($e));
                if ($left <= 0) { $emptyEnt = $e['n']; continue; }
                crrg_drill_stock_take($s['room'], $e['n'], 1);
                crrg_drill_bag_add_n($pc, $e['take'], 1);
                if ($e['take'] === 'gear_hazmat' && !in_array('T3防护服', (array) $s['items'], true)) {
                    $s['items'][] = 'T3防护服';
                    if (!in_array('gear', (array) $s['flags'], true)) $s['flags'][] = 'gear';
                }
                crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '取了' . $pn . '。');
                $out = ['你取走' . $pn . '（剩余 ' . max(0, $left - 1) . ' 件）。' . (crrg_drill_items()[$e['take']]['d'] ?? '')];
                break 2;
            }
            // ② 地上的东西（别人或自己放下的）
            foreach (crrg_drill_drops($s['room']) as $id => $n) {
                $pn = crrg_drill_item_name($id);
                if (mb_strpos($pn, $arg) === false && mb_strpos($arg, $pn) === false && $arg !== $id) continue;
                crrg_drill_drop_take($s['room'], $id, 1);
                crrg_drill_bag_add_n($pc, $id, 1);
                crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '捡起了' . $pn . '。');
                $out = ['你捡起地上的' . $pn . '。'];
                break 2;
            }
            if ($emptyEnt !== '') { $out = [$emptyEnt . '已经被拿光了。']; break; }
            /* 直觉兜底：手上挂着跑腿单、人在值班板前，「取 排班表／取 单子／取 值班板」都算取件 */
            $mm = ($pc['mission'] ?? '') !== '' ? crrg_drill_mission($pc['mission']) : null;
            if ($mm && $s['room'] === 'b2b_hall' && $arg !== '' && stripos($mm['name'], '跑腿') !== false) {
                $hit = mb_strpos($mm['name'], $arg) !== false
                     || in_array($arg, ['排班表', '单子', '外勤单', '值班板', '件', '取件', '单'], true)
                     || mb_strpos($arg, '排班表') !== false || mb_strpos($arg, '单子') !== false
                     || mb_strpos($arg, '值班板') !== false;
                if ($hit) { $out = crrg_drill_do_act($s, $pc, $ctx, $uid, 'pm_pick', ''); break; }
            }
            if ($s['room'] !== 'b2f_gear') { $out = ['这里没有可以取的东西——点左栏带「取」的物件试试。']; break; }
            crrg_drill_bag_add($pc, 'gear_hazmat');
            $s['items'][] = 'T3防护服';
            if (!in_array('gear', (array) $s['flags'], true)) $s['flags'][] = 'gear';
            crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '领了一件防护服。');
            $out = ['登记簿上签了字，取下一件 T3 级防护服。面罩校验日期是上个月，密封条完好。'];
            break;

        case 'swipe':
            if ($s['room'] !== 'b4_buffer') { $out = ['这里没有闸门可刷。']; break; }
            if (!crrg_drill_has_suit($s, $pc)) { $out = ['没穿防护服，值班员不会给你刷卡。']; break; }
            if ($s['gates'] >= 3) {
                /* 2026-09-29 修：闸已全开时再刷卡就"过去"，否则人卡在缓冲区（没有通观察室的出口） */
                if (($s['room'] ?? '') === 'b4_buffer') {
                    $s['room'] = 'b4_obs'; $s['steps'] = (int) $s['steps'] + 1;
                    $out = ['三道闸门都敞着，你直接穿过去——出口是观察室。'];
                    $out = array_merge($out, crrg_drill_room_lines($s, $ctx, $pc));
                } else { $out = ['三道气密闸门都在开启位（出口在观察室那边）。']; }
                break;
            }
            $s['gates'] = (int) $s['gates'] + 1;
            $where = [1 => '入口闸门', 2 => '中部闸门', 3 => '出口闸门'][$s['gates']];
            $out = ['滴。' . $where . '开了，十五秒后自动闭合。'];
            if ($s['gates'] >= 3) {
                $s['flags'][] = 'gates';
                $s['room'] = 'b4_obs';
                $s['steps'] = (int) $s['steps'] + 1;
                $out[] = '三道闸门全开，出口处是观察室。';
                $out = array_merge($out, ['', '〔' . $rooms['b4_obs']['t'] . '〕', $rooms['b4_obs']['d'],
                                          '可往：缓冲区、收容区主走廊']);
            }
            break;

        case 'test':
            if ($s['room'] !== 'b4_obs') { $out = ['认知稳定性测试装置在负四层观察室入口。']; break; }
            $out = ['手掌贴上装置。指示灯由黄转绿。',
                    '提示音：认知稳定，允许进入收容区。'];
            break;

        case 'key_sample':
            if (function_exists('crrg_drill_mod_sample')) {   // 副本模组的取样点
                $flags = (array) ($s['flags'] ?? []);
                $extra = [];
                if (crrg_drill_mod_sample($s, $pc, $extra, $flags, $pc['items'])) {
                    $out = $extra;
                    if (!in_array('record', $flags, true)) {
                        $flags[] = 'record';
                        crrg_drill_bag_add($pc, 'key_sample');
                        $out[] = '样本盒已装好（背包里多了一件）。';
                    }
                    $s['flags'] = $flags;
                    break;
                }
            }
            if ($s['room'] !== 'b4_c2') { $out = ['取样口在 C2 收容廊中段，野外则是凹地的取样点。']; break; }
            if (in_array('sample_c2', (array) $s['flags'], true)) { $out = ['C2 的读数已经记过一遍，不用重复。']; break; }
            $s['flags'][] = 'sample_c2';
            $s['flags'][] = 'record';          // 兼容旧判定（训练考核第三步）
            crrg_drill_bag_add_n($pc, 'key_sample', 1);
            $out = ['打开取样口，读数：23.4 → 24.1 → 23.8。湿度 82%。',
                    '按规程记在记录板上，签名，写好时间。',
                    '（样本盒已装好收进背包。）'];
            break;

        case 'activate':
            if ($s['room'] !== 'b4_c3') { $out = ['那条廊道里没有可激活的装置。']; break; }
            if (mb_strpos($arg, '绞碎') !== false || mb_strpos($cmd, '绞碎') !== false) {
                $out = ['绞碎装置是收容处置用的，演习不执行。'];
                break;
            }
            if (empty($ctx['chairman'])) {
                $out = ['终端识别失败。屏幕上一行小字：授权人限定。',
                        '值班员在身后说了一句「这台别碰」。'];
                break;
            }
            $out = ['终端亮起，等待二次确认。',
                    '演习脚本到此处为止——自毁装置不在演练范围内，红灯常亮。'];
            break;

        case 'report':
            /* 2026-10-29：**引擎任务的结案优先**（怪胎：按轨道分结局）→ 没有任务再走老合约结算 */
            if (function_exists('crrg_task_report')) {
                $rT = crrg_task_report($s, $pc);
                if (is_array($rT) && $rT && mb_strpos((string) ($rT[0] ?? ''), '〔结案报告〕') !== false) {
                    $out = $rT; break;
                }
            }
            // 交报告统一走合约结算：手上有合约就先结算，旧脚本只作无合约时的后备
            $act = $pc['mission'] ?? '';
            if ($act && ($m = crrg_drill_mission($act))) {
                if (($m['brief_room'] ?? 'b2b_hall') !== $s['room']) {
                    $out = ['结算要找' . ($m['brief_name'] ?? '指挥大厅') . '。'];
                    break;
                }
                [$mm, $out] = crrg_drill_mission_settle($pc, $ctx, $uid, $s);
                $out = array_merge(['〔交报告〕按合约结算：'], (array) $out);
                break;
            }
            /* 无合约：直接引导（旧版纸面脚本已不计奖，不再显示那个无奖分数） */
            $out = ['手上没有可交的合约，交报告交不了什么。',
                    '（今日合约在值班板；日常那几条可天天做。「领任务」就在旁边，能直接接新手训练考核。）'];
            break;

        case 'sit':
            $pc['posture'] = '坐下';
            crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '坐了下来。');
            $out = ['你坐了下来。'];
            break;

        case 'stand':
            $pc['posture'] = '站立';
            crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '站了起来。');
            $out = ['你站了起来。'];
            break;

        case 'vitals_eat':
            $out = crrg_vitals_do_eat($s, $pc, $arg);
            break;

        case 'vitals_drink':
            $out = crrg_vitals_do_drink($s, $pc, $arg);
            break;

        case 'say':
            if ($arg === '') { $out = ['说 什么？']; break; }
            $arg = mb_substr($arg, 0, 80);
            crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '：' . $arg, 'say');
            $out = ['你说：「' . $arg . '」'];
            break;

        case 'emote':
            $em = ['点头' => '点了点头', '摇头' => '摇了摇头', '叹气' => '叹了口气', '笑' => '笑了一下',
                   '皱眉' => '皱起眉头', '鼓掌' => '拍了拍手', '挥手' => '挥了挥手', '沉思' => '低头想了一会儿',
                   '耸肩' => '耸了耸肩', '揉眼睛' => '揉了揉眼睛'];
            $what = $arg !== '' ? $arg : '点头';
            $hit = '';
            foreach ($em as $k => $t) if (mb_strpos($k, $what) !== false || mb_strpos($what, $k) !== false) { $hit = $t; break; }
            if (!$hit) { $out = ['可用的表情：' . implode('、', array_keys($em))]; break; }
            crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . $hit . '。');
            $out = ['你' . $hit . '。'];
            break;

        case 'act':
            $ac = ['拍肩' => '拍了拍旁边人的肩', '递过去' => '把手里的东西递了过去', '记录' => '掏出记录板写了两笔',
                   '检查装备' => '低头检查了一遍随身装备', '靠墙' => '靠到墙边站着', '打手势' => '朝对面打了个手势',
                   '蹲下' => '蹲下看地上的痕迹'];
            $what = $arg !== '' ? $arg : '检查装备';
            $hit = '';
            foreach ($ac as $k => $t) if (mb_strpos($k, $what) !== false || mb_strpos($what, $k) !== false) { $hit = $t; break; }
            if (!$hit) { $out = ['可用的动作：' . implode('、', array_keys($ac))]; break; }
            crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . $hit . '。');
            $out = ['你' . $hit . '。'];
            break;

        case 'roll':
            if ($arg === '') {
                $names = array_keys($pc['skills'] ?: crrg_coc_skill_base($pc['attrs']));
                $out = ['用法：掷 <技能> [奖励骰数／-惩罚骰数]，例：掷 侦查 ／ 掷 图书馆使用 1'];
                $out[] = '可掷：' . implode('、', array_slice($names, 0, 16)) . '…';
                break;
            }
            if (!preg_match('/^([^\d\s]+)\s*(-?\d)?$/u', $arg, $mm)) { $out = ['格式：掷 <技能> [修正]']; break; }
            $sk  = $mm[1];
            $mod = (int) ($mm[2] ?? 0);
            $val = crrg_coc_skill_val($pc, $sk);
            if ($val <= 0) { $out = ['角色卡上没有相近的技能。']; break; }
            $r = crrg_coc_check($sk, $val, max(0, $mod), max(0, -$mod));
            $out = [$r['text']];
            if ($r['level'] === 'crit')    $out[] = '骰子给得很干脆。';
            if ($r['level'] === 'fumble')  $out[] = '这一下很难看——记录在案，演习里也一样算。';
            if ($r['level'] === 'extreme') $out[] = '接近极限的好结果。';
            break;

        case 'here':
            $out = ['（「互动」窗口已打开：这里是当下能做的事，每条都能点。）'];
            break;
        case 'sell':
            $out = ['（回収窗口已打开。）'];
            break;
        case 'stash':
            $out = ['（储物柜窗口已打开。）'];
            break;
        case 'board':
            $out = ['（值班板窗口已打开。）'];
            break;
        case 'team':
            $out = ['（队伍窗口已打开。）'];
            break;
        case 'vehicle':
            $out = ['（车辆窗口已打开。）'];
            break;
        case 'bag':
            if (function_exists('crrg_ui_request_open')) crrg_ui_request_open($s, 'bag');
            $out = [];
            if (function_exists('crrg_equip_lines')) {
                $out[] = '〔装备〕' . implode('　', crrg_equip_lines($pc));
            }
            $cnt = crrg_drill_bag_counts($pc);
            if (!$cnt) {
                $out[] = '〔背包〕空。';
            } elseif (function_exists('crrg_item_classify')) {
                /* 按分类分组显示（大类 → 小类 → 物品） */
                $by = [];
                foreach ($cnt as $id => $n) {
                    $cat = crrg_item_classify($id);
                    $tax = crrg_item_taxonomy();
                    $major = $tax[$cat]['major'] ?? 'misc';
                    $by[$major][$cat][] = [$id, $n];
                }
                $out[] = sprintf('〔背包〕共 %d 件，按分类：', count((array) $pc['items']));
                $majors = crrg_item_major();
                foreach ($by as $mid => $subs) {
                    $out[] = sprintf('■ %s', $majors[$mid]['n'] ?? $mid);
                    foreach ($subs as $sid => $list) {
                        $parts = [];
                        foreach ($list as [$id, $n]) $parts[] = (function_exists('crrg_appraise_display') ? crrg_appraise_display($pc, $id) : crrg_drill_item_name($id)) . ($n > 1 ? '×' . $n : '');
                        $out[] = sprintf('　%s：%s', crrg_item_taxonomy()[$sid]['n'] ?? $sid, implode('、', $parts));
                    }
                }
            } else {
                $parts = [];
                foreach ($cnt as $id => $n) $parts[] = crrg_drill_item_name($id) . ($n > 1 ? '×' . $n : '');
                $out[] = sprintf('〔背包〕共 %d 件：', count((array) $pc['items']));
                foreach (array_chunk($parts, 6) as $chunk) $out[] = '　' . implode('、', $chunk);
            }
            $out[] = '（穿戴／脱下／装备栏／放下／用／吃／喝 都可用。）';
            break;

        case 'equip':   // 旧入口：一律转到部位装备系统，避免绕过部位限制
            if (function_exists('crrg_ui_request_open')) crrg_ui_request_open($s, 'equip');
            $out = function_exists('crrg_equip_wear') ? crrg_equip_wear($pc, $arg)
                                                      : ['背包里没有这样东西。'];
            if ($out && is_array($out) && mb_strpos(implode('', $out), '穿上') !== false) {
                crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '穿戴好了' . crrg_drill_item_name($arg) . '。');
            }
            $out = ['你穿好了' . crrg_drill_item_name($id) . '。'];
            break;

        case 'eat_noodle':
            $out = crrg_drill_do_act($s, $pc, $ctx, $uid, 'noodle', $arg === '牛肉面' ? 'beef' : 'plain');
            break;

        case 'use':
            $id = crrg_drill_find_bag($pc, $arg !== '' ? $arg : '手电筒');
            if (!$id) { $out = ['背包里没有这样东西。']; break; }
            $it = crrg_drill_items()[$id];
            $dd = crrg_coc_derive($pc['attrs']);
            if (($it['use'] ?? '') === 'san') {
                $gain = crrg_coc_dice('1d3');
                $pc['san'] = min($dd['san_max'], (int) $pc['san'] + $gain);
                crrg_drill_bag_remove($pc, $id);
                $out = [sprintf('注射了%s，理智 +%d → %d/%d。', $it['n'], $gain, $pc['san'], $dd['san_max'])];
            } elseif (($it['use'] ?? '') === 'hp') {
                $pc['hp'] = min($dd['hp_max'], (int) $pc['hp'] + 1);
                crrg_drill_bag_remove($pc, $id);
                $out = [sprintf('用了%s，生命 +1 → %d/%d。', $it['n'], $pc['hp'], $dd['hp_max'])];
            } else {
                $out = ['你摆弄了一下' . $it['n'] . '。' . $it['d']];
            }
            crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '用了' . $it['n'] . '。');
            break;

        case 'drop':
            $id = crrg_drill_find_bag($pc, $arg);
            if (!$id) { $out = ['背包里没有这样东西。']; break; }
            crrg_drill_bag_del_n($pc, $id, 1);
            crrg_drill_drop_add($s['room'], $id, 1);      // 落到当前地点，可再捡回
            if ($id === 'gear_hazmat') { $s['items'] = array_values(array_diff((array) $s['items'], ['T3防护服'])); }
            crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '把' . crrg_drill_item_name($id) . '放在了地上。');
            $out = ['你放下' . crrg_drill_item_name($id) . '（现在在' . crrg_drill_rooms()[$s['room']]['t'] . '的地上）。'];
            break;

        case 'store':   // 存入个人仓库
            $id = crrg_drill_find_bag($pc, $arg);
            if (!$id) { $out = ['背包里没有这样东西。']; break; }
            if (!in_array($s['room'], ['b1_room', 'b1_dorm', 'b3_warehouse'], true)) {
                $out = ['存东西去宿舍（储物柜）或物资仓库（个人物资柜）。'];
                break;
            }
            crrg_drill_bag_del_n($pc, $id, 1);
            crrg_drill_stash_add($pc, $id, 1);
            if ($id === 'gear_hazmat') $s['items'] = array_values(array_diff((array) $s['items'], ['T3防护服']));
            $out = ['已存入' . crrg_drill_item_name($id) . '。柜子里现有 ' . (int) ($pc['stash'][$id] ?? 0) . ' 件。'];
            break;

        case 'fetch':   // 从个人仓库取出
            $hit = null;
            foreach ((array) ($pc['stash'] ?? []) as $id => $n) {
                $pn = crrg_drill_item_name($id);
                if (mb_strpos($pn, $arg) !== false || mb_strpos($arg, $pn) !== false || $arg === $id) { $hit = $id; break; }
            }
            if (!$hit) { $out = ['柜子里没有「' . $arg . '」。']; break; }
            if (!in_array($s['room'], ['b1_room', 'b1_dorm', 'b3_warehouse'], true)) {
                $out = ['取东西去宿舍（储物柜）或物资仓库（个人物资柜）。'];
                break;
            }
            crrg_drill_stash_take($pc, $hit, 1);
            crrg_drill_bag_add_n($pc, $hit, 1);
            if ($hit === 'gear_hazmat' && !in_array('T3防护服', (array) $s['items'], true)) $s['items'][] = 'T3防护服';
            $out = ['取出' . crrg_drill_item_name($hit) . '（柜中剩余 ' . (int) ($pc['stash'][$hit] ?? 0) . ' 件）。'];
            break;

        case 'search':
            $r = crrg_coc_check('侦查', max(1, crrg_coc_skill_val($pc, '侦查')));
            $out = [$r['text']];
            crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '在这里翻找了一阵。');
            $hidden = ['b1_cor_w' => 'tool_battery', 'b1_dorm' => 'med_bandage', 'b2b_cor' => 'doc_pencil',
                       'b3_warehouse' => 'food_ration', 'b4_main' => 'med_bandage', 'garage_maint' => 'tool_battery'];
            $flag = 'searched_' . $s['room'];
            if (crrg_coc_level_rank($r['level']) >= 2) {
                if (isset($hidden[$s['room']]) && !in_array($flag, (array) $s['flags'], true)) {
                    $s['flags'][] = $flag;
                    crrg_drill_bag_add($pc, $hidden[$s['room']]);
                    $out[] = '在缝隙里摸到一件东西：' . crrg_drill_item_name($hidden[$s['room']]) . '（已放进背包）。';
                } else {
                    $out[] = '没有别的了，能翻的地方都翻过。';
                }
            } else {
                $out[] = '什么也没找到。';
            }
            break;

        case 'archive':
            if ($s['room'] !== 'b1_archive') { $out = ['调阅卷宗在负一层档案室（B 级，双人双锁）。']; break; }
            $r = crrg_coc_check('图书馆使用', max(1, crrg_coc_skill_val($pc, '图书馆使用')));
            $out = [$r['text']];
            crrg_drill_feed_add($s['room'], $pc['name'], $pc['name'] . '在档案室调阅卷宗。');
            if (crrg_coc_level_rank($r['level']) >= 2) {
                $out[] = '你按编号找到一卷：1987 年祁连山南麓地质普查补充记录，夹着一张手绘剖面图，边角写着「此处勿动」。';
            } else {
                $out[] = '目录与架上实物对不上，抽了两卷都不是要找的。';
            }
            break;

        case 'card':
            $out = array_merge(['【角色卡】'], crrg_drill_pc_lines($pc, true),
                ['背包：' . ($pc['items'] ? implode('、', array_map('crrg_drill_item_name', $pc['items'])) : '空'),
                 '（车卡流程：掷骰 → 选职业 <名> → 加点 <技能> <点> → 完成）']);
            break;

        case 'wallet':
            $base_card = crrg_drill_card($ctx['rank'])[0];
            $out = [sprintf('积分 %d ｜ 钱 %d 元 ｜ 演习权限 %s（职务卡级 %s）',
                (int) $pc['credits'], (int) $pc['money'],
                crrg_drill_clearance_name(crrg_drill_eff_lv($pc, $ctx)), $base_card)];
            $out[] = '钱：基地补给、网购、宿舍家具。积分：演习权限晋升、武器与镇物。';
            break;

        case 'missions':
            if (function_exists('crrg_ui_request_open')) crrg_ui_request_open($s, 'missions');
            $out = crrg_drill_mission_lines($pc);
            $out[] = '接合约、出发、交任务都在界面上点（值班板／NPC 面板／出勤按钮）。';
            break;

        case 'accept':
            $m = crrg_drill_mission($arg);
            if (!$m) { $out = ['没有这个合约编号。用「任务」看列表。']; break; }
            $need = (int) ($m['min_lv'] ?? 3);
            $myLv = crrg_drill_eff_lv($pc, $ctx);
            if ($myLv < $need) {
                $out = [sprintf('外勤资格不够：该合约需 %s 级（你的演习权限 %s，职务 %s）。',
                    crrg_drill_clearance_name($need), crrg_drill_clearance_name($myLv ?: 1), $ctx['rank_name'])];
                $out[] = '可用积分在「晋升」里换演习权限，或先做训练考核（基地内那个 C2 巡检脚本）。';
                break;
            }
            if (in_array($arg, (array) ($pc['mission_done'] ?? []), true)) {
                $rep = crrg_drill_mission_repeat_state($pc, $arg);
                if ($rep['can']) { $pc['mission_done'] = array_values(array_diff((array) $pc['mission_done'], [$arg])); }
                else { $out = ['这个合约暂时接不了：' . $rep['why']]; break; }
            }
            $mDef = crrg_drill_mission($arg);
            $isDaily = !empty($mDef['repeat']);
            /* 侧槽：日常，或"在手是课目（coexist）"时的新合约 */
            $inHand = crrg_drill_mission($pc['mission'] ?? '');
            $canSide = ($isDaily || !empty($inHand['coexist'])) && !empty($pc['mission']);
            if ($canSide) {           // 并到侧槽（已占用则拒绝，避免无声覆盖）
                $cur2 = $pc['mission2']['id'] ?? '';
                if ($cur2 !== '' && $cur2 !== $arg) {
                    $mn = crrg_drill_mission($cur2);
                    $out = ['侧槽已占用：' . ($mn['name'] ?? $cur2) . '。先「交任务」结算，或「放弃合约」。'];
                    break;
                }
                if (function_exists('crrg_drill_daily_accept')) crrg_drill_daily_accept($pc, $arg);
                $out = ['已接受（侧槽，与手上合约并行）：' . ($mDef['name'] ?? $arg), $mDef['prompt'] ?? ''];
                break;
            }
            if (function_exists('crrg_drill_mission2')) { $m2 = crrg_drill_mission2($pc); if ($m2['id'] === $arg) { $pc['mission2'] = ['id'=>'','step'=>0,'start'=>0]; } }
            if (!empty($pc['mission'])) {
                $cm = crrg_drill_mission($pc['mission']);
                $out = ['手上还有没交的合约：' . ($cm['name'] ?? $pc['mission']) . '（日常可并行，输入「接受 <日常id>」）'];
                break;
            }
            $pc['mission'] = $arg;
            $pc['mission_step'] = 0;
            $pc['mission_start'] = function_exists('crrg_vitals_now') ? crrg_vitals_now() : time();
            if (function_exists('crrg_seal_on_accept')) crrg_seal_on_accept($s, $pc, $arg);   // 《封条》初始化
            $rmdep = crrg_drill_rooms()[$m['depart_room'] ?? ''] ?? null;
            $step1 = $m['steps'][0] ?? [];
            $hint = '';
            if (function_exists('crrg_drill_route_hint')) {
                foreach ((array) $m['steps'] as $st) {                     // 取第一个带房间的步骤算路线
                    $target = $st['room'] ?? ($st['hint_room'] ?? '');
                    if ($target !== '') { $hint = '怎么走：' . crrg_drill_route_hint($s['room'] ?? '', $target); break; }
                }
            }
            $out = ['已接受：' . $m['name'], $m['prompt'] ?? ''];
            foreach ((array) ($m['intro'] ?? []) as $ix) $out[] = $ix;      // 开场（连续任务）
            $out[] = $hint ?: ('第一步：' . ($step1['text'] ?? ''));
            $out[] = '出发点：' . ($rmdep['t'] ?? ($m['depart_room'] ?? '—')) . '，到那儿「出发」。';
            break;

        case 'depart':
            [$m, $out] = crrg_drill_depart($s, $pc, $ctx, $uid, false);
            break;

        case 'retreat':
            if (crrg_drill_cur_scene() === 'base') { $out = ['你已经在基地里。']; break; }
            crrg_drill_set_scene('base');
            $pc['scene'] = 'base';
            $s['room'] = $pc['base_room'] ?? 'b1_tunnel';
            $out = array_merge(['〔撤离〕返回基地。'], crrg_drill_room_lines($s, $ctx));
            break;

        case 'settle':
            $m = crrg_drill_mission($pc['mission'] ?? '');
            if ($m && ($s['room'] !== ($m['brief_room'] ?? 'b2b_hall'))) {
                $out = ['结算要找' . ($m['brief_name'] ?? '指挥大厅') . '。'];
                break;
            }
            [$mm, $out] = crrg_drill_mission_settle($pc, $ctx, $uid, $s);
            break;

        case 'shop':
            $here = crrg_drill_shops_at($s['room']);
            if (!$here) {
                $out = ['这里没有商店。基地的兑换点：食堂小卖部、基地商超、勤务司兑换处、车库调剂处，宿舍电脑（网购，需先买电脑）。',
                        '（界面上有商店面板，可直接点买。）'];
                break;
            }
            $out = [];
            foreach ($here as $shop) {
                $ok = crrg_drill_shop_unlocked($shop, $pc);
                $out[] = '〔' . $shop['n'] . '〕' . $shop['keep'] . ($ok ? '' : '（未解锁：' . ($shop['need'] ?? '') . '）');
                foreach ($shop['cats'] as $cat) {
                    $out[] = '  · ' . $cat;
                    foreach ((crrg_drill_goods()[$cat] ?? []) as $gid => $g) {
                        $out[] = sprintf('    %s　%d %s　%s', $g['n'], $g['p'], $g['cur'] === 'credits' ? '积分' : '元', $g['d'] ?? '');
                    }
                }
            }
            $out[] = '点面板上的商品即可购买。';
            break;

        case 'buy':
            $arg = trim($arg);
            $cnt = 1;
            if (preg_match('/^(.+?)\s+(\d+)$/u', $arg, $nn)) { $arg = trim($nn[1]); $cnt = (int) $nn[2]; }
            $out = crrg_drill_buy($pc, $arg, $cnt, $s['room']);
            break;

        case 'dorm':
            $out = crrg_drill_dorm_lines($pc, $pc['name'] ?: '你');
            $out[] = '可做：布置 <一句话> ／ 在宿舍里「商店」买家具 ／ 拜访 <姓名>';
            break;

        case 'decor':
            if (!in_array($s['room'], ['b1_room', 'b1_dorm'], true)) { $out = ['布置宿舍要在宿舍里。']; break; }
            $pc['dorm']['desc'] = mb_substr($arg, 0, 120);
            $out = ['私人布置已更新：' . $pc['dorm']['desc']];
            break;

        case 'visit':
            /* 2026-10-22：先认 NPC（原来只查 WP 用户 ✗ NPC 一律"没找到宿舍" ✗）*/
            if (function_exists('crrg_actor_roster')) {
                foreach ((array) crrg_actor_roster() as $nid => $nd) {
                    $nm = (string) ($nd['n'] ?? '');
                    if ($nm === '' || mb_strpos($nm, (string) $arg) === false) continue;
                    $at = function_exists('crrg_actor_where') ? (string) crrg_actor_where((string) $nid) : '';
                    $rn = function_exists('crrg_drill_rooms') ? (string) (crrg_drill_rooms()[$at]['t'] ?? $at) : $at;
                    if ($at !== '' && $at === (string) ($s['room'] ?? '')) {
                        $out = ['（' . $nm . '就在这儿。点他的名牌，或直接「问 ' . $nm . '」。）'];
                    } else {
                        $out = ['（' . $nm . '这会儿在「' . $rn . '」。按作息走的，不是一直在宿舍。）'];
                    }
                    break 2;
                }
            }
            $found = null;
            foreach (get_users(['search' => '*' . $arg . '*', 'search_columns' => ['display_name', 'user_login']]) as $u) {
                $d = crrg_drill_pc($u->ID);
                if (!empty($d['dorm'])) { $found = [$u, $d]; break; }
            }
            if (!$found) { $out = ['没找到这个人的宿舍。']; break; }
            $out = crrg_drill_dorm_lines($found[1], $found[0]->display_name);
            break;

        case 'promote':
            $pr  = crrg_drill_promotions();
            $cur = crrg_drill_eff_lv($pc, $ctx);
            $next = null;
            foreach ($pr as $lv => $cost) if ($lv > $cur) { $next = [$lv, $cost]; break; }
            if (!$next) { $out = ['演习权限已到顶。']; break; }
            $out = [sprintf('下一档：%s 级，需 %d 积分（现有 %d）。',
                crrg_drill_clearance_name($next[0]), $next[1], (int) $pc['credits'])];
            if ((int) $pc['credits'] >= $next[1]) {
                $pc['credits'] -= $next[1];
                $pc['clearance'] = $next[0];
                $out[] = sprintf('已兑换：演习权限升到 %s 级——只改演习门禁，站点职务不动。剩余积分 %d。',
                    crrg_drill_clearance_name($next[0]), (int) $pc['credits']);
            } else {
                $out[] = '积分不足。晋升只走演习权限，职务升迁仍走站内晋升申请。';
            }
            break;

        case 'simstart':   // 训练室：对抗模拟舱（实体点进来的）
            if ($s['room'] !== 'b1_train' && $s['room'] !== 'b2f_sim') { $out = ['对抗模拟舱在负一层训练室（或 F 区战术模拟室）。']; break; }
            if (!function_exists('crrg_bt_start')) { $out = ['战斗系统未加载。']; break; }
            if (function_exists('crrg_fate')) {
                if ((int) $pc['credits'] < 2) { $out = ['演习要花 2 积分（你现在 ' . (int) $pc['credits'] . '）。']; break; }
                $pc['credits'] = (int) $pc['credits'] - 2;
                $out = array_merge(['（演习费用 2 积分，已扣，不返还。）'], (array) crrg_bt_start($s['room'], $uid, 'sim'));
            } else {
                $out = crrg_bt_start($s['room'], $uid, 'sim');
            }
            break;

        case 'reset':
            $s = ['room' => 'b1_tunnel', 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 0];
            $out = array_merge(['演习已重置。'], crrg_drill_room_lines($s, $ctx));
            break;

        default:
            $out = ['不认识的指令：「' . $cmd . '」。输入「帮助」看可用指令。'];
    }
    if (function_exists('crrg_drill_mission_tick')) crrg_drill_mission_tick($s, $pc, $cmd, $out);
    if (!empty($guardMsgs)) $out = array_merge($guardMsgs, (array) $out);
    if (!empty($pre_mission_lines)) $out = array_merge($pre_mission_lines, (array) $out);
    /* ── 窗口化（2026-09-30）：动词 → 窗口。**放在最后**：因为上面的 mission_tick 会重写 $s，
       早设的 ui_open 会被冲掉 ✗（图鉴/车辆 就是这么失败的 ✓）── */
    if (function_exists('crrg_ui_request_open') && isset($uiWinMap[$verb])) crrg_ui_request_open($s, $uiWinMap[$verb]);
    return [$s, $out, $pc];
}


