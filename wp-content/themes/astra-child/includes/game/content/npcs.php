<?php
/**
 * CRRG 演习系统 · 内容（纯数据）· NPC 名册与作息、D 级课目、NPC 附加话题
 * 2026-09-29 Phase 1（含一次事故后的恢复：数据自 drill-npc.php 搬家前备份重新提取）
 */
if (!defined('ABSPATH')) exit;

function crrg_game_npc_roster_data() {
    $base = [
        'kang' => ['n' => '康明远', 'alias' => ['康值班', '小康'], 'role' => '值班调度员',
            'sched' => ['黎明' => 'b2b_duty', '上午' => 'b2b_hall', '正午' => 'b1_canteen', '下午' => 'b2b_hall',
                        '黄昏' => 'b2b_duty', '夜' => 'b2b_duty', '深夜' => 'b2f_barracks'],
            'task' => 'm_pm_duty'],
        'zhou' => ['n' => '周德海', 'alias' => ['老周'], 'role' => '后勤保管员',
            'sched' => ['黎明' => 'b1_canteen', '上午' => 'b3_warehouse', '正午' => 'b3_batt', '下午' => 'b3_warehouse',
                        '黄昏' => 'b3_warehouse', '夜' => 'b3_batt', '深夜' => 'b2f_barracks'],
            'task' => 'm_pm_stock'],
        'chen' => ['n' => '陈连生', 'alias' => ['陈师傅', '老陈'], 'role' => '食堂师傅',
            'sched' => ['黎明' => 'b1_canteen', '上午' => 'b1_canteen', '正午' => 'b1_canteen', '下午' => 'b1_med',
                        '黄昏' => 'b1_canteen', '夜' => 'b2f_barracks', '深夜' => 'b2f_barracks'],
            'task' => 'm_pm_meal'],
        'han' => ['n' => '韩雪', 'alias' => ['小韩'], 'role' => '技术研发员',
            'sched' => ['黎明' => 'b2f_barracks', '上午' => 'b2b_tech', '正午' => 'b1_canteen', '下午' => 'b2b_tech',
                        '黄昏' => 'b2b_tech', '夜' => 'b2b_tech', '深夜' => 'b2f_barracks'],
            'task' => 'm_pm_repair'],
        'bai' => ['n' => '白淑英', 'alias' => ['白大夫', '白医生'], 'role' => '医官',
            'sched' => ['黎明' => 'b1_med', '上午' => 'b1_med', '正午' => 'b1_med', '下午' => 'b1_med',
                        '黄昏' => 'b1_med', '夜' => 'b1_med', '深夜' => 'b1_med'],
            'task' => 'm_pm_med'],
        'wei' => ['n' => '魏长庚', 'alias' => ['老魏'], 'role' => '装备管理员',
            'sched' => ['黎明' => 'b2f_gear', '上午' => 'b2f_armory', '正午' => 'b1_canteen', '下午' => 'b2f_gear', 
                        '黄昏' => 'b2f_gear', '夜' => 'b2f_barracks', '深夜' => 'b2f_barracks'],
            'task' => 'm_pm_gear'],
    ];
    if (function_exists('crrg_game_npc_roster_extra')) $base = array_merge($base, (array) crrg_game_npc_roster_extra());
    if (function_exists('crrg_game_npc_roster_extra_gz')) $base = array_merge($base, (array) crrg_game_npc_roster_extra_gz());
    return $base;}

function crrg_game_npc_missions_data() {
    return [
        'm_d_sim' => ['name' => 'D 级课目：对抗模拟', 'level' => '低', 'kind' => 'base', 'min_lv' => 1,
            'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
            'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
            'prompt' => '值班板挂着一条低危课目：去训练室开一场对抗模拟，打完（或撑过三轮）就算过。',
            'steps' => [['flag' => '模拟_完成', 'text' => '在训练室完成一次对抗模拟']],
            'reward' => ['credits' => 3, 'money' => 80],
            'settle_note' => '值班员在训练记录上签了字：课目合格。'],
        'm_d_night' => ['name' => 'D 级课目：夜班巡楼', 'level' => '低', 'kind' => 'base', 'min_lv' => 1,
            'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
            'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
            'prompt' => '夜班缺人巡楼。**只能在夜里做**：从负一层主走廊西段走到东段，看一眼有没有异常。',
            'steps' => [
                ['room' => 'b1_cor_w', 'night' => true, 'text' => '夜里巡到主走廊西段'],
                ['room' => 'b1_cor_m', 'night' => true, 'text' => '中段（看一眼厕所与浴室的门）'],
                ['room' => 'b1_cor_e', 'night' => true, 'text' => '东段（看组长办公室的门）'],
            ],
            'reward' => ['credits' => 4, 'money' => 60],
            'settle_note' => '巡楼记录签了字：三处无异常。'],
        'm_d_check' => ['name' => 'D 级课目：负三层设备巡检', 'level' => '低', 'kind' => 'base', 'min_lv' => 1,
            'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
            'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
            'prompt' => '后勤请人代看一眼负三层的三处设备：主变电站、柴油发电机、蓄电池组，读数抄在单子上带回来。',
            'steps' => [
                ['room' => 'b3_power', 'text' => '看主变电站'],
                ['room' => 'b3_gen',   'text' => '看柴油发电机组'],
                ['room' => 'b3_batt',  'text' => '看蓄电池组'],
            ],
            'reward' => ['credits' => 5, 'money' => 150],
            'settle_note' => '单子交回，后勤照数核了：三处读数都对。'],
        'm_d_wild2' => ['name' => '野外观察·外围记录（D 级可做）', 'level' => '中', 'kind' => 'base', 'min_lv' => 1,
            'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
            'depart_room' => 'garage_maint', 'entry_room' => 'w_gate',
            'prompt' => '档案 W-01-02-032 的外围记录：只到松林边缘与山脊观察点，**下凹地要 C 级**。带上记录板。',
            'steps' => [
                ['room' => 'w_stand', 'text' => '在松林边缘比对树皮'],
                ['room' => 'w_ridge', 'text' => '上到山脊观察点记录凹地'],
                ['room' => 'b2b_hall', 'text' => '回指挥大厅交记录'],
            ],
            'reward' => ['credits' => 12, 'money' => 500],
            'settle_note' => '记录归入档案 W-01-02-032；未接近本体，无理智损耗。'],
    ];
}

function crrg_game_npc_topics_data() {
    return [
        'han' => [
            ['技术问题', ['技术', '设备', '仪器'], ['她把手里的螺丝刀放下：「问吧，我这儿正拆着。」',
                                                    '「上次那批台灯的线路我改过——你们用着还行吧。」']],
            ['关于封条', ['封条', '纸', '配比'], ['「配比我算过。要真是掺铅，那就不能照抄。」']],
        ],
        'kang' => [
            ['值班表', ['排班', '值班表', '换班'], ['他从抽屉里摸出排班表：「下礼拜的，你要看？」',
                                                  '「我这儿缺人。你要是能顶两个夜班，我请你吃饭。」']],
            ['夜里的动静', ['夜里', '晚上', '动静'], ['「后半夜的动静我都记着。你要真想听，得有耐心。」']],
        ],
        'chen' => [
            ['食堂的账', ['菜单', '饭', '食材'], ['「八份值班餐，多一份我都得算。」',
                                                '「你们出外勤的回来晚，锅我给你们留着。」']],
            ['老周的烟', ['烟', '老周'], ['「他？他戒了三年了。那回之后就没碰过。」']],
        ],
        'zhou' => [
            ['库房的门道', ['库房', '备件', '领料'], ['「领料单我签，但超三件要室主任点头。」',
                                                     '「有些件，单子上没有，库里也不该有。」']],
            ['对不上的数', ['缺口', '对不上', '账'], ['他把算盘拨了一下：「单子上的数，你自己看。」']],
        ],
        'bai' => [
            ['体检', ['体检', '抽血', '指标'], ['「接触过配比的人，三个月查一次。」',
                                               '「你上次的指标我留着，别当没这回事。」']],
            ['当年的旧账', ['三年前', '当年', '旧事'], ['「那是三年前的事了。你要真想问，先跟康明远说一声。」']],
        ],
        'wei' => [
            ['车辆', ['车', '摩托', '越野'], ['「车况你自己摸过没有？别等抛在路上。」']],
            ['旧零件', ['零件', '备件', '旧料'], ['「旧电瓶留了两只，你要铅板就直接拆。」']],
        ],
        /* 镇上 */
        'town_coop' => [['供销社的货', ['进货', '货', '价'], ['「乡下的东西，价都在牌子上。」']]],
        'town_clinic' => [['病人的事', ['病人', '症状', '铅'], ['医生把本子合上：「有的病人不肯说。」']]],
        'town_barber' => [['镇上的门道', ['门道', '路子', '谁管事'], ['师傅把剪子放下：「你要听实在的？」']]],
    ];
}

add_action('init', function () {
    if (!function_exists('crrg_game_register_many')) return;
    crrg_game_register_many('npc', [
        'roster'   => crrg_game_npc_roster_data(),
        'missions' => crrg_game_npc_missions_data(),
    ], ['module' => 'core', 'file' => __FILE__]);
}, 21);
