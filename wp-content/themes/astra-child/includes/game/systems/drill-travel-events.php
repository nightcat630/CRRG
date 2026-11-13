<?php
/**
 * 在途随机事件（2026-09-28 · 第一批 20 个 / 计划 120）
 * 触发：出发时**按里程加权**（8% – 40%，见 crrg_travel_event_roll）；在途面板出现选项，或用「事件 <序号>」选择。
 * 数据格式（便于扩到 120 个）：
 *   ['n'=>标题, 'w'=>权重, 't'=>[描写…],
 *    'o'=>[['l'=>选项文字, 'say'=>[结果文本…], 'e'=>[效果…]], …]]
 * 效果键：energy / food / water / money / hp / san / fuel / wear / time(游戏小时) /
 *        item(id=>件数) / flag / rep(loc=>增减) / roll([['p'=>百分, 'say'=>…, 'e'=>…], …])
 */
if (!defined('ABSPATH')) exit;

function crrg_travel_event_events() {
    return [
        'lift' => ['n' => '路边有人招手', 'w' => 10,
            't' => ['前方路肩上站着一个人，穿旧夹克，手里拎着帆布包，冲你摆手。',
                    '风把他身后的空旷吹得很明显——这段路上，很久没有别的车了。'],
            'o' => [
                ['l' => '捎他一段', 'say' => ['他上车，把包放在脚边，说了声谢。一路上话不多。',
                    '临下车他忽然说：「往前走三十公里，那个加油点别去。他们往油里兑东西。」'],
                 'e' => ['energy' => -2, 'flag' => '线索_加油点有诈']],
                ['l' => '不停，直接开过去', 'say' => ['他没喊，只是站着看你走远。后视镜里，他一直站在原地。'],
                 'e' => ['energy' => 1]],
            ]],
        'flat' => ['n' => '爆胎', 'w' => 9,
            't' => ['一声闷响，方向猛地往右一沉——右后轮瘪了。'],
            'o' => [
                ['l' => '自己换（花两小时）', 'say' => ['你卸下备胎，手上全是黑泥。'], 'e' => ['time' => 2, 'energy' => -14, 'wear' => -2]],
                ['l' => '硬开到前面镇子', 'say' => ['轮毂磨出一圈亮痕，轮胎彻底废了。'], 'e' => ['time' => 0.5, 'wear' => 12, 'money' => -120]],
            ]],
        'checkpoint' => ['n' => '临时检查站', 'w' => 8,
            't' => ['路上横着两道水泥墩，两个穿反光背心的人抬手让你停。',
                    '一个查证件，另一个绕到车后，正弯腰看你的后厢。'],
            'o' => [
                ['l' => '配合，递上证件', 'say' => ['两人对着证件看了很久，最后摆手让你走。'], 'e' => ['time' => 0.4]],
                ['l' => '给点「辛苦费」', 'say' => ['钱递过去，人立刻和气了，还给你指了条近路。'], 'e' => ['money' => -50, 'time' => -0.3, 'rep' => ['town_qingshi' => -1]]],
                ['l' => '掉头绕路', 'say' => ['你倒了半公里才调过头，多绕了一条乡道。'], 'e' => ['time' => 1.5, 'fuel' => -2, 'energy' => -4]],
            ]],
        'deer' => ['n' => '路中间的东西', 'w' => 8,
            't' => ['车灯扫过去，路中间站着一只鹿——不躲，也不动，只是看着灯光。',
                    '它站的位置太正了，像有人把它放在那儿。'],
            'o' => [
                ['l' => '减速绕过去', 'say' => ['你慢慢挪过它身边。它的眼睛跟着你转，身体没动。'], 'e' => ['energy' => -1]],
                ['l' => '按喇叭轰它', 'say' => ['喇叭响，它才像醒过来一样跳进黑暗里。', '你自己的心跳也快了半拍。'], 'e' => ['san' => -1]],
                ['l' => '停车看清楚', 'say' => ['你下车，灯光里那头鹿并没有蹄声。地上有一串印子——是蹄，但比鹿的大。'],
                 'e' => ['san' => -2, 'flag' => '线索_大蹄印']],
            ]],
        'rain' => ['n' => '前挡一片雨幕', 'w' => 9,
            't' => ['雨来得急，雨刮开到最快也只是一层白。', '前面二十米以外什么都看不出来。'],
            'o' => [
                ['l' => '靠边等雨小', 'say' => ['你把车停进路肩，听着雨砸车顶，睡了一觉。'], 'e' => ['time' => 1.2, 'energy' => 6]],
                ['l' => '慢速硬走', 'say' => ['你把速度压到二十码，一路盯着白线。'], 'e' => ['energy' => -6, 'time' => 0.6]],
            ]],
        'fuel_station' => ['n' => '路边加油点', 'w' => 7,
            't' => ['路边一个用水泥砌的加油点，招牌只有一个「油」字。',
                    '屋里的人不看你，只把油枪提起来，等你说数。'],
            'o' => [
                ['l' => '加满', 'say' => ['油枪跳了三次才停。'], 'e' => ['fuel' => 99, 'money' => -160]],
                ['l' => '只加一点，先走', 'say' => ['他不太情愿，还是给了你二十升。'], 'e' => ['fuel' => 20, 'money' => -45]],
                ['l' => '不放心，继续开', 'say' => ['你开出去两公里，才想起那人一直没抬头。'], 'e' => ['flag' => '线索_加油点可疑']],
            ]],
        'salesman' => ['n' => '卖山货的老人', 'w' => 7,
            't' => ['一个老人在路边摆了块布，上面是蘑菇、干菜和几把晒干的草药。',
                    '他说话很慢，问你往哪个方向去。'],
            'o' => [
                ['l' => '买点干粮（10 元）', 'say' => ['干菜沉手，够吃两天。'], 'e' => ['money' => -10, 'item' => ['food_ration' => 2]]],
                ['l' => '问他前路', 'say' => ['「往前二十里，有个老陵。」他说，「车别停那儿。」'], 'e' => ['flag' => '线索_路上老陵']],
                ['l' => '摆手走了', 'say' => ['他在你车后喊了句什么，风大，没听清。'], 'e' => []],
            ]],
        'box_on_road' => ['n' => '路边的行李', 'w' => 5,
            't' => ['护栏外放着一只旅行箱，拉杆还立着，箱子上有雨痕。', '四下没有人，也没有车辙。'],
            'o' => [
                ['l' => '打开看看', 'say' => ['箱子里只有几件旧衣服，和一本写满数字的笔记本。', '最后一个数字后面画了个圈。'],
                 'e' => ['san' => -1, 'item' => ['notepad' => 1], 'flag' => '线索_数字本']],
                ['l' => '别碰，走', 'say' => ['你绕开它。开出很远，才想起箱子的拉杆是干的——那会儿没下雨。'], 'e' => ['san' => -1]],
            ]],
        'fog' => ['n' => '雾里有人影', 'w' => 6,
            't' => ['雾从谷底漫上来，能见度不到三十米。', '雾里有个人形，站在路的右侧，一动不动。'],
            'o' => [
                ['l' => '停下，开双闪', 'say' => ['你停在那里等雾散。那个人形也没有动。', '雾薄下去的时候，路边是空的。'], 'e' => ['time' => 1, 'san' => -1]],
                ['l' => '加速穿过去', 'say' => ['你一脚油门冲过去。后视镜里什么都没有。', '但你记得，刚才那东西是朝车头方向站的。'], 'e' => ['san' => -2, 'energy' => -4, 'wear' => 2]],
                ['l' => '下车走近看', 'say' => ['你走过去，那里只有一根冻硬的木桩，顶上套着一件旧棉袄。',
                    '棉袄的袖子里塞着纸，纸上写着一个人的名字。'], 'e' => ['san' => -3, 'flag' => '线索_雾里的名字', 'energy' => -3]],
            ]],
        'detour' => ['n' => '前方施工', 'w' => 8,
            't' => ['路被三脚架和反光锥封了半幅，牌子写着「前路施工，绕行」。', '牌子是新的，可周围一个人都没有。'],
            'o' => [
                ['l' => '按绕行牌走', 'say' => ['绕行是一条土路，颠得人牙齿发麻。'], 'e' => ['time' => 1.6, 'energy' => -8, 'wear' => 6]],
                ['l' => '挪开锥桶，直穿', 'say' => ['你把锥桶搬到一边，从缺口穿了过去。', '开出两公里，路面突然断开——你差点冲进去。'], 'e' => ['time' => 0.4, 'wear' => 4, 'hp' => -1, 'san' => -1]],
            ]],
        'patrol' => ['n' => '巡逻车', 'w' => 6,
            't' => ['后视镜里闪了两下灯：一辆巡逻车跟在你后面，没鸣笛。'],
            'o' => [
                ['l' => '靠边停，等它过', 'say' => ['它慢下来和你并行了几秒，然后加速走了。'], 'e' => ['time' => 0.2]],
                ['l' => '照常开', 'say' => ['它跟了二十分钟，在一个岔口拐走了。'], 'e' => ['energy' => -3]],
            ]],
        'tool_radio' => ['n' => '电台里的话', 'w' => 6,
            't' => ['电台沙沙地转了个台，只剩一片噪音；噪音里像是有人念数字。', '你听了半天，只听清一个数：七。'],
            'o' => [
                ['l' => '记下来', 'say' => ['你在本子上写下「七」，又画了个问号。'], 'e' => ['flag' => '线索_电台七']],
                ['l' => '关掉电台', 'say' => ['车里安静下来，只剩发动机的声音。'], 'e' => ['energy' => 2]],
            ]],
        'mech' => ['n' => '水温高', 'w' => 7,
            't' => ['水温表慢慢往上爬，机盖缝里冒起白汽。'],
            'o' => [
                ['l' => '停车等凉（一小时）', 'say' => ['你把车停进阴凉处，等水温落回去，顺便拧紧了水管卡子。'], 'e' => ['time' => 1, 'wear' => -4]],
                ['l' => '开暖风散热，继续走', 'say' => ['水温勉强压住，但一路上你都在看表。'], 'e' => ['wear' => 8, 'energy' => -4]],
            ]],
        'dog' => ['n' => '跟着车的狗', 'w' => 6,
            't' => ['一只瘦狗从路肩追上来，跟着车跑，跑了很久都不停。'],
            'o' => [
                ['l' => '停下喂点东西', 'say' => ['它吃了半块干粮，摇了下尾巴，掉头往回走了。'], 'e' => ['item' => ['food_ration' => -1], 'energy' => 3]],
                ['l' => '不管它', 'say' => ['后视镜里，它跑不动了，慢慢停下，坐在路中间看你。'], 'e' => ['san' => -1]],
            ]],
        'paperman' => ['n' => '路边的纸人', 'w' => 4,
            't' => ['路边插着一个纸糊的人，白脸黑线眼睛，身上写着字。', '风一过，它整条手臂都在抖——纸做的，当然会抖。'],
            'o' => [
                ['l' => '停车看清它身上写的字', 'say' => ['字是墨写的，写的是一个人的生辰。', '你再看第二眼，最后一个数字被雨水化开了。'], 'e' => ['san' => -3, 'flag' => '线索_纸人']],
                ['l' => '踩油门过去', 'say' => ['车开过去时，纸人的头轻轻转了一下。你不敢再看后视镜。'], 'e' => ['san' => -1, 'energy' => -2]],
            ]],
        'shortcut' => ['n' => '岔路', 'w' => 7,
            't' => ['主路向左绕远，右边一条土路插进山坳。', '地图上那条土路，比主路短十几公里。'],
            'o' => [
                ['l' => '走土路抄近道', 'say' => ['土路越走越窄，最后是一段搓板路。'], 'e' => ['time' => -0.8, 'wear' => 10, 'fuel' => -1.5]],
                ['l' => '老实走主路', 'say' => ['主路绕是绕，路面平。'], 'e' => ['time' => 0.5, 'wear' => -1]],
            ]],
        'herd' => ['n' => '过路的羊群', 'w' => 7,
            't' => ['一群羊把路堵了，赶羊的人在很后面，隔着三四十米。'],
            'o' => [
                ['l' => '等羊过去', 'say' => ['你熄了火，看羊一只只从车头前过。赶羊人朝你点点头。'], 'e' => ['time' => 0.8, 'energy' => 2, 'rep' => ['town_qingshi' => 1]]],
                ['l' => '按喇叭催', 'say' => ['羊群乱了两分钟才散。赶羊人骂了一句。'], 'e' => ['time' => 0.2, 'rep' => ['town_qingshi' => -2]]],
            ]],
        'stuck_truck' => ['n' => '翻在路边的卡车', 'w' => 6,
            't' => ['一辆卡车歪在路肩，车厢空着，司机蹲在旁边抽烟。', '他抬头看你一眼，又低头。'],
            'o' => [
                ['l' => '停下来帮忙', 'say' => ['你帮着把车斗里的绳子重新系好。他塞给你两瓶水，什么也没说。'], 'e' => ['time' => 1.2, 'energy' => -6, 'item' => ['drink_mineral' => 2], 'rep' => ['town_qingshi' => 1]]],
                ['l' => '只问一句就走', 'say' => ['「往南？路好，别在黑水镇停。」他把烟头摁在鞋底。'], 'e' => ['flag' => '线索_黑水镇']],
            ]],
        'meter' => ['n' => '里程表跳了一下', 'w' => 4,
            't' => ['里程表自己跳了一格，接着又跳回来。', '油表也动了一下——往上的方向。'],
            'o' => [
                ['l' => '停车检查', 'say' => ['你把车停稳，掀开机盖。一切正常。', '回到驾驶位时，里程表比出发时少了两公里。'], 'e' => ['san' => -2, 'time' => 0.3]],
                ['l' => '当没看见', 'say' => ['你继续开。油表指针稳稳地指在比刚才更高的位置。'], 'e' => ['fuel' => 5, 'san' => -1]],
            ]],
        'gift' => ['n' => '路边的热水', 'w' => 6,
            't' => ['路边放着一个保温壶和一只搪瓷缸，壶上压着张纸条：「路远，喝口热的。」', '壶是温的。'],
            'o' => [
                ['l' => '喝一杯', 'say' => ['水是甜的，喝下去整个人都暖了。'], 'e' => ['tool_flask' => 15, 'energy' => 6]],
                ['l' => '不喝，但把壶带上', 'say' => ['你把壶拎上车。开出十几公里，才想起那张纸条上的字像是刚写的。'], 'e' => ['item' => ['flask' => 1], 'san' => -1]],
                ['l' => '不动它', 'say' => ['你绕开它继续走。后视镜里，那只壶还在路边。'], 'e' => []],
            ]],
    ];
}
/** 效果应用（数据驱动；可扩 120 个） */
function crrg_travel_event_apply(&$pc, &$s, $eff, &$out) {
    foreach ((array) $eff as $k => $v) {
        switch ($k) {
            case 'energy': case 'food': case 'tool_flask':
                $pc['vitals'][$k] = max(0, min(100, (float) ($pc['vitals'][$k] ?? 0) + (float) $v));
                break;
            case 'hp':   $pc['hp'] = max(0, (int) $pc['hp'] + (int) $v); break;
            case 'san':  $pc['san'] = max(0, (int) $pc['san'] + (int) $v); $out[] = sprintf('（理智 %+d）', (int) $v); break;
            case 'money': $pc['money'] = max(0, (int) $pc['money'] + (int) $v); $out[] = sprintf('（钱 %+d 元）', (int) $v); break;
            case 'time':  if (!empty($pc['travel'])) { $pc['travel']['arrive'] = (int) $pc['travel']['arrive'] + (int) round($v * 3600); $out[] = sprintf('（行程 %+.1f 小时）', $v); } break;
            case 'wear':
                if (function_exists('crrg_travel_veh')) { $m = crrg_travel_mode($pc);
                    if (crrg_travel_profile($m)['fuel'] > 0) { $vv = crrg_travel_veh($pc, $m); $vv['wear'] = max(0, min(100, (float) $vv['wear'] + (float) $v)); crrg_travel_save_veh($pc, $m, $vv); $out[] = sprintf('（车况 %+.0f%%）', $v); } }
                break;
            case 'fuel':
                if (function_exists('crrg_travel_veh')) { $m = crrg_travel_mode($pc);
                    if (crrg_travel_profile($m)['fuel'] > 0) { $vv = crrg_travel_veh($pc, $m); $vv['fuel'] = max(0, min((float) crrg_travel_profile($m)['tank'], (float) $vv['fuel'] + (float) $v)); crrg_travel_save_veh($pc, $m, $vv); $out[] = sprintf('（油 %+.1f 升）', $v); } }
                break;
            case 'item':
                foreach ((array) $v as $id => $n) {
                    if ($n > 0) crrg_drill_bag_add_n($pc, $id, (int) $n);
                    else        crrg_drill_bag_del_n($pc, $id, abs((int) $n));
                    $out[] = sprintf('（%s %+d）', crrg_drill_item_name($id), (int) $n);
                }
                break;
            /* 2026-10-14（P1.9a）：**在途事件可以直接喂任务线索** ✓（同对话那套）
               任务上下文从行程里取（`$pc['travel']['ctx']['task']`）——所以"路上发生的事"
               天然可以成为任务线索的来源 ✓ 写法：['clue' => '线索键'] 或 ['clue' => ['任务id','线索键']] */
            case 'clue':
                $tk = $v; $task = (string) ($pc['travel']['ctx']['task'] ?? '');
                if (is_array($tk)) { $task = (string) ($tk[0] ?? $task); $tk = (string) ($tk[1] ?? ''); }
                if ($task !== '' && $tk !== '' && function_exists('crrg_clue_gain') && crrg_clue_gain($s, $pc, $task, (string) $tk)) {
                    $d = (array) (crrg_task($task)['clues'][$tk] ?? []);
                    $out[] = '〔线索〕' . ($d['title'] ?? $tk) . '　' . (string) ($d['point'] ?? '');
                }
                break;
            case 'flag':
                if (!is_array($s['flags'] ?? null)) $s['flags'] = [];
                if (!in_array($v, $s['flags'], true)) $s['flags'][] = $v;
                break;
            case 'rep':
                if (function_exists('crrg_rep_add')) foreach ((array) $v as $loc => $dv) { crrg_rep_add($pc, $loc, (int) $dv, '在途事件'); $out[] = sprintf('（%s声望 %+d）', crrg_rep_places()[$loc]['short'] ?? $loc, (int) $dv); }
                break;
            case 'roll':                                  // 概率分支
                $r = mt_rand(1, 100); $acc = 0; $hit = null;
                foreach ((array) $v as $br) { $acc += (int) ($br['p'] ?? 0); if ($r <= $acc) { $hit = $br; break; } }
                if ($hit) { foreach ((array) ($hit['say'] ?? []) as $x) $out[] = $x; crrg_travel_event_apply($pc, $s, (array) ($hit['e'] ?? []), $out); }
                break;
        }
    }
}
/** 抽一个事件（加权，10% 触发） */
function crrg_travel_event_roll(&$pc) {
    /* 2026-10-09（委员长批）：触发率**按里程加权** ✓ —— 短途≈旧行为（~10%），长途明显更容易出事；
       封顶 40%（一次行程最多抽一个）。不动地图拓扑，只改"赶路感" ✓ */
    $km = (int) ($pc['travel']['km'] ?? 0);
    $p  = (int) round(8 + $km * 0.22);
    if ($p < 8) $p = 8;
    if ($p > 40) $p = 40;
    if (mt_rand(1, 100) > $p) return null;
    $all = crrg_travel_event_events(); $total = 0;
    foreach ($all as $e) $total += (int) $e['w'];
    $r = mt_rand(1, max(1, $total)); $acc = 0;
    foreach ($all as $id => $e) { $acc += (int) $e['w']; if ($r <= $acc) return $id; }
    return null;
}
function crrg_travel_event_show($id) {
    $all = crrg_travel_event_events();
    if (!isset($all[$id])) return ['（没有这个事件。）'];
    $e = $all[$id];
    $out = ['〔在途事件 · ' . $e['n'] . '〕'];
    foreach ($e['t'] as $x) $out[] = $x;
    $i = 1; foreach ($e['o'] as $o) $out[] = sprintf('　%d）%s', $i++, $o['l']);
    return $out;
}
function crrg_travel_event_choose(&$pc, &$s, $id, $opt) {
    $all = crrg_travel_event_events();
    if (!isset($all[$id])) return ['（事件不存在。）'];
    $idx = max(0, (int) $opt - 1);
    if (!isset($all[$id]['o'][$idx])) return ['（没有这个选项。）'];
    $o = $all[$id]['o'][$idx];
    $out = ['〔选择〕' . $o['l']];
    foreach ((array) ($o['say'] ?? []) as $x) $out[] = $x;
    crrg_travel_event_apply($pc, $s, (array) ($o['e'] ?? []), $out);
    if (!empty($pc['travel'])) unset($pc['travel']['ev']);
    return $out;
}
