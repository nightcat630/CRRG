<?php
/**
 * 大地图（2026-09-28）
 * 以基地为原点，周边 20 村 / 10 镇 / 5 山区 / 1 大城市，含方位、直线与路程距离、路线与状态。
 * 输出为**分行表格**（不用字符画，避免中文宽度对不齐）。
 * 数据结构供后续模组取用：crrg_worldmap_nodes() / crrg_worldmap_roads() / crrg_worldmap_node($id)
 */
if (!defined('ABSPATH')) exit;

/** 原点：祁连山南麓总基地 */
function crrg_worldmap_base() {
    return ['n' => '青山基地（祁连山南麓总基地）', 'x' => 0, 'y' => 0, 'note' => '主体在山体内；坐标对外为近似点位（38.62, 99.85）。'];
}
/**
 * 节点表
 *   t：村 / 镇 / 县城 / 山区 / 大城市
 *   dir：八方位 ｜ km：直线 ｜ road_km：实际路程 ｜ via：路网编号 ｜ st：已通 / 部分通 / 规划
 *   x,y：以基地为原点的公里网格（东为 +x，北为 +y）
 */
function crrg_worldmap_nodes() {
    /* 坐标不再手填：由「方位角 brg（0=北，90=东）」与「直线里程 km」算出——
     * 于是所有点都落在放射干线上，路网不会互相交叉。 */
    $raw = [
        /* ── 北干线（brg 0） ── */
        'shangying'  => ['n'=>'上营子',   't'=>'村',  'brg'=>0,   'km'=>11, 'road_km'=>14, 'via'=>'X-04 山道',      'st'=>'已通',  'd'=>'老营盘改的村子，院墙都是夯土。信号几乎没有。'],
        'xiaying'    => ['n'=>'下营子',   't'=>'村',  'brg'=>0,   'km'=>12, 'road_km'=>15, 'via'=>'X-04 山道',      'st'=>'已通',  'd'=>'上营子的兄弟村，井水偏咸。'],
        'heisongling'=> ['n'=>'黑松岭',   't'=>'山区','brg'=>0,   'km'=>15, 'road_km'=>19, 'via'=>'林道 L-01',      'st'=>'已通',  'd'=>'北面第一道山脊，松林密，冬天雪深。'],
        'hanliugou'  => ['n'=>'旱柳沟',   't'=>'村',  'brg'=>0,   'km'=>37, 'road_km'=>50, 'via'=>'X-10 山道',      'st'=>'规划',  'd'=>'沟里只有旱柳，一年到头缺水。'],
        'heisongyi'  => ['n'=>'黑松驿镇', 't'=>'镇',  'brg'=>0,   'km'=>66, 'road_km'=>86, 'via'=>'G-05 三级公路',  'st'=>'规划',  'd'=>'旧驿站，现在是岔路口镇，北出山口。'],
        /* ── 东南干线（brg 45） ── */
        'lijiaao'    => ['n'=>'李家坳',   't'=>'村',  'brg'=>45,  'km'=>6,  'road_km'=>8,  'via'=>'X-02 乡道',      'st'=>'已通',  'd'=>'三十来户，靠一口井吃水。有养路队借住的空房。'],
        'jinger'     => ['n'=>'井儿沟',   't'=>'村',  'brg'=>45,  'km'=>10, 'road_km'=>13, 'via'=>'X-02 支线',      'st'=>'已通',  'd'=>'沟底一口深井，井绳换了三代。'],
        'liushugou'  => ['n'=>'柳树沟',   't'=>'村',  'brg'=>45,  'km'=>13, 'road_km'=>17, 'via'=>'X-02 支线',      'st'=>'已通',  'd'=>'沟里全是柳树，秋天落叶堵水渠。'],
        'bingouliang'=> ['n'=>'冰沟梁',   't'=>'山区','brg'=>45,  'km'=>27, 'road_km'=>35, 'via'=>'林道 L-03',      'st'=>'部分通','d'=>'背阴沟里六月还结冰，早年有钨矿的废洞。'],
        'liuhe'      => ['n'=>'柳河镇',   't'=>'镇',  'brg'=>45,  'km'=>32, 'road_km'=>41, 'via'=>'G-02 三级公路',  'st'=>'部分通','d'=>'镇上有粮站和车队，往东北方向的歇脚点。'],
        'sandaogou'  => ['n'=>'三道沟镇', 't'=>'镇',  'brg'=>45,  'km'=>58, 'road_km'=>74, 'via'=>'G-02 支线',      'st'=>'规划',  'd'=>'三条沟汇合处，有煤场与运输队。'],
        /* ── 正东支线（brg 90） ── */
        'danghewan'  => ['n'=>'党河湾',   't'=>'村',  'brg'=>90,  'km'=>7,  'road_km'=>10, 'via'=>'X-02 支线',      'st'=>'已通',  'd'=>'河湾地，夏天有人捞鱼。村东有座小水磨。'],
        'daquanwan'  => ['n'=>'大泉湾',   't'=>'村',  'brg'=>90,  'km'=>31, 'road_km'=>39, 'via'=>'X-08 乡道',      'st'=>'规划',  'd'=>'规划中的观察前置点，目前只有牧道。'],
        'hongliuwan' => ['n'=>'红柳湾',   't'=>'村',  'brg'=>90,  'km'=>42, 'road_km'=>55, 'via'=>'X-08 乡道',      'st'=>'规划',  'd'=>'红柳墩成片，东线最后的补给点。'],
        /* ── 南出山干线（brg 180）：基地唯一的主通道 ── */
        'shiqiao'    => ['n'=>'石桥村',   't'=>'村',  'brg'=>180, 'km'=>9,  'road_km'=>12, 'via'=>'G-01 三级公路',  'st'=>'已通',  'd'=>'村口一座清代石桥，桥面被车压出两道槽。'],
        'cahanliang' => ['n'=>'擦汗梁',   't'=>'村',  'brg'=>180, 'km'=>14, 'road_km'=>18, 'via'=>'X-03 支线',      'st'=>'已通',  'd'=>'梁上风大，赶车的人到这都要停下擦把汗。'],
        'qingshi'    => ['n'=>'青石镇',   't'=>'镇',  'brg'=>180, 'km'=>18, 'road_km'=>22, 'via'=>'G-01 三级公路',  'st'=>'已通',  'd'=>'已开放。南巷李家老宅、后山公墓、老陵与地下通道都在这一带。'],
        'sanchakou'  => ['n'=>'三岔口',   't'=>'村',  'brg'=>180, 'km'=>21, 'road_km'=>27, 'via'=>'X-03 乡道',      'st'=>'已通',  'd'=>'三条路交汇，路口有间开了二十年的小卖部。'],
        'hewan'      => ['n'=>'河湾镇',   't'=>'镇',  'brg'=>180, 'km'=>34, 'road_km'=>45, 'via'=>'G-03 三级公路',  'st'=>'规划',  'd'=>'临大河，有渡口与货场，是南下的路口。'],
        'baishi'     => ['n'=>'白石县',   't'=>'县城','brg'=>180, 'km'=>44, 'road_km'=>56, 'via'=>'G-01 三级公路',  'st'=>'已通',  'd'=>'县融媒体中心采编点在此。追书人一案的照片、旧报都从这儿出来。'],
        'shiquan'    => ['n'=>'石泉镇',   't'=>'镇',  'brg'=>180, 'km'=>55, 'road_km'=>70, 'via'=>'G-03 三级公路',  'st'=>'规划',  'd'=>'镇中心一口石砌泉，赶集日很挤。'],
        'ganzhou'    => ['n'=>'甘州（河西大城）','t'=>'大城市','brg'=>180,'km'=>118,'road_km'=>140,'via'=>'S-01 省道','st'=>'部分通','d'=>'地区首府：省立医院、大学、铁路编组站、军分区都在这里。后续大型模组的主场。'],
        /* ── 西南（brg 225） ── */
        'quanerwan'  => ['n'=>'泉儿湾',   't'=>'村',  'brg'=>225, 'km'=>20, 'road_km'=>26, 'via'=>'X-03 乡道',      'st'=>'已通',  'd'=>'一口常年不冻的泉，镇上人冬天来拉水。'],
        'pandao'     => ['n'=>'盘道村',   't'=>'村',  'brg'=>225, 'km'=>35, 'road_km'=>48, 'via'=>'X-09 山道',      'st'=>'规划',  'd'=>'盘山十七道弯，村里人靠驮队。'],
        'yeniu'      => ['n'=>'野牛岭',   't'=>'山区','brg'=>225, 'km'=>52, 'road_km'=>68, 'via'=>'林道 L-05',      'st'=>'规划',  'd'=>'传说早年有野牛群。岭上有一处测绘点。'],
        'nuanquan'   => ['n'=>'暖泉镇',   't'=>'镇',  'brg'=>225, 'km'=>61, 'road_km'=>79, 'via'=>'G-04 三级公路',  'st'=>'规划',  'd'=>'有温泉，镇上有两间招待所。'],
        /* ── 西（brg 270） ── */
        'shimenzi'   => ['n'=>'石门子',   't'=>'村',  'brg'=>270, 'km'=>22, 'road_km'=>30, 'via'=>'X-06 山道',      'st'=>'部分通','d'=>'两块巨石夹出的路口，雨季常塌方。'],
        'shuimogou'  => ['n'=>'水磨沟',   't'=>'村',  'brg'=>270, 'km'=>33, 'road_km'=>44, 'via'=>'X-06 山道',      'st'=>'规划',  'd'=>'沟里有三座废弃水磨坊。'],
        'shagou'     => ['n'=>'沙沟村',   't'=>'村',  'brg'=>270, 'km'=>48, 'road_km'=>62, 'via'=>'X-06 支线',      'st'=>'规划',  'd'=>'风口上的村子，家家窗户糊两层纸。'],
        /* ── 西北（brg 315） ── */
        'baiyanggou' => ['n'=>'白杨沟',   't'=>'村',  'brg'=>315, 'km'=>19, 'road_km'=>24, 'via'=>'X-05 乡道',      'st'=>'已通',  'd'=>'沟口一片白杨，风大时整条沟都在响。'],
        'yingzuiya'  => ['n'=>'鹰嘴崖',   't'=>'山区','brg'=>315, 'km'=>24, 'road_km'=>31, 'via'=>'林道 L-02',      'st'=>'部分通','d'=>'崖下有条季节性河，崖顶是老鹰窝。'],
        'laojuntai'  => ['n'=>'老君台',   't'=>'村',  'brg'=>315, 'km'=>45, 'road_km'=>58, 'via'=>'X-11 山道',      'st'=>'规划',  'd'=>'台地上有座旧庙，庙里的钟还在。'],
        'dabanshan'  => ['n'=>'大坂山',   't'=>'山区','brg'=>315, 'km'=>38, 'road_km'=>49, 'via'=>'林道 L-04',      'st'=>'规划',  'd'=>'西面屏障，翻过去是另一条流域。'],
        'heishui'    => ['n'=>'黑水镇',   't'=>'镇',  'brg'=>315, 'km'=>47, 'road_km'=>61, 'via'=>'G-05 支线',      'st'=>'规划',  'd'=>'镇边有条发黑的河，据说是矿水染的。'],
        /* ── 高速服务区（不算"村/镇"，属路网设施） ── */
        'sa_s22_qingshi' => ['n' => '青石服务区', 't' => '服务区', 'brg'=>180, 'km'=>28, 'road_km'=>34, 'via'=>'S22 河西绕城高速', 'st'=>'已通',
            'd' => '高速旁的加油站＋便利店，停车位很多，凌晨也亮着灯。'],
        'sa_g30_baishi'  => ['n' => '白石服务区', 't' => '服务区', 'brg'=>180, 'km'=>62, 'road_km'=>78, 'via'=>'G30 连霍高速', 'st'=>'已通',
            'd' => '大货车停了一排。有热水、有便利店，还有一间通宵开的面馆。'],
        'sa_g30_heisongyi' => ['n' => '黑松驿服务区', 't' => '服务区', 'brg'=>0, 'km'=>52, 'road_km'=>66, 'via'=>'G30 连霍高速', 'st'=>'部分通',
            'd' => '北线服务区，风大，招牌被吹掉一角。加油机两台，一台停用。'],
        'sa_s22_liuhe'   => ['n' => '柳河服务区', 't' => '服务区', 'brg'=>45, 'km'=>24, 'road_km'=>30, 'via'=>'S22 河西绕城高速', 'st'=>'部分通',
            'd' => '小服务区，只有便利亭和两排停车位，后面是个缓坡。'],
        'sa_g0611_nuanquan' => ['n' => '暖泉服务区', 't' => '服务区', 'brg'=>225, 'km'=>48, 'road_km'=>62, 'via'=>'G0611 张汶高速', 'st'=>'规划',
            'd' => '规划中的服务区，现在只有加油机和一间活动板房。'],
        'sa_g30_ganzhou' => ['n' => '甘州北服务区', 't' => '服务区', 'brg'=>180, 'km'=>104, 'road_km'=>124, 'via'=>'G30 连霍高速', 'st'=>'已通',
            'd' => '进城前最后一个服务区，麦当劳的灯亮着，停车场里什么人都有。'],

        /* ── 东南环（brg 135 一带） ── */
        'huanghuatan'=> ['n'=>'黄花滩',   't'=>'村',  'brg'=>135, 'km'=>29, 'road_km'=>36, 'via'=>'X-07 乡道',      'st'=>'已通',  'd'=>'滩上黄花连片，也是风蚀最重的地方。'],
        'shuangta'   => ['n'=>'双塔镇',   't'=>'镇',  'brg'=>135, 'km'=>36, 'road_km'=>46, 'via'=>'G-02 支线',      'st'=>'规划',  'd'=>'镇口两座土塔，据说是旧烽燧。'],
        'xindian'    => ['n'=>'新店镇',   't'=>'镇',  'brg'=>135, 'km'=>52, 'road_km'=>66, 'via'=>'G-03 支线',      'st'=>'规划',  'd'=>'新开的镇子，有供销社与修配厂。'],
    ];
    $dirs = [0=>'北', 45=>'东北', 90=>'东', 135=>'东南', 180=>'南', 225=>'西南', 270=>'西', 315=>'西北'];
    $out = [];
    foreach ($raw as $id => $r) {
        $brg = (int) $r['brg']; $km = (int) $r['km'];
        $rad = deg2rad($brg);
        $r['x'] = round($km * sin($rad), 1);       // 东为 +x
        $r['y'] = round($km * cos($rad), 1);       // 北为 +y
        $r['dir'] = $dirs[$brg] ?? '—';
        $out[$id] = $r;
    }
        /* 2026-10-14：**周边地点（只追加）＋ 开放表** —— 由 content/places-around.php 提供 ✓ */
    /* 2026-10-14：**现有地点补描述**（`add` 追加 ✓ 原文一字不动 ✓ 委员长要的"完善"） */
    if (function_exists('crrg_game_worldmap_polish')) {
        foreach ((array) crrg_game_worldmap_polish() as $__id => $__add) {
            if (isset($out[$__id]) && is_string($__add) && $__add !== '') {
                $out[$__id]['d'] = rtrim((string) $out[$__id]['d']) . ' ' . $__add;
            }
        }
    }
    if (function_exists('crrg_game_worldmap_extra')) {
        $__ex = (array) crrg_game_worldmap_extra();
        $__open = function_exists('crrg_game_worldmap_open') ? (array) crrg_game_worldmap_open() : [];
        foreach ($__ex as $__id => $__d) $out[$__id] = $__d;
        foreach ($__open as $__id => $__st) if (isset($out[$__id])) $out[$__id]['st'] = $__st;
    }
return $out;
}
/** 路网：以「路径」表示（折线），干线沿放射轴、环线沿外圈相邻节点，几何上不交叉 */
function crrg_worldmap_roads() {
    return [
        /* 放射干线（自基地路口向外，同一条轴） */
        ['id'=>'G-01', 'lv'=>'三级公路', 'km'=>22,  'st'=>'已通',   'name'=>'南出山干线（基地—青石镇）',
         'path'=>['base', 'shiqiao', 'qingshi'], 'd'=>'唯一的主通道，出山后接县道。'],
        ['id'=>'G-01', 'lv'=>'三级公路', 'km'=>34,  'st'=>'已通',   'name'=>'青石镇—白石县',
         'path'=>['qingshi', 'baishi'], 'd'=>'沿同一条轴向南，路况比出山段好。'],
        ['id'=>'S-01', 'lv'=>'省道',     'km'=>84,  'st'=>'部分通', 'name'=>'白石县—甘州',
         'path'=>['baishi', 'ganzhou'], 'd'=>'往大城的最后一段；末段在修。'],
        ['id'=>'G-02', 'lv'=>'三级公路', 'km'=>41,  'st'=>'部分通', 'name'=>'东北干线（基地—柳河镇）',
         'path'=>['base', 'lijiaao', 'liushugou', 'bingouliang', 'liuhe'], 'd'=>'东北方向的主干；冰沟梁以南尚在修。'],
        ['id'=>'G-02', 'lv'=>'三级公路', 'km'=>33,  'st'=>'规划',   'name'=>'柳河镇—三道沟镇',
         'path'=>['liuhe', 'sandaogou'], 'd'=>'东北方向延长线。'],
        ['id'=>'G-05', 'lv'=>'三级公路', 'km'=>86,  'st'=>'规划',   'name'=>'北干线（基地—黑松驿镇）',
         'path'=>['base', 'shangying', 'xiaying', 'hanliugou', 'heisongyi'], 'd'=>'北出山口，翻大坂山北麓。'],
        ['id'=>'X-06', 'lv'=>'山道',     'km'=>62,  'st'=>'部分通', 'name'=>'西干线（基地—沙沟村）',
         'path'=>['base', 'shimenzi', 'shuimogou', 'shagou'], 'd'=>'沿西面谷地；石门子雨季常塌方。'],
        ['id'=>'X-04', 'lv'=>'山道',     'km'=>19,  'st'=>'已通',   'name'=>'北支线（基地—黑松岭）',
         'path'=>['base', 'heisongling'], 'd'=>'巡山与野外观察用的林道。'],
        ['id'=>'X-02', 'lv'=>'乡道',     'km'=>7,   'st'=>'已通',   'name'=>'东支线（基地—党河湾）',
         'path'=>['base', 'danghewan'], 'd'=>'短支线，通往河湾地。'],
        ['id'=>'X-08', 'lv'=>'乡道',     'km'=>42,  'st'=>'规划',   'name'=>'东支线延长（党河湾—红柳湾）',
         'path'=>['danghewan', 'daquanwan', 'hongliuwan'], 'd'=>'东面长支线，中途只有牧道。'],
        ['id'=>'X-05', 'lv'=>'乡道',     'km'=>24,  'st'=>'已通',   'name'=>'西北支线（基地—白杨沟）',
         'path'=>['base', 'baiyanggou', 'yingzuiya'], 'd'=>'通往沟口与崖下。'],
        ['id'=>'X-11', 'lv'=>'山道',     'km'=>58,  'st'=>'规划',   'name'=>'西北延长（鹰嘴崖—老君台）',
         'path'=>['yingzuiya', 'laojuntai'], 'd'=>'沿山脊西侧北上。'],
        ['id'=>'L-04', 'lv'=>'林道',     'km'=>49,  'st'=>'规划',   'name'=>'大坂山林道（白杨沟—大坂山）',
         'path'=>['baiyanggou', 'dabanshan'], 'd'=>'翻越大坂山的备用线。'],
        /* 高速（三条直通干线；徒步与自行车禁入） */
        ['id'=>'G30',  'lv'=>'高速', 'km'=>150, 'st'=>'部分通', 'name'=>'G30 连霍高速（河西段，东西向）',
         'path'=>['heisongyi','sa_g30_heisongyi','sa_g30_baishi','sa_g30_ganzhou','ganzhou'],
         'd'=>'河西走廊的主干高速：北接黑松驿出入口，南到甘州北。有互通可上下。'],
        ['id'=>'S22',  'lv'=>'高速', 'km'=>78,  'st'=>'部分通', 'name'=>'S22 河西绕城高速（南环联络）',
         'path'=>['baishi','sa_s22_qingshi','qingshi','sa_s22_liuhe','liuhe'],
         'd'=>'把白石县、青石镇、柳河镇串成一条环线，绕过镇区走。'],
        ['id'=>'G0611','lv'=>'高速', 'km'=>120, 'st'=>'规划',  'name'=>'G0611 张汶高速（西南支线）',
         'path'=>['ganzhou','sa_g0611_nuanquan','nuanquan','heishui'],
         'd'=>'西南方向支线，规划中；通车后去暖泉、黑水不用再走乡道。'],

        /* 环线：把外圈城镇串起来，避免任何一条路横穿基地 */
        ['id'=>'R-1',  'lv'=>'乡道环线', 'km'=>96,  'st'=>'部分通', 'name'=>'东南环线',
         'path'=>['qingshi', 'huanghuatan', 'shuangta', 'xindian', 'shiquan', 'hewan', 'qingshi'], 'd'=>'把南出山干线以东的村镇串成一圈。'],
        ['id'=>'R-2',  'lv'=>'三级公路环线', 'km'=>112, 'st'=>'规划', 'name'=>'北环线',
         'path'=>['liuhe', 'sandaogou', 'heisongyi', 'heishui', 'laojuntai', 'shimenzi', 'qingshi'], 'd'=>'东北—北—西的外圈联络线，规划中。'],
        ['id'=>'R-3',  'lv'=>'乡道环线', 'km'=>74,  'st'=>'规划', 'name'=>'西南环线',
         'path'=>['qingshi', 'quanerwan', 'pandao', 'nuanquan', 'yeniu', 'shimenzi'], 'd'=>'西南山区与镇子的联络圈。'],
    ];
}
function crrg_worldmap_node($id) {
    $n = crrg_worldmap_nodes();
    return $n[$id] ?? null;
}
/** 「大地图」指令输出 */
function crrg_worldmap_lines($arg = '') {
    $arg = trim((string) $arg);
    $nodes = crrg_worldmap_nodes();
    $roads = crrg_worldmap_roads();
    /* 单点明细 */
    if ($arg !== '') {
        $hit = '';
        foreach ($nodes as $id => $n) if (mb_strpos($n['n'], $arg) !== false || mb_strpos($arg, $n['n']) !== false || $arg === $id) { $hit = $id; break; }
        if ($hit === '') return ['图上没有叫「' . $arg . '」的地方。'];
        $n = $nodes[$hit];
        $out = [sprintf('〔%s〕%s ｜ 距基地 直线 %d km ／ 路程 %d km', $n['n'], $n['t'], $n['km'], $n['road_km'])];
        $out[] = '　方位：基地的' . $n['dir'] . '方 ｜ 路网：' . $n['via'] . '（' . $n['st'] . '）';
        $out[] = '　' . $n['d'];
        $links = [];
        foreach ($roads as $r) {
            if (!in_array($hit, (array) ($r['path'] ?? []), true)) continue;
            $links[] = sprintf('　· %s %s（%s，%d km，%s）', $r['id'], crrg_worldmap_road_name($r), $r['lv'], $r['km'], $r['st']);
        }
        if ($links) { $out[] = '　连通路线：'; foreach ($links as $l) $out[] = $l; }
        return $out;
    }
    /* 总览 */
    $base = crrg_worldmap_base();
    $out = ['〔大地图 · 以基地为原点〕'];
    $out[] = '　' . $base['n'] . '——' . $base['note'];
    $bands = [
        ['近郊',   5,  15],
        ['中圈',  16,  30],
        ['中远',  31,  50],
        ['远郊',  51,  90],
        ['外线',  91, 200],
    ];
    foreach ($bands as [$bn, $lo, $hi]) {
        $row = [];
        foreach ($nodes as $id => $n) if ($n['km'] >= $lo && $n['km'] <= $hi) $row[] = $n;
        if (!$row) continue;
        usort($row, fn($a, $b) => $a['km'] <=> $b['km']);
        $out[] = sprintf('── %s（%d–%d km，%d 处）──', $bn, $lo, $hi, count($row));
        foreach ($row as $n) {
            $out[] = sprintf('　%s（%s）%s ｜ 直线 %d ／ 路程 %d km ｜ %s ｜ %s',
                $n['n'], $n['t'], $n['dir'], $n['km'], $n['road_km'], $n['via'], $n['st']);
        }
    }
    $out[] = '── 路网 ──';
    foreach ($roads as $r) {
        $out[] = sprintf('　%s %s（%s，%d km，%s）', $r['id'], crrg_worldmap_road_name($r), $r['lv'], $r['km'], $r['st']);
    }
    $cnt = ['村' => 0, '镇' => 0, '县城' => 0, '山区' => 0, '大城市' => 0];
    foreach ($nodes as $n) if (isset($cnt[$n['t']])) $cnt[$n['t']]++;
    $out[] = sprintf('── 合计 ── 村 %d ｜ 镇 %d ｜ 县城 %d ｜ 山区 %d ｜ 大城市 %d（共 %d 处）',
        $cnt['村'], $cnt['镇'], $cnt['县城'], $cnt['山区'], $cnt['大城市'], count($nodes));
    $out[] = '（看某一处：「大地图 青石镇」；只说地方名也行。）';
    return $out;
}

/* ================= 矢量地图（SVG）=================
 * 按公里网格绘制：基地为原点，北向上、东向右。等距圆环 + 路网 + 分类节点标记。
 * 供页面内嵌显示（不做字符画，也不会因中文宽度错位）。
 */
function crrg_worldmap_scale() { return 4.8; }                 // 像素／公里
function crrg_worldmap_viewbox() { return [1240, 980]; }
/** 端点解析：支持节点 id、节点名、"base" */
function crrg_worldmap_point($key) {
    if ($key === 'base' || $key === '基地') return ['x' => 0, 'y' => 0, 'n' => '青山基地', 'id' => 'base'];
    $nodes = crrg_worldmap_nodes();
    if (isset($nodes[$key])) { $n = $nodes[$key]; return ['x' => $n['x'], 'y' => $n['y'], 'n' => $n['n'], 'id' => $key]; }
    foreach ($nodes as $id => $n) {
        if ($n['n'] === $key || mb_strpos($n['n'], $key) !== false || mb_strpos($key, $n['n']) !== false) {
            return ['x' => $n['x'], 'y' => $n['y'], 'n' => $n['n'], 'id' => $id];
        }
    }
    return null;
}
/** 一条路的折线坐标 */
function crrg_worldmap_path_points($road) {
    $pts = [];
    foreach ((array) ($road['path'] ?? []) as $k) { $pp = crrg_worldmap_point($k); if ($pp) $pts[] = $pp; }
    return $pts;
}
/** 路名（兼容旧 a/b 字段） */
function crrg_worldmap_road_name($r) {
    if (!empty($r['name'])) return $r['name'];
    return ($r['a'] ?? '?') . '—' . ($r['b'] ?? '?');
}
function crrg_worldmap_svg() {
    $S = crrg_worldmap_scale();
    /* 画布按数据范围计算——不再写死尺寸，最南的甘州（118 km）也不会被裁掉 */
    $nodes = crrg_worldmap_nodes();
    $mx = 8; $my = 8;
    foreach ($nodes as $n) { $mx = max($mx, abs($n['x'])); $my = max($my, abs($n['y'])); }
    $mx += 14; $my += 12;                                   // 留出标签边距
    /* 图例落位：先在公里空间挑最空的角；若四角都太挤，就在最南侧加一条图例带 */
    $LW = 320; $LH = 104;
    $cornerKm = [
        'lt' => [-$mx + 4,  $my - 4],
        'rt' => [ $mx - 4,  $my - 4],
        'lb' => [-$mx + 4, -$my + 4],
        'rb' => [ $mx - 4, -$my + 4],
    ];
    $bandLegend = false; $bestKey = 'lt'; $bestClear = -1;
    foreach ($cornerKm as $k => [$qcx, $qcy]) {
        $min = 1e9;
        foreach ($nodes as $n) $min = min($min, hypot($qcx - $n['x'], $qcy - $n['y']));
        if ($min > $bestClear) { $bestClear = $min; $bestKey = $k; }
    }
    if ($bestClear < ($LW / $S + 6)) {                       // 角上都放不下 → 南侧加带
        $bandLegend = true;
        $my += ($LH + 30) / $S;
    }
    $W = (int) round($mx * 2 * $S);
    $H = (int) round($my * 2 * $S);
    $cx = $W / 2; $cy = $H / 2;
    $px = fn($x) => round($cx + $x * $S, 1);
    $py = fn($y) => round($cy - $y * $S, 1);
    $esc = fn($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');

    $o = [];
    $o[] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $W . ' ' . $H . '" width="100%" style="max-width:' . $W . 'px;height:auto;background:#070b0d;border:1px solid #1d2a30;border-radius:6px;font-family:inherit">';

    /* 等距圆环 */
    foreach ([20, 40, 60, 80, 100, 120] as $r) {
        $o[] = sprintf('<circle class="wm-ring" data-sw="1" cx="%s" cy="%s" r="%s" fill="none" stroke="#1b2a31" stroke-width="1"/>',
            $cx, $cy, round($r * $S, 1));
        $o[] = sprintf('<text x="%s" y="%s" fill="#3f5a66" font-size="13">%d km</text>',
            $cx + 3, $cy - round($r * $S, 1) + 13, $r);
    }
    /* 方位轴 */
    $o[] = sprintf('<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="#16232a" stroke-width="1"/>', $cx, 20, $cx, $H - 20);
    $o[] = sprintf('<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="#16232a" stroke-width="1"/>', 20, $cy, $W - 20, $cy);
    foreach (['北' => [$cx, 30], '南' => [$cx, $H - 12], '东' => [$W - 26, $cy - 6], '西' => [26, $cy - 6]] as $t => [$tx, $ty]) {
        $o[] = sprintf('<text x="%s" y="%s" fill="#5d7b88" font-size="13" text-anchor="middle">%s</text>', $tx, $ty, $t);
    }

    /* 路网 */
    $stl = [
        '已通'   => ['#6fbf73', 'solid', 2.2],
        '部分通' => ['#d8b45a', 'dasharray="7 5"', 2.0],
        '规划'   => ['#4d6472', 'dasharray="2 6"', 1.6],
    ];
    foreach (crrg_worldmap_roads() as $r) {
        $pts = crrg_worldmap_path_points($r);
        if (count($pts) < 2) continue;
        [$col, $dash, $wd] = $stl[$r['st']] ?? ['#4d6472', 'dasharray="2 6"', 1.6];
        if (($r['lv'] ?? '') === '高速') { $col = '#e6a23c'; $wd = ((float) $wd) + 1.4; }   // 高速：橙色加粗
        $attr = $dash === 'solid' ? '' : ' stroke-dasharray="' . trim(str_replace(['dasharray=', '"'], '', $dash)) . '"';
        $coords = [];
        foreach ($pts as $pp) $coords[] = $px($pp['x']) . ',' . $py($pp['y']);
        $o[] = sprintf('<polyline class="wm-line" data-sw="%s" points="%s" fill="none" stroke="%s" stroke-width="%s"%s opacity="0.85" stroke-linejoin="round"><title>%s %s（%s %d km，%s）</title></polyline>',
            $wd, implode(' ', $coords), $col, $wd, $attr, $esc($r['id']), $esc(crrg_worldmap_road_name($r)), $esc($r['lv']), $r['km'], $esc($r['st']));
        $mi = (int) floor((count($pts) - 1) / 2);
        $mid = $pts[$mi]; $nxt = $pts[$mi + 1] ?? $mid;
        $o[] = sprintf('<text class="wm-r" data-fs="12" x="%s" y="%s" fill="#7d9fae" font-size="12" text-anchor="middle" stroke="#070b0d" stroke-width="3" paint-order="stroke">%s</text>',
            round(($px($mid['x']) + $px($nxt['x'])) / 2, 1), round(($py($mid['y']) + $py($nxt['y'])) / 2, 1) - 4, $esc($r['id']));
    }

    /* 节点 */
    foreach (crrg_worldmap_nodes() as $id => $n) {
        $x = $px($n['x']); $y = $py($n['y']);
        $title = sprintf('%s（%s）· 基地%s方 · 直线 %d ／ 路程 %d km · %s（%s）', $n['n'], $n['t'], $n['dir'], $n['km'], $n['road_km'], $n['via'], $n['st']);
        $t = $n['t'];
        if ($t === '村') {
            $o[] = sprintf('<circle cx="%s" cy="%s" r="4.4" fill="#9fd6a8" stroke="#2a3a33" stroke-width="0.8"><title>%s</title></circle>', $x, $y, $esc($title));
        } elseif ($t === '镇') {
            $o[] = sprintf('<rect x="%s" y="%s" width="10" height="10" rx="1.8" fill="#f0d58a" stroke="#3a3320" stroke-width="0.8"><title>%s</title></rect>', $x - 4, $y - 4, $esc($title));
        } elseif ($t === '县城') {
            $o[] = sprintf('<rect x="%s" y="%s" width="13" height="13" rx="2.2" fill="#f2a35e" stroke="#3a2a20" stroke-width="1"><title>%s</title></rect>', $x - 5.5, $y - 5.5, $esc($title));
        } elseif ($t === '服务区') {
            $o[] = sprintf('<rect x="%s" y="%s" width="11" height="7" rx="2" fill="#7fc7e8" stroke="#1b3a4a" stroke-width="0.8"><title>%s</title></rect>', $x - 5.5, $y - 3.5, $esc($title));
            $o[] = sprintf('<text x="%s" y="%s" fill="#9fd0e6" font-size="11">%s</text>', $x + 10, $y - 6, '⛽');
        } elseif ($t === '山区') {
            $o[] = sprintf('<polygon points="%s,%s %s,%s %s,%s" fill="#8fa6b4" stroke="#26343d" stroke-width="0.8"><title>%s</title></polygon>',
                $x, $y - 6, $x - 6, $y + 4, $x + 6, $y + 4, $esc($title));
        } else { // 大城市
            $o[] = sprintf('<polygon points="%s,%s %s,%s %s,%s %s,%s %s,%s %s,%s %s,%s %s,%s %s,%s %s,%s" fill="#e8776f" stroke="#3a2220" stroke-width="1"><title>%s</title></polygon>',
                $x, $y - 10, $x + 3, $y - 3, $x + 10, $y - 3, $x + 4, $y + 2, $x + 6, $y + 9,
                $x, $y + 4, $x - 6, $y + 9, $x - 4, $y + 2, $x - 10, $y - 3, $x - 3, $y - 3, $esc($title));
        }
        $cls = ($t === '村') ? 'wm-v' : (($t === '山区') ? 'wm-m' : 'wm-t');
        $o[] = sprintf('<text class="%s" data-fs="13" x="%s" y="%s" fill="#dbe9ef" font-size="13" font-weight="500">%s <tspan fill="#8fb0bd">%d</tspan></text>',
            $cls, $x + 9, $y + 4, $esc($n['n']), $n['km']);
    }

    /* 已开放节点高亮（青石镇） */
    $qs = crrg_worldmap_point('qingshi');
    if ($qs) {
        $o[] = sprintf('<circle cx="%s" cy="%s" r="16" fill="none" stroke="#7dd87d" stroke-width="2" stroke-dasharray="4 3"><title>已开放</title></circle>', $px($qs['x']), $py($qs['y']));
    }

    /* 基地（原点） */
    $o[] = sprintf('<circle cx="%s" cy="%s" r="12" fill="none" stroke="#ff2b2b" stroke-width="1.5" opacity="0.7"/>', $cx, $cy);
    $o[] = sprintf('<polygon points="%s,%s %s,%s %s,%s %s,%s" fill="#ff2b2b"><title>青山基地（原点）</title></polygon>',
        $cx, $cy - 8, $cx + 8, $cy, $cx, $cy + 8, $cx - 8, $cy);
    $o[] = sprintf('<text x="%s" y="%s" fill="#ff8a8a" font-size="16" font-weight="bold">青山基地</text>', $cx + 13, $cy - 5);
    $o[] = sprintf('<text x="%s" y="%s" fill="#8a6a6a" font-size="11">原点 · 38.62, 99.85（对外为近似点位）</text>', $cx + 13, $cy + 10);

    /* 图例（位置由上面的择空结果决定；必要时落在南侧专用带里） */
    if ($bandLegend) {
        $lx = round(($W - $LW) / 2); $ly = $H - $LH - 14;
        $o[] = sprintf('<line x1="24" y1="%d" x2="%d" y2="%d" stroke="#16232a" stroke-width="1"/>', $ly - 14, $W - 24, $ly - 14);
    } else {
        $lx = ($bestKey === 'lt' || $bestKey === 'lb') ? 24 : $W - $LW - 24;
        $ly = ($bestKey === 'lt' || $bestKey === 'rt') ? 24 : $H - $LH - 24;
    }
    $o[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d" rx="6" fill="#0b1216" stroke="#1d2a30"/>', $lx, $ly, $LW, $LH);
    $o[] = sprintf('<text x="%d" y="%d" fill="#8fb0bd" font-size="13">图例</text>', $lx + 14, $ly + 20);
    $lg = [['村', '#9fd6a8'], ['镇', '#f0d58a'], ['县城', '#f2a35e'], ['山区', '#8fa6b4'], ['大城市', '#e8776f'], ['服务区', '#7fc7e8']];
    $i = 0;
    foreach ($lg as [$t, $c]) {
        $x = $lx + 20 + ($i % 6) * 50;
        $o[] = sprintf('<circle cx="%s" cy="%s" r="4.6" fill="%s"/>', $x, $ly + 40, $c);
        $o[] = sprintf('<text x="%s" y="%s" fill="#b6c9d2" font-size="12">%s</text>', $x + 8, $ly + 44, $t);
        $i++;
    }
    $o[] = sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#6fbf73" stroke-width="3"/>', $lx + 16, $ly + 62, $lx + 56, $ly + 62);
    $o[] = sprintf('<text x="%d" y="%d" fill="#b6c9d2" font-size="12">已通</text>', $lx + 64, $ly + 68);
    $o[] = sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#d8b45a" stroke-width="3" stroke-dasharray="8 6"/>', $lx + 116, $ly + 62, $lx + 156, $ly + 62);
    $o[] = sprintf('<text x="%d" y="%d" fill="#b6c9d2" font-size="12">部分通</text>', $lx + 164, $ly + 68);
    $o[] = sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#4d6472" stroke-width="2.4" stroke-dasharray="2 7"/>', $lx + 222, $ly + 62, $lx + 262, $ly + 62);
    $o[] = sprintf('<text x="%d" y="%d" fill="#b6c9d2" font-size="12">规划</text>', $lx + 270, $ly + 68);
    $o[] = sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#e6a23c" stroke-width="4.4"/>', $lx + 16, $ly + 82, $lx + 56, $ly + 82);
    $o[] = sprintf('<text x="%d" y="%d" fill="#f0b45f" font-size="12">高速</text>', $lx + 64, $ly + 88);
    $o[] = sprintf('<text x="%d" y="%d" fill="#6d8c98" font-size="11">等距圆环 20–120 km ｜ 绿圈＝已开放 ｜ 蓝块＝服务区</text>', $lx + 120, $ly + 88);

    $o[] = '</svg>';
    return implode('', $o);
}
