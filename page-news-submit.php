<?php
/* Template Name: 新闻投稿 */

if (!is_user_logged_in()) { wp_redirect(wp_login_url()); exit; }
$user_id = get_current_user_id();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_news']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_news')) {
    $title = sanitize_text_field($_POST['news_title'] ?? '');
    $content = wp_kses_post($_POST['news_content'] ?? '');
    if ($title && $content) {
        $post_id = wp_insert_post(['post_title'=>$title,'post_content'=>$content,'post_status'=>'pending','post_type'=>'post','post_author'=>$user_id]);
        if ($post_id) {
            update_post_meta($post_id, 'crrg_is_news', '1');
            // 处理标签：逗号分隔
            $tag_input = sanitize_text_field($_POST['news_tags'] ?? '');
            if (!empty($tag_input)) {
                $tag_names = array_map('trim', explode(',', $tag_input));
                $tag_names = array_filter($tag_names);
                wp_set_post_tags($post_id, $tag_names, false);
            }
            $message = '新闻已提交审核！';
        }
    }
}

get_header();
?>
<div class="gov-main">
    <div class="gov-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">
            <div><h1 style="font-size:22px;color:#1B3A5C;margin:0;">📝 要闻投稿</h1><div style="color:#999;font-size:12px;">中央重生抵御小组 · 新闻社</div></div>
            <a href="/newsroom/" style="color:#1B3A5C;font-size:14px;text-decoration:none;">← 返回列表</a>
        </div>
        <?php if ($message): ?><div style="background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:12px 16px;border-radius:4px;margin-bottom:16px;"><?php echo esc_html($message); ?></div><?php endif; ?>
        <form method="post">
            <?php wp_nonce_field('crrg_news'); ?>
            <div style="margin-bottom:16px;"><label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">新闻标题</label><input type="text" name="news_title" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;" placeholder="输入新闻标题..."></div>
            <div style="margin-bottom:16px;"><label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">标签 <span style="font-weight:normal;color:#999;font-size:12px;">（逗号分隔，如：调查报告,始源实体,重生工程）</span></label><input type="text" name="news_tags" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;" placeholder="输入标签，逗号分隔..."></div>
            <div style="margin-bottom:16px;"><label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">新闻内容</label><?php wp_editor('', 'news_content', ['textarea_name'=>'news_content','textarea_rows'=>12,'media_buttons'=>true,'teeny'=>false]); ?></div>
            <button type="submit" name="submit_news" value="1" style="background:#C41230;color:#fff;border:none;padding:10px 32px;border-radius:4px;font-size:15px;cursor:pointer;font-weight:bold;">提交新闻</button>
        </form>
    </div>
</div>
<?php get_footer(); ?>
