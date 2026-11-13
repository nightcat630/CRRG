---
name: crrg-forum-posting
description: CRRG网站论坛发帖/回复技能。使用bbPress函数正确创建话题和回复，确保元数据完整。v1.2
version: 1.2.0
tags: [bbpress, forum, wordpress, crrg]
---

# CRRG 论坛发帖技能 v1.2

## ⚠️ 两大致命陷阱

### 陷阱一：wp_update_post 只传部分字段会清空未传入的字段
```php
// ❌ 错误：标题和内容会被清空！
wp_update_post(["ID" => $tid, "post_parent" => $fid]);

// ✅ 正确：必须同时传入完整字段
wp_update_post(["ID" => $tid, "post_title" => $title, "post_content" => $content, "post_parent" => $fid]);
```

### 陷阱二：PHP CLI 模式下 wp_set_current_user 不可靠
`wp_set_current_user()` 在命令行脚本中可能不生效，导致 `wp_insert_post` 的 `post_author` 被随机用户覆盖。**必须用 `$wpdb->update` 直接写数据库修复。**

```php
// ✅ 发帖后强制修正作者
global $wpdb;
$wpdb->update($wpdb->posts, ["post_author" => $correct_author_id], ["ID" => $post_id]);
```

## 推荐封装函数

```php
function post_topic($author_id, $forum_id, $title, $content) {
    global $wpdb;
    wp_set_current_user($author_id);
    $tid = bbp_insert_topic([], [], [
        "post_title" => $title, "post_content" => $content, "post_status" => "publish",
    ], ["forum_id" => $forum_id]);
    
    // 一次性设置所有字段
    wp_update_post([
        "ID" => $tid, "post_parent" => $forum_id,
        "post_title" => $title, "post_content" => $content,
        "post_name" => sanitize_title($title) ?: "topic-" . $tid,
    ]);
    
    // 强制修正作者（绕过 wp_set_current_user 不可靠问题）
    $wpdb->update($wpdb->posts, ["post_author" => $author_id], ["ID" => $tid]);
    
    $meta = [
        "_bbp_forum_id" => $forum_id, "_bbp_topic_id" => $tid,
        "_bbp_author_ip" => "10.7." . ($author_id % 256) . "." . rand(1, 99),
        "_bbp_voice_count" => 1, "_bbp_last_active_id" => $tid,
        "_bbp_last_active_time" => current_time("mysql"), "_bbp_engagement" => $author_id,
    ];
    foreach ($meta as $k => $v) update_post_meta($tid, $k, $v);
    return $tid;
}

function post_reply($author_id, $topic_id, $forum_id, $content, $voice_num) {
    global $wpdb;
    wp_set_current_user($author_id);
    $rid = wp_insert_post([
        "post_content" => $content, "post_type" => "reply",
        "post_status" => "publish", "post_author" => $author_id, "post_parent" => $topic_id,
    ]);
    
    // 强制修正作者
    $wpdb->update($wpdb->posts, ["post_author" => $author_id], ["ID" => $rid]);
    
    $meta = [
        "_bbp_forum_id" => $forum_id, "_bbp_topic_id" => $topic_id,
        "_bbp_reply_to" => $topic_id, "_bbp_author_ip" => "10.7." . ($author_id % 256) . "." . rand(1, 99),
        "_bbp_voice_count" => $voice_num, "_bbp_engagement" => $author_id,
    ];
    foreach ($meta as $k => $v) update_post_meta($rid, $k, $v);
    return $rid;
}

// 发完后统一更新计数
foreach ([166, 167, 168, 169, 170] as $fid) {
    bbp_update_forum_topic_count($fid);
    bbp_update_forum_reply_count($fid);
}
```

## 论坛ID速查

| ID | 板块名称 |
|----|---------|
| 166 | 行动指挥部 |
| 167 | 情报分析处 |
| 168 | 镇物研究室 |
| 169 | 档案文献馆 |
| 170 | 人员休息室 |

## 账号ID速查

| ID | 用户名 | 显示名 | 等级 |
|----|--------|--------|------|
| 958 | Zhao Ming Yuan | 赵明远 | 行动处处长 |
| 959 | Chen Si Yan | 陈思言 | 行动组组长 |
| 960 | Lin Zheng Ze | 林正则 | 行动组组长 |
| 961 | Wang Shou Yi | 王守一 | 行动员 |
| 962 | Zhou Zhi Xing | 周知行 | 行动员 |
| 955 | keda | 柯达 | 行动员 |
| 966 | Sun Yi Ran | 孙亦然 | 观察员 |
| 967 | Jiang Xiao | 江晓 | 观察员 |
| 968 | He Ruo Feng | 何若风 | 观察员 |
| 969 | Shen Wei | 沈微 | 观察员 |
| 970 | Bai Lu | 白露 | 观察员 |

## 常见错误

| 症状 | 原因 | 解决 |
|------|------|------|
| 话题标题内容空白 | `wp_update_post` 只传 parent 没传 title/content | 一次性传入全部字段 |
| 帖子挂到邓铉名下 | CLI 下 `wp_set_current_user` 不可靠 | `$wpdb->update` 直接修 post_author |
| 论坛显示空 | 缺少 `_bbp_topic_id` 或 `post_parent` | 补全元数据 |
| 中文标题 slug 为空 | `sanitize_title` 对纯中文返回空 | 用 `"topic-{$tid}"` 兜底 |
| 回复不显示 | 缺少 `_bbp_topic_id` 或 `_bbp_forum_id` | 补全这两个 meta |

## 禁忌
- 不要动叶知夏账号(ID 964)的任何帖子
- 删帖前必须征得用户同意
- API key 不要写死在公开文件中
