<?php
// ===== 收藏系统 =====
// Toggle favorite via AJAX
add_action('wp_ajax_crrg_toggle_fav', function () {
    if (!is_user_logged_in()) { wp_die('0'); }
    $user_id = get_current_user_id();
    $post_id = (int)($_POST['post_id'] ?? 0);
    $favs = get_user_meta($user_id, 'crrg_favorites', true) ?: [];
    if (in_array($post_id, $favs)) {
        $favs = array_diff($favs, [$post_id]);
        echo 'removed';
    } else {
        $favs[] = $post_id;
        echo 'added';
    }
    update_user_meta($user_id, 'crrg_favorites', array_values($favs));
    wp_die();
});
add_action('wp_ajax_nopriv_crrg_toggle_fav', function () { wp_die('0'); });

// Helper: check if user favorited
function crrg_is_favorited($user_id, $post_id) {
    $favs = get_user_meta($user_id, 'crrg_favorites', true) ?: [];
    return in_array($post_id, $favs);
}

