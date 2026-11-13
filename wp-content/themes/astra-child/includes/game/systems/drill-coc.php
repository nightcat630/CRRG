<?php
/**
 * 虚拟演习 · CoC 第七版规则层
 * 属性、技能基线、职业、检定（含奖惩骰、成功等级）、理智检定、角色卡渲染。
 * 数值与《克苏鲁的呼唤》第七版规则一致：
 *   力量/体质/敏捷/外貌/意志 = 3D6×5；体型/智力/教育 = (2D6+6)×5；幸运 = 3D6×5
 *   生命 = (体质+体型)/10 向下取整；魔法 = 意志/5；理智 = 意志
 *   检定：D100 ≤ 技能＝成功，≤技能/2＝困难成功，≤技能/5＝极难成功；1＝大成功；
 *         技能<50 时 96–100 为大失败，技能≥50 时只有 100 为大失败
 */
if (!defined('ABSPATH')) exit;

/* ---------------- 骰子 ---------------- */
function crrg_coc_dice($expr) {
    $expr = strtolower(trim((string) $expr));
    if (preg_match('/^(\d*)d(\d+)([+-]\d+)?$/', $expr, $m)) {
        $n = $m[1] === '' ? 1 : (int) $m[1];
        $f = (int) $m[2];
        $sum = 0;
        for ($i = 0; $i < max(1, min(20, $n)); $i++) $sum += mt_rand(1, max(2, $f));
        if (isset($m[3])) $sum += (int) $m[3];
        return $sum;
    }
    return (int) $expr;
}
/** D100：十位一颗、个位一颗；十位与个位同时为 0 记作 100；奖惩骰只加掷十位、取最好/最坏的一颗 */
function crrg_coc_roll100($bonus = 0, $penalty = 0) {
    $n = max(0, $bonus) - max(0, $penalty);
    $count = 1 + abs($n);
    $ones  = mt_rand(0, 9);
    $best  = null;
    for ($i = 0; $i < $count; $i++) {
        $t = mt_rand(0, 9) * 10;
        if ($best === null) { $best = $t; continue; }
        if ($n > 0) $best = min($best, $t);       // 奖励骰取小
        elseif ($n < 0) $best = max($best, $t);   // 惩罚骰取大
    }
    if ($best === null) $best = 0;
    $v = $best + $ones;
    return $v === 0 ? 100 : $v;
}
function crrg_coc_level($roll, $skill) {
    if ($roll === 1) return 'crit';
    if ($skill < 50 ? ($roll >= 96 && $roll <= 100) : $roll === 100) return 'fumble';
    if ($roll <= (int) floor($skill / 5)) return 'extreme';
    if ($roll <= (int) floor($skill / 2)) return 'hard';
    if ($roll <= $skill) return 'ok';
    return 'fail';
}
function crrg_coc_level_name($lv) {
    return ['crit' => '大成功', 'extreme' => '极难成功', 'hard' => '困难成功',
            'ok' => '成功', 'fail' => '失败', 'fumble' => '大失败'][$lv] ?? $lv;
}
function crrg_coc_level_rank($lv) {
    return ['fumble' => 0, 'fail' => 1, 'ok' => 2, 'hard' => 3, 'extreme' => 4, 'crit' => 5][$lv] ?? 0;
}
/** 一次技能检定：返回 roll / level / 文案 */
function crrg_coc_check($skill, $val, $bonus = 0, $penalty = 0) {
    /* 身体状态带来的惩罚骰（由动作入口设置，力竭/精力耗尽/脱水时生效） */
    if (!empty($GLOBALS['crrg_vital_pen'])) $penalty += (int) $GLOBALS['crrg_vital_pen'];
    /* 2026-10-14（madness）：**症状/临时疯狂 → 相关检定 +1 惩罚骰**（CoC 口径 ✓） */
    if (!empty($GLOBALS['crrg_pc_ref']) && function_exists('crrg_mad_penalty')) {
        $penalty += (int) crrg_mad_penalty($GLOBALS['crrg_pc_ref'], (string) $skill, '');
    }
    /* 2026-10-14（R10）：**天气也要咬人** —— 雨雪雾天，视线/脚底/耳朵不如平时 ✓ */
    if (function_exists('crrg_weather_pen')) {
        $penalty += (int) crrg_weather_pen((string) $skill, (string) ($tag ?? ''));
    }
    $roll = crrg_coc_roll100($bonus, $penalty);
    $lv   = crrg_coc_level($roll, $val);
    $bd   = $bonus > 0 ? '（奖励骰）' : ($penalty > 0 ? '（惩罚骰）' : '');
    /* 2026-10-14（bus P0）：检定是"公共可见的后果"的来源之一 →
       **在出口统一发事件**（成败都在 payload 里 ✓ 订阅者自己决定要不要理） */
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('player.check', [
            'uid'   => (int) ($GLOBALS['crrg_ctx_uid'] ?? 0),
            'room'  => (string) ($GLOBALS['crrg_ctx_room'] ?? ''),
            'skill' => (string) $skill,
            'roll'  => $roll,
            'level' => $lv,
            'ok'    => crrg_coc_level_rank($lv) >= 2,
        ], ['src' => 'coc']);
    }
    return [
        'roll'  => $roll, 'level' => $lv, 'name' => crrg_coc_level_name($lv), 'skill' => $skill,
        'text'  => sprintf('%s检定%s：D100＝%d ／ %s（技能 %d%%）', $skill, $bd, $roll,
                           crrg_coc_level_name($lv), $val),
    ];
}

/* ---------------- 属性与派生 ---------------- */
function crrg_coc_roll_attrs() {
    return [
        '力量' => crrg_coc_dice('3d6') * 5, '体质' => crrg_coc_dice('3d6') * 5,
        '敏捷' => crrg_coc_dice('3d6') * 5, '外貌' => crrg_coc_dice('3d6') * 5,
        '意志' => crrg_coc_dice('3d6') * 5, '体型' => (crrg_coc_dice('2d6') + 6) * 5,
        '智力' => (crrg_coc_dice('2d6') + 6) * 5, '教育' => (crrg_coc_dice('2d6') + 6) * 5,
        '幸运' => crrg_coc_dice('3d6') * 5,
    ];
}
function crrg_coc_derive($a) {
    $str = (int) ($a['力量'] ?? 50); $siz = (int) ($a['体型'] ?? 50); $dex = (int) ($a['敏捷'] ?? 50);
    $db  = $str + $siz;
    if ($db < 65)        { $build = -2; $dmg = '-2'; }
    elseif ($db < 85)    { $build = -1; $dmg = '-1'; }
    elseif ($db < 125)   { $build = 0;  $dmg = '0'; }
    elseif ($db < 165)   { $build = 1;  $dmg = '1D4'; }
    elseif ($db < 205)   { $build = 2;  $dmg = '1D6'; }
    else                 { $build = 3;  $dmg = '2D6'; }
    $mov = 8;
    if ($dex < $siz && $str < $siz) $mov = 7;
    if ($dex > $siz && $str > $siz) $mov = 9;
    return [
        'hp_max' => (int) floor(((int) ($a['体质'] ?? 50) + (int) ($a['体型'] ?? 50)) / 10),
        'mp_max' => (int) floor((int) ($a['意志'] ?? 50) / 5),
        'san_max' => (int) ($a['意志'] ?? 50),
        'mov' => $mov, 'build' => $build, 'db' => $dmg,
    ];
}

/* ---------------- 技能基线 ---------------- */
function crrg_coc_skill_base($a) {
    return [
        '会计' => 5, '人类学' => 1, '估价' => 5, '考古学' => 1, '魅惑' => 15, '攀爬' => 20,
        '计算机' => 5, '信用评级' => 0, '克苏鲁神话' => 0, '乔装' => 5,
        '闪避' => (int) floor($a['敏捷'] / 2), '驾驶' => 20, '电气维修' => 10, '电子学' => 1,
        '话术' => 5, '格斗' => 25, '手枪' => 20, '步枪霰弹枪' => 25,
        '急救' => 30, '历史' => 5, '恐吓' => 15, '跳跃' => 20,
        '母语' => $a['教育'], '法律' => 5, '图书馆使用' => 20, '聆听' => 20, '锁匠' => 1,
        '机械维修' => 10, '医学' => 1, '博物学' => 10, '导航' => 10, '神秘学' => 5,
        '操作重型机械' => 1, '说服' => 10, '精神分析' => 1, '心理学' => 10, '骑术' => 5,
        '科学' => 1, '妙手' => 10, '侦查' => 25, '潜行' => 20, '生存' => 10, '游泳' => 20,
        '投掷' => 20, '追踪' => 10, '外语' => 1,
    ];
}

/* ---------------- 职业（CRRG 编制） ---------------- */
function crrg_coc_occ() {
    return [
        '行动员'      => ['格斗', '手枪', '步枪霰弹枪', '闪避', '潜行', '侦查', '聆听', '攀爬'],
        '情报分析员'  => ['图书馆使用', '侦查', '聆听', '心理学', '说服', '会计', '法律', '外语'],
        '研究员'      => ['科学', '医学', '博物学', '计算机', '图书馆使用', '电气维修', '机械维修', '侦查'],
        '档案文献馆员' => ['图书馆使用', '历史', '法律', '会计', '母语', '外语', '侦查', '估价'],
        '特殊勤务司队员' => ['格斗', '手枪', '闪避', '恐吓', '聆听', '侦查', '急救', '攀爬'],
        '医疗员'      => ['医学', '急救', '精神分析', '心理学', '科学', '博物学', '聆听', '侦查'],
        '文职'        => ['会计', '计算机', '法律', '图书馆使用', '说服', '心理学', '母语', '侦查'],
        '后勤'        => ['机械维修', '电气维修', '操作重型机械', '驾驶', '会计', '估价', '侦查', '急救'],
    ];
}

/* ---------------- 角色卡 ---------------- */
function crrg_coc_new_pc() {
    return ['ready' => false, 'stage' => 'attrs', 'name' => '', 'occ' => '',
            'attrs' => [], 'skills' => [], 'occ_pts' => 0, 'int_pts' => 0,
            'hp' => 0, 'mp' => 0, 'san' => 0, 'items' => [], 'worn' => [], 'posture' => '站立'];
}
/** 未建卡的账号拿到的兜底卡：演习数值取中位，保证老账号不被卡住 */
function crrg_coc_default_pc() {
    $a = ['力量' => 50, '体质' => 50, '敏捷' => 50, '外貌' => 50, '意志' => 50,
          '体型' => 50, '智力' => 50, '教育' => 50, '幸运' => 50];
    $d = crrg_coc_derive($a);
    return ['ready' => true, 'stage' => 'done', 'name' => '未登记', 'occ' => '文职',
            'attrs' => $a, 'skills' => crrg_coc_skill_base($a), 'occ_pts' => 0, 'int_pts' => 0,
            'hp' => $d['hp_max'], 'mp' => $d['mp_max'], 'san' => $d['san_max'],
            'items' => [], 'worn' => [], 'posture' => '站立'];
}
function crrg_coc_sheet_lines($pc) {
    if (empty($pc['attrs'])) return ['（尚无角色卡）'];
    $a = $pc['attrs']; $d = crrg_coc_derive($a);
    $l = [];
    $l[] = sprintf('姓名 %s ｜ 职业 %s ｜ 姿态 %s', $pc['name'] ?: '—', $pc['occ'] ?: '—', $pc['posture'] ?? '站立');
    $l[] = sprintf('力量 %d　体质 %d　敏捷 %d　外貌 %d　意志 %d', $a['力量'], $a['体质'], $a['敏捷'], $a['外貌'], $a['意志']);
    $l[] = sprintf('体型 %d　智力 %d　教育 %d　幸运 %d', $a['体型'], $a['智力'], $a['教育'], $a['幸运']);
    $l[] = sprintf('生命 %d/%d　魔法 %d/%d　理智 %d/%d　移动 %d　体格 %d　伤害加值 %s',
        $pc['hp'], $d['hp_max'], $pc['mp'], $d['mp_max'], $pc['san'], $d['san_max'],
        $d['mov'], $d['build'], $d['db']);
    return $l;
}
function crrg_coc_skill_lines($pc) {
    $sk = $pc['skills'] ?: [];
    arsort($sk);
    $out = [];
    foreach ($sk as $k => $v) $out[] = sprintf('%s %d%%', $k, $v);
    return $out;
}
/** 八项属性名（检定取值时优先于技能表） */
function crrg_coc_attr_names() {
    return ['力量', '体质', '敏捷', '外貌', '意志', '体型', '智力', '教育', '幸运'];
}
/** 通用取值：角色卡或战斗参与者都能用——属性优先，其次技能 */
function crrg_coc_who_val($who, $name) {
    $attrs  = (array) ($who['attrs']  ?? []);
    $skills = (array) ($who['skills'] ?? []);
    if (isset($attrs[$name]))  return (int) $attrs[$name];
    if (isset($skills[$name])) return (int) $skills[$name];
    foreach ($skills as $k => $v) if (mb_strpos($k, $name) !== false || mb_strpos($name, $k) !== false) return (int) $v;
    foreach ($attrs as $k => $v)  if (mb_strpos($k, $name) !== false || mb_strpos($name, $k) !== false) return (int) $v;
    return 0;
}
/** 技能的「当前值」：优先角色卡技能，其次属性（力量/敏捷/意志这类本来就在属性里） */
function crrg_coc_skill_val($pc, $name) {
    if (in_array($name, crrg_coc_attr_names(), true) && isset($pc['attrs'][$name])) return (int) $pc['attrs'][$name];
    if (!isset($pc['attrs']) || !isset($pc['skills'])) { /* R5：容错——缺属性/技能表的角色不该抛 Warning ✓ */ $pc['attrs'] = (array) ($pc['attrs'] ?? []); $pc['skills'] = (array) ($pc['skills'] ?? []); }
    $sk = $pc['skills'] ?: crrg_coc_skill_base($pc['attrs'] ?: crrg_coc_default_pc()['attrs']);
    if (isset($sk[$name])) return (int) $sk[$name];
    foreach ($sk as $k => $v) if (mb_strpos($k, $name) !== false || mb_strpos($name, $k) !== false) return (int) $v;
    if (in_array($name, crrg_coc_attr_names(), true)) return (int) ($pc['attrs'][$name] ?? 0);
    return 0;
}

/* ---------------- 理智检定 ---------------- */
function crrg_coc_san_check(&$pc, $expr, $reason) {
    $san = (int) $pc['san'];
    $roll = crrg_coc_roll100();
    $lv = crrg_coc_level($roll, max(1, $san));
    $lost = ($lv === 'ok' || $lv === 'hard' || $lv === 'extreme' || $lv === 'crit')
          ? crrg_coc_dice(explode('/', $expr)[0]) : crrg_coc_dice(explode('/', $expr)[1] ?? explode('/', $expr)[0]);
    if ($lv === 'fumble') $lost = max($lost, crrg_coc_dice(explode('/', $expr)[1] ?? '1'));
    $pc['san'] = max(0, $san - $lost);
    return sprintf('理智检定（%s）：D100＝%d ／ %s，理智 %d → %d（损失 %d）',
                   $reason, $roll, crrg_coc_level_name($lv), $san, $pc['san'], $lost);
}
