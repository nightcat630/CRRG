<?php
/**
 * 河洛 · 对话代理端点
 * 链路：前端 → admin-ajax.php?action=crrg_heluo_chat → 本文件 → ai-gateway(127.0.0.1:8788) → AstrBot
 * 安全：权限与口令全部在服务端，前端拿不到任何密钥
 */
if (!defined('ABSPATH')) exit;

const CRRG_HELUO_RANKS = ['advisor', 'deputy', 'chairman'];

function crrg_heluo_can_access($user_id) {
    return in_array(crrg_get_rank($user_id), CRRG_HELUO_RANKS, true);
}

function crrg_heluo_gateway_token() {
    static $token = null;
    if ($token !== null) return $token;
    $f = __DIR__ . '/heluo-secret.php';
    $token = file_exists($f) ? (string) include $f : '';
    return $token;
}

add_action('wp_ajax_crrg_heluo_chat', 'crrg_heluo_ajax_handler');
function crrg_heluo_ajax_handler() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['msg' => '未登录'], 403);
    }
    $uid = get_current_user_id();
    if (!crrg_heluo_can_access($uid)) {
        wp_send_json_error(['msg' => '权限不足'], 403);
    }
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_heluo')) {
        wp_send_json_error(['msg' => '安全校验失败，请刷新页面'], 403);
    }

    $msg = trim(wp_unslash($_POST['message'] ?? ''));
    if ($msg === '')                       wp_send_json_error(['msg' => '消息不能为空'], 400);
    if (mb_strlen($msg) > 2000)            wp_send_json_error(['msg' => '消息过长（上限 2000 字）'], 400);

    // ★ 身份标记
    // 网页端多个使用者共用同一条对话（与 QQ 端共享记忆），若不在消息里标明说话人，
    // AI 会把别人的话当成本人（1220713948）说的。故每条网页端消息都带上身份头。
    $cur        = wp_get_current_user();
    $nick       = mb_substr($cur->display_name, 0, 12);
    $identity   = sprintf('[网｜%s#%d]', $nick, $uid);
    $msg_for_ai = $identity . ' ' . $msg;

    $token = crrg_heluo_gateway_token();
    if ($token === '') wp_send_json_error(['msg' => '网关口令缺失，请联系运维'], 500);

    $resp = wp_remote_post('http://127.0.0.1:8788/chat', [
        'timeout' => 300,
        'headers' => [
            'Content-Type' => 'application/json',
            'X-Token'      => $token,
        ],
        'body' => wp_json_encode([
            'message'    => $msg_for_ai,
            'session_id' => 'shared',        // ★ 与 QQ 端同一会话 → 共享记忆
            'username'   => '1220713948',
        ]),
    ]);

    if (is_wp_error($resp)) {
        wp_send_json_error(['msg' => '网关不可达：' . $resp->get_error_message()], 502);
    }
    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code !== 200 || empty($body['ok'])) {
        wp_send_json_error(['msg' => $body['error'] ?? ('网关返回 ' . $code)], 502);
    }

    // 本站留档：仅本站可见的每账户历史，不参与 AI 上下文
    crrg_heluo_log_add($uid, $msg, (string) ($body['reply'] ?? ''));

    wp_send_json_success([
        'reply'   => $body['reply'] ?? '',
        'elapsed' => $body['elapsed'] ?? 0,
    ]);
}

/* ------------------------------------------------------------------
 * 对话留档（2026-09-25）
 * 目的：网页端多人共用一条 AI 对话，刷新页面后前端空白。
 * 做法：本站按账户各留一份问答记录，用于刷新后回读。
 * 注意：只存本站自用，不写回 AI 上下文，不改动共享记忆。
 * ------------------------------------------------------------------ */
if (!defined('CRRG_HELUO_KEEP')) define('CRRG_HELUO_KEEP', 50);   // 每账户保留条数

function crrg_heluo_log_table() {
    global $wpdb;
    return $wpdb->prefix . 'crrg_heluo_log';
}

function crrg_heluo_maybe_install() {
    if (get_option('crrg_heluo_log_ver') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t   = crrg_heluo_log_table();
    $sql = "CREATE TABLE $t (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        question LONGTEXT NOT NULL,
        reply LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY user_id_id (user_id, id)
    ) " . $wpdb->get_charset_collate() . ";";
    dbDelta($sql);
    update_option('crrg_heluo_log_ver', '1');
}
add_action('init', 'crrg_heluo_maybe_install');

function crrg_heluo_log_add($uid, $q, $a) {
    global $wpdb;
    $t = crrg_heluo_log_table();
    $wpdb->insert($t, [
        'user_id'    => $uid,
        'question'   => $q,
        'reply'      => $a,
        'created_at' => current_time('mysql'),
    ]);
    $n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE user_id = %d", $uid));
    if ($n > CRRG_HELUO_KEEP) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $t WHERE user_id = %d ORDER BY id ASC LIMIT %d",
            $uid, $n - CRRG_HELUO_KEEP
        ));
    }
}

add_action('wp_ajax_crrg_heluo_history', 'crrg_heluo_history_handler');
function crrg_heluo_history_handler() {
    if (!is_user_logged_in())                     wp_send_json_error(['msg' => '未登录'], 403);
    $uid = get_current_user_id();
    if (!crrg_heluo_can_access($uid))             wp_send_json_error(['msg' => '权限不足'], 403);
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_heluo'))
                                                  wp_send_json_error(['msg' => '安全校验失败'], 403);

    global $wpdb;
    $t    = crrg_heluo_log_table();
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT question, reply, created_at FROM $t WHERE user_id = %d ORDER BY id DESC LIMIT %d",
        $uid, CRRG_HELUO_KEEP
    ), ARRAY_A);

    $items = [];
    foreach (array_reverse($rows ?: []) as $r) {
        $items[] = [
            'q' => (string) $r['question'],
            'a' => (string) $r['reply'],
            't' => substr((string) $r['created_at'], 11, 5),   // HH:MM
        ];
    }
    wp_send_json_success(['items' => $items, 'keep' => CRRG_HELUO_KEEP]);
}
