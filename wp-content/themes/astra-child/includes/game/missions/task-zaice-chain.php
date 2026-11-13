<?php
/**
 * 《在册》· 第二～五幕的**桩**（missions/task-zaice-chain.php，R5，2026-10-14）
 * ------------------------------------------------------------
 * 为什么要桩：第一幕声明了 `chain_next => m_zaice2` ✓ 链条不能断 ✗（门禁 verify-game「任务链：链条完整无环」会拦 ✓）
 * → 先立四个空壳（名字/缘起/奖励位留好），**R6/R7 把内容填进各自的 act 文件** ✓
 * 桩里**不许**假内容 ✗：prompt 只写"待填"并说明由哪一轮负责 ✓
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    if (!function_exists('crrg_task_define')) return;
    /* 2026-10-14（R6）：**第二、三幕已经写实** ✓ → 从桩名单里删掉
       （桩与真定义同 id，同 init 优先级 = 后注册的覆盖先注册的 ✗ —— R6 一开始就被这条坑过：
        门禁报"还是桩" ✓ 这是门禁救的场，记一笔）*/
    /* 2026-10-14（R7）：**第四、五幕已经写实** ✓ → 桩名单清空（同 id 不许两处定义 ✗） */
    $stub = [];
    foreach ($stub as $id => [$name, $next, $who]) {
        crrg_task_define($id, [
            'name' => $name . '（内容待填）', 'level' => '中', 'kind' => 'base', 'min_lv' => 1, 'hidden' => true,
            'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
            'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall', 'deadline_h' => 168,
            'prompt' => '（这一幕的内容还没写——由 ' . $who . ' 负责填进对应的 act 文件。）',
            'steps' => [], 'reward' => ['credits' => 0, 'money' => 0], 'rep' => [],
            'trigger' => ['room' => '__never__'],                       // 桩不触发 ✓
            'stub' => true,                                             // 声明是桩：门禁放行，但会在提醒里点名 ✓
            'stages' => ['s0' => ['name' => '待写', 'text' => '待写']],
            'clues' => [], 'paths' => [],
            'chain_no' => (int) substr($id, -1), 'chain_total' => 5, 'chain_next' => $next,
            'cleanup' => true,
        ]);
    }
}, 35);
