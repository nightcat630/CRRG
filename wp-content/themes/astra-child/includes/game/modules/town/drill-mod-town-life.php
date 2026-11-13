<?php
/**
 * 青石镇生活层（2026-09-28）
 *   新增可进入建筑 8 处 + 旅社住宿 + 澡堂/理发/修车等服务
 *   口径：一切都按账号走（花费、当日次数都写在本账号的旗标里），不影响别人
 */
if (!defined('ABSPATH')) exit;

crrg_drill_scene_extend('town', [
    'tw_barber' => ['t' => '理发店', 'f' => '镇上', 'c' => 1,
        'd' => '两把铁椅、一面起雾的镜子，墙上贴着几张过期的挂历。师傅手里剪子不停，嘴也不停。',
        'x' => ['主街·西段' => 'tw_main_w']],
    'tw_tailor' => ['t' => '裁缝铺', 'f' => '镇上', 'c' => 1,
        'd' => '案板上摊着裁开的布，墙上挂着成衣和几卷帆布。缝纫机是脚踩的老式机子。',
        'x' => ['主街·西段' => 'tw_main_w']],
    'tw_repair' => ['t' => '修车铺', 'f' => '镇上', 'c' => 1,
        'd' => '一个地沟、两台风炮，门口停着半辆拆开的皮卡。地上到处是油。',
        'x' => ['主街·东段' => 'tw_main_e']],
    'tw_bath' => ['t' => '澡堂', 'f' => '镇上', 'c' => 1,
        'd' => '澡堂子，进门是柜台和几排木头柜子，里头雾气腾腾。水声、说话声混在一起。',
        'x' => ['南巷' => 'tw_alley_s']],
    'tw_vet' => ['t' => '兽医站', 'f' => '镇上', 'c' => 1,
        'd' => '一间诊室加一个后院栏舍，院里拴着一头瘸腿的驴。药柜上摆着瓶瓶罐罐。',
        'x' => ['北巷' => 'tw_alley_n']],
    'tw_mill' => ['t' => '磨坊', 'f' => '镇上', 'c' => 1,
        'd' => '水磨还在转，石盘声音低。地上落着一层细白的面粉。',
        'x' => ['河沿路' => 'tw_river']],
    'tw_club' => ['t' => '文化活动室', 'f' => '镇上', 'c' => 1,
        'd' => '四张方桌，一桌在打牌，两桌在看。墙角一台旧电视，声音开得很小。',
        'x' => ['十字口' => 'tw_main_c']],
    'tw_watch' => ['t' => '联防队小屋', 'f' => '镇上', 'c' => 1,
        'd' => '一间平房，墙上贴着手写的巡逻表，桌上压着两本登记簿。有人在炉子边烤手。',
        'x' => ['主街·东段' => 'tw_main_e']],
], [
    'tw_barber' => [28, 56], 'tw_tailor' => [58, 56], 'tw_repair' => [178, 24],
    'tw_bath' => [116, 10], 'tw_vet' => [68, 72], 'tw_mill' => [74, 6],
    'tw_club' => [94, 30], 'tw_watch' => [186, 56],
], [
    'tw_barber' => [['n' => '理发师傅', 'k' => 'npc', 'c' => '问 理发师傅']],
    'tw_tailor' => [['n' => '裁缝', 'k' => 'npc', 'c' => '问 裁缝'], ['n' => '帆布', 'k' => 'item', 'c' => '查看 帆布']],
    'tw_repair' => [['n' => '修车师傅', 'k' => 'npc', 'c' => '问 修车师傅'], ['n' => '地沟', 'k' => 'item', 'c' => '查看 地沟']],
    'tw_bath' => [['n' => '澡堂老板', 'k' => 'npc', 'c' => '问 澡堂老板']],
    'tw_vet' => [['n' => '兽医', 'k' => 'npc', 'c' => '问 兽医'], ['n' => '瘸腿的驴', 'k' => 'item', 'c' => '查看 驴']],
    'tw_mill' => [['n' => '磨坊主', 'k' => 'npc', 'c' => '问 磨坊主'], ['n' => '石磨', 'k' => 'item', 'c' => '查看 石磨']],
    'tw_club' => [['n' => '打牌的老人', 'k' => 'npc', 'c' => '问 打牌的老人'], ['n' => '旧电视', 'k' => 'item', 'c' => '查看 电视']],
    'tw_watch' => [['n' => '联防队员', 'k' => 'npc', 'c' => '问 联防队员'], ['n' => '巡逻表', 'k' => 'item', 'c' => '查看 巡逻表']],
]);

/* ================= 住宿与服务 ================= */
function crrg_town_today() { return current_time('Y-m-d'); }
function crrg_town_did(&$s, $what) {
    $k = '今日_' . $what;
    return in_array($k . '_' . crrg_town_today(), (array) ($s['flags'] ?? []), true);
}
function crrg_town_mark(&$s, $what) {
    $k = '今日_' . $what . '_' . crrg_town_today();
    if (!in_array($k, (array) $s['flags'], true)) $s['flags'][] = $k;
}

/** 旅社住宿：一晚 80 元，回满生命、理智 +1（每日一次） */
function crrg_town_inn(&$s, &$pc) {
    if (($s['room'] ?? '') !== 'tw_inn') return [['住宿在旅社。']];
    if (crrg_town_did($s, '住店')) return [['今天已经住过了。前台说镇上就这一家，床位紧。']];
    if ((int) $pc['money'] < 80) return [['前台说一晚 80，钱不够。']];
    $pc['money'] -= 80;
    crrg_town_mark($s, '住店');
    if (function_exists('crrg_vitals_sleep')) {          // 与「睡」同一套：床上睡 8 小时
        $out = array_merge(['你付了 80 元，拿了钥匙上二楼。房间不大，被褥有股肥皂味。'],
            (array) crrg_vitals_sleep($pc, 8, 'bed'));
        $pc['vitals']['rest_bonus'] = ['san' => 0, 'hp' => 0];
        $out[] = sprintf('（结算点：游戏时间 %s ｜ 还需现实约 %d 分钟：精力回满、理智 +1、生命 +2。）',
            crrg_wdate('m-d H:i', (int) $pc['vitals']['rest_until']), (int) ceil((8 * 3600 / 48) / 60));
        $out[] = '（等不及就「起床」——已睡的部分照算；不必干等。）';
        return $out;
    }
    return [['前台没人。']];
}

/** 澡堂：15 元，理智 +1（每日一次） */
function crrg_town_bath(&$s, &$pc) {
    if (crrg_town_did($s, '洗澡')) return [['今天已经洗过了。']];
    if ((int) $pc['money'] < 15) return [['老板说十五块，钱不够。']];
    $pc['money'] -= 15;
    $d = crrg_coc_derive($pc['attrs']);
    $pc['san'] = min($d['san_max'], (int) $pc['san'] + 1);
    crrg_town_mark($s, '洗澡');
    $gossip = ['隔壁池子有人在说后坡的事。', '有人在骂今年的水费。', '两个跑车的在争路况。'];
    return ['你泡进热水里，肩膀松下来。', $gossip[array_rand($gossip)],
            sprintf('（理智 +1 → %d。）', $pc['san'])];
}

/** 理发：10 元，理智 +1（每日一次） */
function crrg_town_barber(&$s, &$pc) {
    if (crrg_town_did($s, '理发')) return [['师傅说你今天已经理过了，别浪费钱。']];
    if ((int) $pc['money'] < 10) return [['师傅说十块，钱不够。']];
    $pc['money'] -= 10;
    $d = crrg_coc_derive($pc['attrs']);
    $pc['san'] = min($d['san_max'], (int) $pc['san'] + 1);
    crrg_town_mark($s, '理发');
    return ['师傅一边推剪一边问东问西，你说得少，他说得多。',
            '「头发短了像换个人。」他拍拍你肩膀。',
            sprintf('（理智 +1 → %d。）', $pc['san'])];
}

/** 修车：有车才能做，30 元 */
function crrg_town_repair(&$s, &$pc) {
    if (!crrg_drill_has_vehicle($pc)) return [['师傅问你的车在哪。你说没车。他说那你来干嘛。']];
    if (crrg_town_did($s, '修车')) return [['今天已经拾掇过了。']];
    if ((int) $pc['money'] < 30) return [['师傅说三十块。']];
    $pc['money'] -= 30;
    crrg_town_mark($s, '修车');
    return ['他把' . $pc['vehicle'] . '升起来看了一遍，紧了几个螺丝，打了气。',
            '「你这车还行。跑长途的话，别贪快。」'];
}
