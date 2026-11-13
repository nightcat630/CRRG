<?php
/**
 * 中型模组：《追书人》（移植自 Paper Chase，Chaosium 7e 入门套装）
 *
 * 移植口径：**结构不动**（调查六向、三条推进线、三种遭遇分支、尾声奖励与两份 NPC 数据均照原样）；
 *           场景与文字**全面本地化**到青石镇 / 当代西北（人名、机构、器物一并对换）。
 * 人数：1～2 人小组。时长：一次聚会。
 */
if (!defined('ABSPATH')) exit;

/* ================= 一、场景追加（青石镇） ================= */
crrg_drill_scene_extend('town', [
    'tw_li_house' => ['t' => '李家老宅（南巷）', 'f' => '镇上', 'c' => 1,
        'd' => '青砖门楼，两进院子。侄子李承业还住在这儿，除了东厢那间书房——书房门一直锁着，钥匙挂在堂屋墙上。',
        'x' => ['南巷' => 'tw_alley_s', '书房' => 'tw_li_study']],
    'tw_li_study' => ['t' => '李家书房', 'f' => '镇上', 'c' => 1,
        'd' => '三面墙全是书架，从地板顶到房梁。书按开本排得整整齐齐，只有一处空着——大约六本的位置。窗闩的木屑掉在窗台上。',
        'x' => ['老宅' => 'tw_li_house']],
    'tw_police' => ['t' => '青石镇派出所', 'f' => '镇上', 'c' => 1,
        'd' => '一间值班室加一间调解室。墙上挂着辖区图和值班表，桌上压着一沓寻人启事的存根。',
        'x' => ['主街·东段' => 'tw_main_e']],
    'tw_culture' => ['t' => '镇文化站阅览室', 'f' => '镇上', 'c' => 1,
        'd' => '两排铁书架，一半是农业技术，一半是过期的报纸合订本。管理员在窗边写值班记录。',
        'x' => ['北巷' => 'tw_alley_n']],
    'tw_press' => ['t' => '县融媒体中心采编点', 'f' => '镇上', 'c' => 1,
        'd' => '邮电所二楼的一间屋，两台旧电脑一张长桌。资料柜里按年份码着报纸合订本和采访笔记。',
        'x' => ['邮电所' => 'tw_post']],
    'tw_grave' => ['t' => '后山公墓', 'f' => '镇外', 'c' => 1,
        'd' => '镇子西边的一道缓坡，灌木长得比人高。石碑一层层排上去，半山腰有几座带门的老陵。守墓人的平房在坡脚。',
        'x' => ['镇口' => 'tw_gate', '李家老宅' => 'tw_li_house', '老陵' => 'tw_tomb']],
    'tw_tomb' => ['t' => '老陵', 'f' => '镇外', 'c' => 1,
        'd' => '一座塌了一半的石陵，门扇是整块青石，缝里往外渗着凉气。门楣上的字被磨平了。',
        'x' => ['公墓' => 'tw_grave']],
    'tw_tunnel' => ['t' => '地下隧道', 'f' => '镇外', 'c' => 1,
        'd' => '土壁的通道，宽窄不一，方向也说不清。空气里是潮土和腐叶的味道。远处偶尔传来纸张翻动的窸窣声。',
        'x' => ['返回地面' => 'tw_tomb']],
], [
    'tw_li_house' => [116, 14], 'tw_li_study' => [124, 20], 'tw_police' => [172, 52],
    'tw_culture' => [88, 60], 'tw_press' => [146, 62], 'tw_grave' => [22, 68],
    'tw_tomb' => [34, 74], 'tw_tunnel' => [30, 78],
], [
    'tw_li_house' => [
        ['n' => '李承业（委托人）', 'k' => 'npc', 'c' => '问 李承业'],
        ['n' => '书房钥匙', 'k' => 'item', 'c' => '查看 钥匙'],
    ],
    'tw_li_study' => [
        ['n' => '书架上的空档', 'k' => 'item', 'c' => '查看 空档'],
        ['n' => '窗闩', 'k' => 'item', 'c' => '查看 窗闩'],
        ['n' => '一摞旧书', 'k' => 'item', 'c' => '翻找 日记'],
    ],
    'tw_police' => [['n' => '值班警官', 'k' => 'npc', 'c' => '询问 警官'], ['n' => '寻人启事存根', 'k' => 'item', 'c' => '查看 存根']],
    'tw_culture' => [['n' => '管理员', 'k' => 'npc', 'c' => '查看 管理员'], ['n' => '报纸合订本', 'k' => 'item', 'c' => '查阅 合订本']],
    'tw_press' => [['n' => '资料柜', 'k' => 'item', 'c' => '查阅 资料柜'], ['n' => '采访笔记', 'k' => 'item', 'c' => '查阅 笔记']],
    'tw_grave' => [
        ['n' => '马占魁（守墓人）', 'k' => 'npc', 'c' => '问 马占魁'],
        ['n' => '矮墓碑', 'k' => 'item', 'c' => '查看 墓碑'],
        ['n' => '灌木丛', 'k' => 'item', 'c' => '查看 灌木'],
    ],
    'tw_tomb' => [['n' => '青石门扇', 'k' => 'item', 'c' => '查看 门扇']],
    'tw_tunnel' => [['n' => '通道深处', 'k' => 'item', 'c' => '查看 深处']],
]);

/* 接线：老宅与公墓相邻（原模组即如此），镇口也能绕上去 */
crrg_drill_scene_add_exit('town', 'tw_li_house', '后山公墓', 'tw_grave');
crrg_drill_scene_add_exit('town', 'tw_gate', '后山公墓', 'tw_grave');

/* ================= 二、NPC 数据（照抄原模组数值） ================= */
function crrg_drill_extra_npcs() {
    return [
        'chase_sexton' => ['n' => '守墓人马占魁', 'persona' => 'aggressive', 'hp' => 12, 'armor' => 0,
            'skills' => ['格斗' => 30, '闪避' => 25, '恐吓' => 45, '机械维修' => 55, '博物学' => 70],
            'weapon' => 'tool_shovel'],
        'chase_ghoul'  => ['n' => '夜色里的身影', 'persona' => 'cautious', 'hp' => 12, 'armor' => 0,
            'skills' => ['格斗' => 50, '闪避' => 30, '攀爬' => 85, '潜行' => 70, '聆听' => 70, '侦查' => 50],
            'weapon' => 'claw', 'half_fire' => true, 'note' => '枪械伤害减半（向下取整）'],
    ];
}
/* 武器表补两件模组用到的 */
function crrg_drill_chase_weapons($w) {
    // 只补名字，不动武器类型层给的数值（伤害/类型/力量要求）
    if (isset($w['tool_shovel'])) $w['tool_shovel']['n'] = '铁铲';
    else $w['tool_shovel'] = ['n' => '铁铲', 'skill' => '格斗', 'dmg' => '1d8', 'range' => 2, 'melee' => true, 'cls' => 'blunt_h', 'need_str' => 50];
    return $w;
}

/* ================= 三、观察钩子 ================= */
/** 检定的统一写法：成功/困难/极难 三档 + 失败后果 */
function crrg_chase_check($pc, $skill, $hard = false, $extreme = false) {
    $v = max(1, crrg_coc_skill_val($pc, $skill));
    $r = crrg_coc_check($skill, $v);
    $need = $extreme ? 4 : ($hard ? 3 : 2);
    $r['pass'] = crrg_coc_level_rank($r['level']) >= $need;
    return $r;
}
function crrg_chase_look($s, $pc, $arg, &$lines, &$flags) {
    $room = $s['room'] ?? '';
    $add  = function ($t) use (&$lines) { $lines[] = $t; };
    $has  = function ($f) use (&$flags) { return in_array($f, (array) $flags, true); };
    $set  = function ($f) use (&$flags) { if (!in_array($f, (array) $flags, true)) $flags[] = $f; };
    $p    = function ($t) use ($add) { $add($t); };

    /* —— 先给外貌：查看某个人，先看他的样子 —— */
    if (function_exists('crrg_chase_appearance')) {
        $ap = crrg_chase_appearance($arg);
        if ($ap && (mb_strpos($arg, '看') === false || true)) {
            $hit = false;
            foreach ((crrg_drill_ents()[$room] ?? []) as $e) {
                if (mb_strpos($e['n'], $arg) !== false || mb_strpos($arg, $e['n']) !== false) { $hit = true; break; }
            }
            if ($hit) { foreach ($ap as $l) $add($l); return true; }
        }
    }

    /* —— 李家老宅 —— */
    if (mb_strpos($arg, '李承业') !== false && $room === 'tw_li_house') {
        $add('李承业四十上下，在镇上开车跑运输。他把书房钥匙从墙上摘下来递给你。');
        $add('「我叔李道生，去年秋天没的影儿。派出所报了，寻人启事也登过，没信儿。」');
        $add('「前阵子家里进了贼，丢的是他最喜欢的几本书，别的什么都没动。书不值钱，可我就想弄明白是谁、为什么。」');
        $add('「他要是还活着，麻烦你告诉我一声。要是……不在了，也让我知道。」');
        $set('见过委托人');
        return true;
    }
    if (mb_strpos($arg, '钥匙') !== false && $room === 'tw_li_house') {
        $add('钥匙串上挂着一张超市积分卡，铜钥匙磨得发亮——有人常开这扇门。');
        return true;
    }
    if ($room === 'tw_li_study') {
        if (mb_strpos($arg, '空档') !== false) {
            $add('书架上空出一段，灰痕压得很新。按手指量：大约六本，厚薄不一。');
            $add('旁边的书都是文学和历史，依开本排的——拿走的显然不是随手抓的。');
            $set('线索_空档');
            return true;
        }
        if (mb_strpos($arg, '窗闩') !== false) {
            [$wb, $wp, $ww] = function_exists('crrg_world_mod') ? crrg_world_mod($room, '侦查', $pc) : [0, 0, []];
            $r = crrg_coc_check('侦查', max(1, crrg_coc_skill_val($pc, '侦查')), $wb, $wp);
            $r['pass'] = crrg_coc_level_rank($r['level']) >= 2;
            $add('窗闩：' . $r['text']);
            if ($r['pass']) {
                $add('窗闩因为年久松动，从外侧用足力气就能推开；窗台上还留着两处浅浅的印子。');
                $set('线索_窗锁');
            } else {
                $add('窗闩看着是好的，只有一层灰。');
            }
            return true;
        }
        if (mb_strpos($arg, '日记') !== false || mb_strpos($arg, '翻找') !== false) {
            [$wb, $wp, $ww] = function_exists('crrg_world_mod') ? crrg_world_mod($room, '侦查', $pc) : [0, 0, []];
            $r = crrg_coc_check('侦查', max(1, crrg_coc_skill_val($pc, '侦查')), $wb, $wp);
            $r['pass'] = crrg_coc_level_rank($r['level']) >= 2;
            $add('翻检整间书房：' . $r['text']);
            if ($r['pass']) {
                $add('在一摞旧书的夹层里找出一本硬皮日记。');
                $add('最后一页写在失踪前一天：「我做了决定。地下的朋友们要关掉那处口子，我得把自己的书搬完。」');
                $add('更早的条目里反复提到一条通道——就在公墓底下，通向"他们"的地方。');
                $set('线索_日记');
            } else {
                $add('整间书房翻下来，只有成堆的书和一层浮灰。');
                $set('日记_未果');
            }
            return true;
        }
    }
    /* —— 委托人念叨（推进提示） —— */
    if (mb_strpos($arg, '进展') !== false) {
        $p('李承业把手在裤子上擦了擦：「有信儿了？」');
        return true;
    }

    /* —— 人影（活着或倒着都能看） —— */
    if (mb_strpos($arg, '人影') !== false || mb_strpos($arg, '尸') !== false) {
        if (!in_array('遭遇_进行中', (array) $flags, true) && !in_array('遭遇_结束', (array) $flags, true)) {
            $add('这屋里没有别人。');
            return true;
        }
        if (function_exists('crrg_chase_sight_lines')) foreach (crrg_chase_sight_lines() as $l) $add($l);
        $add('它手指上的爪甲缝里塞着黑土；油布包的是一本硬皮书，扉页上有铅笔写的名字——李道生。');
        return true;
    }

    /* —— 公墓 —— */
    if ($room === 'tw_grave') {
        if (mb_strpos($arg, '马占魁') !== false || mb_strpos($arg, '搭话') !== false) {
            if ($has('守墓人_翻脸')) {
                $r = crrg_chase_check($pc, '说服', true);
                $add('马占魁斜你一眼：' . $r['text'] . '（他记着你上次的态度，这回想聊得拿出真本事）');
            } else {
                $r = crrg_chase_check($pc, '魅惑');
                if (!$r['pass']) $r = crrg_chase_check($pc, '说服');
                $add('搭话：' . $r['text']);
            }
            if (!in_array('守墓人_可用', (array) $flags, true) && (!$r['pass'])) {
                $add('「忙，没空站着唠。」马占魁把铁锹往肩上一搭，转身去铲坡上的草。');
                $set('守墓人_生冷');
                return true;
            }
            $set('守墓人_可用');
            $add('马占魁话不多，放下锹头，跟你蹲在坡上。');
            $add('「李道生？认识。那人爱看书，天天夹一本往坡上走，就坐那块矮碑上。」他往半山腰抬了下下巴。');
            $add('「他没了以后我还念叨，没人陪我扯闲篇了。」');
            $set('线索_墓地');
            if (!$has('线索_墓碑')) { $add('他用锹把往半山腰点了点，没再多说。'); }
            if (!$has('守墓人_隐瞒')) {
                $rp = crrg_chase_check($pc, '心理学');
                $add('看他神色：' . $rp['text']);
                if ($rp['pass']) {
                    $add('你注意到他话到一半收住了——他知道的比说出来的多，而且不愿意说。');
                    $set('守墓人_隐瞒');
                }
            }
            if (!$has('线索_酒瓶')) {
                $rs = crrg_chase_check($pc, '侦查');
                $add('扫一眼他周身：' . $rs['text']);
                if ($rs['pass']) {
                    $add('他的外套口袋里露出半截瓶子。');
                    $set('线索_酒瓶');
                }
            }
            return true;
        }
        if (mb_strpos($arg, '墓碑') !== false) {
            $add('一块矮碑，多年风雨把字磨平了，认不出墓主。碑面被磨得发亮——是常年有人坐上去的痕迹。');
            [$wb, $wp, $ww] = function_exists('crrg_world_mod') ? crrg_world_mod($room, '侦查', $pc) : [0, 0, []];
            $r = crrg_coc_check('侦查', max(1, crrg_coc_skill_val($pc, '侦查')), $wb, $wp);
            $r['pass'] = crrg_coc_level_rank($r['level']) >= 2;
            if (!$r['pass']) $r = crrg_chase_check($pc, '追踪');
            $add('看碑周围：' . $r['text']);
            if ($r['pass']) {
                $add('碑脚边的土上有几个浅浅的印子：赤脚的成年人，脚趾位置是分叉的，像偶蹄。');
                $add('印子一串串往半山腰去，尽头是一座塌了一半的石陵。');
                $set('线索_足迹'); $set('线索_墓碑');
            } else {
                $add('碑脚全是落叶和浮土，看不出什么。');
            }
            return true;
        }
        if (mb_strpos($arg, '灌木') !== false) {
            $add('灌木长得比人高，把靠西的几排碑挡得严实。坡上没有新翻的土。');
            return true;
        }
    }

    /* —— 老陵 —— */
    if ($room === 'tw_tomb') {
        if (mb_strpos($arg, '门扇') !== false) {
            $add('整块青石，靠里侧有被反复推动磨出的亮痕——这扇门近来开过，不止一次。');
            $set('线索_门痕');
            return true;
        }
    }

    /* —— 隧道 —— */
    if ($room === 'tw_tunnel' && mb_strpos($arg, '深处') !== false) {
        $set('进入隧道');
        if ($has('隧道尽头_封')) {
            $add('再往里是一条堵死的短岔，土壁上留着新刮的痕。那股翻纸的窸窣声没有了。');
            return true;
        }
        $r = crrg_chase_check($pc, '聆听');
        $add('停下听：' . $r['text']);
        if ($r['pass']) $add('通道深处有纸页翻动的窸窣声，还有轻轻的、像哭又像哼的调子。');
        else $add('只有自己的呼吸声。');
        return true;
    }

    /* —— 派出 / 文化站 / 融媒体 —— */
    if ($room === 'tw_police') {
        if (mb_strpos($arg, '警官') !== false || mb_strpos($arg, '询问') !== false) {
            $r = crrg_chase_check($pc, '法律');
            if (!$r['pass']) $r = crrg_chase_check($pc, '魅惑');
            $add('跟值班警官搭话：' . $r['text']);
            if (!$r['pass']) { $add('「忙着呢，明天再来。」'); return true; }
            $add('他把手里的笔放下。「入室盗窃？近一年没接到了。上回那桩是「六指刘三」干的，人还在里头蹲着。」');
            $add('「公墓那片，这些年没人报过事。要说有，六七年前有人反映深夜里头有动静，我们连着巡了几周，什么也没见着。」');
            $add('「李道生？记得。寻人启事是我们帮着发的，照片贴了半个县，石沉大海。」');
            $set('线索_派出所');
            return true;
        }
        if (mb_strpos($arg, '存根') !== false) {
            $add('一沓寻人启事存根，最上面一张是去年十月：李道生，六十二岁，白发，戴圆框眼镜，身高一米七上下。');
            $set('线索_派出所');
            return true;
        }
    }
    if ($room === 'tw_culture') {
        if (mb_strpos($arg, '合订本') !== false || mb_strpos($arg, '查阅') !== false) {
            $r = crrg_chase_check($pc, '图书馆使用');
            $add('查报纸合订本：' . $r['text']);
            if ($r['pass']) {
                $add('翻到十几年前的一条短讯：有人反映，夜里看见一群人赤身在后山公墓一带嬉闹。');
                $add('民警出警搜了整片坡，人没找着，只在土上留下一些形状古怪的脚印。');
                $set('线索_阅览室');
            } else {
                $add('合订本缺了几期，翻到的都是种粮补贴的报道。');
            }
            return true;
        }
        if (mb_strpos($arg, '管理员') !== false) {
            $add('管理员说很久没人来查旧报了，问你是哪个单位的。要查就得登记，写清楚年月。');
            return true;
        }
    }
    if ($room === 'tw_press') {
        if (mb_strpos($arg, '资料柜') !== false || mb_strpos($arg, '笔记') !== false) {
            $r = crrg_chase_check($pc, '图书馆使用');
            $add('翻资料柜：' . $r['text']);
            if ($r['pass']) {
                $add('找到一份没发表的采访笔记，受访人是当年六十四岁的王秀兰，失眠多年。');
                $add('她说二十多年里常看见"魔鬼的子嗣"在后山出没：轮廓像人，可比人矮，脚是蹄子，身上沾着烂泥。');
                $add('笔记末尾写着：王秀兰已搬去县城，别的邻居没人承认见过。');
                $set('线索_融媒体');
            } else {
                $add('资料柜里年份标签脱落，一时找不到那几年的本子。');
            }
            return true;
        }
        if (mb_strpos($arg, '记者') !== false) {
            $add('镇上的采编点只有一个人常驻，姓赵，跑农业口。他说查旧报没问题，别带走原件。');
            return true;
        }
    }
    return false;
}
crrg_drill_add_look_hook('crrg_chase_look');

/* ================= 四、模组动作（面板点，含遭遇分支） ================= */
function crrg_chase_act(&$s, &$pc, $ctx, $uid, $id) {
    $flags = (array) ($s['flags'] ?? []);
    $set = function ($f) use (&$s) { if (!in_array($f, (array) $s['flags'], true)) $s['flags'][] = $f; };
    $has = function ($f) use ($s) { return in_array($f, (array) ($s['flags'] ?? []), true); };
    $room = $s['room'] ?? '';
    $out = [];
    switch ($id) {
        case 'tell_truth':     // 交代：说实话
            return crrg_chase_epilogue($s, $pc, 'truth');
        case 'tell_lie':       // 交代：瞒下来
            return crrg_chase_epilogue($s, $pc, 'lie');
        case 'take_case':      // 接案：转给合约系统
            if (!function_exists('crrg_drill_do_act')) return ['合约系统未加载。'];
            return crrg_drill_do_act($s, $pc, $ctx, $uid, 'accept', 'm_chase_01');
        case 'sexton':         // 与守墓人搭话 → 交给对话系统列话题
            if (function_exists('crrg_dlg_ask')) {
                [$o, $h] = crrg_dlg_ask($s, $pc, $uid, 'ma_zankui', '');
                return $o;
            }
            return ['（对话系统未加载）'];
        case 'sexton_bribe':   // 用烟／酒换消息（原模组：贿赂无需检定）
            if (!$has('线索_酒瓶')) return ['他身上没什么能搭话的东西。'];
            if (!crrg_drill_bag_count($pc, 'smoke_cig') && !crrg_drill_bag_count($pc, 'drink_liquor')) {
                return ['你身上没带烟也没带酒——去供销社或加油站便利店买点再来。'];
            }
            if (crrg_drill_bag_count($pc, 'smoke_cig')) crrg_drill_bag_del_n($pc, 'smoke_cig', 1);
            else crrg_drill_bag_del_n($pc, 'drink_liquor', 1);
            $out[] = '你一包烟拍在马占魁手里，他愣了下，塞进兜里。';
            $out[] = '「……行，我说。」他蹲下去，声音压低。';
            $out[] = '「前几个月，深夜里头我在坡上见过人影，就在李道生常坐那块碑旁边。我没敢过去——我寻思那是他回来了。」';
            $out[] = '「这事你别往外说。说完就完了。」';
            $set('线索_收买'); $set('线索_墓碑');
            if (function_exists('crrg_rep_add')) {
                $now = crrg_rep_add($pc, 'town_qingshi', -1, '追书人：给守墓人递了烟酒');
                $out[] = sprintf('（青石镇声望 -1 → %d）', $now);
            }
            return $out;
        case 'sexton_press':   // 要挟（恐吓常规 / 说服困难）
            if (!$has('线索_酒瓶')) return ['你手上没有能拿住他的东西。'];
            $r = crrg_chase_check($pc, '恐吓');
            if (!$r['pass']) $r = crrg_chase_check($pc, '说服', true);
            $out[] = '要挟：' . $r['text'];
            if (!$r['pass']) {
                $out[] = '马占魁脸一沉，把铁锹横过来：「你少来这套。」他记着你这次，以后更难搭话。';
                $set('守墓人_翻脸');
                return $out;
            }
            $out[] = '他没吭声，隔了会儿才说：「深夜里见过人影，在李道生那块碑边上。我当是鬼。」';
            $set('线索_收买'); $set('线索_墓碑');
            if (function_exists('crrg_rep_add')) {
                $now = crrg_rep_add($pc, 'town_qingshi', -3, '追书人：拿酒瓶要挟守墓人');
                $out[] = sprintf('（青石镇声望 -3 → %d）', $now);
            }
            return $out;
        case 'accept_late':    // 事后补接：先干了活，再补手续
            if (($pc['mission'] ?? '') !== '' ) return ['你手上还有别的合约，先结掉。'];
            if (in_array('m_chase_01', (array) ($pc['mission_done'] ?? []), true)) return ['这桩委托你已经结过了。'];
            $pc['mission'] = 'm_chase_01';
            $pc['mission_step'] = 0;
            $out = ['李承业听你把话说完，才从墙上摘下钥匙。',
                    '「你们……已经查过了？行，那就按流程走一遍。花销我出，报酬另算。」',
                    '（委托已补接到手。之前查到的线索与遭遇都算数。）'];
            $tmp = [];
            crrg_drill_mission_tick($s, $pc, 'accept_late', $tmp);
            return $out;

        case 'watch':          // 监视：结案后没意义；且得先有说法
            if ($has('结局_已交代')) return ['案子已经了了，没必要再蹲。'];
            $hypo = ['线索_窗锁', '线索_日记', '线索_墓碑', '线索_收买', '目击_人影'];
            $ok = false;
            foreach ($hypo as $f) if ($has($f)) { $ok = true; break; }
            if (!$ok) {
                return ['你在暗处蹲了半宿。坡上的草一直响，别的什么都没有。',
                        '（没有由头的蹲守，通常只是白蹲一晚。）'];
            }
            if (function_exists('crrg_world_clock') && !crrg_world_clock()['night']) {
                return ['现在是' . crrg_world_clock()['phase'] . '，人都在街上走，蹲不出什么。', '（入夜后再来。）'];
            }
            $no_case = ($pc['mission'] ?? '') !== 'm_chase_01' && !in_array('m_chase_01', (array) ($pc['mission_done'] ?? []), true);
            if ($no_case && !$has('擅自行动_追书人')) {
                $s['flags'][] = '擅自行动_追书人';
                $out = ['（你没接这桩委托就自己来蹲了——蹲到什么，也交不了差。要接，回李家老宅问李承业。）'];
            }
            $where = in_array($room, ['tw_grave', 'tw_tomb'], true) ? '公墓' : '老宅';
            $help = $has('联防_已报案') || $has('线索_巡逻表');
            $r = crrg_coc_check('幸运', (int) ($pc['attrs']['幸运'] ?? 50), $help ? 1 : 0, 0);
            if ($no_case) $out[] = '（提醒：这桩委托你还没接。蹲到的结果，接上案之后照样算数。）';
            $out[] = '在' . $where . '外蹲了一个晚上：' . $r['text'] . ($help ? '　［联防知会过 +1］' : '');
            if (crrg_coc_level_rank($r['level']) < 2) {
                $out[] = '一夜无事。风把坡上的草吹得沙沙响，别的什么都没有。';
                return $out;
            }
            $out[] = '后半夜，一条人影从公墓方向过来，径直走到李家老宅，从书房那扇窗翻了进去。';
            $out[] = '过了几分钟，它抱着几本书原路出来，往公墓走。';
            if (!$has('遭遇_进行中')) { $set('遭遇_进行中'); $set('遭遇_地点_老宅'); }
            $set('目击_人影');
            return $out;
        case 'talk':           // 交谈：原模组的三次理智检定在此合并为两条
            if (!$has('遭遇_进行中')) return ['眼下没有可以交谈的对象。'];
            $out[] = '你喊了一声。人影顿了顿，没回头，走得更慢了些，像是等你跟上。';
            $r1 = crrg_coc_roll100();
            $lv1 = crrg_coc_level($r1, max(1, (int) $pc['san']));
            $out[] = sprintf('看清那张脸：D100＝%d ／ %s', $r1, crrg_coc_level_name($lv1));
            if (function_exists('crrg_chase_sight_lines')) foreach (crrg_chase_sight_lines() as $l) $out[] = $l;
            if (crrg_coc_level_rank($lv1) < 2) {
                $pc['san'] = max(0, (int) $pc['san'] - crrg_coc_dice('1d4'));
                $out[] = '你的头皮一下子炸开——那东西不像人。理智 −1D4，现在 ' . $pc['san'] . '。';
            }
            $out[] = '它坐到那块矮碑上，把书放在膝头，开口说：「你好。」声音含糊，像哭，也像咳嗽。';
            $r2 = crrg_coc_roll100();
            $lv2 = crrg_coc_level($r2, max(1, (int) $pc['san']));
            $out[] = sprintf('认出它是谁：D100＝%d ／ %s', $r2, crrg_coc_level_name($lv2));
            $lost = crrg_coc_level_rank($lv2) >= 2 ? 1 : crrg_coc_dice('1d4');
            $pc['san'] = max(0, (int) $pc['san'] - $lost);
            $out[] = '那是李道生。「他」还认得人。理智 −' . $lost . '，现在 ' . $pc['san'] . '。';
            $out[] = '——「书是我拿的，本来就是我的。」';
            $out[] = '「人间的应酬我过厌了。这边没人管我什么时候读书，白天晚上都行。」';
            $out[] = '「这里要封口了，回头不来了。地下能看能学的东西多，我打算自己写一本。」';
            $out[] = '「别跟我侄子说我还活着——说得难听些，我这样也算不上活着。」';
            $out[] = '他说这些的时候，指甲一直在油布边缘上蹭，像是怕把书角压坏。';
            $out[] = '你注意到他说话时舌头不太灵便，某些字要含糊两遍才能听清；'
                   . '但他念到书里某一段时，忽然又清楚得像另一个人在说话。';
            $out[] = '说完他把油布重新裹好，抱起来，滑下碑，钻进石陵；'
                   . '反手拖过一块石板把那个口子堵上。石头擦地的声音拖得很长，然后就没有了。';
            $sk = $pc['skills'] ?: [];
            $sk['克苏鲁神话'] = (int) ($sk['克苏鲁神话'] ?? 0) + 3; $pc['skills'] = $sk;
            $set('遭遇_交谈'); $set('遭遇_结束');
            $s['flags'] = array_values(array_diff((array) $s['flags'], ['遭遇_进行中']));
            $set('隧道尽头_封');
            $out[] = '他矮身钻进陵道，回手拖过一块石板，把那个口子堵上了。';
            $out[] = '（通道只剩来路。往上走就是陵门。）';
            return $out;
        case 'attack':         // 攻击 → 转入战斗（进行中标记收掉，由战斗收场钩子回填结果）
            if (!$has('遭遇_进行中')) return ['没有可攻击的目标。'];
            $ar = crrg_bt_arena($room);
            crrg_bt_start($room, $uid, 'encounter', [
                crrg_bt_npc_from('chase_ghoul', 0, max(2, (int) ($ar['w'] / 2)), (int) ($ar['h'] / 2), '西'),
            ]);
            $set('遭遇_开打');
            $s['flags'] = array_values(array_diff((array) $s['flags'], ['遭遇_进行中']));
            return ['你动了手。'];
        case 'retreat':
            if (!$has('遭遇_进行中')) return ['没有需要回避的事。'];
            $set('遭遇_逃亡'); $set('遭遇_结束');
            return ['你退进巷子，压低身子往回走。身后的脚步声没有追过来。',
                    '第二天再去公墓，那块碑旁边什么痕迹都没有了。'];
        case 'door':           // 推开陵门（力量检定；有撬棍则奖励骰）
            if ($has('结局_已交代') && $has('遭遇_交谈')) return ['石缝里灌满了水泥，推不动了。'];
            $pry = crrg_drill_bag_count($pc, 'tool_prybar') > 0;
            $r = crrg_coc_check('力量', max(1, crrg_coc_skill_val($pc, '力量')), $pry ? 1 : 0, 0);
            $out[] = '推门：' . $r['text'] . ($pry ? '　［撬棍 +1］' : '');
            if (crrg_coc_level_rank($r['level']) < 2) {
                $out[] = '石门只挪了一指宽。找件家伙撬，或者换个法子。';
                return $out;
            }
            $out[] = '门轴嘎吱一声，门开了。里头涌出一股腐土的恶臭。';
            $set('门已开');
            return $out;
        case 'enter':          // 直接进（没屏息）
            if ($has('结局_已交代')) return ['口子已经封死了，钻不进去。'];
            if (!$has('门已开')) return ['门还没开。'];
            $pc['posture'] = '躺着';
            $out[] = '你没来得及屏息——那股味道顶上来，眼前一黑。';
            $out[] = '再睁眼是天黑，你躺在碑座下面，身上沾着土。';
            $set('昏过去过'); $set('进入隧道');
            $s['room'] = 'tw_tunnel';
            return $out;
        case 'enter_hold':     // 屏息进入
            if ($has('结局_已交代')) return ['口子已经封死了，钻不进去。'];
            if (!$has('门已开')) return ['门还没开。'];
            $out[] = '你屏住呼吸进去。洞里是一条用手挖出来的通道，往下走，越走越宽。';
            $set('进入隧道');
            $s['room'] = 'tw_tunnel';
            return $out;
        case 'tunnel':         // 隧道迷宫：导航检定
            if ($has('结局_已交代')) return ['通道那头封死了，没得走。往回吧。'];
            if ($has('遭遇_结束')) {
                return ['通道里只剩你自己。来路的斜坡就在身后。',
                        '（「返回地面」可以出去。）'];
            }
            $rope = crrg_drill_bag_count($pc, 'tool_rope_nylon') > 0;
            $r = crrg_coc_check('导航', max(1, crrg_coc_skill_val($pc, '导航')), $rope ? 1 : 0, 0);
            $out[] = '在通道里走：' . $r['text'] . ($rope ? '　［绳子 +1］' : '');
            if (crrg_coc_level_rank($r['level']) < 2) {
                $out[] = '你在土壁间绕了很久，绕到腿发软，只能坐下歇。';
                $out[] = '等再抬头，前面站着一个人——不，是那个东西。';
            } else {
                $out[] = '你摸到了通向地面的那道斜坡，正要往上，前面站着一个人——不，是那个东西。';
            }
            $set('遭遇_进行中'); $set('遭遇_地点_地下');
            return $out;
        case 'surface':        // 沿斜坡爬回陵门
            $s['room'] = 'tw_tomb';
            return ['你顺着斜坡往上爬，从陵门下钻出来。天光刺眼。'];
        case 'report':         // 回老宅交代（幂等：交代过就不再来一遍）
            if ($has('结局_已交代')) return ['这事已经交代过了。'];
            $done = $has('遭遇_交谈') || $has('遭遇_击倒') || $has('遭遇_逃亡');
            if (!$done) return ['还没到能交代的时候。'];
            $out[] = '你把事情说了——说了多少，自己拿主意。';
            if ($has('遭遇_交谈')) $out[] = '按李道生的意思，你只说偷书的人不会再来了。';
            return $out;
    }
    /* 兜底：这个动作没登记，但房间里若是可对话的人，就转对话（列话题） */
    if (function_exists('crrg_dlg_npcs_at')) {
        $here = crrg_dlg_npcs_at($room);
        foreach ($here as $nid => $d) {
            $n = $d['name'] ?? '';
            if ($n !== '' && (mb_strpos($n, $id) !== false || mb_strpos($id, $n) !== false)) {
                [$o, $h] = crrg_dlg_ask($s, $pc, $uid, $nid, '');
                return $o;
            }
        }
        if (count($here) === 1) {
            [$o, $h] = crrg_dlg_ask($s, $pc, $uid, array_key_first($here), '');
            return $o;
        }
    }
    return ['这里没有这个动作。'];
}

/* ================= 五、面板（按房间给动作） ================= */
function crrg_chase_panels($s, $pc, $room) {
    $has = function ($f) use ($s) { return in_array($f, (array) ($s['flags'] ?? []), true); };
    $it  = [];
    $act = function ($label, $id) { return ['label' => $label, 'k' => 'chase', 'id' => $id]; };
    if ($room === 'tw_li_house' && ($has('遭遇_交谈') || $has('遭遇_击倒') || $has('遭遇_逃亡')) && !$has('结局_已交代')) {
        $it[] = $act('把实情告诉他', 'tell_truth');
        $it[] = $act('只说窃贼不会再来了', 'tell_lie');
    }
    if ($room === 'tw_li_house' && $has('见过委托人') && empty($pc['mission']) && !in_array('m_chase_01', (array) ($pc['mission_done'] ?? []), true)) {
        $it[] = $act('接下这桩委托', 'take_case');
    }
    if ($room === 'tw_li_house' && $has('见过委托人')) {
        $hyp = false;
        foreach (['线索_窗锁', '线索_日记', '线索_墓碑', '线索_收买', '目击_人影'] as $f) if ($has($f)) { $hyp = true; break; }
        if ($hyp && !$has('结局_已交代')) $it[] = $act('监视老宅（通宵）', 'watch');
        /* 事后补接：不要求"见过委托人"——自己查过、蹲过、撞见过，都能来补手续 */
        if (($pc['mission'] ?? '') === '' && !in_array('m_chase_01', (array) ($pc['mission_done'] ?? []), true)) {
            $involved = $has('见过委托人');
            foreach (['线索_窗锁','线索_日记','线索_墓碑','线索_收买','线索_足迹','目击_人影',
                      '遭遇_交谈','遭遇_击倒','遭遇_逃亡','擅自行动_追书人'] as $f) if ($has($f)) { $involved = true; break; }
            if ($involved) $it[] = $act('补接这桩委托（事后）', 'accept_late');
        }
        if (($has('遭遇_交谈') || $has('遭遇_击倒') || $has('遭遇_逃亡')) && !$has('结局_已交代')) {
            $it[] = $act('向李承业交代结果', 'report');
        }
    }
    if ($room === 'tw_grave') {
        $it[] = $act('与马占魁搭话', 'sexton');
        if ($has('线索_酒瓶') && !$has('结局_已交代')) {
            $it[] = $act('递包烟（贿赂，免检定）', 'sexton_bribe');
            $it[] = $act('拿酒瓶要挟（恐吓／困难说服）', 'sexton_press');
        }
        $hyp = false;
        foreach (['线索_窗锁', '线索_日记', '线索_墓碑', '线索_收买', '目击_人影'] as $f) if ($has($f)) { $hyp = true; break; }
        if ($hyp && !$has('结局_已交代')) $it[] = $act('监视公墓（通宵）', 'watch');
    }
    if ($room === 'tw_tomb') {
        if (!$has('门已开') && !$has('结局_已交代')) $it[] = $act('用力推石门（力量）', 'door');
        elseif (!$has('结局_已交代')) {
            $it[] = $act('屏息进去（免受恶臭）', 'enter_hold');
            $it[] = $act('不管不顾进去（会昏厥）', 'enter');
        }
    }
    if ($room === 'tw_tunnel') {
        if ($has('遭遇_结束')) $it[] = $act('沿斜坡回地面', 'surface');
        else $it[] = $act('沿通道往前走（导航）', 'tunnel');
    }
    if ($has('遭遇_进行中') && !$has('遭遇_结束') && in_array($room, ['tw_grave', 'tw_tunnel', 'tw_alley_s', 'tw_li_house'], true)) {
        $it[] = $act('开口交谈', 'talk');
        $it[] = $act('动手（转入战斗）', 'attack');
        $it[] = $act('悄悄退走', 'retreat');
    }
    return $it;
}

/* ================= 六、合约 ================= */
crrg_drill_register_mission('m_chase_01', [
    'name' => '追书人', 'level' => '中', 'kind' => 'npc', 'min_lv' => 1,
    'party' => ['min' => 1, 'max' => 2],
    'scene' => 'town', 'brief_room' => 'tw_li_house', 'brief_name' => '李家老宅',
    'depart_room' => 'tw_li_house', 'entry_room' => 'tw_li_house',
    'prompt' => '李承业家进了贼，丢的是他叔叔李道生最喜欢的几本书。他叔叔一年前失踪，杳无音信。'
              . '他要你找出偷书的人、追回书，顺便查一查他叔叔是死是活。办案花销他出，事成另给 1500 元。',
    'steps' => [
        ['flag' => '见过委托人', 'text' => '受理这桩委托'],
        ['any'  => ['线索_实据'], 'text' => '拿到站得住的实据'],
        ['any'  => ['遭遇_交谈', '遭遇_击倒', '遭遇_逃亡'], 'text' => '与夜里的身影了结'],
        ['flag' => '结局_已交代', 'text' => '向委托人交代'],
    ],
    'require_flags' => ['结局_已交代'],
    'bonus_flags' => ['样本_已寄回' => ['credits' => 10, 'why' => '样本已寄回基地']],
    'evidence' => ['线索_日记', '线索_足迹', '线索_收买', '目击_人影', '线索_隧道口',
                    '遭遇_交谈', '遭遇_击倒'],
    'reward' => ['credits' => 25, 'money' => 1500],
    'settle_note' => '李承业如约付了 1500 元（途中有损坏，从中扣除）。'
                   . '此后再没人进过那间书房。至于要不要告诉他叔叔的事，是你自己的选择。',
]);


/* 战斗收场：对手清空 → 遭遇按结果收尾（供战斗引擎回调） */
function crrg_drill_on_fight_end(&$s, $uid) {
    $f = (array) ($s['flags'] ?? []);
    if (!in_array('遭遇_开打', $f, true)) return [];
    $s['flags'] = array_values(array_diff($f, ['遭遇_进行中']));
    if (!in_array('遭遇_击倒', (array) $s['flags'], true)) $s['flags'][] = '遭遇_击倒';
    if (!in_array('遭遇_结束', (array) $s['flags'], true)) $s['flags'][] = '遭遇_结束';
    $out = ['它倒在碑座旁边，怀里的书散了一地，油布摊开，纸页被土糊住大半。',
            '凑近看那张脸——像李道生，又不是。'];
    if (function_exists('crrg_chase_sight_lines')) foreach (crrg_chase_sight_lines() as $l) $out[] = $l;
    $out[] = '（想细看就说「查看 人影」；想就此收手，回老宅交代。）';
    return $out;
}


/* ==================================================================
 * 结局与世界变化（2026-09-27）
 * 口径：一切后果写进**本账号**的旗标（user_meta），房间描述与 NPC 台词按旗标改写；
 *       别人没做过这桩事，他们的老宅、公墓、守墓人一律照旧，任务也照样能接。
 * ================================================================== */

/** 现场改写：按旗标往房间描述里追加变化 */
function crrg_drill_room_extra($s, $pc, $room) {
    $f = (array) ($s['flags'] ?? []);
    $has = fn($x) => in_array($x, $f, true);
    $out = [];
    if (!in_array('结局_已交代', $f, true) && !$has('遭遇_结束')) return [];

    switch ($room) {
        case 'tw_li_study':
            if ($has('结局_已交代')) $out[] = '空档补上了几本杂志，灰痕被擦过。窗闩换成了新的。';
            elseif ($has('线索_空档')) $out[] = '架子上的空档还空着。';
            break;
        case 'tw_li_house':
            if ($has('结局_已交代')) $out[] = '堂屋的灯亮着，钥匙已经收回墙上。院里停着李承业那辆旧货车。';
            break;
        case 'tw_grave':
            if ($has('结局_已交代') && $has('遭遇_交谈')) $out[] = '半山腰那块矮碑被人挪正过，碑脚多了一小堆碎石。';
            if ($has('结局_已交代') && $has('遭遇_击倒')) $out[] = '坡西边有一片新翻过的土，插着几根树枝做记。';
            break;
        case 'tw_tomb':
            if ($has('结局_已交代') && $has('遭遇_交谈')) $out[] = '石门后面灌了水泥，缝里还泛着潮。门楣上有人用粉笔划了个叉。';
            break;
        case 'tw_tunnel':
            if ($has('结局_已交代') && $has('遭遇_交谈')) $out[] = '通道尽头那道口子被封住了，敲上去是实的。';
            break;
    }
    return $out;
}

/** 委托人后续话头（按「告知／隐瞒」分叉，dyn 动态取词） */
function crrg_chase_after_text($s, $pc) {
    $f = (array) ($s['flags'] ?? []);
    if (in_array('结局_告知', $f, true)) {
        return ['他把你让进堂屋，倒了茶，自己没喝。',
                '「这么说，他是自己走的。走的时候还好好的。」',
                '「我昨晚一宿没睡。今天好些了。」',
                '「他那几本书你要是有兴趣，拿两本去。我留着也读不懂。」'];
    }
    return ['「贼没再来过。我睡得着了。」他笑了一下。',
            '「我叔的事……你们查着了吗？」',
            '（你可以接着说，也可以就此打住。）'];
}

/** 守墓人的结案后说法（按你留下的痕迹分叉） */
function crrg_chase_sexton_after($s, $pc) {
    $f = (array) ($s['flags'] ?? []);
    if (in_array('遭遇_交谈', $f, true)) {
        return ['「老李那块碑我挪正了。」他蹲在坡上抽烟。',
                '「山腰那口陵有人来灌了水泥，说是镇上报的危房。」',
                '「我不管那些。坡上总算安静了。」'];
    }
    return ['「坡西那片土我给平了。」他把锹立在脚边。',
            '「夜里没人来了。这就好。」'];
}

/* 结算与收尾：交代完毕 → 写结局旗标；交谈结局按原模组补 1D6 理智 */
function crrg_chase_epilogue(&$s, &$pc, $mode) {
    $f = (array) ($s['flags'] ?? []);
    $has = fn($x) => in_array($x, $f, true);
    $set = function ($x) use (&$s) { if (!in_array($x, (array) $s['flags'], true)) $s['flags'][] = $x; };
    $out = [];
    if ($has('结局_已交代')) return ['这事你昨天已经跟他说过了。' . ($has('结局_告知') ? '他知道实情。' : '他只知道窃贼没再来。')];
    if (!$has('遭遇_交谈') && !$has('遭遇_击倒') && !$has('遭遇_逃亡')) return ['还没到交代的时候。'];

    if ($mode === 'truth') {
        $set('结局_告知');
        $out[] = '你把知道的都说了：那东西是他叔叔，书是它自己拿的，坡底下那条道正在封口。';
        $out[] = '李承业听完没说话，去院里站了很久。回来时只说了一句：「知道了。」';
    } else {
        $set('结局_隐瞒');
        $out[] = '你只说窃贼不会再来了，丢的书找不回。';
        $out[] = '他点点头，说那就这样吧，钱照付。';
    }
    /* 结局与选择 → 青石镇声望（按地点独立） */
    if (function_exists('crrg_rep_add')) {
        $dv = 0; $why = '';
        if ($has('遭遇_击倒'))       { $dv = -3; $why = '追书人：动手收场'; }
        elseif ($has('遭遇_交谈'))   { $dv = ($mode === 'truth') ? 6 : 4; $why = '追书人：' . ($mode === 'truth' ? '告知实情' : '只说窃贼不再来'); }
        elseif ($has('遭遇_逃亡'))   { $dv = 1;  $why = '追书人：知难而退'; }
        if ($dv) {
            $now = crrg_rep_add($pc, 'town_qingshi', $dv, $why);
            $out[] = sprintf('（青石镇声望 %+d → %d）', $dv, $now);
        }
    }
    $set('结局_已交代');
    if ($has('遭遇_交谈') && !$has('结局_理智已补')) {   // 原模组：得知它不会再回来 → 1D6 理智（只补一次）
        $set('结局_理智已补');
        $gain = crrg_coc_dice('1d6');
        $d = crrg_coc_derive($pc['attrs']);
        $pc['san'] = min($d['san_max'], (int) $pc['san'] + $gain);
        $out[] = sprintf('（事情了结，理智 +%d → %d/%d。）', $gain, $pc['san'], $d['san_max']);
    }
    return $out;
}
