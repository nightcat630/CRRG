<?php
/**
 * 鉴定系统（2026-09-28 建立）
 *   哪些东西需要鉴定：物品定义里写 'unknown' => true（未鉴定）＋ 'appr' => 类别
 *   在哪鉴定：按类别去对应站点（见 crrg_appraise_sites()）
 *   怎么判：对应技能检定——成功＝鉴定清楚（记入个人档案与笔记）；失败＝只能看出门类，可再来；
 *           大失败＝看了不该看的（异常／镇物类：理智 1D3）
 *   费用：基地内部站点免费（每次 2 精力）；镇上站点收费且条件差（+1 惩罚骰）
 */
if (!defined('ABSPATH')) exit;

/** 鉴定类别 → 站点与技能 */
function crrg_appraise_kinds() {
    return [
        'occult'  => ['n' => '镇物与异常物', 'skill' => '神秘学',     'risk' => 1, 'd' => '涉及镇物、封印、不明痕迹的东西'],
        'science' => ['n' => '机械与样本',   'skill' => '科学',       'risk' => 0, 'd' => '器械、土样、环境样本'],
        'medical' => ['n' => '药品与生物',   'skill' => '医学',       'risk' => 0, 'd' => '药物、组织、体液'],
        'doc'     => ['n' => '文书与影像',   'skill' => '图书馆使用', 'risk' => 0, 'd' => '旧纸、照片、卷宗、地图'],
        'tech'    => ['n' => '器材与工具',   'skill' => '电气维修',   'risk' => 0, 'd' => '电子设备、仪器'],
    ];
}
/** 可用站点：room => 支持类别、技能加成、费用 */
function crrg_appraise_sites() {
    return [
        'b2b_tech'   => ['n' => '技术研发室·检测台', 'kinds' => ['science', 'tech', 'medical'], 'pen' => 0, 'fee' => 0, 'cur' => 'credits'],
        'b4_obs'     => ['n' => '观察室·鉴定台',     'kinds' => ['occult', 'science'],          'pen' => 0, 'fee' => 0, 'cur' => 'credits'],
        'b1_archive' => ['n' => '档案室·阅览台',     'kinds' => ['doc'],                        'pen' => 0, 'fee' => 0, 'cur' => 'credits'],
        'b2b_intel'  => ['n' => '情报分析室',        'kinds' => ['doc', 'science', 'occult'],   'pen' => 0, 'fee' => 0, 'cur' => 'credits'],
        'b1_med'     => ['n' => '医疗室·化验台',     'kinds' => ['medical'],                    'pen' => 0, 'fee' => 0, 'cur' => 'credits'],
        'tw_clinic'  => ['n' => '镇卫生院',          'kinds' => ['medical'],                    'pen' => 1, 'fee' => 20, 'cur' => 'money'],
        'tw_post'    => ['n' => '镇邮电所',        'kinds' => ['doc'],                        'pen' => 1, 'fee' => 10, 'cur' => 'money'],
    ];
}
/** 该物品是否需要鉴定 */
function crrg_appraise_needed($pc, $id) {
    if (!function_exists('crrg_drill_item_id')) return false;
    $id = crrg_drill_item_id($id);
    $def = crrg_drill_items()[$id] ?? [];
    if (empty($def['unknown'])) return false;
    return empty($pc['identified'][$id]);          // 已鉴定过就不再需要
}
/** 某站点能否鉴定该物品 */
function crrg_appraise_site_for($id, $room = null) {
    $id = crrg_drill_item_id($id);
    $def = crrg_drill_items()[$id] ?? [];
    $kind = $def['appr'] ?? '';
    $sites = crrg_appraise_sites();
    if ($room !== null) {
        if (!isset($sites[$room])) return null;
        return in_array($kind, (array) $sites[$room]['kinds'], true) ? $sites[$room] : null;
    }
    foreach ($sites as $r => $s) if (in_array($kind, (array) $s['kinds'], true)) return $s + ['room' => $r];
    return null;
}
/** 未鉴定时怎么显示（不给真名） */
function crrg_appraise_display($pc, $id) {
    if (!crrg_appraise_needed($pc, $id)) return crrg_drill_item_name($id);
    $def = crrg_drill_items()[crrg_drill_item_id($id)] ?? [];
    $k = crrg_appraise_kinds()[$def['appr'] ?? ''] ?? ['n' => '不明物'];
    return '未鉴定物（' . $k['n'] . '）';
}
/** 执行鉴定 */
function crrg_appraise(&$s, &$pc, $arg = '') {
    $room = $s['room'] ?? '';
    $sites = crrg_appraise_sites();
    if (!isset($sites[$room])) return ['这里没有能鉴定的地方。',
        '（基地：技术研发室／收容区鉴定台／档案室／情报分析室／医疗室；镇上：诊所、邮电所。）'];
    $site = $sites[$room];
    /* 找物品：没写名字就挑背包里第一件未鉴定的、且本站点能做的 */
    $id = '';
    if ($arg !== '') {
        $id = crrg_drill_find_bag($pc, $arg);
        if (!$id) return ['背包里没有「' . $arg . '」。'];
        if (!crrg_appraise_needed($pc, $id)) return [crrg_drill_item_name($id) . '不需要鉴定。'];
    } else {
        foreach ((array) crrg_drill_bag_counts($pc) as $iid => $n) {
            if (!crrg_appraise_needed($pc, $iid)) continue;
            if (crrg_appraise_site_for($iid, $room)) { $id = $iid; break; }
        }
        if ($id === '') return ['（背包里没有这里能鉴定的东西。用法：「鉴定 <物品>」。）'];
    }
    if (!crrg_appraise_site_for($id, $room)) {
        $want = crrg_appraise_site_for($id);
        return [crrg_drill_item_name($id) . '不该在这儿看。',
                $want ? ('（这东西要去：' . $want['n'] . '。）') : '（暂时没有能鉴定它的地方。）'];
    }
    /* 费用与次数 */
    $day = function_exists('crrg_wdate') ? crrg_wdate('Ymd', crrg_vitals_now()) : date('Ymd');
    $v = &$pc['vitals'];
    if (($v['appr_day'] ?? '') !== $day) { $v['appr_day'] = $day; $v['appr_n'] = 0; }
    if ((int) $v['appr_n'] >= 4) return ['今天已经看太多了——眼睛和脑子都需要歇。'];
    if ((int) $site['fee'] > 0) {
        $cur = $site['cur'] === 'credits' ? 'credits' : 'money';
        if ((int) $pc[$cur] < (int) $site['fee']) return [sprintf('%s要 %d %s。', $site['n'], $site['fee'], $cur === 'credits' ? '积分' : '元')];
        $pc[$cur] = (int) $pc[$cur] - (int) $site['fee'];
    }
    $def = crrg_drill_items()[crrg_drill_item_id($id)] ?? [];
    $kind = crrg_appraise_kinds()[$def['appr'] ?? ''] ?? ['n' => '不明物', 'skill' => '侦查', 'risk' => 0];
    $skill = $kind['skill'];
    $val = max(1, crrg_coc_skill_val($pc, $skill));
    $r = crrg_coc_check($skill, $val, 0, (int) $site['pen']);
    $v['appr_n'] = (int) $v['appr_n'] + 1;
    foreach ((array) crrg_vitals_spend($pc, 'check') as $x) { /* 精力 1 点 */ }
    $out = [sprintf('你在%s把%s摊开看。', $site['n'], crrg_appraise_display($pc, $id)),
            $skill . '检定：' . $r['text'] . ((int) $site['pen'] > 0 ? '　［条件差 +1 惩罚骰］' : '')];
    $lv = crrg_coc_level_rank($r['level']);
    if ($lv >= 2) {
        if (!isset($pc['identified']) || !is_array($pc['identified'])) $pc['identified'] = [];
        $pc['identified'][crrg_drill_item_id($id)] = ['at' => crrg_game_now(), 'by' => $skill];
        $out[] = '鉴定结果：' . crrg_drill_item_name($id) . '。';
        if (!empty($def['id_note'])) $out[] = $def['id_note'];
        else if (!empty($def['d'])) $out[] = $def['d'];
        $out[] = '（已录入个人档案；这件东西现在能用、也能说清楚了。）';
        if (!empty($def['id_flag'])) {
            if (!is_array($s['flags'] ?? null)) $s['flags'] = [];
            if (!in_array($def['id_flag'], $s['flags'], true)) $s['flags'][] = $def['id_flag'];
            $out[] = '（新线索记入笔记。）';
        }
    } elseif ($r['level'] === 'fumble') {
        $out[] = '大失败：你看出了一点不该看的东西。';
        if (!empty($kind['risk'])) {
            $dmg = crrg_coc_dice('1d3');
            $pc['san'] = max(0, (int) $pc['san'] - $dmg);
            $out[] = sprintf('（理智 −%d → %d。）', $dmg, (int) $pc['san']);
        } else {
            $out[] = '（手一抖，东西落回桌上——没看出什么，也没弄坏。）';
        }
    } else {
        $out[] = '只能看出个大概门类，具体的还说不清。' .
                 (!empty($def['d']) ? '（' . mb_substr($def['d'], 0, 14) . '……）' : '');
        $out[] = '（可以再试。）';
    }
    $out[] = sprintf('（今天还可看 %d 次。）', max(0, 4 - (int) $v['appr_n']));
    return $out;
}
/** 封门：消耗镇物封条，把当前房间贴上封条（需要先鉴定过封条） */
function crrg_seal_room(&$s, &$pc) {
    if (crrg_drill_bag_count($pc, 'key_sigil') <= 0) return ['没有镇物封条。',
        '（勤务司兑换处 6 积分一张；镇上也有人给。）'];
    if (crrg_appraise_needed($pc, 'key_sigil')) return ['这张封条你还没看明白怎么用——先去鉴定。',
        '（技术研发室或收容区鉴定台。）'];
    $room = $s['room'] ?? '';
    if (in_array($room, (array) ($s['sealed'] ?? []), true)) return ['这间已经贴过封条了。'];
    crrg_drill_bag_del_n($pc, 'key_sigil', 1);
    if (!is_array($s['sealed'] ?? null)) $s['sealed'] = [];
    $s['sealed'][] = $room;
    $rooms = crrg_drill_rooms();
    $nm = $rooms[$room]['t'] ?? $room;
    $out = ['你把封条贴在门框上，按实了四角。', sprintf('（%s 已封存。）', $nm)];
    if (function_exists('wp_insert_post')) {
        $body = "【封存记录】\n\n操作人：" . ($pc['name'] ?? '') . "（#" . ($pc['uid'] ?? '') . "）\n"
              . "房间：" . $nm . "（" . $room . "）\n"
              . "时间：" . (function_exists('crrg_wdate') ? crrg_wdate('Y-m-d H:i', crrg_game_now()) : date('Y-m-d H:i')) . "\n"
              . "用物：镇物封条 ×1\n";
        $pid = wp_insert_post(['post_title' => '【封存记录】' . $nm, 'post_content' => $body,
                               'post_status' => 'private', 'post_type' => 'post'], true);
        if (!is_wp_error($pid)) {
            update_post_meta($pid, 'crrg_report_type_name', '事件');
            update_post_meta($pid, 'crrg_access_level', 'operative');
            update_post_meta($pid, 'crrg_record_status', 'seal_record');
        }
    }
    return $out;
}
