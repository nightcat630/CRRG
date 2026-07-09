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
    $status = ($_POST['save_as'] ?? '') === 'draft' ? 'draft' : 'pending';
    
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
            
            // 访问等级（不超过作者等级）
            $access = sanitize_text_field($_POST['access_level'] ?? 'observer');
            $my_rank = crrg_get_rank($user_id);
            $allowed = array_column(crrg_get_accessible_ranks($my_rank), 'id');
            if (!in_array($access, $allowed)) $access = 'observer';
            update_post_meta($post_id, 'crrg_access_level', $access);
            
            // 威胁等级
            $threat = sanitize_text_field($_POST['threat_level'] ?? '');
            if ($threat) update_post_meta($post_id, 'crrg_threat_level', $threat); else delete_post_meta($post_id, 'crrg_threat_level');
            
            // 事件时间
            $event_date = sanitize_text_field($_POST['event_date'] ?? '');
            if ($event_date) update_post_meta($post_id, 'crrg_event_date', $event_date);
            // 事件起止时间
            $event_start = sanitize_text_field($_POST['event_start'] ?? '');
            $event_end = sanitize_text_field($_POST['event_end'] ?? '');
            if ($event_start) update_post_meta($post_id, 'crrg_event_start', $event_start);
            if ($event_end) update_post_meta($post_id, 'crrg_event_end', $event_end);
            
            // 事件地点
            $loc_parts = array_filter([
                sanitize_text_field($_POST['addr_country'] ?? ''),
                sanitize_text_field($_POST['addr_province'] ?? ''),
                sanitize_text_field($_POST['addr_city'] ?? ''),
                sanitize_text_field($_POST['addr_county'] ?? ''),
            ]);
            $loc_manual = sanitize_text_field($_POST['report_location'] ?? '');
            $location = $loc_manual ?: implode(' ', $loc_parts);
            if ($location && ($_POST['addr_country'] ?? '') !== '__other__') update_post_meta($post_id, 'crrg_location', $location);
            // 坐标
            $lat = $_POST['report_lat'] ?? '';
            $lng = $_POST['report_lng'] ?? '';
            if ($lat && $lng) { update_post_meta($post_id, 'crrg_lat', $lat); update_post_meta($post_id, 'crrg_lng', $lng); }
            
            // 处理标签
            $tag_input = sanitize_text_field($_POST['report_tags'] ?? '');
            if (!empty($tag_input)) {
                $tag_names = array_map('trim', explode(',', $tag_input));
                $tag_names = array_filter($tag_names);
                wp_set_post_tags($post_id, $tag_names, false);
            }
            
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
        $new_status = ($_POST['save_as'] ?? '') === 'draft' ? 'draft' : 'pending';
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
        
        // 访问等级
        $access = sanitize_text_field($_POST['access_level'] ?? 'observer');
        $my_rank = crrg_get_rank($user_id);
        $allowed = array_column(crrg_get_accessible_ranks($my_rank), 'id');
        if (!in_array($access, $allowed)) $access = 'observer';
        update_post_meta($draft_id, 'crrg_access_level', $access);
        
        // 威胁等级
        $threat = sanitize_text_field($_POST['threat_level'] ?? '');
        if ($threat) update_post_meta($draft_id, 'crrg_threat_level', $threat); else delete_post_meta($draft_id, 'crrg_threat_level');
        
        // 事件地点
        $location = sanitize_text_field($_POST['report_location'] ?? '');
        if ($location) update_post_meta($draft_id, 'crrg_location', $location); else delete_post_meta($draft_id, 'crrg_location');
        
        // 处理标签
        $tag_input = sanitize_text_field($_POST['report_tags'] ?? '');
        if (!empty($tag_input)) {
            $tag_names = array_map('trim', explode(',', $tag_input));
            wp_set_post_tags($draft_id, array_filter($tag_names), false);
        }
        
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
        // 标签修改
        $edit_tags = sanitize_text_field($_POST['edit_tags'] ?? '');
        update_post_meta($post_id, 'crrg_edit_tags', $edit_tags);
        // 访问等级
        $edit_access = sanitize_text_field($_POST['edit_access'] ?? '');
        if ($edit_access) update_post_meta($post_id, 'crrg_edit_access', $edit_access);
        // 威胁等级 + 地点
        $edit_threat = sanitize_text_field($_POST['edit_threat'] ?? '');
        if ($edit_threat !== '') update_post_meta($post_id, 'crrg_edit_threat', $edit_threat);
        $edit_location = sanitize_text_field($_POST['edit_location'] ?? '');
        if ($edit_location !== '') update_post_meta($post_id, 'crrg_edit_location', $edit_location);
        $edit_lat = $_POST['edit_lat'] ?? '';
        $edit_lng = $_POST['edit_lng'] ?? '';
        if ($edit_lat && $edit_lng) { update_post_meta($post_id, 'crrg_edit_lat', $edit_lat); update_post_meta($post_id, 'crrg_edit_lng', $edit_lng); }
        $message = '修改申请已提交，等待管理员审核。';
        wp_redirect(add_query_arg('msg', urlencode($message), remove_query_arg(['edit_post','msg'])));
        exit;
    }
}

// POST 后统一重定向
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($message)) {
    wp_redirect(add_query_arg('msg', urlencode($message), remove_query_arg(['new','edit_draft','edit_post','msg'])));
    exit;
}
$get_msg = sanitize_text_field($_GET['msg'] ?? '');
if ($get_msg) $message = $get_msg;

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
                        <?php $types=['artifacts'=>'镇物','events'=>'事件','personnel'=>'人物','organizations'=>'组织','research'=>'研究发现','entities'=>'祂们','esoterica'=>'秘术','outstanding'=>'优秀员工','other'=>'其他']; $cur = get_post_meta($editing_draft->ID,'crrg_report_type',true) ?: 'other'; foreach($types as $k=>$v) echo '<option value=\"'.$k.'\"'.($k===$cur?' selected':'').'>'.$v.'</option>'; ?>
                    </select>
                    <div id="artifact-naming-hint-edit" style="<?php echo $cur==='artifacts'?'':'display:none;'; ?>margin-top:10px;padding:12px 14px;background:#f8f9fa;border:1px solid #e0e0e0;border-radius:4px;font-size:12px;color:#555;line-height:1.7;">
                        <strong style="color:#1B3A5C;">📐 镇物命名：X-x-y-z</strong><br>
                        <span>X: <code>E</code>东方 <code>W</code>西方 <code>O</code>其他</span><br>
                        <span>x: 神话体系编号（E01~16 / W01~11 / O01~08）</span><br>
                        <span>y: 项目编号 · z: 个体编号</span><br>
                        <span style="color:#999;">示例：<code>E-01-001-003</code></span><br>
                        <a href="/artifacts/" target="_blank" style="font-size:11px;color:#1B3A5C;">→ 查看完整编号表</a>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;"><span class="time-label-text">报告时间</span></label>
                    <span id="event_time_single">
                        <input type="datetime-local" name="event_date" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;">
                        <span style="font-size:11px;color:#999;">对外显示的发布时间，留空则使用实际提交时间</span>
                    </span>
                    <span id="event_time_range" style="display:none;">
                        <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;">
                            <span style="font-size:12px;color:#666;white-space:nowrap;">起始：</span>
                            <input type="datetime-local" name="event_start" style="flex:1;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;">
                            <span style="font-size:12px;color:#666;white-space:nowrap;">结束：</span>
                            <input type="datetime-local" name="event_end" style="flex:1;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;">
                        </div>
                        <span style="font-size:11px;color:#999;">起始留空=持续至终点；终点留空=起始后持续至今；均留空=常驻事件</span>
                    </span>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">访问等级 <span style="font-weight:normal;color:#999;font-size:12px;">（不超过自身等级）</span></label>
                    <select name="access_level" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;background:#fff;">
                        <?php $cur_access = get_post_meta($editing_draft->ID, 'crrg_access_level', true) ?: 'observer'; $my_rank2 = crrg_get_rank($user_id); foreach (crrg_get_accessible_ranks($my_rank2) as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo $r['id']===$cur_access?'selected':''; ?>><?php echo $r['icon']; ?> <?php echo $r['name']; ?> 及以上可阅</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">威胁等级</label>
                    <select name="threat_level" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;background:#fff;">
                        <?php $cur_threat = get_post_meta($editing_draft->ID, 'crrg_threat_level', true) ?: ''; ?>
                        <option value="">未评级</option>
                        <option value="ren" <?php echo $cur_threat==='ren'?'selected':''; ?>>👤 人 — 对人类产生影响</option>
                        <option value="gui" <?php echo $cur_threat==='gui'?'selected':''; ?>>👻 鬼 — 对神秘生物/古神眷属产生影响</option>
                        <option value="mo" <?php echo $cur_threat==='mo'?'selected':''; ?>>👿 魔 — 对次级旧日支配者/旧日支配者/古神产生影响</option>
                        <option value="shen" <?php echo $cur_threat==='shen'?'selected':''; ?>>👼 神 — 对旧神产生影响</option>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">事件地点</label>
                    <input type="text" name="report_location" value="<?php echo esc_attr(get_post_meta($editing_draft->ID, 'crrg_location', true)); ?>" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;" placeholder="如：广西横州市云表镇">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">标签 <span style="font-weight:normal;color:#999;font-size:12px;">（逗号分隔）</span></label>
                    <input type="text" name="report_tags" value="<?php $tags = wp_get_post_tags($editing_draft->ID, ['fields'=>'names']); echo esc_attr(implode(',', $tags)); ?>" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;" placeholder="调查报告,始源实体,重生工程">
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
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">修改标签 <span style="font-weight:normal;color:#999;font-size:12px;">（逗号分隔）</span></label>
                    <input type="text" name="edit_tags" value="<?php $etags = wp_get_post_tags($edit_post->ID, ['fields'=>'names']); echo esc_attr(implode(',', $etags)); ?>" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;" placeholder="调查报告,始源实体">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">修改访问等级 <span style="font-weight:normal;color:#999;font-size:12px;">（不超过自身等级）</span></label>
                    <select name="edit_access" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;background:#fff;">
                        <?php $cur_acc = get_post_meta($edit_post->ID, 'crrg_access_level', true) ?: 'observer'; $my_rk = crrg_get_rank($user_id); foreach (crrg_get_accessible_ranks($my_rk) as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo $r['id']===$cur_acc?'selected':''; ?>><?php echo $r['icon']; ?> <?php echo $r['name']; ?> 及以上可阅</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">修改威胁等级</label>
                    <select name="edit_threat" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;background:#fff;">
                        <?php $cur_th2 = get_post_meta($edit_post->ID, 'crrg_threat_level', true) ?: ''; ?>
                        <option value="">未评级</option>
                        <option value="ren" <?php echo $cur_th2==='ren'?'selected':''; ?>>👤 人 — 对人类产生影响</option>
                        <option value="gui" <?php echo $cur_th2==='gui'?'selected':''; ?>>👻 鬼 — 对神秘生物/古神眷属产生影响</option>
                        <option value="mo" <?php echo $cur_th2==='mo'?'selected':''; ?>>👿 魔 — 对次级旧日支配者/旧日支配者/古神产生影响</option>
                        <option value="shen" <?php echo $cur_th2==='shen'?'selected':''; ?>>👼 神 — 对旧神产生影响</option>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">修改事件地点</label>
                    <?php $addr = json_decode(file_get_contents(__DIR__ . '/includes/addresses.json'), true); $countries = array_keys($addr); ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <select id="addr_country_edit" style="flex:1;min-width:100px;padding:8px 10px;border:1px solid #d5d5d5;border-radius:4px;font-size:13px;background:#fff;">
                            <option value="">国家</option>
                            <?php foreach($countries as $c): ?><option value="<?php echo $c; ?>"><?php echo $c; ?></option><?php endforeach; ?>
                            <option value="__other__">其他</option>
                        </select>
                        <select id="addr_province_edit" style="flex:1;min-width:100px;padding:8px 10px;border:1px solid #d5d5d5;border-radius:4px;font-size:13px;background:#fff;" disabled><option value="">省/自治区</option></select>
                        <select id="addr_city_edit" style="flex:1;min-width:100px;padding:8px 10px;border:1px solid #d5d5d5;border-radius:4px;font-size:13px;background:#fff;" disabled><option value="">市/州</option></select>
                        <select id="addr_county_edit" style="flex:1;min-width:100px;padding:8px 10px;border:1px solid #d5d5d5;border-radius:4px;font-size:13px;background:#fff;" disabled><option value="">区/县</option></select>
                    </div>
                    <input type="text" name="edit_location" id="edit_location_manual" style="display:none;width:100%;margin-top:8px;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;" placeholder="手动输入完整地点...">
                    <input type="hidden" name="edit_lat" id="edit_lat">
                    <input type="hidden" name="edit_lng" id="edit_lng">
                    <script>
                    (function(){
                        var P='_edit',addr=addrData,cp=document.getElementById('addr_country'+P),pp=document.getElementById('addr_province'+P),
                        cc=document.getElementById('addr_city'+P),ct=document.getElementById('addr_county'+P),
                        man=document.getElementById('edit_location_manual'),lat=document.getElementById('edit_lat'),lng=document.getElementById('edit_lng');
                        var sc='',sp='',sci='';
                        var cl=function(){lat.value='';lng.value='';};
                        var sl=function(la,ln){lat.value=la;lng.value=ln;};
                        cp.addEventListener('change',function(){
                            sc=this.value;pp.innerHTML='<option value=\"\">省/自治区</option>';cc.innerHTML='<option value=\"\">市/州</option>';ct.innerHTML='<option value=\"\">区/县</option>';
                            pp.disabled=true;cc.disabled=true;ct.disabled=true;cl();
                            if(this.value==='__other__'){man.style.display='';return;}
                            man.style.display='none';
                            if(!addr[this.value])return;
                            Object.keys(addr[this.value]).forEach(function(p){pp.add(new Option(p,p));});pp.disabled=false;
                        });
                        pp.addEventListener('change',function(){
                            sp=this.value;cc.innerHTML='<option value=\"\">市/州</option>';ct.innerHTML='<option value=\"\">区/县</option>';
                            cc.disabled=true;ct.disabled=true;cl();
                            if(!this.value||!addr[sc]||!addr[sc][this.value])return;
                            var arr=addr[sc][this.value],capital=arr[0],cities=arr[1];cc.disabled=false;
                            Object.keys(cities).forEach(function(c){cc.add(new Option(c,c));});
                            if(capital)cc.value=capital;
                        });
                        cc.addEventListener('change',function(){
                            sci=this.value;ct.innerHTML='<option value=\"\">区/县</option>';ct.disabled=true;cl();
                            if(!this.value||!addr[sc][sp]||!addr[sc][sp][1][this.value])return;
                            var cd=addr[sc][sp][1][this.value];sl(cd[0],cd[1]);
                            cd.slice(2).forEach(function(d){ct.add(new Option(d,d));});ct.disabled=false;
                        });
                        ct.addEventListener('change',function(){
                            if(this.value&&addr[sc]&&addr[sc][sp]&&addr[sc][sp][1][sci])sl(addr[sc][sp][1][sci][0],addr[sc][sp][1][sci][1]);
                        });
                    })();
                    </script>
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
                        <option value="artifacts">镇物</option><option value="events">事件</option><option value="personnel">人物</option><option value="organizations">组织</option><option value="research">研究发现</option><option value="entities">祂们</option><option value="esoterica">秘术</option><option value="outstanding">优秀员工</option><option value="other">其他</option>
                    </select>
                    <div id="artifact-naming-hint" style="display:none;margin-top:10px;padding:12px 14px;background:#f8f9fa;border:1px solid #e0e0e0;border-radius:4px;font-size:12px;color:#555;line-height:1.7;">
                        <strong style="color:#1B3A5C;">📐 镇物命名：X-x-y-z</strong><br>
                        <span>X: <code>E</code>东方 <code>W</code>西方 <code>O</code>其他</span><br>
                        <span>x: 神话体系编号（E01~16 / W01~11 / O01~08）</span><br>
                        <span>y: 项目编号 · z: 个体编号</span><br>
                        <span style="color:#999;">示例：<code>E-01-001-003</code>（东方·中原河洛·001号·003号）</span><br>
                        <a href="/artifacts/" target="_blank" style="font-size:11px;color:#1B3A5C;">→ 查看完整编号表</a>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;"><span class="time-label-text">报告时间</span></label>
                    <span id="event_time_single3">
                        <input type="datetime-local" name="event_date" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;">
                        <span style="font-size:11px;color:#999;">对外显示的发布时间，留空则使用实际提交时间</span>
                    </span>
                    <span id="event_time_range3" style="display:none;">
                        <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;">
                            <span style="font-size:12px;color:#666;white-space:nowrap;">起始：</span>
                            <input type="datetime-local" name="event_start" style="flex:1;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;">
                            <span style="font-size:12px;color:#666;white-space:nowrap;">结束：</span>
                            <input type="datetime-local" name="event_end" style="flex:1;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;">
                        </div>
                        <span style="font-size:11px;color:#999;">起始留空=持续至终点；终点留空=起始后持续至今；均留空=常驻事件</span>
                    </span>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">访问等级 <span style="font-weight:normal;color:#999;font-size:12px;">（不超过自身等级）</span></label>
                    <select name="access_level" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;background:#fff;">
                        <?php $my_rank = crrg_get_rank($user_id); foreach (crrg_get_accessible_ranks($my_rank) as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo $r['icon']; ?> <?php echo $r['name']; ?> 及以上可阅</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">威胁等级</label>
                    <select name="threat_level" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;background:#fff;">
                        <option value="">未评级</option>
                        <option value="ren">👤 人 — 对人类产生影响</option>
                        <option value="gui">👻 鬼 — 对神秘生物/古神眷属产生影响</option>
                        <option value="mo">👿 魔 — 对次级旧日支配者/旧日支配者/古神产生影响</option>
                        <option value="shen">👼 神 — 对旧神产生影响</option>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">事件地点</label>
                    <?php $addr = json_decode(file_get_contents(__DIR__ . '/includes/addresses.json'), true); $countries = array_keys($addr); ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <select id="addr_country" style="flex:1;min-width:100px;padding:8px 10px;border:1px solid #d5d5d5;border-radius:4px;font-size:13px;background:#fff;">
                            <option value="">国家</option>
                            <?php foreach($countries as $c): ?><option value="<?php echo $c; ?>"><?php echo $c; ?></option><?php endforeach; ?>
                            <option value="__other__">其他</option>
                        </select>
                        <select id="addr_province" style="flex:1;min-width:100px;padding:8px 10px;border:1px solid #d5d5d5;border-radius:4px;font-size:13px;background:#fff;" disabled>
                            <option value="">省/自治区</option>
                        </select>
                        <select id="addr_city" style="flex:1;min-width:100px;padding:8px 10px;border:1px solid #d5d5d5;border-radius:4px;font-size:13px;background:#fff;" disabled>
                            <option value="">市/州</option>
                        </select>
                        <select id="addr_county" style="flex:1;min-width:100px;padding:8px 10px;border:1px solid #d5d5d5;border-radius:4px;font-size:13px;background:#fff;" disabled>
                            <option value="">区/县</option>
                        </select>
                    </div>
                    <input type="text" name="report_location" id="report_location_manual" style="display:none;width:100%;margin-top:8px;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;" placeholder="手动输入完整地点...">
                    <input type="hidden" name="report_lat" id="report_lat">
                    <input type="hidden" name="report_lng" id="report_lng">
                    <script>
                    var addrData = <?php echo json_encode($addr, JSON_UNESCAPED_UNICODE); ?>;
                    var cp=document.getElementById('addr_country'), pp=document.getElementById('addr_province');
                    var cc=document.getElementById('addr_city'), ct=document.getElementById('addr_county');
                    var man=document.getElementById('report_location_manual'), lat=document.getElementById('report_lat'), lng=document.getElementById('report_lng');
                    function clearLoc(){lat.value='';lng.value='';}
                    function setLoc(la,ln){lat.value=la;lng.value=ln;}
                    var selCountry='', selProvince='', selCity='';
                    cp.addEventListener('change',function(){
                        selCountry=this.value; pp.innerHTML='<option value="">省/自治区</option>'; cc.innerHTML='<option value="">市/州</option>'; ct.innerHTML='<option value="">区/县</option>';
                        pp.disabled=true;cc.disabled=true;ct.disabled=true;clearLoc();
                        if(this.value==='__other__'){man.style.display='';man.required=true;return;}
                        man.style.display='none';man.required=false;
                        if(!addrData[this.value])return;
                        var provs=Object.keys(addrData[this.value]); pp.disabled=false;
                        provs.forEach(function(p){pp.add(new Option(p,p));});
                    });
                    pp.addEventListener('change',function(){
                        selProvince=this.value; cc.innerHTML='<option value="">市/州</option>'; ct.innerHTML='<option value="">区/县</option>';
                        cc.disabled=true;ct.disabled=true;clearLoc();
                        if(!this.value||!addrData[selCountry]||!addrData[selCountry][this.value])return;
                        var arr=addrData[selCountry][this.value];
                        var capital=arr[0], cities=arr[1];
                        cc.disabled=false;
                        Object.keys(cities).forEach(function(c){cc.add(new Option(c,c));});
                        if(capital) cc.value=capital;
                    });
                    cc.addEventListener('change',function(){
                        selCity=this.value; ct.innerHTML='<option value="">区/县</option>';
                        ct.disabled=true;clearLoc();
                        if(!this.value||!addrData[selCountry][selProvince]||!addrData[selCountry][selProvince][1][this.value])return;
                        var cityData=addrData[selCountry][selProvince][1][this.value];
                        var la=cityData[0], ln=cityData[1]; setLoc(la,ln);
                        var districts=cityData.slice(2);
                        if(districts.length>0){ct.disabled=false; districts.forEach(function(d){ct.add(new Option(d,d));});}
                    });
                    ct.addEventListener('change',function(){if(this.value) setLoc(addrData[selCountry][selProvince][1][selCity][0],addrData[selCountry][selProvince][1][selCity][1]);});
                    // 事件类型切换时间字段（所有表单）
                    (function(){
                        document.querySelectorAll('select[name="report_category"]').forEach(function(cat){
                            var form=cat.closest('form');
                            var single=form.querySelector('[id^="event_time_single"]');
                            var range=form.querySelector('[id^="event_time_range"]');
                            if(!single||!range)return;
                            var toggle=function(){
                                if(cat.value==='events'){single.style.display='none';range.style.display='';}
                                else{single.style.display='';range.style.display='none';}
                                // 切换标签文字
                                var label=form.querySelector('.time-label-text');
                                if(label) label.textContent=cat.value==='events'?'事件时间':'报告时间';
                            };
                            cat.addEventListener('change',toggle);
                            toggle();
                        });
                    })();
                    </script>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">标签 <span style="font-weight:normal;color:#999;font-size:12px;">（逗号分隔，如：调查报告,始源实体）</span></label>
                    <input type="text" name="report_tags" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;" placeholder="输入标签，逗号分隔...">
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
<script>
(function(){
    // 新建报告表单
    var sel = document.querySelector('select[name="report_category"]');
    var hint = document.getElementById('artifact-naming-hint');
    if (sel && hint) {
        sel.addEventListener('change', function(){
            hint.style.display = this.value === 'artifacts' ? '' : 'none';
        });
    }
    // 编辑草稿表单
    var sel2 = document.querySelector('form[method="post"] select[name="report_category"]:not([onchange])');
    var hint2 = document.getElementById('artifact-naming-hint-edit');
    if (sel2 && hint2) {
        // 给编辑表单的 select 也加事件（需要精确匹配）
    }
    // 更简单：给所有 report_category select 加事件
    document.querySelectorAll('select[name="report_category"]').forEach(function(s){
        var targetId = s.closest('form').querySelector('[id^="artifact-naming-hint"]');
        if (!targetId) return;
        var toggle = function(){ targetId.style.display = s.value === 'artifacts' ? '' : 'none'; };
        s.addEventListener('change', toggle);
        toggle(); // 初始加载时触发
    });
})();
</script>
<?php get_footer(); ?>
