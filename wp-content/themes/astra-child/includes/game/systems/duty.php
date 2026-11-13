<?php
/**
 * CRRG 演习 · 值班表（systems/duty.php，P1.9c，2026-10-14）
 * ------------------------------------------------------------
 * 为什么单独立一个系统：《在册》这条线的**地基**就是"排班"——一个岗位、一行字、谁来值。
 *   现有游戏里"值班表"只是面板上的一句话 ✗ 没有数据结构、没有编制概念、没有空岗 ✗
 *
 * 三样东西：
 *   ① **岗位表**（posts）：值夜 / 巡逻 / 执勤长 / 后勤 / 通讯 —— 每个岗位有"在编人数"
 *   ② **一周值班表**（week）：周一..周日 × 岗位 → 谁（uid 或名字，或**空**）
 *   ③ **编制**（roster）：基地在编人员的名字（可含"不在场"的人）→ 空岗与"在册"由此而来
 *
 * 纪律：
 *   · 值班表按**游戏日/周**存，换日由世界推进器（bus system.day）触发滚动 ✓ 不另起定时任务 ✗
 *   · 写入**只追加版本**（`rev`），旧版留档 ✓ —— 谁改过表，查得到 ✓（这条线的核心就是"谁动了表"）
 *   · 玩家路径**不许**推世界钟 ✗（同引擎纪律）
 *
 * 与任务引擎的对接（engine/links.php 白名单）：
 *   need.duty_vacant（该岗位今天空着）／need.duty_is（今天这个岗是我）／need.roster_has（编制里有没有这个人）
 *   gain.duty_row（往表里写一行）／outcome.facts（写进公共事实 ✓）
 */
if (!defined('ABSPATH')) exit;

const CRRG_DUTY_OPT = 'crrg_duty';

/** 岗位表：id => [名称, 在编人数, 说明] */
function crrg_duty_posts() {
    return [
        'night'   => ['n' => '值夜', 'size' => 2, 'd' => 'B 区夜班，签到按巡检点走。'],
        'patrol'  => ['n' => '巡逻', 'size' => 2, 'd' => '地面与外围，两小时一圈。'],
        'chief'   => ['n' => '执勤长', 'size' => 1, 'd' => '当班负责人。签字、交接、兜底。'],
        'logi'    => ['n' => '后勤', 'size' => 1, 'd' => '库房与发料。'],
        'comm'    => ['n' => '通讯', 'size' => 1, 'd' => '登记簿与对讲机。'],
    ];
}

/** 基地编制（在编人员名单；可含"人不在场"的名字 ✓ 这是《在册》要用的） */
function crrg_duty_roster() {
    $r = (array) get_option(CRRG_DUTY_OPT . '_roster', []);
    if (!$r) {
        /* 初始编制：从 NPC 名册 + 值班体系里已有的名字搭 */
        $r = [];
        if (function_exists('crrg_game_npc_roster_data')) {
            foreach ((array) crrg_game_npc_roster_data() as $id => $d) {
                $r[$id] = ['n' => (string) ($d['n'] ?? $id), 'role' => (string) ($d['role'] ?? ''), 'state' => 'in'];
            }
        }
    }
    return $r;
}
function crrg_duty_roster_add($key, $name, $role = '', $state = 'in') {
    $r = crrg_duty_roster();
    $r[(string) $key] = ['n' => (string) $name, 'role' => (string) $role, 'state' => (string) $state];
    update_option(CRRG_DUTY_OPT . '_roster', $r, false);
    return true;
}
function crrg_duty_roster_has($who) {                       // 按 key 或名字找
    foreach (crrg_duty_roster() as $k => $d) {
        if ((string) $k === (string) $who || (string) ($d['n'] ?? '') === (string) $who) return $d + ['key' => $k];
    }
    return null;
}
/** 在编人数（含"不在场"的 ✓） */
function crrg_duty_roster_size() { return count(crrg_duty_roster()); }

/* ══════════ 一周值班表 ══════════ */

/** 当前是第几游戏周（世界时间 / 7 天） */
function crrg_duty_week_no($ts = null) {
    $ts = $ts ?: (function_exists('crrg_fact_now') ? crrg_fact_now() : time());
    return (int) floor($ts / 604800);
}
function crrg_duty_weekday($ts = null) {
    $ts = $ts ?: (function_exists('crrg_fact_now') ? crrg_fact_now() : time());
    return (int) gmdate('N', (int) $ts);                    // 1=周一 … 7=周日
}

/** 取本周表（没有就按"上表 + 空"生成 ✓ 并留旧版） */
function crrg_duty_week($weekNo = null) {
    $weekNo = $weekNo === null ? crrg_duty_week_no() : (int) $weekNo;
    $all = (array) get_option(CRRG_DUTY_OPT, []);
    if (!isset($all['weeks'][$weekNo])) {
        $base = [];
        foreach (array_keys(crrg_duty_posts()) as $post) {
            for ($d = 1; $d <= 7; $d++) {
                $base[$post][$d] = ['who' => '', 'uid' => 0, 'why' => '', 'at' => 0, 'by' => 0];
            }
        }
        /* 沿用上一周的人（模板）——空岗留空 ✓ */
        $prev = $all['weeks'][$weekNo - 1] ?? null;
        if (is_array($prev)) foreach ((array) $prev['rows'] as $post => $days) foreach ((array) $days as $d => $cell) {
            if (!empty($cell['who']) || !empty($cell['uid'])) $base[$post][$d] = $cell;
        }
        $all['weeks'][$weekNo] = ['rows' => $base, 'rev' => 1, 'at' => time()];
        update_option(CRRG_DUTY_OPT, $all, false);
    }
    return $all['weeks'][$weekNo];
}

/** 某天某岗是谁（'' ＝ 空岗 ✓） */
function crrg_duty_cell($post, $weekday = null, $weekNo = null) {
    $w = crrg_duty_week($weekNo);
    $d = $weekday ?: crrg_duty_weekday();
    return (array) ($w['rows'][$post][$d] ?? ['who' => '', 'uid' => 0]);
}
/** 今天这个岗空着吗 */
function crrg_duty_vacant($post, $weekday = null, $weekNo = null) {
    $c = crrg_duty_cell($post, $weekday, $weekNo);
    return (empty($c['who']) && empty($c['uid']));
}
/** 今天这个岗是不是我 */
function crrg_duty_is($post, $uid, $weekday = null, $weekNo = null) {
    $c = crrg_duty_cell($post, $weekday, $weekNo);
    return ((int) ($c['uid'] ?? 0) === (int) $uid && (int) $uid > 0);
}

/**
 * 往表里写一行（**只追加版本** ✓ 旧版留档：谁改过表，查得到）
 * @param int $by 改动者 uid（0＝系统）
 */
function crrg_duty_set($post, $who, $weekday = null, $why = '', $uid = 0, $by = 0, $weekNo = null) {
    $posts = crrg_duty_posts();
    if (!isset($posts[$post])) return [false, '没有这个岗位。'];
    $weekNo = $weekNo === null ? crrg_duty_week_no() : (int) $weekNo;
    $d = $weekday ?: crrg_duty_weekday();
    $all = (array) get_option(CRRG_DUTY_OPT, []);
    $w = crrg_duty_week($weekNo);
    $cell = ['who' => (string) $who, 'uid' => (int) $uid, 'why' => (string) $why, 'at' => time(), 'by' => (int) $by];
    $w['rows'][$post][$d] = $cell;
    $w['rev'] = (int) ($w['rev'] ?? 1) + 1;
    $all['weeks'][$weekNo] = $w;
    /* 版本留档（最多 40 条）：记谁在什么时候改了哪一格 ✓ */
    $all['log'] = (array) ($all['log'] ?? []);
    array_unshift($all['log'], ['week' => $weekNo, 'day' => $d, 'post' => $post, 'cell' => $cell, 'rev' => $w['rev']]);
    $all['log'] = array_slice($all['log'], 0, 40);
    update_option(CRRG_DUTY_OPT, $all, false);
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('system.tick', [], ['src' => 'duty']);        // 轻提示（面板可标红）
    }
    return [true, sprintf('%s：周%d %s 已写为「%s」。', $posts[$post]['n'], $d, $posts[$post]['n'], $cell['who'] ?: '空')];
}

/** 一周视图（面板/任务用） */
function crrg_duty_lines($weekNo = null) {
    $posts = crrg_duty_posts();
    $w = crrg_duty_week($weekNo);
    $out = ['〔本周值班表〕'];
    foreach ($posts as $post => $p) {
        $row = [];
        for ($d = 1; $d <= 7; $d++) {
            $c = (array) ($w['rows'][$post][$d] ?? []);
            $row[] = ['一', '二', '三', '四', '五', '六', '日'][$d - 1] . ':' . (($c['who'] ?? '') !== '' ? $c['who'] : '————');
        }
        $out[] = sprintf('　%-4s %s', $p['n'], implode(' ', $row));
    }
    $out[] = sprintf('　（在编 %d 人 ｜ 表 rev %d ｜ 空着的格子＝没人认领）', crrg_duty_roster_size(), (int) ($w['rev'] ?? 1));
    return $out;
}

/** 哪些格子今天空着（任务/面板用） */
function crrg_duty_vacancies($weekday = null) {
    $out = [];
    foreach (array_keys(crrg_duty_posts()) as $post) if (crrg_duty_vacant($post, $weekday)) $out[] = $post;
    return $out;
}

/* ══════════ 换周滚动（搭世界推进器 ✓ 不另起定时任务 ✗） ══════════ */
add_action('init', function () {
    if (!function_exists('crrg_evt_on')) return;
    crrg_evt_on('system.day', function () {
        /* 换日：如果跨到新的一周，就把表滚出来（沿用上一周的人 + 空格）✓ 幂等 ✓ */
        crrg_duty_week(crrg_duty_week_no());
    }, ['module' => 'duty']);
}, 33);
