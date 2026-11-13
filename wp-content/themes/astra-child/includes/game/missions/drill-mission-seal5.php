<?php
/**
 * 中型任务《封条》· 第 5 轮（Act III：封口与 12 种结局）
 *   工艺执行（稳妥／激进／放弃）→ 出货 → 贴封 → 结案
 *   12 种结局 = 路线（自造·稳妥／自造·激进／镇上·就地／特批）× 廊内结果（无／小／大）
 *   每种结局留两个 `hook_*` 回声旗标，并**实装两条回声小任务**作为可玩证明
 */
if (!defined('ABSPATH')) exit;
add_action('init', function () {
    /* 2026-10-14：《封条》已下线（委员长：整个不要了）——只关注册，函数与存档都留着 ✓
       恢复办法：把 missions/_retired.php 里的 crrg_retired_seal() 改成 return true ✓ */
    if (function_exists('crrg_retired_seal') && !crrg_retired_seal()) return;
    if (!function_exists('crrg_drill_register_mission')) return;
    crrg_drill_register_mission('m_mid_seal5', [
                'pass_rooms' => ['b2b_tech', 'b4_c2', 'b2b_hall'],
        'name' => '封条', 'level' => '中', 'kind' => 'base', 'min_lv' => 3, 'hidden' => true, 'chain_no' => 5, 'chain_total' => 5,
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
        'deadline_h' => 240,
        'prompt' => '只剩三件事：出货、贴上、结案。做法不同，结出来的东西也不同——报告要写明。',
        'steps' => [
            ['room' => 'b2b_tech',  'text' => '到技术研发室开工（按选定的工艺）'],
            ['flag' => 's5_出货',    'text' => '出货：拿到这一批封条'],
            ['flag' => 's5_貼封',    'text' => '把 C2 西头那扇门重新贴上'],
            ['flag' => 's5_结案',    'text' => '写结案报告'],
            ['room' => 'b2b_hall',  'text' => '回值班板归档'],
        ],
        'reward' => ['credits' => 16, 'money' => 700], 'rep' => ['base' => 3],
        'settle_note' => '结案。档案里有了完整的一份。',
    ]);
    /* 回声小任务（由结局旗标解锁） */
    crrg_drill_register_mission('m_hook_med', [
        'name' => '回声：体检（接触者随访）', 'level' => '低', 'kind' => 'base', 'min_lv' => 1, 'need_chain' => 3,
        'scene' => 'base', 'brief_room' => 'b1_med', 'brief_name' => '医疗室',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b1_med', 'min_rep' => [],
        'prompt' => '接触过配比的人都要查一遍血。白大夫把名单贴在门上。',
        'steps' => [['room' => 'b1_med', 'text' => '抽血、等结果'], ['room' => 'b1_med', 'text' => '拿报告']],
        'reward' => ['credits' => 3, 'money' => 0], 'rep' => ['base' => 1],
        'settle_note' => '结果出来了：**指标偏高，但不至于。**',
    ]);
    crrg_drill_register_mission('m_hook_after', [
        'name' => '回声：善后（那条廊）', 'level' => '低', 'kind' => 'base', 'min_lv' => 1, 'need_chain' => 5,
        'pass_rooms' => ['b4_buffer', 'b4_c2'],
        'scene' => 'base', 'brief_room' => 'b2b_hall', 'brief_name' => '值班板',
        'depart_room' => 'b2b_hall', 'entry_room' => 'b2b_hall',
        'prompt' => 'C2 那条廊封了以后，得有人进去把清单核对一遍——东西还在不在，缺哪一件。',
        'steps' => [['room' => 'b4_c2', 'text' => '进 C2 廊核清单'], ['room' => 'b2b_hall', 'text' => '回值班板交核清单']],
        'reward' => ['credits' => 4, 'money' => 150], 'rep' => ['base' => 2],
        'settle_note' => '清单核完了。**少了一件，登记为"未查明"。**',
    ]);
}, 27);

/* ══════════ 工艺执行 ══════════ */
function crrg_seal5_run(&$s, &$pc) {
    if (crrg_seal_has($s, 's5_出货')) return ['（已经出过货了。）'];
    /* 镇上路线：第 2 轮把配方留给了常守业，他那边直接供货（没有 craft_* 旗标） */
    if (!crrg_seal_has($s, 'craft_选择') && crrg_seal_has($s, 'paper_路线_就地')) {
        crrg_seal_set($s, 's5_出货'); crrg_seal_set($s, 'route_镇上');
        $pc['seal']['stock_days'] = (int) ($pc['seal']['stock_days'] ?? 9) + 6;
        return ['没等技术研发室，镇上的货先到了：**常守业托班车捎来一包封条**。',
                '纸比他以前做的厚一些，边上还留着他剪裁的痕迹。',
                '（出货 6 天量 ｜ 库存 +6 天 ｜ 路线：镇上。）'];
    }
    if (!crrg_seal_has($s, 'craft_选择')) return ['（还没选工艺——先去技术研发室交接材料、选一个方案。）'];
    $out = [];
    if (crrg_seal_has($s, 'craft_稳妥')) {
        crrg_seal_set($s, 's5_出货'); crrg_seal_set($s, 'route_自造稳妥');
        if (function_exists('crrg_seal_use')) { /* 出货回补库存 */ }
        $pc['seal']['stock_days'] = (int) ($pc['seal']['stock_days'] ?? 9) + 7;
        $out[] = '三天。韩雪没出过实验室，出来的时候手里是一叠裁好的封条，边角齐得像印刷的。';
        $out[] = '「合格。」她说，「就是有点慢。」';
        $out[] = '（出货 7 天量 ｜ 封条库存 +7 天 ｜ 韩雪这三天不能派别的活。）';
    } elseif (crrg_seal_has($s, 'craft_激进')) {
        crrg_seal_set($s, 's5_出货'); crrg_seal_set($s, 'route_自造激进');
        $pc['seal']['stock_days'] = (int) ($pc['seal']['stock_days'] ?? 9) + 5;
        $out[] = '一夜。周德海蹲在电炉边上，把铅箔一层层压进去，手上没戴手套。';
        $out[] = '「成了。」他把手往裤子上蹭了蹭。';
        $val = max(1, crrg_coc_skill_val($pc, '体质'));
        $r = crrg_coc_check('体质', $val);
        $out[] = '（接触者体质检定：' . $r['text'] . '）';
        if (crrg_coc_level_rank($r['level']) < 2) {
            crrg_seal_set($s, 'poison_玩家');
            $pc['hp'] = max(1, (int) $pc['hp'] - 1);
            $out[] = '当天夜里你开始头疼，牙龈发麻。（生命 −1 ｜ **你自己也进去了**。）';
        } else {
            $out[] = '你没事——但周德海的手抖得比昨天厉害。';
        }
        $out[] = '（出货 5 天量 ｜ 库存 +5 天。）';
    } elseif (crrg_seal_has($s, 'craft_放弃')) {
        if (!crrg_seal_has($s, 'hook_特批等待')) crrg_seal_set($s, 'hook_特批等待');
        crrg_seal_set($s, 's5_出货'); crrg_seal_set($s, 'route_特批');
        $pc['seal']['stock_days'] = (int) ($pc['seal']['stock_days'] ?? 9) + 10;
        $out[] = '特批下来了——比申请书上写的晚了三天。批件末尾一行小字：「今后同类申请走加急通道」。';
        $out[] = '（出货 10 天量 ｜ 库存 +10 天 ｜ **提案在档案里留下了**。）';
    } else {
        return ['（工艺状态不对——回技术研发室看看。）'];
    }
    return $out;
}

/* ══════════ 贴封 + 结局判定 ══════════ */
function crrg_seal5_close(&$s, &$pc, $force = false) {
    /* 2026-10-01 修（林晓雅卡在"贴封"✓）：守卫原来用 **`s5_结案`** 当闸 ✗
       —— 可「结案」还有另一个来源：**「结案校对」调查节点**（`seal-multi-logic` 的旗标表 ✓）也会设它 ✓
       → 玩家先做了结案校对，**贴封就永远被自己挡死** ✗（"先做第 4 步，第 3 步就没了"✓）
       现在改成看**它自己的产物** `s5_貼封` ✓（幂等 ✓），顺序怎么排都不会死锁 ✓ */
    /* 2026-10-09 修（真凶·「结案」不可达 ✓）：顶层幂等守卫要看的**是本函数自己的终态产物** `s5_结案` ✓
       —— 上一版写成 `if (has('s5_貼封')) return` ✗，与下面"贴封"那一支**共用同一个旗标** ✗
       → 贴封成功后，再进来**永远**在顶层被挡回 → 「写结案报告」恒回「（这扇门已经贴过了。）」
       → **12 种结局／奖励／归档全不可达** ✗（实测：出货→贴封→写结案报告，见 2026-10-09 变更日志） */
    if (crrg_seal_has($s, 's5_结案')) return ['（已经结案了。）'];
    if (!crrg_seal_has($s, 's5_出货')) return ['（先出货。）'];
    /* 廊内结果：由第 4 轮的事故档 + 本轮出货质量共同决定 */
    $acc = crrg_seal_has($s, 'acc_重') ? '大' : (crrg_seal_has($s, 'acc_中') ? '小' : '无');
    $handled = crrg_seal_has($s, 'acc_处理_补件') ? 1 : (crrg_seal_has($s, 'acc_处理_封闭') ? 2 : (crrg_seal_has($s, 'acc_处理_上报') ? 3 : 0));
    if (!crrg_seal_has($s, 's5_貼封')) {
        crrg_seal_set($s, 's5_貼封');
        $out = ['你带着新封条去了 C2。'];
        if ($acc === '大' && $handled === 2) {
            $out[] = '那条廊还封着——你只能把新封条贴在外侧的水泥墙上，标明"待清理"。';
            $out[] = '（廊内结果：**大** ｜ 门后的东西还在里面。）';
        } elseif ($acc === '小') {
            $out[] = '值班员的手还包着。你和他一起把门推平，贴上新的。';
            $out[] = '（廊内结果：**小** ｜ 手背那道疤会留。）';
        } else {
            $out[] = '门框擦干净、封条压平、四角按实。你退开半步看了一眼——平的。';
            $out[] = '（廊内结果：**无**。）';
        }
        return $out;
    }
    /* 结案：算 12 种结局 */
    $route = crrg_seal_has($s, 'route_自造稳妥') ? '自造稳妥' : (crrg_seal_has($s, 'route_自造激进') ? '自造激进'
           : (crrg_seal_has($s, 'route_镇上') ? '镇上' : (crrg_seal_has($s, 'route_特批') ? '特批' : '自造稳妥')));
    if (crrg_seal_has($s, 'paper_路线_就地') && !crrg_seal_has($s, 'route_自造稳妥') && !crrg_seal_has($s, 'route_自造激进')) $route = '镇上';
    $key = 'seal_end_' . $route . '_' . $acc;
    crrg_seal_set($s, $key); crrg_seal_set($s, 's5_结案');
    $T = [
        '自造稳妥' => ['没有意外。三天出一批合格的，贴上，登记，归档。',
                       '韩雪在报告上写了一句：「建议把配比入库，作为标准工艺。」'],
        '自造激进' => ['封条贴上了，但那一夜之后，参与的人都被列进了随访名单。',
                       '周德海在名单第一行。他在下面自己加了一句：「跟别人没关系。」'],
        '镇上'     => ['你把基地的做法与老匠人的做法都写进报告，附了替代材料方案。',
                       '常守业的名字第一次出现在基地档案里——身份栏写着"镇上手艺人（外协）"。'],
        '特批'     => ['加急通道在报告末尾被画了圈。第二次申请用了四天。',
                       '有人在报告上批了三个字：「可复制。」'],
    ][$route] ?? ['结案。'];
    $out = array_merge(['【结案】' . $route . ' · 廊内结果：' . $acc], $T);
    /* 回声钩子（每种结局各两条） */
    $hooks = [];
    if (in_array($route, ['自造激进'], true) || crrg_seal_has($s, 'poison_玩家')) $hooks[] = ['hook_体检线', '你和其他接触者都在随访名单上。'];
    if ($acc === '大' || crrg_seal_has($s, 'acc_处理_封闭'))                $hooks[] = ['hook_善后线', '那条廊还封着，清单没核过。'];
    if ($route === '镇上')                                     $hooks[] = ['hook_镇上人情', '镇上记着这份人情——也可能记着别的。'];
    if ($route === '特批')                                     $hooks[] = ['hook_制度线', '加急通道写进了制度。'];
    if ($acc === '无' && $route === '自造稳妥')                $hooks[] = ['hook_标准工艺', '你的配比可能被写进标准。'];
    foreach (array_slice($hooks, 0, 2) as [$f, $d]) { crrg_seal_set($s, $f); $out[] = '（回声：' . $d . '）'; }
    /* 奖励按档次 */
    $bonus = ['无' => 6, '小' => 3, '大' => 1][$acc] ?? 3;
    if ($route === '自造稳妥') $bonus += 2;
    $pc['credits'] = (int) ($pc['credits'] ?? 0) + $bonus;
    $pc['money']   = (int) ($pc['money'] ?? 0) + $bonus * 50;
    crrg_rep_add($pc, 'base', $acc === '无' ? 4 : ($acc === '小' ? 2 : 0), '《封条》结案：' . $route);
    $out[] = sprintf('（结案奖励：+%d 积分 ｜ +%d 元 ｜ 基地声望 %s）', $bonus, $bonus * 50,
        $acc === '无' ? '+4' : ($acc === '小' ? '+2' : '+0'));
    /* 归档 */
    if (function_exists('wp_insert_post') && !empty($pc['uid'])) {
        $pid = wp_insert_post(['post_title' => '【结案报告】封条 · ' . $route . '（廊内：' . $acc . '）',
            'post_content' => "结案要点：\n- 路线：{$route}\n- 廊内结果：{$acc}\n- 处置方式：" . ($handled ? ['', '补件', '封闭', '上报'][$handled] : '未处置') . "\n- 回声：\n" . implode("\n", array_map(fn($h) => '- ' . $h[1], $hooks)) . "\n",
            'post_status' => 'private', 'post_type' => 'post'], true);
        if (!is_wp_error($pid)) {
            update_post_meta($pid, 'crrg_report_type_name', '事件');
            update_post_meta($pid, 'crrg_access_level', 'operative');
            update_post_meta($pid, 'crrg_record_status', 'seal_close_report');
        }
    }
    return $out;
}
/** 面板 */
function crrg_seal5_stage_lines(&$s, $pc) {
    $f = (array) ($s['flags'] ?? []);
    $stock = (int) (function_exists('crrg_seal_inventory') ? crrg_seal_inventory($pc)['stock_days'] : 0);
    $route = '';
    foreach (['自造稳妥', '自造激进', '镇上', '特批'] as $r) if (in_array('route_' . $r, $f, true)) $route = $r;
    $acc = in_array('acc_重', $f, true) ? '大' : (in_array('acc_中', $f, true) ? '小' : '无');
    return [sprintf('第三幕 · 封口 ｜ 路线 %s ｜ 廊内 %s ｜ 出厂状态 %s ｜ 库存 %d 天',
        $route ?: '（未定）', $acc, in_array('s5_出货', $f, true) ? '已出货' : '未出货', $stock),
        '（开工（技术研发室）→ 出货 → 贴封（B4 C2）→ 结案（指挥大厅））'];
}
/** 12 种结局清单（自检/文档用） */
function crrg_seal5_endings() {
    $routes = ['自造稳妥', '自造激进', '镇上', '特批'];
    $accs = ['无', '小', '大'];
    $out = [];
    foreach ($routes as $r) foreach ($accs as $a) $out[] = 'seal_end_' . $r . '_' . $a;
    return $out;
}
