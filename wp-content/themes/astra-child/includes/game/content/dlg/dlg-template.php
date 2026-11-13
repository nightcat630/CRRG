<?php
/**
 * 对话内容模板（content/dlg/dlg-template.php）
 * ------------------------------------------------------------
 * 照这份抄一份就能加一个 NPC 的对话。**不要在这里改逻辑**，逻辑在 engine / systems 里。
 * 文件名建议 `dlg-<谁>.php`，在 functions.php 里照同类 require 一行。
 *
 * 字段说明（只列会被引擎读的）：
 *   'npc'    => 'kang'                  // NPC id（= assets/faces/<id>.png 的 id ✓ 立绘自动对上）
 *   'name'   => '康明远'                 // 显示名
 *   'room'   => 'b2b_duty'              // 常驻房间（不在场时由 actor_where 覆盖 ✓）
 *   'topics' => [                       // 话题表：key = 话题 id
 *       '当班' => [
 *           'label'  => '当班（夜班）',   // 按钮上显示的字（**别写内部键** ✓）
 *           'need'   => ['flag' => ['s1_xxx']],   // 出现条件（可选）
 *           'once'   => true,            // 只能问一次（可选）
 *           'stage'  => 's2',            // 只在任务某阶段出现（可选）
 *           'text'   => ['第一句', '第二句'],      // NPC 说的话（**人话，不带状态键** ✓）
 *           'effect' => ['clue' => 'clue_xxx', 'rep' => ['base' => 1]],  // 问完给什么（可选）
 *       ],
 *   ],
 */
if (!defined('ABSPATH')) exit;

crrg_dlg_define('kang', [
    'name' => '康明远',
    'room' => 'b2b_duty',
    'topics' => [
        '当班' => [
            'label' => '当班（夜班）',
            'text'  => [
                '「夜里就我一个。」他把手电往桌上一放。',
                '「有事敲两下门框，别直接推——我这边在记东西。」',
            ],
        ],
        '活儿' => [
            'label'  => '有没有活儿给我',
            'once'   => true,
            'effect' => ['rep' => ['base' => 0]],
            'text'   => [
                '「活儿不缺。」他翻了一页排班表。',
                '「缺的是敢接的人。你要哪一样，说清楚。」',
            ],
        ],
    ],
]);
