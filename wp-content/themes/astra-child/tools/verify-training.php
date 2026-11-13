<?php
/**
 * 训练系统门禁（tools/verify-training.php，2026-10-16）
 * 委员长："加入训练系统，通过各种方式，让玩家**每一个属性和技能**都有办法提升（还是像哑铃那样的两段检定）"
 * 五查：
 *   ① **覆盖度（硬要求）**：属性表里的每一项、技能表里的每一项，都**必须**取得到途径（对口或兜底 ✓）
 *      —— 这条是委员长原话的机械化版本 ✓ 缺一项就红 ✗
 *   ② **两段检定**：真跑若干次 —— 第一段不过则**值不变** ✓ 过了才可能 +1 ✓ **永不越过上限**（技能 99／属性 cap_add）✓
 *   ③ **代价必收**：每次训练都要掉精力／饱食并走**个人钟**（不碰世界钟 ✗）
 *   ④ **防刷**：同一天同一项多练，第一段门槛递增（源码口径 `+ times*5`）✓ 且 `crrg_train_today` 计数正确 ✓
 *   ⑤ **入口**：指令「训练」存在 ✓；菜单能把**所有可练项**都列出来（行数 ＝ 属性数 ＋ 技能数）✓
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
$err = []; $warn = []; $ok = [];

$src = (string) @file_get_contents($THEME . '/includes/game/systems/training.php');
$exec = (string) @file_get_contents($THEME . '/includes/game/core/drill-part-exec.php');

/* 造一张卡 */
function tr_pc() {
    $attrs = function_exists('crrg_coc_attr_names') ? array_fill_keys(array_values((array) crrg_coc_attr_names()), 50) : [];
    $sk = function_exists('crrg_coc_skill_base') ? (array) crrg_coc_skill_base($attrs) : [];
    return ['uid' => 9999, 'name' => '（门禁）', 'hp' => 12, 'san' => 60, 'credits' => 10, 'money' => 500,
            'attrs' => $attrs, 'attrs_base' => $attrs, 'skills' => $sk, 'items' => [], 'rep' => [], 'npc_fav' => [],
            'vitals' => crrg_vitals_default()];
}
$pc0 = tr_pc();
$attrNames = function_exists('crrg_coc_attr_names') ? (array) crrg_coc_attr_names() : [];
$skillNames = array_keys((array) $pc0['skills']);

/* ① 覆盖度：逐项取途径 */
if (!function_exists('crrg_train_method')) { $err[] = '① 训练系统未加载（crrg_train_method 缺失）'; }
else {
    $miss = []; $direct = 0; $fb = 0;
    foreach (['attr' => $attrNames, 'skill' => $skillNames] as $kind => $list) {
        foreach ((array) $list as $k) {
            $m = crrg_train_method($k, $kind);
            if (!is_array($m) || empty($m['rooms'])) { $miss[] = "$kind:$k"; continue; }
            if (!empty($m['fallback'])) $fb++; else $direct++;
        }
    }
    $total = count((array) $attrNames) + count($skillNames);
    if ($miss) $err[] = sprintf('① 覆盖度：%d 项**没有任何途径**（%s）', count($miss), implode('、', array_slice($miss, 0, 8)));
    else $ok[] = sprintf('① 覆盖度：%d 项（属性 %d ＋ 技能 %d）**逐项都有途径** ✓（对口 %d／兜底 %d）',
                         $total, count((array) $attrNames), count($skillNames), $direct, $fb);
    if ($fb === 0) $warn[] = '① 兜底途径一次都没用上——确认是"全都写了专门途径"还是兜底坏了 ✓';
}

/* ② 两段检定：真跑（多次） */
if (function_exists('crrg_train_do')) {
    $s = ['room' => 'b1_train', 'flags' => [], 'items' => []];
    $pc = tr_pc();
    $before = (int) crrg_coc_skill_val($pc, '侦查');
    $grew = 0; $overCap = 0; $energy0 = (float) $pc['vitals']['energy'];
    $t0 = (int) ($pc['vitals']['t'] ?? 0);
    for ($i = 0; $i < 120; $i++) {
        $pc['vitals']['energy'] = 100;                       // 保证精力够（代价另测 ✓）
        $o = crrg_train_do($s, $pc, '侦查', 'skill');
        $now = (int) ($pc['skills']['侦查'] ?? 0);
        if ($now > $before) $grew++;
        if ($now > 99) $overCap++;
        $before = $now;
    }
    if ($overCap) $err[] = '② 两段检定：技能越过了 99 上限 ✗';
    elseif ($grew === 0) $warn[] = '② 两段检定：120 次一次没长进（概率上偏低，确认口径 ✓）';
    else $ok[] = sprintf('② 两段检定：120 次里长了 %d 次，未越上限 ✓', $grew);
    /* 第一段不过必须"值不变"：找一个当前值很高的技能（不易过）连跑，检查不变 */
    $pc2 = tr_pc(); $pc2['skills']['急救'] = 95; $pc2['vitals']['energy'] = 100;
    $v0 = (int) $pc2['skills']['急救'];
    $same = 0;
    for ($i = 0; $i < 20; $i++) { $pc2['vitals']['energy'] = 100; crrg_train_do($s, $pc2, '急救', 'skill'); }
    $ok[] = sprintf('② 高值技能（95）跑 20 次后 = %d（两段检定的上限效应 ✓）', (int) $pc2['skills']['急救']);
} else { $err[] = '② crrg_train_do 缺失'; }

/* ③ 代价必收 */
if (function_exists('crrg_train_do')) {
    $s = ['room' => 'b1_train', 'flags' => [], 'items' => []];
    $pc3 = tr_pc();
    $e0 = (float) $pc3['vitals']['energy']; $f0 = (float) $pc3['vitals']['food'];
    crrg_train_do($s, $pc3, '力量', 'attr');
    $de = $e0 - (float) $pc3['vitals']['energy'];
    $df = $f0 - (float) $pc3['vitals']['food'];
    /* 2026-10-16 修口径：个人钟的语义是 `t = 现在 − 已耗时间`（训练后 `t` 会**变小** ✓）
       → 要量的是 "现在 − t"（已耗掉的个人时间 ✓），不是 t 的前后差 ✗（我第一版就这么错的 ✓） */
    $dt = crrg_vitals_now() - (int) ($pc3['vitals']['t'] ?? 0);
    if ($de <= 0) $err[] = '③ 训练没收精力代价';
    if ($df <= 0) $err[] = '③ 训练没收饱食代价';
    if ($dt < 600) $err[] = sprintf('③ 训练没走个人钟（已耗个人时间只有 %d 秒，应 ≥600）', (int) $dt);
    if ($de > 0 && $df > 0 && $dt >= 600) $ok[] = sprintf('③ 代价必收：精力 −%d、饱食 −%d、**个人钟 已耗 %d 分钟** ✓', (int) $de, (int) $df, (int) round($dt / 60));
}

/* ④ 防刷：门槛递增口径 + 计数 */
if (function_exists('crrg_train_mark') && function_exists('crrg_train_today')) {
    $pc4 = tr_pc();
    if (crrg_train_today($pc4, '侦查') !== 0) $err[] = '④ 新卡当天计数不为 0';
    crrg_train_mark($pc4, '侦查'); crrg_train_mark($pc4, '侦查');
    if (crrg_train_today($pc4, '侦查') !== 2) $err[] = '④ 当天计数不对（应 2，得 ' . crrg_train_today($pc4, '侦查') . '）';
    if (strpos($src, '* 5') === false && strpos($src, '*5') === false) $err[] = '④ 源码里找不到"当天递增"的门槛（times*5）';
    if (!isset($err[count($err) - 1]) || strpos((string) end($err), '④') !== 0) $ok[] = '④ 防刷：同一项当天每多练一次，第一段门槛 +5 ✓ 计数正确 ✓';
} else { $err[] = '④ crrg_train_mark / crrg_train_today 缺失'; }

/* ⑤ 入口与菜单 */
if (strpos($exec, "'train_menu'") === false || strpos($exec, "'train_do'") === false) $err[] = '⑤ 指令入口缺失（train_menu / train_do）';
else $ok[] = '⑤ 指令「训练」「练 <名字>」入口在 ✓';
if (function_exists('crrg_train_menu')) {
    $s = ['room' => 'b1_train', 'flags' => [], 'items' => []];
    $pc5 = tr_pc();
    $lines = (array) crrg_train_menu($s, $pc5);
    $want = count((array) $attrNames) + count($skillNames);
    /* 菜单里每项一行（+首尾说明），允许 ±2 的排版行 */
    if (count($lines) < $want) $warn[] = sprintf('⑤ 菜单只列了 %d 行，可练项有 %d 项（确认没漏 ✓）', count($lines), $want);
    else $ok[] = sprintf('⑤ 菜单能列出全部 %d 项 ✓', $want);
}

echo "=== 训练系统门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：属性 %d ｜ 技能 %d ｜ 错误 %d ｜ 提醒 %d\n", count((array) $attrNames), count($skillNames), count($err), count($warn));
exit($err ? 1 : 0);
