<?php
// ===== 站内私信系统 =====

function crrg_get_messages($user_id) {
    $all = get_option('crrg_messages', []);
    $msgs = [];
    foreach ($all as $mid => $msg) {
        if ((int)$msg['to'] === (int)$user_id) {
            $msgs[$mid] = $msg;
        }
    }
    // 最新在前
    uasort($msgs, function($a, $b) {
        return strcmp($b['time'], $a['time']);
    });
    return $msgs;
}

function crrg_get_sent_messages($user_id) {
    $all = get_option('crrg_messages', []);
    $msgs = [];
    foreach ($all as $mid => $msg) {
        if ((int)$msg['from'] === (int)$user_id) {
            $msgs[$mid] = $msg;
        }
    }
    uasort($msgs, function($a, $b) {
        return strcmp($b['time'], $a['time']);
    });
    return $msgs;
}

function crrg_send_message($from_uid, $to_uid, $title, $content) {
    $all = get_option('crrg_messages', []);
    $mid = time() . '_' . wp_rand(1000, 9999);
    $all[$mid] = [
        'from'    => (int)$from_uid,
        'to'      => (int)$to_uid,
        'title'   => wp_strip_all_tags($title),
        'content' => wp_kses_post($content),
        'time'    => current_time('mysql'),
        'read'    => false,
    ];
    // 只保留最近 500 条
    if (count($all) > 500) {
        $all = array_slice($all, -500, 500, true);
    }
    update_option('crrg_messages', $all);
    return $mid;
}

function crrg_mark_message_read($mid) {
    $all = get_option('crrg_messages', []);
    if (isset($all[$mid])) {
        $all[$mid]['read'] = true;
        update_option('crrg_messages', $all);
    }
}

function crrg_delete_message($mid, $user_id) {
    $all = get_option('crrg_messages', []);
    // 只有收件人或发件人能删
    if (isset($all[$mid]) && ((int)$all[$mid]['to'] === (int)$user_id || (int)$all[$mid]['from'] === (int)$user_id)) {
        unset($all[$mid]);
        update_option('crrg_messages', $all);
    }
}

function crrg_unread_count($user_id) {
    $msgs = crrg_get_messages($user_id);
    $count = 0;
    foreach ($msgs as $msg) {
        if (!$msg['read']) $count++;
    }
    return $count;
}
