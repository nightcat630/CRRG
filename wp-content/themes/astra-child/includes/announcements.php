<?php
// Announcements system

function crrg_get_announcements() {
    $anns = get_option('crrg_announcements', []);
    usort($anns, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
    return $anns;
}
function crrg_add_announcement($title, $content, $time = '') {
    $anns = crrg_get_announcements();
    $anns[] = ['title' => $title, 'content' => $content, 'time' => $time ?: current_time('mysql')];
    // 按时间降序
    usort($anns, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
    update_option('crrg_announcements', array_slice($anns, 0, 50));
}
function crrg_update_announcement($index, $title, $content, $time = '') {
    $anns = crrg_get_announcements();
    if (isset($anns[$index])) {
        $anns[$index]['title'] = $title;
        $anns[$index]['content'] = $content;
        if ($time) $anns[$index]['time'] = $time;
        update_option('crrg_announcements', array_values($anns));
    }
}
function crrg_delete_announcement($index) {
    $anns = crrg_get_announcements();
    if (isset($anns[$index])) { unset($anns[$index]); update_option('crrg_announcements', array_values($anns)); }
}
