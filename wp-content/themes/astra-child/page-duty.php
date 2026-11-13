<?php
/* Template Name: 值班表 */
get_header();

$schedule = crrg_get_schedule();
$today = date('D');
$day_names = ['Mon'=>'周一','Tue'=>'周二','Wed'=>'周三','Thu'=>'周四','Fri'=>'周五','Sat'=>'周六','Sun'=>'周日'];
$today_duty = $schedule['schedule'][$today] ?? [];
?>
<div class="gov-main">
<div class="gov-content">
    <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;"> 值班表</h1>
    <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">
        中央重生抵御小组 · <?php echo $schedule['week_start']; ?> 起一周 · <?php echo date('n月j日'); ?> <?php echo $day_names[$today]; ?>
    </div>

    <!-- 今日值班 -->
    <div style="background:#f0f5fa;border:1px solid #d1d5db;border-radius:4px;padding:16px 20px;margin-bottom:24px;">
        <h3 style="margin:0 0 12px;font-size:15px;color:#1B3A5C;"> 今日值班（<?php echo $day_names[$today]; ?>）</h3>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <?php foreach (CRRG_DUTY_ROLES as $rid => $role):
                $person = $today_duty[$rid] ?? '—';
                $on = ($person !== '—');
            ?>
                <div style="flex:1;min-width:140px;background:<?php echo $on?'#fff':'#f8f8f8'; ?>;border:1px solid <?php echo $on?'#86efac':'#e0e0e0'; ?>;border-radius:4px;padding:12px;text-align:center;">
                    <div style="font-size:24px;"><?php echo $role['icon']; ?></div>
                    <div style="font-size:12px;color:#666;margin:2px 0;"><?php echo $role['name']; ?></div>
                    <div style="font-size:14px;font-weight:600;color:<?php echo $on?'#1B3A5C':'#999'; ?>;"><?php echo esc_html($person); ?></div>
                    <div style="font-size:10px;color:<?php echo $on?'#16a34a':'#999'; ?>;"><?php echo $on?'🟢 在岗':'—'; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 整周安排 -->
    <h3 style="font-size:15px;color:#1B3A5C;margin-bottom:12px;"> 本周安排</h3>
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:600px;">
        <thead>
            <tr style="background:#1B3A5C;color:#fff;">
                <th style="padding:8px 10px;text-align:left;">岗位</th>
                <?php foreach ($day_names as $dk => $dv): ?>
                    <th style="padding:8px 10px;text-align:center;<?php echo $dk===$today?'background:#C41230;':''; ?>"><?php echo $dv; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach (CRRG_DUTY_ROLES as $rid => $role): ?>
                <tr style="border-bottom:1px solid #e0e0e0;">
                    <td style="padding:8px 10px;font-weight:600;"><?php echo $role['icon']; ?> <?php echo $role['name']; ?></td>
                    <?php foreach ($day_names as $dk => $dv): ?>
                        <?php $p = $schedule['schedule'][$dk][$rid] ?? '—'; ?>
                        <td style="padding:8px 10px;text-align:center;<?php echo $dk===$today?'background:#fef2f2;font-weight:600;':''; ?>">
                            <?php echo esc_html($p); ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p style="margin-top:16px;font-size:12px;color:#999;">⚠ 排班每周一0点自动更新。如有临时调整，请联系委员长。</p>
    <p style="font-size:12px;color:#666;background:#fefce8;border:1px solid #fde68a;padding:10px 14px;border-radius:4px;"> <strong>值班激励：</strong>当日值班人员提交报告审批通过后，额外获得 <strong>+8 资历</strong>（报告）或 <strong>+5 资历</strong>（新闻）的加成。</p>
</div>
<div class="gov-sidebar">
    <div class="widget">
        <div class="widget-title"> 今日值班</div>
        <ul style="list-style:none;padding:0;margin:0;font-size:13px;line-height:2.2;">
            <?php foreach ($today_duty as $rid => $person):
                $role = CRRG_DUTY_ROLES[$rid];
            ?>
                <li><?php echo $role['icon']; ?> <?php echo $role['name']; ?>：<strong><?php echo esc_html($person); ?></strong></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
</div>
<?php get_footer(); ?>
