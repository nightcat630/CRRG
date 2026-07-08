<?php
/* Template Name: 内页模板 */

get_header();

$cat_map = [
    'artifacts' => '镇物', 'events' => '事件', 'personnel' => '人物',
    'organizations' => '组织', 'research' => '研究发现', 'entities' => '祂们',
    'esoterica' => '秘术',
];
$slug = get_post_field('post_name', get_the_ID());
$cat_name = $cat_map[$slug] ?? '';

$posts = [];
$date_filter = '';
$year = $_GET['year'] ?? '';
$month = $_GET['month'] ?? '';

if ($cat_name) {
    $args = [
        'post_type' => 'post', 'post_status' => 'publish',
        'posts_per_page' => 50,
        'meta_query' => [
            ['key' => 'crrg_report_type_name', 'value' => $cat_name],
            crrg_get_access_meta_query(),
        ],
    ];
    // 日期筛选
    if ($year) {
        $args['date_query'] = [['year' => (int)$year]];
        if ($month) $args['date_query'][0]['month'] = (int)$month;
    }
    $posts = get_posts($args);
    if ($year) $date_filter = $year . '年' . ($month ? $month . '月' : '');
}
?>
<div class="gov-main">
    <div class="gov-content">
        <?php global $post; if ($post): ?>
            <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 8px;font-weight:bold;"><?php echo esc_html($post->post_title); ?></h1>
            <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">中央重生抵御小组 · 内部档案</div>

            <?php if ($slug === 'artifacts'): ?>
            <details style="background:#f8f9fa;border:1px solid #e0e0e0;border-radius:4px;padding:16px 20px;margin-bottom:20px;">
                <summary style="font-size:15px;font-weight:bold;color:#1B3A5C;cursor:pointer;user-select:none;">📐 镇物命名规范</summary>
                <div style="margin-top:12px;font-size:13px;color:#555;line-height:1.8;">
                    <p style="margin:0 0 8px;"><strong>格式：X-x-y-z</strong></p>
                    <p style="margin:0 0 4px;"><strong>X</strong> — 所属区域：<code>E</code> 东方 · <code>W</code> 西方 · <code>O</code> 其他</p>
                    <p style="margin:0 0 4px;"><strong>x</strong> — 神话体系编号</p>
                    <p style="margin:0 0 4px;"><strong>y</strong> — 所属项目编号</p>
                    <p style="margin:0 0 12px;"><strong>z</strong> — 个体编号</p>
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <thead><tr style="background:#1B3A5C;color:#fff;"><th style="padding:6px 10px;text-align:left;width:44px;">区域</th><th style="padding:6px 10px;text-align:left;width:40px;">编号</th><th style="padding:6px 10px;text-align:left;">神话体系</th><th style="padding:6px 10px;text-align:left;width:80px;">地区</th></tr></thead>
                        <tbody>
                            <tr><td style="padding:4px 10px;font-weight:bold;color:#C41230;text-align:center;" rowspan="16">E</td><td style="padding:4px 10px;">01</td><td style="padding:4px 10px;">中原河洛神系</td><td style="padding:4px 10px;color:#888;font-size:11px;" rowspan="11">中国地区</td></tr>
                            <tr><td style="padding:4px 10px;">02</td><td style="padding:4px 10px;">南方楚神系</td></tr>
                            <tr><td style="padding:4px 10px;">03</td><td style="padding:4px 10px;">西方昆仑神系</td></tr>
                            <tr><td style="padding:4px 10px;">04</td><td style="padding:4px 10px;">东方蓬莱神系</td></tr>
                            <tr><td style="padding:4px 10px;">05</td><td style="padding:4px 10px;">东方鸟夷系</td></tr>
                            <tr><td style="padding:4px 10px;">06</td><td style="padding:4px 10px;">苗蛮及百越系</td></tr>
                            <tr><td style="padding:4px 10px;">07</td><td style="padding:4px 10px;">道教神系</td></tr>
                            <tr><td style="padding:4px 10px;">08</td><td style="padding:4px 10px;">佛教神系</td></tr>
                            <tr><td style="padding:4px 10px;">09</td><td style="padding:4px 10px;">蒙古长生天神系</td></tr>
                            <tr><td style="padding:4px 10px;">10</td><td style="padding:4px 10px;">满族萨满神系</td></tr>
                            <tr><td style="padding:4px 10px;">11</td><td style="padding:4px 10px;">民间神系</td></tr>
                            <tr><td style="padding:4px 10px;">12</td><td style="padding:4px 10px;">记纪神话</td><td style="padding:4px 10px;color:#888;font-size:11px;" rowspan="3">日本地区</td></tr>
                            <tr><td style="padding:4px 10px;">13</td><td style="padding:4px 10px;">民俗神</td></tr>
                            <tr><td style="padding:4px 10px;">14</td><td style="padding:4px 10px;">人神</td></tr>
                            <tr><td style="padding:4px 10px;">15</td><td style="padding:4px 10px;">吠陀神话</td><td style="padding:4px 10px;color:#888;font-size:11px;" rowspan="2">印度神话</td></tr>
                            <tr><td style="padding:4px 10px;">16</td><td style="padding:4px 10px;">往世书神话</td></tr>
                            <tr style="background:#f5f5f5;"><td style="padding:4px 10px;font-weight:bold;color:#1B3A5C;text-align:center;" rowspan="11">W</td><td style="padding:4px 10px;">01</td><td style="padding:4px 10px;">希腊神话</td><td style="padding:4px 10px;color:#888;font-size:11px;" rowspan="11">西方神话</td></tr>
                            <tr><td style="padding:4px 10px;">02</td><td style="padding:4px 10px;">北欧神话</td></tr>
                            <tr><td style="padding:4px 10px;">03</td><td style="padding:4px 10px;">凯尔特神话</td></tr>
                            <tr><td style="padding:4px 10px;">04</td><td style="padding:4px 10px;">斯拉夫神话</td></tr>
                            <tr><td style="padding:4px 10px;">05</td><td style="padding:4px 10px;">芬兰神话</td></tr>
                            <tr><td style="padding:4px 10px;">06</td><td style="padding:4px 10px;">美索不达米亚神话</td></tr>
                            <tr><td style="padding:4px 10px;">07</td><td style="padding:4px 10px;">犹太教神话</td></tr>
                            <tr><td style="padding:4px 10px;">08</td><td style="padding:4px 10px;">基督教神话</td></tr>
                            <tr><td style="padding:4px 10px;">09</td><td style="padding:4px 10px;">伊斯兰教神话</td></tr>
                            <tr><td style="padding:4px 10px;">10</td><td style="padding:4px 10px;">阿尔冈昆神话</td></tr>
                            <tr><td style="padding:4px 10px;">11</td><td style="padding:4px 10px;">因纽特神话</td></tr>
                            <tr style="background:#f5f5f5;"><td style="padding:4px 10px;font-weight:bold;color:#C41230;text-align:center;" rowspan="8">O</td><td style="padding:4px 10px;">01</td><td style="padding:4px 10px;">古埃及神话</td><td style="padding:4px 10px;color:#888;font-size:11px;" rowspan="8">其他神话</td></tr>
                            <tr><td style="padding:4px 10px;">02</td><td style="padding:4px 10px;">两河流域神话</td></tr>
                            <tr><td style="padding:4px 10px;">03</td><td style="padding:4px 10px;">波斯神话</td></tr>
                            <tr><td style="padding:4px 10px;">04</td><td style="padding:4px 10px;">阿依努神话</td></tr>
                            <tr><td style="padding:4px 10px;">05</td><td style="padding:4px 10px;">菲律宾/印尼神话</td></tr>
                            <tr><td style="padding:4px 10px;">06</td><td style="padding:4px 10px;">冈瓦纳型神话</td></tr>
                            <tr><td style="padding:4px 10px;">07</td><td style="padding:4px 10px;">克苏鲁神话</td></tr>
                            <tr><td style="padding:4px 10px;">08</td><td style="padding:4px 10px;">都市传说</td></tr>
                        </tbody>
                    </table>
                    <p style="margin:10px 0 0;font-size:11px;color:#999;">示例：<code>E-01-001-003</code> = 东方 · 中原河洛 · 001号项目 · 003号个体</p>
                </div>
            </details>
            <?php endif; ?>

            <?php the_content(); ?>
        <?php endif; ?>

        <?php if ($posts): ?>
            <div style="margin-top:32px;border-top:1px solid #eee;padding-top:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h3 style="font-size:16px;color:#1B3A5C;margin:0;">📋 归档报告 (<?php echo count($posts); ?><?php echo $date_filter ? ' · ' . $date_filter : ''; ?>)</h3>
                    <form method="get" style="display:flex;gap:6px;align-items:center;">
                        <select name="year" style="padding:4px 8px;border:1px solid #d5d5d5;border-radius:3px;font-size:12px;background:#fff;">
                            <option value="">全部年份</option>
                            <?php for($y=2026;$y>=2024;$y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $year==$y?'selected':''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="month" style="padding:4px 8px;border:1px solid #d5d5d5;border-radius:3px;font-size:12px;background:#fff;">
                            <option value="">全部月份</option>
                            <?php for($m=1;$m<=12;$m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $month==$m?'selected':''; ?>><?php echo $m; ?>月</option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" style="background:#1B3A5C;color:#fff;border:none;padding:4px 12px;border-radius:3px;font-size:12px;cursor:pointer;">筛选</button>
                        <?php if($date_filter): ?>
                            <a href="?" style="font-size:11px;color:#C41230;text-decoration:none;">清除</a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php foreach ($posts as $p): $a=get_userdata($p->post_author); $cc=get_comments_number($p->ID); 
                    $thumb = '';
                    $img_match = preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/', $p->post_content, $m);
                    if ($img_match) $thumb = $m[1];
                ?>
                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:16px;margin-bottom:8px;display:flex;gap:16px;">
                        <?php if ($thumb): ?>
                            <img src="<?php echo esc_url($thumb); ?>" style="width:160px;height:100px;object-fit:cover;border-radius:3px;flex-shrink:0;" alt="">
                        <?php endif; ?>
                        <div style="flex:1;">
                            <a href="<?php echo get_permalink($p); ?>" style="font-size:15px;font-weight:bold;color:#1B3A5C;text-decoration:none;"><?php echo esc_html($p->post_title); ?></a>
                            <div style="font-size:12px;color:#999;margin-top:4px;"><?php echo get_the_date('Y-m-d',$p); ?> · <a href="/author/<?php echo $a ? $a->user_nicename : ''; ?>/" style="color:#1B3A5C;text-decoration:none;"><?php echo $a?esc_html($a->display_name):'未知'; ?></a> · 💬 <?php echo $cc; ?> · <a href="#" class="fav-btn" data-post="<?php echo $p->ID; ?>" style="color:<?php echo (is_user_logged_in()&&crrg_is_favorited(get_current_user_id(),$p->ID))?'#e8b800':'#999'; ?>;text-decoration:none;font-size:12px;"><?php echo (is_user_logged_in()&&crrg_is_favorited(get_current_user_id(),$p->ID))?'⭐':'☆'; ?> 收藏</a></div>
                            <div style="font-size:13px;color:#666;margin-top:6px;"><?php echo wp_trim_words(strip_tags($p->post_content),50); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="gov-sidebar">
        <div class="widget"><div class="widget-title"><a href="/reports/" style="color:inherit;text-decoration:none;">📝 报告</a></div>
            <ul style="list-style:none;padding:0;margin:0;font-size:13px;line-height:2;"><li><a href="/reports/" style="color:#333;">→ 提交新报告</a></li><li><a href="/reports/" style="color:#333;">→ 我的报告</a></li></ul>
        </div>
        <div class="widget"><div class="widget-title"><a href="/notices/" style="color:inherit;text-decoration:none;">📌 最新通知</a></div>
            <ul style="list-style:none;padding:0;margin:0;font-size:13px;line-height:2;">
                <?php $anns = crrg_get_announcements(); if ($anns): foreach (array_slice($anns,0,4) as $ann): ?>
                    <li>· <a href="/notices/" style="color:#333;text-decoration:none;"><?php echo esc_html($ann['title']); ?></a></li>
                <?php endforeach; else: ?><li>· 暂无通知</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php get_footer(); ?>
