<?php
/**
 * 青石镇 · 镇民对话（2026-09-28）
 * 口径：老式 CRPG——不问不说；生活话题（闲聊）与案件话题分开；
 *       有人知道一点与后坡有关的事（构成网状的旁证来源），但都不直接给答案。
 */
if (!defined('ABSPATH')) exit;

/* 理发师傅：全镇消息集散地 */
crrg_dlg_register('town_barber', crrg_game_town_npc_1());

/* 裁缝 */
crrg_dlg_register('town_tailor', crrg_game_town_npc_2());

/* 修车师傅 */
crrg_dlg_register('town_repairman', crrg_game_town_npc_3());

/* 澡堂老板 */
crrg_dlg_register('town_bathman', crrg_game_town_npc_4());

/* 兽医 */
crrg_dlg_register('town_vet', crrg_game_town_npc_5());

/* 磨坊主 */
crrg_dlg_register('town_miller', crrg_game_town_npc_6());

/* 活动室打牌的老人：镇上旧事的主要来源 */
crrg_dlg_register('town_oldman', crrg_game_town_npc_7());

/* 联防队员：夜里的巡逻 */
crrg_dlg_register('town_watchman', crrg_game_town_npc_8());

/* ================= 老街上的人（存量 NPC 补对话） ================= */
crrg_dlg_register('town_seller', crrg_game_town_npc_9());
crrg_dlg_register('town_grainman', crrg_game_town_npc_10());
crrg_dlg_register('town_noodle', crrg_game_town_npc_11());
crrg_dlg_register('town_teller', crrg_game_town_npc_12());
crrg_dlg_register('town_doctor2', crrg_game_town_npc_13());
crrg_dlg_register('town_clerk', crrg_game_town_npc_14());

/* 声望门实例：镇上声望 ≥25（受信）才肯给你指的"门道" */
crrg_dlg_add_topic('town_barber', [
    'id' => 'insider', 'label' => '镇上的门道（受信）', 'q' => ['门道', '路子', '谁管事'],
    'rep' => ['town_qingshi' => 25],
    'text' => ['师傅把剪子放下，擦擦手。',
               '「你在镇上算是混出名了。那我说句实在的：后坡的事，联防队比派出所门儿清。」',
               '「他们夜里巡逻的表，你上小屋找他们，报我的名，能看一眼。」',
               '（得到「联防巡逻表」——夜里蹲守时不容易被人撞见。）'],
    'sets' => ['线索_巡逻表'],
]);
