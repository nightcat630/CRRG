<?php
/**
 * CRRG 演习系统 · 内容（纯数据）· 镇民对话定义（自 drill-mod-town-dlg.php 的 dlg_register 调用原样提取）
 */
if (!defined('ABSPATH')) exit;

/* 原键：town_barber */
function crrg_game_town_npc_1() {
    return [
    'name' => '理发师傅', 'alias' => ['师傅', '理发'], 'room' => 'tw_barber',
    'topics' => [
        ['id' => 'chat', 'label' => '镇上最近的事', 'q' => ['最近', '闲聊', '镇上', '有什么消息'], 'once' => true,
         'text' => ['「这几年人往外走，留下的都是老头老太太。」',
                    '「前街李家的侄子，跑运输的，前阵子说家里进贼。丢书，你说稀罕不。」']],
        ['id' => 'li', 'label' => '李家的事', 'q' => ['李家', '李承业', '李道生'], 'once' => true,
         'text' => ['「李道生？那人我给他理过二十年头。」',
                    '「他有个毛病——看书。走道也看，我这儿也看，剪头发都得把书搁膝头上。」',
                    '「后来就不来了。听说是没了。」']],
        ['id' => 'slope', 'label' => '后坡的传闻', 'q' => ['后坡', '公墓', '传闻', '怪事'],
         'need' => ['any:线索_邻居|线索_阅览室|线索_融媒体|线索_收买'],
         'text' => ['师傅手里剪子停了停，压低声音。',
                    '「你别说是我说的——早些年，后坡那边出过事。有人说夜里看见人影。」',
                    '「具体我不清楚，别问我。你要问，去活动室找老周他们，那帮人嘴碎。」'],
         'sets' => ['线索_镇民闲话']],
    ],
];
}

/* 原键：town_tailor */
function crrg_game_town_npc_2() {
    return [
    'name' => '裁缝', 'alias' => ['缝纫', '做衣服'], 'room' => 'tw_tailor',
    'topics' => [
        ['id' => 'work', 'label' => '做件什么（成衣）', 'q' => ['做衣服', '成衣', '工装'], 'once' => true,
         'text' => ['「帆布的结实，耐磨。你要下地干活就做帆布的。」',
                    '「样衣挂在墙上，自己看。钱先付一半。」']],
        ['id' => 'cloth', 'label' => '布从哪来', 'q' => ['布', '料子', '哪来的'], 'once' => true,
         'text' => ['「县里拉来的。前阵子路不好，车晚了两天。」']],
        ['id' => 'news', 'label' => '谁家夜里出去过', 'q' => ['夜里', '谁家', '出门', '车'],
         'text' => ['「夜里出去的车？李家侄子常跑夜路，那是营生。」',
                    '「再就是收山货的，前阵子来过两趟，往西边去了。」']],
    ],
];
}

/* 原键：town_repairman */
function crrg_game_town_npc_3() {
    return [
    'name' => '修车师傅', 'alias' => ['修车', '师傅'], 'room' => 'tw_repair',
    'topics' => [
        ['id' => 'tire', 'label' => '这车怎么了', 'q' => ['车', '轮胎', '怎么了'], 'once' => true,
         'text' => ['「皮卡，减震废了。跑山路跑废的。」',
                    '「谁的车？山外拉货的。跟我打过招呼就放这儿了。」']],
        ['id' => 'road', 'label' => '去后山的路', 'q' => ['后山', '路', '坡上'], 'once' => true,
         'text' => ['「往后山？车开不上去，坡太陡。走上去二十分钟。」',
                    '「那坡我十年不去了。现在除了看坟的老马，没人往那儿去。」']],
        ['id' => 'food_oil', 'label' => '能不能借个手电', 'q' => ['手电', '工具', '借'], 'once' => true,
         'text' => ['「手电有，你要用就拿去。别给我摔了。」',
                    '（师傅把一支旧手电塞给你。）']],
    ],
];
}

/* 原键：town_bathman */
function crrg_game_town_npc_4() {
    return [
    'name' => '澡堂老板', 'alias' => ['老板', '澡堂'], 'room' => 'tw_bath',
    'topics' => [
        ['id' => 'bath', 'label' => '澡堂的规矩', 'q' => ['澡堂', '规矩', '多少钱'], 'once' => true,
         'text' => ['「十五块，泡到你自己不想泡。毛巾押金五块。」']],
        ['id' => 'talk', 'label' => '池子里都在说什么', 'q' => ['说什么', '闲话', '池子'], 'once' => true,
         'text' => ['「还能说什么。地里的、厂里的、谁家闺女。」',
                    '「这两天说得最多的是后坡——有人说看见有人往上头搬东西。你自己听去吧。」'],
         'sets' => ['线索_镇民闲话']],
    ],
];
}

/* 原键：town_vet */
function crrg_game_town_npc_5() {
    return [
    'name' => '兽医', 'alias' => ['医生', '看牲口'], 'room' => 'tw_vet',
    'topics' => [
        ['id' => 'donkey', 'label' => '这头驴', 'q' => ['驴', '牲口', '怎么了'], 'once' => true,
         'text' => ['「崴了蹄子。老赵家的，拉磨拉了大半辈子。」',
                    '「我这儿看的是牲口，人不行。人你上卫生院。」']],
        ['id' => 'wild', 'label' => '坡上的野东西', 'q' => ['野', '坡上', '痕迹', '脚印'], 'once' => true,
         'text' => ['「后坡那片林子，早些年有野物。现在少了。」',
                    '「前阵子有人捡到过一截蹄印照片，模模糊糊的，说不清是啥。我看不像狗。」'],
         'sets' => ['线索_兽迹']],
    ],
];
}

/* 原键：town_miller */
function crrg_game_town_npc_6() {
    return [
    'name' => '磨坊主', 'alias' => ['磨坊', '磨面的'], 'room' => 'tw_mill',
    'topics' => [
        ['id' => 'mill', 'label' => '磨坊还转吗', 'q' => ['磨坊', '石磨', '还转吗'], 'once' => true,
         'text' => ['「转。就是人少了，一天来不了几个。」']],
        ['id' => 'river', 'label' => '河沿这一带', 'q' => ['河沿', '河', '这一带'], 'once' => true,
         'text' => ['「河不深，夏天有人下去捞鱼。往西走，坡根底下跟河湾挨着。」',
                    '「早些年发过一次水，把坡脚下的土冲开过。现在又长满了。」']],
    ],
];
}

/* 原键：town_oldman */
function crrg_game_town_npc_7() {
    return [
    'name' => '打牌的老人', 'alias' => ['老人', '老周', '打牌的'], 'room' => 'tw_club',
    'topics' => [
        ['id' => 'cards', 'label' => '牌局', 'q' => ['打牌', '牌局', '玩什么'], 'once' => true,
         'text' => ['「打升级。三缺一，你要坐就坐。」',
                    '（他没真让你坐，手里一直没停。）']],
        ['id' => 'old', 'label' => '镇上的旧事', 'q' => ['旧事', '以前', '早些年', '从前'], 'once' => true,
         'text' => ['「早些年这镇子热闹。粮站、供销社、车队，都有。」',
                    '「后来一样一样撤了。人也就散了。」']],
        ['id' => 'hill', 'label' => '后坡底下是什么', 'q' => ['后坡', '坡底下', '底下', '洞里'],
         'need' => ['any:线索_镇民闲话|线索_兽迹|线索_王秀兰|线索_阅览室|线索_融媒体'],
         'text' => ['老人把手里的牌扣在桌上。',
                    '「后坡底下是空的。老人讲的——早年间躲兵，人都往那里头钻。」',
                    '「道口在半山腰那几座老陵里。后来塌了，也没人再进去。」',
                    '「你问这个干嘛？……算了，我什么也没说。」'],
         'sets' => ['线索_地道传闻']],
    ],
];
}

/* 原键：town_watchman */
function crrg_game_town_npc_8() {
    return [
    'name' => '联防队员', 'alias' => ['联防', '巡逻'], 'room' => 'tw_watch',
    'topics' => [
        ['id' => 'patrol', 'label' => '巡逻的事', 'q' => ['巡逻', '值班', '路线'], 'once' => true,
         'text' => ['「就我们几个轮。一夜两趟，前街到河沿。」',
                    '「后坡不在线上——那儿没人住，出了事也没人知道。」']],
        ['id' => 'night', 'label' => '夜里见过什么', 'q' => ['夜里', '见过', '动静'],
         'skill' => ['说服', 2],
         'text' => ['他往炉子边凑了凑。',
                    '「上个月有一次，我巡到东头，看见有条人影从坡上下来，往李家那边去。」',
                    '「我喊了一声，人没了。第二天我去李家问，他家说没事。」',
                    '「我没往上报。报了也没用。」'],
         'sets' => ['线索_巡逻目击'],
         'fail' => ['「啥也没见过。你想多了。」他把登记簿合上。']],
    ],
];
}

/* 原键：town_seller */
function crrg_game_town_npc_9() {
    return [
    'name' => '供销社售货员', 'alias' => ['售货员', '售货'], 'room' => 'tw_coop',
    'topics' => [
        ['id' => 'buy', 'label' => '最近什么好卖', 'q' => ['好卖', '生意', '什么卖得好'], 'once' => true,
         'text' => ['「盐、酱油、电池。再就是蜡烛，前阵子停电。」']],
        ['id' => 'story', 'label' => '镇上的闲话', 'q' => ['闲话', '最近', '听说', '消息'], 'once' => true,
         'text' => ['「前街李家进贼那事，街面上都传。」',
                    '「丢的是书，别的没动。你要问这个，去问理发的老李，他嘴最碎。」']],
    ],
];
}

/* 原键：town_grainman */
function crrg_game_town_npc_10() {
    return [
    'name' => '粮油店老板', 'alias' => ['粮店', '老板'], 'room' => 'tw_grain',
    'topics' => [
        ['id' => 'price', 'label' => '今年的行情', 'q' => ['行情', '米价', '贵', '多少钱'], 'once' => true,
         'text' => ['「米比去年贵两毛。油价也是。」',
                    '「上头收粮的时候说不准，压着不卖的都亏了。」']],
        ['id' => 'him', 'label' => '李道生买过粮吗', 'q' => ['李道生', '买粮', '那个人'],
         'text' => ['「他？半个月来一趟，买得少。一个人吃不了多少。」',
                    '「去年秋天以后就没见着了。我还纳闷。」']],
    ],
];
}

/* 原键：town_noodle */
function crrg_game_town_npc_11() {
    return [
    'name' => '面馆老板娘', 'alias' => ['老板娘', '面馆'], 'room' => 'tw_noodle',
    'topics' => [
        ['id' => 'menu', 'label' => '今天有什么', 'q' => ['吃什么', '菜单', '今天'], 'once' => true,
         'text' => ['「牛肉面。牛肉不多，给你多舀一勺汤。」']],
        ['id' => 'regular', 'label' => '常客都有谁', 'q' => ['常客', '谁来吃', '熟客'],
         'text' => ['「看坟的老马，隔三差五来。还有联防队那几个。」',
                    '「李道生也来过，一个人坐角落，边吃边看书。」']],
    ],
];
}

/* 原键：town_teller */
function crrg_game_town_npc_12() {
    return [
    'name' => '信用社柜员', 'alias' => ['柜员', '信用社'], 'room' => 'tw_bank',
    'topics' => [
        ['id' => 'save', 'label' => '存取的事', 'q' => ['存钱', '取钱', '利息'], 'once' => true,
         'text' => ['「现在利息低。你要存款得带身份证。」']],
        ['id' => 'account', 'label' => '这里能查旧账吗', 'q' => ['查账', '旧账', '记录'], 'once' => true,
         'text' => ['「个人的不给查。要看你也得有关系。」',
                    '（她看了你一眼，把窗口的小牌子扶正了。）']],
    ],
];
}

/* 原键：town_doctor2 */
function crrg_game_town_npc_13() {
    return [
    'name' => '卫生院医生', 'alias' => ['医生', '卫生院'], 'room' => 'tw_clinic',
    'topics' => [
        ['id' => 'sick', 'label' => '最近有什么病人', 'q' => ['病人', '看病', '最近'], 'once' => true,
         'text' => ['「感冒的多，秋里换季。」',
                    '「再就是睡不着的。赵家老太太，一宿一宿地坐着。」']],
        ['id' => 'insomnia', 'label' => '睡不着的人', 'q' => ['睡不着', '失眠', '睡不好'],
         'text' => ['「睡不着多半是心里有事。药能顶一阵，不治根。」',
                    '「后坡底下住过的那家老太太，也是这个病。后来搬走了。」'],
         'sets' => ['线索_失眠旁证']],
    ],
];
}

/* 原键：town_clerk */
function crrg_game_town_npc_14() {
    return [
    'name' => '镇政府办事员', 'alias' => ['办事员', '镇政府'], 'room' => 'tw_townhall',
    'topics' => [
        ['id' => 'work', 'label' => '要开什么证明', 'q' => ['证明', '盖章', '办事'], 'once' => true,
         'text' => ['「带身份证和户口本。介绍信要单位盖章。」']],
        ['id' => 'hill', 'label' => '后坡归谁管', 'q' => ['后坡', '公墓', '归谁', '土地'], 'once' => true,
         'text' => ['「公墓归镇上。地是村上的，早年划出去一块。」',
                    '「你要看地籍图？……得走手续，你先说你要干什么。」']],
    ],
];
}

add_action('init', function () {
    if (!function_exists('crrg_game_register_many')) return;
    crrg_game_register_many('npc', [
        'town_barber' => crrg_game_town_npc_1(),
        'town_tailor' => crrg_game_town_npc_2(),
        'town_repairman' => crrg_game_town_npc_3(),
        'town_bathman' => crrg_game_town_npc_4(),
        'town_vet' => crrg_game_town_npc_5(),
        'town_miller' => crrg_game_town_npc_6(),
        'town_oldman' => crrg_game_town_npc_7(),
        'town_watchman' => crrg_game_town_npc_8(),
        'town_seller' => crrg_game_town_npc_9(),
        'town_grainman' => crrg_game_town_npc_10(),
        'town_noodle' => crrg_game_town_npc_11(),
        'town_teller' => crrg_game_town_npc_12(),
        'town_doctor2' => crrg_game_town_npc_13(),
        'town_clerk' => crrg_game_town_npc_14(),
    ], ['module' => 'core', 'file' => __FILE__]);
}, 21);
