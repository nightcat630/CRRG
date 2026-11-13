<?php
// 论坛提醒 — 检测48小时内未回复的真人帖子/回复
// v2.1 (2026-09-08) 修复：
//   1. 老话题上的新回复漏检（原版只查48h内创建的话题）
//   2. 去重状态导致提醒被"无新回复"覆盖丢失（原版提醒一次后静默）
// 新逻辑：只要48h窗口内有未回复的真人内容就持续输出，机器人回复后自然消失
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

$bot_ids = [958, 959, 960, 961, 962, 966, 967, 968, 969, 970, 955, 973]; // 973 = 河洛（机器人账号，勿漏）
$cutoff  = time() - 48 * 3600; // 48小时前的时间戳

$unreplied = []; // 需要回复的内容列表

// 最近 150 个话题（放宽范围，覆盖"老话题上出现的新真人回复"）
$topics = get_posts(['post_type' => 'topic', 'posts_per_page' => 150, 'orderby' => 'ID', 'order' => 'DESC']);

foreach ($topics as $t) {
    if ($t->post_status !== 'publish') continue;

    $replies = get_posts(['post_type' => 'reply', 'post_parent' => $t->ID, 'posts_per_page' => 100, 'orderby' => 'ID', 'order' => 'ASC']);
    $topic_time = strtotime($t->post_date);

    // 整楼是否有机器人回复
    $has_bot = false;
    foreach ($replies as $r) {
        if (in_array((int)$r->post_author, $bot_ids)) { $has_bot = true; break; }
    }

    // —— 真人话题：48h内创建 + 整楼无bot回复 + 作者非机器人/非叶知夏 ——
    if ($topic_time >= $cutoff && !in_array((int)$t->post_author, $bot_ids) && (int)$t->post_author !== 964 && !$has_bot) {
        $a = get_userdata($t->post_author);
        $f = get_post($t->post_parent);
        if ($a && $f) {
            $unreplied[] = "🆕 {$a->display_name} 在【{$f->post_title}】发帖: {$t->post_title}";
        }
    }

    // —— 真人回复：48h内发出 + 下一条不是bot + 作者非机器人/非叶知夏 ——
    $reply_count = count($replies);
    for ($i = 0; $i < $reply_count; $i++) {
        $r = $replies[$i];
        if (in_array((int)$r->post_author, $bot_ids) || (int)$r->post_author === 964) continue;
        if (strtotime($r->post_date) < $cutoff) continue; // 只看48h内的真人回复
        $next_is_bot = false;
        if ($i + 1 < $reply_count) {
            $next_is_bot = in_array((int)$replies[$i + 1]->post_author, $bot_ids);
        }
        if (!$next_is_bot) {
            $author = get_userdata($r->post_author);
            if ($author) {
                $unreplied[] = "💬 {$author->display_name} 在【{$t->post_title}】回复: " . mb_substr(strip_tags($r->post_content), 0, 40);
            }
        }
    }
}

// 输出：有未回复内容就持续显示，机器人回复后该条自然消失
if ($unreplied) {
    echo "=== 需要回复 (" . count($unreplied) . "条) ===\n";
    foreach ($unreplied as $msg) echo $msg . "\n";
} else {
    echo "无新回复\n";
}
