<?php
// Announcements system

function crrg_get_announcements() {
    return get_option('crrg_announcements', []);
}
function crrg_add_announcement($title, $content) {
    $anns = crrg_get_announcements();
    array_unshift($anns, ['title' => $title, 'content' => $content, 'time' => current_time('mysql')]);
    update_option('crrg_announcements', array_slice($anns, 0, 50));
}
function crrg_update_announcement($index, $title, $content) {
    $anns = crrg_get_announcements();
    if (isset($anns[$index])) {
        $anns[$index]['title'] = $title;
        $anns[$index]['content'] = $content;
        update_option('crrg_announcements', array_values($anns));
    }
}
function crrg_delete_announcement($index) {
    $anns = crrg_get_announcements();
    if (isset($anns[$index])) { unset($anns[$index]); update_option('crrg_announcements', array_values($anns)); }
}
