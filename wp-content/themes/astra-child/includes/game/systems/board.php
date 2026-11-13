<?php
/**
 * 值班板：基地任务清单（2026-09-30 自 drill-economy 的面板内联块抽出）
 * 面板与窗口**共用**本函数，保证两边内容一致；面板日后可安全摘除。
 */
if (!defined('ABSPATH')) exit;

function crrg_board_items(&$s, &$ctx, &$pc, $uid = 0) {
    $act = function ($label, $k, $id = '', $extra = []) { return ['label' => $label, 'k' => $k, 'id' => $id] + $extra; };
        $items = [];
        foreach (crrg_drill_mission_by_kind('base') as $id => $m) {
            /* 「回声」类按主线进度上锁（2026-09-29）：没走到那一步不该看见 */
            if (!empty($m['need_chain'])) {
                $needId = ['1' => 'm_mid_seal', '2' => 'm_mid_seal2', '3' => 'm_mid_seal3', '4' => 'm_mid_seal4', '5' => 'm_mid_seal5'][(string) (int) $m['need_chain']] ?? '';
                if ($needId !== '' && !in_array($needId, (array) ($pc['mission_done'] ?? []), true)) continue;
            }
            if (!empty($m['hidden'])) {
                /* 连续任务：后续段落平时不挂出；但若链"断了"（前段做过、这段没做）→ 重新挂出，让玩家能接回来 */
                $chain = ['m_mid_seal' => 1, 'm_mid_seal2' => 2, 'm_mid_seal3' => 3, 'm_mid_seal4' => 4, 'm_mid_seal5' => 5];
                $no = (int) ($m['chain_no'] ?? 0);
                $doneList = (array) ($pc['mission_done'] ?? []);
                $started = false; $doneThis = in_array($id, $doneList, true);
                foreach ($chain as $cid => $cno) if ($cno < $no && in_array($cid, $doneList, true)) $started = true;
                /* 2026-09-29 补：第一段逾期退回后也要能重接——只要这条线动过（有 seal_ 旗标）就算"开始过" */
                if (!$started && $no === 1) {
                    foreach ((array) ($s['flags'] ?? []) as $ff) if (strpos((string) $ff, 'seal_') === 0) { $started = true; break; }
                    if (!$started && !empty($pc['seal'])) $started = true;
                }
                $inHand = ($pc['mission'] ?? '') === $id;
                $laterDone = false; foreach ($chain as $cid => $cno) if ($cno > $no && in_array($cid, $doneList, true)) $laterDone = true;
                if (!$started || $doneThis || $inHand || $laterDone) continue;
                /* 只挂"最靠前的未完成段"，避免一次列出后面好几段 */
                $firstOpen = 0;
                foreach ($chain as $cid => $cno) if (!in_array($cid, $doneList, true)) { $firstOpen = $cno; break; }
                if ($no !== $firstOpen) continue;
                $m['name'] = ($no === 1) ? '封条' : '封条（续）';                    // 第一段恢复原名，其余标"续"
            }
            $done = in_array($id, (array) ($pc['mission_done'] ?? []), true);
            $lock = crrg_drill_eff_lv($pc, $ctx) < (int) ($m['min_lv'] ?? 3);
            /* 可重复日常：显示"每日可做／冷却中／可再接" */
            $repTxt = '';
            if (!empty($m['repeat'])) {
                if (!$done) $repTxt = ' · 每日可做';
                else {
                    $rs = crrg_drill_mission_repeat_state($pc, $id);
                    if ($rs['can']) $repTxt = ' · 可再接';
                    else { $repTxt = ' · 冷却中（' . preg_replace('/^今天的份已经做过了（约 |）。$/', '', $rs['why']) . '）'; $done = true; }
                }
            }
            $pz = !empty($m['party']) ? sprintf('%d～%d 人', (int) $m['party']['min'], (int) $m['party']['max']) : '不限人数';
            $seg = (!empty($m['chain_total']) && !empty($m['chain_no'])) ? sprintf(' · 第 %d／%d 段', $m['chain_no'], $m['chain_total']) : '';
            $items[] = $act(sprintf('%s%s（%s ｜ %d 积分 / %d 元）%s%s', $m['name'], $seg, $pz, $m['reward']['credits'] ?? 0,
                                    $m['reward']['money'] ?? 0, $repTxt, ($done && $repTxt === '') ? ' · 已完成' : ($lock ? ' · 资格不足' : '')),
                            'accept', $id, ['note' => $m['prompt'] ?? '', 'done' => $done, 'locked' => $lock]);
        }
        /* 连续任务的"续段"可能不是 base 类（如镇上的段落），也要能从值班板接回来 */
        $chainMap = ['m_mid_seal' => 1, 'm_mid_seal2' => 2, 'm_mid_seal3' => 3, 'm_mid_seal4' => 4, 'm_mid_seal5' => 5];
        $doneL = (array) ($pc['mission_done'] ?? []);
        foreach ($chainMap as $cid => $cno) {
            if (in_array($cid, $doneL, true)) continue;
            $isFirstOpen = true;
            foreach ($chainMap as $cid2 => $cno2) if ($cno2 < $cno && !in_array($cid2, $doneL, true)) { $isFirstOpen = false; break; }
            if (!$isFirstOpen) continue;                                    // 只挂最靠前的未完成段
            if (($pc['mission'] ?? '') === $cid) continue;
            if ($cid === 'm_mid_seal') continue;          // 第一段本来就挂在板上，别重复
            $cm = crrg_drill_mission_any($cid); if (!$cm) continue;
            if (crrg_drill_eff_lv($pc, $ctx) < (int) ($cm['min_lv'] ?? 3)) {
                $items[] = $act(sprintf('封条（续） · 第 %d／5 段（资格不足）', $cno), 'accept', $cid,
                    ['note' => '链上的下一段；演习权限到 ' . crrg_drill_clearance_name((int) ($cm['min_lv'] ?? 3)) . ' 才能接。', 'locked' => true]);
                continue;
            }
            $items[] = $act(sprintf('封条（续） · 第 %d／5 段（接着做）', $cno), 'accept', $cid,
                ['note' => $cm['prompt'] ?? '']);
        }
    return $items;
}
