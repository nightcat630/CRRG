<?php
/**
 * CRRG 演习 · 内容扩充 · 周边地点（2026-10-14）
 * ------------------------------------------------------------
 * 委员长："完善一批周边地点，开放车旅。"
 *
 * 口径：
 *   ① **沿现有放射干线加**（brg 0／45／90／180 ✓ 不新增交叉路网 ✗；里程取现有节点之间的空档 ✓）
 *   ② 每个地点写全：`n` 名／`t` 类型／`brg` 方位／`km` 直线／`road_km` 路里／`via` 路名／`st` 状态／`d` 描述
 *   ③ **开放车旅**：原先把一批节点写成"规划"（去不了 ✗）→ 这里给出**开放表**（`open` 里列的 = 已通 ✓）
 *   ④ 每个地点在模块里配 1–2 间房 ＋ **勘查点**（到了能查东西 ✓ 接 R11 的野外勘查）
 */
if (!defined('ABSPATH')) exit;

/** 新增周边地点 ⚠ **2026-10-14 16:15 委员长纠正：他要的是"完善现有地点"，不是新增** ✗
 *  → 本表**默认返回空**（我加的那 11 处不生效 ✓ 代码留着，一行可恢复）
 *  要恢复：把下面 `return [];` 注释掉、把真正的数组解开 ✓ */
function crrg_game_worldmap_extra() {
    if (true) return [];      // ← 关掉新增（委员长：谁让你加了 ✓）
    return [
        /* ── 北干线（brg 0）：基地背后那条沟 ── */
        'hangtuyao'  => ['n' => '夯土窑洞群', 't' => '遗址', 'brg' => 0, 'km' => 13, 'road_km' => 16,
                         'via' => 'X-04 山道', 'st' => '已通',
                         'd' => '十来孔窑洞凿在土崖上，多数塌了半截。最东边那孔门框还立着，门是新的。'],
        'hulinzhan'  => ['n' => '护林点',     't' => '站点', 'brg' => 0, 'km' => 9,  'road_km' => 11,
                         'via' => 'X-04 山道', 'st' => '已通',
                         'd' => '一间砖房，屋顶压着油毡。门口的登记本被雨泡过，字迹化开。'],
        /* ── 东南干线（brg 45）：往山外去的那条 ── */
        'yangludao'  => ['n' => '养路道班',   't' => '站点', 'brg' => 45, 'km' => 5,  'road_km' => 6,
                         'via' => 'X-02 乡道', 'st' => '已通',
                         'd' => '道班院里停着一台压路机，轮胎陷在土里。值班室的炉子还有余温。'],
        'jiuzhuanyao'=> ['n' => '旧砖窑',     't' => '遗址', 'brg' => 45, 'km' => 15, 'road_km' => 20,
                         'via' => 'X-02 支线', 'st' => '部分通',
                         'd' => '窑体塌了一角，野草从砖缝里长出来。窑膛里堆着还没出窑的砖坯。'],
        /* ── 正东支线（brg 90）：沿河 ── */
        'shuimofang' => ['n' => '水磨房',     't' => '遗址', 'brg' => 90, 'km' => 8,  'road_km' => 12,
                         'via' => 'X-02 支线', 'st' => '已通',
                         'd' => '木结构还在，水轮不动了。磨盘压着半袋没磨完的粮食。'],
        'dukou'      => ['n' => '渡口',       't' => '站点', 'brg' => 90, 'km' => 24, 'road_km' => 31,
                         'via' => 'X-08 乡道', 'st' => '部分通',
                         'd' => '两只渡船系在桩上，其中一只进了水。渡口的木牌写着当日的价。'],
        /* ── 南出山干线（brg 180）：基地唯一的主通道 ── */
        'suidaokou'  => ['n' => '旧隧道口',   't' => '隧道', 'brg' => 180, 'km' => 11, 'road_km' => 13,
                         'via' => 'G-01 三级公路', 'st' => '已通',
                         'd' => '隧道口砌着砖，一块新牌子立在路边，落款没有单位。里面是黑的，有风穿过来。'],
        'shuiwenzhan'=> ['n' => '水文站',     't' => '站点', 'brg' => 180, 'km' => 16, 'road_km' => 20,
                         'via' => 'X-03 支线', 'st' => '已通',
                         'd' => '站房墙上画着历年水位线，最上面那道是用另一种笔加的。屋里有台还在走的自记仪。'],
        'shuinichang'=> ['n' => '水泥厂宿舍', 't' => '遗址', 'brg' => 180, 'km' => 20, 'road_km' => 26,
                         'via' => 'X-03 乡道', 'st' => '部分通',
                         'd' => '三栋红砖楼，窗玻璃基本没了。楼下的晾衣绳还绷着，挂着件早该烂掉的衣服。'],
        'lingyuancha'=> ['n' => '陵园岔路',   't' => '路口', 'brg' => 180, 'km' => 25, 'road_km' => 32,
                         'via' => 'X-03 乡道', 'st' => '已通',
                         'd' => '岔路口立着块指路石，往西是陵园，往南是河湾。石头上被人用粉笔画了个箭头。'],
    ];
}

/** **开放车旅**：这些地点对小组开放（未列出的保持原状态 ✓） */
function crrg_game_worldmap_open() {
    return [
        'hangtuyao' => '已通', 'hulinzhan' => '已通', 'yangludao' => '已通', 'jiuzhuanyao' => '部分通',
        'shuimofang' => '已通', 'dukou' => '部分通', 'suidaokou' => '已通', 'shuiwenzhan' => '已通',
        'shuinichang' => '部分通', 'lingyuancha' => '已通',
        /* 原先把这几处挂着"规划"（去不了）→ 委员长要"开放车旅"，一并开通 ✓ */
        'liuhe' => '已通', 'daquanwan' => '部分通', 'hongliuwan' => '部分通', 'hewan' => '部分通',
    ];
}

/**
 * **落地映射**：每个周边地点到了以后进哪间房（scene mod_around 里我写的那些 ✓）
 * 不给的话，车旅会按"节点自动生成一间 r_<节点>"（村口/镇口那种泛用房 ✗ 内容薄）
 */
function crrg_game_travel_open_extra() {
    if (true) return [];      // ← 同上：关掉对新增地点的落地映射 ✓
    return [
        'hulinzhan'   => ['room' => 'ar_hulin',    'scene' => 'mod_around'],
        'hangtuyao'   => ['room' => 'ar_yao',      'scene' => 'mod_around'],
        'yangludao'   => ['room' => 'ar_daoban',   'scene' => 'mod_around'],
        'jiuzhuanyao' => ['room' => 'ar_zhuan',    'scene' => 'mod_around'],
        'shuimofang'  => ['room' => 'ar_shuimo',   'scene' => 'mod_around'],
        'dukou'       => ['room' => 'ar_dukou',    'scene' => 'mod_around'],
        'suidaokou'   => ['room' => 'ar_suidao',   'scene' => 'mod_around'],
        'shuiwenzhan' => ['room' => 'ar_shuiwen',  'scene' => 'mod_around'],
        'shuinichang' => ['room' => 'ar_shuini',   'scene' => 'mod_around'],
        'lingyuancha' => ['room' => 'ar_lingyuan','scene' => 'mod_around'],
    ];
}
