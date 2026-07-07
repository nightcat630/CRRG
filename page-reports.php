<?php
/* Template Name: 报告管理 */

if (!is_user_logged_in()) { wp_redirect(wp_login_url()); exit; }

$user_id = get_current_user_id();
$message = '';
$error = '';

// Handle new report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_report')) {
    $title = sanitize_text_field($_POST['report_title'] ?? '');
    $content = wp_kses_post($_POST['report_content'] ?? '');
    $category = sanitize_text_field($_POST['report_category'] ?? '');
    $status = $_POST['save_as'] === 'draft' ? 'draft' : 'pending';
    
    if (empty($title)) {
        $error = '请输入标题';
    } elseif (empty($content)) {
        $error = '请输入内容';
    } else {
        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
            'post_type' => 'post',
            'post_author' => $user_id,
        ]);
        
        if ($post_id && !is_wp_error($post_id)) {
            // Store report type as post meta
            $cat_map = ['artifacts'=>'镇物','events'=>'事件','personnel'=>'人物','organizations'=>'组织','research'=>'研究发现','entities'=>'祂们','esoterica'=>'秘术','other'=>'其他'];
            $cat_name = $cat_map[$category] ?? '其他';
            update_post_meta($post_id, 'crrg_report_type', $category);
            update_post_meta($post_id, 'crrg_report_type_name', $cat_name);
            
            // Add 资历 (only for submitted, not drafts)
            // 资历 on approval only
            
            $message = $status === 'pending' ? '报告已提交审核！待审批后发布 +15 资历' : '草稿已保存';
        } else {
            $error = '发布失败，请重试';
        }
    }
}

// Handle delete draft
if (isset($_GET['delete']) && wp_verify_nonce($_GET['_nonce'] ?? '', 'crrg_delete_report')) {
    $post_id = (int)$_GET['delete'];
    $post = get_post($post_id);
    if ($post && (int)$post->post_author === $user_id && $post->post_status === 'draft') {
        wp_delete_post($post_id, true);
        $message = '草稿已删除';
    }
}

// Get user's reports
$my_drafts = get_posts([
    'post_type' => 'post', 'post_status' => 'draft',
    'author' => $user_id, 'posts_per_page' => 10,
]);
$my_published = get_posts([
    'post_type' => 'post', 'post_status' => 'publish',
    'author' => $user_id, 'posts_per_page' => 10,
]);

// Editing a draft?
$editing_draft = null;
// Edit post request
$edit_post = null;
if (isset($_GET["edit_post"])) {
    $edit_post = get_post((int)$_GET["edit_post"]);
    if (!$edit_post || (int)$edit_post->post_author !== $user_id) $edit_post = null;
}

if (isset($_GET['edit_draft'])) {
    $editing_draft = get_post((int)$_GET['edit_draft']);
    if (!$editing_draft || (int)$editing_draft->post_author !== $user_id || $editing_draft->post_status !== 'draft') {
        $editing_draft = null;
    }
}

// Handle editing draft submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_draft']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_edit_draft')) {
    $draft_id = (int)$_POST['draft_id'];
    $draft = get_post($draft_id);
    if ($draft && (int)$draft->post_author === $user_id && $draft->post_status === 'draft') {
        $new_status = $_POST['save_as'] === 'draft' ? 'draft' : 'pending';
        wp_update_post([
            'ID' => $draft_id,
            'post_title' => sanitize_text_field($_POST['report_title']),
            'post_content' => wp_kses_post($_POST['report_content']),
            'post_status' => $new_status,
        ]);
        $category = sanitize_text_field($_POST['report_category'] ?? 'other');
        $cat_map = ['artifacts'=>'镇物','events'=>'事件','personnel'=>'人物','organizations'=>'组织','research'=>'研究发现','entities'=>'祂们','esoterica'=>'秘术','other'=>'其他'];
        update_post_meta($draft_id, 'crrg_report_type', $category);
        update_post_meta($draft_id, 'crrg_report_type_name', $cat_map[$category] ?? '其他');
        
        // 资历 on approval only
        $message = $new_status === 'pending' ? '报告已提交审核！' : '草稿已更新';
        $editing_draft = null;
        $show_form = false;
    }
}

// Show form?
$show_form = isset($_GET['new']) || $edit_post || $editing_draft || (empty($my_drafts) && empty($my_published));

// Handle delete request
if (isset($_GET['req_delete']) && wp_verify_nonce($_GET['_nonce'] ?? '', 'crrg_req_delete')) {
    $post_id = (int)$_GET['req_delete'];
    $post = get_post($post_id);
    if ($post && (int)$post->post_author === $user_id) {
        update_post_meta($post_id, 'crrg_delete_request', $user_id);
        update_post_meta($post_id, 'crrg_delete_request_time', current_time('mysql'));
        $message = '删除申请已提交，等待管理员审核。';
    }
}

// Handle edit request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_edit']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_edit_req')) {
    $post_id = (int)$_POST['edit_post_id'];
    $new_title = sanitize_text_field($_POST['edit_title'] ?? '');
    $new_content = wp_kses_post($_POST['edit_content'] ?? '');
    $post = get_post($post_id);
    if ($post && (int)$post->post_author === $user_id) {
        update_post_meta($post_id, 'crrg_edit_request', $user_id);
        update_post_meta($post_id, 'crrg_edit_title', $new_title);
        update_post_meta($post_id, 'crrg_edit_content', $new_content);
        update_post_meta($post_id, 'crrg_edit_time', current_time('mysql'));
        $message = '修改申请已提交，等待管理员审核。';
    }
}

get_header();
?>
<div class="gov-main">
    <div class="gov-content">
        <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;">📝 报告管理</h1>
        <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">
            中央重生抵御小组 · 档案提交系统
                <?php if ($edit_post): ?>
                <a href="?" style="float:right;color:#1B3A5C;font-size:12px;">← 返回列表</a>
            <?php elseif (!$show_form): ?>
                <a href="?new=1" style="float:right;background:#C41230;color:#fff;padding:4px 14px;border-radius:3px;font-size:12px;text-decoration:none;">+ 新建报告</a>
            <?php else: ?>
                <a href="?" style="float:right;color:#1B3A5C;font-size:12px;">← 返回列表</a>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div style="background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:12px 16px;border-radius:4px;margin-bottom:16px;"><?php echo esc_html($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:12px 16px;border-radius:4px;margin-bottom:16px;"><?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <?php if ($editing_draft): ?>
            <!-- Edit Draft Form -->
            <form method="post" style="margin-bottom:40px;">
                <?php wp_nonce_field('crrg_edit_draft'); ?>
                <input type="hidden" name="draft_id" value="<?php echo $editing_draft->ID; ?>">
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">报告标题</label>
                    <input type="text" name="report_title" value="<?php echo esc_attr($editing_draft->post_title); ?>" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">档案类型</label>
                    <select name="report_category" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;background:#fff;">
                        <?php $types=['artifacts'=>'镇物','events'=>'事件','personnel'=>'人物','organizations'=>'组织','research'=>'研究发现','entities'=>'祂们','esoterica'=>'秘术','other'=>'其他']; $cur = get_post_meta($editing_draft->ID,'crrg_report_type',true) ?: 'other'; foreach($types as $k=>$v) echo '<option value=\"'.$k.'\"'.($k===$cur?' selected':'').'>'.$v.'</option>'; ?>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">报告内容</label>
                    <?php wp_editor($editing_draft->post_content, 'report_content', ['textarea_name'=>'report_content','textarea_rows'=>12,'media_buttons'=>true,'teeny'=>false]); ?>
                </div>
                <div style="display:flex;gap:12px;">
                    <button type="submit" name="update_draft" value="1" style="background:#C41230;color:#fff;border:none;padding:10px 32px;border-radius:4px;font-size:15px;cursor:pointer;font-weight:bold;">发布报告</button>
                    <button type="submit" name="update_draft" value="1" onclick="var f=this.form;var i=document.createElement('input');i.type='hidden';i.name='save_as';i.value='draft';f.appendChild(i);" style="background:#f0f0f0;color:#555;border:1px solid #d5d5d5;padding:10px 24px;border-radius:4px;font-size:14px;cursor:pointer;">保存草稿</button>
                    <a href="?" style="background:#f0f0f0;color:#555;border:1px solid #d5d5d5;padding:10px 24px;border-radius:4px;font-size:14px;text-decoration:none;">取消</a>
                </div>
            </form>
        <?php elseif ($edit_post): ?>
            <!-- Edit Request Form -->
            <form method="post" style="margin-bottom:40px;">
                <?php wp_nonce_field('crrg_edit_req'); ?>
                <input type="hidden" name="edit_post_id" value="<?php echo $edit_post->ID; ?>">
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">修改后标题</label>
                    <input type="text" name="edit_title" value="<?php echo esc_attr($edit_post->post_title); ?>" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">修改后内容</label>
                    <?php wp_editor($edit_post->post_content, 'edit_content', ['textarea_name'=>'edit_content','textarea_rows'=>12,'media_buttons'=>true,'teeny'=>false]); ?>
                </div>
                <div style="display:flex;gap:12px;">
                    <button type="submit" name="submit_edit" value="1" style="background:#C41230;color:#fff;border:none;padding:10px 32px;border-radius:4px;font-size:15px;cursor:pointer;">提交修改申请</button>
                    <a href="?" style="background:#f0f0f0;color:#555;border:1px solid #d5d5d5;padding:10px 24px;border-radius:4px;font-size:14px;text-decoration:none;">取消</a>
                </div>
            </form>
        <?php elseif ($show_form): ?>
            <!-- New Report Form -->
            <form method="post" style="margin-bottom:40px;">
                <?php wp_nonce_field('crrg_report'); ?>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">报告标题</label>
                    <input type="text" name="report_title" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;" placeholder="输入报告标题...">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">档案类型</label>
                    <select name="report_category" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;background:#fff;">
                        <option value="artifacts">镇物</option><option value="events">事件</option><option value="personnel">人物</option><option value="organizations">组织</option><option value="research">研究发现</option><option value="entities">祂们</option><option value="esoterica">秘术</option><option value="other">其他</option>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">报告内容</label>
                    <?php wp_editor('', 'report_content', ['textarea_name'=>'report_content','textarea_rows'=>12,'media_buttons'=>true,'teeny'=>false]); ?>
                </div>
                <div style="display:flex;gap:12px;">
                    <button type="submit" name="submit_report" value="1" style="background:#C41230;color:#fff;border:none;padding:10px 32px;border-radius:4px;font-size:15px;cursor:pointer;font-weight:bold;">发布报告</button>
                    <button type="submit" name="submit_report" value="1" onclick="var f=this.form;var i=document.createElement('input');i.type='hidden';i.name='save_as';i.value='draft';f.appendChild(i);" style="background:#f0f0f0;color:#555;border:1px solid #d5d5d5;padding:10px 24px;border-radius:4px;font-size:14px;cursor:pointer;">保存草稿</button>
                </div>
            </form>
        <?php else: ?>
            <!-- Drafts -->
            <?php if ($my_drafts): ?>
                <h3 style="font-size:16px;color:#1B3A5C;margin:20px 0 12px;">📄 草稿</h3>
                <?php foreach ($my_drafts as $draft): ?>
                    <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:4px;padding:12px 16px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
                        <div><strong style="color:#333;"><?php echo esc_html($draft->post_title ?: '无标题'); ?></strong><span style="color:#999;font-size:12px;margin-left:8px;"><?php echo get_the_date('m-d H:i', $draft); ?></span></div>
                        <div><a href="?edit_draft=<?php echo $draft->ID; ?>" style="color:#1B3A5C;font-size:12px;margin-right:12px;">编辑</a><a href="?delete=<?php echo $draft->ID; ?>&_nonce=<?php echo wp_create_nonce('crrg_delete_report'); ?>" style="color:#c00;font-size:12px;" onclick="return confirm('确定删除？')">删除</a></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Published -->
            <?php if ($my_published): ?>
                <h3 style="font-size:16px;color:#1B3A5C;margin:24px 0 12px;">✅ 已发布</h3>
                <?php foreach ($my_published as $post): $cats = get_the_category($post->ID); $comments_count = get_comments_number($post->ID); setup_postdata($post); ?>
                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:16px;margin-bottom:8px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                            <div style="flex:1;">
                                <a href="<?php the_permalink(); ?>" style="font-size:15px;font-weight:bold;color:#1B3A5C;text-decoration:none;"><?php the_title(); ?></a>
                                <div style="margin-top:6px;font-size:12px;color:#999;"><?php if ($cats): ?><span style="background:#f0f0f0;padding:2px 8px;border-radius:2px;margin-right:6px;"><?php echo $cats[0]->name; ?></span><?php endif; ?><?php echo get_the_date('Y-m-d'); ?> · 💬 <?php echo $comments_count; ?> 评论</div>
                            </div>
                            <div style="display:flex;gap:6px;flex-shrink:0;">
                                <a href="?edit_post=<?php echo $post->ID; ?>" style="font-size:11px;color:#1B3A5C;text-decoration:none;padding:4px 8px;border:1px solid #ddd;border-radius:3px;">申请修改</a>
                                <a href="?req_delete=<?php echo $post->ID; ?>&_nonce=<?php echo wp_create_nonce('crrg_req_delete'); ?>" style="font-size:11px;color:#c00;text-decoration:none;padding:4px 8px;border:1px solid #fcc;border-radius:3px;" onclick="return confirm('确定申请删除？')">申请删除</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; wp_reset_postdata(); ?>
            <?php endif; ?>

            <?php if (empty($my_drafts) && empty($my_published)): ?>
                <div style="text-align:center;padding:40px;color:#999;"><div style="font-size:48px;margin-bottom:12px;">📝</div><p>还没有报告，点击上方按钮创建第一篇</p></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="gov-sidebar" style="width:320px;">
        <?php $notifs = get_user_meta($user_id, 'crrg_notifications', true) ?: []; $favs = get_user_meta($user_id, 'crrg_favorites', true) ?: []; ?>
        <!-- Notifications -->
        <div class="widget">
            <div class="widget-title">🔔 消息 (<?php echo count($notifs); ?>)</div>
            <?php if ($notifs): ?>
                <div style="max-height:300px;overflow-y:auto;font-size:12px;">
                <?php foreach (array_slice($notifs, 0, 10) as $n): ?>
                    <div style="padding:8px 0;border-bottom:1px solid #f0f0f0;">
                        <a href="<?php echo get_permalink($n['post_id']); ?>" style="color:#333;text-decoration:none;">
                            <strong><?php echo $n['type']==='comment'?'💬':'⭐'; ?> <?php echo esc_html($n['commenter']); ?></strong>
                            <span style="color:#999;"><?php echo $n['content']; ?></span>
                            <div style="color:#bbb;font-size:10px;"><?php echo $n['post_title']; ?></div>
                        </a>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color:#999;font-size:12px;">暂无消息</p>
            <?php endif; ?>
        </div>
        <!-- Favorites -->
        <div class="widget">
            <div class="widget-title">⭐ 收藏 (<?php echo count($favs); ?>)</div>
            <?php if ($favs): $fav_posts = get_posts(['post__in'=>$favs,'post_type'=>'post','post_status'=>'publish','posts_per_page'=>10]); ?>
                <?php foreach ($fav_posts as $fp): 
                    $fp_favs = get_post_meta($fp->ID, 'crrg_favorited_by', true) ?: [];
                    $fp_count = count($fp_favs);
                ?>
                    <div style="font-size:12px;padding:6px 0;border-bottom:1px solid #f0f0f0;">
                        <a href="<?php echo get_permalink($fp); ?>" style="color:#333;text-decoration:none;"><?php echo esc_html($fp->post_title); ?></a>
                        <div style="color:#999;font-size:11px;">共 <?php echo $fp_count; ?> 人收藏 · <a href="<?php echo get_permalink($fp); ?>" style="color:#1B3A5C;">查看详情</a></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:#999;font-size:12px;">暂无收藏</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php get_footer(); ?>
