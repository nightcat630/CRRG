<?php
/**
 * 中央重生抵御小组 - Astra 子主题
 * 
 * 模块化结构:
 *   includes/rank-system.php     - 等级与资历系统
 *   includes/favorites.php       - 收藏功能
 *   includes/announcements.php   - 公告系统
 *   includes/emergency-alert.php - 紧急预警
 */

// ─── 基础设置 ───
add_filter('show_admin_bar', '__return_false');

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('astra-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('astra-child', get_stylesheet_uri(), ['astra-parent'], '1.0');
    if (is_page('reports')) {
        wp_enqueue_style('leaflet', get_stylesheet_directory_uri() . '/assets/leaflet/leaflet.css');
        wp_enqueue_script('leaflet', get_stylesheet_directory_uri() . '/assets/leaflet/leaflet.js', [], null, true);
    }
}, 999);

// ─── 加载功能模块 ───
require_once __DIR__ . '/includes/rank-system.php';
require_once __DIR__ . '/includes/favorites.php';
require_once __DIR__ . '/includes/announcements.php';
require_once __DIR__ . '/includes/emergency-alert.php';
require_once __DIR__ . '/includes/messages.php';
require_once __DIR__ . '/includes/duty-system.php';
require_once __DIR__ . '/includes/ann-carousel.php';
require_once __DIR__ . '/includes/threats.php';
require_once __DIR__ . '/includes/report-form.php';
require_once __DIR__ . '/includes/heluo-chat.php';   // 河洛对话代理（AI 入口）
require_once __DIR__ . '/includes/game/registry.php';           // 游戏·注册中枢（Phase 0，2026-09-29）
/* ── bus（Phase 0，2026-10-14）：事件总线 / 世界事实表 / 世界推进器 ──
   规矩见 includes/game/bus/README.md：模块之间只发事件与订阅事件，不互相调函数。 */
require_once __DIR__ . '/includes/game/bus/evt.php';            // 总线：crrg_evt_emit / crrg_evt_on
require_once __DIR__ . '/includes/game/bus/facts.php';          // 事实表：只追加（公共发生的事）
require_once __DIR__ . '/includes/game/bus/tick.php';           // 推进器：惰性补算、幂等、不碰个人档
require_once __DIR__ . '/includes/game/bus/subscribers-p0.php'; // 第一批订阅者（探索足迹/事故标记）
require_once __DIR__ . '/includes/game/bus/notify.php';        // 通知订阅者（世界动向/换日锚）
/* ── engine（Phase 1，2026-10-14）：任务引擎（模板化）──
   定义/线索/对话注入/NPC 时段/多解法检定/结算善后；作者模板见 engine/README.md */
require_once __DIR__ . '/includes/game/engine/task.php';        // 任务对象：阶段/触发/生命周期
require_once __DIR__ . '/includes/game/engine/clues.php';       // 线索 → 台账 → 结论层（治"反网状"）
require_once __DIR__ . '/includes/game/engine/infer.php';       // 推论机制（R5：一个槽位两种读法）
require_once __DIR__ . '/includes/game/engine/actors.php';      // NPC 时段移动（作息 + 任务期覆盖）
require_once __DIR__ . '/includes/game/engine/dlg-overlay.php'; // 对话注入（新人/插支线/结束回收）
require_once __DIR__ . '/includes/game/engine/links.php';       // 与各系统对接（车旅/体力/物品/钱/声望/多人/期限）
require_once __DIR__ . '/includes/game/engine/resolve.php';     // 多解法 + 检定 + 失败代价
require_once __DIR__ . '/includes/game/engine/outcome.php';     // 结算（个人/公共/NPC/世界）+ 善后
require_once __DIR__ . '/includes/game/engine/links2.php';     // 引擎对接·第二批（日程触发/宿舍/交易/鉴定/节点勘查）
require_once __DIR__ . '/includes/game/engine/fight.php';      // 战斗接入（P2.5：入口带上下文·出口落四样）
/* 任务引擎任务（照 missions/task-template.php 写 ✓；模板本身不加载，只当 source 用）
   2026-10-14：旧的 5 条一次性小任务已删除，改在引擎版重写 ✓ */
require_once __DIR__ . '/includes/game/missions/_retired.php';       // 已收官内容开关（《封条》下线；一键可恢复）
require_once __DIR__ . '/includes/game/missions/task-base-rewrite.php';
/* 《在册》五幕（委员长 2026-10-14 定的替代线：封条线整条不要了 ✓） */
require_once __DIR__ . '/includes/game/missions/task-zaice-act1.php';
require_once __DIR__ . '/includes/game/missions/task-zaice-act2.php';
require_once __DIR__ . '/includes/game/missions/task-zaice-act3.php';
require_once __DIR__ . '/includes/game/missions/task-zaice-act4.php';
require_once __DIR__ . '/includes/game/missions/task-zaice-act5.php';
require_once __DIR__ . '/includes/game/missions/task-guaitai.php';      // 任务《怪胎》本地化改编（2026-10-22，骨架）
require_once __DIR__ . '/includes/game/missions/task-zaice-act1.php';
require_once __DIR__ . '/includes/game/missions/task-zaice-chain.php';   // 《在册》二～五幕的桩（R6/R7 填）  // 《在册》第一幕（R5）
require_once __DIR__ . '/includes/game/ui/windows.php';             // 窗口层（可拖动/可关闭的窗口）
require_once __DIR__ . '/includes/game/ui/panels.php';             // 面板构建（自 drill-economy 搬出）
require_once __DIR__ . '/includes/game/ui/actions.php';            // 动作分发（自 drill-economy 搬出）
require_once __DIR__ . '/includes/game/ui/help.php';               // 指令清单（唯一来源）
require_once __DIR__ . '/includes/game/modules/social/social-mod.php';      // 玩家互动模块 v1
require_once __DIR__ . '/includes/game/modules/around/drill-mod-around.php';   // 副本模组：周边地点（房间＋勘查点）
require_once __DIR__ . '/includes/game/modules/county/drill-mod-county.php';  // 副本模组：白石县（县城，42 间）
require_once __DIR__ . '/includes/game/modules/county/drill-mod-gz.php';     // 副本模组：甘州（大城，42 间）
require_once __DIR__ . '/includes/game/modules/towns/drill-mod-towns.php';   // 副本模组：四个镇治（黑松驿/柳河/三道沟/河湾）
require_once __DIR__ . '/includes/game/modules/dorm/drill-mod-dorms.php';   // 副本模组：NPC 宿舍（真房间＋可拜访，2026-10-22）
require_once __DIR__ . '/includes/game/modules/villages/drill-mod-villages.php';  // 副本模组：十六个村（每村 ≥10 间）
require_once __DIR__ . '/includes/game/modules/social/social-window.php';   // 互动窗口（player:/social）
require_once __DIR__ . '/includes/game/content/items-extra.php';   // 物品扩充包（2026-10-14：每类都有、各有作用）
require_once __DIR__ . '/includes/game/content/items-extra2.php';  // 物品第二批（2026-10-14 下午）
require_once __DIR__ . '/includes/game/content/places-around.php';  // 周边地点＋开放车旅（2026-10-14）
require_once __DIR__ . '/includes/game/content/places-polish.php';   // 现有地点完善（描述追加＋勘查点，2026-10-14）
require_once __DIR__ . '/includes/game/content/rooms.php';        // 内容·房间表（Phase 1，纯数据）
require_once __DIR__ . '/includes/game/content/items.php';        // 内容·物品/别名（Phase 1，纯数据）
require_once __DIR__ . '/includes/game/content/taxonomy.php';   // 内容·物品分类（Phase 1，纯数据）
require_once __DIR__ . '/includes/game/content/entities.php';    // 内容·在场物与描述（Phase 1，纯数据）
require_once __DIR__ . '/includes/game/content/shops.php';        // 内容·商店与货品（Phase 1，纯数据）
require_once __DIR__ . '/includes/game/content/shops-town.php';   // 内容·镇上商店（Phase 1，纯数据）
require_once __DIR__ . '/includes/game/content/npcs.php';         // 内容·NPC 名册/作息/课目（Phase 1，纯数据）
require_once __DIR__ . '/includes/game/content/shop-stock.php';  // 内容·基地店铺货架（Phase 1）
require_once __DIR__ . '/includes/game/content/town-npcs.php';     // 内容·镇民对话（Phase 1，从注册调用提取）
require_once __DIR__ . '/includes/game/core/drill.php';        // 虚拟演习（基地模拟终端）
require_once __DIR__ . '/includes/game/systems/training.php';            // 训练系统（9 属性＋46 技能全覆盖，两段检定）
require_once __DIR__ . '/includes/game/systems/cooking.php';            // 烹饪系统（2026-10-14：食材+厨具+火+检定 ✓）
require_once __DIR__ . '/includes/game/systems/weekly.php';               // 天气机制影响 ＋ 周报审查（R10）
require_once __DIR__ . '/includes/game/systems/archive.php';              // 档案检索（R10：按人名/编号/月日/地点查旧档）
require_once __DIR__ . '/includes/game/systems/phone-ui.php';          // 手机界面（一个窗口多页，像智能机 ✓）
require_once __DIR__ . '/includes/game/systems/equip.php';            // 装备维护/消耗（R9①）
require_once __DIR__ . '/includes/game/systems/npc-net.php';         // NPC 关系网（R9②）
require_once __DIR__ . '/includes/game/systems/hints.php';           // 提示档（R9④）
require_once __DIR__ . '/includes/game/systems/cleanup.php';         // 善后/痕迹（R9⑥）
require_once __DIR__ . '/includes/game/engine/team.php';          // 多人分工（R9③）
require_once __DIR__ . '/includes/game/systems/phone-life.php';       // 手机·非业务功能（群聊/动态/论坛/备忘录/收音机/小游戏/时钟）
require_once __DIR__ . '/includes/game/systems/contain.php';           // 收容流程与小队（R4）
require_once __DIR__ . '/includes/game/systems/phone.php';             // 手机/通讯（R3：含远程交报告）
require_once __DIR__ . '/includes/game/systems/calendar.php';          // 日历与日程（第2轮：无年份，只有月日）
require_once __DIR__ . '/includes/game/systems/rollcall.php';           // 点名：报到+2积分／连30天+10声望／无惩罚／NPC按作息点名
require_once __DIR__ . '/includes/game/systems/madness.php';           // 理智与疯狂（第1轮：CoC 还原）
require_once __DIR__ . '/includes/game/systems/duty.php';            // 值班表/编制（P1.9c：任务与排班的地基）
require_once __DIR__ . '/includes/game/systems/drill-world.php';   // 虚拟演习 · 世界时间与天气（1:48）
require_once __DIR__ . '/includes/game/systems/drill-coc.php';    // 虚拟演习 · CoC 7e 规则层
require_once __DIR__ . '/includes/game/systems/drill-items.php';  // 虚拟演习 · 物品/实体扩充/车卡/播报
require_once __DIR__ . '/includes/game/systems/drill-weapons.php'; // 虚拟演习 · 武器类型层（万物可为兵）
require_once __DIR__ . '/includes/game/systems/drill-miracles.php'; // 秘术（2026-10-31：《怪胎》六条，学/施/列；文本见 世界观/17）
require_once __DIR__ . '/includes/game/systems/drill-worldmap.php'; // 虚拟演习 · 大地图（周边村落/城镇/山区/大城）
/* 2026-10-22：**《封条》任务已作废**（委员长确认）→ 停止加载实现文件 ✓
   原因：之前只从任务列表里移除 ✗ 实现文件仍在 → 技研室还挂着「修复（现场）」等交互点 ✗
   恢复：把下面两行注释解开即可 ✓ */
require_once __DIR__ . '/includes/game/missions/drill-mission-seal-multi.php';
require_once __DIR__ . '/includes/game/missions/drill-mission-seal-multi-logic.php';
require_once __DIR__ . '/includes/game/missions/drill-mission-seal-ask-engine.php';   // 问 NPC·引擎（2026-09-29 合并三份重复）
require_once __DIR__ . '/includes/game/missions/drill-mission-seal-survey-engine.php';   // 调查点·引擎（2026-09-29）
require_once __DIR__ . '/includes/game/missions/drill-mission-seal-choice-engine.php';   // 决断·引擎（2026-09-29）
require_once __DIR__ . '/includes/game/missions/drill-mission-seal5.php'; // 虚拟演习 · 中型《封条》第 5 轮
require_once __DIR__ . '/includes/game/missions/drill-mission-seal4.php'; // 虚拟演习 · 中型《封条》第 4 轮
require_once __DIR__ . '/includes/game/missions/drill-mission-seal3.php'; // 虚拟演习 · 中型《封条》第 3 轮
require_once __DIR__ . '/includes/game/missions/drill-mission-seal2.php'; // 虚拟演习 · 中型《封条》第 2 轮
require_once __DIR__ . '/includes/game/missions/drill-mission-seal.php'; // 虚拟演习 · 中型《封条》第 1 轮
require_once __DIR__ . '/includes/game/missions/drill-missions-base.php'; // 虚拟演习 · 基地任务扩充包
require_once __DIR__ . '/includes/game/systems/drill-appraise.php';     // 虚拟演习 · 鉴定与封存
require_once __DIR__ . '/includes/game/systems/drill-item-taxonomy.php'; // 虚拟演习 · 物品分类体系
require_once __DIR__ . '/includes/game/systems/drill-travel-events.php'; // 虚拟演习 · 在途随机事件
require_once __DIR__ . '/includes/game/systems/drill-travel.php';  // 虚拟演习 · 长途车旅与露营
require_once __DIR__ . '/includes/game/systems/drill-fate.php';    // 虚拟演习 · 命运（殉职/疯狂/轮回）
require_once __DIR__ . '/includes/game/systems/drill-vitals.php';  // 虚拟演习 · 身体状态（精力/饱食/口渴）
require_once __DIR__ . '/includes/game/systems/drill-rep.php';     // 虚拟演习 · 地方声望
require_once __DIR__ . '/includes/game/systems/drill-trade.php';   // 虚拟演习 · 回收与拆除
require_once __DIR__ . '/includes/game/systems/drill-equip.php';   // 虚拟演习 · 装备与部位
require_once __DIR__ . '/includes/game/core/registry-legacy.php';       // 注册表/场景访问（自 drill-hub 搬出）
require_once __DIR__ . '/includes/game/systems/mission-hub.php';       // 合约核心（自 drill-hub 搬出）
require_once __DIR__ . '/includes/game/systems/scene-hub.php';         // 场景扩展/宿舍/服务（自 drill-hub 搬出）
require_once __DIR__ . '/includes/game/systems/board.php';        // 值班板清单（面板与窗口共用）
require_once __DIR__ . '/includes/game/systems/dorm.php';         // 宿舍区块（面板与窗口共用）
require_once __DIR__ . '/includes/game/systems/team.php';         // 队伍/车辆区块（面板与窗口共用）
require_once __DIR__ . '/includes/drill-economy.php';  // 虚拟演习 · 经济/队伍/宿舍效果
require_once __DIR__ . '/includes/game/systems/drill-combat.php';   // 虚拟演习 · 战斗引擎（CoC 7e + 房规）
require_once __DIR__ . '/includes/game/systems/drill-art.php';      // 虚拟演习 · 房间 ASCII 示意图
require_once __DIR__ . '/includes/game/systems/notes.php';    // 笔记系统（Phase 2 拆分）
require_once __DIR__ . '/includes/game/systems/looks.php';    // 观察钩子（Phase 2 拆分）
require_once __DIR__ . '/includes/game/modules/wild/drill-mod-wild.php'; // 虚拟演习 · 副本模组：野外观察 + 任务分型
require_once __DIR__ . '/includes/game/modules/town/drill-mod-town.php'; // 虚拟演习 · 场景模组：青石镇（地表）
require_once __DIR__ . '/includes/game/systems/dialogue2.php';        // 对话引擎 v2（节点树/条件/效果）
require_once __DIR__ . '/includes/game/systems/dialogue2-ask.php';    // 对话 v2·交互层
require_once __DIR__ . '/includes/game/content/dlg/demo-changshouye.php';   // 对话 v2 示范树
require_once __DIR__ . '/includes/game/content/dlg/town-fixers.php';   // 镇上两个 NPC 补话题（2026-10-22）
require_once __DIR__ . '/includes/game/content/dlg/town-v2-auto.php';   // 对话 v2·机械迁移的树
require_once __DIR__ . '/includes/game/systems/seal-compat.php';        // 封条兼容层（只留 seal_has/set ✓ 2026-10-22）
require_once __DIR__ . '/includes/game/systems/drill-dialogue.php';  // 虚拟演习 · 对话引擎
require_once __DIR__ . '/includes/game/modules/chase/drill-mod-chase.php'; // 虚拟演习 · 中型模组：追书人
require_once __DIR__ . '/includes/game/systems/drill-npc.php';        // 虚拟演习 · 基地 NPC（作息／跑腿委托）
require_once __DIR__ . '/includes/game/systems/npc-registry.php';    // NPC 统一模块（模板/作息/特殊出现/对话/功能）
require_once __DIR__ . '/includes/game/systems/dorm-visit.php';        // 宿舍独立实例（自己的/拜访 ✓）
require_once __DIR__ . '/includes/game/modules/chase/drill-mod-chase-dlg.php'; // 追书人 · 对话树
require_once __DIR__ . '/includes/game/modules/chase/drill-mod-chase-notes.php'; // 追书人 · 笔记条目（线索台账）
require_once __DIR__ . '/includes/game/modules/chase/drill-mod-chase-prose.php'; // 追书人 · 文本加厚（场景/外貌/对话/最后所见）
require_once __DIR__ . '/includes/game/modules/town/drill-mod-town-life.php'; // 青石镇 · 生活层（建筑/住宿/服务）
require_once __DIR__ . '/includes/game/modules/town/drill-mod-town-dlg.php';  // 青石镇 · 镇民对话
require_once __DIR__ . '/includes/game/modules/town/drill-mod-town-services.php'; // 青石镇 · 建筑功能

add_filter('astra_single_post_navigation_enabled', '__return_false');

// ─── 自定义头部（红条+导航+品牌+新闻） ───
add_action('astra_header', 'crrg_custom_header', 5);
function crrg_custom_header() {
    ?>
    <div class="gov-header-wrap">
    <div class="gov-top-bar">
        <div class="container">
            <span>中央重生抵御小组 · 官方信息平台</span>
            <form class="top-search" action="/" method="get">
                <input type="text" name="s" placeholder="搜索档案、事件、人物..." required pattern=".*[a-zA-Z0-9\u4e00-\u9fff].*" title="请至少输入一个字母、数字或中文">
                <button type="submit">搜索</button>
            </form>
            <span id="gov-date"></span>
        </div>
    </div>
    <div class="gov-nav">
        <div class="container">
            <div class="nav-left">
                <a href="/" style="color:#fff;text-decoration:none;">中央重生抵御小组</a>
                <span class="separator">|</span>
                <span class="current-page" id="current-page-name">首页</span>
            </div>
            <ul class="nav-menu">
                <li><a href="/archives/">电子档案馆 ▾</a>
                    <ul class="dropdown-menu">
                        <li><a href="/artifacts/">镇物</a></li><li><a href="/events/">事件</a></li>
                        <li><a href="/personnel/">人物</a></li><li><a href="/organizations/">组织</a></li>
                        <li><a href="/research/">研究发现</a></li><li><a href="/entities/">祂们</a></li>
                        <li><a href="/esoterica/">秘术（仅登记）</a></li>
                        <li><a href="/outstanding/">优秀员工</a></li>
                    </ul>
                </li>
                <li><a href="/forum/">会议厅</a></li>
                <li><a href="/newsroom/">新闻社</a></li>
                <li><a href="/members/">人员名录</a></li>
                <li><a href="/duty/">值班表</a></li>
                <li><a href="/map/">事件态势</a></li>
                <li class="nav-auth">
                    <?php if (is_user_logged_in()): $u=wp_get_current_user(); $rk=crrg_get_rank_data(crrg_get_rank($u->ID)); ?>
                        <div class="nav-user-dropdown">
                            <span class="nav-user-trigger">
                                <?php echo get_avatar($u->ID,28,'','',['class'=>'nav-avatar']); ?>
                                <span class="nav-nickname"><?php echo esc_html($u->display_name); ?></span>
                                <span class="nav-rank-badge" title="<?php echo $rk['name']; ?>"><?php echo $rk['icon']; ?></span>
                                <span class="nav-arrow">▾</span>
                            </span>
                            <ul class="dropdown-menu dropdown-menu-right">
                                <li><a href="/author/<?php echo $u->user_nicename; ?>/">我的主页</a></li>
                                <li><a href="/profile/">修改个人资料</a></li>
                                <li><a href="/promotion/">晋升申请</a></li>
                                <li><a href="/messages/">✉ 私信 <?php $ur = function_exists('crrg_unread_count') ? crrg_unread_count($u->ID) : 0; if($ur): ?><span style="color:#C41230;font-weight:bold;">(<?php echo $ur; ?>)</span><?php endif; ?></a></li>
                                <li><a href="<?php echo wp_logout_url(home_url()); ?>">登出</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="/wp-login.php" class="nav-login">登录</a>
                        <a href="/wp-login.php?action=register" class="nav-register">注册</a>
                    <?php endif; ?>
                </li>
            </ul>
        </div>
    </div>
    <div class="gov-brand">
        <div class="container">
            <div class="brand-emblem"><img src="/wp-content/themes/astra-child/assets/emblem.png" alt="CRRG" style="width:170px;height:170px;object-fit:contain;"></div>
            <div class="brand-text"><h1>中央重生抵御小组</h1><div class="subtitle">CENTRAL REBIRTH RESISTANCE GROUP · 记录异常 抵御重生</div></div>
        </div>
    </div>
    </div><!-- end gov-header-wrap -->
    <div class="gov-news-carousel">
        <div class="container"><div class="carousel-header"><span class="carousel-label">央视新闻</span></div><div class="carousel-outer"><div class="carousel-track" id="carousel-track">加载中…</div><button class="carousel-arrow carousel-prev" id="carousel-prev" aria-label="上一条">◀</button><button class="carousel-arrow carousel-next" id="carousel-next" aria-label="下一条">▶</button></div></div>
    </div>
    <?php
}

add_filter('astra_header_disable', '__return_true');
add_action('after_setup_theme', function () {
    remove_action('astra_masthead_content', 'astra_primary_navigation_markup');
});

// ─── 自定义页脚 ───
add_action('astra_footer', 'crrg_custom_footer', 5);
function crrg_custom_footer() {
    ?>
    <div class="gov-footer">
        <div class="partner-links">
            <span class="partner-label">合作机构</span>
            <a href="https://www.cas.cn/" target="_blank">中国科学院</a><span class="partner-sep">|</span>
            <a href="https://www.mps.gov.cn/" target="_blank">公安部</a><span class="partner-sep">|</span>
            <a href="https://www.12339.gov.cn/" target="_blank">国家安全部</a><span class="partner-sep">|</span>
            <a href="https://www.cass.cn/" target="_blank">中国历史研究院</a><span class="partner-sep">|</span>
            <a href="https://www.dpm.org.cn/" target="_blank">故宫博物院</a><span class="partner-sep">|</span>
            <a href="https://www.spacechina.com/" target="_blank">中国航天科技集团</a><span class="partner-sep">|</span>
            <a href="https://www.chinafxj.cn/" target="_blank">中国反邪教网</a>
        </div>
        <p>中央重生抵御小组 © 2026 | 仅供内部人员查阅 | 未经授权禁止传播</p>
        <p>备案号：CRRG-2026-001 · 信息安全管理等级：机密 · <a href="/contact/" style="color:rgba(255,255,255,0.5);">联系我们 / 举报信箱</a> · <a href="/feed/" style="color:rgba(255,255,255,0.5);"> RSS 订阅</a> <span style="color:rgba(255,255,255,0.3);font-size:10px;">（建议使用 RSS 阅读器打开）</span></p>
    </div>
    <?php
}
add_filter('astra_footer_disable', '__return_true');

// ─── 登录页样式 ───
add_action('login_enqueue_scripts', function () { ?>
    <style>
        body.login{background:#F0F2F5;font-family:'Microsoft YaHei','PingFang SC',sans-serif;}
        .login h1 a{background:none!important;background-size:contain!important;width:120px!important;height:120px!important;margin:0 auto 10px!important;pointer-events:none;}
        #login{width:380px;padding:40px 0 20px;}
        #loginform{background:#fff;border:1px solid #e0e0e0;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.06);padding:30px;border-top:3px solid #C41230;}
        #loginform label{color:#333;font-size:14px;}
        #loginform input[type="text"],#loginform input[type="password"]{border:1px solid #d5d5d5;border-radius:3px;padding:8px 12px;font-size:14px;background:#fafafa;box-shadow:none;}
        #loginform input[type="text"]:focus,#loginform input[type="password"]:focus{border-color:#C41230;box-shadow:0 0 0 2px rgba(196,18,48,0.1);}
        #wp-submit{background:#C41230!important;border:none!important;border-radius:3px!important;padding:8px 0!important;font-size:15px!important;font-weight:bold!important;width:100%!important;}
        #wp-submit:hover{background:#A00E25!important;}
        #nav a,#backtoblog a{color:#666;font-size:13px;text-decoration:none;}
        #nav a:hover,#backtoblog a:hover{color:#C41230;}
        .login #backtoblog{display:none;}
        .login .message,.login .notice{border-left-color:#C41230;}
        .login .language-switcher{display:none;}
    </style>
<?php });

add_action('login_head', function () {
    echo '<style>.login h1 a{background-image:url(/wp-content/themes/astra-child/assets/emblem.png)!important;background-size:contain!important;width:120px!important;height:120px!important;}.login h1 a::before{content:none!important;}</style>';
});

add_filter('login_headerurl', function(){return home_url();});
add_filter('login_headertext', function(){return '中央重生抵御小组';});

// ─── 头像系统 ───
add_filter('get_avatar_url', function ($url, $id_or_email) {
    $user_id = 0;
    if (is_numeric($id_or_email)) $user_id = $id_or_email;
    elseif ($id_or_email instanceof WP_User) $user_id = $id_or_email->ID;
    elseif ($id_or_email instanceof WP_Comment) $user_id = $id_or_email->user_id;
    if ($user_id && $custom = get_user_meta($user_id, 'custom_avatar', true)) {
        $url = str_replace('http://', 'https://', $custom);
        if (is_file(str_replace(home_url(), ABSPATH, $url))) $url .= '?ts=' . filemtime(str_replace(home_url(), ABSPATH, $url));
        return $url;
    }
    // 无自定义头像时使用站点图标
    if ($user_id) {
        return home_url('/wp-content/uploads/2026/07/icon-150x150.png');
    }
    return $url;
}, 10, 2);

// ─── 评论删除 ───
add_action('init', function () {
    if (!isset($_GET['del_comment'], $_GET['_nonce'])) return;
    $cid = (int)$_GET['del_comment'];
    if (!wp_verify_nonce($_GET['_nonce'], 'crrg_del_comment_'.$cid)) return;
    $comment = get_comment($cid);
    if (!$comment) return;
    $uid = get_current_user_id();
    $can = ((int)$comment->user_id === $uid) || in_array(crrg_get_rank($uid), ['advisor','deputy','chairman']);
    if ($can) {
        // 顾问以上删除他人评论，扣除被删者10资历
        if ((int)$comment->user_id !== $uid && in_array(crrg_get_rank($uid), ['advisor','deputy','chairman'])) {
            $penalty = 10;
            $current_xp = crrg_get_xp($comment->user_id);
            update_user_meta($comment->user_id, 'crrg_xp', max(0, $current_xp - $penalty));
        }
        wp_delete_comment($cid, true);
        wp_redirect(remove_query_arg(['del_comment','_nonce']));
        exit;
    }
});

add_filter('comment_text', function ($text, $comment, $args) {
    if (!is_single() || !is_user_logged_in()) return $text;
    $uid = get_current_user_id();
    $can = ((int)$comment->user_id === $uid) || in_array(crrg_get_rank($uid), ['advisor','deputy','chairman']);
    if ($can) {
        $nonce = wp_create_nonce('crrg_del_comment_'.$comment->comment_ID);
        $url = add_query_arg(['del_comment'=>$comment->comment_ID,'_nonce'=>$nonce]);
        $text .= '<div style="margin-top:4px;font-size:11px;"><a href="'.esc_url($url).'" style="color:#c00;" onclick="return confirm(\'确定删除？\')">删除</a></div>';
    }
    return $text;
}, 10, 3);

// ─── 注册验证问题 ───
add_action('register_form', function () {
    $q = get_option('crrg_reg_question', '重生抵御小组现任委员长是谁？');
    echo '<p><label for="crrg_security_q">'.esc_html($q).'<br><input type="text" name="crrg_security_a" id="crrg_security_q" class="input" size="25" required></label></p>';
});
add_filter('registration_errors', function ($errors, $user, $email) {
    if (empty($_POST['crrg_security_a']) || $_POST['crrg_security_a'] !== get_option('crrg_reg_answer', '朱贞吉')) {
        $errors->add('security_fail', '安全验证问题回答错误');
    }
    return $errors;
}, 10, 3);

// ─── 注册 report_type 查询变量 ───
add_filter('query_vars', function ($vars) {
    $vars[] = 'report_type';
    return $vars;
});

// ─── 禁止 report_type 页面的规范重定向 ───
add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
    if (!empty($_GET['report_type'])) {
        return false;
    }
    return $redirect_url;
}, 10, 2);

// ─── 搜索范围：文章 + 论坛话题 + 档案馆导航 ───
add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query()) return;

    // ─── 档案馆：按报告类型过滤（/?s=archive&type=post&report_type=xxx）───
    if (!empty($_GET['report_type'])) {
        $query->set('s', '');  // 清掉 s=archive，避免关键字搜索覆盖 meta 过滤
        $query->set('post_type', 'post');
        $query->set('meta_key', 'crrg_report_type_name');
        $query->set('meta_value', sanitize_text_field($_GET['report_type']));
        $query->set('posts_per_page', 20);
        $query->set('orderby', 'date');
        $query->set('order', 'DESC');
        return;
    }

    // ─── 搜索范围：文章 + 论坛话题 ───
    if ($query->is_search()) {
        $type = $_GET['type'] ?? 'all';
        if ($type === 'post') {
            $query->set('post_type', 'post');
        } elseif ($type === 'topic') {
            $query->set('post_type', 'topic');
        } else {
            $query->set('post_type', ['post', 'topic']);
        }
        // 访问等级过滤
        $access_mq = crrg_get_access_meta_query();
        $existing = $query->get('meta_query') ?: [];
        $existing[] = $access_mq;
        $query->set('meta_query', $existing);
    }
});

// ─── 搜索 CRRG-917 → 管理面板 ───
add_action('template_redirect', function () {
    if (is_search() && isset($_GET['s'])) {
        $s = trim($_GET['s']);
        // 档案馆导航：允许空搜索 + report_type
        if ($s === '' && empty($_GET['report_type'])) {
            wp_redirect(home_url('/')); exit;
        }
        if (strtolower($s) === 'crrg-917') {
            wp_redirect(home_url('/admin/')); exit;
        }
        if (strtolower($s) === 'crrg-河洛') {   // 彩蛋：河洛 AI 入口
            wp_redirect(home_url('/heluo/')); exit;
        }
        if (!preg_match('/[a-zA-Z0-9\x{4e00}-\x{9fff}]/u', $s)) {
            wp_redirect(home_url('/')); exit;
        }
    }
    if (is_404() && $_SERVER['REQUEST_URI'] === '/register') {
        wp_redirect('/wp-login.php?action=register'); exit;
    }
});

// ─── 文章底部收藏按钮 ───
add_filter('the_content', function ($content) {
    if (!is_single() || !is_main_query()) return $content;
    $post_id = get_the_ID();
    $favs = get_post_meta($post_id, 'crrg_favorited_by', true) ?: [];
    $count = count($favs);
    $html = '<div style="margin:20px 0;padding:16px;background:#f8f9fa;border:1px solid #e0e0e0;border-radius:4px;">';
    if (is_user_logged_in()) {
        $is_fav = crrg_is_favorited(get_current_user_id(), $post_id);
        $html .= '<a href="#" class="fav-btn" data-post="'.$post_id.'" style="color:'.($is_fav?'#e8b800':'#999').';text-decoration:none;font-size:16px;">'.($is_fav?'⭐':'☆').' 收藏此报告</a>';
        $html .= '<span style="font-size:12px;color:#999;margin-left:8px;">'.($is_fav?'已收藏':'点击收藏').'</span>';
    }
    if ($count > 0) {
        $html .= '<div style="margin-top:10px;font-size:12px;color:#666;">共 '.$count.' 人收藏';
        $fav_users = get_users(['include'=>array_slice($favs,0,10)]);
        $names = array_map(function($u){return esc_html($u->display_name);}, $fav_users);
        $html .= '：'.implode('、', $names);
        if ($count > 10) $html .= ' 等';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $content . $html;
});

// ─── 收藏 AJAX JS ───
add_action('wp_footer', function () {
    if (!is_user_logged_in()) return;
    ?>
    <script>
    document.addEventListener('click',function(e){
        var btn=e.target.closest('.fav-btn');if(!btn)return;e.preventDefault();
        var fd=new FormData();fd.append('action','crrg_toggle_fav');fd.append('post_id',btn.getAttribute('data-post'));
        fetch('/wp-admin/admin-ajax.php',{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.text()).then(res=>{
            if(res==='added'){btn.innerHTML='⭐ 收藏';btn.style.color='#e8b800';}
            else if(res==='removed'){btn.innerHTML='☆ 收藏';btn.style.color='#999';}
        });
    });
    </script>
    <?php
}, 999);

// ─── 每日登录资历提示 ───
add_action('wp_footer', function () {
    if (!is_user_logged_in()) return;
    $uid = get_current_user_id();
    if (get_user_meta($uid, 'crrg_xp_toast', true)) {
        delete_user_meta($uid, 'crrg_xp_toast');
        $xp = crrg_get_xp($uid);
        $rank = crrg_get_rank_data(crrg_get_rank($uid));
        echo '<div id="xp-toast" style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1B3A5C;color:#fff;padding:12px 24px;border-radius:6px;font-size:14px;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,0.3);animation:xpToastIn 0.4s ease,xpToastOut 0.4s ease 2.5s forwards;"> 每日签到 +2 资历！当前：'.$rank['icon'].' '.$rank['name'].' · '.$xp.' 资历</div>';
        echo '<style>@keyframes xpToastIn{from{opacity:0;transform:translateX(-50%) translateY(20px);}to{opacity:1;transform:translateX(-50%) translateY(0);}}@keyframes xpToastOut{from{opacity:1;}to{opacity:0;}}</style>';
    }
}, 999);

// ─── 论坛点赞 ───
add_action('wp_ajax_crrg_like_topic', function () {
    if (!is_user_logged_in()) wp_die('0');
    $tid = (int)($_POST['topic_id']??0); $uid = get_current_user_id();
    $likes = get_post_meta($tid, 'crrg_likes', true) ?: [];
    if (get_post_meta($tid, 'crrg_liked_by_'.$uid, true)) {
        $likes = array_diff($likes, [$uid]); delete_post_meta($tid, 'crrg_liked_by_'.$uid);
        echo 'unliked|'.count($likes);
    } else {
        $likes[] = $uid; update_post_meta($tid, 'crrg_liked_by_'.$uid, '1');
        echo 'liked|'.count($likes);
    }
    update_post_meta($tid, 'crrg_likes', array_values($likes)); wp_die();
});

add_action('bbp_theme_after_topic_title', function () {
    $tid = bbp_get_topic_id(); $likes = get_post_meta($tid, 'crrg_likes', true) ?: [];
    $liked = is_user_logged_in() && get_post_meta($tid, 'crrg_liked_by_'.get_current_user_id(), true);
    echo '<span class="topic-like-btn" data-topic="'.$tid.'" style="cursor:pointer;font-size:12px;color:'.($liked?'#C41230':'#999').';margin-left:8px;">'.($liked?'❤':'🤍').' <span class="like-count">'.count($likes).'</span></span>';
});

add_action('wp_footer', function () {
    if (!is_singular(['topic','forum']) && !is_post_type_archive(['topic','forum'])) return;
    ?>
    <script>
    document.addEventListener('click',function(e){
        var btn=e.target.closest('.topic-like-btn');if(!btn)return;e.preventDefault();
        var fd=new FormData();fd.append('action','crrg_like_topic');fd.append('topic_id',btn.getAttribute('data-topic'));
        fetch('/wp-admin/admin-ajax.php',{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.text()).then(res=>{
            var c=res.split('|')[1]||0;btn.querySelector('.like-count').textContent=c;
            if(res.startsWith('liked')){btn.innerHTML='❤ <span class="like-count">'+c+'</span>';btn.style.color='#C41230';}
            else{btn.innerHTML='🤍 <span class="like-count">'+c+'</span>';btn.style.color='#999';}
        });
    });
    </script>
    <?php
}, 998);

// ─── 编辑器文本修改 ───
add_filter('media_view_strings', function ($s) {
    $s['insertIntoPost']='插入到报告';$s['addMedia']='插入档案图片';$s['mediaLibraryTitle']='档案图片库';
    return $s;
}, 999);

// ─── 品牌栏折叠 + 新闻横滑 + 日期 ───
add_action('wp_footer', function () {
    ?>
    <script>
    (function(){var d=new Date();var w=['日','一','二','三','四','五','六'];var s=d.getFullYear()+'年'+(d.getMonth()+1)+'月'+d.getDate()+'日 星期'+w[d.getDay()];var e=document.getElementById('gov-date');if(e)e.textContent=s;})();
    (function(){var b=document.querySelector('.gov-brand');if(!b)return;var m=160;function u(){var s=window.pageYOffset,p=Math.min(s/m,1);b.style.clipPath='inset(0 0 '+(p*100)+'% 0)';b.style.opacity=1-p;}u();b.classList.add('ready');var t=false;window.addEventListener('scroll',function(){if(!t){requestAnimationFrame(function(){u();t=false;});t=true;}},{passive:true});})();
    (function(){var t=document.getElementById('carousel-track');if(!t)return;fetch('/cctv-news.php').then(r=>r.json()).then(d=>{if(!d.length){t.innerHTML='暂无新闻';return;}t.innerHTML=d.map(i=>{var img=i.image?'<img class="news-img" src="'+i.image+'" alt="" loading="lazy">':'<div class="news-placeholder">央视</div>';return'<a class="carousel-item" href="'+(i.url||'#')+'" target="_blank">'+img+'<div class="news-text"><div class="news-title">'+i.title+'</div>'+(i.brief?'<div class="news-brief">'+i.brief+'</div>':'')+'</div></a>';}).join('');var p=document.getElementById('carousel-prev'),n=document.getElementById('carousel-next');if(p&&n){function g(){var w=t.querySelector('.carousel-item');return w?w.offsetWidth+16:320;}p.addEventListener('click',function(){t.scrollBy({left:-g(),behavior:'smooth'});});n.addEventListener('click',function(){t.scrollBy({left:g(),behavior:'smooth'});});}}).catch(function(){t.innerHTML='新闻加载失败';});})();
    </script>
    <?php
}, 997);

// ─── 通知评论/收藏 ───
add_action('wp_insert_comment', function ($id, $comment) {
    $post = get_post($comment->comment_post_ID);
    if ($post) {
        $notifs = get_user_meta($post->post_author, 'crrg_notifications', true) ?: [];
        array_unshift($notifs, ['type'=>'comment','post_id'=>$post->ID,'post_title'=>$post->post_title,'commenter'=>$comment->comment_author,'content'=>wp_trim_words($comment->comment_content,20),'time'=>current_time('mysql'),'read'=>false]);
        update_user_meta($post->post_author, 'crrg_notifications', array_slice($notifs,0,50));
    }
}, 10, 2);

// ─── 收藏通知 ───
add_action('wp_ajax_crrg_toggle_fav', function () {
    if (!is_user_logged_in()) wp_die('0');
    $uid = get_current_user_id(); $pid = (int)($_POST['post_id']??0);
    $favs = get_user_meta($uid, 'crrg_favorites', true) ?: [];
    if (in_array($pid, $favs)) {
        $favs = array_diff($favs, [$pid]); echo 'removed';
    } else {
        $favs[] = $pid; echo 'added';
        $post = get_post($pid);
        if ($post && (int)$post->post_author !== $uid) {
            $pf = get_post_meta($pid, 'crrg_favorited_by', true) ?: [];
            if (!in_array($uid, $pf)) { $pf[] = $uid; update_post_meta($pid, 'crrg_favorited_by', $pf); }
            $u = wp_get_current_user();
            $n = get_user_meta($post->post_author, 'crrg_notifications', true) ?: [];
            array_unshift($n, ['type'=>'favorite','post_id'=>$pid,'post_title'=>$post->post_title,'commenter'=>$u->display_name,'content'=>'收藏了你的报告','time'=>current_time('mysql'),'read'=>false]);
            update_user_meta($post->post_author, 'crrg_notifications', array_slice($n,0,50));
        }
    }
    update_user_meta($uid, 'crrg_favorites', array_values($favs)); wp_die();
});
add_action('wp_ajax_nopriv_crrg_toggle_fav', function(){wp_die('0');});

// ─── RSS Feed XSL 样式表 ───
add_action('rss_tag_pre', function () {
    echo '<?xml-stylesheet type="text/xsl" href="' . get_stylesheet_directory_uri() . '/rss-style.xsl"?>' . "
";
});

// RSS 自动发现标签
add_action('wp_head', function () {
    echo '<link rel="alternate" type="application/rss+xml" title="中央重生抵御小组 RSS" href="' . home_url('/feed/') . '">' . "
";
});

// ─── 文章机密等级水印 ───
add_action('wp_head', function () {
    if (!is_single() || !is_main_query()) return;
    $post_id = get_the_ID();
    $access = get_post_meta($post_id, 'crrg_access_level', true) ?: 'observer';
    $rank = crrg_get_rank_data($access);
    if ($access === 'observer') return; // 公开级别不显示水印
    $levels = ['observer'=>'','operative'=>'内部','tl'=>'秘密','chief'=>'机密','advisor'=>'绝密','deputy'=>'绝密','chairman'=>'绝密'];
    $label = $levels[$access] ?? '内部';
    $opacity = $access === 'chairman' ? '0.08' : '0.05';
    ?>
    <style>
    body.single .gov-content::before {
        content: "<?php echo $label; ?>";
        position: fixed;
        top: 50%; left: 50%;
        transform: translate(-50%,-50%) rotate(-30deg);
        font-size: 120px;
        font-weight: 900;
        color: rgba(196,18,48,<?php echo $opacity; ?>);
        pointer-events: none;
        z-index: 0;
        white-space: nowrap;
        letter-spacing: 0.3em;
    }
    </style>
    <?php
});

// 改 feed Content-Type 为 text/xml，避免 Firefox 直接下载
add_filter('feed_content_type', function ($content_type, $type) {
    return 'text/xml';
}, 10, 2);

// ─── 显示日期（优先事件时间） ───
add_filter('get_the_date', function($the_date, $format, $post) {
    $post_id = is_object($post) ? $post->ID : ($post ?: get_the_ID());
    $event = get_post_meta($post_id, 'crrg_event_date', true);
    if ($event) return date($format ?: 'Y-m-d', strtotime($event));
    return $the_date;
}, 10, 3);

add_filter('get_the_time', function($the_time, $format, $post) {
    $post_id = is_object($post) ? $post->ID : ($post ?: get_the_ID());
    $event = get_post_meta($post_id, 'crrg_event_date', true);
    if ($event) return date($format ?: 'H:i', strtotime($event));
    return $the_time;
}, 10, 3);
function crrg_get_access_meta_query() {
    $user_rank = is_user_logged_in() ? crrg_get_rank(get_current_user_id()) : 'observer';
    $user_level = crrg_get_rank_level($user_rank);
    $allowed = [];
    foreach (CRRG_RANKS as $r) {
        if (crrg_get_rank_level($r['id']) <= $user_level) $allowed[] = $r['id'];
    }
    return [
        'key' => 'crrg_access_level',
        'value' => $allowed,
        'compare' => 'IN',
    ];
}

// 单篇文章访问拦截
add_action('template_redirect', function () {
    if (!is_single() || !is_main_query()) return;
    $post_id = get_the_ID();
    $access = get_post_meta($post_id, 'crrg_access_level', true) ?: 'observer';
    $user_rank = is_user_logged_in() ? crrg_get_rank(get_current_user_id()) : 'observer';
    $user_level = crrg_get_rank_level($user_rank);
    $access_level = crrg_get_rank_level($access);
    if ($access_level > $user_level) {
        $access_rank = CRRG_RANKS[$access_level] ?? ['name' => $access];
        $user_rank_data = CRRG_RANKS[$user_level] ?? ['name' => $user_rank];
        wp_die('<div style="text-align:center;padding:60px 20px;font-family:Microsoft YaHei,sans-serif;"><h1 style="color:#C41230;">🔒 访问受限</h1><p>此报告需要 <strong>' . esc_html($access_rank['name']) . '</strong> 及以上等级方可查阅。</p><p>当前等级：' . esc_html($user_rank_data['name']) . '</p><a href="/" style="color:#1B3A5C;">← 返回首页</a></div>', '访问受限', ['response' => 403]);
    }
});

// ─── 文章标签展示 + 相关文章 ───
add_filter('the_content', function ($content) {
    if (!is_single() || !is_main_query() || !in_the_loop()) return $content;
    $post_id = get_the_ID();
    
    // 威胁等级徽章
    $threat = get_post_meta($post_id, 'crrg_threat_level', true);
    $threat_badge = '';
    if ($threat && isset(CRRG_THREAT_LEVELS[$threat])) {
        $threat_badge = crrg_threat_info($threat);
    }
    
    // 地图链接
    $lat = get_post_meta($post_id, 'crrg_lat', true);
    $map_link = '';
    if ($lat) {
        $loc = get_post_meta($post_id, 'crrg_location', true);
        $map_link = '<div style="margin-bottom:16px;font-size:13px;">📍 <a href="/map/" style="color:#1B3A5C;">在地图上查看：' . esc_html($loc) . '</a></div>';
    }
    
    // 事件时间范围
    $event_range = '';
    $cat = get_post_meta($post_id, 'crrg_report_type', true);
    if ($cat === 'events') {
        $es = get_post_meta($post_id, 'crrg_event_start', true);
        $ee = get_post_meta($post_id, 'crrg_event_end', true);
        if ($es || $ee) {
            $start_str = $es ? date('Y年n月j日 H:i', strtotime($es)) : '不限';
            $end_str = $ee ? date('Y年n月j日 H:i', strtotime($ee)) : '至今';
            $event_range = '<div style="margin-bottom:16px;padding:8px 14px;background:#f0f5fa;border-left:3px solid #1B3A5C;border-radius:2px;font-size:13px;"><strong>⏱ 事件时间：</strong>' . $start_str . ' → ' . $end_str . '</div>';
        }
    }
    
    // 面包屑
    $cat_name = get_post_meta($post_id, 'crrg_report_type_name', true);
    $breadcrumb = '<div style="margin-bottom:16px;font-size:12px;color:#999;"><a href="/" style="color:#999;">首页</a> › ';
    if ($cat_name) {
        $slug_map = ['镇物'=>'artifacts','事件'=>'events','人物'=>'personnel','组织'=>'organizations','研究发现'=>'research','祂们'=>'entities','秘术'=>'esoterica','优秀员工'=>'outstanding'];
        $slug = $slug_map[$cat_name] ?? '';
        if ($slug) $breadcrumb .= '<a href="/'.$slug.'/" style="color:#999;">'.$cat_name.'</a> › ';
        else $breadcrumb .= $cat_name . ' › ';
    }
    $breadcrumb .= '<span style="color:#666;">正文</span></div>';
    
    // 把面包屑、威胁、地图加到正文前面
    $content = $breadcrumb . $threat_badge . $map_link . $event_range . $content;
    
    $tags = get_the_tags($post_id);
    if (!$tags || empty($tags)) return $content;
    ob_start();
    ?>
    <div class="crrg-post-tags" style="margin:24px 0;padding:16px;background:#f8f9fa;border:1px solid #e0e0e0;border-radius:4px;">
        <span style="font-weight:600;color:#1e293b;margin-right:8px;"> 标签：</span>
        <?php foreach ($tags as $tag): ?>
            <a href="<?php echo get_tag_link($tag); ?>" style="display:inline-block;margin:2px 4px;padding:4px 12px;background:#1B3A5C;color:#fff;border-radius:3px;font-size:13px;text-decoration:none;"><?php echo esc_html($tag->name); ?></a>
        <?php endforeach; ?>
    </div>
    <?php
    $first_tag = $tags[0];
    $related = new WP_Query([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 3,
        'post__not_in' => [$post_id],
        'tag_id' => $first_tag->term_id,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    if ($related->have_posts()):
        ?>
        <div class="crrg-related-posts" style="margin:20px 0;padding:16px;background:#f0f5fa;border:1px solid #d1d5db;border-radius:4px;">
            <h4 style="margin:0 0 12px;color:#1e293b;"> 相关报告</h4>
            <ul style="margin:0;padding:0;list-style:none;">
                <?php while ($related->have_posts()): $related->the_post(); ?>
                    <li style="margin-bottom:6px;">→ <a href="<?php the_permalink(); ?>" style="color:#046bd2;"><?php the_title(); ?></a></li>
                <?php endwhile; wp_reset_postdata(); ?>
            </ul>
        </div>
    <?php endif;
    $tags_html = ob_get_clean();
    return $content . $tags_html;
});

// ===== REST API 安全加固（2026-09-01）=====
// 禁止匿名 REST 写请求（POST/PUT/PATCH/DELETE），拦截批量建号/提权攻击（如 w2s_* 管理员账号事件）
add_filter('rest_authentication_errors', function ($result) {
    if (!is_user_logged_in() && isset($_SERVER['REQUEST_METHOD'])) {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return new WP_Error(
                'rest_forbidden',
                'REST API 写操作需要登录。',
                ['status' => 403]
            );
        }
    }
    return $result;
});

/* ===== PWA / 手机端"添加到主屏幕"（2026-09-29） =====
 * 原则：**内容永远走线上**（与站点同步）；SW 只在 HTTPS 下注册，且只缓存壳与离线页。
 */
add_action('wp_head', function () {
    if (is_admin()) return;
    echo "\n<!-- CRRG 终端 · PWA -->\n";
    echo '<link rel="manifest" href="/app-shell/manifest.webmanifest">' . "\n";
    echo '<meta name="theme-color" content="#0a0a0c">' . "\n";
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
    echo '<meta name="apple-mobile-web-app-title" content="CRRG 终端">' . "\n";
    echo '<link rel="apple-touch-icon" href="/app-shell/icon-192.png">' . "\n";
    echo '<script>if("serviceWorker" in navigator && location.protocol === "https:"){addEventListener("load",function(){navigator.serviceWorker.register("/app-shell/sw.js");});}</script>' . "\n";
}, 5);

/**
 * 演习页**禁用缓存**（2026-10-01）：页面把窗口层 JS 内嵌在 HTML 里 ✗，
 * 浏览器缓存旧 HTML 会让玩家跑到旧代码（症状：新按钮点了回「没接上动作」✗）。
 * 另外加版本注释标记，便于一眼确认玩家拿到的是哪一版 ✓
 */
add_action('template_redirect', function () {
    if (is_page('drill') || (function_exists('is_page') && is_page(3201))) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}, 1);

/* 2026-10-19: 把页面 568（基地俯瞰）从 wp_page_menu() 自动列表里排除；页面本身仍可访问 */
add_filter('wp_page_menu_args', function ($args) {
    $ex = (array) ($args['exclude'] ?? []);
    $ex[] = 568;
    $args['exclude'] = implode(',', array_unique(array_filter($ex)));
    return $args;
});
