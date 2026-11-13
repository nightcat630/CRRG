<?php
/**
 * 玩家互动 · 窗口（2026-10-01）
 *   · `player:<uid>` —— 对**某个人**能做的事（打招呼/递东西/邀请组队/留话 ✓）
 *   · `social`        —— **我的互动**：留言 / 礼物（可领）/ 组队邀请（可接受）✓
 */
if (!defined('ABSPATH')) exit;

crrg_ui_win_register('player', function (&$s, &$ctx, &$pc, $uid, $arg) {
    /* 2026-10-01 改**两级菜单**（委员长：太臃肿 ✓）：
       `player:<uid>`        → 一级：菜单（打招呼／千年杀／递个东西→／邀请组队／留话提示 ✓）
       `player:<uid>:gift`   → 二级：递东西（物品列表 ＋ 返回 ✓） */
    $parts = explode(':', (string) $arg);
    $t   = (int) ($parts[0] ?? 0);
    $sub = (string) ($parts[1] ?? '');
    if ($t <= 0 || $t === (int) $uid) return null;
    $u  = get_userdata($t);
    if (!$u) return null;
    $same = function_exists('crrg_soc_same_room') && crrg_soc_same_room($uid, $t, $s);
    $note = ($u->display_name ?? ('#' . $t)) .
            '　｜　' . (function_exists('crrg_drill_ctx') ? (crrg_drill_ctx($t)['rank_name'] ?? '—') : '—') .
            '　｜　' . ($same ? '就在你眼前' : '不在此处（打招呼/递东西得当面；邀请/留言随时可以）');

    /* ── 二级：递东西 ── */
    if ($sub === 'gift') {
        $items = [];
        $cnt = [];
        foreach ((array) ($pc['items'] ?? []) as $it) $cnt[(string) $it] = ($cnt[(string) $it] ?? 0) + 1;
        if (!$cnt) $items[] = ['label' => '（你身上没东西可递）', 'k' => 'noop', 'id' => '', 'off' => true];
        foreach ($cnt as $it => $n) {
            $nm = function_exists('crrg_drill_item_name') ? crrg_drill_item_name($it) : $it;
            $items[] = ['label' => '递给他：「' . $nm . '」' . ($n > 1 ? '（×' . $n . '）' : ''), 'k' => 'soc_gift', 'id' => $nm . '|' . $t];
        }
        $items[] = ['label' => '← 返回', 'k' => 'win', 'id' => 'player:' . $t];
        return ['note' => $note . '　｜　递东西（点一件就递过去）', 'text' => [], 'items' => $items];
    }

    /* ── 一级：菜单 ── */
    $items = [];
    $hasItems = !empty($pc['items']);
    if ($same) {
        $items[] = ['label' => '打个招呼（同处的人看得见）', 'k' => 'soc_wave', 'id' => (string) $t];
        $items[] = ['label' => '千年杀（从背后并两指——演习老梗）', 'k' => 'soc_kancho', 'id' => (string) $t];
        if ($hasItems) $items[] = ['label' => '递个东西给他…（进入选物）', 'k' => 'win', 'id' => 'player:' . $t . ':gift'];
    }
    $items[] = ['label' => '邀请组队', 'k' => 'soc_invite', 'id' => (string) $t];
    return ['note' => $note . '　｜　留话可写「留话 ' . $t . '|内容」',
            'text' => [], 'items' => $items];
}, ['title' => '玩家互动', 'order' => 8, 'w' => 430]);

crrg_ui_win_register('social', function (&$s, &$ctx, &$pc, $uid, $arg) {
    if (!function_exists('crrg_soc_box')) return null;
    $b = crrg_soc_box($pc);
    $items = [];
    /* 礼物（可领 ✓） */
    foreach ((array) $b['gifts'] as $i => $g) {
        [$nm] = array_pad(explode('|', (string) ($g['text'] ?? ''), 2), 1, '');
        $items[] = ['label' => '收下：' . (string) $nm . '（来自 ' . (string) ($g['from_name'] ?? '?') . '）', 'k' => 'soc_take', 'id' => (string) $i];
    }
    /* 组队邀请（可接受 ✓） */
    foreach ((array) $b['invites'] as $i => $g) {
        $items[] = ['label' => '接受组队邀请（' . (string) ($g['from_name'] ?? '?') . '）', 'k' => 'soc_accept', 'id' => (string) $i];
    }
    /* 留言（只读 ✓） */
    $text = [];
    if (!empty($b['inbox'])) {
        $text[] = '—— 留言 ——';
        foreach (array_slice((array) $b['inbox'], -8) as $m) {
            $text[] = '· ' . (string) ($m['from_name'] ?? '?') . '：' . (string) ($m['text'] ?? '');
        }
    }
    if (!$items && !$text) return ['note' => '（互动信箱是空的）', 'text' => [], 'items' => []];
    return ['note' => '礼物／邀请点一下就办；留言在下面。', 'text' => $text, 'items' => $items];
}, ['title' => '我的互动', 'order' => 9, 'w' => 430]);
