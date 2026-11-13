<?php
/**
 * 命名自检（2026-09-28）
 * 用途：部署前跑一遍，专抓这几类"撞名/失配"问题——
 *   ① 同一个 switch 里重复的 case（后写的永远不执行）
 *   ② 同名函数被定义多次（后加载的覆盖前面的）
 *   ③ 动作 id（panel k / cmd verb）在不同分发器里重名
 *   ④ 物品 id：代码里引用但物品表里没有（或反之，从未被引用的死物品）
 *   ⑤ 场景房间键重复定义 / 未定义引用
 *   ⑥ sprintf 字符串里的裸 %（PHP 8 会抛错）
 * 用法：php tools/lint-names.php
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
/* 2026-09-29：目录重构后，运行时检查（注册表/房间表/面板）需要游戏已加载 → 显式引导 WP
   （与其它工具一致；也让检查更真实） */
if (!function_exists('crrg_drill_reg')) {
    $wl = '/www/wwwroot/crrg_moebius_com_114/wp-load.php';


    if (file_exists($wl)) require $wl;

/* 2026-10-14：**门禁不许留痕** —— 本工具会跑游戏逻辑 → 会落事实 ✗
   ⚠ 必须插在**真正的 require 语句之后**（插在 `$wl = ...` 赋值行之后＝还没加载 WP → fatal ✗ 今天栽了两次） */
$__ft = function_exists('crrg_facts_table') ? crrg_facts_table() : '';
$__maxId = 0;
if ($__ft) { global $wpdb; $__maxId = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM $__ft"); }
$__optBak = [];
foreach (['crrg_world_tick','crrg_last_day','crrg_fact_ping','crrg_recent_settle','crrg_world_day_note','crrg_fight_pending','crrg_travel_pending','crrg_bt_state','crrg_duty','crrg_duty_roster'] as $o) $__optBak[$o] = get_option($o, null);
register_shutdown_function(function () use ($__ft, $__maxId, $__optBak) {
    if ($__ft) { global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM $__ft WHERE id > %d", $__maxId)); }
    foreach ($__optBak as $o => $v) { if ($v === null) delete_option($o); else update_option($o, $v, false); }
});
}
/* 2026-09-29：目录重构后要**递归**扫描（core/systems/modules/missions/content 都在 includes/game 下） */
$files = array_merge(
    glob($THEME . '/*.php') ?: [],
    glob($THEME . '/includes/*.php') ?: [],
    glob($THEME . '/includes/game/*.php') ?: [],
    glob($THEME . '/includes/game/*/*.php') ?: []
);
/* 按文件名在旧/新目录里查找（搬迁后仍可用） */
function crrg_lint_find_file($name) {
    foreach (['/includes/', '/includes/game/core/', '/includes/game/systems/', '/includes/game/missions/',
              '/includes/game/modules/town/', '/includes/game/modules/chase/', '/includes/game/modules/wild/',
              '/includes/game/content/'] as $dir) {
        $p = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child' . $dir . $name;
        if (file_exists($p)) return $p;
    }
    return '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child/includes/' . $name;
}
$bad = []; $warn = [];

/* ── ① switch case 重复 ── */
foreach ($files as $f) {
    $src = file_get_contents($f);
    $lines = explode("\n", $src);
    $stack = [];                       // 每个 switch 的 case 集合
    $depth = 0; $si = 0;
    foreach ($lines as $i => $ln) {
        if (preg_match('/\bswitch\s*\(/', $ln)) { $stack[++$si] = ['cases' => [], 'depth' => $depth]; }
        if (preg_match('/^\s*case\s+\'([a-zA-Z0-9_|:-]+)\'\s*:/', $ln, $m)) {
            $key = $m[1];
            $cur = end($stack);
            $idx = key($stack);
            if ($cur && isset($cur['cases'][$key])) {
                $bad[] = sprintf('%s:%d 重复 case \'%s\'（首次在第 %d 行）', basename($f), $i + 1, $key, $cur['cases'][$key]);
            } elseif ($cur) {
                $stack[$idx]['cases'][$key] = $i + 1;
            }
        }
        if (preg_match('/\bswitch\s*\(/', $ln)) { /* 上面已处理 */ }
        $depth += substr_count($ln, '{') - substr_count($ln, '}');
        while ($stack && $depth <= end($stack)['depth']) array_pop($stack);
    }
}
/* ── ② 函数重复定义 ── */
$defs = [];
foreach ($files as $f) {
    foreach (explode("\n", file_get_contents($f)) as $i => $ln) {
        /* 只认行首无缩进的 PHP 函数定义（排除 <script> 里的 JS 函数） */
        if (preg_match('/^function\s+([a-zA-Z0-9_]+)\s*\(/', $ln, $m)) $defs[$m[1]][] = basename($f) . ':' . ($i + 1);
    }
}
foreach ($defs as $fn => $where) if (count($where) > 1) $bad[] = sprintf('函数重名 %s（%s）', $fn, implode('、', $where));

/* ── ③ 动作 id 重名（panel k / cmd verb） ── */
$verbs = []; $acts = [];
foreach ($files as $f) {
    $src = file_get_contents($f);
    if (preg_match_all("/case '([a-z_]+)':\s*\/\//", $src, $m)) foreach ($m[1] as $v) $verbs[$v][] = basename($f);
    if (preg_match_all("/\{\$verb = '([a-z_]+)'/", $src, $m2)) foreach ($m2[1] as $v) $acts[$v][] = basename($f);
}
foreach ($acts as $v => $where) if (count(array_unique($where)) > 1) $warn[] = sprintf('动词 %s 在多个文件里分发（%s）——注意别撞语义', $v, implode('、', array_unique($where)));

/* ── ④ 物品 id 引用检查 ── */
/* 2026-09-29：文件已迁入 includes/game/ → 守卫改为"函数可用"（WP 已引导） */
if (function_exists('crrg_drill_items')) {
    $items = crrg_drill_items();
    $refs = [];
    foreach ($files as $f) {
        $src = file_get_contents($f);
        if (preg_match_all("/crrg_drill_item_name\(\s*'([a-z_]+)'/", $src, $m)) foreach ($m[1] as $id) $refs[$id][] = basename($f);
        if (preg_match_all("/crrg_drill_bag_count\(\\\$pc,\s*'([a-z_]+)'/", $src, $m3)) foreach ($m3[1] as $id) $refs[$id][] = basename($f);
    }
    foreach ($refs as $id => $where) if (!isset($items[$id])) $bad[] = sprintf('引用了不存在的物品 id「%s」（%s）', $id, implode('、', array_unique($where)));
}

/* ── ④a 物品表里重复定义的 id（后定义的会静默覆盖前者） ── */
if (file_exists(crrg_lint_find_file('drill-items.php'))) {
    $src = file_get_contents(crrg_lint_find_file('drill-items.php'));
    preg_match_all("/^\s*'([a-z0-9_]+)'\s*=>\s*\[/m", $src, $mm);
    $cnt = array_count_values($mm[1]);
    foreach ($cnt as $iid => $n2) if ($n2 > 1) $bad[] = sprintf('物品表里「%s」被定义了 %d 次（后者覆盖前者）', $iid, $n2);
}

/* ── ④a2 物品 cat 必须是小类 id ── */
if (isset($items) && function_exists('crrg_item_taxonomy')) {
    $tax = crrg_item_taxonomy();
    foreach ($items as $iid => $it3) {
        if (empty($it3['cat'])) { $bad[] = sprintf('物品「%s」没有 cat（分类）', $iid); continue; }
        if (!isset($tax[$it3['cat']])) $bad[] = sprintf('物品「%s」的 cat=%s 不是合法分类', $iid, $it3['cat']);
    }
}

/* ── ④a3 商品（商店）引用的物品必须存在；家具类（有 'dorm' 字段）不算物品 ── */
if (isset($items) && function_exists('crrg_drill_goods')) {
    foreach (crrg_drill_goods() as $cat => $list) {
        foreach ($list as $gid => $g) {
            if (isset($g['dorm']) || isset($g['furn']) || isset($g['deco']) || isset($g['vehicle']) || isset($g['bundle'])) continue;   // 家具／装修／车辆／补给包：不是背包物品
            $iid = $g['item'] ?? $gid;
            if (!isset($items[$iid])) $bad[] = sprintf('商品「%s」（%s）引用了不存在的物品 id「%s」', $g['n'] ?? $gid, $cat, $iid);
        }
    }
}

/* ── ④a4 同一商店里不要重复卖同一物品（同名／同 item） ── */
if (function_exists('crrg_drill_goods')) {
    foreach (crrg_drill_goods() as $cat => $list) {
        $seenItem = []; $seenName = [];
        foreach ($list as $gid => $g) {
            if (isset($g['dorm']) || isset($g['furn']) || isset($g['deco']) || isset($g['vehicle']) || isset($g['bundle'])) continue;
            $iid = $g['item'] ?? $gid;
            if (isset($seenItem[$iid])) $bad[] = sprintf('%s 里「%s」与「%s」重复卖同一物品（item=%s）', $cat, $seenItem[$iid], $gid, $iid);
            else $seenItem[$iid] = $gid;
            $nm = (string) ($g['n'] ?? '');
            if ($nm !== '' && isset($seenName[$nm])) $warn[] = sprintf('%s 里商品同名「%s」（%s 与 %s）——确认是否是同一件东西', $cat, $nm, $seenName[$nm], $gid);
            else if ($nm !== '') $seenName[$nm] = $gid;
        }
    }
}

/* ── ④a5 货架必须挂到某个商店：否则是"死库存"（玩家永远看不到） ── */
if (function_exists('crrg_drill_goods') && function_exists('crrg_drill_shops')) {
    $used = [];
    foreach ((array) crrg_drill_shops() as $sid => $shop) foreach ((array) ($shop['cats'] ?? []) as $c) $used[$c] = $sid;
    foreach (array_keys(crrg_drill_goods()) as $cat) {
        if (!isset($used[$cat])) $warn[] = sprintf('货架「%s」没有挂到任何商店（死库存：玩家看不到）', $cat);
    }
}

/* ── ④a6 商店不要重复定义（同房同名，或同房同货架） ── */
if (function_exists('crrg_drill_shops')) {
    $seenShop = [];
    foreach ((array) crrg_drill_shops() as $sid => $shop) {
        $sig = ($shop['room'] ?? '') . '|' . ($shop['n'] ?? '');
        $sig2 = ($shop['room'] ?? '') . '|' . implode(',', (array) ($shop['cats'] ?? []));
        if (isset($seenShop[$sig])) $bad[] = sprintf('商店重复：%s 在 %s 定义了两次（%s 与 %s）', $shop['n'] ?? $sid, $shop['room'] ?? '', $seenShop[$sig], $sid);
        elseif (isset($seenShop[$sig2])) $warn[] = sprintf('商店 %s 与 %s 同房间同货架，可能重复', $seenShop[$sig2], $sid);
        else { $seenShop[$sig] = $sid; $seenShop[$sig2] = $sid; }
    }
}

/* ── ④a7 分类与"能不能吃/喝"必须一致：food_* 要有 food 或 ingredient；drink_* 要有 drink ── */
if (isset($items) && function_exists('crrg_item_taxonomy')) {
    $tax = crrg_item_taxonomy();
    foreach ($items as $iid => $it4) {
        $maj = $tax[$it4['cat'] ?? '']['major'] ?? '';
        if ($maj === 'food' && empty($it4['food']) && empty($it4['ingredient'])) $warn[] = sprintf('食品「%s」既没标 food（可直接吃）也没标 ingredient（原料）', $iid);
        if ($maj === 'drink' && empty($it4['drink'])) $warn[] = sprintf('饮品「%s」没有 drink 标记', $iid);
    }
}

/* ── ④a8 身体效果表（crrg_vitals_item_fx）的键必须是存在的物品 id ── */
if (isset($items) && function_exists('crrg_vitals_item_fx')) {
    foreach ($items as $iid => $it5) {
        $fx = crrg_vitals_item_fx($iid);
        if ($fx && !array_key_exists($iid, array_flip(array_keys($items)))) { /* 不可能 */ }
    }
    /* 全站空键（'' => …）：键名被改坏的征兆，一律报错 */
    foreach ($files as $f2) {
        $src2 = file_get_contents($f2);
        if (preg_match_all("/^\s*''\s*=>/m", $src2, $mk)) {
            $bad[] = sprintf('%s 里有 %d 处空键（空字符串键名）——键名被改坏，对应功能会静默失效', basename($f2), count($mk[0]));
        }
    }

    /* 反向：扫源码里写死的效果键 */
    $src = file_get_contents(crrg_lint_find_file('drill-vitals.php'));
    if (preg_match('/function crrg_vitals_item_fx.*?return \$M\[\$id\]/s', $src, $mm)) {
        preg_match_all("/^\s*'([a-z0-9_]+)'\s*=>\s*\['(food|water|energy|why)/m", $mm[0], $kk);
        foreach ($kk[1] as $key) if (!isset($items[$key])) $bad[] = sprintf('身体效果表里有不存在的物品 id「%s」', $key);
    }
}

/* ── ④a9 身体状态键白名单（历史上的 water→tool_flask 事故） ── */
$VK = array_flip(['t','energy','food','water','fatigue','rest_until','rest_where','rest_bonus','coffee_today','coffee_day','flask','seen_real','treat_day','treat_n',
    /* 2026-10-01 新增的两个**内部账**键（正当 ✓）：分档提示的基线、惩罚骰提示的记忆 */
    '_tier_seen','_pen_seen']);
$needleVitals = '$pc[' . chr(39) . 'vitals' . chr(39) . '][';
foreach ($files as $f) {
    $src = file_get_contents($f);
    $nds = [$needleVitals];
    if (basename($f) === 'drill-vitals.php') $nds[] = '$v[';
    foreach ($nds as $nd) {
        $off = 0;
        while (($pos = strpos($src, $nd, $off)) !== false) {
            $off = $pos + 1;
            $rest = substr($src, $pos + strlen($nd), 40);
            if (!preg_match('/^[\x27"]([a-z_]+)[\x27"]\]/', $rest, $m)) continue;
            if (!isset($VK[$m[1]])) $bad[] = basename($f) . ' 里出现未知的身体状态键 ' . $nd . $m[1];
        }
    }
}
/* 物品标记白名单 */
if (isset($items)) {
    $IF = array_flip(['cat','n','d','take','food','drink','ingredient','wear','armor','dmg','skill','onuse','quest','deco','furn','stack','worn','use','combat','ammo','cap','feed','unknown','appr','id_note','id_flag','drinkable','light','fuel']);
    foreach ($items as $iid => $it6) foreach (array_keys($it6) as $fl) if (!isset($IF[$fl])) $warn[] = sprintf('物品「%s」有未登记的标记「%s」', $iid, $fl);
}

/* ── ⑤ 房间键：同一场景内重复定义 ── */
if (function_exists('crrg_travel_ensure_region')) crrg_travel_ensure_region();
$sceneIds = array_keys((array) (crrg_drill_reg()['scenes'] ?? []));
$roomSeen = [];
foreach ($sceneIds as $sc) {
    crrg_drill_set_scene($sc);
    foreach (array_keys((array) crrg_drill_scene_rooms()) as $k) {
        if (isset($roomSeen[$sc][$k])) $bad[] = sprintf('场景 %s 里房间键 %s 重复定义', $sc, $k);
        $roomSeen[$sc][$k] = 1;
    }
}
crrg_drill_set_scene('base');
$roomCount = [];
foreach ($roomSeen as $sc => $ks) foreach (array_keys($ks) as $k) $roomCount[$k][] = $sc;

/* ── ⑤b 各处配置表里引用的房间键必须真实存在 ── */
$allRooms = [];
$sceneIds2 = array_keys((array) (crrg_drill_reg()['scenes'] ?? []));
foreach ($sceneIds2 as $sc) { crrg_drill_set_scene($sc); foreach (array_keys((array) crrg_drill_scene_rooms()) as $k) $allRooms[$k] = $sc; }
crrg_drill_set_scene('base');
foreach (array_keys((array) crrg_drill_rooms()) as $k) $allRooms[$k] = 'base';
$roomRefs = [];
if (function_exists('crrg_appraise_sites')) foreach (array_keys(crrg_appraise_sites()) as $r) $roomRefs['鉴定站'][] = $r;
if (function_exists('crrg_drill_shops')) foreach (crrg_drill_shops() as $sid => $sh) $roomRefs['商店'][] = $sh['room'] ?? '';
if (function_exists('crrg_travel_fuel_spots')) foreach (array_keys(crrg_travel_fuel_spots()) as $r) $roomRefs['加油点'][] = $r;
foreach ($roomRefs as $what => $rooms) {
    foreach (array_unique($rooms) as $r) {
        if ($r === '' || $r === 'baishi_node') continue;
        if (!isset($allRooms[$r])) $bad[] = sprintf('%s 引用了不存在的房间键「%s」', $what, $r);
    }
}

/* ── ⑤c 面板发出的动作 k 必须有 do_act 的 case（否则点了回「未知操作」） ── */
if (file_exists(crrg_lint_find_file('drill-economy.php'))) {
    $srcE = @file_get_contents(crrg_lint_find_file('drill-economy.php')) ?: '';
    $i0 = strpos($srcE, 'function crrg_drill_do_act(');
    $i1 = strpos($srcE, "['未知操作。']", $i0);
    $body = $i0 !== false && $i1 !== false ? substr($srcE, $i0, $i1 - $i0) : $srcE;
    preg_match_all("/case '([a-z_0-9]+)':/", $body, $mm);
    $handled = array_flip($mm[1]);
    $emit = [];
    foreach (['drill-economy.php', 'drill-missions-base.php', 'drill-mission-seal-multi.php', 'drill-mission-seal.php', 'drill-mission-seal2.php', 'drill-mission-seal3.php', 'drill-mission-seal4.php', 'drill-mission-seal5.php', 'drill-mod-chase.php', 'drill-combat.php'] as $f) {
        $fp = $THEME . '/includes/' . $f;
        if (!file_exists($fp)) continue;
        $src = file_get_contents($fp);
        if (preg_match_all("/'k'\s*=>\s*'([a-z_0-9]+)'/", $src, $m2)) foreach ($m2[1] as $k) $emit[$k] = $f;
    }
    $ignore = ['noop' => 1, 'divider' => 1, 'item' => 1, 'chase' => 1, 'dlg' => 1, 'bt_start' => 1, 'talk' => 1, 'vehicle' => 1,
               'npc' => 1, 'pc' => 1, 'prop' => 1, 'fire' => 1, 'actor' => 1];   // 战斗界面的选择键
    foreach ($emit as $k => $f) {
        if (isset($ignore[$k])) continue;
        if (strpos($k, 'bt_') === 0) {                      // 战斗动作统一转 crrg_bt_act()（2026-09-29 拆分后分散在多个文件 → 全局找）
            $hasBt = false;
            foreach (array_merge(glob($THEME . '/includes/drill*.php') ?: [], glob($THEME . '/includes/game/*/*.php') ?: [], glob($THEME . '/includes/game/*.php') ?: []) as $gf) {
                if (strpos(file_get_contents($gf), 'crrg_bt_act') !== false) { $hasBt = true; break; }
            }
            if ($hasBt) continue;
        }
        if (!isset($handled[$k])) $bad[] = sprintf('面板动作 k=%s（%s）在 crrg_drill_do_act 里没有对应 case——点了会回「未知操作」', $k, $f);
    }
}

/* ── ⑤d 任务步骤旗标必须和"当场动作表"对得上（防 st5_记录 这类错配） ── */
if (function_exists('crrg_base_mission_acts') && function_exists('crrg_drill_missions')) {
    foreach (crrg_drill_missions() as $mid => $m) {
        $actFlags = [];
        foreach ((array) (crrg_base_mission_acts()[$mid] ?? []) as $room2 => $specs2) foreach ((array) $specs2 as $sp2) $actFlags[$sp2[1]] = 1;
        if (!$actFlags) continue;                       // 没有动作表的任务不校验
        foreach ((array) ($m['steps'] ?? []) as $i => $st) {
            $f = $st['flag'] ?? '';
            if ($f === '' || isset($actFlags[$f])) continue;
            $bad[] = sprintf('任务「%s」第 %d 步要求旗标 %s，但当场动作表里没有任何动作会写入它（玩家会永远卡在第 %d 步）', $m['name'] ?? $mid, $i + 1, $f, $i + 1);
        }
    }
}

/* ── ⑤e 任务路径不能撞门禁：步骤房间的门禁等级 > 接单等级时，必须写进 pass_rooms ── */
if (function_exists('crrg_drill_missions') && function_exists('crrg_drill_gate_of')) {
    foreach (crrg_drill_missions() as $mid => $m) {
        $minLv = (int) ($m['min_lv'] ?? 3);
        $pass  = (array) ($m['pass_rooms'] ?? []);
        $rooms = [];
        foreach ((array) ($m['steps'] ?? []) as $st) { $r = $st['room'] ?? ($st['hint_room'] ?? ''); if ($r !== '') $rooms[$r] = 1; }
        /* 加上"途经房间"：从基地入口到目标房间的必经点（用简化判断：B4 类房间必经缓冲区） */
        if (preg_grep('/^b4_/', array_keys($rooms))) $rooms['b4_buffer'] = 1;
        foreach (array_keys($rooms) as $r) {
            $need = crrg_drill_gate_of($r);
            if ($need > 1 && $need > $minLv && !in_array($r, $pass, true)) {
                $bad[] = sprintf('任务「%s」（接单需 %d 级）要走 %s（门禁 %s 级），但没写进 pass_rooms——玩家会卡在门口',
                    $m['name'] ?? $mid, $minLv, $r, crrg_drill_card_name($need));
            }
        }
    }
}

/* ── ⑤f 世界钟异常（2026-10-09 再修）：旧判据"锚点 game 与 real 相差 >3 天"会**误报** ✗ ——
 *    锚点本来就是**人为定**的（可以领先现实、也可以落后），48 倍速下这差值还会一直变 ✓。
 *    只保留**一种**真事故的判据：世界钟停在**快照工具的钉值**上（＝钉完没还原 ✗）✓ ── */
$wcOpt = get_option('crrg_drill_clock', null);
if (is_array($wcOpt) && !empty($wcOpt['game'])) {
    $pin = strtotime('2026-09-29 10:00:00');                  // 与 tools/snapshot-*.php 的钉值同源
    if ($pin && abs((int) $wcOpt['game'] - $pin) < 120) {
        $warn[] = '世界钟停在快照钉值（2026-09-29 10:00）上——snapshot-game/seal 钉钟后没还原，请校正';
    }
}

/* ── ⑤i 状态读写纪律（2026-09-29｜游戏侧优化）：exec 的 $s 是按值传，必须接住返回状态 ── */
foreach ($files as $f3) {
    $src3 = file_get_contents($f3);
    foreach (preg_split('/\n/', $src3) as $ln3 => $line3) {
        if (strpos($line3, 'crrg_drill_exec(') === false) continue;
        if (strpos($line3, 'function crrg_drill_exec') !== false) continue;   // 定义行
        if (preg_match('/=\s*crrg_drill_exec\s*\(/', $line3)) continue;      // 结果被**赋值/解构**接住了 ✓（2026-09-30 修：
                                                                            //   原来只排除 [$s,…]= 形态 → 把 $r = crrg_drill_exec(…) 误报 ✗）
        if (preg_match('/\[\s*\$s\s*,/', $line3)) continue;               // 已解构 [$s, $lines, $pc] = ...
        if (preg_match('/^\s*\*|^\s*\/\//', $line3)) continue;           // 注释
        $bad[] = sprintf('%s:%d 直接调了 crrg_drill_exec() 且没接住状态 → 请改用 crrg_game_exec()（否则旗标会丢）',
            basename($f3), $ln3 + 1);
    }
}

/* ── ⑤j 内部键外泄（2026-09-29）：玩家可见的报错里不得出现纯英文内部标识符 ── */
$knownWords = ['travel' => 1, 'report' => 1, 'help' => 1, 'exit' => 1, 'say' => 1, 'emote' => 1, 'h' => 1];
foreach ($files as $f4) {
    if (strpos($f4, 'drill') === false && strpos($f4, 'page-') === false) continue;
    $src4 = file_get_contents($f4);
    foreach (preg_split('/\n/', $src4) as $ln4 => $line4) {
        if (!preg_match('/[「『]([a-z][a-z_0-9]{2,})[」』]/', $line4, $mm4)) continue;
        if (isset($knownWords[$mm4[1]])) continue;
        if (preg_match('/^\s*\*|^\s*\/\/|^\s*\/\*/', $line4)) continue;   // 注释（含 /* 开头）
        $bad[] = sprintf('%s:%d 玩家可见文本里出现了内部键「%s」——会显示成"不认识的指令：「%s」"这类鬼话',
            basename($f4), $ln4 + 1, $mm4[1], $mm4[1]);
    }
}

/* ── ⑤k 面板标签"像命令"的——**真的跑一遍**（2026-09-29）
 *   聊天/投影通道是"把标签当命令发"，所以每个像命令的标签都必须能跑通；
 *   这里在沙箱状态里执行它，只在回「不认识的指令／未知操作」时报错。 ── */
if (function_exists('crrg_drill_exec') && function_exists('crrg_drill_panels')) {
    $verbs = ['细问', '调查', '问', '换法', '决断', '交涉', '查看'];
    $tested = 0;
    foreach (['base', 'town'] as $sc2) {
        crrg_drill_set_scene($sc2);
        foreach ((array) crrg_drill_rooms() as $room2 => $rv2) {
            $S3 = ['room' => $room2, 'items' => [], 'flags' => [], 'steps' => 0, 'gates' => 3, 'att' => [], 'times' => [], 'ui' => []];
            $pc0 = function_exists('crrg_coc_new_pc') ? crrg_coc_new_pc() : [];
            if (function_exists('crrg_coc_derive')) { $dS = crrg_coc_derive($pc0['attrs'] ?? []); $pc0['hp'] = $dS['hp_max'] ?? 10; $pc0['san'] = $dS['san_max'] ?? 50; $pc0['mp'] = $dS['mp_max'] ?? 10; }
            $pc0['name'] = '自检'; $pc0['occ'] = '文职'; $pc0['ready'] = true; $pc0['skills'] = ['侦查' => 60, '急救' => 60];
            $pc0['vitals'] = function_exists('crrg_vitals_default') ? crrg_vitals_default() : [];
            $pc0['money'] = 999; $pc0['credits'] = 99; $pc0['clearance'] = 3; $pc0['items'] = []; $pc0['npc_fav'] = [];
            $pc0['mission'] = 'm_mid_seal2'; $pc0['mission2'] = ['id' => '', 'step' => 0, 'start' => 0];
            $ctx3 = ['lv' => 3, 'card' => 'C', 'rank_name' => 'x', 'rank' => 'observer', 'chairman' => false, 's' => false];
            foreach ((array) crrg_drill_panels($S3, $ctx3, $pc0, 0) as $pp3) {
                foreach ((array) ($pp3['items'] ?? []) as $ii3) {
                    $lb3 = trim((string) ($ii3['label'] ?? ''));
                    if ($lb3 === '' || mb_strpos($lb3, '：') === false) continue;
                    $isVerb = false;
                    foreach ($verbs as $v3) if (mb_strpos($lb3, $v3) === 0) { $isVerb = true; break; }
                    if (!$isVerb) continue;
                    $tested++;
                    $pcT = $pc0; $ST = $S3;
                    $oldErr = error_reporting(0);            // 沙箱试跑：静音警告，别污染自检输出
                    $rT = crrg_drill_exec($ST, $ctx3, $lb3, $pcT, 0);
                    error_reporting($oldErr);
                    $txt = is_array($rT) ? implode(' ', (array) ($rT[1] ?? [])) : '';
                    if (mb_strpos($txt, '不认识的指令') !== false || mb_strpos($txt, '未知操作') !== false) {
                        $bad[] = sprintf('面板标签「%s」（@%s/%s）当命令跑不通——聊天/投影通道点了会失败', $lb3, $sc2, $room2);
                    }
                }
            }
        }
    }
    crrg_drill_set_scene('base');
}

/* ── ⑥ sprintf 裸 % ── */
foreach ($files as $f) {
    foreach (explode("\n", file_get_contents($f)) as $i => $ln) {
        if (!preg_match('/sprintf\(/', $ln)) continue;
        $probe = str_replace('%%', '', $ln);
        if (preg_match('/%[^0-9\.\+\-sdbfoxeEgG]/u', $probe)) $bad[] = sprintf('%s:%d sprintf 里有可疑的裸 %%：%s', basename($f), $i + 1, trim(mb_substr($ln, 0, 60)));
    }
}
echo "=== 命名自检报告 ===\n";
echo "错误 " . count($bad) . " 项：\n";
foreach ($bad as $x) echo "  ✗ " . $x . "\n";
echo "提醒 " . count($warn) . " 项：\n";
foreach (array_slice($warn, 0, 12) as $x) echo "  ! " . $x . "\n";
echo "（物品 " . (isset($items) ? count($items) : 0) . " 种 ｜ 房间键 " . count($roomCount) . " 个）\n";
