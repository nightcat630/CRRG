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
if ($cat_name) {
    $posts = get_posts([
        'post_type' => 'post', 'post_status' => 'publish',
        'meta_key' => 'crrg_report_type_name',
        'meta_value' => $cat_name,
        'posts_per_page' => 20,
    ]);
}
?>
<div class="gov-main">
    <div class="gov-content">
        <?php global $post; if ($post): ?>
            <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 8px;font-weight:bold;"><?php echo esc_html($post->post_title); ?></h1>
            <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">中央重生抵御小组 · 内部档案</div>
            <?php the_content(); ?>
        <?php endif; ?>

        <?php if ($posts): ?>
            <div style="margin-top:32px;border-top:1px solid #eee;padding-top:20px;">
                <h3 style="font-size:16px;color:#1B3A5C;margin-bottom:12px;">📋 归档报告 (<?php echo count($posts); ?>)</h3>
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
                            <div style="font-size:12px;color:#999;margin-top:4px;"><?php echo get_the_date('Y-m-d',$p); ?> · <?php echo $a?esc_html($a->display_name):'未知'; ?> · 💬 <?php echo $cc; ?> · <a href="#" class="fav-btn" data-post="<?php echo $p->ID; ?>" style="color:<?php echo (is_user_logged_in()&&crrg_is_favorited(get_current_user_id(),$p->ID))?'#e8b800':'#999'; ?>;text-decoration:none;font-size:12px;"><?php echo (is_user_logged_in()&&crrg_is_favorited(get_current_user_id(),$p->ID))?'⭐':'☆'; ?> 收藏</a></div>
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
