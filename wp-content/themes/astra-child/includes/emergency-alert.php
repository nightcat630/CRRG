<?php
// Emergency alert + Weekly top member

function crrg_get_alert() {
    return get_option('crrg_emergency_alert', ['active'=>false,'title'=>'','content'=>'','color'=>'#C41230']);
}
function crrg_set_alert($title, $content, $color) {
    update_option('crrg_emergency_alert', ['active'=>true,'title'=>$title,'content'=>$content,'color'=>$color]);
}
function crrg_close_alert() {
    update_option('crrg_emergency_alert', ['active'=>false,'title'=>'','content'=>'','color'=>'#C41230']);
}

function crrg_get_top_member() {
    global $wpdb;
    $week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
    $result = $wpdb->get_row("SELECT p.post_author as user_id, COUNT(*) as cnt FROM {$wpdb->posts} p WHERE p.post_date > '$week_ago' AND p.post_status='publish' AND p.post_type IN ('post','topic') GROUP BY p.post_author ORDER BY cnt DESC LIMIT 1");
    return $result ? get_userdata($result->user_id) : null;
}
