<?php
/**
 * Plugin Name: CRRG Changes Feed
 * Description: 实时生成 latest-changes.json —— 汇总论坛话题/回复、文章、紧急预警、公告、新闻，供外部程序读取「网站最近有什么变化」。
 * Version: 1.2.0
 * Author: SiteGuardian
 */

if (!defined('ABSPATH')) { exit; }

/** 输出路径（网站根目录 → https://crrg-moebius.com/latest-changes.json） */
if (!defined('CRRG_FEED_FILE'))     define('CRRG_FEED_FILE',     ABSPATH . 'latest-changes.json');
/** 每种帖子类型各保留最近 N 条（避免论坛刷屏把文章挤掉） */
if (!defined('CRRG_FEED_PER_TYPE')) define('CRRG_FEED_PER_TYPE', 20);

/* ============================================================
 * 一、论坛 / 文章（有 WP 帖子 ID，可增量）
 * ============================================================ */

/** 纳入监控的帖子类型（可扩展） */
function crrg_feed_types() {
    return apply_filters('crrg_feed_types', [
        'topic' => '论坛话题',
        'reply' => '论坛回复',
        'post'  => '文章',
    ]);
}

/* ============================================================
 * 二、预警 / 公告 / 新闻（非帖子，靠 key 去重）
 * ============================================================ */

/** 新闻缓存文件：候选里取更新的那个 */
function crrg_feed_news_file() {
    $cands = [
        ABSPATH . 'cctv_news_cache.json',
        get_stylesheet_directory() . '/cctv_news_cache.json',
    ];
    $best = ''; $best_t = 0;
    foreach ($cands as $f) {
        if (is_file($f) && filemtime($f) > $best_t) { $best = $f; $best_t = filemtime($f); }
    }
    return $best;
}

/** 非帖子类条目：紧急预警 / 公告 / 新闻 */
function crrg_feed_extra_items() {
    $items = [];

    /* --- 1) 紧急预警（管理面板发布，active 时才有） --- */
    $alert = get_option('crrg_emergency_alert', []);
    if (!empty($alert['active'])) {
        $title   = isset($alert['title'])   ? $alert['title']   : '';
        $content = isset($alert['content']) ? $alert['content'] : '';
        $ts      = (int) get_option('crrg_emergency_alert_updated', time());
        $items[] = [
            'id'         => 0,
            'key'        => 'alert-' . substr(md5($title . '|' . $content), 0, 8),
            'type'       => 'alert',
            'type_label' => '紧急预警',
            'title'      => '【紧急预警】' . $title,
            'author'     => '管理面板',
            'author_id'  => 0,
            'place'      => '',
            'time'       => date('Y-m-d H:i:s', $ts),
            'ts'         => $ts,
            'url'        => home_url('/'),
            'excerpt'    => mb_substr(trim(wp_strip_all_tags($content)), 0, 80),
            'meta'       => ['color' => isset($alert['color']) ? $alert['color'] : '#C41230'],
        ];
    }

    /* --- 2) 公告（管理面板发布） --- */
    $anns = get_option('crrg_announcements', []);
    foreach ((array) $anns as $a) {
        $t = isset($a['time']) ? $a['time'] : '';
        $ts = strtotime($t);
        if (!$ts) { continue; }
        $title   = isset($a['title'])   ? $a['title']   : '';
        $content = isset($a['content']) ? $a['content'] : '';
        $items[] = [
            'id'         => 0,
            'key'        => 'ann-' . substr(md5($title . '|' . $t), 0, 8),
            'type'       => 'announcement',
            'type_label' => '公告',
            'title'      => '【公告】' . $title,
            'author'     => '管理面板',
            'author_id'  => 0,
            'place'      => '',
            'time'       => date('Y-m-d H:i:s', $ts),
            'ts'         => $ts,
            'url'        => home_url('/notices/'),
            'excerpt'    => mb_substr(trim(wp_strip_all_tags($content)), 0, 80),
            'meta'       => [],
        ];
    }

    /* --- 3) 新闻（CCTV 新闻抓取缓存，汇总为一条；内容变了 key 才变） --- */
    $nf = crrg_feed_news_file();
    if ($nf) {
        $news = json_decode(@file_get_contents($nf), true);
        if (is_array($news) && $news) {
            $titles = [];
            foreach ($news as $n) {
                if (!empty($n['title'])) { $titles[] = $n['title']; }
            }
            if ($titles) {
                $ts = filemtime($nf);
                $items[] = [
                    'id'         => 0,
                    'key'        => 'news-' . substr(md5(implode('|', $titles)), 0, 8),
                    'type'       => 'news',
                    'type_label' => '新闻',
                    'title'      => '新闻列表更新（' . count($titles) . ' 条）',
                    'author'     => '新闻中心',
                    'author_id'  => 0,
                    'place'      => '新闻中心',
                    'time'       => date('Y-m-d H:i:s', $ts),
                    'ts'         => $ts,
                    'url'        => home_url('/newsroom/'),
                    'excerpt'    => mb_substr(implode(' ／ ', array_slice($titles, 0, 3)), 0, 80),
                    'meta'       => [
                        'count'       => count($titles),
                        'source_file' => str_replace(ABSPATH, '', $nf),
                    ],
                ];
            }
        }
    }

    return $items;
}

/* ============================================================
 * 三、重建
 * ============================================================ */

function crrg_feed_rebuild() {
    global $wpdb;
    $labels = crrg_feed_types();
    $items  = [];

    /* 论坛 / 文章 */
    if (!empty($labels)) {
        foreach ($labels as $type => $label) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_type, post_title, post_author, post_parent, post_date, post_content
                 FROM {$wpdb->posts}
                 WHERE post_type = %s AND post_status = 'publish'
                 ORDER BY post_date DESC, ID DESC
                 LIMIT %d",
                $type, CRRG_FEED_PER_TYPE
            ));
            foreach ((array) $rows as $r) {
                $author = get_userdata($r->post_author);
                $place  = '';
                if ($r->post_type === 'topic' || $r->post_type === 'reply') {
                    $fid   = get_post_meta($r->ID, '_bbp_forum_id', true);
                    $place = $fid ? get_the_title($fid) : '';
                } elseif ($r->post_type === 'post') {
                    $cats  = get_the_category($r->ID);
                    $place = !empty($cats) ? $cats[0]->name : '';
                }
                $title = ($r->post_type === 'reply')
                       ? ('回复：《' . get_the_title($r->post_parent) . '》')
                       : $r->post_title;

                $items[] = [
                    'id'         => (int) $r->ID,
                    'key'        => 'post-' . (int) $r->ID,
                    'type'       => $r->post_type,
                    'type_label' => isset($labels[$r->post_type]) ? $labels[$r->post_type] : $r->post_type,
                    'title'      => $title,
                    'author'     => $author ? $author->display_name : '',
                    'author_id'  => (int) $r->post_author,
                    'place'      => $place,
                    'time'       => $r->post_date,
                    'ts'         => (int) strtotime($r->post_date),
                    'url'        => get_permalink($r->ID),
                    'excerpt'    => mb_substr(trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($r->post_content))), 0, 80),
                    'meta'       => [],
                ];
            }
        }
    }

    /* 预警 / 公告 / 新闻 */
    $items = array_merge($items, crrg_feed_extra_items());

    /* 统一按时间倒序 */
    usort($items, function ($a, $b) { return $b['ts'] <=> $a['ts']; });

    $cursor = 0;
    foreach ($items as $it) { if ($it['id'] > $cursor) { $cursor = $it['id']; } }

    $alert = get_option('crrg_emergency_alert', []);
    $data  = [
        'generated_at' => current_time('mysql'),
        'generated_ts' => time(),
        'site'         => home_url(),
        'cursor'       => $cursor,                        // 帖子游标：读取方只看 id > cursor 的帖子类条目
        'count'        => count($items),
        'alert_active' => !empty($alert['active']),
        'items'        => $items,
    ];
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    @file_put_contents(CRRG_FEED_FILE, $json);

    if (defined('CRRG_FEED_LOCAL') && CRRG_FEED_LOCAL) {
        $dir = dirname(CRRG_FEED_LOCAL);
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        @file_put_contents(CRRG_FEED_LOCAL, $json);
    }

    return $cursor;
}

/* ============================================================
 * 四、触发时机
 * ============================================================ */

/* 论坛 */
add_action('bbp_new_topic', 'crrg_feed_rebuild', 99);
add_action('bbp_new_reply', 'crrg_feed_rebuild', 99);
/* 文章/页面 */
add_action('save_post', function ($post_id, $post) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { return; }
    if (in_array($post->post_type, array_keys(crrg_feed_types()), true)) { crrg_feed_rebuild(); }
}, 99, 2);
add_action('trashed_post', 'crrg_feed_rebuild');
add_action('untrashed_post', 'crrg_feed_rebuild');
add_action('deleted_post', 'crrg_feed_rebuild');
/* 紧急预警（管理面板） */
add_action('update_option_crrg_emergency_alert', function ($old, $value) {
    update_option('crrg_emergency_alert_updated', time(), false);
    crrg_feed_rebuild();
}, 10, 2);
/* 公告（管理面板） */
add_action('update_option_crrg_announcements', 'crrg_feed_rebuild');

/* WP-Cron 兜底（新闻缓存由外部写入，靠这里兜底） */
add_filter('cron_schedules', function ($s) {
    $s['crrg_five_minutes'] = ['interval' => 300, 'display' => '每5分钟(CRRG)'];
    return $s;
});
add_action('crrg_feed_cron', 'crrg_feed_rebuild');
add_action('init', function () {
    if (!wp_next_scheduled('crrg_feed_cron')) {
        wp_schedule_event(time() + 60, 'crrg_five_minutes', 'crrg_feed_cron');
    }
});
