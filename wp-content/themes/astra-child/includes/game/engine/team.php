<?php
/**
 * CRRG 演习 · 多人分工（engine/team.php，R9-③，2026-10-14）
 * ------------------------------------------------------------
 * 委员长："多人分工：任务里可指派队友（带路/问话/把风）→ 接 crrg_players_here()"
 * 口径：
 *   · **搭档必须在场**（`crrg_players_here()`，15 分钟内活动过 ✓）——"派一个不在的人"不算 ✓
 *   · 四种分工：**带路**（少走冤枉路：旅途/搜查 +1 奖励骰）／**问话**（NPC 更好说话：社交 +1）／
 *     **把风**（有人靠近先知道：被动侦查 +1、暴露 -1）／**搬运**（重物/设备：力量类 +1）
 *   · 分工**只记在个人档**（`$pc['team']`）✓；任务期满/善后清掉 ✓
 *   · 与引擎：`need.team_role`（要有这个分工的人在场）／`check.bonus_from='team:lookout'` ✓
 */
if (!defined('ABSPATH')) exit;

function crrg_team_roles() {
    return ['guide' => '带路', 'talk' => '问话', 'lookout' => '把风', 'carry' => '搬运'];
}

/** 指派（需要人在场 ✓） */
function crrg_team_assign(&$pc, $role, $whoUid) {
    $roles = crrg_team_roles();
    if (!isset($roles[$role])) return ['（没有这种分工。）'];
    $room = (string) ($pc['room'] ?? '');
    $here = function_exists('crrg_players_here') ? crrg_players_here($room, (int) ($pc['uid'] ?? 0)) : [];
    if (!in_array((int) $whoUid, array_map('intval', $here), true)) {
        return ['（人不在这一间——先把人叫过来。）'];
    }
    $pc['team'] = (array) ($pc['team'] ?? []);
    $pc['team'][$role] = (int) $whoUid;
    return [sprintf('（%s 交给他了。）', $roles[$role])];
}
function crrg_team_clear(&$pc) { $pc['team'] = []; return ['（分工撤了。）']; }
function crrg_team_has(&$pc, $role) { return (int) (($pc['team'] ?? [])[$role] ?? 0) > 0; }

/** 分工带来的检定加成（引擎 check.bonus_from = 'team:lookout' ✓） */
function crrg_team_bonus(&$pc, $role, $skill = '') {
    if (!crrg_team_has($pc, $role)) return 0;
    $rel = ['guide' => ['追踪', '导航', '侦查'], 'talk' => ['说服', '话术', '心理学'],
            'lookout' => ['侦查', '聆听'], 'carry' => ['力量', '搬运', '机械维修']];
    if ($skill !== '' && !in_array((string) $skill, (array) ($rel[$role] ?? []), true)) return 0;
    return 1;                                                            // 奖励骰 ✓
}

/** 结伴行动：把风的人能压暴露（引擎/任务可用 ✓） */
function crrg_team_expose_mod(&$pc, $n) {
    if (crrg_team_has($pc, 'lookout')) return max(0, (int) $n - 1);
    return (int) $n;
}

/** 一行（面板/手机） */
function crrg_team_lines(&$pc) {
    $roles = crrg_team_roles();
    $out = ['〔分工〕'];
    $any = false;
    foreach ($roles as $r => $cn) {
        if (!crrg_team_has($pc, $r)) continue;
        $out[] = sprintf('　%s：已指派', $cn);
        $any = true;
    }
    if (!$any) $out[] = '　（还没分工。有人在场时可以指派：带路／问话／把风／搬运。）';
    return $out;
}
