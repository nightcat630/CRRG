<?php
/* Template Name: 人员名录 */

get_header();

// 获取所有用户，按资历降序
$all_users = get_users(['orderby' => 'ID', 'order' => 'ASC']);
$user_data = [];
foreach ($all_users as $u) {
    $xp = crrg_get_xp($u->ID);
    $rank_id = crrg_get_rank($u->ID);
    $rank = crrg_get_rank_data($rank_id);
    $report_count = count_user_posts($u->ID, 'post');
    $joined = strtotime($u->user_registered);
    // 跳过零资历零报告用户
    if ($xp == 0 && $report_count == 0) continue;
    $user_data[] = [
        'id' => $u->ID,
        'name' => $u->display_name,
        'avatar' => get_avatar_url($u->ID, ['size' => 80]),
        'xp' => $xp,
        'rank_id' => $rank_id,
        'rank_name' => $rank['name'],
        'rank_icon' => $rank['icon'],
        'reports' => $report_count,
        'joined' => $joined,
    ];
}

// 按 XP 降序
usort($user_data, function($a, $b) { return $b['xp'] - $a['xp']; });

// 等级顺序用于分组
$rank_order = ['chairman' => 0, 'deputy' => 1, 'advisor' => 2, 'chief' => 3, 'tl' => 4, 'operative' => 5, 'observer' => 6];

// 按等级分组
$grouped = [];
foreach ($user_data as $u) {
    $rid = $u['rank_id'];
    if (!isset($grouped[$rid])) $grouped[$rid] = ['rank' => $u['rank_name'], 'icon' => $u['rank_icon'], 'users' => []];
    $grouped[$rid]['users'][] = $u;
}

// 按等级顺序排列
uksort($grouped, function($a, $b) use ($rank_order) {
    return ($rank_order[$a] ?? 99) - ($rank_order[$b] ?? 99);
});
?>

<div class="gov-main">
<div class="gov-content">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;border-bottom:1px solid #eee;padding-bottom:12px;">
    <div>
        <h1 style="font-size:22px;color:#1B3A5C;margin:0;font-weight:bold;">👥 人员名录</h1>
        <div style="color:#999;font-size:12px;">中央重生抵御小组 · 在编人员 <?php echo count($user_data); ?> 人</div>
    </div>
</div>

<?php foreach ($grouped as $rid => $group): ?>
<div style="margin-bottom:28px;">
    <h2 style="font-size:16px;color:#1B3A5C;margin:0 0 14px;padding-bottom:8px;border-bottom:2px solid #C41230;display:flex;align-items:center;gap:6px;">
        <span style="font-size:20px;"><?php echo $group['icon']; ?></span>
        <?php echo esc_html($group['rank']); ?>
        <span style="font-size:12px;color:#999;font-weight:normal;"><?php echo count($group['users']); ?>人</span>
    </h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
    <?php foreach ($group['users'] as $u): ?>
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:16px;display:flex;align-items:center;gap:14px;transition:box-shadow 0.2s;" onmouseover="this.style.boxShadow='0 2px 12px rgba(0,0,0,0.08)'" onmouseout="this.style.boxShadow='none'">
            <img src="<?php echo esc_url($u['avatar']); ?>" alt="" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid <?php echo $rid === 'chairman' ? '#C41230' : ($rid === 'deputy' ? '#F0A500' : '#e0e0e0'); ?>;flex-shrink:0;" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect fill=%22%23ddd%22 width=%22100%22 height=%22100%22/><text x=%2250%22 y=%2255%22 text-anchor=%22middle%22 fill=%22%23999%22 font-size=%2240%22>👤</text></svg>'">
            <div style="flex:1;min-width:0;">
                <div style="font-size:15px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><a href="/author/<?php echo get_the_author_meta('user_nicename', $u['id']); ?>/" style="color:#1e293b;text-decoration:none;"><?php echo esc_html($u['name']); ?></a></div>
                <div style="font-size:12px;color:#999;margin:3px 0;">
                    <span><?php echo $u['rank_icon']; ?> <?php echo $u['rank_name']; ?></span>
                    <span style="margin-left:8px;">· <?php echo $u['xp']; ?> 资历</span>
                </div>
                <div style="font-size:11px;color:#bbb;">
                    📄 <?php echo $u['reports']; ?> 份报告 · 🕐 <?php echo date('Y-m', $u['joined']); ?> 入职
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($user_data)): ?>
<div style="text-align:center;padding:60px 20px;color:#999;">
    <div style="font-size:48px;margin-bottom:12px;">👥</div>
    <p>暂无在编人员记录</p>
</div>
<?php endif; ?>

</div>
</div>

<?php get_footer(); ?>
