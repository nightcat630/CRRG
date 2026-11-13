<?php
/**
 * 宿舍：面板与窗口**共用**的区块（2026-09-30 自 drill-economy 逐字抽出）
 * 返回 ['title','note','items']；不在宿舍则返回 null。
 */
if (!defined('ABSPATH')) exit;

function crrg_dorm_block(&$s, &$ctx, &$pc, $uid = 0) {
    $act = function ($label, $k, $id = '', $extra = []) { return ['label' => $label, 'k' => $k, 'id' => $id] + $extra; };
    $room = $s['room'] ?? '';
    if (in_array($room, ['b1_room', 'b1_dorm'], true)) {
        $fx = crrg_drill_dormfx($pc);
        $d  = crrg_coc_derive($pc['attrs']);
        return ['title' => '宿舍', 'note' => sprintf('休整可恢复理智 +%d（每天一次）｜锻炼机会 %d 次（每完成一次合约 +1）｜器械：%s',
                    max(1, min(2, $fx['san'])), (int) ($pc['train_tokens'] ?? 0),
                    $fx['train'] ? implode('、', $fx['train']) : '无'),
                'items' => array_merge(
                    [$act('休整一晚（睡 8 小时）', 'dorm_rest')],
                    array_map(fn($at) => $act('锻炼 · ' . $at . '（D100 需 > ' . (int) $pc['attrs'][$at] . '）', 'dorm_train', $at), $fx['train']),
                    [$act('看装修效果', 'dorm_deco')]
                )];
    }

    return null;
}

/* ══════════ 宿舍区另外两块（2026-09-30 自 drill-economy 逐字抽出，面板与窗口共用） ══════════ */

/** 拆下退掉（宿舍／营房） */
function crrg_dorm_unsell_block(&$s, &$ctx, &$pc, $uid = 0) {
    $act = function ($label, $k, $id = '', $extra = []) { return ['label' => $label, 'k' => $k, 'id' => $id] + $extra; };
    $room = $s['room'] ?? '';
        if (in_array($room, ['b1_room', 'b1_dorm'], true)) {
            $it = [];
            foreach ((array) ($pc['dorm']['deco'] ?? []) as $slot => $name) {
                [$pr] = crrg_trade_price($name);
                if ($pr > 0) $it[] = ['label' => sprintf('拆下%s（%s，+%d 元）', $slot, $name, (int) floor($pr * 0.5)), 'k' => 'unsell', 'id' => 'deco|' . $slot];
            }
            foreach (array_unique((array) ($pc['dorm']['furn'] ?? [])) as $name) {
                [$pr] = crrg_trade_price($name);
                if ($pr > 0) $it[] = ['label' => sprintf('搬走%s（+%d 元）', $name, (int) floor($pr * 0.5)), 'k' => 'unsell', 'id' => 'furn|' . $name];
            }
            if ($it) return ['title' => '宿舍 · 拆下退掉（退 50%）',
                             'note' => '装错了、买重了都可以拆，按半价退回。', 'items' => $it];
        }
    return null;
}

/** 储物柜（宿舍／营房／仓库共用一份柜子） */
function crrg_stash_block(&$s, &$ctx, &$pc, $uid = 0) {
    $act = function ($label, $k, $id = '', $extra = []) { return ['label' => $label, 'k' => $k, 'id' => $id] + $extra; };
    $room = $s['room'] ?? '';
    /* 个人仓库面板（宿舍储物柜 / 物资仓库物资柜） */
    if (in_array($room, ['b1_room', 'b1_dorm', 'b3_warehouse'], true)) {
        $st = (array) ($pc['stash'] ?? []);
        $si = [];
        foreach (crrg_drill_bag_counts($pc) as $id => $n) {
            $si[] = $act('存入 · ' . crrg_drill_item_name($id) . '（身上 ' . $n . '）', 'store', $id);
        }
        foreach ($st as $id => $n) {
            if ($n <= 0) continue;
            $si[] = $act('取出 · ' . crrg_drill_item_name($id) . '（柜中 ' . $n . '）', 'fetch', $id);
        }
        return ['title' => '储物柜（' . ($room === 'b3_warehouse' ? '个人物资柜' : '宿舍储物柜') . '）'
                    . '　身上 ' . count((array) ($pc['items'] ?? [])) . ' 件 / 柜中 ' . array_sum($st) . ' 件',
                'note' => '点一下存入或取出；仓库与宿舍共用同一份柜子。',
                'items' => $si ?: [['label' => '（空）', 'k' => 'noop', 'id' => '', 'off' => true]]];
    }

    return null;
}
