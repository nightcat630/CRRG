<?php
/**
 * CRRG 演习 · 世界事实表（bus/facts.php，Phase 0，2026-10-14）
 * ------------------------------------------------------------
 * 是什么：**公共发生的事**的账本。"谁第一个到过 B4 C2""镇上有人见过你""水声被注意到了"——
 *   这类事实不属于任何单个玩家，属于**局面**，所以不能塞进个人档 ✗，单独一张表。
 *
 * 三条硬规矩：
 *   1. **只追加**（append-only）：不 UPDATE、不 DELETE。要"撤回"就追加一条相反事实 ✓
 *      → 多人同时改同一件事**无锁无冲突** ✓（两个请求各插一行，谁也不覆盖谁）
 *   2. **边界**：个人档内的事仍用个人 flag ✗ 不进这张表；
 *      只有"公共发生的事"才进来 ✓（否则又是两套真相）
 *   3. 写入公共事实要**过授权** crrg_fact_propose()：
 *      · once      独有型：一次写入不可逆（"他把东西交上去了"）
 *      · threshold 阈值型：够 N 个人参与才成立（默认 2）
 *      · roles     职务型：值班/处级以上允许单人写入
 */
if (!defined('ABSPATH')) exit;

function crrg_facts_table() { global $wpdb; return $wpdb->prefix . 'crrg_facts'; }

/** 建表（幂等；用 option 版本号挡一次，不做每次 dbDelta） */
function crrg_facts_install() {
    if (get_option('crrg_facts_ver') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = crrg_facts_table();
    $sql = "CREATE TABLE $t (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        k VARCHAR(190) NOT NULL,
        v LONGTEXT NULL,
        scope VARCHAR(32) NOT NULL DEFAULT 'global',
        write_at BIGINT NOT NULL DEFAULT 0,
        real_at BIGINT NOT NULL DEFAULT 0,
        by_uid BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY k_scope (k, scope),
        KEY scope_id (scope, id)
    ) DEFAULT CHARSET=utf8mb4;";
    dbDelta($sql);
    update_option('crrg_facts_ver', '1', false);
}
add_action('init', 'crrg_facts_install', 1);

/** 当前世界时刻（没有世界钟时退回现实时间；**只读，绝不写钟**） */
function crrg_fact_now() {
    if (function_exists('crrg_world_clock')) {
        $c = crrg_world_clock();
        if (!empty($c['ts'])) return (int) $c['ts'];
    }
    return time();
}

/**
 * 追加一条事实（**底层写入**；公共域请走 crrg_fact_propose()）
 * @return int 新行 id（失败返回 0）
 */
function crrg_fact_add($k, $v = true, $scope = 'global', $by = 0) {
    global $wpdb;
    crrg_facts_install();
    $ok = $wpdb->insert(crrg_facts_table(), [
        'k'        => (string) $k,
        'v'        => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE),
        'scope'    => (string) $scope,
        'write_at' => crrg_fact_now(),
        'real_at'  => time(),
        'by_uid'   => (int) $by,
    ]);
    if (!$ok) return 0;
    $id = (int) $wpdb->insert_id;
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('world.fact', ['k' => $k, 'v' => $v, 'scope' => $scope, 'by' => $by, 'id' => $id],
                      ['src' => 'facts']);
    }
    return $id;
}

/** 取某键最新一条（返回 ['v','at','by','id'] 或 null） */
function crrg_fact($k, $scope = null) {
    global $wpdb;
    crrg_facts_install();
    $sql = "SELECT * FROM " . crrg_facts_table() . " WHERE k = %s";
    $args = [$k];
    if ($scope !== null) { $sql .= " AND scope = %s"; $args[] = $scope; }
    $sql .= " ORDER BY id DESC LIMIT 1";
    $r = $wpdb->get_row($wpdb->prepare($sql, $args), ARRAY_A);
    return $r ? ['v' => $r['v'], 'at' => (int) $r['write_at'], 'by' => (int) $r['by_uid'], 'id' => (int) $r['id']] : null;
}

/** 计数（**多人阈值靠它**）：某键一共被记了几次 / 几个不同的人写过 */
function crrg_fact_count($k, $scope = null, $distinct_by = false) {
    global $wpdb;
    crrg_facts_install();
    $col = $distinct_by ? 'COUNT(DISTINCT by_uid)' : 'COUNT(*)';
    $sql = "SELECT $col FROM " . crrg_facts_table() . " WHERE k = %s";
    $args = [$k];
    if ($scope !== null) { $sql .= " AND scope = %s"; $args[] = $scope; }
    return (int) $wpdb->get_var($wpdb->prepare($sql, $args));
}

/** 最近若干条（面板"公共事实速览"用） */
function crrg_fact_recent($scope = null, $limit = 20) {
    global $wpdb;
    crrg_facts_install();
    $sql = "SELECT k, v, scope, write_at, by_uid FROM " . crrg_facts_table();
    $args = [];
    if ($scope !== null) { $sql .= " WHERE scope = %s"; $args[] = $scope; }
    $sql .= " ORDER BY id DESC LIMIT %d";
    $args[] = (int) $limit;
    return $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];
}

/**
 * 提议一条**公共**事实（带授权）。
 * @param array $opt ['mode'=>'once'|'threshold'|'roles', 'need'=>2, 'roles'=>['chairman','head'], 'scope'=>'base', 'who'=>['lv'=>3,'role'=>'…']]
 * @return array [bool 成立, string 说明]
 */
function crrg_fact_propose($k, $v = true, array $opt = [], $by = 0) {
    $mode  = $opt['mode'] ?? 'once';
    $scope = $opt['scope'] ?? 'base';
    if ($mode === 'once') {
        if (crrg_fact_count($k, $scope) > 0) return [false, '这件事已经有人定过（独有型：一次写入不可逆）'];
        crrg_fact_add($k, $v, $scope, $by);
        return [true, '写入：' . $k];
    }
    if ($mode === 'threshold') {
        $need = max(1, (int) ($opt['need'] ?? 2));
        // 同一个人重复参与只算一次 ✓（by_uid 去重）
        if (crrg_fact_count($k, $scope, true) >= $need) return [false, '已达阈值'];
        crrg_fact_add($k, $v, $scope, $by);
        $now = crrg_fact_count($k, $scope, true);
        return $now >= $need ? [true, sprintf('够了：%d/%d 人参与，%s 成立', $now, $need, $k)]
                             : [false, sprintf('还不够：%d/%d 人（差 %d）', $now, $need, $need - $now)];
    }
    if ($mode === 'roles') {
        $roles = (array) ($opt['roles'] ?? ['chairman']);
        $role  = (string) (($opt['who']['role'] ?? ''));
        $lv    = (int) ($opt['who']['lv'] ?? 0);
        if (in_array($role, $roles, true) || $lv >= 3) {          // 3 = 处级起步（与门禁矩阵同源）
            crrg_fact_add($k, $v, $scope, $by);
            return [true, '写入（职务型）：' . $k];
        }
        return [false, '这件事要值班/处级以上才能定下来'];
    }
    return [false, '未知的授权模式：' . $mode];
}
