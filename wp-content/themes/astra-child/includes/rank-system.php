<?php
// ===== 等级系统 =====
define('CRRG_RANKS', [
    ['id' => 'observer',     'name' => '观察员',     'xp' => 0,    'icon' => '🔍'],
    ['id' => 'operative',    'name' => '行动员',     'xp' => 100,  'icon' => '⚔️'],
    ['id' => 'tl',           'name' => '行动组组长', 'xp' => 300,  'icon' => '👑'],
    ['id' => 'chief',        'name' => '行动处处长', 'xp' => 800,  'icon' => '🏛️'],
    ['id' => 'advisor',      'name' => '首席顾问',   'xp' => 2000, 'icon' => '📜', 'apply' => true],
    ['id' => 'deputy',       'name' => '副总指挥',   'xp' => 5000, 'icon' => '⭐', 'apply' => true],
    ['id' => 'chairman',     'name' => '委员长',     'xp' => 99999,'icon' => '🛡️'],
]);

// 获取用户资历
function crrg_get_xp($user_id) {
    return (int) get_user_meta($user_id, 'crrg_xp', true);
}

// 增加资历
function crrg_add_xp($user_id, $amount) {
    $xp = crrg_get_xp($user_id) + $amount;
    update_user_meta($user_id, 'crrg_xp', $xp);
    // Auto-promote
    crrg_auto_promote($user_id, $xp);
}

// Get current rank
function crrg_get_rank($user_id) {
    $rank = get_user_meta($user_id, 'crrg_rank', true);
    if (!$rank) {
        $xp = crrg_get_xp($user_id);
        foreach (array_reverse(CRRG_RANKS) as $r) {
            if ($xp >= $r['xp'] && $r['id'] !== 'chairman') {
                $rank = $r['id'];
                update_user_meta($user_id, 'crrg_rank', $rank);
                break;
            }
        }
        if (!$rank) $rank = 'observer';
    }
    // Chairman override
    if (get_user_meta($user_id, 'crrg_is_chairman', true)) return 'chairman';
    return $rank;
}

// Get rank data
function crrg_get_rank_data($rank_id) {
    foreach (CRRG_RANKS as $r) {
        if ($r['id'] === $rank_id) return $r;
    }
    return CRRG_RANKS[0];
}

// Auto promote (up to chief)
function crrg_auto_promote($user_id, $xp) {
    if (get_user_meta($user_id, 'crrg_is_chairman', true)) return;
    $current = crrg_get_rank($user_id);
    foreach (CRRG_RANKS as $r) {
        if ($r['id'] === 'chairman') continue;
        if (!empty($r['apply'])) continue; // Need application
        if ($xp >= $r['xp'] && array_search($r['id'], array_column(CRRG_RANKS, 'id')) > array_search($current, array_column(CRRG_RANKS, 'id'))) {
            update_user_meta($user_id, 'crrg_rank', $r['id']);
        }
    }
}

// 每日登录资历
add_action('init', function () {
    if (!is_user_logged_in()) return;
    $user_id = get_current_user_id();
    $today = date('Y-m-d');
    $last = get_user_meta($user_id, 'crrg_last_login_xp', true);
    if ($last !== $today) {
        crrg_add_xp($user_id, 2);
        update_user_meta($user_id, 'crrg_last_login_xp', $today);
        update_user_meta($user_id, 'crrg_xp_toast', '1');
    }
});

// Set 朱贞吉 as chairman
$chairman_user = get_user_by('login', '1220713948');
if ($chairman_user) {
    update_user_meta($chairman_user->ID, 'crrg_rank', 'chairman');
    update_user_meta($chairman_user->ID, 'crrg_xp', 99999);
    update_user_meta($chairman_user->ID, 'crrg_is_chairman', '1');
}

// 获取等级数字（越小越高）
function crrg_get_rank_level($rank_id) {
    $ids = array_column(CRRG_RANKS, 'id');
    $pos = array_search($rank_id, $ids);
    return $pos !== false ? $pos : 0;
}

// 获取某等级及以下的所有等级列表
function crrg_get_accessible_ranks($user_rank_id) {
    $level = crrg_get_rank_level($user_rank_id);
    $all = CRRG_RANKS;
    $accessible = [];
    foreach ($all as $r) {
        if (crrg_get_rank_level($r['id']) <= $level) {
            $accessible[] = $r;
        }
    }
    return $accessible;
}

