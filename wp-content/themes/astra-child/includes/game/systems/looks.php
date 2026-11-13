<?php
/**
 * 观察钩子（自 drill-hub.php 搬出）
 */
if (!defined('ABSPATH')) exit;

/** 观察钩子注册表：各模组把自己的观察函数塞进来，避免函数名冲突 */
function crrg_drill_look_hooks() {
    $r = &crrg_drill_reg();
    if (!isset($r['look_hooks'])) $r['look_hooks'] = [];
    return $r['look_hooks'];
}function crrg_drill_add_look_hook($fn) { $r = &crrg_drill_reg(); $r['look_hooks'][] = $fn; }
/** 依次跑所有钩子；命中即返回 true 并写回 flags *//** 依次跑所有钩子；命中即返回 true 并写回 flags */
function crrg_drill_run_look_hooks($s, $pc, $arg, &$out, &$flags) {
    foreach (crrg_drill_look_hooks() as $fn) {
        if (!is_callable($fn)) continue;
        $extra = [];
        if ($fn($s, $pc, $arg, $extra, $flags)) { $out = $extra; return true; }
    }
    return false;
}
/** 任务步骤支持多路径：room / cmd / flag / any */
/** 玩家名下的某物件数：背包 + 宿舍装修登记（装修类商品买完直接进宿舍） */