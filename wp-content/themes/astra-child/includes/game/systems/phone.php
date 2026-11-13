<?php
/**
 * CRRG 演习 · 手机 / 通讯（systems/phone.php，R3，2026-10-14）
 * ------------------------------------------------------------
 * 委员长口径：**"加个手机系统，功能多一点，窗口形式；最好有远程交报告——玩家可能在几百公里以外，
 *   不可能回基地交任务，远程交个报告来结算比较合理"** ✓
 *
 * 本系统四件（压缩版，只做能玩的）：
 *   ① **通讯录**：名册里的人 ＋ 人情 ＋ 最近一次联系
 *   ② **通话记录**：只追加 ✓ 可被查（也是线索来源）
 *   ③ **呼叫**：按**信号**判定（B4 深处弱、野外基本没有）；求援要人情
 *   ④ **远程交报告**（★核心）：写好报告 → 发送 → **走同一套结算**（`crrg_drill_mission_settle`，
 *      引擎任务会在它里面转 `crrg_task_finish` ✓ 绝不另写一份结算 ✗）——有延迟、信号差会掉字/失败
 *
 * 纪律：
 *   · **同一套结算**：本文件不准自己算积分/声望、不准自己写`mission_done`（门禁 verify-phone.php 会扫 ✓）
 *   · **通话记录只追加**：只有 unshift + 截断，没有改写/删除 ✓
 *   · **无信号＝明确失败**（不是静默不结算 ✗）
 */
if (!defined('ABSPATH')) exit;

const CRRG_PHONE_LOG_META = 'crrg_phone_log';

/* ══════════ 一、信号 ══════════ */

/**
 * 信号 0–5 格。
 * @param string $where 房间键 / 地图节点 / 'transit'
 */
function crrg_signal_at($where) {
    $w = (string) $where;
    if ($w === '') return 0;
    if ($w === 'transit') return 0;                                  // 路上：没信号 ✓
    if ($w === 'field' || strpos($w, 'w_') === 0) return 0;           // 野外：**没有信号** ✓（委员长口径）
    if (preg_match('/^sa_/', $w)) return 2;                           // 服务区（有基站，靠不住）
    if (preg_match('/^(r_ganzhou|r_baishi)/', $w)) return 3;          // 县城/大城市好一点 ✓
    if (preg_match('/^r_/', $w)) return 1;                            // 其余地区节点（村口/山脚…）
    if (preg_match('/^b4_/', $w)) return ($w === 'b4_buffer') ? 2 : 1; // B4 深处最差 ✓
    if (preg_match('/^b3_/', $w)) return 2;
    if (preg_match('/^b2/', $w))  return 3;
    if (preg_match('/^b1_/', $w)) return 3;
    if (preg_match('/^tw_/', $w)) return 3;                           // 镇上
    return 3;
}

/** 信号文案 */
function crrg_signal_cn($n) {
    $n = (int) $n;
    if ($n <= 0) return '没有信号';
    return str_repeat('▮', $n) . str_repeat('▯', 5 - $n) . sprintf('（%d 格）', $n);
}

/* ══════════ 二、通话记录（只追加 ✓）══════════ */

function crrg_phone_log($uid) {
    $l = get_user_meta((int) $uid, CRRG_PHONE_LOG_META, true);
    return is_array($l) ? $l : [];
}
/** 只有追加与截断；没有改写、没有删除 ✓ */
function crrg_phone_log_add($uid, $kind, $who, $text = '', $ok = true) {
    $l = crrg_phone_log($uid);
    array_unshift($l, ['t' => time(), 'kind' => (string) $kind, 'who' => (string) $who,
                       'text' => (string) $text, 'ok' => (bool) $ok]);
    $l = array_slice($l, 0, 60);
    update_user_meta((int) $uid, CRRG_PHONE_LOG_META, $l);
    return $l[0];
}

/* ══════════ 三、通讯录 ══════════ */

function crrg_phone_book(&$pc) {
    $out = [];
    $roster = function_exists('crrg_actor_roster') ? crrg_actor_roster() : [];
    $log = [];
    foreach (crrg_phone_log((int) ($pc['uid'] ?? 0)) as $r) $log[(string) ($r['who'] ?? '')] = (int) ($r['t'] ?? 0);
    foreach ($roster as $id => $d) {
        $nm = (string) ($d['n'] ?? $id);
        $out[] = ['id' => $id, 'n' => $nm, 'role' => (string) ($d['role'] ?? ''),
                  'fav' => (int) ($pc['npc_fav'][$nm] ?? 0), 'last' => (int) ($log[$nm] ?? 0)];
    }
    usort($out, fn($a, $b) => $b['fav'] <=> $a['fav']);
    return $out;
}

/* ══════════ 四、呼叫 ══════════ */

function crrg_phone_call(&$s, &$pc, $who) {
    $room = (string) ($s['room'] ?? '');
    $sig = crrg_signal_at($room);
    $uid = (int) ($pc['uid'] ?? 0);
    $roster = function_exists('crrg_actor_roster') ? crrg_actor_roster() : [];
    $hit = null;
    foreach ($roster as $id => $d) {
        if ((string) $id === (string) $who || (string) ($d['n'] ?? '') === (string) $who) { $hit = $d + ['id' => $id]; break; }
    }
    if (!$hit) return ['（通讯录里没有这个人。）'];
    $nm = (string) ($hit['n'] ?? $who);
    if ($sig <= 0) {
        crrg_phone_log_add($uid, 'call', $nm, '无信号', false);
        return ['（拨不出去——这里没有信号。）', '（走出去一段再试，或者用对讲机找值班室转。）'];
    }
    $ok = ($sig >= 2) || (mt_rand(1, 100) <= 40);                     // 1 格信号：六成打不通 ✓
    if (!$ok) {
        crrg_phone_log_add($uid, 'call', $nm, '没接通', false);
        return ['（响了很久，没人接。信号一格。）'];
    }
    /* 接通：把人叫过来（写旗标，供任务判定）；求援要人情 ✓ */
    $flag = 'called_' . $hit['id'];
    if (!in_array($flag, (array) $s['flags'], true)) $s['flags'][] = $flag;
    $s['call_pending'][$hit['id']] = time();
    crrg_phone_log_add($uid, 'call', $nm, '接通', true);
    return [
        sprintf('（通了。）%s 那边有点吵：「说。」', $nm),
        '（他答应过来看一眼——把手上的事放一放，等人。）',
        $sig <= 1 ? '（信号一格，你们的对话断断续续。）' : '',
    ];
}

/* ══════════ 五、★ 远程交报告（走同一套结算 ✓）══════════ */

/** 找在手任务（按 id 或名字；不写就取在手那个） */
function crrg_phone_find_mission(&$pc, $q = '') {
    $id = (string) ($pc['mission'] ?? '');
    if ($q === '') return $id ?: '';
    $q = trim($q);
    if ($id === $q) return $id;
    if ($id && ($m = function_exists('crrg_drill_mission') ? crrg_drill_mission($id) : null)) {
        if (mb_strpos((string) ($m['name'] ?? ''), $q) !== false || mb_strpos($q, (string) ($m['name'] ?? '')) !== false) return $id;
    }
    foreach ((array) (function_exists('crrg_drill_missions') ? crrg_drill_missions() : []) as $mid => $m) {
        if ((string) $mid === $q) return (string) $mid;
        if (mb_strpos((string) ($m['name'] ?? ''), $q) !== false) return (string) $mid;
    }
    return '';
}

/**
 * 远程交报告：信号 → 延迟 → **同一套结算** ✓
 * @return array 给玩家看的行
 */
function crrg_phone_report(&$s, &$pc, &$ctx, $q = '', $text = '') {
    $uid = (int) ($pc['uid'] ?? 0);
    $room = (string) ($s['room'] ?? '');
    $sig = crrg_signal_at($room);
    $mid = crrg_phone_find_mission($pc, $q);
    if ($mid === '') return ['（手上没有能交的活——先说清是哪一个。）'];
    $m = function_exists('crrg_drill_mission') ? crrg_drill_mission($mid) : null;
    $nm = (string) ($m['name'] ?? $mid);

    if ($sig <= 0) {
        crrg_phone_log_add($uid, 'report', $nm, '无信号', false);
        return ['（报告发不出去——这里没有信号。）',
                '（换个地方：楼上、镇上、或者车开出去一段。）'];
    }
    /* 信号差 → 掉字/失败 ✓（1 格：一半概率丢包） */
    if ($sig <= 1 && mt_rand(1, 100) <= 50) {
        crrg_phone_log_add($uid, 'report', $nm, '发送失败（信号差）', false);
        return ['（发送到一半断了——那头只收到半页。）', '（信号一格。再试一次，或者换个地方。）'];
    }
    $delay = max(1, 6 - $sig) * 5;                                     // 延迟：信号越差等越久（游戏分钟）
    $body = trim((string) $text);
    if ($body === '') $body = '按标准格式报：情况、处置、遗留。';

    /* ★ 同一套结算 ✓（老任务走 mission_settle；引擎任务会在它内部转 crrg_task_finish ✓） */
    if (!function_exists('crrg_drill_mission_settle')) return ['（结算系统没加载——报告留在草稿箱里。）'];
    $out = [];
    $out[] = sprintf('（发出去了。%d 格信号，那头大约 %d 分钟后收到。）', $sig, $delay);
    $before = (int) ($pc['credits'] ?? 0);
    [$mm, $settleOut] = crrg_drill_mission_settle($pc, $ctx, $uid, $s);
    $out = array_merge($out, ['〔远程交报告 · ' . $nm . '〕'], (array) $settleOut);
    $got = (int) ($pc['credits'] ?? 0) - $before;
    if ($got > 0) $out[] = sprintf('（远程结算到账：+%d 积分。）', $got);
    crrg_phone_log_add($uid, 'report', $nm, $body, true);
    if (function_exists('crrg_evt_emit')) {
        /* settle 返回的 $mm 可能是数组（老结算会回一个状态数组）→ 安全取 id ✓ */
        $mmId = is_array($mm) ? (string) ($mm['id'] ?? ($mm['mission'] ?? '')) : (string) $mm;
        crrg_evt_emit('mission.settle', ['task' => $mmId, 'result' => 'phone_report', 'uid' => $uid], ['src' => 'phone']);
    }
    return $out;
}

/* ══════════ 六、窗口（窗口形式 ✓）══════════ */

if (function_exists('crrg_ui_win_register')) {
    /* 2026-10-14（phone-ui）：窗口改成**手机屏分页** ✓
       窗口 id 支持 `phone:<页>`（windows.php 把冒号后当 $arg 传进来 ✓）
       以前这里是一坨纯文本清单 ✗ —— 正是委员长说的「操作太分散」✓ */
    crrg_ui_win_register('phone', function (&$s, &$ctx, &$pc, $uid, $arg) {
        /* 窗口契约：返回 ['text'=>[行…]]（裸数组会被判定为"什么都没渲染" ✗ 门禁抓过我 ✓） */
        $page = (string) ($arg ?? '');
        $lines = function_exists('crrg_phone_screen')
            ? crrg_phone_screen($page, $s, $pc, '')
            : ['（手机界面未加载。）'];
        $items = [];
        if (function_exists('crrg_phone_home_items')) {
            $items = ($page === '' || strtolower($page) === 'home')
                ? crrg_phone_home_items()                     // 主屏：一排应用，**点了就切页** ✓
                : crrg_phone_page_items($page);               // 子页：返回键 ＋ 本页动作 ✓
        }
        return ['text' => $lines, 'items' => $items];
    });
}
