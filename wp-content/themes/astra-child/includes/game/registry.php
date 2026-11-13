<?php
/**
 * CRRG 演习系统 · 注册中枢（Phase 0，2026-09-29）
 * ------------------------------------------------------------
 * 目的：把"内容"与"逻辑"分开，让**加东西只需动一个模块目录**，而不是改 8–11 个文件。
 *
 * 约定（以后所有新增都按这个来）：
 *   · 每个**玩法模块**（城镇/追击/野外/合约线）有自己的目录与**唯一入口** module.php，
 *     在入口里把所有属于它的东西注册进来：房间、NPC、商店、合约、面板区块、观察钩子。
 *   · 每个**注册类型**都有唯一入口函数（见下），逻辑代码只读注册表，不硬编码内容。
 *
 * 用法：
 *   crrg_game_register('room',  'tw_noodle', ['t'=>'面馆', …]);        // 注册/覆盖一条
 *   crrg_game_register_many('mission', ['m_x'=>[…] , 'm_y'=>[…]]);      // 批量
 *   crrg_game_all('mission');                                          // 取全部
 *   crrg_game_one('room', 'tw_noodle');                                // 取一条
 *   crrg_game_has('panel', 'seal_stage');                              // 有没有
 *
 * 已支持的类型（可继续加，加类型只需在 CRRG_GAME_TYPES 里登记 + 写文档）：
 *   room      房间（含出口）   npc        NPC（名字/别名/所在/话题）
 *   item       物品/图纸        shop       商店（房间/货品/价）
 *   mission    合约/任务        panel      面板区块（title/note/构建函数）
 *   look       观察/外观钩子    cmd        命令处理器（"加一条命令"只需注册）
 *   scene      场景（一组房间）  text       长文本（叙述/档案）
 */
if (!defined('ABSPATH')) exit;

/** 允许的注册类型（新增类型要在这里登记，便于自检与文档同步） */
function crrg_game_types() {
    /* 2026-09-29：新增 dlg2（对话 v2 的节点树）—— 注册类型必须先在这里登记，
       否则 crrg_game_register() 会**静默返回 false**（今天踩过：树注册不上，表现为"这里没有这个人"✗） */
    return ['room', 'npc', 'item', 'shop', 'mission', 'panel', 'look', 'cmd', 'scene', 'text', 'dlg2', 'window'];
}

/**
 * 注册表本体：**纯内存，每次请求由各模块重新注册**。
 *   （曾想落库，但那样会读到上一次请求的陈旧内容 ✗ —— 注册必须"每次请求现注册"，与 WP 的 init 钩子同节拍。）
 */
function &crrg_game_reg() {
    static $R = [];
    return $R;
}

/** 注册一条 */
function crrg_game_register($type, $id, $val, $meta = []) {
    if (!in_array($type, crrg_game_types(), true)) return false;
    $R = &crrg_game_reg();
    if (!isset($R[$type])) $R[$type] = [];
    $val = is_array($val) ? $val : ['v' => $val];
    if ($meta) $val['_meta'] = $meta;                       // 例：['module'=>'town','file'=>__FILE__]
    $R[$type][$id] = $val;
    return true;
}
/** 批量注册：['id'=>值, …] */
function crrg_game_register_many($type, array $rows, $meta = []) {
    $n = 0;
    foreach ($rows as $id => $v) if (crrg_game_register($type, $id, $v, $meta)) $n++;
    return $n;
}
/** 取一条 / 取全部 / 有没有 */
function crrg_game_one($type, $id, $default = null) {
    $R = &crrg_game_reg();
    return $R[$type][$id] ?? $default;
}
function crrg_game_all($type) {
    $R = &crrg_game_reg();
    return $R[$type] ?? [];
}
function crrg_game_has($type, $id) { return crrg_game_one($type, $id) !== null; }

/**
 * 模块入口：扫描 includes/game/modules/<模块>/module.php 并加载。
 * 加一个玩法模块＝新建一个目录 + 一个 module.php，**不改其它文件**。
 */
function crrg_game_load_modules() {
    $dir = __DIR__ . '/modules/';
    if (!is_dir($dir)) return [];
    $loaded = [];
    foreach (glob($dir . '*/module.php') as $entry) {
        $loaded[] = basename(dirname($entry));
        require_once $entry;
    }
    return $loaded;
}

/**
 * 面板区块注册（现状里"面板"是手写在 economy/exec 里的 ✗ —— 这是扩展性最大的缺口）。
 * 约定：构建函数签名 fn(&$s, &$ctx, &$pc, $uid) → 返回 ['title'=>…, 'note'=>…, 'items'=>[…]] 或 null
 */
function crrg_game_panel_register($id, $builder, $opts = []) {
    return crrg_game_register('panel', $id, [
        'builder' => $builder,
        'order'   => (int) ($opts['order'] ?? 50),
        'when'    => $opts['when'] ?? null,                  // 可选：fn($s,$ctx,$pc) 决定显不显示
        'note'    => (string) ($opts['note'] ?? ''),
    ], $opts['meta'] ?? []);
}
/** 收集所有面板区块（按 order 排序，逐个调用构建函数，空的不进列表） */
function crrg_game_panel_collect(&$s, &$ctx, &$pc, $uid) {
    $out = [];
    foreach (crrg_game_all('panel') as $id => $p) {
        $order = (int) ($p['order'] ?? 50);
        $fn = $p['builder'] ?? '';
        if ($fn === '' || !is_callable($fn)) continue;
        if (!empty($p['when']) && is_callable($p['when']) && !$p['when']($s, $ctx, $pc)) continue;
        $blk = $fn($s, $ctx, $pc, $uid);
        if (is_array($blk) && !empty($blk['items'])) { $blk['id'] = $id; $out[] = ['order' => $order, 'blk' => $blk]; }
    }
    usort($out, fn($a, $b) => $a['order'] <=> $b['order']);
    return array_map(fn($x) => $x['blk'], $out);
}

/**
 * 命令处理器注册：把"一条命令一段逻辑"登记进来，
 * 加一条命令＝crrg_game_cmd('eat', fn(&$s,&$pc,$ctx,$uid,$arg)=>[…])，不必再改 exec 的巨型 switch。
 */
function crrg_game_cmd($name, $fn, $opts = []) {
    return crrg_game_register('cmd', $name, [
        'fn'    => $fn,
        'alias' => (array) ($opts['alias'] ?? []),
        'help'  => (string) ($opts['help'] ?? ''),
    ]);
}
/** 按名字/别名找命令处理器 */
function crrg_game_cmd_lookup($name) {
    $name = mb_strtolower(trim((string) $name));
    if ($name === '') return null;
    foreach (crrg_game_all('cmd') as $id => $c) {
        if ($id === $name || in_array($name, (array) ($c['alias'] ?? []), true)) return [$id, $c['fn'] ?? null];
    }
    return null;
}

/* 模块入口统一加载（优先级 20：早于各内容注册，晚于中枢自身的定义） */
add_action('init', function () { crrg_game_load_modules(); }, 20);
