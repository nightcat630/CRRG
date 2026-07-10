<?php
/* Template Name: 报告管理 */
if (!is_user_logged_in()) { wp_redirect(wp_login_url()); exit; }
$user_id = get_current_user_id();
$message = ''; $error = '';

// ─── 新建报告 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_report')) {
    $title = sanitize_text_field($_POST['report_title'] ?? '');
    $content = wp_kses_post($_POST['report_content'] ?? '');
    $category = sanitize_text_field($_POST['report_category'] ?? '');
    $status = ($_POST['save_as'] ?? '') === 'draft' ? 'draft' : 'pending';
    if (empty($title)) $error = '请输入标题';
    elseif (empty($content)) $error = '请输入内容';
    else {
        $post_id = wp_insert_post(['post_title'=>$title,'post_content'=>$content,'post_status'=>$status,'post_type'=>'post','post_author'=>$user_id]);
        if ($post_id && !is_wp_error($post_id)) {
            $cat_map = ['artifacts'=>'镇物','events'=>'事件','personnel'=>'人物','organizations'=>'组织','research'=>'研究发现','entities'=>'祂们','esoterica'=>'秘术','outstanding'=>'优秀员工','other'=>'其他'];
            update_post_meta($post_id, 'crrg_report_type', $category);
            update_post_meta($post_id, 'crrg_report_type_name', $cat_map[$category] ?? '其他');
            // 访问等级
            $access = sanitize_text_field($_POST['report_access_level'] ?? 'observer');
            $my_rank = crrg_get_rank($user_id);
            $allowed = array_column(crrg_get_accessible_ranks($my_rank), 'id');
            update_post_meta($post_id, 'crrg_access_level', in_array($access, $allowed) ? $access : 'observer');
            // 威胁等级
            $threat = sanitize_text_field($_POST['report_threat_level'] ?? '');
            if ($threat) update_post_meta($post_id, 'crrg_threat_level', $threat); else delete_post_meta($post_id, 'crrg_threat_level');
            // 时间
            $event_date = sanitize_text_field($_POST['report_event_date'] ?? '');
            if ($event_date) update_post_meta($post_id, 'crrg_event_date', $event_date);
            $event_start = sanitize_text_field($_POST['report_event_start'] ?? '');
            if ($event_start) update_post_meta($post_id, 'crrg_event_start', $event_start);
            $event_end = sanitize_text_field($_POST['report_event_end'] ?? '');
            if ($event_end) update_post_meta($post_id, 'crrg_event_end', $event_end);
            // 地点
            $loc_manual = sanitize_text_field($_POST['report_location'] ?? '');
            $loc_parts = array_filter([sanitize_text_field($_POST['report_addr_country']??''), sanitize_text_field($_POST['report_addr_province']??''), sanitize_text_field($_POST['report_addr_city']??''), sanitize_text_field($_POST['report_addr_county']??'')]);
            $location = $loc_manual ?: implode(' ', $loc_parts);
            if ($location && ($_POST['report_addr_country']??'') !== '__other__') update_post_meta($post_id, 'crrg_location', $location);
            $lat = $_POST['report_lat'] ?? ''; $lng = $_POST['report_lng'] ?? '';
            if ($lat && $lng) { update_post_meta($post_id, 'crrg_lat', $lat); update_post_meta($post_id, 'crrg_lng', $lng); }
            // 标签
            $tags = sanitize_text_field($_POST['report_tags'] ?? '');
            if ($tags) wp_set_post_tags($post_id, array_filter(array_map('trim', preg_split('/[,，]/', $tags))), false);
            $message = $status === 'pending' ? '报告已提交审核！待审批后发布 +15 资历' : '草稿已保存';
        } else $error = '发布失败';
    }
}

// ─── 编辑草稿 ───
$editing_draft = null;
if (isset($_GET['edit_draft'])) { $editing_draft = get_post((int)$_GET['edit_draft']); if (!$editing_draft || (int)$editing_draft->post_author !== $user_id) $editing_draft = null; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_draft']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_edit_draft')) {
    $draft_id = (int)$_POST['draft_id'];
    $draft = get_post($draft_id);
    if ($draft && (int)$draft->post_author === $user_id && $draft->post_status === 'draft') {
        $ns = ($_POST['save_as'] ?? '') === 'draft' ? 'draft' : 'pending';
        wp_update_post(['ID'=>$draft_id,'post_title'=>sanitize_text_field($_POST['report_title']),'post_content'=>wp_kses_post($_POST['report_content']),'post_status'=>$ns]);
        $cat = sanitize_text_field($_POST['report_category'] ?? 'other');
        $cm = ['artifacts'=>'镇物','events'=>'事件','personnel'=>'人物','organizations'=>'组织','research'=>'研究发现','entities'=>'祂们','esoterica'=>'秘术','outstanding'=>'优秀员工','other'=>'其他'];
        update_post_meta($draft_id, 'crrg_report_type', $cat); update_post_meta($draft_id, 'crrg_report_type_name', $cm[$cat]??'其他');
        $ac = sanitize_text_field($_POST['report_access_level'] ?? 'observer');
        $mr = crrg_get_rank($user_id); $al = array_column(crrg_get_accessible_ranks($mr), 'id');
        update_post_meta($draft_id, 'crrg_access_level', in_array($ac,$al)?$ac:'observer');
        $th = sanitize_text_field($_POST['report_threat_level'] ?? ''); if ($th) update_post_meta($draft_id, 'crrg_threat_level', $th); else delete_post_meta($draft_id, 'crrg_threat_level');
        $ed = sanitize_text_field($_POST['report_event_date'] ?? ''); if ($ed) update_post_meta($draft_id, 'crrg_event_date', $ed);
        $es = sanitize_text_field($_POST['report_event_start'] ?? ''); if ($es) update_post_meta($draft_id, 'crrg_event_start', $es);
        $ee = sanitize_text_field($_POST['report_event_end'] ?? ''); if ($ee) update_post_meta($draft_id, 'crrg_event_end', $ee);
        $loc = sanitize_text_field($_POST['report_location'] ?? ''); if ($loc) update_post_meta($draft_id, 'crrg_location', $loc);
        $tg = sanitize_text_field($_POST['report_tags'] ?? ''); if ($tg) wp_set_post_tags($draft_id, array_filter(array_map('trim', preg_split('/[,，]/', $tg))), false);
        $message = $ns === 'pending' ? '报告已提交审核！' : '草稿已更新';
        $editing_draft = null;
    }
}

// ─── 修改申请 ───
$edit_post = null;
if (isset($_GET['edit_post'])) { $edit_post = get_post((int)$_GET['edit_post']); if (!$edit_post || (int)$edit_post->post_author !== $user_id) $edit_post = null; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_edit']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_edit_req')) {
    $pid = (int)$_POST['edit_post_id']; $p = get_post($pid);
    if ($p && (int)$p->post_author === $user_id) {
        update_post_meta($pid, 'crrg_edit_request', $user_id);
        update_post_meta($pid, 'crrg_edit_title', sanitize_text_field($_POST['edit_title'] ?? ''));
        update_post_meta($pid, 'crrg_edit_content', wp_kses_post($_POST['edit_content'] ?? ''));
        update_post_meta($pid, 'crrg_edit_time', current_time('mysql'));
        update_post_meta($pid, 'crrg_edit_tags', sanitize_text_field($_POST['edit_tags'] ?? ''));
        update_post_meta($pid, 'crrg_edit_access', sanitize_text_field($_POST['edit_access_level'] ?? ''));
        update_post_meta($pid, 'crrg_edit_threat', sanitize_text_field($_POST['edit_threat_level'] ?? ''));
        update_post_meta($pid, 'crrg_edit_location', sanitize_text_field($_POST['edit_location'] ?? ''));
        update_post_meta($pid, 'crrg_edit_date', sanitize_text_field($_POST['edit_event_date'] ?? ''));
        update_post_meta($pid, 'crrg_edit_start', sanitize_text_field($_POST['edit_event_start'] ?? ''));
        update_post_meta($pid, 'crrg_edit_end', sanitize_text_field($_POST['edit_event_end'] ?? ''));
        update_post_meta($pid, 'crrg_edit_category', sanitize_text_field($_POST['edit_category'] ?? ''));
        $el = sanitize_text_field($_POST['edit_lat'] ?? ''); $en = sanitize_text_field($_POST['edit_lng'] ?? '');
        if ($el && $en) { update_post_meta($pid, 'crrg_edit_lat', $el); update_post_meta($pid, 'crrg_edit_lng', $en); }
        $message = '修改申请已提交，等待管理员审核。';
        wp_redirect(add_query_arg('msg', urlencode($message), remove_query_arg(['edit_post','msg']))); exit;
    }
}

// 重定向 + 消息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($message)) { wp_redirect(add_query_arg('msg', urlencode($message), remove_query_arg(['new','edit_draft','edit_post','msg']))); exit; }
if (!empty($_GET['msg'])) $message = sanitize_text_field($_GET['msg']);

// 删除草稿
if (isset($_GET['delete']) && wp_verify_nonce($_GET['_nonce'] ?? '', 'crrg_delete_report')) {
    $dp = get_post((int)$_GET['delete']);
    if ($dp && (int)$dp->post_author === $user_id && $dp->post_status === 'draft') { wp_delete_post($dp->ID, true); $message = '草稿已删除'; }
}

// 删除申请
if (isset($_GET['req_delete']) && wp_verify_nonce($_GET['_nonce'] ?? '', 'crrg_req_delete')) {
    $rp = get_post((int)$_GET['req_delete']);
    if ($rp && (int)$rp->post_author === $user_id) { update_post_meta($rp->ID, 'crrg_delete_request', $user_id); update_post_meta($rp->ID, 'crrg_delete_request_time', current_time('mysql')); $message = '删除申请已提交'; }
}

$show_form = isset($_GET['new']) || $edit_post || $editing_draft || (empty(get_posts(['post_type'=>'post','post_status'=>'draft','author'=>$user_id,'posts_per_page'=>1])) && empty(get_posts(['post_type'=>'post','post_status'=>'publish','author'=>$user_id,'posts_per_page'=>1])));
require_once __DIR__ . '/includes/report-form.php';
require_once __DIR__ . "/includes/report-form.php";


get_header();
?>
<div class="gov-main"><div class="gov-content">
<h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;"> 报告管理</h1>
<div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">
    中央重生抵御小组 · 档案提交系统
    <?php if ($edit_post): ?><a href="?" style="float:right;color:#1B3A5C;font-size:12px;">← 返回列表</a>
    <?php elseif (!$show_form): ?><a href="?new=1" style="float:right;background:#C41230;color:#fff;padding:4px 14px;border-radius:3px;font-size:12px;text-decoration:none;">+ 新建报告</a>
    <?php else: ?><a href="?" style="float:right;color:#1B3A5C;font-size:12px;">← 返回列表</a><?php endif; ?>
</div>
<?php if ($message): ?><div style="background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:12px 16px;border-radius:4px;margin-bottom:16px;"><?php echo esc_html($message); ?></div><?php endif; ?>
<?php if ($error): ?><div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:12px 16px;border-radius:4px;margin-bottom:16px;"><?php echo esc_html($error); ?></div><?php endif; ?>

<?php if ($editing_draft): ?>
    <!-- 编辑草稿 -->
    <form method="post">
        <?php wp_nonce_field('crrg_edit_draft'); ?>
        <input type="hidden" name="draft_id" value="<?php echo $editing_draft->ID; ?>">
        <div style="margin-bottom:16px;"><label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">报告标题</label><input type="text" name="report_title" value="<?php echo esc_attr($editing_draft->post_title); ?>" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;"></div>
        <?php report_form_fields('report_', get_post_meta($editing_draft->ID,'crrg_report_type',true)?:'other', get_post_meta($editing_draft->ID,'crrg_access_level',true)?:'observer', get_post_meta($editing_draft->ID,'crrg_threat_level',true)?:'', get_post_meta($editing_draft->ID,'crrg_event_date',true)?:'', get_post_meta($editing_draft->ID,'crrg_event_start',true)?:'', get_post_meta($editing_draft->ID,'crrg_event_end',true)?:'', get_post_meta($editing_draft->ID,'crrg_location',true)?:'', implode(',', wp_get_post_tags($editing_draft->ID,['fields'=>'names']))); ?>
        <div style="margin-bottom:16px;"><label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">报告内容</label><?php wp_editor($editing_draft->post_content, 'report_content', ['textarea_name'=>'report_content','textarea_rows'=>12,'media_buttons'=>true,'teeny'=>false]); ?></div>
        <div style="display:flex;gap:12px;">
            <button type="submit" name="update_draft" value="1" style="background:#C41230;color:#fff;border:none;padding:10px 32px;border-radius:4px;font-size:15px;cursor:pointer;font-weight:bold;">发布报告</button>
            <button type="submit" name="update_draft" value="1" onclick="var f=this.form;var i=document.createElement('input');i.type='hidden';i.name='save_as';i.value='draft';f.appendChild(i);" style="background:#f0f0f0;color:#555;border:1px solid #d5d5d5;padding:10px 24px;border-radius:4px;font-size:14px;cursor:pointer;">保存草稿</button>
            <a href="?" style="background:#f0f0f0;color:#555;border:1px solid #d5d5d5;padding:10px 24px;border-radius:4px;font-size:14px;text-decoration:none;">取消</a>
        </div>
    </form>

<?php elseif ($edit_post): ?>
    <!-- 修改申请 -->
    <form method="post">
        <?php wp_nonce_field('crrg_edit_req'); ?>
        <input type="hidden" name="edit_post_id" value="<?php echo $edit_post->ID; ?>">
        <div style="margin-bottom:16px;"><label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">修改后标题</label><input type="text" name="edit_title" value="<?php echo esc_attr($edit_post->post_title); ?>" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;"></div>
        <?php report_form_fields('edit_', get_post_meta($edit_post->ID,'crrg_report_type',true)?:'other', get_post_meta($edit_post->ID,'crrg_access_level',true)?:'observer', get_post_meta($edit_post->ID,'crrg_threat_level',true)?:'', get_post_meta($edit_post->ID,'crrg_event_date',true)?:'', get_post_meta($edit_post->ID,'crrg_event_start',true)?:'', get_post_meta($edit_post->ID,'crrg_event_end',true)?:'', get_post_meta($edit_post->ID,'crrg_location',true)?:'', implode(',', wp_get_post_tags($edit_post->ID,['fields'=>'names']))); ?>
        <div style="margin-bottom:16px;"><label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">修改后内容</label><?php wp_editor($edit_post->post_content, 'edit_content', ['textarea_name'=>'edit_content','textarea_rows'=>12,'media_buttons'=>true,'teeny'=>false]); ?></div>
        <div style="display:flex;gap:12px;">
            <button type="submit" name="submit_edit" value="1" style="background:#C41230;color:#fff;border:none;padding:10px 32px;border-radius:4px;font-size:15px;cursor:pointer;">提交修改申请</button>
            <a href="?" style="background:#f0f0f0;color:#555;border:1px solid #d5d5d5;padding:10px 24px;border-radius:4px;font-size:14px;text-decoration:none;">取消</a>
        </div>
    </form>

<?php elseif ($show_form): ?>
    <!-- 新建报告 -->
    <form method="post">
        <?php wp_nonce_field('crrg_report'); ?>
        <div style="margin-bottom:16px;"><label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">报告标题</label><input type="text" name="report_title" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;" placeholder="输入报告标题..."></div>
        <?php report_form_fields('report_', 'other', 'observer', '', '', '', '', '', ''); ?>
        <div style="margin-bottom:16px;"><label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">报告内容</label><?php wp_editor('', 'report_content', ['textarea_name'=>'report_content','textarea_rows'=>12,'media_buttons'=>true,'teeny'=>false]); ?></div>
        <div style="display:flex;gap:12px;">
            <button type="submit" name="submit_report" value="1" style="background:#C41230;color:#fff;border:none;padding:10px 32px;border-radius:4px;font-size:15px;cursor:pointer;font-weight:bold;">发布报告</button>
            <button type="submit" name="submit_report" value="1" onclick="var f=this.form;var i=document.createElement('input');i.type='hidden';i.name='save_as';i.value='draft';f.appendChild(i);" style="background:#f0f0f0;color:#555;border:1px solid #d5d5d5;padding:10px 24px;border-radius:4px;font-size:14px;cursor:pointer;">保存草稿</button>
        </div>
    </form>

<?php else: ?>
    <?php $drafts = get_posts(['post_type'=>'post','post_status'=>'draft','author'=>$user_id,'posts_per_page'=>10]); $published = get_posts(['post_type'=>'post','post_status'=>'publish','author'=>$user_id,'posts_per_page'=>10]); ?>
    <?php if ($drafts): ?><h3 style="font-size:16px;color:#1B3A5C;margin:20px 0 12px;"> 草稿</h3><?php foreach($drafts as $d): ?><div style="background:#fff8e1;border:1px solid #ffe082;border-radius:4px;padding:12px 16px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;"><div><strong><?php echo esc_html($d->post_title?:'无标题'); ?></strong><span style="color:#999;font-size:12px;margin-left:8px;"><?php echo get_the_date('m-d H:i',$d); ?></span></div><div><a href="?edit_draft=<?php echo $d->ID; ?>" style="color:#1B3A5C;font-size:12px;margin-right:12px;">编辑</a><a href="?delete=<?php echo $d->ID; ?>&_nonce=<?php echo wp_create_nonce('crrg_delete_report'); ?>" style="color:#c00;font-size:12px;" onclick="return confirm('确定删除？')">删除</a></div></div><?php endforeach; ?><?php endif; ?>
    <?php if ($published): ?><h3 style="font-size:16px;color:#1B3A5C;margin:24px 0 12px;">√ 已发布</h3><?php foreach($published as $p): $cats=get_the_category($p->ID); ?><div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:12px 16px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;"><div><strong><a href="<?php echo get_permalink($p); ?>" style="color:#1B3A5C;text-decoration:none;"><?php echo esc_html($p->post_title); ?></a></strong><span style="color:#999;font-size:12px;margin-left:8px;"><?php echo get_the_date('Y-m-d',$p); ?></span></div><div><a href="?edit_post=<?php echo $p->ID; ?>" style="color:#1B3A5C;font-size:12px;">申请修改</a> <a href="?req_delete=<?php echo $p->ID; ?>&_nonce=<?php echo wp_create_nonce('crrg_req_delete'); ?>" style="color:#c00;font-size:12px;margin-left:8px;" onclick="return confirm('确定申请删除？')">申请删除</a></div></div><?php endforeach; ?><?php endif; ?>
    <?php if (empty($drafts) && empty($published)): ?><p style="color:#999;text-align:center;padding:40px;">暂无报告，<a href="?new=1">点击新建</a></p><?php endif; ?>
<?php endif; ?>
</div></div>

<script>
// 事件类型切换
document.querySelectorAll('select[name$="category"]').forEach(function(cat){
    var form=cat.closest('form');
    var range=form.querySelector('.event-range-block');
    if(!range)return;
    var toggle=function(){ range.style.display=cat.value==='events'?'':'none'; };
    cat.addEventListener('change',toggle);
    toggle();
});

// 级联地址
var addrData=<?php echo json_encode(json_decode(file_get_contents(__DIR__.'/includes/addresses.json'),true), JSON_UNESCAPED_UNICODE); ?>;
document.querySelectorAll('form').forEach(function(form){
    var cp=form.querySelector('[id$="addr_country"]'),pp=form.querySelector('[id$="addr_province"]'),cc=form.querySelector('[id$="addr_city"]'),ct=form.querySelector('[id$="addr_county"]');
    var man=form.querySelector('input[name$="location"]'),lat=form.querySelector('input[name$="lat"]'),lng=form.querySelector('input[name$="lng"]');
    if(!cp||!pp||!cc||!ct)return;
    var sc='',sp='',sci='';
    var cl=function(){lat.value='';lng.value='';};
    var sl=function(la,ln){lat.value=la;lng.value=ln;};
    cp.addEventListener('change',function(){sc=this.value;pp.innerHTML='<option value=\"\">省/自治区</option>';cc.innerHTML='<option value=\"\">市/州</option>';ct.innerHTML='<option value=\"\">区/县</option>';pp.disabled=true;cc.disabled=true;ct.disabled=true;cl();if(this.value==='__other__'){man.style.display='';return;}man.style.display='none';if(!addrData[this.value])return;Object.keys(addrData[this.value]).forEach(function(p){pp.add(new Option(p,p));});pp.disabled=false;});
    pp.addEventListener('change',function(){sp=this.value;cc.innerHTML='<option value=\"\">市/州</option>';ct.innerHTML='<option value=\"\">区/县</option>';cc.disabled=true;ct.disabled=true;cl();if(!this.value||!addrData[sc]||!addrData[sc][this.value])return;var arr=addrData[sc][this.value],capital=arr[0],cities=arr[1];cc.disabled=false;Object.keys(cities).forEach(function(c){cc.add(new Option(c,c));});if(capital)cc.value=capital;});
    cc.addEventListener('change',function(){sci=this.value;ct.innerHTML='<option value=\"\">区/县</option>';ct.disabled=true;cl();if(!this.value||!addrData[sc][sp]||!addrData[sc][sp][1][this.value])return;var cd=addrData[sc][sp][1][this.value];sl(cd[0],cd[1]);cd.slice(2).forEach(function(d){ct.add(new Option(typeof d==='string'?d:d[0],typeof d==='string'?d:d[0]));});ct.disabled=false;ct.setAttribute('onchange','var v=this.value;var c=this.form.querySelector("[id$=addr_city]").value;var p=this.form.querySelector("[id$=addr_province]").value;var co=this.form.querySelector("[id$=addr_country]").value;if(v&&addrData[co]&&addrData[co][p]&&addrData[co][p][1][c]){var cd=addrData[co][p][1][c];var f=null;cd.slice(2).forEach(function(d){if((typeof d=="string"?d:d[0])===v)f=d;});var la=this.form.querySelector("input[name$=lat]");var ln=this.form.querySelector("input[name$=lng]");if(f&&typeof f!=="string"){la.value=f[1];ln.value=f[2];}else{la.value=cd[0];ln.value=cd[1];}}');});
    ct.addEventListener('change',function(){if(!this.value)return;var cVal=cc.value;var pVal=pp.value;var country=cp.value;if(!addrData[country]||!addrData[country][pVal]||!addrData[country][pVal][1][cVal])return;var cd=addrData[country][pVal][1][cVal];var found=null;cd.slice(2).forEach(function(d){if((typeof d==='string'?d:d[0])===ct.value)found=d;});if(found&&typeof found!=='string')sl(found[1],found[2]);else sl(cd[0],cd[1]);});
});
</script>
<?php get_footer(); ?>
