<?php
/* Template Name: 电子档案馆 */

get_header();

$sections = [
    'artifacts' => ['name' => '镇物', 'page' => '/artifacts/', 'icon' => '📦'],
    'events' => ['name' => '事件', 'page' => '/events/', 'icon' => '📋'],
    'personnel' => ['name' => '人物', 'page' => '/personnel/', 'icon' => '👤'],
    'organizations' => ['name' => '组织', 'page' => '/organizations/', 'icon' => '🏛️'],
    'research' => ['name' => '研究发现', 'page' => '/research/', 'icon' => '📄'],
    'entities' => ['name' => '祂们', 'page' => '/entities/', 'icon' => '⚡'],
    'esoterica' => ['name' => '秘术（仅登记）', 'page' => '/esoterica/', 'icon' => '🔮'],
    'outstanding' => ['name' => '优秀员工', 'page' => '/outstanding/', 'icon' => '🏅'],
];
?>
<div class="gov-main">
    <div class="gov-content">
        <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 8px;font-weight:bold;">📚 电子档案馆</h1>
        <div style="color:#999;font-size:12px;margin-bottom:24px;border-bottom:1px solid #eee;padding-bottom:12px;">中央重生抵御小组 · 档案索引</div>

        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">
        <?php foreach ($sections as $slug => $sec): 
            $posts = get_posts(['post_type'=>'post','post_status'=>'publish','meta_key'=>'crrg_report_type_name','meta_value'=>$sec['name'],'posts_per_page'=>3]);
        ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:18px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;padding-bottom:8px;border-bottom:2px solid #C41230;">
                    <h2 style="font-size:15px;color:#1B3A5C;margin:0;"><?php echo $sec['icon'].' '.$sec['name']; ?></h2>
                    <a href="<?php echo $sec['page']; ?>" style="font-size:11px;color:#C41230;text-decoration:none;white-space:nowrap;">更多 →</a>
                </div>
                <?php if ($posts): foreach ($posts as $p): $a=get_userdata($p->post_author); ?>
                    <div style="padding:6px 0;border-bottom:1px solid #f8f8f8;">
                        <a href="<?php echo get_permalink($p); ?>" style="font-size:13px;color:#333;text-decoration:none;"><?php echo esc_html($p->post_title); ?></a>
                        <div style="font-size:10px;color:#bbb;"><?php echo get_the_date('m-d',$p); ?> · <?php echo $a?esc_html($a->display_name):'未知'; ?></div>
                    </div>
                <?php endforeach; else: ?>
                    <p style="color:#ccc;font-size:12px;padding:8px 0;">暂无归档</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <div class="gov-sidebar">
        <div class="widget">
            <div class="widget-title"><a href="/reports/" style="color:inherit;text-decoration:none;">📝 报告</a></div>
            <ul style="list-style:none;padding:0;margin:0;font-size:13px;line-height:2;"><li><a href="/reports/" style="color:#333;">→ 提交新报告</a></li><li><a href="/reports/" style="color:#333;">→ 我的报告</a></li></ul>
        </div>
    </div>
</div>
<?php get_footer(); ?>
