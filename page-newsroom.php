<?php
/* Template Name: 新闻社 */

get_header();
$news = get_posts(['post_type'=>'post','post_status'=>'publish','meta_key'=>'crrg_is_news','meta_value'=>'1','posts_per_page'=>20]);
?>
<div class="gov-main">
    <div class="gov-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">
            <div>
                <h1 style="font-size:22px;color:#1B3A5C;margin:0;font-weight:bold;">📰 新闻归档</h1>
                <div style="color:#999;font-size:12px;">中央重生抵御小组 · 要闻列表</div>
            </div>
            <?php if (is_user_logged_in()): ?>
                <a href="/newsroom/submit/" style="background:#C41230;color:#fff;padding:8px 20px;border-radius:4px;text-decoration:none;font-size:14px;">📝 要闻投稿</a>
            <?php endif; ?>
        </div>
        <?php if ($news): foreach ($news as $p): $a=get_userdata($p->post_author); $thumb=''; preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/',$p->post_content,$m); if($m)$thumb=$m[1]; ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:16px;margin-bottom:12px;display:flex;gap:16px;">
                <?php if($thumb): ?><img src="<?php echo esc_url($thumb); ?>" style="width:180px;height:110px;object-fit:cover;border-radius:3px;flex-shrink:0;" alt=""><?php endif; ?>
                <div style="flex:1;">
                    <a href="<?php echo get_permalink($p); ?>" style="font-size:15px;font-weight:bold;color:#1B3A5C;text-decoration:none;"><?php echo esc_html($p->post_title); ?></a>
                    <div style="font-size:11px;color:#999;margin:4px 0;"><?php echo get_the_date('Y-m-d H:i',$p); ?> · <?php echo $a?esc_html($a->display_name):'未知'; ?></div>
                    <div style="font-size:13px;color:#666;margin-top:6px;"><?php echo wp_trim_words(strip_tags($p->post_content),40); ?></div>
                </div>
            </div>
        <?php endforeach; else: ?>
            <p style="color:#999;text-align:center;padding:60px;">暂无新闻</p>
        <?php endif; ?>
    </div>
</div>
<?php get_footer(); ?>
