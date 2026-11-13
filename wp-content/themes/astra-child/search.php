<?php get_header(); ?>
<main id="main" class="site-main" style="max-width:860px;margin:0 auto;padding:20px;">
    <?php $current_type = $_GET['type'] ?? 'all'; $search_term = get_search_query(); ?>
    <h1 style="font-size:22px;color:#1B3A5C;margin-bottom:8px;">搜索结果：<?php echo esc_html($search_term); ?></h1>
    <div class="search-tabs" style="display:flex;gap:0;margin:16px 0 24px;border-bottom:2px solid #e0e0e0;">
        <?php
        $tabs = [
            'all'   => '全部',
            'post'  => '文章',
            'topic' => '论坛话题',
        ];
        foreach ($tabs as $k => $label):
            $active = ($current_type === $k);
            $url = add_query_arg(['s' => $search_term, 'type' => $k], home_url('/'));
        ?>
        <a href="<?php echo esc_url($url); ?>" style="padding:10px 24px;text-decoration:none;font-size:15px;color:<?php echo $active ? '#1B3A5C' : '#666'; ?>;border-bottom:<?php echo $active ? '3px solid #1B3A5C' : '3px solid transparent'; ?>;margin-bottom:-2px;transition:all 0.2s;"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </div>
    <?php if (have_posts()): ?>
        <ul style="list-style:none;padding:0;">
        <?php while (have_posts()): the_post(); ?>
            <li style="padding:14px 0;border-bottom:1px solid #eee;">
                <a href="<?php the_permalink(); ?>" style="font-size:16px;color:#1B3A5C;text-decoration:none;font-weight:600;"><?php the_title(); ?></a>
                <div style="font-size:13px;color:#999;margin-top:4px;">
                    <?php echo get_post_type() === 'topic' ? '论坛话题' : '文章'; ?> · <?php the_time('Y-m-d'); ?>
                </div>
            </li>
        <?php endwhile; ?>
        </ul>
        <?php the_posts_pagination(['mid_size'=>2,'prev_text'=>'←','next_text'=>'→']); ?>
    <?php else: ?>
        <p style="color:#999;font-size:15px;">没有找到相关内容。</p>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
