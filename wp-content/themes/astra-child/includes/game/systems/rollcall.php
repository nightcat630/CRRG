<?php
/**
 * CRRG 演习 · 点名（systems/rollcall.php，2026-10-19）
 * ------------------------------------------------------------
 * 委员长口径（原话）：
 *   · "报到有 **2 积分** 奖励"
 *   · "连着 **三十天** 报到 **+10 声望**"
 *   · "**惩罚就不要了**"
 *   · "**NPC 点名和作息也要加入**"
 *
 * 机制：
 *   ① 每天 **07:00–07:59** 在**作战值班室**「报到」→ **积分 +2**（每日一次 ✓ 幂等）
 *   ② 连续天数记在个人档（`roll_last` 日序 ＋ `roll_streak`）→ **满 30 天给一次声望 +10**（每满 30 天一次 ✓）
 *   ③ **没有惩罚** ✗ —— 没来就没来，只记账不给后果 ✓（源码与真跑都验：不出现任何扣分/扣声望 ✓）
 *   ④ 「点名」列出 **NPC 名册**：按各自**作息**（`crrg_actor_where()` ✓）判"在／未到"，以及**值班表今日空岗**
 *      —— NPC 缺勤**只记录** ✓ 不罚 ✓
 */
if (!defined('ABSPATH')) exit;

const CRRG_RC_HOUR   = 7;          // 开始小时
const CRRG_RC_ROOM   = 'b2b_duty'; // 作战值班室
const CRRG_RC_BONUS  = 30;         // 连续天数奖励
const CRRG_RC_REP    = 10;         // 奖励声望

/** 报到时间段：07:00–07:59 ✓ 之外一律不受理（**不罚** ✓ 只是记不了） */
function crrg_rc_open() {
    /* 只在测试里放开小时限制（门禁要真跑报到 ✓ 生产环境不定义该常量 ✗ 不受影响） */
    if (defined('CRRG_RC_TEST') && CRRG_RC_TEST) return true;
    $h = function_exists('crrg_cal_hour') ? (int) crrg_cal_hour() : (int) date('G');
    return $h === CRRG_RC_HOUR;
}
/** 今天报过到没有（个人档 ✓ 幂等） */
function crrg_rc_done(&$s) {
    $day = function_exists('crrg_cal_day') ? (int) crrg_cal_day() : (int) date('z');
    return in_array('roll_' . $day, (array) ($s['flags'] ?? []), true);
}

/**
 * 报到（**只有奖励，没有惩罚** ✓）
 */
function crrg_rc_checkin(&$s, &$pc) {
    $day = function_exists('crrg_cal_day') ? (int) crrg_cal_day() : (int) date('z');
    $room = (string) ($s['room'] ?? '');
    $out = [];

    if ($room !== CRRG_RC_ROOM) {
        return ['（报到处在作战值班室。走两步的事。）'];
    }
    if (!crrg_rc_open()) {
        $h = function_exists('crrg_cal_hour') ? (int) crrg_cal_hour() : (int) date('G');
        return [sprintf('（点名是每天 %02d:00 那一小时。现在是 %02d 点——不是点名的时候。）', CRRG_RC_HOUR, $h)];
    }
    if (crrg_rc_done($s)) {
        return ['（今天已经报过到了。）'];
    }

    /* ① 积分 +2 ✓ */
    $pc['credits'] = (int) ($pc['credits'] ?? 0) + 2;
    $s['flags'][] = 'roll_' . $day;

    /* ② 连续天数（断一天就重新数 ✓ 不惩罚 ✗） */
    $last   = (int) ($pc['roll_last'] ?? 0);
    $streak = (int) ($pc['roll_streak'] ?? 0);
    if ($last === $day - 1) $streak++;
    else                    $streak = 1;
    $pc['roll_last']   = $day;
    $pc['roll_streak'] = $streak;

    $out[] = '〔报到〕到。';
    $out[] = '（积分 +2。）';
    $out[] = sprintf('（连续 %d 天。）', $streak);

    /* ③ 满 30 天 → 声望 +10（每满 30 天一次 ✓ 幂等） */
    if ($streak >= CRRG_RC_BONUS && $streak % CRRG_RC_BONUS === 0) {
        $mark = 'roll_rep_' . $streak;
        if (!in_array($mark, (array) $s['flags'], true)) {
            $s['flags'][] = $mark;
            if (function_exists('crrg_rep_add')) {
                $n = crrg_rep_add($pc, 'base', CRRG_RC_REP, '连续报到 ' . $streak . ' 天');
                $out[] = sprintf('〔连续 %d 天〕基地声望 +%d → %d。', $streak, CRRG_RC_REP, (int) $n);
            }
        }
    }
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.beat', ['kind' => 'rollcall', 'streak' => $streak, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'rollcall']);
    }
    return $out;
}

/**
 * 点名册：NPC 按**作息**判在／未到 ＋ 值班表今日空岗（**只记录，不惩罚** ✓）
 */
function crrg_rc_roster(&$s, &$pc) {
    $out = ['〔点名〕' . (function_exists('crrg_cal_text') ? crrg_cal_text() : '')];
    $room = (string) ($s['room'] ?? '');

    /* ① NPC：现在该在哪（crrg_actor_where 唯一口径 ✓） */
    if (function_exists('crrg_actor_roster')) {
        $here = 0; $away = [];
        foreach ((array) crrg_actor_roster() as $id => $d) {
            $at = function_exists('crrg_actor_where') ? (string) crrg_actor_where((string) $id) : '';
            $nm = (string) ($d['n'] ?? $id);
            if ($at === '') continue;                                  // 这名册里没给房 → 不点他 ✓
            if ($at === $room) { $here++; $out[] = sprintf('　✓ %s（在）', $nm); }
            else $away[] = sprintf('%s（在%s）', $nm, crrg_rc_room_name($at));
        }
        if ($away) {
            $out[] = '　—— 未到（按各自作息，这会儿在别处；只记，不罚）：';
            foreach (array_slice($away, 0, 12) as $x) $out[] = '　　' . $x;
        }
        $out[] = sprintf('（在值班室 %d 人。）', $here);
    }

    /* ② 值班表：今天空着的岗（duty ✓ 只列出来 ✓ 不罚 ✓） */
    if (function_exists('crrg_duty_vacancies')) {
        $v = (array) crrg_duty_vacancies();
        $posts = function_exists('crrg_duty_posts') ? (array) crrg_duty_posts() : [];
        if ($v) {
            $names = [];
            foreach ($v as $p) $names[] = (string) ($posts[$p]['n'] ?? $p);
            $out[] = '〔今日空岗〕' . implode('、', $names) . '（没人认领的格子——不扣谁，只是摆在这儿。）';
        } else {
            $out[] = '〔今日空岗〕没有，表是齐的。';
        }
    }

    /* ③ 玩家自己的连续天数（看得见） */
    $out[] = sprintf('（你自己的连续报到：%d 天；满 %d 天有一次声望 +%d。）',
        (int) ($pc['roll_streak'] ?? 0), CRRG_RC_BONUS, CRRG_RC_REP);
    return $out;
}

/** 房间名（全场景查 ✓ 县城/甘州/镇上的房间不在当前场景里） */
function crrg_rc_room_name($room) {
    $room = (string) $room;
    if (function_exists('crrg_drill_rooms')) {
        $r = crrg_drill_rooms();
        if (isset($r[$room]['t'])) return (string) $r[$room]['t'];
    }
    $R = &crrg_drill_reg();
    foreach ((array) ($R['scenes'] ?? []) as $sc) {
        if (isset($sc['rooms'][$room]['t'])) return (string) $sc['rooms'][$room]['t'];
    }
    return $room;
}
