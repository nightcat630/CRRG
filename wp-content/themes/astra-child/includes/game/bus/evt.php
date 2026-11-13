<?php
/**
 * CRRG 演习 · 事件总线（bus，Phase 0，2026-10-14）
 * ------------------------------------------------------------
 * 为什么要有它：任务、对话、物品、门禁、声望以前是**互相直接调函数**的 ✗ ——
 *   加一条联动就要改两三个文件，改完还容易漏（"两套真相"），而且谁也说不清"谁监听谁"。
 * 现在只有两句话：
 *   · 模块之间**不许互相调函数**，只许 `crrg_evt_emit()` 发事件、`crrg_evt_on()` 订阅事件。
 *   · 事件只描述**发生了什么**（"康明远说了话"），**不描述该怎么反应**（那是订阅者的事）。
 *
 * 纪律（门禁 tools/verify-bus.php 会查）：
 *   1. 每个事件名**至少有一个订阅者**；每个订阅者订阅的事件名必须**在名单里**。
 *   2. 订阅者必须**幂等**（同一事件重复到达不得出错）。
 *   3. 订阅是**每次请求重新注册**的纯内存表（与 registry.php 同节拍），不落库。
 *
 * 事件名规范：域.动作（小写点分）。域：system / world / npc / player / mission / item。
 * 写新事件前先看 bus/README.md，把名字登记进 crrg_evt_known()，否则门禁会报"不在名单里"。
 */
if (!defined('ABSPATH')) exit;

/** 已知事件名（**唯一名单**：新增事件要在这里登记，门禁按它校验） */
function crrg_evt_known() {
    return [
        /* 世界 */
        'system.hour'    => '世界钟过整点',
        'system.day'     => '世界换日',
        'system.tick'    => '世界推进器跑过一格',
        'world.fact'     => '有公共事实写入',
        /* 玩家 */
        'player.enter'   => '玩家进入一个房间（含换场景）',
        'player.use'     => '玩家使用了一件物品',
        'player.check'   => '玩家做了一次检定（成败都在里）',
        /* NPC */
        'npc.talked'     => '玩家与某个 NPC 说了话（含话题）',
        'npc.plan'       => 'NPC 自己按计划做了一件事（世界自走）',
        /* 任务 */
        'mission.accept' => '接了任务',
        'mission.beat'   => '任务节拍推进',
        'mission.settle' => '任务结算',
    ];
}

/** 事件轨迹（本次请求内，供调试与快照用） */
function &crrg_evt_trace() { static $T = []; return $T; }

/** 订阅表：name => [ [cb, meta], … ] */
function &crrg_evt_subs() { static $S = []; return $S; }

/** 发过但没人订阅的事件名（门禁来捡；运行时只记不报错） */
function &crrg_evt_orphan() { static $O = []; return $O; }

/**
 * 订阅一个事件。
 * @param string   $name 事件名（规范：域.动作）
 * @param callable $cb   回调 function(array $payload, array $ctx): void
 * @param array    $meta 例：['module'=>'seal','file'=>__FILE__]（便于门禁报出是谁订阅的）
 */
function crrg_evt_on($name, callable $cb, array $meta = []) {
    $S = &crrg_evt_subs();
    $S[$name][] = ['cb' => $cb, 'meta' => $meta];
    return true;
}

/**
 * 发一个事件。同步、按注册顺序执行订阅者；**任何订阅者抛错都不许拖垮整局**
 * （记进 trace，继续跑下一个 —— 游戏不能因为一条联动炸掉）。
 * @return int 实际处理的订阅者数
 */
function crrg_evt_emit($name, array $payload = [], array $ctx = []) {
    $S = &crrg_evt_subs();
    $T = &crrg_evt_trace();
    $O = &crrg_evt_orphan();
    $subs = $S[$name] ?? [];
    if (!$subs) $O[$name] = ($O[$name] ?? 0) + 1;      // 没人听 → 记一笔（门禁会报）
    $n = 0;
    foreach ($subs as $s) {
        $n++;
        try {
            call_user_func($s['cb'], $payload, $ctx);
        } catch (Throwable $e) {
            $T[] = ['t' => microtime(true), 'evt' => $name, 'err' => $e->getMessage(),
                    'sub' => ($s['meta']['module'] ?? '?')];
            continue;
        }
        $T[] = ['t' => microtime(true), 'evt' => $name, 'sub' => ($s['meta']['module'] ?? '?')];
    }
    return $n;
}

/** 读本次请求的事件轨迹（调试/自检） */
function crrg_evt_log($limit = 50) { $T = &crrg_evt_trace(); return array_slice($T, -$limit); }

/** 读"无人订阅"的名单（门禁与自检用） */
function crrg_evt_orphans() { $O = &crrg_evt_orphan(); return $O; }
