<?php
// 威胁等级统一定义（全站唯一来源）

define('CRRG_THREAT_LEVELS', [
    'ren'  => ['name' => '人', 'icon' => '👤', 'color' => '#16a34a', 'desc' => '对人类产生影响'],
    'gui'  => ['name' => '鬼', 'icon' => '👻', 'color' => '#8B5CF6', 'desc' => '对神秘生物/古神眷属产生影响'],
    'mo'   => ['name' => '魔', 'icon' => '👿', 'color' => '#C41230', 'desc' => '对次级旧日支配者/旧日支配者/古神产生影响'],
    'shen' => ['name' => '神', 'icon' => '👼', 'color' => '#F0A500', 'desc' => '对外神产生影响'],
]);

function crrg_threat_badge($threat_id, $size = 'small') {
    if (!$threat_id || !isset(CRRG_THREAT_LEVELS[$threat_id])) return '';
    $t = CRRG_THREAT_LEVELS[$threat_id];
    if ($size === 'large') {
        return '<span style="display:inline-block;margin-left:6px;padding:2px 10px;border-radius:2px;font-size:13px;font-weight:600;background:'.$t['color'].';color:#fff;">'.$t['icon'].' '.$t['name'].'</span>';
    }
    return '<span style="display:inline-block;margin-left:6px;padding:1px 8px;border-radius:2px;font-size:11px;font-weight:600;background:'.$t['color'].';color:#fff;">'.$t['icon'].' '.$t['name'].'</span>';
}

function crrg_threat_info($threat_id) {
    if (!$threat_id || !isset(CRRG_THREAT_LEVELS[$threat_id])) return '';
    $t = CRRG_THREAT_LEVELS[$threat_id];
    return '<div style="margin-bottom:16px;padding:8px 14px;background:'.$t['color'].'10;border-left:3px solid '.$t['color'].';border-radius:2px;font-size:13px;"><strong>'.$t['icon'].' '.$t['name'].'级威胁</strong> — '.$t['desc'].'</div>';
}
