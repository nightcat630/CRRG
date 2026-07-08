<?php
/* Template Name: 值班表 */
get_header();

$duty = get_option('crrg_duty_roster', [
    ['name'=>'朱贞吉','rank'=>'委员长','period'=>'本月','status'=>'在岗'],
    ['name'=>'柯达','rank'=>'行动员','period'=>'本月','status'=>'在岗'],
]);
?>
<div class="gov-main">
<div class="gov-content">
    <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;">🕐 值班表</h1>
    <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">中央重生抵御小组 · 轮值安排</div>

    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead>
            <tr style="background:#1B3A5C;color:#fff;">
                <th style="padding:10px 14px;text-align:left;">值班人员</th>
                <th style="padding:10px 14px;text-align:left;">等级</th>
                <th style="padding:10px 14px;text-align:left;">值班周期</th>
                <th style="padding:10px 14px;text-align:left;">状态</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($duty as $d): 
                $status_color = $d['status']==='在岗'?'#16a34a':($d['status']==='轮休'?'#F0A500':'#999');
            ?>
                <tr style="border-bottom:1px solid #e0e0e0;">
                    <td style="padding:10px 14px;font-weight:600;"><?php echo esc_html($d['name']); ?></td>
                    <td style="padding:10px 14px;color:#666;"><?php echo esc_html($d['rank']); ?></td>
                    <td style="padding:10px 14px;color:#666;"><?php echo esc_html($d['period']); ?></td>
                    <td style="padding:10px 14px;"><span style="display:inline-block;padding:2px 10px;border-radius:3px;font-size:12px;font-weight:600;background:<?php echo $status_color; ?>;color:#fff;"><?php echo esc_html($d['status']); ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top:16px;font-size:12px;color:#999;">⚠️ 如遇紧急异常事件，请立即联系当前值班人员。值班表由委员长统一调度。</p>
</div>
<div class="gov-sidebar">
    <div class="widget">
        <div class="widget-title">📋 当前在岗</div>
        <?php $on_duty = array_filter($duty, fn($d)=>$d['status']==='在岗'); ?>
        <?php if ($on_duty): ?>
            <ul style="list-style:none;padding:0;margin:0;font-size:13px;line-height:2;">
                <?php foreach ($on_duty as $d): ?>
                    <li>🟢 <?php echo esc_html($d['name']); ?> · <?php echo esc_html($d['rank']); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="color:#999;">暂无在岗人员</p>
        <?php endif; ?>
    </div>
</div>
</div>
<?php get_footer(); ?>
