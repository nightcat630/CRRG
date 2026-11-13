<?php
/**
 * 物品分类体系（2026-09-28 建立）
 *   原则：**先把分类全部立起来**，装不满不算问题；未来加物品只需在物品定义里写 'cat' => '<小类 id>'。
 *   层级：大类（12）→ 小类（60+）。大类与物品 id 前缀一一对应，所以"分类"永远有兜底。
 *   用法：
 *     crrg_item_taxonomy()              全部分类（含父子、说明、排序）
 *     crrg_item_classify($id)           某物品落在哪个小类（显式 cat > 前缀 > 关键词 > 兜底）
 *     crrg_item_cat_tree()              大类 → 小类 → 物品，带数量（空类也会出现）
 */
if (!defined('ABSPATH')) exit;

/** 大类（与 id 前缀对应） */
function crrg_item_major() {
    /* Phase 1：数据已搬到 includes/game/content/taxonomy.php */
    return function_exists('crrg_game_tax_major_data') ? crrg_game_tax_major_data() : [];
}
function crrg_item_minor() {
    /* Phase 1：数据已搬到 includes/game/content/taxonomy.php */
    return function_exists('crrg_game_tax_minor_data') ? crrg_game_tax_minor_data() : [];
}
function crrg_item_taxonomy() {
    $out = [];
    foreach (crrg_item_major() as $id => $m) $out[$id] = ['n' => $m['n'], 'p' => '', 'order' => $m['order'], 'd' => $m['d'], 'major' => $id];
    foreach (crrg_item_minor() as $id => $m) $out[$id] = ['n' => $m['n'], 'p' => $m['p'], 'order' => $m['order'], 'd' => $m['d'], 'major' => $m['p']];
    uasort($out, fn($a, $b) => $a['order'] <=> $b['order']);
    return $out;
}
/** 关键词兜底（物品没写 cat 时按名字猜） */
function crrg_item_cat_by_keywords($id, $name = '') {
    $hay = $id . ' ' . $name;
    $rules = [
        'tool_light' => '/手电|灯|荧光/u', 'tool_power' => '/电池|充电|发电/u', 'tool_rope' => '/绳|梯|挂钩/u',
        'tool_break' => '/撬|铲|钎|锤|镐/u', 'tool_comm' => '/对讲|通讯|电台|信号/u', 'tool_survey' => '/检测|定位|望远镜|卷尺|测绘/u',
        'tool_cont'  => '/封|匣|收纳|袋/u', 'tool_camp' => '/帐篷|睡袋|炉|水壶/u', 'tool_repair' => '/胶带|铁丝|铁钉|扳手|机油/u',
        'tool_life'  => '/缸|纸|皂|针线|瓶|毛巾|刷子/u', 'tool_kitchen' => '/锅|暖水瓶|饭盒|炊/u', 'tool_clean' => '/消毒液|洗涤|拖把/u',
        'med_firstaid' => '/绷带|纱布|急救/u', 'med_drug' => '/药|抗生|止痛/u', 'med_psych' => '/稳定剂|安眠|镇静/u', 'med_inst' => '/注射|听诊|手术/u',
        'food_staple' => '/干粮|饼干|能量棒/u', 'food_grain' => '/米|面|油|豆/u', 'food_cond' => '/酱油|盐|醋|糖/u',
        'food_fresh' => '/蛋|奶|菜|肉/u', 'food_ready' => '/饭|面馆|盒饭|热餐/u', 'food_snack' => '/糖|坚果|蜜饯/u',
        'drink_water' => '/水|矿泉/u', 'drink_hot' => '/茶|咖啡|热饮/u', 'drink_cold' => '/汽水|酸奶|冰/u', 'drink_alcohol' => '/酒/u',
        'smoke_cig' => '/烟|卷烟|雪茄/u', 'smoke_fire' => '/火柴|打火机|火石/u', 'smoke_other' => '/槟榔|口香糖/u',
        'weap_fire' => '/手枪|步枪|霰弹/u', 'weap_melee' => '/匕首|棍|军刺|刀/u', 'weap_throw' => '/燃烧瓶|飞刀/u', 'weap_heavy' => '/机枪|榴弹/u',
        'ammo_pistol' => '/手枪弹/u', 'ammo_rifle' => '/步枪弹/u', 'ammo_shot' => '/霰弹/u', 'ammo_exp' => '/炸药|雷管/u', 'ammo_special' => '/信号弹|催泪/u',
        'gear_body' => '/护具|背心/u', 'gear_head' => '/头盔|安全帽/u', 'gear_hand' => '/手套/u', 'gear_foot' => '/靴|护腿/u',
        'gear_face' => '/口罩|面罩|滤/u', 'gear_carry' => '/携行|背包|腰包/u', 'gear_suit' => '/防护服|雨衣|隔离/u',
        'key_door' => '/门禁|钥匙|密码/u', 'key_sample' => '/样本/u', 'key_sigil' => '/符|封印|镇物/u', 'key_task' => '/信物|委托|证物/u', 'key_vehicle' => '/车钥匙|启动卡/u',
        'doc_note' => '/笔|记录板|便签|笔记/u', 'doc_photo' => '/照片|胶卷|存储卡/u', 'doc_file' => '/报告|卷宗|报纸|档案/u',
        'doc_map' => '/地图|图纸|线路/u', 'doc_device' => '/相机|录音/u',
        'veh_parts' => '/轮胎|皮带|灯泡|保险丝/u', 'veh_fuel' => '/汽油|柴油|机油桶/u', 'veh_tool' => '/千斤顶|拖车绳|搭电/u',
        'veh_docs' => '/行驶证|通行证|派车/u', 'veh_body' => '/自行车|摩托|越野车/u',
        'misc_trade' => '/废品|回收/u',
    ];
    foreach ($rules as $cat => $re) if (preg_match($re, $hay)) return $cat;
    return null;
}
/** 归类：显式 cat > id 前缀 > 关键词 > 兜底 */
function crrg_item_classify($id, $item = null) {
    if (!function_exists('crrg_drill_item_id')) return 'misc_unknown';
    $id = crrg_drill_item_id($id);
    if (!is_array($item)) $item = (function_exists('crrg_drill_items') ? (crrg_drill_items()[$id] ?? []) : []);
    /* 顺序很重要：物品自己写的 cat 最优先，其次关键词，最后按大类兜底 */
    if (!empty($item['cat']) && isset(crrg_item_taxonomy()[$item['cat']])) return $item['cat'];
    $pfx = strstr($id, '_', true) ?: '';
    $name = $item['n'] ?? '';
    if ($cat = crrg_item_cat_by_keywords($id, $name)) return $cat;
    foreach (crrg_item_minor() as $cid => $m) if ($m['p'] === $pfx) return $cid;   // 该大类第一个小类兜底
    return 'misc_unknown';
}
/** 大类 → 小类 → 物品（含数量；空类也出现） */
function crrg_item_cat_tree() {
    $items = function_exists('crrg_drill_items') ? crrg_drill_items() : [];
    $tree = [];
    foreach (crrg_item_major() as $mid => $m) $tree[$mid] = ['n' => $m['n'], 'd' => $m['d'], 'subs' => [], 'n_items' => 0];
    foreach (crrg_item_minor() as $sid => $s) {
        $ids = [];
        foreach ($items as $iid => $it) if (crrg_item_classify($iid, $it) === $sid) $ids[] = $iid;
        $tree[$s['p']]['subs'][$sid] = ['n' => $s['n'], 'd' => $s['d'], 'items' => $ids];
        $tree[$s['p']]['n_items'] += count($ids);
    }
    /* 不在任何小类里的（理论不会发生）落到杂项待鉴定 */
    return $tree;
}
