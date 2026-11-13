<?php
/**
 * CRRG 演习 · 档案检索（systems/archive.php，R10，2026-10-14）
 * ------------------------------------------------------------
 * 为什么要有它：追书人线要"翻旧档"，《在册》要"查十年前的编制本"——
 *   可现在档案是**帖子**，游戏里**查不了** ✗ 玩家只能靠任务喂线索，没法自己挖 ✓
 *
 * 三样：
 *   ① **索引**：把现有档案（报告/任务归档的帖子）＋**事实表**里的公共记录统一索引
 *   ② **检索**：按 人名／编号／月日／地点／关键词 查 → 命中条目（标题＋日期＋等级＋能不能借出）
 *   ③ **权限**：条目带 `crrg_access_level` meta ✓ 与卡级对齐；不足级别的**看得到存在、看不到内容** ✓（这比"查不到"更真实 ✓）
 *
 * 纪律：
 *   · 检索**要花时间**（个人在线钟 ✓ 不碰世界钟 ✗）
 *   · 查过什么**留痕**（`arch_<hash>` 旗标 ✓ 幂等 ✓）→ 任务可以用 `need.flags` 判定"你查过这一条"
 *   · 索引**只读**：不改任何帖子 ✗
 */
if (!defined('ABSPATH')) exit;

/** 卡级（与门禁/收容同源口径） */
function crrg_arch_level_of(&$pc) { return (int) ($pc['clearance'] ?? 0); }

/** 等级是否够看 */
function crrg_arch_can(&$pc, $need) {
    $map = ['public' => 0, 'operative' => 1, 'confidential' => 2, 'secret' => 3, 'archive' => 1];
    $need = (int) ($map[(string) $need] ?? 1);
    return crrg_arch_level_of($pc) >= $need;
}

/** 建立索引（**按需重建**，缓存 10 分钟 ✓；只读帖子 ✓） */
function crrg_arch_index() {
    $cache = get_option('crrg_arch_index', null);
    if (is_array($cache) && (time() - (int) ($cache['at'] ?? 0)) < 600) return (array) ($cache['rows'] ?? []);
    if (!function_exists('get_posts')) return [];
    $rows = [];
    $posts = get_posts(['post_type' => ['post', 'page'], 'post_status' => ['publish', 'private'],
                        'numberposts' => 300, 'orderby' => 'date', 'order' => 'DESC']);
    foreach ((array) $posts as $p) {
        $type = (string) get_post_meta($p->ID, 'crrg_report_type_name', true);
        $lvl  = (string) get_post_meta($p->ID, 'crrg_access_level', true);
        $rows[] = [
            'id'    => (int) $p->ID,
            'title' => (string) $p->post_title,
            'date'  => (string) $p->post_date,
            'type'  => $type !== '' ? $type : '档案',
            'level' => $lvl !== '' ? $lvl : 'operative',
            'text'  => mb_substr(wp_strip_all_tags((string) $p->post_content), 0, 600),
        ];
    }
    update_option('crrg_arch_index', ['at' => time(), 'rows' => $rows], false);
    return $rows;
}

/** 检索：返回 [条目…]；`$q` 支持空格分词（全部命中才算） */
function crrg_arch_search($q, $limit = 8) {
    $q = trim((string) $q);
    if ($q === '') return [];
    $words = preg_split('/\s+/u', $q);
    $out = [];
    foreach (crrg_arch_index() as $r) {
        $hay = $r['title'] . ' ' . $r['type'] . ' ' . $r['text'] . ' ' . $r['date'];
        $ok = true;
        foreach ($words as $w) if ($w !== '' && mb_strpos($hay, $w) === false) { $ok = false; break; }
        if ($ok) $out[] = $r;
        if (count($out) >= $limit) break;
    }
    return $out;
}

/** 检索（玩家侧）：花时间、留痕、按权限给结果 ✓ */
function crrg_arch_do(&$s, &$pc, $q) {
    $q = trim((string) $q);
    if ($q === '') return ['（查什么？打「档案 <关键词>」。）'];
    $hash = 'arch_' . substr(md5(mb_strtolower($q)), 0, 8);
    $fresh = !in_array($hash, (array) ($s['flags'] ?? []), true);
    $out = [];
    if ($fresh) {
        $mins = 20 + 10 * max(1, (int) (mb_strlen($q) / 3));
        if (function_exists('crrg_vitals_now') && isset($pc['vitals'])) {
            $pc['vitals']['t'] = crrg_vitals_now() - $mins * 60;      // 个人时间账 ✓
        }
        $s['flags'][] = $hash;
        $out[] = sprintf('（你在档案柜前站了约 %d 分钟。）', $mins);
    }
    $hits = crrg_arch_search($q);
    if (!$hits) return array_merge($out, ['（没有对应条目。也许换个说法，或者去别处问。）']);
    $out[] = sprintf('〔档案 · 命中 %d 条〕', count($hits));
    foreach ($hits as $i => $r) {
        $can = function_exists('crrg_arch_can') ? crrg_arch_can($pc, (string) $r['level']) : true;
        $out[] = sprintf('%d．%s　【%s】　%s', $i + 1, (string) $r['title'], (string) $r['type'], mb_substr((string) $r['date'], 0, 10));
        if ($can) {
            $snip = mb_substr(trim((string) $r['text']), 0, 60);
            if ($snip !== '') $out[] = '　　' . $snip . '…';
        } else {
            $out[] = '　　（这一条的等级不够，只能看到它的存在。要走审批。）';
        }
    }
    $out[] = '（要细看某一条：「调阅 <序号>」。）';
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('mission.beat', ['kind' => 'archive', 'q' => $q, 'n' => count($hits), 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'archive']);
    }
    return $out;
}

/** 调阅某一条（要够级别；不够就拒绝 ✓） */
function crrg_arch_open(&$s, &$pc, $n, $q = '') {
    $n = max(1, (int) $n);
    $hits = crrg_arch_search($q ?: ' ');
    if (!isset($hits[$n - 1])) return ['（没有这一条。）'];
    $r = $hits[$n - 1];
    if (!crrg_arch_can($pc, (string) $r['level'])) return ['（这一条的等级不够——要走审批。）'];
    $body = trim((string) $r['text']);
    $out = ['〔' . $r['title'] . '〕', $body !== '' ? $body : '（这一条只有编号，没有正文。）'];
    $s['flags'][] = 'arch_read_' . substr(md5((string) $r['id']), 0, 6);      // 读过留痕 ✓
    return $out;
}

/** 档案条目数（面板/门禁用） */
function crrg_arch_count() { return count(crrg_arch_index()); }
