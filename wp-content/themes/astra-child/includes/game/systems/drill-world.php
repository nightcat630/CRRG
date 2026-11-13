<?php
/**
 * 世界时间与天气（2026-09-28）
 * 时间流速 1 : 48 —— 现实 30 分钟 = 游戏 1 天（现实 1 秒 = 游戏 48 秒）。
 * 时钟是全球统一的（所有人看到同一个时间），天气按「游戏日 + 时段 + 地域」确定性生成。
 * 天气与时段会进程检定：雨雾沙尘压侦查与射击，雪天反而留脚印，夜里没灯就摸黑。
 */
if (!defined('ABSPATH')) exit;

const CRRG_CLOCK_OPT = 'crrg_drill_clock';
/** 用站点时区格式化（WP 会把 PHP 时区设成 UTC，直接用 date() 会差一个时区） */
function crrg_wdate($fmt, $ts) {
    if (function_exists('wp_date')) return wp_date($fmt, $ts);
    return date($fmt, $ts + (int) (get_option('gmt_offset', 0) * 3600));
}
const CRRG_TIME_SCALE = 48;      // 现实 1 秒 = 游戏 48 秒

/** 世界时钟 */
function crrg_world_clock() {
    $o = get_option(CRRG_CLOCK_OPT, null);
    if (!is_array($o) || empty($o['real'])) {
        /* 选项缺失：优先用备份恢复，避免"重建＝时间倒回现实时间" */
        $b = get_option(CRRG_CLOCK_OPT . '_backup', null);
        if (is_array($b) && !empty($b['game'])) {
            $o = ['real' => time(), 'game' => (int) $b['game'], 'day0' => (int) ($b['day0'] ?? $b['game']), 'day0_day' => (int) ($b['day0_day'] ?? 1)];
        } else {
            $o = ['real' => time(), 'game' => time(), 'day0' => time(), 'day0_day' => 1];
        }
        update_option(CRRG_CLOCK_OPT, $o, false);
    }
    $g   = (int) $o['game'] + (int) round((time() - (int) $o['real']) * CRRG_TIME_SCALE);
    /* 天数按**起点日**算，不随锚点重写而回卷（2026-09-29 修） */
    if (empty($o['day0'])) { $o['day0'] = (int) $o['game']; $o['day0_day'] = 1; update_option(CRRG_CLOCK_OPT, $o, false); }
    $day = (int) floor(($g - (int) $o['day0']) / 86400) + (int) ($o['day0_day'] ?? 1);
    if ($day < 1) $day = 1;
    $h   = (int) crrg_wdate('G', $g);
    $phase = $h < 5 ? '深夜' : ($h < 8 ? '黎明' : ($h < 11 ? '上午' : ($h < 13 ? '正午'
           : ($h < 17 ? '下午' : ($h < 19 ? '黄昏' : ($h < 23 ? '夜' : '深夜'))))));
    if ($day === 1 && $h < 5) $phase = '深夜';
    return ['ts' => $g, 'ymd' => crrg_wdate('Y-m-d', $g), 'hm' => crrg_wdate('H:i', $g), 'h' => $h,
            'day' => $day, 'phase' => $phase, 'night' => ($h >= 20 || $h < 5),
            'scale' => CRRG_TIME_SCALE];
}
/** 安全设置游戏时间（任何需要推进时间的代码都该走这里）
 *  规则：向前推进照做；向后回退超过 1 小时的一律忽略（防"日期回卷"） */
function crrg_world_set_game($gameTs, $why = '') {
    $o = get_option(CRRG_CLOCK_OPT, null);
    if (!is_array($o) || empty($o['real'])) { crrg_world_clock(); $o = get_option(CRRG_CLOCK_OPT, null); }
    $live = (int) $o['game'] + (int) round((time() - (int) $o['real']) * CRRG_TIME_SCALE);
    if ($gameTs < $live - 3600) return ['ok' => false, 'live' => $live, 'why' => 'ignored-backward'];
    update_option(CRRG_CLOCK_OPT . '_backup', ['game' => (int) $gameTs, 'day0' => (int) ($o['day0'] ?? $gameTs), 'day0_day' => (int) ($o['day0_day'] ?? 1)], false);
    update_option(CRRG_CLOCK_OPT, ['real' => time(), 'game' => (int) $gameTs,
        'day0' => (int) ($o['day0'] ?? $gameTs), 'day0_day' => (int) ($o['day0_day'] ?? 1)], false);
    return ['ok' => true, 'live' => (int) $gameTs, 'why' => $why];
}
/** 带起点日的设置器（仅用于修复：把游戏时间与"第 N 天"的起点一起校准） */
function crrg_world_set_origin($gameTs, $day0Ts, $day0Day = 1) {
    update_option(CRRG_CLOCK_OPT . '_backup', ['game' => (int) $gameTs, 'day0' => (int) $day0Ts, 'day0_day' => (int) $day0Day], false);
    update_option(CRRG_CLOCK_OPT, ['real' => time(), 'game' => (int) $gameTs, 'day0' => (int) $day0Ts, 'day0_day' => (int) $day0Day], false);
    return crrg_world_clock();
}
/** 现实还剩多少秒到下一个游戏整点（给玩家一个"时间在走"的感知） */
function crrg_world_next_hour_in() {
    $c = crrg_world_clock();
    $left = 3600 - ((int) crrg_wdate('i', $c['ts']) * 60 + (int) crrg_wdate('s', $c['ts']));
    return max(1, (int) ceil($left / CRRG_TIME_SCALE));
}

/** 天气：按游戏日 + 6 小时时段 + 地域确定性生成（所有人一致） */
function crrg_world_weather($scene = 'base') {
    $c    = crrg_world_clock();
    $slot = (int) floor($c['h'] / 6);
    $seed = crc32($c['ymd'] . '#' . $slot . '#' . $scene);
    $mon  = (int) crrg_wdate('n', $c['ts']);
    $winter = ($mon <= 2 || $mon >= 11);
    $summer = ($mon >= 6 && $mon <= 8);
    $r = $seed % 100;
    // 西北内陆：晴多雨少；冬有雪，夏有雷雨
    if ($winter) {
        if ($r < 45) return ['k' => 'clear',  'n' => '晴',    'd' => '天很亮，风冷得割脸。'];
        if ($r < 65) return ['k' => 'cloud',  'n' => '多云',  'd' => '云压得低，看不见太阳。'];
        if ($r < 80) return ['k' => 'snow',   'n' => '小雪',  'd' => '细雪斜着落，地上薄薄一层。'];
        if ($r < 90) return ['k' => 'wind',   'n' => '大风',  'd' => '风从坡上刮下来，带着土腥味。'];
        return ['k' => 'snow', 'n' => '大雪', 'd' => '雪片很密，几步之外看不清。'];
    }
    if ($summer) {
        if ($r < 40) return ['k' => 'clear',  'n' => '晴',    'd' => '日头毒，影子很短。'];
        if ($r < 60) return ['k' => 'cloud',  'n' => '多云',  'd' => '云走得快。'];
        if ($r < 75) return ['k' => 'rain',   'n' => '阵雨',  'd' => '雨来得急，砸在土上冒烟。'];
        if ($r < 88) return ['k' => 'storm',  'n' => '雷雨',  'd' => '雷在坡后头滚，雨大得看不清路。'];
        return ['k' => 'fog', 'n' => '雾', 'd' => '不知道哪来的潮气，一路白雾。'];
    }
    if ($r < 35) return ['k' => 'clear', 'n' => '晴',   'd' => '天色干净，能看见远处山棱。'];
    if ($r < 60) return ['k' => 'cloud', 'n' => '多云', 'd' => '云一层压一层。'];
    if ($r < 72) return ['k' => 'overcast', 'n' => '阴', 'd' => '一整天都像傍晚。'];
    if ($r < 84) return ['k' => 'rain',  'n' => '小雨', 'd' => '雨丝很细，一会就湿透了。'];
    if ($r < 92) return ['k' => 'wind',  'n' => '大风', 'd' => '风把草吹得倒伏。'];
    return ['k' => 'fog', 'n' => '雾', 'd' => '雾从河边起来，两三步外看不真。'];
}
/** 是不是在露天（露天才吃天气） */
function crrg_world_outdoor($room) {
    /* 镇上的室内房间（有屋顶的铺面与人家） */
    $townIn = ['tw_coop','tw_grain','tw_noodle','tw_hardware','tw_bank','tw_inn','tw_station','tw_post',
               'tw_book','tw_photo','tw_clinic','tw_school','tw_townhall','tw_temple','tw_homes','tw_junk',
               'tw_farm','tw_gas','tw_barber','tw_tailor','tw_repair','tw_bath','tw_vet','tw_mill','tw_club',
               'tw_watch','tw_li_house','tw_li_study','tw_press','tw_police','tw_culture'];
    if (in_array($room, $townIn, true)) return false;
    /* 基地所有房间都在山体内，一律室内（此前漏了这条，宿舍、指挥大厅都被当成露天） */
    if (preg_match('/^(b1_|b2f_|b2b_|b3_|b4_|garage|stair|freight|qrf|b1t|b2t|b3t)/', (string) $room)) return false;
    /* 其余：镇上街道、村口、镇口、山脚、野外山林——露天 */
    return true;
}
/** 天气／时段对某项检定的修正 → [奖励骰, 惩罚骰, 说明] */
function crrg_world_mod($room, $skill, $pc = null) {
    $b = 0; $p = 0; $why = [];
    if (!crrg_world_outdoor($room)) return [0, 0, []];
    $c = crrg_world_clock();
    $w = crrg_world_weather('town');
    $k = $w['k'];
    if (in_array($k, ['rain', 'storm'], true)) {
        if (in_array($skill, ['侦查', '追踪'], true)) { $p++; $why[] = $w['n'] . '冲刷痕迹 −1'; }
        if (in_array($skill, ['手枪', '步枪霰弹枪', '射击'], true)) { $p++; $why[] = $w['n'] . '影响视线 −1'; }
        if (in_array($skill, ['潜行', '躲藏'], true)) { $b++; $why[] = $w['n'] . '盖住动静 +1'; }
    }
    if ($k === 'fog') {
        if (in_array($skill, ['侦查', '聆听', '导航'], true)) { $p++; $why[] = '雾里看不清 −1'; }
        if (in_array($skill, ['手枪', '步枪霰弹枪', '射击'], true)) { $p++; $why[] = '雾中射击 −1'; }
    }
    if (in_array($k, ['wind', 'sand'], true)) {
        if (in_array($skill, ['手枪', '步枪霰弹枪', '射击'], true)) { $p++; $why[] = '风偏 −1'; }
        if ($skill === '追踪') { $p++; $why[] = '风盖住痕迹 −1'; }
    }
    if ($k === 'snow') {
        if ($skill === '追踪') { $b++; $why[] = '雪地上脚印清楚 +1'; }
        if ($skill === '导航') { $p++; $why[] = '雪里认不得路 −1'; }
    }
    if ($c['night'] && in_array($skill, ['侦查', '射击', '手枪', '步枪霰弹枪', '追踪'], true)) {
        $lit = false;
        if ($pc) foreach (array_merge((array) ($pc['items'] ?? []), (array) ($pc['worn'] ?? [])) as $it)
            if (in_array($it, ['tool_flashlight', 'tool_lamp'], true)) { $lit = true; break; }
        if (!$lit) { $p++; $why[] = '夜里没灯 −1'; }
    }
    return [$b, $p, $why];
}
/** 下一个天气时段（每 6 小时）边界的游戏时间戳 */
function crrg_world_slot_end() {
    $c = crrg_world_clock();
    $ts = (int) $c['ts'];
    $h  = (int) crrg_wdate('G', $ts);
    $dayStart = $ts - ($h * 3600 + (int) crrg_wdate('i', $ts) * 60 + (int) crrg_wdate('s', $ts));
    $slot = (int) floor($h / 6);
    return $dayStart + ($slot + 1) * 6 * 3600;
}

/** 商店营业时间 07:00–20:00 */
function crrg_world_shop_open() { $h = crrg_world_clock()['h']; return ($h >= 7 && $h < 20); }
/** 一句话描述（给状态栏与房间） */
function crrg_world_line() {
    $c = crrg_world_clock();
    $w = crrg_world_weather('town');
    /* 2026-10-14（第 2 轮）：**不再显示年份/第 N 天** ✗（委员长口径：后期可能过去几千年，写年份不合理）
       → 只显示「月日 周几 · 时段」✓ 口径统一走 crrg_cal_text()（一处真相） */
    $when = function_exists('crrg_cal_text') ? crrg_cal_text() : $c['phase'];
    return sprintf('%s %s · %s ｜ 天气：%s', $when, $c['hm'], $c['phase'], $w['n']);
}
