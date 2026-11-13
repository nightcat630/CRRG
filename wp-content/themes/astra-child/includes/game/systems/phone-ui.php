<?php
/**
 * CRRG 演习 · 手机界面（systems/phone-ui.php，2026-10-14）
 * ------------------------------------------------------------
 * 委员长两条意见：
 *   ① 动态/群聊/论坛**反复发同一条** ✗ —— 那是内容池固定取前两条的锅（已在 phone-life.php 改成**按日序轮转** ✓）
 *   ② 操作**太分散**（九条指令）✗ + 想要**像智能手机** ✓
 * → 本文件把手机做成**一个窗口、多页**：主屏（图标网格）＋ 各页（消息/动态/论坛/备忘录/相册/收音机/翻牌/时间/通话/交报告）
 *   入口只有一个：**「手机」** 回主屏；**「手机 群聊」** 这类进子页 ✓
 *   纯文本界面用符号画出"手机屏"（状态栏／内容区／底栏）——QQ 上是等宽以外的字体也能看 ✓
 */
if (!defined('ABSPATH')) exit;

/** 显示宽度：中日韩/全角/emoji 算 2，其余算 1（**不这么做方框就会歪** ✗ 委员长一眼就看出来 ✓） */
function crrg_phone_w($ch) {
    $cp = mb_ord((string) $ch, 'UTF-8');
    if ($cp === false || $cp === null) return 1;
    if ($cp >= 0x1F000) return 2;                                   // emoji / 符号
    if (($cp >= 0x1100 && $cp <= 0x115F) || ($cp >= 0x2E80 && $cp <= 0xA4CF)
        || ($cp >= 0xAC00 && $cp <= 0xD7A3) || ($cp >= 0xF900 && $cp <= 0xFAFF)
        || ($cp >= 0xFE30 && $cp <= 0xFE6F) || ($cp >= 0xFF00 && $cp <= 0xFF60)
        || ($cp >= 0xFFE0 && $cp <= 0xFFE6)) return 2;
    return 1;
}
function crrg_phone_len($str) {
    $n = 0; $len = mb_strlen((string) $str, 'UTF-8');
    for ($i = 0; $i < $len; $i++) $n += crrg_phone_w(mb_substr((string) $str, $i, 1, 'UTF-8'));
    return $n;
}
/** 按显示宽度截断／补齐到 $want 列 */
function crrg_phone_pad($str, $want) {
    $str = (string) $str;
    if (crrg_phone_len($str) > $want) {                             // 超宽就按列截
        $out = ''; $w = 0; $len = mb_strlen($str, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($str, $i, 1, 'UTF-8');
            $cw = crrg_phone_w($ch);
            if ($w + $cw > $want) break;
            $out .= $ch; $w += $cw;
        }
        $str = $out;
    }
    return $str . str_repeat(' ', max(0, $want - crrg_phone_len($str)));
}

/** 按显示宽度折行（**内容不许被截** ✗ —— 手机上是换行 ✓） */
function crrg_phone_wrap($str, $W, $indent = 0) {
    $str = (string) $str;
    if (crrg_phone_len($str) <= $W) return [$str];
    $out = []; $cur = ''; $w = 0; $limit = $W;
    $len = mb_strlen($str, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $ch = mb_substr($str, $i, 1, 'UTF-8');
        $cw = crrg_phone_w($ch);
        if ($w + $cw > $limit) { $out[] = $cur; $cur = str_repeat(' ', $indent); $w = $indent; $limit = $W; }
        $cur .= $ch; $w += $cw;
    }
    if ($cur !== '') $out[] = $cur;
    return $out;
}

/** 屏幕外框：状态栏 ＋ 内容 ＋ 底栏（**按显示宽度排版** ✓） */
function crrg_phone_frame(&$pc, array $body, $title = '') {
    $room = (string) ($GLOBALS['crrg_phone_room'] ?? '');
    $sig = 0;
    if ($room !== '' && function_exists('crrg_signal_at')) $sig = (int) crrg_signal_at($room);
    $bars = [3 => '▮▮▮▮▮', 2 => '▮▮▮▯▯', 1 => '▮▮▯▯▯', 0 => '▮▯▯▯▯'];
    $net  = $sig >= 3 ? '基地网' : ($sig >= 2 ? '基地网' : ($sig >= 1 ? '弱信号' : '无服务'));
    $clock = function_exists('crrg_cal_hour') ? sprintf('%02d:%02d', (int) crrg_cal_hour(), (int) date('i')) : date('H:i');
    $W = 30;                                                        // 内容区宽度（列）
    $line = function ($t) use ($W) { return '│' . crrg_phone_pad($t, $W) . '│'; };
    $out = [];
    $out[] = '┌' . str_repeat('─', $W) . '┐';
    $out[] = $line($bars[$sig] . ' ' . $net . '   ' . $clock . '   87%');
    $out[] = '├' . str_repeat('─', $W) . '┤';
    if ($title !== '') $out[] = $line($title);
    foreach ($body as $b) {
        foreach (explode("\n", (string) $b) as $one) {
            /* 折行的续行**保持原来的缩进**（气泡/列表看着才不散 ✓） */
            preg_match('/^(\s*)/u', (string) $one, $mm);
            $ind = crrg_phone_len($mm[1] ?? '');
            foreach (crrg_phone_wrap((string) $one, $W, min($ind + 2, 6)) as $seg) $out[] = $line($seg);
        }
    }
    $out[] = '├' . str_repeat('─', $W) . '┤';
    foreach (crrg_phone_wrap('［主页］打「手机」；子页打「手机 消息／动态／论坛／备忘录／收音机／翻牌／时间／通话／交报告」', $W) as $seg) $out[] = $line($seg);
    $out[] = '└' . str_repeat('─', $W) . '┘';
    return $out;
}

/** 主屏：图标网格 ✓ */
function crrg_phone_home(&$s, &$pc) {
    $msgOn = ''; $rep = '';
    $mid = (string) ($pc['mission'] ?? '');
    if ($mid !== '' && function_exists('crrg_drill_mission')) {
        $m = crrg_drill_mission($mid);
        if ($m) $rep = '（' . mb_substr((string) ($m['name'] ?? ''), 0, 6) . '…）';
    }
    $body = [];
    $body[] = '';
    $body[] = '        ▪ 主 屏 ▪';
    $body[] = '';
    $icons = [['💬 消息', '🎞 动态'], ['🗣 论坛', '📝 备忘录'], ['📻 收音机', '📷 相册'],
              ['🎮 翻牌', '⏰ 时间'], ['📞 通话', '📤 交报告' . $rep]];
    foreach ($icons as $row) {
        $body[] = '  ' . crrg_phone_pad($row[0], 13) . $row[1];
    }
    $body[] = '';
    $body[] = '  信号：' . (function_exists('crrg_signal_at') ? crrg_signal_cn((int) crrg_signal_at((string) ($s['room'] ?? ''))) : '—');
    $body[] = '  在手：' . ($mid !== '' ? (function_exists('crrg_drill_mission') ? (string) (crrg_drill_mission($mid)['name'] ?? $mid) : $mid) : '没有活');
    return crrg_phone_frame($pc, $body, '　手　机');
}

/** 消息：群列表 */
function crrg_phone_msgs(&$s, &$pc) {
    $body = ['', '  ▪ 消息 ▪', ''];
    foreach (crrg_ph_groups() as $g => $d) {
        $rows = crrg_ph_group($g, 1);
        $last = $rows ? end($rows) : [];
        $body[] = sprintf('  %s %-6s %s', $d[1], $d[0], mb_substr((string) ($last['text'] ?? '（没有消息）'), 0, 12));
    }
    $body[] = '';
    $body[] = '  进群：「手机 值班／后勤／闲聊」';
    return crrg_phone_frame($pc, $body, '　消 息');
}

/** 单个群：气泡式排版（对方靠左、自己靠右 ✓） */
function crrg_phone_group_page(&$s, &$pc, $g) {
    $G = crrg_ph_groups();
    $name = $G[$g][0] ?? '群';
    $body = ['', '  ▪ ' . $name . ' ▪', ''];
    foreach (crrg_ph_group($g, 8) as $r) {
        $who = (string) ($r['who'] ?? '');
        $txt = (string) ($r['text'] ?? '');
        if (!empty($r['me'])) { $body[] = '            ' . $txt . ' ▐'; continue; }
        $body[] = '  ' . $who;
        $body[] = '  ▐ ' . $txt;
        $body[] = '';
    }
    $body[] = '  发言：「发言 值班 内容」';
    return crrg_phone_frame($pc, $body, '　' . $name);
}

/** 动态：卡片式 */
function crrg_phone_moments(&$s, &$pc) {
    $body = ['', '  ▪ 动态 ▪', ''];
    foreach (crrg_ph_feed(5) as $i => $r) {
        $body[] = sprintf('  %d．%s', $i + 1, (string) ($r['who'] ?? ''));
        $body[] = '     ' . (string) ($r['text'] ?? '');
        $body[] = '     ♡ ' . (int) ($r['likes'] ?? 0);
        $body[] = '';
    }
    $body[] = '  点赞：「点赞 <序号>」';
    return crrg_phone_frame($pc, $body, '　动 态');
}

/** 论坛：帖子列表 */
function crrg_phone_forum(&$s, &$pc) {
    $body = ['', '  ▪ 本地论坛 ▪', ''];
    foreach (crrg_ph_forum(5) as $i => $r) {
        $body[] = sprintf('  %d．%s', $i + 1, (string) ($r['title'] ?? ''));
        $body[] = '     — ' . (string) ($r['who'] ?? '');
        $body[] = '     ' . mb_substr((string) ($r['text'] ?? ''), 0, 26);
        $body[] = '';
    }
    return crrg_phone_frame($pc, $body, '　论 坛');
}

/** 备忘录 */
function crrg_phone_memo_page(&$s, &$pc) {
    $m = crrg_ph_memo($pc);
    $body = ['', '  ▪ 备忘录 ▪', ''];
    if (!$m) $body[] = '  （空的。）';
    foreach (array_slice($m, 0, 7) as $r) {
        $by = (string) ($r['by'] ?? 'me');
        $body[] = '  · ' . mb_substr((string) ($r['text'] ?? ''), 0, 22) . ($by !== 'me' ? '　' . $by : '');
    }
    $body[] = '';
    $body[] = '  记一条：「记 <内容>」';
    return crrg_phone_frame($pc, $body, '　备 忘 录');
}

/** 收音机 */
function crrg_phone_radio(&$s, &$pc) {
    $body = ['', '  ▪ 收音机 ▪', ''];
    $body[] = '  ' . str_replace("\n", "\n  ", (string) crrg_ph_radio_now());
    $body[] = '';
    $body[] = '  （凌晨 1–4 点，频率不太一样。）';
    return crrg_phone_frame($pc, $body, '　收 音 机');
}

/** 相册 */
function crrg_phone_album(&$s, &$pc) {
    $ph = [];
    foreach ((array) ($pc['items'] ?? []) as $id => $n) {
        if (strpos((string) $id, 'photo') === 0) $ph[] = (string) $id;
    }
    $body = ['', '  ▪ 相册 ▪', ''];
    if (!$ph) $body[] = '  （还没有照片。）';
    foreach (array_slice($ph, 0, 6) as $p) $body[] = '  ▣ ' . $p;
    $body[] = '';
    $body[] = '  （拍照：遇到该记下来的东西时用「拍照 <名字>」。）';
    return crrg_phone_frame($pc, $body, '　相 册');
}

/** 通话（通讯录 ＋ 记录入口） */
function crrg_phone_call_page(&$s, &$pc) {
    $body = ['', '  ▪ 通话 ▪', ''];
    foreach (array_slice(crrg_phone_book($pc), 0, 6) as $p) {
        $body[] = sprintf('  📞 %-4s（%s）人情 %d', (string) $p['n'], mb_substr((string) $p['role'], 0, 4), (int) $p['fav']);
    }
    $body[] = '';
    $body[] = '  打过去：「呼叫 <名字>」　记录：「通话记录」';
    return crrg_phone_frame($pc, $body, '　通 话');
}

/** 交报告 */
function crrg_phone_report_page(&$s, &$pc) {
    $body = ['', '  ▪ 交报告（远程）▪', ''];
    $body[] = '  信号：' . (function_exists('crrg_signal_at') ? crrg_signal_cn((int) crrg_signal_at((string) ($s['room'] ?? ''))) : '—');
    $body[] = '  （人在外头也能交——基地会收到，晚一点结。）';
    $body[] = '';
    $body[] = '  用法：「远程报告」或「远程报告 内容」';
    return crrg_phone_frame($pc, $body, '　交 报 告');
}

/** 翻牌 */
function crrg_phone_game_page(&$s, &$pc) {
    $body = ['', '  ▪ 翻 牌 ▪', ''];
    $body[] = '  ▪▪▪▪  ▪▪▪▪  ▪▪▪▪';
    $body[] = '  ▪▪▪▪  ▪▪▪▪  ▪▪▪▪';
    $body[] = '';
    $body[] = sprintf('  最好成绩：%d 局', (int) ($pc['ph_game_best'] ?? 0));
    $body[] = '  来一局：「翻牌」';
    return crrg_phone_frame($pc, $body, '　翻 牌');
}

/** 时间 */
function crrg_phone_clock_page(&$s, &$pc) {
    $body = ['', '  ▪ 时 间 ▪', ''];
    foreach (crrg_ph_clock() as $x) $body[] = '  ' . $x;
    if (function_exists('crrg_cal_events_today')) {
        $body[] = '';
        $body[] = '  今日日程：';
        foreach ((array) crrg_cal_events_today() as $e) $body[] = '  · ' . $e;
    }
    return crrg_phone_frame($pc, $body, '　时 间');
}

/** 分页总入口（窗口与指令都走这里 ✓ 一处真相） */
function crrg_phone_screen($page, &$s, &$pc, $arg = '') {
    $GLOBALS['crrg_phone_room'] = (string) ($s['room'] ?? '');
    $page = strtolower(trim((string) $page));
    switch ($page) {
        case '': case 'home': case '主页':        return crrg_phone_home($s, $pc);
        case 'msg': case '消息':                  return crrg_phone_msgs($s, $pc);
        case 'g1': case '值班':                   return crrg_phone_group_page($s, $pc, 'group_duty');
        case 'g2': case '后勤':                   return crrg_phone_group_page($s, $pc, 'group_logi');
        case 'g3': case '闲聊': case '闲人':       return crrg_phone_group_page($s, $pc, 'group_chat');
        case 'moments': case '动态':              return crrg_phone_moments($s, $pc);
        case 'forum': case '论坛':                return crrg_phone_forum($s, $pc);
        case 'memo': case '备忘录':                return crrg_phone_memo_page($s, $pc);
        case 'radio': case '收音机':               return crrg_phone_radio($s, $pc);
        case 'album': case '相册':                return crrg_phone_album($s, $pc);
        case 'call': case '通话':                 return crrg_phone_call_page($s, $pc);
        case 'report': case '交报告':              return crrg_phone_report_page($s, $pc);
        case 'game': case '翻牌':                 return crrg_phone_game_page($s, $pc);
        case 'clock': case '时间':                return crrg_phone_clock_page($s, $pc);
        default:
            /* 认不出就当"进某个群/某页"：给主屏 + 一句提示 ✓ */
            return array_merge(['（没有这一页：「' . $page . '」。）'], crrg_phone_home($s, $pc));
    }
}


/* ══════════ 按钮化：窗口 items（点了就切页 ✓）══════════ */

/** 主屏：十二个应用 → 按钮 */
function crrg_phone_home_items() {
    $apps = [
        ['💬 消息', 'msg'], ['🎞 动态', 'moments'],
        ['🗣 论坛', 'forum'], ['📝 备忘录', 'memo'],
        ['📻 收音机', 'radio'], ['📷 相册', 'album'],
        ['🎮 翻牌', 'game'], ['⏰ 时间', 'clock'],
        ['📞 通话', 'call'], ['📤 交报告', 'report'],
        ['💬 值班群', 'g1'], ['📦 后勤群', 'g2'], ['🗣 闲人群', 'g3'],
    ];
    $rows = [];
    foreach ($apps as $a) $rows[] = ['label' => $a[0], 'k' => 'phone_page', 'id' => $a[1]];
    return $rows;
}

/** 子页的按钮：返回主屏 ＋ 该页自己的动作 */
function crrg_phone_page_items($page) {
    $rows = [['label' => '← 返回主屏', 'k' => 'phone_page', 'id' => 'home']];
    switch ($page) {
        case 'msg':
            foreach (crrg_ph_groups() as $g => $d) {
                $rows[] = ['label' => $d[1] . ' 进「' . $d[0] . '」', 'k' => 'phone_page', 'id' => ($g === 'group_duty' ? 'g1' : ($g === 'group_logi' ? 'g2' : 'g3'))];
            }
            break;
        case 'g1': case 'g2': case 'g3':
            $rows[] = ['label' => '发言（在下面输入框里写「发言 值班 内容」）', 'k' => 'noop', 'id' => ''];
            break;
        case 'moments':
            foreach (array_slice(crrg_ph_feed(5), 0, 5) as $i => $r) {
                $rows[] = ['label' => '♡ 给这条点赞（' . mb_substr((string) ($r['who'] ?? ''), 0, 4) . '）', 'k' => 'phone_like', 'id' => (string) ($i + 1)];
            }
            break;
        case 'radio':  $rows[] = ['label' => '调一调频率', 'k' => 'phone_page', 'id' => 'radio']; break;
        case 'game':   $rows[] = ['label' => '来一局', 'k' => 'ph_game', 'id' => '']; break;
        case 'call':   $rows[] = ['label' => '通话记录', 'k' => 'phone_log', 'id' => '']; break;
        case 'memo':   $rows[] = ['label' => '（写新备忘：输入框里打「记 内容」）', 'k' => 'noop', 'id' => '']; break;
        case 'report': $rows[] = ['label' => '把在手任务交上去（远程）', 'k' => 'phone_report', 'id' => '']; break;
    }
    return $rows;
}
