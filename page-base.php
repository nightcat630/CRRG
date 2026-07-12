<?php
/* Template Name: 基地俯瞰 */
get_header();
?>
<div class="gov-main">
<div class="gov-content" style="padding:12px;">
    <h1 style="font-size:20px;color:#1B3A5C;margin:0 0 4px;">祁连山南麓总基地 · 结构俯瞰</h1>
    <div style="color:#999;font-size:12px;margin-bottom:12px;border-bottom:1px solid #eee;padding-bottom:6px;">中央重生抵御小组 · 机密档案</div>

    <svg viewBox="0 0 1100 800" style="width:100%;max-width:1100px;font-family:'Microsoft YaHei',sans-serif;">
        <defs>
            <pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M 20 0 L 0 0 0 20" fill="none" stroke="#1a1a2e" stroke-width="0.3"/></pattern>
            <pattern id="hatch" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><line x1="0" y1="0" x2="0" y2="6" stroke="#C41230" stroke-width="1" opacity="0.3"/></pattern>
        </defs>
        
        <!-- 山体外轮廓 -->
        <ellipse cx="550" cy="480" rx="520" ry="350" fill="#111827" stroke="#374151" stroke-width="2"/>
        <text x="550" y="770" fill="#374151" font-size="10" text-anchor="middle">祁连山南麓山体 · 地下设施</text>
        
        <!-- ===== A区 行政与生活区 ===== -->
        <rect x="50" y="60" width="320" height="260" fill="#1B3A5C" stroke="#F0A500" stroke-width="1.5" rx="2" opacity="0.92"/>
        <text x="210" y="80" fill="#F0A500" font-size="12" font-weight="bold" text-anchor="middle">A区 · 行政与生活区</text>
        
        <!-- 中央走廊 -->
        <rect x="200" y="88" width="20" height="224" fill="#2a3a4e" stroke="#3a4a5e" stroke-width="0.5"/>
        <text x="198" y="110" fill="#999" font-size="8" text-anchor="end">中</text><text x="198" y="120" fill="#999" font-size="8" text-anchor="end">央</text><text x="198" y="130" fill="#999" font-size="8" text-anchor="end">走</text><text x="198" y="140" fill="#999" font-size="8" text-anchor="end">廊</text>
        
        <!-- 左侧房间 -->
        <rect x="60" y="90" width="60" height="40" fill="#243656" stroke="#3a5a7e" rx="1"/><text x="90" y="114" fill="#aac" font-size="8" text-anchor="middle">办公室</text>
        <rect x="60" y="135" width="60" height="35" fill="#243656" stroke="#3a5a7e" rx="1"/><text x="90" y="156" fill="#aac" font-size="7" text-anchor="middle">文职室</text>
        <rect x="60" y="175" width="60" height="50" fill="#243656" stroke="#3a5a7e" rx="1"/><text x="90" y="204" fill="#aac" font-size="8" text-anchor="middle">会议室</text>
        <rect x="60" y="230" width="130" height="35" fill="#243656" stroke="#F0A500" stroke-width="1" rx="1"/><text x="125" y="251" fill="#F0A500" font-size="7" text-anchor="middle">🔒 档案室 · 双人双锁</text>
        <rect x="60" y="270" width="60" height="30" fill="#243656" stroke="#3a5a7e" rx="1"/><text x="90" y="289" fill="#aac" font-size="8" text-anchor="middle">医疗室</text>
        
        <!-- 右侧房间 -->
        <rect x="225" y="90" width="135" height="65" fill="#243656" stroke="#3a5a7e" rx="1"/><text x="292" y="120" fill="#aac" font-size="8" text-anchor="middle">宿舍区（单人×6）</text><text x="292" y="134" fill="#666" font-size="7" text-anchor="middle">基本设施 · 轮值共用</text>
        <rect x="225" y="160" width="70" height="55" fill="#243656" stroke="#3a5a7e" rx="1"/><text x="260" y="192" fill="#aac" font-size="8" text-anchor="middle">食堂</text>
        <rect x="295" y="160" width="65" height="55" fill="#243656" stroke="#3a5a7e" rx="1"/><text x="327" y="192" fill="#aac" font-size="8" text-anchor="middle">休息室</text>
        <rect x="225" y="220" width="135" height="45" fill="#243656" stroke="#3a5a7e" rx="1"/><text x="292" y="240" fill="#aac" font-size="8" text-anchor="middle">气密闸门 · 缓冲区</text><text x="292" y="252" fill="#888" font-size="7" text-anchor="middle">紧急分段封闭</text>
        
        <!-- A区入口 -->
        <rect x="170" y="318" width="50" height="8" fill="#F0A500" rx="1"/>
        <text x="195" y="334" fill="#999" font-size="7" text-anchor="middle">← 基地入口（山体外侧）</text>
        
        <!-- ===== 主通道 ===== -->
        <rect x="370" y="130" width="40" height="330" fill="#1a1a2e" stroke="#444" stroke-width="0.5"/>
        <text x="390" y="300" fill="#555" font-size="8" text-anchor="middle" transform="rotate(-90,390,300)">主 通 道（电磁屏蔽 · 气密闸门）</text>
        
        <!-- ===== B区 指挥情报中心 ===== -->
        <rect x="50" y="340" width="320" height="230" fill="#0F2440" stroke="#F0A500" stroke-width="1.5" rx="2" opacity="0.92"/>
        <text x="210" y="360" fill="#F0A500" font-size="12" font-weight="bold" text-anchor="middle">B区 · 指挥与情报中心</text>
        
        <rect x="60" y="370" width="140" height="70" fill="#121a2e" stroke="#3a5a7e" rx="1"/><text x="130" y="395" fill="#ccd" font-size="9" font-weight="bold" text-anchor="middle">指挥大厅</text><text x="130" y="410" fill="#888" font-size="7" text-anchor="middle">加密通讯 · 异常监测终端</text><text x="130" y="422" fill="#888" font-size="7" text-anchor="middle">实时任务追踪屏</text>
        <rect x="205" y="370" width="75" height="40" fill="#121a2e" stroke="#C41230" stroke-width="1" rx="1"/><text x="242" y="390" fill="#faa" font-size="7" text-anchor="middle">🛡️作战</text><text x="242" y="402" fill="#faa" font-size="7" text-anchor="middle">值班室</text>
        <rect x="205" y="415" width="75" height="25" fill="#121a2e" stroke="#3a5a7e" rx="1"/><text x="242" y="431" fill="#aac" font-size="7" text-anchor="middle">武器柜</text>
        <rect x="285" y="370" width="75" height="70" fill="#121a2e" stroke="#3a5a7e" rx="1"/><text x="322" y="390" fill="#aac" font-size="8" text-anchor="middle">情报</text><text x="322" y="403" fill="#aac" font-size="8" text-anchor="middle">分析室</text><text x="322" y="418" fill="#666" font-size="6" text-anchor="middle">4-6人常驻</text><text x="322" y="430" fill="#666" font-size="6" text-anchor="middle">独立服务器</text>
        
        <rect x="60" y="445" width="100" height="40" fill="#121a2e" stroke="#3a5a7e" rx="1"/><text x="110" y="465" fill="#aac" font-size="8" text-anchor="middle">通讯室</text><text x="110" y="477" fill="#666" font-size="6" text-anchor="middle">加密·卫星·秘术传讯</text>
        <rect x="165" y="445" width="80" height="40" fill="#121a2e" stroke="#3a5a7e" rx="1"/><text x="205" y="465" fill="#aac" font-size="8" text-anchor="middle">地图室</text><text x="205" y="477" fill="#666" font-size="6" text-anchor="middle">手动更新</text>
        <rect x="250" y="445" width="110" height="40" fill="#121a2e" stroke="#F0A500" stroke-width="1" rx="1"/><text x="305" y="465" fill="#F0A500" font-size="8" text-anchor="middle">技术研发室 🔬</text><text x="305" y="477" fill="#666" font-size="6" text-anchor="middle">秘术·电子信号互转</text>
        
        <rect x="60" y="490" width="300" height="30" fill="none" stroke="#888" stroke-width="0.5" stroke-dasharray="3,3" rx="1"/>
        <text x="210" y="510" fill="#888" font-size="7" text-anchor="middle">直连兰州政治中心加密线路 · 乌鲁木齐分部 · 各地分基地</text>
        
        <!-- ===== C区 高危收容区 ===== -->
        <rect x="410" y="50" width="340" height="280" fill="#2a0808" stroke="#C41230" stroke-width="2" rx="2" opacity="0.92"/>
        <pattern id="hatchC" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><line x1="0" y1="0" x2="0" y2="8" stroke="#C41230" stroke-width="0.8" opacity="0.15"/></pattern>
        <rect x="410" y="50" width="340" height="280" fill="url(#hatchC)" rx="2" opacity="0.3"/>
        <text x="580" y="70" fill="#C41230" font-size="12" font-weight="bold" text-anchor="middle">C区 · 高危收容区 ⚠️</text>
        
        <!-- 气密闸门 -->
        <rect x="440" y="78" width="280" height="12" fill="#222" stroke="#C41230" stroke-width="1"/><text x="580" y="87" fill="#C41230" font-size="7" text-anchor="middle">气密闸门① → 气密闸门② → 气密闸门③ · 50m缓冲区</text>
        
        <!-- C1 信息污染型 -->
        <rect x="420" y="98" width="150" height="110" fill="#1a0404" stroke="#8B0000" rx="1"/>
        <text x="495" y="114" fill="#faa" font-size="9" font-weight="bold" text-anchor="middle">C1 · 信息污染型收容廊</text>
        <rect x="430" y="122" width="40" height="35" fill="#2a0808" stroke="#600" rx="1"/><text x="450" y="146" fill="#e88" font-size="6" text-anchor="middle">收容01</text>
        <rect x="475" y="122" width="40" height="35" fill="#2a0808" stroke="#600" rx="1"/><text x="495" y="146" fill="#e88" font-size="6" text-anchor="middle">收容02</text>
        <rect x="520" y="122" width="40" height="35" fill="#2a0808" stroke="#600" rx="1"/><text x="540" y="146" fill="#e88" font-size="6" text-anchor="middle">收容03</text>
        <text x="495" y="178" fill="#888" font-size="6" text-anchor="middle">电磁屏蔽 · 吸音 · 常亮冷光灯 · 红外热成像</text>
        <text x="495" y="190" fill="#888" font-size="6" text-anchor="middle">无开关 · 独立隔音 · 入口储存柜</text>
        
        <!-- C2 生物危害型 -->
        <rect x="580" y="98" width="160" height="110" fill="#1a0404" stroke="#8B0000" rx="1"/>
        <text x="660" y="114" fill="#faa" font-size="9" font-weight="bold" text-anchor="middle">C2 · 生物危害型收容廊</text>
        <rect x="590" y="122" width="40" height="35" fill="#2a0808" stroke="#600" rx="1"/><text x="610" y="146" fill="#e88" font-size="6" text-anchor="middle">收容04</text>
        <rect x="635" y="122" width="40" height="35" fill="#2a0808" stroke="#600" rx="1"/><text x="655" y="146" fill="#e88" font-size="6" text-anchor="middle">收容05</text>
        <rect x="680" y="122" width="40" height="35" fill="#2a0808" stroke="#600" rx="1"/><text x="700" y="146" fill="#e88" font-size="6" text-anchor="middle">收容06</text>
        <text x="660" y="178" fill="#888" font-size="6" text-anchor="middle">多层复合 · 真空隔离 · 电磁屏蔽</text>
        <text x="660" y="190" fill="#888" font-size="6" text-anchor="middle">认知扭曲防护 · 低频信号屏蔽</text>
        
        <!-- 高危收容室 -->
        <rect x="420" y="215" width="100" height="40" fill="#2a0404" stroke="#C41230" stroke-width="1.5" rx="1"/>
        <text x="470" y="235" fill="#C41230" font-size="8" font-weight="bold" text-anchor="middle">高危收容室</text><text x="470" y="248" fill="#e88" font-size="6" text-anchor="middle">魔级 · 定制措施</text>
        
        <!-- 观察室 -->
        <rect x="530" y="215" width="70" height="40" fill="#1a1a2e" stroke="#3a5a7e" rx="1"/>
        <text x="565" y="235" fill="#aac" font-size="8" text-anchor="middle">观察室</text><text x="565" y="248" fill="#666" font-size="6" text-anchor="middle">远程监控</text>
        
        <!-- 转运走廊 -->
        <rect x="610" y="215" width="130" height="55" fill="#1a1a2e" stroke="#C41230" stroke-width="1" rx="1"/>
        <text x="675" y="235" fill="#faa" font-size="8" text-anchor="middle">🛡️ 转运走廊</text><text x="675" y="248" fill="#e88" font-size="6" text-anchor="middle">特殊勤务司武装值守</text><text x="675" y="260" fill="#888" font-size="6" text-anchor="middle">防爆气密门 · 分段封锁</text>
        
        <text x="580" y="310" fill="#888" font-size="7" text-anchor="middle">独立通风 · 独立供电（C3不接主电网）· 排气高温焚化</text>
        
        <!-- ===== D区 研究实验区 ===== -->
        <rect x="410" y="340" width="340" height="130" fill="#0a1a0a" stroke="#F0A500" stroke-width="1.5" rx="2" opacity="0.92"/>
        <text x="580" y="358" fill="#F0A500" font-size="11" font-weight="bold" text-anchor="middle">D区 · 研究实验区</text>
        
        <rect x="420" y="368" width="95" height="45" fill="#0a150a" stroke="#2a5a2a" rx="1"/><text x="467" y="388" fill="#afa" font-size="8" text-anchor="middle">生物实验室</text><text x="467" y="402" fill="#666" font-size="6" text-anchor="middle">负压 · 高温灭活</text>
        <rect x="520" y="368" width="95" height="45" fill="#0a150a" stroke="#2a5a2a" rx="1"/><text x="567" y="388" fill="#afa" font-size="8" text-anchor="middle">理化分析室</text><text x="567" y="402" fill="#666" font-size="6" text-anchor="middle">光谱·色谱·材料</text>
        <rect x="620" y="368" width="120" height="45" fill="#0a150a" stroke="#F0A500" stroke-width="1" rx="1"/><text x="680" y="388" fill="#F0A500" font-size="8" text-anchor="middle">秘术研究室</text><text x="680" y="402" fill="#666" font-size="6" text-anchor="middle">六爻手札电子件 · 思之共振装置</text>
        <rect x="420" y="418" width="80" height="40" fill="#0a150a" stroke="#2a5a2a" rx="1"/><text x="460" y="438" fill="#afa" font-size="8" text-anchor="middle">样本存储室</text><text x="460" y="450" fill="#666" font-size="6" text-anchor="middle">双人登记</text>
        <rect x="505" y="418" width="100" height="40" fill="#0a150a" stroke="#C41230" stroke-width="1" rx="1"/><text x="555" y="438" fill="#faa" font-size="7" text-anchor="middle">隔离实验室</text><text x="555" y="450" fill="#666" font-size="6" text-anchor="middle">远程机械手 · 消杀</text>
        <rect x="610" y="418" width="130" height="40" fill="#0a150a" stroke="#888" stroke-width="0.5" stroke-dasharray="3,3" rx="1"/><text x="675" y="438" fill="#888" font-size="7" text-anchor="middle">废弃物高温焚烧炉</text>
        
        <!-- ===== E区 后勤保障区 ===== -->
        <rect x="50" y="590" width="320" height="195" fill="#1a1a0a" stroke="#F0A500" stroke-width="1.5" rx="2" opacity="0.92"/>
        <text x="210" y="610" fill="#F0A500" font-size="11" font-weight="bold" text-anchor="middle">E区 · 后勤保障区</text>
        
        <rect x="60" y="620" width="90" height="35" fill="#1a1a10" stroke="#3a3a1e" rx="1"/><text x="105" y="640" fill="#ccc" font-size="7" text-anchor="middle">主供电</text><text x="105" y="650" fill="#888" font-size="6" text-anchor="middle">专线+柴油+蓄电池</text>
        <rect x="155" y="620" width="70" height="35" fill="#1a1a10" stroke="#3a3a1e" rx="1"/><text x="190" y="641" fill="#ccc" font-size="7" text-anchor="middle">通风过滤</text>
        <rect x="230" y="620" width="70" height="35" fill="#1a1a10" stroke="#3a3a1e" rx="1"/><text x="265" y="641" fill="#ccc" font-size="7" text-anchor="middle">水源净化</text>
        <rect x="305" y="620" width="55" height="35" fill="#1a1a10" stroke="#888" stroke-dasharray="3,3" rx="1"/><text x="332" y="641" fill="#888" font-size="7" text-anchor="middle">30日储水</text>
        
        <rect x="60" y="660" width="130" height="45" fill="#1a1a10" stroke="#3a3a1e" rx="1"/><text x="125" y="680" fill="#ccc" font-size="8" text-anchor="middle">物资仓库</text><text x="125" y="694" fill="#888" font-size="6" text-anchor="middle">3个月全封闭储备 · 食品·药品·弹药</text>
        <rect x="195" y="660" width="60" height="45" fill="#1a1a10" stroke="#F0A500" stroke-width="1" rx="1"/><text x="225" y="680" fill="#F0A500" font-size="7" text-anchor="middle">🔒武器库</text><text x="225" y="694" fill="#888" font-size="6" text-anchor="middle">双人登记</text>
        <rect x="260" y="660" width="100" height="45" fill="#1a1a10" stroke="#3a3a1e" rx="1"/><text x="310" y="680" fill="#ccc" font-size="8" text-anchor="middle">车库</text><text x="310" y="694" fill="#666" font-size="6" text-anchor="middle">越野·运输·装甲撤离车</text>
        
        <rect x="60" y="710" width="300" height="30" fill="#1a1a10" stroke="#888" stroke-width="0.5" rx="1"/>
        <text x="210" y="722" fill="#666" font-size="7" text-anchor="middle">装备申领窗口 · 外勤装备库（检测仪·防护服·应急通讯·常规武器）</text>
        <text x="210" y="734" fill="#666" font-size="7" text-anchor="middle">独立隔间：朱昂沃时间车原型（封存 · 非激活）</text>
        
        <!-- ===== F区 特殊勤务司 ===== -->
        <rect x="760" y="50" width="310" height="530" fill="#051525" stroke="#F0A500" stroke-width="1.5" rx="2" opacity="0.92"/>
        <text x="915" y="70" fill="#F0A500" font-size="12" font-weight="bold" text-anchor="middle">F区 · 特殊勤务司驻防区</text>
        
        <rect x="770" y="82" width="140" height="50" fill="#0a1520" stroke="#2a4a6a" rx="1"/><text x="840" y="102" fill="#aac" font-size="9" text-anchor="middle">营房</text><text x="840" y="118" fill="#666" font-size="7" text-anchor="middle">小队编制 · 独立储物</text>
        <rect x="915" y="82" width="145" height="25" fill="#0a1520" stroke="#2a4a6a" rx="1"/><text x="987" y="98" fill="#aac" font-size="8" text-anchor="middle">军械维护室</text>
        <rect x="915" y="108" width="145" height="24" fill="#0a1520" stroke="#2a4a6a" rx="1"/><text x="987" y="124" fill="#aac" font-size="8" text-anchor="middle">装备整备室</text>
        
        <rect x="770" y="140" width="140" height="40" fill="#0a1520" stroke="#2a4a6a" rx="1"/><text x="840" y="163" fill="#aac" font-size="8" text-anchor="middle">体能训练室</text>
        <rect x="915" y="140" width="145" height="40" fill="#0a1520" stroke="#2a4a6a" rx="1"/><text x="987" y="163" fill="#aac" font-size="8" text-anchor="middle">战术模拟室</text>
        
        <rect x="770" y="188" width="290" height="35" fill="#0a1520" stroke="#2a4a6a" rx="1"/><text x="915" y="210" fill="#aac" font-size="8" text-anchor="middle">简报室 · 加密终端直连指挥大厅</text>
        
        <!-- 快速反应通道 -->
        <rect x="770" y="235" width="290" height="35" fill="#1a0a0a" stroke="#C41230" stroke-width="1.5" rx="1"/>
        <text x="915" y="257" fill="#C41230" font-size="9" font-weight="bold" text-anchor="middle">⚡ 快速反应通道 · 3分钟武装整备</text>
        <line x1="770" y1="270" x2="1060" y2="270" stroke="#C41230" stroke-width="1" stroke-dasharray="6,3"/>
        
        <rect x="770" y="280" width="290" height="25" fill="#0a1520" stroke="#888" stroke-width="0.5" rx="1"/>
        <text x="915" y="296" fill="#888" font-size="7" text-anchor="middle">应急武器柜（预置装备）</text>
        
        <!-- F区通道连接 -->
        <rect x="750" y="160" width="12" height="80" fill="#F0A500" opacity="0.4"/>
        <text x="748" y="210" fill="#F0A500" font-size="6" text-anchor="end">→A/E区</text>
        
        <!-- 图例 -->
        <rect x="770" y="555" width="290" height="22" fill="none" stroke="#333" stroke-width="0.5" rx="2"/>
        <text x="780" y="571" fill="#666" font-size="7">🔒管制区 ⚠️高危区 🛡️武装值守 🔬研究设施 ⚡快速通道 --- 虚线=非实体连接</text>
        
        <!-- ===== 应急撤离通道 ===== -->
        <line x1="440" y1="500" x2="360" y2="560" stroke="#C41230" stroke-width="1.5" stroke-dasharray="8,4"/>
        <text x="370" y="545" fill="#C41230" font-size="7" transform="rotate(-45,370,545)">撤离通道①</text>
        <line x1="580" y1="500" x2="580" y2="580" stroke="#C41230" stroke-width="1.5" stroke-dasharray="8,4"/>
        <text x="570" y="555" fill="#C41230" font-size="7" transform="rotate(-90,570,555)">撤离通道②</text>
        <line x1="720" y1="500" x2="780" y2="555" stroke="#C41230" stroke-width="1.5" stroke-dasharray="8,4"/>
        <text x="750" y="510" fill="#C41230" font-size="7">撤离通道③</text>
        <text x="750" y="540" fill="#666" font-size="7">仅组长及指定人员知晓出口位置</text>
    </svg>
    
    <p style="margin-top:8px;font-size:10px;color:#666;text-align:center;">祁连山南麓总基地 · 结构俯瞰示意图 · 机密档案</p>
</div>
</div>
<?php get_footer(); ?>
