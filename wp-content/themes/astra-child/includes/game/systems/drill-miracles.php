<?php
/**
 * 秘术系统（2026-10-31 · R9）
 * 文本来源：世界观/17-怪胎秘术（本方改写）.md —— 六条，不改设定。
 * 口径：来源只认「思」「重生」「兵主」三条线；代价只记个人账（理智／时间／暴露）。
 *
 * 实现范围：
 *   ✓ 学／施／列三条指令，六条术全部可学可施，代价照文本扣。
 *   ✓ 借手 → 真正加奖励骰（drill-combat.php 里读 crrg_miracle_borrow_bonus()）。
 *   ✓ 封声 → 真删本房间最近一分钟的现场记录（feed 只到分钟级，见下方 TODO）。
 *   ✓ 递条子 → 真降演习权限并记恢复时间。
 *   ⚠ 闻字／续一口气／看门：机制通，内容表是空的 —— 剧情答案留给委员长填，
 *     空表时不编造，只回机械结果（次数／有无／黑）。
 *
 * 存储：学过的术记在 $pc['skills']['秘术·<名>'] = 1（会出现在角色卡技能表里）。
 */

/** 六条术的定义（照文本抄，别在这里改数值） */
function crrg_miracles() {
    return [
        'wenzi' => [
            'n' => '闻字', 'src' => '思', 'tier' => 1, 'tier_n' => '低阶',
            'cost' => ['k' => 'fix', 'v' => 1], 'cost_n' => '1 理智；用过三天内读手写东西反胃',
            'd' => '手贴纸页，听出这页被写过几次、最后一次有没有被擦。看不出内容。',
        ],
        'xuyi' => [
            'n' => '续一口气', 'src' => '重生', 'tier' => 1, 'tier_n' => '低阶',
            'cost' => ['k' => 'dice', 'v' => '2d6'], 'cost_n' => '2D6 理智；说完要亲手合眼',
            'd' => '让刚死不到一炷香的人续一口气，能说三句话。',
        ],
        'jieshou' => [
            'n' => '借手', 'src' => '兵主', 'tier' => 2, 'tier_n' => '中阶',
            'cost' => ['k' => 'dice', 'v' => '1d4'], 'cost_n' => '1D4 理智；之后一段时间看谁都像要动手',
            'd' => '一轮内格斗 +1 奖励骰。只对同类（人）有效。',
        ],
        'fengsheng' => [
            'n' => '封声', 'src' => '思', 'tier' => 2, 'tier_n' => '中阶',
            'cost' => ['k' => 'fix', 'v' => 3], 'cost_n' => '3 理智；自己也会忘，忘得比他们干净',
            'd' => '抹掉现场刚说过的三十秒，含你自己。',
        ],
        'kanmen' => [
            'n' => '看门', 'src' => '重生', 'tier' => 3, 'tier_n' => '高阶',
            'cost' => ['k' => 'dice', 'v' => '1d10'], 'cost_n' => '1D10 理智；一时分不清自己在门的哪一边',
            'd' => '对被人为封死的门，看三个画面：谁最后进去、谁最后出来、什么时候封的。',
        ],
        'ditiaozi' => [
            'n' => '递条子', 'src' => '兵主', 'tier' => 3, 'tier_n' => '高阶（慎用）',
            'cost' => ['k' => 'fix', 'v' => 5], 'cost_n' => '5 理智＋自降一级演习权限，一周后恢复',
            'd' => '把镇物临时封进一个容器，封 24 小时。只能封，不能解。',
        ],
    ];
}

/** 演习权限降一级：档位不是连续整数（1=D 3=C 5=B 6=A 7=S），不能直接减 1 */
function crrg_miracle_lv_down($lv) {
    $lv = (int) $lv;
    foreach ([7 => 6, 6 => 5, 5 => 3, 3 => 1] as $from => $to) if ($lv >= $from) return $to;
    return 1;
}

/** 学这条术需要的演习权限（D=1 / C=3 / B=5） */
function crrg_miracle_need_lv($tier) { return [1 => 1, 2 => 3, 3 => 5][(int) $tier] ?? 1; }

/** 按名字（含简称）找一条术 */
function crrg_miracle_find($arg) {
    $arg = trim((string) $arg);
    if ($arg === '') return '';
    foreach (crrg_miracles() as $k => $m) {
        if ($arg === $m['n'] || $arg === $k) return $k;
        if (mb_strpos($m['n'], $arg) !== false && mb_strlen($arg) >= 2) return $k;
    }
    return '';
}

function crrg_miracle_key($k) { $m = crrg_miracles(); return '秘术·' . ($m[$k]['n'] ?? $k); }

function crrg_miracle_learned($pc, $k) {
    return isset($pc['skills'][crrg_miracle_key($k)]);
}

/** 权限降级的到期恢复（每次用秘术指令都会先跑一遍） */
function crrg_miracle_tick(&$pc) {
    $out = [];
    $r = $pc['miracle_restore'] ?? null;
    if (is_array($r) && (int) ($r['at'] ?? 0) > 0 && time() >= (int) $r['at']) {
        $pc['clearance'] = max((int) ($pc['clearance'] ?? 0), (int) ($r['lv'] ?? 0));
        unset($pc['miracle_restore']);
        $out[] = '（一周到了，演习权限恢复。递条子的账清了。）';
    }
    $b = $pc['miracle_borrow'] ?? null;   // 借手：下一轮格斗的奖励骰，过期作废
    if (is_array($b) && (int) ($b['at'] ?? 0) > 0 && time() - (int) $b['at'] > 600) {
        unset($pc['miracle_borrow']);
        $out[] = '（借手的那股劲散了。）';
    }
    return $out;
}

/* ================= 指令：列 / 学 / 施 ================= */

function crrg_miracle_lines(&$pc) {
    $out = crrg_miracle_tick($pc);
    $out[] = '秘术（来源只认思、重生、兵主三条线；代价记个人账）：';
    foreach (crrg_miracles() as $k => $m) {
        $out[] = sprintf('　%s·%s　%s　代价：%s%s', $m['src'], $m['n'], $m['tier_n'], $m['cost_n'],
            crrg_miracle_learned($pc, $k) ? '　［已学］' : '');
    }
    $out[] = '学：「学秘术 <名>」。施：「施术 <名> [目标]」。';
    $out[] = '（高阶两条要 B 级演习权限；递条子用完自降一级，一周后才回。）';
    return $out;
}

function crrg_miracle_learn(&$s, &$pc, $arg, $ctx = null) {
    $out = crrg_miracle_tick($pc);
    $k = crrg_miracle_find($arg);
    if ($k === '') return array_merge($out, ['没有这条术。打「秘术」看名录。']);
    $m = crrg_miracles()[$k];
    if (crrg_miracle_learned($pc, $k)) return array_merge($out, [sprintf('「%s」已经会了。', $m['n'])]);
    $need = crrg_miracle_need_lv($m['tier']);
    $my = function_exists('crrg_drill_eff_lv') ? crrg_drill_eff_lv($pc, $ctx) : (int) ($pc['clearance'] ?? 0);
    if ($my < $need) {
        return array_merge($out, [sprintf('%s 级才能学「%s」，你的演习权限是 %s 级。',
            crrg_drill_clearance_name($need), $m['n'], crrg_drill_clearance_name($my ?: 1))]);
    }
    $pc['skills'][crrg_miracle_key($k)] = 1;
    $out[] = sprintf('学了「%s」（%s·%s）。%s', $m['n'], $m['src'], $m['tier_n'], $m['d']);
    $out[] = '代价：' . $m['cost_n'] . '。';
    $out[] = '（学不扣理智，用才扣。）';
    if (function_exists('crrg_evt_emit')) {
        crrg_evt_emit('miracle.learn', ['k' => $k, 'uid' => (int) ($pc['uid'] ?? 0)], ['src' => 'miracles']);
    }
    return $out;
}

function crrg_miracle_cast(&$s, &$pc, $arg) {
    $out = crrg_miracle_tick($pc);
    $parts = preg_split('/\s+/u', trim((string) $arg), 2);
    $name = (string) ($parts[0] ?? '');
    $target = trim((string) ($parts[1] ?? ''));
    $k = crrg_miracle_find($name);
    if ($k === '') return array_merge($out, ['没有这条术。打「秘术」看名录。']);
    $m = crrg_miracles()[$k];
    if (!crrg_miracle_learned($pc, $k)) return array_merge($out, [sprintf('你不会「%s」。先「学秘术 %s」。', $m['n'], $m['n'])]);

    /* 这四条要先有目标，否则不扣代价（免得敲错一次就白掉理智） */
    if ($target === '' && in_array($k, ['wenzi', 'xuyi', 'kanmen', 'ditiaozi'], true)) {
        $eg = ['wenzi' => '施术 闻字 病历', 'xuyi' => '施术 续一口气 <人名>',
               'kanmen' => '施术 看门 <门>', 'ditiaozi' => '施术 递条子 <容器>'][$k];
        return array_merge($out, [sprintf('「%s」要有对象。例：%s。', $m['n'], $eg)]);
    }

    /* 代价先扣：CoC 口径，术成不成都要付 */
    $cost = (int) ($m['cost']['k'] === 'fix' ? $m['cost']['v'] : crrg_coc_dice($m['cost']['v']));
    $cost = min($cost, crrg_san_of($pc));       // 不够就扣到 0，不欠账
    if ($cost > 0) $out = array_merge($out, crrg_san_loss($pc, $cost, '秘术·' . $m['n']));
    if (crrg_san_of($pc) <= 0) {
        $out[] = '理智见底——术没成，人先散了。';
        return $out;
    }

    $h = 'crrg_miracle_h_' . $k;
    $res = function_exists($h) ? (array) $h($s, $pc, $target) : ['（这条术的实现还没挂上。）'];
    return array_merge($out, $res);
}

/* ================= 六条术的实现 ================= */

/** 内容表：纸页被写过几次。委员长填了才有剧情答案，空着只回机械结果。 */
function crrg_miracle_paper() {
    return [
        // '病历' => ['writes' => 2, 'erased' => 1, 'wet' => 0, 'note' => 'TODO(待委员长)：最后一次擦掉的是哪一行'],
        // '字条' => ['writes' => 1, 'erased' => 1, 'wet' => 1,  'note' => ''],
    ];
}

function crrg_miracle_h_wenzi(&$s, &$pc, $target) {
    if ($target === '') return ['要贴在哪页纸上？「施术 闻字 <东西>」。'];
    $tab = crrg_miracle_paper();
    $hit = '';
    foreach ($tab as $key => $row) if (mb_strpos($target, $key) !== false || mb_strpos($key, $target) !== false) { $hit = $key; break; }
    if ($hit === '') {
        /* 内容表没这一条 → 只回"听不出"，不编次数（要剧情答案请填 crrg_miracle_paper()） */
        return [sprintf('手贴上去，闭眼——「%s」听不出字。', $target)];
    }
    $r = $tab[$hit];
    if (!empty($r['wet'])) {
        return ['湿过又干——纸纤维记住了水，忘了笔。听不出。'];
    }
    $lines = [sprintf('「%s」：被写过 %d 次，最后一次%s。', $hit, (int) ($r['writes'] ?? 0), !empty($r['erased']) ? '被擦过' : '没擦')];
    $lines[] = '内容看不出。';
    if (!empty($r['note'])) $lines[] = $r['note'];
    return $lines;
}

function crrg_miracle_h_xuyi(&$s, &$pc, $target) {
    if ($target === '') return ['要给谁续？「施术 续一口气 <人名>」。'];
    $flags = (array) ($s['flags'] ?? []);
    $dead = false;
    foreach ($flags as $f) {
        $f = (string) $f;
        if (mb_strpos($f, 'dead_') === 0 || mb_strpos($f, '死了') !== false || mb_strpos($f, 'death') !== false) { $dead = true; break; }
    }
    if (!$dead) return [sprintf('这屋里没有刚死的人（%s 不在这条账上）。', $target)];
    $s['flags'][] = 'miracle_xuyi_' . $target;
    return [
        sprintf('你把手按上去。%s的胸口动了一下——他能说三句话。', $target),
        '（问什么他答什么，三句为限。）',
        /* TODO(待委员长)：三句台词未定 —— 已记旗标 miracle_xuyi_<人名>，文案定了往这里挂 */
        '说完你得亲手把他的眼睛合上，否则他会一直看着你。',
        '（他脸上会留一道很淡的印子，像哭过。）',
    ];
}

function crrg_miracle_h_jieshou(&$s, &$pc, $target) {
    $pc['miracle_borrow'] = ['at' => time(), 'rounds' => 1];
    return [
        '手心发烫，像有人从背后搭了一下你的手。',
        '（下一轮近身格斗 +1 奖励骰；只对同类有效。看谁都像要动手，别在这时候说话。）',
    ];
}

function crrg_miracle_h_fengsheng(&$s, &$pc, $target) {
    $room = (string) ($s['room'] ?? '');
    $now  = current_time('H:i');
    $f = get_option('crrg_drill_feed', []);
    $n = 0;
    if (is_array($f) && !empty($f['rooms'][$room])) {
        $keep = [];
        foreach ((array) $f['rooms'][$room] as $m) {
            // feed 只记到分钟（H:i），所以"三十秒"按"同一分钟"算 —— TODO(待委员长)：要精确到秒得先给 feed 加秒
            if ((string) ($m['t'] ?? '') === $now && $n < 6) { $n++; continue; }
            $keep[] = $m;
        }
        $f['rooms'][$room] = $keep;
        update_option('crrg_drill_feed', $f, false);
    }
    return [
        '你听见自己的声音断了。',
        $n > 0 ? sprintf('（现场记录里刚抹掉 %d 行；在场的人都不记得刚说过什么——包括你。）', $n)
               : '（这一分钟里没人说过话，抹了个空。）',
        '（纸上的记录不会消失。要收拾纸面，得另外动手。）',
    ];
}

/** 内容表：门那一头的三个画面。空着就回"黑"。 */
function crrg_miracle_gates() {
    return [
        // '铁门' => ['in' => 'TODO(待委员长)', 'out' => 'TODO(待委员长)', 'seal' => 'TODO(待委员长)'],
    ];
}

function crrg_miracle_h_kanmen(&$s, &$pc, $target) {
    if ($target === '') return ['要看哪扇门？「施术 看门 <门>」。'];
    $tab = crrg_miracle_gates();
    $hit = '';
    foreach ($tab as $key => $row) if (mb_strpos($target, $key) !== false || mb_strpos($key, $target) !== false) { $hit = $key; break; }
    if ($hit === '') {
        return [sprintf('「%s」那一头什么都没有。只有黑。', $target)];
    }
    $r = $tab[$hit];
    $lines = [sprintf('「%s」：', $hit),
              '　最后进去的：' . (string) ($r['in'] ?? '—'),
              '　最后出来的：' . (string) ($r['out'] ?? '—'),
              '　封的时辰：' . (string) ($r['seal'] ?? '—')];
    if (mb_strpos((string) ($r['in'] ?? ''), '你自己') !== false) $lines[] = '看到的是你自己——立刻停手。';
    $lines[] = '（看完有一阵分不清自己在门的哪一边。）';
    return $lines;
}

function crrg_miracle_h_ditiaozi(&$s, &$pc, $target) {
    if ($target === '') return ['要封进什么容器？「施术 递条子 <容器或镇物>」。'];
    $pc['miracle_seal'] = ['what' => $target, 'until' => time() + 86400];
    $old = (int) ($pc['clearance'] ?? 0);
    $pc['clearance'] = crrg_miracle_lv_down($old);
    $pc['miracle_restore'] = ['lv' => $old, 'at' => time() + 7 * 86400];
    return [
        sprintf('你把「%s」封进去了。封 24 小时。', $target),
        sprintf('演习权限：%s → %s（一周后回）。', crrg_drill_clearance_name($old ?: 1), crrg_drill_clearance_name($pc['clearance'] ?: 1)),
        '只能封，不能解。要解，得用封条本体。',
        '（容器若是人，那人之后一个月不能说话。）',
    ];
}

/* ================= 给战斗系统的钩子 ================= */

/**
 * 借手：取一次奖励骰。
 * 只对同类（人）有效 —— 由调用方保证目标是人形；用掉即失效。
 */
function crrg_miracle_borrow_bonus($uid) {
    $uid = (int) $uid;
    if ($uid <= 0) return 0;
    $pc = crrg_drill_pc($uid);
    $b = $pc['miracle_borrow'] ?? null;
    if (!is_array($b) || empty($b['rounds'])) return 0;
    if (time() - (int) ($b['at'] ?? 0) > 600) {   // 十分钟没用就散
        unset($pc['miracle_borrow']);
        crrg_drill_pc_save($uid, $pc);
        return 0;
    }
    unset($pc['miracle_borrow']);
    crrg_drill_pc_save($uid, $pc);
    return 1;
}
