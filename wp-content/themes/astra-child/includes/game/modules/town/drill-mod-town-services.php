<?php
/**
 * 青石镇 · 建筑功能层（2026-09-28）
 * 原则：每栋建筑面积都要能**做一件有机制后果的事**——挣钱、治伤、查档、换情报、
 *       影响检定、改变任务结算。纯摆设的描写不进这里。
 * 全部按账号：钱、存款、次数、旗标都写在自己的卡/状态里。
 */
if (!defined('ABSPATH')) exit;

/* ================= 一、通用小工具 ================= */
function crrg_town_flag(&$s, $f) { return in_array($f, (array) ($s['flags'] ?? []), true); }
function crrg_town_set(&$s, $f) { if (!in_array($f, (array) $s['flags'], true)) $s['flags'][] = $f; }
function crrg_town_give(&$pc, $id, $n = 1) { crrg_drill_bag_add_n($pc, $id, $n); }
function crrg_town_pay(&$pc, $n) { $pc['money'] = max(0, (int) $pc['money'] - $n); }
function crrg_town_earn(&$pc, $n) { $pc['money'] = (int) $pc['money'] + $n; }
function crrg_town_times(&$s, $key, $date = null) {
    $date = $date ?: crrg_town_today();
    return (int) ($s['times'][$key . '_' . $date] ?? 0);
}
function crrg_town_bump(&$s, $key) {
    if (!isset($s['times']) || !is_array($s['times'])) $s['times'] = [];
    $k = $key . '_' . crrg_town_today();
    $s['times'][$k] = (int) ($s['times'][$k] ?? 0) + 1;
}

/* ================= 二、各建筑的功能 ================= */

/** 信用社：存取款、查旧账（查账需镇政府证明） */
function crrg_bank_act(&$s, &$pc, $id) {
    $bank = (int) ($pc['bank'] ?? 0);
    switch ($id) {
        case 'deposit':
            if ((int) $pc['money'] < 100) return ['存一百起。'];
            $pc['bank'] = $bank + (int) $pc['money'];
            $n = (int) $pc['money']; $pc['money'] = 0;
            return [sprintf('你把 %d 元存进信用社，柜员点了两遍，打了一张条子。', $n),
                    sprintf('（存款 %d 元。带着现金出门，丢了就是丢了。）', $pc['bank'])];
        case 'withdraw':
            if ($bank < 100) return ['取一百起。'];
            $n = min($bank, 500);
            $pc['bank'] = $bank - $n; crrg_town_earn($pc, $n);
            return [sprintf('你取了 %d 元。', $n), sprintf('（存款剩 %d 元。）', $pc['bank'])];
        case 'record':
            if (!crrg_town_flag($s, '镇府_证明')) return ['「查个人账户的往来？得有镇上开的证明。」'];
            if (crrg_town_flag($s, '线索_旧账')) return ['「那条记录你抄过了。」'];
            crrg_town_set($s, '线索_旧账');
            return ['柜员翻出一本旧登记簿，指着去年十月的一条。',
                    '「李道生，取空了。之后没再动过。」',
                    '「他取钱那天，后头跟着一个人签字——签的是李家侄子的名。」',
                    '（这条对不上：李承业说他叔失踪前后没动过家里的账。）'];
        default: return ['信用社的窗口开着。'];
    }
}

/** 邮电所：汇款、寄样本、向基地求援 */
function crrg_post_act(&$s, &$pc, $ctx, $id) {
    switch ($id) {
        case 'remit':   // 汇款：身上的钱→基地账户（等于存款）
            if ((int) $pc['money'] < 50) return ['汇款五十起，还要手续费。'];
            $n = (int) $pc['money']; $pc['bank'] = (int) ($pc['bank'] ?? 0) + $n; $pc['money'] = 0;
            return [sprintf('你在柜台填了汇款单，把 %d 元汇回基地账户。', $n),
                    sprintf('（存款 %d 元。手续费已扣。）', $pc['bank'])];
        case 'mail_sample':   // 寄样本回基地：远程完成取样提交（两种样本都收）
            $sid = crrg_drill_bag_count($pc, 'key_sample') > 0 ? 'key_sample'
                 : (crrg_drill_bag_count($pc, 'key_sample_soil') > 0 ? 'key_sample_soil' : '');
            if ($sid === '') return ['你身上没有样本要寄。'];
            if (crrg_town_flag($s, '样本_已寄回')) return ['样本已经寄出去了。'];
            crrg_town_pay($pc, 30);
            crrg_drill_bag_del_n($pc, $sid, 1);
            crrg_town_set($s, '样本_已寄回');
            return ['你把样本盒装进泡沫箱，办了 EMS 寄回基地。三十块。',
                    '（基地收到后会走流程；这条可以在交报告时少跑一趟。）'];
        case 'call_base':     // 打电话求援：花积分调装备
            if (crrg_town_times($s, '求援') >= 1) return ['今天已经打过一次电话了。'];
            if ((int) $pc['credits'] < 20) return ['调一件装备要 20 积分。'];
            $pc['credits'] = (int) $pc['credits'] - 20;
            crrg_town_bump($s, '求援');
            crrg_town_give($pc, 'tool_flashlight', 1);
            crrg_town_give($pc, 'med_bandage', 2);
            return ['你借邮电所的电话打到基地值班室，报了代号。',
                    '对面说：东西明早的班车捎过来。',
                    '（战术手电 ×1、绷带 ×2 已随班车送到。）'];
        case 'telegram':      // 给基地发电报：留句话（不消耗，纯记录）
            crrg_town_pay($pc, 20);
            return ['你按字计费发了封电报：位置青石镇，事有眉目，暂无异状。',
                    '（基地会记一条你的动向。）'];
        default: return ['邮电所开着，柜台后面有人在打毛线。'];
    }
}

/** 卫生院：处置轻伤、处理重伤、住院观察 */
function crrg_clinic_act(&$s, &$pc, $id) {
    /* 2026-09-29 修：面板送的是 clinic|treat，而这里写的是 case 'clinic' →
       'treat' 落进 default，只回一句"值班表上今天只有一个名字"。 */
    if ($id === 'clinic') $id = 'treat';
    $d = crrg_coc_derive($pc['attrs']);
    switch ($id) {
        case 'treat':
        case 'clinic':
        case 'fate_radical_treat':
            if (crrg_town_times($s, '门诊') >= 1) return ['今天已经看过了。'];
            if ((int) $pc['money'] < 50) return ['门诊五十。'];
            crrg_town_pay($pc, 50);
            crrg_town_bump($s, '门诊');
            $gain = crrg_coc_dice('1d6');
            $pc['hp'] = min($d['hp_max'], (int) $pc['hp'] + $gain);
            return [sprintf('大夫看了看，给你上了药、包了扎。'), sprintf('（生命 +%d → %d/%d。）', $gain, $pc['hp'], $d['hp_max'])];
        case 'major':
            if (!in_array('重伤', (array) ($s['flags'] ?? []), true) && !crrg_town_flag($s, '重伤中')) {
                return ['大夫问：谁伤了？你这不好好的。'];
            }
            if ((int) $pc['money'] < 300) return ['处置重伤三百。'];
            crrg_town_pay($pc, 300);
            crrg_town_set($s, '重伤已处置');
            return ['大夫把你按在诊疗床上，重新清创、缝合、打了针。',
                    '（重伤状态解除；今天不能再折腾了。）'];
        case 'rest':
            if (crrg_town_times($s, '住院') >= 1) return ['今天已经躺过了。'];
            if ((int) $pc['money'] < 200) return ['留观一天两百。'];
            crrg_town_pay($pc, 200);
            crrg_town_bump($s, '住院');
            $pc['san'] = min($d['san_max'], (int) $pc['san'] + 2);
            return ['你在留观室的空床上躺了一天，窗外的杨树一直在响。',
                    sprintf('（理智 +2 → %d。）', $pc['san'])];
        default: return ['卫生院的值班表上今天只有一个名字。'];
    }
}

/** 新华书店：买书研读（涨技能）、查县志 */
function crrg_book_act(&$s, &$pc, $id) {
    switch ($id) {
        case 'read':
            if (crrg_town_times($s, '研读') >= 1) return ['今天读不动了。'];
            if ((int) $pc['money'] < 120) return ['专业书一百二，平装的。'];
            crrg_town_pay($pc, 120);
            crrg_town_bump($s, '研读');
            $sk = $pc['skills'] ?: crrg_coc_skill_base($pc['attrs']);
            $pick = ['侦查', '图书馆使用', '心理学', '说服'][array_rand(['0', '1', '2', '3'])];
            $pc['skills'] = $sk;
            $pc['skills'][$pick] = min(95, (int) ($sk[$pick] ?? 0) + 2);
            return [sprintf('你买了本《现场勘查与询问技巧》，在旅社翻到半夜。'),
                    sprintf('（%s +2 → %d%%。每日限一次。）', $pick, $pc['skills'][$pick])];
        case 'gazetteer':
            if (crrg_town_flag($s, '线索_县志')) return ['县志你翻过了。'];
            crrg_town_set($s, '线索_县志');
            return ['你抽出硬壳的《青石镇志》，翻到「山川」一节。',
                    '上面写着：镇西后坡，旧称「坟坡」，清末民初有「穴居避兵」的记载，',
                    '注一句——「洞口在旧茔之内，后封」。',
                    '（册页边上有人用铅笔写过小字：一九六三年复勘，未开。）'];
        default: return ['书架上多是教辅和农业技术。'];
    }
}

/** 照相馆：冲洗证据（把记录板数据变成可用物件） */
function crrg_photo_act(&$s, &$pc, $id) {
    switch ($id) {
        case 'doc_film':
            if ((int) $pc['money'] < 40) return ['一卷四十。'];
            crrg_town_pay($pc, 40);
            crrg_town_give($pc, 'doc_film', 1);
            return ['你买了一卷胶卷。'];
        case 'develop':
            if (crrg_drill_bag_count($pc, 'doc_film') <= 0) return ['得先有胶卷，或者把记录板的数据带过来。'];
            if (crrg_town_flag($s, '照片_已冲洗')) return ['已经冲过了。'];
            crrg_town_pay($pc, 20);
            crrg_drill_bag_del_n($pc, 'doc_film', 1);
            crrg_town_set($s, '照片_已冲洗');
            crrg_town_give($pc, 'doc_photos', 2);
            return ['师傅把胶卷推进暗房，半小时后端出两张照片。',
                    '（得到「现场照片」×2。这种东西交给县里的记者，能换钱；留在手上，也能当凭据。）'];
        default: return ['背景布还挂着，是块褪色的山水画。'];
    }
}

/** 五金店：买撬棍、买绳子、买绳梯 */
function crrg_hw_act(&$s, &$pc, $id) {
    switch ($id) {
        case 'tool_prybar':
            if ((int) $pc['money'] < 90) return ['撬棍九十。'];
            crrg_town_pay($pc, 90);
            crrg_town_give($pc, 'tool_prybar', 1);
            return ['你挑了根一米长的撬棍，掂了掂。',
                    '（撬门、撬石板时好用：推开重物的力量检定有奖励骰。）'];
        case 'tool_rope_nylon':
            if ((int) $pc['money'] < 60) return ['绳子六十。'];
            crrg_town_pay($pc, 60);
            crrg_town_give($pc, 'tool_rope_nylon', 1);
            return ['一盘二十米的尼龙绳。',
                    '（下有坡有洞时好用：地下通道里的导航检定有奖励骰。）'];
        case 'tool_ladder':
            if ((int) $pc['money'] < 120) return ['绳梯一百二。'];
            crrg_town_pay($pc, 120);
            crrg_town_give($pc, 'tool_ladder', 1);
            return ['一卷带木踏板的绳梯。', '（从高处上下时好用。）'];
        default: return ['钉子铁丝按号卖，工具挂在里墙。'];
    }
}

/** 磨坊：帮工赚钱 */
function crrg_mill_act(&$s, &$pc, $id) {
    if (crrg_town_times($s, '帮工') >= 1) return ['磨坊主说今天够了，明天再来。'];
    crrg_town_bump($s, '帮工');
    $pay = 30 + crrg_coc_dice('2d6');
    crrg_town_earn($pc, $pay);
    return ['你帮着扛了半日麦袋，肩膀磨红。',
            sprintf('（工钱 %d 元。每日一次。）', $pay)];
}

/** 文化活动室：打牌（赌）、听闲话 */
function crrg_club_act(&$s, &$pc, $id) {
    switch ($id) {
        case 'gamble':
            if (crrg_town_times($s, '打牌') >= 1) return ['今天不打了，牌桌上的人都散了。'];
            if ((int) $pc['money'] < 50) return ['押注五十起。'];
            crrg_town_bump($s, '打牌');
            crrg_town_pay($pc, 50);
            $win = crrg_coc_dice('1d100') <= 45;
            if ($win) { crrg_town_earn($pc, 120); return ['你替人打了两圈，赢了三把。', '（+120 元。）']; }
            return ['你牌运不好，五十块打了水漂。', '（−50 元。）'];
        case 'gossip':
            if (crrg_town_flag($s, '线索_镇民闲话')) return ['闲话你听过了。'];
            crrg_town_set($s, '线索_镇民闲话');
            return ['你把烟递过去，牌桌歇了一轮。',
                    '「后坡那事，早年间出过。有人说夜里见过人影，往李家那边去。」',
                    '「你要真想知道，去问老周——他们家那辈人是住在坡底下的。」'];
        default: return ['一桌打牌，两桌看。'];
    }
}

/** 联防队小屋：报案（换取夜间巡逻支援） */
function crrg_watch_act(&$s, &$pc, $id) {
    switch ($id) {
        case 'roster':
            crrg_town_set($s, '线索_巡逻表');
            return ['墙上贴着巡逻表：一夜两趟，前街到河沿，后坡不在线上。',
                    '（知道路线之后，夜间蹲守不容易被人撞见。）'];
        case 'report':
            if (!crrg_town_flag($s, '线索_巡逻目击') && !crrg_town_flag($s, '线索_墓碑') && !crrg_town_flag($s, '线索_收买')) {
                return ['「你要报什么？得有东西。」'];
            }
            if (crrg_town_flag($s, '联防_已报案')) return ['已经报过了，他们在排班。'];
            crrg_town_set($s, '联防_已报案');
            $rep = '';
            if (function_exists('crrg_rep_add')) { $n2 = crrg_rep_add($pc, 'town_qingshi', 2, '向联防队报了案、配合工作'); $rep = sprintf('（青石镇声望 +2 → %d）', $n2); }
            return ['队员记了你说的地名，末了说：「那我们今晚上后坡转一趟。」', $rep,
                    '（夜间蹲守会有人搭手：运气检定有奖励骰。）'];
        case 'borrow':
            if ((int) $pc['money'] < 20) return ['押二十块，归还时退。'];
            crrg_town_pay($pc, 20);
            crrg_town_give($pc, 'tool_flashlight', 1);
            return ['你借了队上一只手电，押金二十。', '（战术手电 ×1。）'];
        default: return ['炉子边有人烤手。'];
    }
}

/** 老祠堂：撬锁进正厅（族谱与祭器） */
function crrg_temple_act(&$s, &$pc, $ctx, $id) {
    switch ($id) {
        case 'break':
            if (crrg_town_flag($s, '祠堂_已开')) return ['门已经开了。'];
            $r = crrg_coc_check('机械维修', max(1, crrg_coc_skill_val($pc, '机械维修')));
            if (crrg_coc_level_rank($r['level']) < 2) {
                $r2 = crrg_coc_check('力量', max(1, crrg_coc_skill_val($pc, '力量')));
                if (crrg_coc_level_rank($r2['level']) < 2) {
                    return ['锁是新的，锁孔却很旧。你摆弄半天，没开。',
                            '（撬棍之类的工具可能有用。）'];
                }
                crrg_town_set($s, '祠堂_已开');
                return ['你用肩膀把门顶开，门轴发出一声惨叫。'];
            }
            crrg_town_set($s, '祠堂_已开');
            return ['你用两根铁丝把锁拨开了。锁是新的，锁孔是旧的。'];
        case 'clan':
            if (!crrg_town_flag($s, '祠堂_已开')) return ['门锁着。'];
            if (crrg_town_flag($s, '线索_族谱')) return ['族谱你翻过了。'];
            crrg_town_set($s, '线索_族谱');
            return ['正厅供桌上有一函族谱。你翻到李氏一支，末页夹着张纸条：',
                    '「道生迁出，别居。」落款是去年秋天——他失踪的那阵子。',
                    '（这条能改动你对委托人那套说法的分量。）'];
        case 'relic':
            if (!crrg_town_flag($s, '祠堂_已开')) return ['门锁着。'];
            if (crrg_town_flag($s, '祠堂_取过')) return ['供桌上没什么了。'];
            crrg_town_set($s, '祠堂_取过');
            crrg_town_give($pc, 'sigil', 1);
            return ['供桌角上摆着一枚旧铜牌，背面刻着看不懂的纹。',
                    '（得到「镇物封条」×1 一类的小东西——基地会有兴趣。）'];
        default: return ['门锁着，锁上那层漆是新的。'];
    }
}

/** 镇政府：开证明、查地籍图 */
function crrg_townhall_act(&$s, &$pc, $id) {
    switch ($id) {
        case 'cert':
            if (crrg_town_flag($s, '镇府_证明')) return ['证明你已经拿过了。'];
            crrg_town_set($s, '镇府_证明');
            return ['办事员在表格上盖了章：「用途」一栏写的是「查档」。',
                    '（凭这张证明，信用社能给你查旧账。）'];
        case 'land':
            if (crrg_town_flag($s, '线索_地籍')) return ['图你已经看过了。'];
            if (!crrg_town_flag($s, '镇府_证明')) return ['「看地籍得走手续，先开证明。」'];
            crrg_town_set($s, '线索_地籍');
            return ['办事员抽出后坡一带的地籍图，摊在桌上。',
                    '图纸上，半山腰那三座老陵被红笔圈过，旁边写：「危房，建议封堵。二〇〇一年」。',
                    '「一直没封。谁去封？没钱。」'];
        default: return ['办事员在盖章。'];
    }
}


/** 融媒体：卖现场照片给记者（照片的唯一变现口） */
function crrg_rep_from_junk(&$pc) {   // 废品站出手关键物：镇上有人看见
    if (function_exists('crrg_rep_add')) crrg_rep_add($pc, 'town_qingshi', -2, '在废品站出手来路不明的东西');
}
function crrg_press_act(&$s, &$pc, $id) {
    if ($id !== 'sell') return ['采编点在二楼上。'];
    $n = crrg_drill_bag_count($pc, 'doc_photos');
    if ($n <= 0) return ['赵记者问：照片呢？'];
    crrg_drill_bag_del_n($pc, 'doc_photos', $n);
    $pay = 300 * $n;
    crrg_town_earn($pc, $pay);
    crrg_town_set($s, '照片_已出手');
    return [sprintf('赵记者把照片摊在桌上看了很久，最后按每张 300 收了 %d 张。', $n),
            sprintf('（+%d 元。）', $pay),
            '「这种东西，我们不发。但你送来得对。」'];
}


/** 裁缝铺：做一件帆布护具（战斗减伤） */
function crrg_tailor_act(&$s, &$pc, $id) {
    if ($id !== 'gear_vest') return ['案板上摊着裁开的布。'];
    if (crrg_drill_bag_count($pc, 'gear_vest') > 0 || in_array('帆布护具', (array) ($pc['worn'] ?? []), true)) {
        return ['你身上已经有一件了。'];
    }
    if ((int) $pc['money'] < 80) return ['手工费八十。'];
    crrg_town_pay($pc, 80);
    crrg_town_give($pc, 'gear_vest', 1);
    return ['裁缝量了你的肩宽，用双层帆布加了两道缝线。',
            '（得到「帆布护具」：穿上后战斗减伤 1。跟 T3 防护服可以叠。）'];
}


/** 兽医站：买兽用镇静剂（战斗道具） */
function crrg_vet_act(&$s, &$pc, $id) {
    if ($id !== 'med_sedative') return ['院里拴着一头瘸腿的驴。'];
    if ((int) $pc['money'] < 150) return ['一支一百五。兽医说这东西管制，得登记。'];
    if (crrg_drill_bag_count($pc, 'med_sedative') >= 3) return ['兽医说这东西管制，一人最多三支。'];
    crrg_town_pay($pc, 150);
    crrg_town_give($pc, 'med_sedative', 1);
    return ['兽医从铁柜里取出一支粗针管，让你在登记簿上写名字和用途。',
            '他写「兽用」两个字的时候看了你一眼，没多问。',
            '（得到「兽用镇静剂」：战斗中扎中目标，可让它昏睡 1–3 轮。检定用急救。）'];
}

/** 裁缝铺：改工装（影响镇上社交检定） */
function crrg_outfit_act(&$s, &$pc, $id) {
    $sets = [
        'work'   => ['n' => '整洁工装', 'd' => '熨过的深色工装，袖口齐整。办事、打交道都体面。'],
        'casual' => ['n' => '便装',     'd' => '普通夹克牛仔裤。不出挑，也不扎眼。'],
        'dirty'  => ['n' => '油污旧衣', 'd' => '沾着机油的旧夹克。上等人嫌你，下等人当你是自己人。'],
    ];
    if (!isset($sets[$id])) return ['案板上摊着裁开的布。'];
    if ((int) $pc['money'] < 40) return ['手工费四十。'];
    crrg_town_pay($pc, 40);
    $pc['outfit'] = $sets[$id]['n'];
    return ['裁缝量了尺寸，把衣服改了一遍。', '（外观：' . $sets[$id]['n'] . '——' . $sets[$id]['d'] . '）'];
}

/** 工装对镇上社交检定的修正：[奖励骰, 惩罚骰, 说明] */
function crrg_outfit_mod($pc, $skill, $npc_id = '') {
    $o = (string) ($pc['outfit'] ?? '');
    if ($o === '') return [0, 0, []];
    $social = ['外貌', '信用评级', '说服', '魅惑', '话术'];
    if (!in_array($skill, $social, true)) return [0, 0, []];
    $low = ['town_barber', 'town_watchman', 'town_repairman', 'town_miller', 'town_oldman', 'ma_zankui', 'town_seller'];
    $high = ['officer', 'town_clerk', 'town_teller', 'town_doctor2', 'librarian', 'press_zhao'];
    if ($o === '整洁工装') {
        if (in_array($npc_id, $high, true)) return [1, 0, ['整洁工装 +1']];
        if (in_array($npc_id, $low, true))  return [0, 1, ['工装太齐整，他们看着别扭 −1']];
    }
    if ($o === '油污旧衣') {
        if (in_array($npc_id, $low, true))  return [1, 0, ['一身油污，他们不设防 +1']];
        if (in_array($npc_id, $high, true)) return [0, 1, ['这身打扮，人家皱眉 −1']];
    }
    return [0, 0, []];
}
