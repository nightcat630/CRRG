<?php
/**
 * 副本模组：白石县（2026-10-14，委员长："所有县城，而且要各具特色"）
 * ------------------------------------------------------------
 * 规模对标青石镇（43 间）✓ 但**气质不同**：
 *   青石镇 = 一个路口的小镇（镇口/供销社/面馆/老宅）
 *   白石县 = **农区的县城**：国道穿城一条主街、县机关排一溜、县医院和融媒体中心、
 *            小火车站（一天两趟）、农贸市场、老城巷子；**办事要跑三趟，谁都认识谁**
 *
 * 接入：本文件 + functions.php 一行 require ✓（照 mod-wild / mod-run 的骨架）
 * 落地：车旅到 `baishi` → 进 `bs_gate`（见 content/places-polish 旁的车旅落地表）
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    if (!function_exists('crrg_drill_register_scene')) return;

    crrg_drill_register_scene('county', [
        'name' => '白石县（县城）',
        'rooms' => [
            /* ── 进城 ── */
            'bs_gate'    => ['t' => '县城北口', 'f' => '县城', 'c' => 2,
                'd' => '国道在这里进城，路两边是卖瓜和修车的。县城不大，一条主街走到底。',
                'x' => ['主街·北段' => 'bs_main_n', '汽车站' => 'bs_bus']],
            'bs_main_n'  => ['t' => '主街·北段', 'f' => '县城', 'c' => 2,
                'd' => '沿街是五金、粮油、理发店，招牌都是统一尺寸的。',
                'x' => ['北口' => 'bs_gate', '十字口' => 'bs_main_c', '西街' => 'bs_main_w']],
            'bs_main_c'  => ['t' => '十字口', 'f' => '县城', 'c' => 2,
                'd' => '全县城最热闹的地方。四个角各一个摊子，卖烟、卖瓜、修鞋、看相。',
                'x' => ['主街·北' => 'bs_main_n', '主街·南' => 'bs_main_s', '东街' => 'bs_main_e', '县府广场' => 'bs_square']],
            'bs_main_s'  => ['t' => '主街·南段', 'f' => '县城', 'c' => 2,
                'd' => '往南出城是河。这一段的铺子关得早，卷闸门上都落了灰。',
                'x' => ['十字口' => 'bs_main_c', '河堤' => 'bs_river', '老城巷' => 'bs_alley_1']],
            'bs_main_w'  => ['t' => '西街', 'f' => '县城', 'c' => 2,
                'd' => '西街窄，两边是国营时期的老门面，玻璃柜台还是木框的。',
                'x' => ['主街·北' => 'bs_main_n', '农贸市场' => 'bs_market', '供销大楼' => 'bs_coop']],
            'bs_main_e'  => ['t' => '东街', 'f' => '县城', 'c' => 2,
                'd' => '东街新一些：邮电局、县农行、新华书店都在这边。',
                'x' => ['十字口' => 'bs_main_c', '邮电局' => 'bs_post', '县农行' => 'bs_bank', '新华书店' => 'bs_book']],
            /* ── 老城与河 ── */
            'bs_alley_1' => ['t' => '老城巷', 'f' => '县城', 'c' => 2,
                'd' => '巷子里的院子门都朝着巷开，门上贴着褪色的对联。',
                'x' => ['主街·南' => 'bs_main_s', '窄巷' => 'bs_alley_2', '家属院' => 'bs_homes']],
            'bs_alley_2' => ['t' => '窄巷', 'f' => '县城', 'c' => 2,
                'd' => '两个人并排走都要侧身。头顶是晾衣绳，滴水。',
                'x' => ['老城巷' => 'bs_alley_1', '胡同口' => 'bs_hutong']],
            'bs_hutong'  => ['t' => '胡同口', 'f' => '县城', 'c' => 2,
                'd' => '胡同口有口井，井台边坐着下棋的人，一坐一下午。',
                'x' => ['窄巷' => 'bs_alley_2', '文化馆' => 'bs_culture']],
            'bs_river'   => ['t' => '河堤', 'f' => '县城', 'c' => 2,
                'd' => '河水浅，堤上晒着玉米。下游有座漫水桥，涨水就断。',
                'x' => ['主街·南' => 'bs_main_s', '漫水桥' => 'bs_bridge']],
            'bs_bridge'  => ['t' => '漫水桥', 'f' => '县城', 'c' => 2,
                'd' => '低水位时能走车，桥面上常年一层薄水。',
                'x' => ['河堤' => 'bs_river']],
            /* ── 机关 ── */
            'bs_square'  => ['t' => '县府广场', 'f' => '县城', 'c' => 1,
                'd' => '广场中间一根旗杆，四周是各单位的大门，门牌号连着排。',
                'x' => ['十字口' => 'bs_main_c', '县政府' => 'bs_countyhall', '县公安局' => 'bs_publicsec', '县法院' => 'bs_court']],
            'bs_countyhall' => ['t' => '县政府', 'f' => '县城', 'c' => 1,
                'd' => '三层砖楼，走廊里挂着历任领导的照片。办事窗口在一楼右手第一间。',
                'x' => ['县府广场' => 'bs_square']],
            'bs_publicsec'  => ['t' => '县公安局', 'f' => '县城', 'c' => 1,
                'd' => '院里有辆停了很久的吉普。值班室的登记本压在玻璃板下面。',
                'x' => ['县府广场' => 'bs_square']],
            'bs_court'      => ['t' => '县法院', 'f' => '县城', 'c' => 1,
                'd' => '门口石狮子是新换的。公告栏里贴着开庭安排。',
                'x' => ['县府广场' => 'bs_square']],
            'bs_bureau'     => ['t' => '县农业局', 'f' => '县城', 'c' => 2,
                'd' => '墙上挂着全县的水利图，红笔画的几条改造线还没动工。',
                'x' => ['县府广场' => 'bs_square', '粮站' => 'bs_grain']],
            'bs_radio'      => ['t' => '县融媒体中心', 'f' => '县城', 'c' => 2,
                'd' => '二楼是采编点，灯常年亮着。走廊里堆着没归档的照片箱。',
                'x' => ['东街' => 'bs_main_e', '图书馆' => 'bs_library']],
            'bs_post'       => ['t' => '邮电局', 'f' => '县城', 'c' => 2,
                'd' => '柜台一半是寄信，一半是打电话。长途要排队登记。',
                'x' => ['东街' => 'bs_main_e', '银行' => 'bs_bank']],
            'bs_bank'       => ['t' => '县农行', 'f' => '县城', 'c' => 2,
                'd' => '铁栏杆后面点钞声不断。大堂里贴着一张手写的兑换牌价。',
                'x' => ['东街' => 'bs_main_e']],
            /* ── 医教 ── */
            'bs_hospital'   => ['t' => '县医院', 'f' => '县城', 'c' => 1,
                'd' => '三层楼，挂号在门口。住院部在里院，走廊里总有人在等。',
                'x' => ['主街·北' => 'bs_main_n', '卫生站' => 'bs_clinic']],
            'bs_clinic'     => ['t' => '卫生站', 'f' => '县城', 'c' => 2,
                'd' => '一间门面，打针输液都在这里。墙上贴着接种通知，日期没换。',
                'x' => ['县医院' => 'bs_hospital', '家属院' => 'bs_homes']],
            'bs_school'     => ['t' => '县一中', 'f' => '县城', 'c' => 2,
                'd' => '操场是土的，跑道用白灰画。上课时整条街都安静。',
                'x' => ['西街' => 'bs_main_w', '小学' => 'bs_primary']],
            'bs_primary'    => ['t' => '城关小学', 'f' => '县城', 'c' => 2,
                'd' => '院墙矮，墙头插着碎玻璃。放学时门口挤满自行车。',
                'x' => ['县一中' => 'bs_school']],
            'bs_library'    => ['t' => '县图书馆', 'f' => '县城', 'c' => 2,
                'd' => '阅览室有两排长条桌，报纸用夹子夹着，边角都起毛了。',
                'x' => ['融媒体中心' => 'bs_radio', '文化馆' => 'bs_culture']],
            'bs_culture'    => ['t' => '县文化馆', 'f' => '县城', 'c' => 2,
                'd' => '一楼展出本县的老照片，二楼有个活动室，平时锁着。',
                'x' => ['胡同口' => 'bs_hutong', '图书馆' => 'bs_library']],
            /* ── 交通 ── */
            'bs_bus'        => ['t' => '汽车站', 'f' => '县城', 'c' => 2,
                'd' => '一天四班车。候车室的椅子是水泥的，冬天没人敢坐。',
                'x' => ['北口' => 'bs_gate', '站前广场' => 'bs_station_sq']],
            'bs_train'      => ['t' => '白石站（小站）', 'f' => '县城', 'c' => 1,
                'd' => '一天两趟慢车，站台就一段。值班室门口挂着列车时刻表，用铅笔改过。',
                'x' => ['站前广场' => 'bs_station_sq']],
            'bs_station_sq' => ['t' => '站前广场', 'f' => '县城', 'c' => 2,
                'd' => '站前有几辆三轮，司机坐在车上打盹。广场角落有个报亭，只卖两种报。',
                'x' => ['汽车站' => 'bs_bus', '小火车站' => 'bs_train', '主街·北' => 'bs_main_n']],
            'bs_gas'        => ['t' => '加油站', 'f' => '县城', 'c' => 2,
                'd' => '一台加油机，一台停用。站长兼加油。',
                'x' => ['北口' => 'bs_gate', '修车铺' => 'bs_repair']],
            'bs_repair'     => ['t' => '修车铺', 'f' => '县城', 'c' => 2,
                'd' => '铺子门口永远躺着一辆拆开的车。师傅满手是油，边修边听收音机。',
                'x' => ['加油站' => 'bs_gas', '水泥厂' => 'bs_cement']],
            /* ── 商业 ── */
            'bs_market'     => ['t' => '农贸市场', 'f' => '县城', 'c' => 1,
                'd' => '早市最挤，卖菜的把秤杆压得很低。中午之后摊子撤一半。',
                'x' => ['西街' => 'bs_main_w', '供销大楼' => 'bs_coop']],
            'bs_coop'       => ['t' => '供销大楼', 'f' => '县城', 'c' => 1,
                'd' => '两层，一楼日用，二楼布匹。柜台是玻璃的，货摆得稀疏。',
                'x' => ['西街' => 'bs_main_w', '农贸市场' => 'bs_market']],
            'bs_noodle'     => ['t' => '面馆', 'f' => '县城', 'c' => 2,
                'd' => '一间门面，四张桌。老板下面的时候不说话。',
                'x' => ['十字口' => 'bs_main_c', '饭馆' => 'bs_rest']],
            'bs_rest'       => ['t' => '饭馆', 'f' => '县城', 'c' => 2,
                'd' => '门口挂着白布帘，里头油烟大。墙上菜单只用粉笔写三样。',
                'x' => ['面馆' => 'bs_noodle', '县招待所' => 'bs_inn']],
            'bs_inn'        => ['t' => '县招待所', 'f' => '县城', 'c' => 1,
                'd' => '两层，前台只有一个人。登记本要从上往下翻，最新的在上面。',
                'x' => ['饭馆' => 'bs_rest', '主街·南' => 'bs_main_s']],
            'bs_bath'       => ['t' => '澡堂', 'f' => '县城', 'c' => 2,
                'd' => '锅炉下午四点开始烧。池子里的水一天换一次。',
                'x' => ['西街' => 'bs_main_w']],
            'bs_barber'     => ['t' => '理发店', 'f' => '县城', 'c' => 2,
                'd' => '转椅是老式的铁家伙。镜子边夹着几张旧报纸。',
                'x' => ['主街·北' => 'bs_main_n']],
            'bs_pharmacy'   => ['t' => '药店', 'f' => '县城', 'c' => 2,
                'd' => '柜台后面坐着一位老药师，处方药要看条子。',
                'x' => ['十字口' => 'bs_main_c', '县医院' => 'bs_hospital']],
            'bs_book'       => ['t' => '新华书店', 'f' => '县城', 'c' => 2,
                'd' => '书架靠墙一圈，中间是玻璃展柜。教辅占了一半。',
                'x' => ['东街' => 'bs_main_e']],
            'bs_photo'      => ['t' => '照相馆', 'f' => '县城', 'c' => 2,
                'd' => '橱窗里摆着样板照，背景布换了三种。老板兼冲印。',
                'x' => ['东街' => 'bs_main_e', '主街·南' => 'bs_main_s']],
            'bs_hardware'   => ['t' => '五金店', 'f' => '县城', 'c' => 2,
                'd' => '从钉子到水泵都有，老板找货靠记性。',
                'x' => ['主街·北' => 'bs_main_n', '粮站' => 'bs_grain']],
            'bs_grain'      => ['t' => '粮站', 'f' => '县城', 'c' => 2,
                'd' => '粮仓外墙上刷着"严禁烟火"。院里的磅秤比镇上的大一号。',
                'x' => ['五金店' => 'bs_hardware', '仓库' => 'bs_warehouse']],
            'bs_junk'       => ['t' => '废品站', 'f' => '县城', 'c' => 2,
                'd' => '纸壳、酒瓶、旧铁分三堆。老板按堆估价，从不称。',
                'x' => ['河堤' => 'bs_river', '仓库' => 'bs_warehouse']],
            /* ── 工业与其他 ── */
            'bs_cement'     => ['t' => '县水泥厂', 'f' => '县城', 'c' => 2,
                'd' => '厂门口挂着"安全生产"的红横幅，颜色已经淡了。',
                'x' => ['修车铺' => 'bs_repair', '仓库' => 'bs_warehouse']],
            'bs_warehouse'  => ['t' => '城关仓库区', 'f' => '县城', 'c' => 2,
                'd' => '几排仓库，编号刷在门上。最里侧那间的门是新换的。',
                'x' => ['粮站' => 'bs_grain', '水泥厂' => 'bs_cement', '废品站' => 'bs_junk']],
            'bs_homes'      => ['t' => '家属院', 'f' => '县城', 'c' => 2,
                'd' => '四栋筒子楼围出一个院，晾衣绳从这栋拉到那栋。',
                'x' => ['老城巷' => 'bs_alley_1', '卫生站' => 'bs_clinic']],
        ],
        'pos' => [
            'bs_gate' => [95, 5], 'bs_main_n' => [95, 25], 'bs_main_c' => [95, 45], 'bs_main_s' => [95, 65],
            'bs_main_w' => [70, 25], 'bs_main_e' => [120, 25], 'bs_square' => [120, 45],
            'bs_alley_1' => [75, 75], 'bs_alley_2' => [60, 85], 'bs_hutong' => [55, 95],
            'bs_river' => [95, 85], 'bs_bridge' => [95, 100], 'bs_homes' => [65, 70],
        ],
        'ents' => [
            'bs_countyhall' => [['n' => '办事窗口', 'd' => '窗口玻璃上贴着一张"今日不办"的纸，纸是旧的。']],
            'bs_train'      => [['n' => '时刻表', 'd' => '时刻表用铅笔改过两次，最后一趟的时间被划掉却没写新的。']],
            'bs_inn'        => [['n' => '登记本', 'd' => '最新一页上有一个名字是重复出现的，日期不同。']],
            'bs_radio'      => [['n' => '照片箱', 'd' => '箱子侧面写着年份，最上面那箱是空的。']],
            'bs_market'     => [['n' => '公平秤', 'd' => '公平秤上的纸条写着"如发现缺斤短两，请举报"，下面的电话被涂了。']],
            'bs_warehouse'  => [['n' => '最里侧的仓库门', 'd' => '门是新换的，门牌号却比旁边的旧一号。']],
        ],
    ], ['name' => '白石县（县城）']);
});

/** 车旅落地：`baishi` → 县城北口 ✓（**普通函数**，由 crrg_travel_open_nodes() 合并 ✓
 *   —— 游戏里没有 add_filter 钩子 ✗ 我今天已经栽过一次 ✓） */
function crrg_game_travel_landing() {
    return [
        'baishi'  => ['room' => 'bs_gate', 'scene' => 'county'],   // 白石县 ✓
        'ganzhou' => ['room' => 'gz_gate', 'scene' => 'gz_city'],  // 甘州 ✓（另一座城，别一个模子 ✗）
    ];
}

/** 县城的店（**各有特色**：农资/土产/粮油，与镇上那些不同 ✓） */

/** **县城的店**（房间 → 店 ✓ 由 content/shops.php 合并 ✓；货品由上面 register_shop 定义 ✓） */
function crrg_game_shops_extra() {
    return [
        'bs_coop_shop' => ['n' => '白石供销大楼', 'room' => 'bs_coop', 'cats' => ['白石供销'],
            'keep' => '一楼玻璃柜台，售货员在织毛衣。乡下人赶集都上这儿。'],
        'bs_grain_shop' => ['n' => '县粮站门市', 'room' => 'bs_grain', 'cats' => ['白石粮油'],
            'keep' => '粮站门口开的小窗口，按票供应，现金也行。'],
        'bs_pharm_shop' => ['n' => '县药店', 'room' => 'bs_pharmacy', 'cats' => ['县药店'],
            'keep' => '柜台后面坐着老药师，处方药得看条子。'],
    ];
}

/** **县城的人**（并入名册 ✓ 由 content/npcs.php 合并 ✓） */
function crrg_game_npc_roster_extra() {
    return [
        'bs_clerk'      => ['n' => '窗口办事员', 'alias' => ['办事员'], 'role' => '县府办事窗口',
                            'sched' => ['黎明' => 'bs_countyhall', '上午' => 'bs_countyhall', '正午' => 'bs_countyhall',
                                        '下午' => 'bs_countyhall', '黄昏' => 'bs_noodle', '夜' => 'bs_homes', '深夜' => 'bs_homes']],
        'bs_vendor'     => ['n' => '卖菜的', 'alias' => ['摊主'], 'role' => '农贸市场摊主',
                            'sched' => ['黎明' => 'bs_market', '上午' => 'bs_market', '正午' => 'bs_market',
                                        '下午' => 'bs_main_w', '黄昏' => 'bs_homes', '夜' => 'bs_homes', '深夜' => 'bs_homes']],
        'bs_innkeeper'  => ['n' => '招待所前台', 'alias' => ['前台'], 'role' => '县招待所',
                            'sched' => ['黎明' => 'bs_inn', '上午' => 'bs_inn', '正午' => 'bs_noodle',
                                        '下午' => 'bs_inn', '黄昏' => 'bs_inn', '夜' => 'bs_inn', '深夜' => 'bs_inn']],
        'bs_stationman' => ['n' => '车站值班员', 'alias' => ['值班员'], 'role' => '白石站值班',
                            'sched' => ['黎明' => 'bs_train', '上午' => 'bs_train', '正午' => 'bs_station_sq',
                                        '下午' => 'bs_train', '黄昏' => 'bs_train', '夜' => 'bs_homes', '深夜' => 'bs_homes']],
        'bs_reporter'   => ['n' => '采编员', 'alias' => ['记者'], 'role' => '融媒体中心',
                            'sched' => ['黎明' => 'bs_homes', '上午' => 'bs_radio', '正午' => 'bs_rest',
                                        '下午' => 'bs_radio', '黄昏' => 'bs_radio', '夜' => 'bs_radio', '深夜' => 'bs_homes']],
    ];
}

/**
 * **出口对称化**（2026-10-14，门禁逮到"从入口走不到 17 间房"✗）
 * 街道本来就该是**双向**的：A 能到 B，B 也能回 A ✓
 * 只对我这两座城生效 ✓ 只**补**缺的反向出口 ✗ 不删任何东西 ✓
 */
function crrg_city_symmetrize($sceneId) {
    $R = &crrg_drill_reg();
    if (!isset($R['scenes'][$sceneId]['rooms'])) return 0;
    $rooms = &$R['scenes'][$sceneId]['rooms'];
    $added = 0;
    foreach ($rooms as $rk => $rd) {
        foreach ((array) ($rd['x'] ?? []) as $label => $dest) {
            if (!isset($rooms[$dest])) continue;
            $back = (string) ($rooms[$rk]['t'] ?? $rk);
            $have = false;
            foreach ((array) ($rooms[$dest]['x'] ?? []) as $l2 => $d2) if ($d2 === $rk) { $have = true; break; }
            if ($have) continue;
            $rooms[$dest]['x'][$back] = $rk;      // 补反向出口（标签用对方的名字 ✓）
            $added++;
        }
    }
    return $added;
}

add_action('init', function () {
    if (!function_exists('crrg_city_symmetrize')) return;
    crrg_city_symmetrize('county');
    crrg_city_symmetrize('gz_city');
}, 40);

/** **县城的货品**（合并进 crrg_game_goods_data ✓ 与商店条目里的 cats 同名 ✓） */
function crrg_game_goods_extra() {
    if (!function_exists('crrg_game_goods_extra_gz')) {
        // 甘州模块后加载，做个兜底 ✓
    }
    return [
        '白石供销' => [
        'bs_f1' => ['n' => '化肥 一袋',   'p' => 60, 'cur' => 'money', 'item' => 'food_salt', 'd' => '农资柜，按袋卖。'],
        'bs_f2' => ['n' => '搪瓷盆',     'p' => 25, 'cur' => 'money', 'item' => 'tool_mug'],
        'bs_f3' => ['n' => '胶靴',       'p' => 70, 'cur' => 'money', 'item' => 'gear_boots'],
        'bs_f4' => ['n' => '暖水瓶',     'p' => 35, 'cur' => 'money', 'item' => 'tool_thermos'],
        'bs_f5' => ['n' => '手电筒',     'p' => 80, 'cur' => 'money', 'item' => 'tool_flashlight'],
        'bs_f6' => ['n' => '电池 两节',  'p' => 25, 'cur' => 'money', 'item' => 'tool_battery'],
        ],
        '白石粮油' => [
        'bs_g1' => ['n' => '挂面 1kg',    'p' => 22, 'cur' => 'money', 'item' => 'food_noodle_dry'],
        'bs_g2' => ['n' => '菜籽油 5L',  'p' => 70, 'cur' => 'money', 'item' => 'food_oil'],
        'bs_g3' => ['n' => '大米 10kg',   'p' => 55, 'cur' => 'money', 'item' => 'food_rice'],
        'bs_g4' => ['n' => '面粉 5kg',    'p' => 32, 'cur' => 'money', 'item' => 'food_flour'],
        'bs_g5' => ['n' => '陈醋',        'p' => 8,  'cur' => 'money', 'item' => 'food_vinegar'],
        ],
        '县药店' => [
        'bs_m1' => ['n' => '感冒药 一板', 'p' => 12, 'cur' => 'money', 'item' => 'med_cold'],
        'bs_m2' => ['n' => '退烧药',      'p' => 10, 'cur' => 'money', 'item' => 'med_fever'],
        'bs_m3' => ['n' => '碘伏',        'p' => 9,  'cur' => 'money', 'item' => 'med_iodine'],
        'bs_m4' => ['n' => '绷带',        'p' => 10, 'cur' => 'money', 'item' => 'med_bandage'],
        'bs_m5' => ['n' => '安眠药',      'p' => 18, 'cur' => 'money', 'item' => 'med_sleep'],
        ],
    ];
}

/** 两座城的货品合并（县 + 甘州） */
function crrg_game_goods_all_extra() {
    $a = (array) crrg_game_goods_extra();
    if (function_exists('crrg_game_goods_extra_gz')) $a = array_merge($a, (array) crrg_game_goods_extra_gz());
    return $a;
}

/** 把县城房间也登记进**全局房间表**（NPC/门禁/查房都要用 ✓ 场景表只是布局 ✓） */
add_action('init', function () {
    if (!function_exists('crrg_game_register_many')) return;
    $R = &crrg_drill_reg();
    $rooms = (array) ($R['scenes']['county']['rooms'] ?? []);
    if ($rooms) crrg_game_register_many('room', $rooms, ['module' => 'county', 'file' => __FILE__]);
}, 45);
