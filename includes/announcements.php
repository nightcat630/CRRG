<?php
// Announcements system

function crrg_get_announcements() {
    return get_option('crrg_announcements', []);
}
function crrg_add_announcement($title, $content, $time = '') {
    $anns = crrg_get_announcements();
    array_unshift($anns, ['title' => $title, 'content' => $content, 'time' => $time ?: current_time('mysql')]);
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
