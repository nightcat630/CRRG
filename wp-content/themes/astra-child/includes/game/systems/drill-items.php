<?php
/**
 * 虚拟演习 · 物品、房间实体扩充、车卡流程、同房间动态播报
 */
if (!defined('ABSPATH')) exit;

/* 房间掉落物的存储键（2026-09-29 补：此前**只被使用、从未定义** → 掉落物子系统 100% 抛错 ✗，
   是"证据式验证器"跑「帮助」时抓出来的；文本式自检看不到这类问题） */
if (!defined('CRRG_DRILL_DROPS')) define('CRRG_DRILL_DROPS', 'crrg_drill_drops');
/* 2026-09-29 补：房间库存的存储键（与掉落物同一类问题——"只用不定义"→ 库存子系统抛错 ✗，
   由证据式验证器跑「帮助」/商店路径时抓出） */
if (!defined('CRRG_DRILL_STOCK')) define('CRRG_DRILL_STOCK', 'crrg_drill_stock');

/* ================= 物品表 ================= */
function crrg_drill_items() {
    /* Phase 1（2026-09-29）：数据已搬到 includes/game/content/items.php（纯数据）。
       本函数保留为兼容壳；输出与搬移前逐字节一致。 */
    return function_exists('crrg_game_items_data') ? crrg_game_items_data() : [];
}
function crrg_drill_item_name($id) {
    if (function_exists('crrg_drill_item_id')) $id = crrg_drill_item_id($id);
    $i = crrg_drill_items();
    return $i[$id]['n'] ?? $id;
}

/* ================= 房间实体扩充（在原有基础上追加） ================= */
function crrg_drill_ents_extra() {
    /* Phase 1：数据已搬到 includes/game/content/entities.php（纯数据） */
    return function_exists('crrg_game_ents_extra_data') ? crrg_game_ents_extra_data() : [];
}
function crrg_drill_ents() {
    $base = crrg_drill_ents_base();
    foreach (crrg_drill_ents_extra() as $room => $list) {
        $base[$room] = array_merge($base[$room] ?? [], $list);
    }
    foreach (crrg_drill_scene_ents() as $room => $list) {   // 副本模组的实体
        $base[$room] = array_merge($base[$room] ?? [], $list);
    }
    foreach (crrg_drill_ents_train() as $room => $list) {    // 训练室
        $base[$room] = array_merge($base[$room] ?? [], $list);
    }
    return $base;
}

function crrg_drill_ent_desc_extra() {
    /* Phase 1：数据已搬到 includes/game/content/entities.php（纯数据） */
    return function_exists('crrg_game_ent_desc_data') ? crrg_game_ent_desc_data() : [];
}
function crrg_drill_feed_add($room, $who, $text, $kind = 'act') {
    $f = get_option('crrg_drill_feed', []);
    if (!is_array($f) || !isset($f['rooms'])) $f = ['seq' => 0, 'rooms' => []];
    $f['seq'] = (int) ($f['seq'] ?? 0) + 1;
    $f['rooms'][$room][] = ['id' => $f['seq'], 't' => current_time('H:i'), 'who' => $who,
                            'text' => $text, 'kind' => $kind];
    if (count($f['rooms'][$room]) > 30) $f['rooms'][$room] = array_slice($f['rooms'][$room], -30);
    update_option('crrg_drill_feed', $f, false);
    return $f['seq'];
}
function crrg_drill_feed_since($room, $after) {
    $f = get_option('crrg_drill_feed', []);
    $out = [];
    foreach ((array) ($f['rooms'][$room] ?? []) as $m) {
        if ((int) $m['id'] > (int) $after) $out[] = $m;
    }
    return $out;
}
function crrg_drill_feed_last($room) {
    $f = get_option('crrg_drill_feed', []);
    $msgs = (array) ($f['rooms'][$room] ?? []);
    $last = end($msgs);
    return $last ? (int) $last['id'] : 0;
}

/* ================= 车卡流程 ================= */
function crrg_drill_create(&$pc, $uid, $cmd) {
    $lines = [];
    if (empty($pc['attrs'])) $pc = crrg_coc_new_pc();
    if ($cmd === '') $cmd = '角色卡';
    $parts = explode(' ', trim(preg_replace('/\s+/u', ' ', $cmd)), 2);
    $v = $parts[0]; $arg = trim($parts[1] ?? '');
    $occ = crrg_coc_occ();

    switch (true) {
        case (bool) preg_match('/^(掷骰|重掷|roll)$/u', $v):
            $pc['attrs'] = crrg_coc_roll_attrs();
            $pc['stage']  = 'occ';
            $lines[] = '掷骰完成。力量/体质/敏捷/外貌/意志＝3D6×5，体型/智力/教育＝(2D6+6)×5，幸运＝3D6×5。';
            $lines = array_merge($lines, array_slice(crrg_coc_sheet_lines(['attrs' => $pc['attrs'], 'hp' => 0,
                     'mp' => 0, 'san' => 0, 'name' => '', 'occ' => '', 'posture' => '站立']), 1, 3));
            $lines[] = '下一步：选职业 <名称>。可选：' . implode('、', array_keys($occ));
            break;

        case (bool) preg_match('/^选职业\s+(.+)$/u', $cmd, $mm):
            if (empty($pc['attrs'])) { $lines[] = '先「掷骰」。'; break; }
            $name = trim($mm[1]);
            $hit = '';
            foreach ($occ as $k => $sk) if (mb_strpos($k, $name) !== false || mb_strpos($name, $k) !== false) { $hit = $k; break; }
            if (!$hit) { $lines[] = '没有这个职业。可用「选职业」看列表。'; break; }
            $pc['occ'] = $hit;
            $pc['skills'] = crrg_coc_skill_base($pc['attrs']);
            $pc['occ_pts'] = (int) $pc['attrs']['教育'] * 4;
            $pc['int_pts'] = (int) $pc['attrs']['智力'] * 2;
            $pc['stage'] = 'alloc';
            $lines[] = "职业：{$hit}。技能点：本职 {$pc['occ_pts']} 点（教育×4）、兴趣 {$pc['int_pts']} 点（智力×2）。";
            $lines[] = '本职技能：' . implode('、', $occ[$hit]);
            $lines[] = '用「加点 <技能> <点数>」分配（本职扣职业点，其他扣兴趣点）；也可以「自动分配」一次点完，然后「完成」。';
            break;

        case ($cmd === '选职业'):
            $lines[] = '可选职业（各含八项本职技能）：';
            foreach ($occ as $k => $sk) $lines[] = '  ' . $k . '：' . implode('、', $sk);
            $lines[] = '用「选职业 <名称>」确定。';
            break;

        case (bool) preg_match('/^加点\s+(\S+)\s+(\d+)$/u', $cmd, $mm):
            if (($pc['stage'] ?? '') !== 'alloc') { $lines[] = '先选职业。'; break; }
            $sk = $mm[1]; $n = (int) $mm[2];
            $key = '';
            foreach (array_keys($pc['skills']) as $k) if ($k === $sk || mb_strpos($k, $sk) !== false) { $key = $k; break; }
            if (!$key) { $lines[] = "没有「{$sk}」这项技能。"; break; }
            if ($n <= 0) { $lines[] = '点数要大于零。'; break; }
            $isOcc = in_array($key, $occ[$pc['occ']], true);
            $pool = $isOcc ? 'occ_pts' : 'int_pts';
            if ($pc[$pool] < $n) { $lines[] = sprintf('点数不够：%s只剩 %d 点。', $isOcc ? '职业点' : '兴趣点', $pc[$pool]); break; }
            if ($pc['skills'][$key] + $n > 99) { $lines[] = '技能上限 99%。'; break; }
            $pc['skills'][$key] += $n;
            $pc[$pool] -= $n;
            $lines[] = sprintf('%s → %d%%（%s点剩 %d）', $key, $pc['skills'][$key], $isOcc ? '职业' : '兴趣', $pc[$pool]);
            break;

        case (bool) preg_match('/^自动分配$/u', $v):
            if (($pc['stage'] ?? '') !== 'alloc') { $lines[] = '先选职业。'; break; }
            $order = array_merge($occ[$pc['occ']], ['侦查', '闪避', '聆听', '急救', '图书馆使用', '潜行']);
            $spent = 0;
            foreach ($order as $k) {
                if (!isset($pc['skills'][$k])) continue;
                $isOcc = in_array($k, $occ[$pc['occ']], true);
                $pool = $isOcc ? 'occ_pts' : 'int_pts';
                $give = min($pc[$pool], 99 - $pc['skills'][$k]);
                if ($give <= 0) continue;
                $pc['skills'][$k] += $give;
                $pc[$pool] -= $give;
                $spent += $give;
            }
            $lines[] = "已自动分配 {$spent} 点。剩余：职业 {$pc['occ_pts']}、兴趣 {$pc['int_pts']}。";
            $lines[] = '满意就「完成」；想改可继续「加点」。';
            break;

        case (bool) preg_match('/^(完成|就绪|确认)$/u', $v):
            if (($pc['stage'] ?? '') !== 'alloc') { $lines[] = '流程还没到这一步。'; break; }
            $u = get_userdata($uid);
            $pc['name'] = $pc['name'] ?: ($u ? $u->display_name : '演习员');
            $d = crrg_coc_derive($pc['attrs']);
            $pc['hp'] = $d['hp_max']; $pc['mp'] = $d['mp_max']; $pc['san'] = $d['san_max'];
            $pc['ready'] = true; $pc['stage'] = 'done';
            $lines[] = '角色卡已建立，可以进演习了。';
            $lines = array_merge($lines, crrg_coc_sheet_lines($pc));
            break;

        case (bool) preg_match('/^(角色卡|卡|属性)$/u', $v):
        default:
            $lines[] = '【车卡】首次进入要先建角色卡。流程：';
            $lines[] = '  掷骰　　　　→ 生成八项属性与幸运（可「重掷」）';
            $lines[] = '  选职业 <名> → 定职业，得到技能点（教育×4 本职 / 智力×2 兴趣）';
            $lines[] = '  加点 <技能> <点数>　或　自动分配';
            $lines[] = '  完成　　　　→ 计算生命/魔法/理智，进入演习';
            if (!empty($pc['attrs'])) { $lines = array_merge($lines, ['', '当前：'], crrg_coc_sheet_lines($pc)); }
            break;
    }
    return $lines;
}

/* ================= 背包操作 ================= */
function crrg_drill_bag_add(&$pc, $id, $n = 1) {
    if (function_exists('crrg_drill_item_id')) $id = crrg_drill_item_id($id);
    for ($i = 0; $i < $n; $i++) $pc['items'][] = $id;
    return $pc['items'];
}
function crrg_drill_bag_remove(&$pc, $id) {
    $k = array_search($id, (array) $pc['items'], true);
    if ($k !== false) { array_splice($pc['items'], $k, 1); return true; }
    $k = array_search($id, (array) $pc['worn'], true);
    if ($k !== false) { array_splice($pc['worn'], $k, 1); return true; }
    return false;
}
/** 按名字（或部分名字）在背包＋穿戴里找物品 id */
function crrg_drill_find_bag($pc, $q) {
    $q = trim((string) $q);
    if ($q === '') return null;
    $all = array_merge((array) ($pc['items'] ?? []), (array) ($pc['worn'] ?? []));
    foreach ([true, false] as $exact) {
        foreach ($all as $id) {
            $n = crrg_drill_item_name($id);
            if ($exact ? ($n === $q || $id === $q) : (mb_strpos($n, $q) !== false || mb_strpos($q, $n) !== false)) return $id;
        }
    }
    return null;
}

/* 合并版说明表：基础 + 扩充 */
function crrg_drill_ent_desc() {
    return crrg_drill_ent_desc_base() + crrg_drill_ent_desc_extra();
}

/* 训练室实体与说明 */
function crrg_drill_ents_train() {
    /* Phase 1：数据已搬到 includes/game/content/entities.php（纯数据） */
    return function_exists('crrg_game_ents_train_data') ? crrg_game_ents_train_data() : [];
}
function crrg_drill_unique_items() {
    return ['key_timekey', 'sigil', 'key_sealbox', 'key_idcard', 'doc_camera', 'doc_recorder', 'tool_binocular', 'tool_gps', 'weap_knife'];
}
function crrg_drill_ent_stock($e) {
    if (isset($e['stock'])) return max(1, (int) $e['stock']);
    if (in_array($e['take'] ?? '', crrg_drill_unique_items(), true)) return 1;
    return 3;
}
/** 按游戏日补货（2026-09-29）：基地房间的配发物每天回到默认库存
 *  否则一旦被取光（例如训练/演习被反复领用），后来的玩家会永久拿不到，直接卡死。 */
function crrg_drill_stock_refill() {
    $day = function_exists('crrg_wdate') && function_exists('crrg_vitals_now')
         ? crrg_wdate('Ymd', crrg_vitals_now()) : date('Ymd');
    if (get_option('crrg_roomstock_day', '') === $day) return;
    update_option('crrg_roomstock_day', $day, false);
    $S = get_option(CRRG_DRILL_STOCK, []); if (!is_array($S)) $S = [];
    $changed = false;
    foreach ((array) crrg_drill_ents() as $room => $list) {
        foreach ((array) $list as $e) {
            if (empty($e['take'])) continue;
            $def = crrg_drill_ent_stock($e);                 // 唯一件＝1，其余＝3
            $cur = (int) ($S[$room][$e['n']] ?? 0);
            if ($cur < $def) { $S[$room][$e['n']] = $def; $changed = true; }
        }
    }
    if ($changed) update_option(CRRG_DRILL_STOCK, $S, false);
}
/** 取某房间某物的剩余份数（首次访问按定义初始化） */
function crrg_drill_stock_get($room, $name, $default = 1) {
    $S = get_option(CRRG_DRILL_STOCK, []);
    if (!is_array($S)) $S = [];
    if (!isset($S[$room][$name])) { $S[$room][$name] = (int) $default; update_option(CRRG_DRILL_STOCK, $S, false); }
    return (int) $S[$room][$name];
}
function crrg_drill_stock_take($room, $name, $n = 1) {
    $S = get_option(CRRG_DRILL_STOCK, []); if (!is_array($S)) $S = [];
    $cur = (int) ($S[$room][$name] ?? 0);
    if ($cur < $n) return false;
    $S[$room][$name] = $cur - $n;
    update_option(CRRG_DRILL_STOCK, $S, false);
    return true;
}
/** 房间里的掉落物 */
function crrg_drill_drops($room) {
    $D = get_option(CRRG_DRILL_DROPS, []);
    return is_array($D) && isset($D[$room]) ? (array) $D[$room] : [];
}
function crrg_drill_drop_add($room, $id, $n = 1) {
    $D = get_option(CRRG_DRILL_DROPS, []); if (!is_array($D)) $D = [];
    $D[$room][$id] = (int) ($D[$room][$id] ?? 0) + $n;
    update_option(CRRG_DRILL_DROPS, $D, false);
}
function crrg_drill_drop_take($room, $id, $n = 1) {
    $D = get_option(CRRG_DRILL_DROPS, []); if (!is_array($D)) return false;
    $cur = (int) ($D[$room][$id] ?? 0);
    if ($cur < $n) return false;
    if ($cur - $n <= 0) unset($D[$room][$id]); else $D[$room][$id] = $cur - $n;
    update_option(CRRG_DRILL_DROPS, $D, false);
    return true;
}
/** 背包按件数归纳 */
function crrg_drill_bag_counts($pc) {
    $out = [];
    foreach ((array) ($pc['items'] ?? []) as $id) $out[$id] = (int) ($out[$id] ?? 0) + 1;
    return $out;
}
function crrg_drill_bag_count($pc, $id) { return (int) (crrg_drill_bag_counts($pc)[$id] ?? 0); }
function crrg_drill_bag_add_n(&$pc, $id, $n = 1) {
    if (function_exists('crrg_drill_item_id')) $id = crrg_drill_item_id($id); for ($i = 0; $i < $n; $i++) $pc['items'][] = $id; }
function crrg_drill_bag_del_n(&$pc, $id, $n = 1) {
    if (function_exists('crrg_drill_item_id')) $id = crrg_drill_item_id($id);
    $took = 0;
    foreach ((array) $pc['items'] as $k => $v) {
        if ($v !== $id || $took >= $n) continue;
        unset($pc['items'][$k]); $took++;
    }
    $pc['items'] = array_values((array) $pc['items']);
    return $took;
}
/** 个人仓库（宿舍储物柜 / 仓库物资柜共用一份） */
function crrg_drill_stash(&$pc) { return (array) ($pc['stash'] ?? []); }
function crrg_drill_stash_add(&$pc, $id, $n = 1) {
    $pc['stash'] = (array) ($pc['stash'] ?? []);
    $pc['stash'][$id] = (int) ($pc['stash'][$id] ?? 0) + $n;
}
function crrg_drill_stash_take(&$pc, $id, $n = 1) {
    $pc['stash'] = (array) ($pc['stash'] ?? []);
    $cur = (int) ($pc['stash'][$id] ?? 0);
    if ($cur < $n) return false;
    if ($cur - $n <= 0) unset($pc['stash'][$id]); else $pc['stash'][$id] = $cur - $n;
    return true;
}
function crrg_drill_stash_label($id) { return crrg_drill_item_name($id); }

/** 旧 id → 新 id（2026-09-28 命名迁移；老存档与老引用都靠它兜住） */
function crrg_drill_item_alias() {
    /* Phase 1（2026-09-29）：数据已搬到 includes/game/content/items.php（纯数据）。
       本函数保留为兼容壳；输出与搬移前逐字节一致。 */
    return function_exists('crrg_game_item_alias_data') ? crrg_game_item_alias_data() : [];
}
function crrg_drill_item_id($id) {
    $m = crrg_drill_item_alias();
    return $m[$id] ?? $id;
}
/** 把一张卡上的物品 id 迁到新命名（老存档用） */
function crrg_drill_items_migrate(&$pc) {
    $m = crrg_drill_item_alias();
    foreach (['items', 'stash'] as $k) {
        if (empty($pc[$k]) || !is_array($pc[$k])) continue;
        $new = [];
        foreach ($pc[$k] as $kk => $vv) {
            if (is_string($vv)) { $new[] = $m[$vv] ?? $vv; }          // items：列表
            else { $new[$m[$kk] ?? $kk] = $vv; }                      // stash：id=>数量
        }
        $pc[$k] = $new;
    }
    return $pc;
}
