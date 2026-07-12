<?php
/* Template Name: 基地俯瞰 */
get_header();
?>
<div class="gov-main">
<div class="gov-content" style="padding:16px;">
    <h1 style="font-size:20px;color:#1B3A5C;margin:0 0 4px;">祁连山南麓总基地 · 结构俯瞰</h1>
    <div style="color:#999;font-size:12px;margin-bottom:16px;border-bottom:1px solid #eee;padding-bottom:8px;">中央重生抵御小组 · 机密档案</div>

    <svg viewBox="0 0 900 600" style="width:100%;max-width:900px;font-size:11px;font-family:'Microsoft YaHei',sans-serif;">
        <!-- 背景 - 山体 -->
        <rect x="0" y="0" width="900" height="600" fill="#1a1a2e" rx="8"/>
        <text x="450" y="590" fill="#333" font-size="10" text-anchor="middle">祁连山南麓 · 地下设施</text>

        <!-- A区 - 行政与生活 -->
        <rect x="20" y="20" width="280" height="180" fill="#1B3A5C" rx="4" opacity="0.9"/>
        <text x="160" y="42" fill="#fff" font-weight="bold" font-size="13" text-anchor="middle">A区 · 行政与生活区</text>
        <line x1="40" y1="50" x2="280" y2="50" stroke="#F0A500" stroke-width="1"/>
        <text x="40" y="68" fill="#ccc">中央走廊（气密闸门）</text>
        <text x="40" y="84" fill="#ccc">办公室 ×5</text>
        <text x="40" y="100" fill="#ccc">会议室 ×2（大/小）</text>
        <text x="40" y="116" fill="#ccc">档案室 🔒 双人双锁</text>
        <text x="40" y="132" fill="#ccc">宿舍区</text>
        <text x="40" y="148" fill="#ccc">食堂 · 休息室</text>
        <text x="40" y="164" fill="#ccc">医疗室</text>
        <text x="40" y="180" fill="#888" font-size="9">← 基地入口（山体外侧）</text>

        <!-- B区 - 指挥情报 -->
        <rect x="20" y="220" width="280" height="170" fill="#0F2440" rx="4" opacity="0.9"/>
        <text x="160" y="242" fill="#fff" font-weight="bold" font-size="13" text-anchor="middle">B区 · 指挥与情报中心</text>
        <line x1="40" y1="250" x2="280" y2="250" stroke="#F0A500" stroke-width="1"/>
        <text x="40" y="268" fill="#ccc">指挥大厅</text>
        <text x="40" y="284" fill="#ccc">作战值班室 🛡️ 24h轮守</text>
        <text x="40" y="300" fill="#ccc">情报分析室（4-6人）</text>
        <text x="40" y="316" fill="#ccc">通讯室（加密·卫星·秘术传讯）</text>
        <text x="40" y="332" fill="#ccc">地图室（手动更新）</text>
        <text x="40" y="348" fill="#ccc">技术研发室 🔬</text>
        <text x="40" y="364" fill="#888" font-size="9">直连兰州政治中心</text>

        <!-- 中间通道 -->
        <rect x="300" y="180" width="60" height="250" fill="#2a2a3e" rx="2"/>
        <text x="330" y="310" fill="#666" font-size="10" text-anchor="middle" transform="rotate(-90,330,310)">主通道</text>

        <!-- C区 - 高危收容 -->
        <rect x="360" y="20" width="260" height="240" fill="#3d0000" rx="4" opacity="0.9"/>
        <text x="490" y="42" fill="#C41230" font-weight="bold" font-size="13" text-anchor="middle">C区 · 高危收容区 ⚠️</text>
        <line x1="380" y1="50" x2="600" y2="50" stroke="#C41230" stroke-width="1"/>
        <text x="380" y="68" fill="#fcc">三道气密闸门 · 50m缓冲区</text>
        <text x="380" y="88" fill="#fcc">C1 信息污染型收容廊</text>
        <text x="395" y="102" fill="#e88">电磁屏蔽 · 常亮冷光灯 · 红外监控</text>
        <text x="380" y="122" fill="#fcc">C2 生物危害型收容廊</text>
        <text x="395" y="136" fill="#e88">多层复合 · 真空隔离 · 电磁屏蔽</text>
        <text x="380" y="156" fill="#fcc">高危收容室（魔级·定制）</text>
        <text x="380" y="172" fill="#fcc">观察室（认知稳定性测试）</text>
        <text x="380" y="188" fill="#fcc">转运走廊 🛡️ 武装值守</text>
        <text x="380" y="204" fill="#888" font-size="9">独立通风 · 独立供电 · 高温焚化排气</text>

        <!-- D区 -->
        <rect x="360" y="280" width="260" height="140" fill="#1a3a1a" rx="4" opacity="0.9"/>
        <text x="490" y="302" fill="#fff" font-weight="bold" font-size="13" text-anchor="middle">D区 · 研究实验区</text>
        <line x1="380" y1="310" x2="600" y2="310" stroke="#F0A500" stroke-width="1"/>
        <text x="380" y="328" fill="#cfc">生物实验室（负压·高温灭活）</text>
        <text x="380" y="344" fill="#cfc">理化分析室</text>
        <text x="380" y="360" fill="#cfc">秘术研究室（六爻手札电子件）</text>
        <text x="380" y="376" fill="#cfc">样本存储室</text>
        <text x="380" y="392" fill="#cfc">隔离实验室（远程机械手）</text>

        <!-- E区 -->
        <rect x="20" y="410" width="280" height="170" fill="#2a2a1a" rx="4" opacity="0.9"/>
        <text x="160" y="432" fill="#fff" font-weight="bold" font-size="13" text-anchor="middle">E区 · 后勤保障区</text>
        <line x1="40" y1="440" x2="280" y2="440" stroke="#F0A500" stroke-width="1"/>
        <text x="40" y="458" fill="#ccc">主供电·柴油备电·蓄电池</text>
        <text x="40" y="474" fill="#ccc">通风过滤（C区独立循环）</text>
        <text x="40" y="490" fill="#ccc">水源（地下水·30日应急储水）</text>
        <text x="40" y="506" fill="#ccc">物资仓库（3个月全封闭储备）</text>
        <text x="40" y="522" fill="#ccc">武器库 🔒</text>
        <text x="40" y="538" fill="#ccc">车库（越野·运输·装甲撤离车）</text>
        <text x="40" y="554" fill="#666" font-size="9">· 朱昂沃时间车原型（封存）</text>

        <!-- F区 -->
        <rect x="640" y="20" width="240" height="560" fill="#0a1a2a" rx="4" opacity="0.9"/>
        <text x="760" y="42" fill="#fff" font-weight="bold" font-size="13" text-anchor="middle">F区 · 特殊勤务司驻防区</text>
        <line x1="660" y1="50" x2="860" y2="50" stroke="#F0A500" stroke-width="1"/>
        <text x="660" y="68" fill="#ccc">营房</text>
        <text x="660" y="84" fill="#ccc">军械维护室</text>
        <text x="660" y="100" fill="#ccc">装备整备室</text>
        <text x="660" y="116" fill="#ccc">体能训练室</text>
        <text x="660" y="132" fill="#ccc">战术模拟室</text>
        <text x="660" y="148" fill="#ccc">简报室</text>
        <text x="660" y="170" fill="#F0A500" font-size="10">⚡ 快速反应通道</text>
        <text x="660" y="186" fill="#888" font-size="9">直通车库·3分钟武装整备</text>
        
        <!-- F区通道标注 -->
        <rect x="630" y="90" width="12" height="8" fill="#F0A500" opacity="0.6"/>
        <text x="630" y="120" fill="#F0A500" font-size="8">直连A/E区</text>

        <!-- 应急通道 -->
        <rect x="320" y="440" width="30" height="8" fill="#C41230" rx="1" opacity="0.7"/>
        <text x="320" y="462" fill="#C41230" font-size="9">应急撤离通道 ×3</text>
        <text x="320" y="474" fill="#666" font-size="8">仅组长及指定人员知晓</text>

        <!-- 图例 -->
        <rect x="660" y="560" width="220" height="30" fill="none" stroke="#444" stroke-width="0.5" rx="2"/>
        <text x="670" y="580" fill="#666" font-size="9">🔒 管制区域  ⚠️ 高危  🛡️ 武装值守  🔬 研究设施</text>
    </svg>

    <p style="margin-top:12px;font-size:11px;color:#999;text-align:center;">本示意图基于《祁连山南麓总基地档案》绘制 · 仅供内部参考</p>
</div>
</div>
<?php get_footer(); ?>
