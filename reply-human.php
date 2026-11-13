<?php
// 真人回复检测 — DeepSeek API 动态回复
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

$users = [958,959,960,961,962,966,967,968,969,970];
$forums = [166,167,168,169,170];
$bot_ids = array_merge($users, [955]);

// DeepSeek API
define('DS_KEY', 'sk-5150a9631d9740ddac9a7e65b8731d21');
define('DS_URL', 'https://api.deepseek.com/chat/completions');

$persona_cache = [];
function get_persona($name) {
    global $persona_cache;
    if (isset($persona_cache[$name])) return $persona_cache[$name];
    foreach (glob('/root/重生抵御小组网站/员工人设/*.md') as $f) {
        if (strpos(file_get_contents($f), $name) !== false) {
            $persona_cache[$name] = file_get_contents($f);
            return $persona_cache[$name];
        }
    }
    return "小组普通成员，说话简洁。";
}

function ds_reply($topic_title, $topic_content, $reply_to, $author_name, $persona, $is_topic) {
    $system = "你是{$author_name}。以下是你的性格和语言风格：\n{$persona}\n\n请以你的口吻回复论坛帖子。回复要简短自然，1-3句话，不加前缀不加emoji。像真实论坛回帖一样。如果话题不是你的专业领域，可以坦率地说不了解。";
    $prompt = $is_topic
        ? "话题【{$topic_title}】\n内容：{$topic_content}\n\n回复这个话题。"
        : "话题【{$topic_title}】有人说：{$reply_to}\n\n回复。";
    $data = json_encode(["model"=>"deepseek-chat","messages"=>[["role"=>"system","content"=>$system],["role"=>"user","content"=>$prompt]],"max_tokens"=>120,"temperature"=>0.85]);
    $ch = curl_init(DS_URL);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>["Content-Type: application/json","Authorization: Bearer ".DS_KEY],CURLOPT_POSTFIELDS=>$data,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
    $r = curl_exec($ch); curl_close($ch);
    if(!$r) return false;
    $j = json_decode($r,true);
    return $j['choices'][0]['message']['content'] ?? false;
}

function bot_reply($topic_id, $forum_id, $human_post, $is_topic) {
    global $users, $bot_ids;
    $author = get_userdata($human_post->post_author);
    if (!$author || $author->ID == 964) return false;
    $content = strip_tags($human_post->post_content);
    $responder_id = $users[array_rand(array_diff($users, [$author->ID]))];
    $responder = get_userdata($responder_id);
    $topic = get_post($topic_id);
    $persona = get_persona($responder->display_name);
    $reply = ds_reply($topic->post_title, strip_tags($topic->post_content), $content, $responder->display_name, $persona, $is_topic);
    if (!$reply) return false;
    wp_set_current_user($responder_id);
    $rid = wp_insert_post(["post_content"=>$reply,"post_type"=>"reply","post_status"=>"publish","post_author"=>$responder_id,"post_parent"=>$topic_id]);
    $vc = count(get_posts(['post_type'=>'reply','post_parent'=>$topic_id,'posts_per_page'=>50])) + 1;
    foreach(["_bbp_forum_id"=>$forum_id,"_bbp_topic_id"=>$topic_id,"_bbp_reply_to"=>$topic_id,"_bbp_author_ip"=>"10.7.".($responder_id%256).".".rand(1,99),"_bbp_voice_count"=>$vc,"_bbp_engagement"=>$responder_id] as $k=>$v) update_post_meta($rid,$k,$v);
    echo "👤 {$author->display_name}: ".mb_substr($content,0,50)."\n🤖 {$responder->display_name}: {$reply}\n\n";
    return true;
}

// ─── 检查真人话题 ───
$topics = get_posts(['post_type'=>'topic','posts_per_page'=>20,'orderby'=>'ID','order'=>'DESC','date_query'=>['after'=>'48 hours ago']]);
foreach ($topics as $topic) {
    if (in_array((int)$topic->post_author, $bot_ids)) continue;
    $replies = get_posts(['post_type'=>'reply','post_parent'=>$topic->ID,'posts_per_page'=>50]);
    $has_bot = false;
    foreach ($replies as $r) { if (in_array((int)$r->post_author, $bot_ids)) $has_bot = true; }
    if (!$has_bot) {
        $fid = get_post_meta($topic->ID, '_bbp_forum_id', true) ?: $topic->post_parent;
        bot_reply($topic->ID, $fid, $topic, true);
    }
}

// ─── 检查真人回复 ───
foreach ($topics as $topic) {
    $replies = get_posts(['post_type'=>'reply','post_parent'=>$topic->ID,'posts_per_page'=>50,'orderby'=>'ID','order'=>'ASC']);
    foreach ($replies as $r) {
        if (in_array((int)$r->post_author, $bot_ids) || (int)$r->post_author == 964) continue;
        $has_bot_after = false;
        foreach ($replies as $r2) { if ($r2->ID > $r->ID && in_array((int)$r2->post_author, $bot_ids)) $has_bot_after = true; }
        if (!$has_bot_after) {
            $fid = get_post_meta($topic->ID, '_bbp_forum_id', true) ?: $topic->post_parent;
            bot_reply($topic->ID, $fid, $r, false);
        }
    }
}

foreach ($forums as $f) { bbp_update_forum_topic_count($f); bbp_update_forum_reply_count($f); }
