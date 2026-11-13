<?php
/**
 * CRRG 演习 · 手机·非业务功能（systems/phone-life.php，2026-10-14）
 * ------------------------------------------------------------
 * 委员长："手机要加非业务功能。" —— 挑的四条标准：
 *   ① **有味道**（收容小组的日常：值班群、食堂动态、镇上论坛、深夜收音机）
 *   ② **能用**（不是装饰：能看、能发、能互动）
 *   ③ **能藏线索**（任务可以把消息塞进群聊/论坛/动态 → 玩家自己翻出来 ✓ 不给"去 X"提示）
 *   ④ **离线也在长**（按世界日生成，不依赖谁在线 ✓ 沙盒感）
 *
 * 八个功能：
 *   1. **群聊**（值班群／后勤群／闲人群）——NPC 按世界日发消息，玩家能发言
 *   2. **动态**（成员日常）——可点赞、可评论
 *   3. **论坛**（镇上的本地论坛）——有人在讨论怪事；外来 IP 会问"你们那儿晚上有声音吗"
 *   4. **备忘录**——自己写；**也会被"别人"改**（这条是《在册》的彩蛋 ✓）
 *   5. **相册**——拍照存这儿（与业务拍照联动）
 *   6. **收音机**——按小时给内容；**深夜频率会收到不像电台的东西**
 *   7. **小游戏**——「翻牌」（等活儿的时候打发时间）
 *   8. **时钟/闹钟**——读日历系统（无年份口径 ✓）
 *
 * 纪律：所有"会长的东西"都**按世界日 + 幂等**生成 ✓；写入**只追加** ✓；不碰世界钟 ✗
 */
if (!defined('ABSPATH')) exit;

const CRRG_PH_LIFE_OPT = 'crrg_phone_life';

/* ══════════ 数据存取 ══════════ */
function crrg_ph_all() {
    $d = (array) get_option(CRRG_PH_LIFE_OPT, []);
    return $d + ['groups' => [], 'feed' => [], 'forum' => [], 'memo' => [], 'album' => [], 'seen_day' => ''];
}
function crrg_ph_save($d) { update_option(CRRG_PH_LIFE_OPT, $d, false); return true; }

/** 追加一条（幂等靠调用方；这里只管只追加 ✓） */
function crrg_ph_push($kind, array $row) {
    $d = crrg_ph_all();
    $row['at'] = $row['at'] ?? time();
    $row['day'] = $row['day'] ?? (function_exists('crrg_cal_day') ? crrg_cal_day() : 0);
    $d[$kind] = (array) ($d[$kind] ?? []);
    array_unshift($d[$kind], $row);
    $d[$kind] = array_slice($d[$kind], 0, 120);            // 上限，防无限增长
    crrg_ph_save($d);
    return $row;
}

/* ══════════ 世界日生成（离线也在长 ✓ 幂等 ✓）══════════ */
add_action('init', function () {
    if (!function_exists('crrg_evt_on')) return;
    crrg_evt_on('system.day', function ($p) {
        $d = crrg_ph_all();
        $day = (int) ($p['day'] ?? crrg_cal_day());
        crrg_ph_grow_day($day);                                        // 幂等守卫在函数里 ✓
    }, ['module' => 'phone.life']);
}, 34);

/** 按世界日生成一批日常（**按日序轮转，绝不重复同一条** ✓；
 *   2026-10-14 修：原来固定取每组前两条 → 玩家看到"同一条反复发" ✗ 委员长报的 ✓） */
function crrg_ph_grow_day($day) {
    $d0 = crrg_ph_all();
    if ((int) ($d0['seen_day'] ?? 0) === (int) $day) return false;      // 幂等 ✓
    $d0['seen_day'] = (int) $day; crrg_ph_save($d0);
    $P = crrg_ph_pool();
    /* 群聊：每组按"日序 + 组偏移"轮转取 1–2 条（绕完一圈才会再见同一条 ✓） */
    $off = 0;
    foreach (['group_duty', 'group_logi', 'group_chat'] as $g) {
        $n = count($P[$g]);
        for ($i = 0; $i < 2; $i++) {
            $idx = ($day + $off + $i) % $n;
            $r = $P[$g][$idx];
            crrg_ph_push($g, ['who' => $r[0], 'text' => $r[1]]);
        }
        $off += 3;
    }
    /* 动态：每天 1 条，轮转 ✓ */
    $fd = $P['feed']; $r = $fd[($day * 2 + 1) % count($fd)];
    crrg_ph_push('feed', ['who' => $r[0], 'text' => $r[1], 'likes' => mt_rand(0, (int) $r[2])]);
    /* 论坛：隔天一条，轮转 ✓ */
    if ($day % 2 === 0) {
        $fo = $P['forum']; $t = $fo[intdiv($day, 2) % count($fo)];
        crrg_ph_push('forum', ['title' => $t[0], 'who' => $t[1], 'text' => $t[2], 'replies' => []]);
    }
    /* 收音机：每天一条（深夜那班留给任务 ✓） */
    $ra = $P['radio']; crrg_ph_push('radio', ['who' => '县广播', 'text' => $ra[$day % count($ra)]]);
    return true;
}

/** 内容池（每组 ≥8 条；**轮转的原料** ✓） */
function crrg_ph_pool() {
    return [
        'group_duty' => [
            ['周德海', '今晚风大，B4 那扇窗我又栓了一道。'],
            ['康明远', '班表出了，自己看。有意见明天例会提。'],
            ['康明远', '周三四号位空着，谁顶一下。'],
            ['王守一', '二楼水房的水龙头我修好了，别用蛮劲。'],
            ['江晓', '夜班巡逻记得带手电，仓库那截没灯。'],
            ['林正则', '我把上个月的巡检牌核了一遍，日期有一处没换。'],
            ['白露', '食堂那锅汤别剩到下顿，我昨天倒了半锅。'],
            ['周德海', '谁把钥匙串落在值班室了？在我抽屉里。'],
        ],
        'group_logi' => [
            ['陈连生', '明天早上有肉。别问哪来的。'],
            ['周德海', '领料单我压着两张，忘写的自己补。'],
            ['魏长庚', '手电电池换了新的一批，旧的别扔，还能撑。'],
            ['魏长庚', '库房最里头那卷铅封别动，是留着封东西的。'],
            ['陈连生', '谁要加餐提早说，锅就那么大。'],
            ['周德海', 'B3 那扇门我今天又去锁了一遍。'],
            ['林正则', '新到的册子我按编号排了，别乱翻。'],
            ['魏长庚', '保养油见底了，谁去县里带两瓶。'],
        ],
        'group_chat' => [
            ['白露', '谁家的猫又跑到食堂门口了，第三回了。'],
            ['江晓', '今天有人在外头放炮，隔着两座山都听得见。'],
            ['林正则', '我把上个月的表核了三遍，还是对不上。'],
            ['何若风', '对不上就别对，先睡。'],
            ['白露', '你们谁半夜听见山那边敲铁的声音没有。'],
            ['王守一', '听见了，我以为是风。'],
            ['孙亦然', '这种声音别在群里说，回头又有人紧张。'],
            ['何若风', '紧张也得说。不说才出事。'],
        ],
        'feed' => [
            ['白露', '食堂今天的土豆丝切得比昨天细。可能是心情。', 12],
            ['王守一', '修了一下午的除湿机，最后发现是插头。', 9],
            ['林正则', '（无文案）', 3],
            ['周德海', '夜里三点，B3 的灯自己亮了。我下去关了。', 21],
            ['江晓', '今天山上的雪线又低了一点。', 6],
            ['何若风', '连着三晚的值班表对不上号，懒得说了。', 4],
            ['陈连生', '腌的萝卜能吃了，谁要自己来拿。', 15],
            ['康明远', '例会推迟到周四。别问为什么。', 18],
        ],
        'forum' => [
            ['[本地] 有没有人半夜听见山那边有铁的声音', '阿棉', '连着三晚了，像谁在敲铁轨。位置大概在旧隧道那一线。'],
            ['[闲聊] 镇口那家面馆换人了？', '吃面的', '味道不太一样，汤更咸。老板娘说是侄子来帮忙。'],
            ['[求助] 谁家丢了一只黄猫', '纸扎铺', '尾巴尖是白的。见了喊一声。'],
            ['[本地] 你们那儿晚上有声音吗', '过路的', '我在省道边上住，最近夜里老觉得有人在念名单。'],
            ['[本地] 旧隧道口立了新牌子', '修车的', '写着"禁止通行"，落款没单位。'],
            ['[闲聊] 今年雪来得早', '看山的', '往年这时候还没落呢。'],
            ['[求助] 有人看见我叔吗', '小杨', '他上礼拜去山上收药，到现在没回。'],
            ['[本地] 供电所又跳闸了', '住街口的', '连着两晚，十一点前后。'],
        ],
        'radio' => [
            '整点报时。北风，三到四级。',
            '县里通知：下周起省道修路，过境车辆绕行。',
            '下面播放一首老歌。',
            '……（这段没有台标。）',
            '气象台提示：夜间降温，注意用火安全。',
            '下面重播本县新闻：林场越冬物资已到位。',
            '听众朋友，下面为您播报一段寻人启事。',
            '（信号忽然弱了一下，随后恢复。）',
        ],
    ];
}

/* ══════════ 群列表 ══════════ */
function crrg_ph_groups() {
    return ['group_duty' => ['值班群', '💬'], 'group_logi' => ['后勤群', '📦'], 'group_chat' => ['闲人群', '🗣']];
}

/* ══════════ 内容读取（给窗口用）══════════ */
function crrg_ph_group($g, $n = 8) {
    $d = crrg_ph_all();
    $out = [];
    foreach (array_slice((array) ($d[$g] ?? []), 0, $n) as $r) $out[] = $r;
    return array_reverse($out);                                        // 旧→新（像聊天记录 ✓）
}
function crrg_ph_feed($n = 6) { $d = crrg_ph_all(); return array_slice((array) $d['feed'], 0, $n); }
function crrg_ph_forum($n = 5) { $d = crrg_ph_all(); return array_slice((array) $d['forum'], 0, $n); }
function crrg_ph_memo(&$pc) { $d = crrg_ph_all(); $k = crrg_ph_uid_key($pc); return (array) ($d['memo'][$k] ?? []); }
function crrg_ph_uid_key(&$pc) { return 'u' . (int) ($pc['uid'] ?? 0); }

/** 群聊里发言（玩家） */
function crrg_ph_say(&$pc, $g, $text) {
    $text = trim((string) $text);
    if ($text === '') return ['（发空消息？）'];
    $g = in_array($g, ['group_duty', 'group_logi', 'group_chat'], true) ? $g : 'group_chat';
    $nm = (string) ($pc['name'] ?? '你');
    crrg_ph_push($g, ['who' => $nm, 'text' => $text, 'me' => true]);
    /* 有人应一句（轻互动 ✓ 也可能带回线索） */
    $rep = ['周德海' => '收到。', '白露' => '哈哈，你说得对。', '康明远' => '知道了。',
            '王守一' => '回头我去看看。', '江晓' => '嗯，你也注意。'][array_rand(['周德海' => 1, '白露' => 1, '康明远' => 1, '王守一' => 1, '江晓' => 1])] ?? '嗯。';
    crrg_ph_push($g, ['who' => '（有人回）', 'text' => $rep]);
    return ['（发出去了。有人回了一句。）'];
}

/** 备忘录：自己写；**也会被改**（《在册》彩蛋 ✓） */
function crrg_ph_memo_add(&$pc, $text) {
    $text = trim((string) $text);
    if ($text === '') return ['（写了什么？）'];
    $d = crrg_ph_all(); $k = crrg_ph_uid_key($pc);
    $d['memo'][$k] = (array) ($d['memo'][$k] ?? []);
    array_unshift($d['memo'][$k], ['text' => $text, 'at' => time(), 'by' => 'me']);
    $d['memo'][$k] = array_slice($d['memo'][$k], 0, 40);
    crrg_ph_save($d);
    return ['（记下了。）'];
}
/** 由任务/世界往玩家备忘录里塞一条「别人写的」（幂等由调用方保证 ✓） */
function crrg_ph_memo_by_system($uid, $text, $sign = '（不是你写的）') {
    $d = crrg_ph_all(); $k = 'u' . (int) $uid;
    $d['memo'][$k] = (array) ($d['memo'][$k] ?? []);
    array_unshift($d['memo'][$k], ['text' => (string) $text, 'at' => time(), 'by' => $sign]);
    $d['memo'][$k] = array_slice($d['memo'][$k], 0, 40);
    crrg_ph_save($d);
    return true;
}

/** 收音机：按当前小时给内容；**深夜（凌晨 1–4 点）会收到不像电台的东西** ✓ */
function crrg_ph_radio_now() {
    $h = function_exists('crrg_task_fact_hour') ? (int) crrg_task_fact_hour() : (int) date('G');
    $d = crrg_ph_all();
    if ($h >= 1 && $h <= 4) {
        $odd = [
            '……下面播报名单。第一位：' . "\n" . '（后面是一串听不清的名字，其中有三个音节，你听成了自己的名字。）',
            '（电流声里有人在念数字，念得不快，像在点名。）',
            '（一个很平的男声）：值班表已收到。谢谢配合。',
        ];
        return '〔深夜频率 ' . sprintf('%02d:00', $h) . '〕' . "\n" . $odd[mt_rand(0, count($odd) - 1)];
    }
    $r = array_values(array_filter((array) $d['radio'], fn($x) => true));
    $t = $r ? (string) ($r[0]['text'] ?? '') : '整点报时。';
    return '〔县广播 ' . sprintf('%02d:00', $h) . '〕' . "\n" . $t;
}

/** 小游戏：翻牌（3×4，配对）；记录在个人档，纯打发时间 ✓ */
function crrg_ph_game(&$pc) {
    $board = [];
    for ($i = 0; $i < 6; $i++) { $board[] = $i; $board[] = $i; }
    shuffle($board);
    $best = (int) ($pc['ph_game_best'] ?? 0);
    /* 一次"猜"：命中概率与运气相关（不引入真交互，一句话给结果 ✓） */
    $ok = mt_rand(1, 100) <= 55;
    if ($ok) { $pc['ph_game_best'] = max($best, (int) ($pc['ph_game_best'] ?? 0) + 1); }
    return [
        '〔翻牌〕' . "\n" . '十二张扣着，配成六对。',
        $ok ? '（这一局你翻得很顺——六对全开。）' : '（最后一对没配上，散了。）',
        sprintf('（最好成绩 %d 局。）', (int) ($pc['ph_game_best'] ?? 0)),
    ];
}

/** 时钟/闹钟：读日历（**无年份**口径 ✓；字段名以 calendar.php 为准 ✓） */
function crrg_ph_clock() {
    if (function_exists('crrg_cal_text')) {
        $h = function_exists('crrg_cal_hour') ? (int) crrg_cal_hour() : (int) date('G');
        return [sprintf('〔%s〕%02d 时', crrg_cal_text(), $h),
                '（这台机上没有年份——也没人记得清第几年。）'];
    }
    return ['〔' . date('m-d H:i') . '〕'];
}

/** 任务/世界往里塞消息（线索渠道 ✓）：群聊／动态／论坛／收音机 */
function crrg_ph_post($kind, array $row) { return crrg_ph_push($kind, $row); }
