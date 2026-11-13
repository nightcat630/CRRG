<?php
/**
 * 模块入口示例（Phase 0 约定示范；本模块默认不含任何内容，加东西时照这里写）
 * 约定：目录名＝模块名；module.php 是唯一入口；所有注册挂在 init（优先级 ≥ 20）。
 */
if (!defined('ABSPATH')) exit;
if (!function_exists('crrg_game_register')) return;

add_action('init', function () {
    // ① 这个模块的房间（示例：默认注释掉，避免影响现有内容）
    // crrg_game_register('room', 'x_room', ['t'=>'示例房间','f'=>'示例','c'=>1,'d'=>'说明','x'=>[]], ['module'=>'example','file'=>__FILE__]);

    // ② 这个模块的 NPC
    // crrg_game_register('npc', 'x_npc', ['name'=>'示例NPC','room'=>'x_room','topics'=>[]], ['module'=>'example']);

    // ③ 这个模块的面板区块
    // crrg_game_panel_register('x_panel', fn(&$s,&$c,&$p,$u)=>null, ['order'=>60]);

    // ④ 这个模块的合约
    // crrg_game_register('mission', 'm_x', [...], ['module'=>'example']);
}, 21);
