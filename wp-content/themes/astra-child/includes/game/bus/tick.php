<?php
/**
 * CRRG 演习 · 世界推进器（bus/tick.php，Phase 0，2026-10-14）
 * ------------------------------------------------------------
 * 沙盒的核心：**世界自己会动**。但它绝不能用"每分钟定时任务"去做 ✗ ——
 *   · 定时任务会跟玩家动作抢写入、还跑在没人看的时候，出问题没人知道；
 *   · 更关键的是：**它不许推进世界钟**（多人的钟是共享的，谁都不许拨）✗。
 *
 * 所以这里只有两只手：
 *   ① **惰性补算**：玩家任意动作时，把"上次 tick 到现在"之间**该发生的事**补算掉（照搬 vitals 那套）；
 *   ② 低频 cron 兜底（可选）：保证没人在线时世界也在动。
 *
 * 三条性质：
 *   · **幂等**：同一格算两次结果一致（订阅者必须幂等，门禁 verify-bus 会验）；
 *   · **可重放**：只依赖 (last_tick, now, 订阅者的纯函数) —— 给同一个时间点重跑，落的事实一样；
 *   · **不碰个人档**：个人一律惰性、按在线时长另算 ✗ 这里绝不动玩家的血/体力/期限。
 *
 * 一格 = CRRG_TICK_SLOT 世界秒（默认 30 游戏分钟）。订阅：
 *   crrg_tick_on(function ($slotAt, $ctx) { … })      // $slotAt = 该格的世界时刻
 */
if (!defined('ABSPATH')) exit;

const CRRG_TICK_OPT  = 'crrg_world_tick';
const CRRG_TICK_SLOT = 1800;      // 30 游戏分钟/格
const CRRG_TICK_MAX  = 480;       // 一次最多补算这么多格（≈10 游戏天），再久就"跳格"

function &crrg_tick_handlers() { static $H = []; return $H; }

/** 注册一个"每格做什么"（幂等！） */
function crrg_tick_on(callable $fn, array $meta = []) {
    $H = &crrg_tick_handlers();
    $H[] = ['fn' => $fn, 'meta' => $meta];
    return true;
}

function crrg_tick_last() {
    $o = get_option(CRRG_TICK_OPT, null);
    return is_array($o) ? (int) ($o['last'] ?? 0) : 0;
}

/**
 * 推进世界（惰性调用；幂等）。
 * @param int|null $now 世界时刻；默认取世界钟现值
 * @return array ['slots'=>跑了多少格, 'skipped'=>跳过的格数, 'at'=>现在的 last_tick]
 */
function crrg_tick($now = null) {
    $now = $now ?: crrg_fact_now();
    $last = crrg_tick_last();
    if ($last <= 0) {                                    // 首次：只立个起点，不倒算历史 ✓
        update_option(CRRG_TICK_OPT, ['last' => $now, 'boot' => time()], false);
        return ['slots' => 0, 'skipped' => 0, 'at' => $now];
    }
    if ($now <= $last) return ['slots' => 0, 'skipped' => 0, 'at' => $last];

    $slots = (int) floor(($now - $last) / CRRG_TICK_SLOT);
    if ($slots <= 0) return ['slots' => 0, 'skipped' => 0, 'at' => $last];

    $skipped = 0;
    if ($slots > CRRG_TICK_MAX) {                        // 离线太久：不逐格倒算，直接跳，并说清楚 ✓
        $skipped = $slots - CRRG_TICK_MAX;
        $last += $skipped * CRRG_TICK_SLOT;
        $slots = CRRG_TICK_MAX;
        crrg_fact_add('system.tick_skipped', $skipped, 'global', 0);
    }

    $H = &crrg_tick_handlers();
    $ran = 0;
    for ($i = 0; $i < $slots; $i++) {
        $last += CRRG_TICK_SLOT;
        foreach ($H as $h) {
            try { call_user_func($h['fn'], $last, ['slot' => $i, 'skipped' => $skipped]); }
            catch (Throwable $e) { /* 一格出错不许拖垮整轮 */ }
        }
        $ran++;
        if (function_exists('crrg_evt_emit')) crrg_evt_emit('system.tick', ['at' => $last], ['src' => 'tick']);
    }
    update_option(CRRG_TICK_OPT, ['last' => $last, 'ran' => $ran, 'skipped' => $skipped, 'boot' => (get_option(CRRG_TICK_OPT, [])['boot'] ?? time())], false);
    return ['slots' => $ran, 'skipped' => $skipped, 'at' => $last];
}

/** 自检：把推进器拨到指定时刻（**只在测试脚本里用，且测完必须复原**） */
function crrg_tick_set($worldAt) {
    $o = get_option(CRRG_TICK_OPT, []);
    update_option(CRRG_TICK_OPT, ['last' => (int) $worldAt, 'boot' => ($o['boot'] ?? time()), 'test' => 1], false);
}
