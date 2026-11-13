<?php
// 智能随机发帖 v5 — 预设话题(带论坛)+AI生成回复
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';

$users = [958,959,960,961,962,966,967,968,969,970];
$forums = [166,167,168,169,170];
$hour = (int)date('H');

define('DS_KEY', 'sk-5150a9631d9740ddac9a7e65b8731d21');
define('DS_URL', 'https://api.deepseek.com/chat/completions');

$persona_cache = [];
function get_persona($name) {
    global $persona_cache;
    if (isset($persona_cache[$name])) return $persona_cache[$name];
    foreach (glob('/root/重生抵御小组网站/员工人设/*.md') as $f) {
        if (strpos(file_get_contents($f), $name) !== false) {
            $persona_cache[$name] = file_get_contents($f);
            return $persona_cache[$name];
        }
    }
    return "小组普通成员。";
}

function call_ds($system, $prompt, $tokens = 120) {
    $data = json_encode(["model"=>"deepseek-chat","messages"=>[["role"=>"system","content"=>$system],["role"=>"user","content"=>$prompt]],"max_tokens"=>$tokens,"temperature"=>0.85]);
    $ch = curl_init(DS_URL);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>["Content-Type: application/json","Authorization: Bearer ".DS_KEY],CURLOPT_POSTFIELDS=>$data,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
    $r = curl_exec($ch); curl_close($ch);
    if(!$r) return false;
    $j = json_decode($r,true);
    return $j['choices'][0]['message']['content'] ?? false;
}

// 时间筛选
$night = [961,958,968];
$available = ($hour>=0&&$hour<6) ? $night : (($hour>=6&&$hour<9) ? [967,960,958] : $users);
$author_id = $available[array_rand($available)];
$author_name = get_userdata($author_id)->display_name;

// 话题库（标题+内容+论坛ID）— 严格按板块分类
$all_topics = [
    // 行动指挥部(166): 巡逻/值班/装备/调度
    166 => [
        ["本周巡逻数据汇总","各线路本周巡逻数据已汇总完毕。秦岭线路巡查三次，北坡双人岗运行正常，东侧山脊C线孙亦然同志汇报灌木过密需清理。请各组确认下周值班安排。"],
        ["关于规范巡逻汇报格式的通知","近期收到多份格式不统一的巡逻汇报，不利于汇总。请统一使用附件模板，重点标注异常数据和发现时间。模板已发各组长邮箱。"],
        ["下周装备维护计划","下周安排：一、通讯设备全面检修（王守一负责）；二、便携检测仪电池更换（何若风协助）；三、驱兽喷雾库存盘点（周三前完成）。"],
        ["北坡双人岗第一周运行评估","北坡双人夜间岗运行一周，整体平稳。值班人员反馈夜间视野受限，建议增配头灯。另孙亦然同志申请补发手电筒——已批准。"],
        ["秦岭采石场复查计划","本周三安排秦岭采石场复测。周知行和孙亦然已报名。技术保障组何若风负责设备调试。出发时间07:00，预计12:00前返回。"],
    ],
    // 情报分析处(167): 分析/报告/异常讨论
    167 => [
        ["秦岭采石场数据的一个小发现","对比了三次电磁场检测数据，发现异常读数每次都在同一位置出现，且强度有微弱递增趋势。虽然仍低于阈值，但趋势本身值得关注。建议下次复测时携带更精确的设备。"],
        ["关于值班日志格式的统一建议","各线路的值班日志在异常描述部分的用词差异较大。'正常'、'无明显异常'、'未见异常'三个词的使用频率变化可能反映心理状态——建议统一用'正常'作为标准表述，特殊情况下再加补充说明。"],
        ["手稿批注与近期事件的文本关联","整理了朱陆爻手稿批注中涉及地理方向的条目，与近期巡逻中发现的异常位置做了初步比对。有兴趣的同僚可以来我办公室看比对图。纯文本分析，不做任何结论。"],
    ],
    // 镇物研究室(168): 镇物监测/数据报告
    168 => [
        ["伪水仙本周生长数据","伪水仙叶片恢复至事件前约90%，西北向生长速度降至0.8厘米/周。维持B级收容。下周若继续向好，建议下调至C级。"],
        ["年兽常规监测记录","本周年兽生命体征平稳，无异常心率或次声波活动。按常规频次继续监测。"],
        ["蚩的收容环境检测","蚩本周未出现电磁脉冲活动。环境参数稳定。辅助监测设备运行正常。下次全面检查安排在月底。"],
    ],
    // 档案文献馆(169): 档案/文献/手稿
    169 => [
        ["第三批手稿修复完成报告","朱陆爻老先生第三批手稿47页已于本周全部完成修复。修复过程中共发现铅笔批注11条、便条3张。详细修复报告和扫描件已归档。"],
        ["手稿墨迹成分检测初步结果","朱陆爻老先生1958年和1959年的手稿使用了不同配方的墨迹。1959年的墨迹中含有微量金属粉末。何若风同志已协助做了电磁特性检测，结果显示该粉末对特定频段电磁波有微弱响应。不确定这是否为老先生刻意为之。"],
        ["关于建立手稿数字化索引的建议","目前手稿已完成修复三批共约140页，建议建立以'年代-批次-页码'为基础的数字化索引，方便后续检索和交叉引用。可参考标准档案分类法。"],
    ],
    // 人员休息室(170): 闲聊/生活/吐槽
    170 => [
        ["今天食堂换了新菜单","食堂今天突然换了菜单，出现了久违的糖醋排骨。不知道是委员长'尽快处理'的效果还是纯粹的巧合。反正我吃了两份。"],
        ["周末有人想出去走走吗","连着在基地待了两周了。这个周末有没有人想去镇上？不讨论工作，纯粹透气。上次说的人均三十我还能兑现。"],
        ["分享一个野外小知识","最近被分配去采购驱兽喷雾，顺便了解了一下。野外遇到野猪不能跑——要站住不动举起双手让自己看起来大。遇到熊的话要装死但别趴着，要侧躺保护内脏。希望用不上但可以记一下。"],
        ["值班时最容易犯困的时段调查","我统计了一下自己的值班日志：凌晨三点到四点半是最困的时段。咖啡基本没用。何若风说红牛有用但我试了觉得心跳太快。有没有人有其他推荐？"],
        ["新对讲机使用一周体验","新换的那批对讲机待机确实长，充一次电能撑两天值班。就是切换频道那个按钮设计太反人类了，我到现在还没形成肌肉记忆。王哥说用习惯就好——但我已经用一周了。"],
    ],
];

// 按板块抽话题
$forum = $forums[array_rand($forums)];
$pool = $all_topics[$forum] ?? $all_topics[170];
$topic = $pool[array_rand($pool)];

// 去重：检查3小时内是否有相同标题
$recent = get_posts(['post_type'=>'topic','posts_per_page'=>20,'orderby'=>'ID','order'=>'DESC','date_query'=>['after'=>'3 hours ago']]);
foreach ($recent as $rt) {
    if ($rt->post_title === $topic[0]) { echo "重复话题，跳过\n"; exit; }
}

// 创建话题
wp_set_current_user($author_id);
$tid = bbp_insert_topic([],[],["post_title"=>$topic[0],"post_content"=>$topic[1],"post_status"=>"publish"],["forum_id"=>$forum]);
wp_update_post(["ID"=>$tid,"post_parent"=>$forum,"post_title"=>$topic[0],"post_content"=>$topic[1],"post_name"=>sanitize_title($topic[0])?:("topic-".$tid)]);
foreach (["_bbp_forum_id"=>$forum,"_bbp_topic_id"=>$tid,"_bbp_author_ip"=>"10.7.".($author_id%256).".".rand(1,99),"_bbp_voice_count"=>1,"_bbp_last_active_id"=>$tid,"_bbp_last_active_time"=>current_time("mysql"),"_bbp_engagement"=>$author_id] as $k=>$v) update_post_meta($tid,$k,$v);
$fnames = [166=>'行动指挥部',167=>'情报分析处',168=>'镇物研究室',169=>'档案文献馆',170=>'人员休息室'];
echo date('H:i:s')." {$author_name} → {$fnames[$forum]}: {$topic[0]}\n";

// AI生成回复
$other = array_diff($available,[$author_id]);
if(count($other)<2) $other = array_diff($users,[$author_id]);
$keys = array_rand(array_flip($other), min(rand(2,4), count($other)));
if(!is_array($keys)) $keys = [$keys];
$vc = 2;
foreach($keys as $ruid){
    if($ruid==$author_id) continue;
    $rname = get_userdata($ruid)->display_name;
    $rp = get_persona($rname);
    $rsys = "你是{$rname}。以下是你的性格：\n{$rp}\n\n请以你的口吻回复话题【{$topic[0]}】。回复简短自然，1-3句话。不要加前缀。";
    $rc = call_ds($rsys, "话题内容：{$topic[1]}\n\n回复。");
    if (!$rc) continue;
    wp_set_current_user($ruid);
    $rid = wp_insert_post(["post_content"=>$rc,"post_type"=>"reply","post_status"=>"publish","post_author"=>$ruid,"post_parent"=>$tid]);
    foreach(["_bbp_forum_id"=>$forum,"_bbp_topic_id"=>$tid,"_bbp_reply_to"=>$tid,"_bbp_author_ip"=>"10.7.".($ruid%256).".".rand(1,99),"_bbp_voice_count"=>$vc,"_bbp_engagement"=>$ruid] as $k=>$v) update_post_meta($rid,$k,$v);
    echo "  {$rname}: {$rc}\n";
    $vc++;
}
foreach($forums as $f){bbp_update_forum_topic_count($f);bbp_update_forum_reply_count($f);}
