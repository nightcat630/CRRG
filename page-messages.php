<?php
/* Template Name: 私信 */

if (!is_user_logged_in()) { wp_redirect(wp_login_url()); exit; }

$uid = get_current_user_id();
$message = '';

// 发送私信
if ($_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'crrg_msg')) {
    $action = $_POST['msg_action'] ?? '';
    if ($action === 'send') {
        $to_input = sanitize_text_field(wp_unslash($_POST['to_user'] ?? ''));
        // 支持用户名或数字 ID
        if (is_numeric($to_input)) {
            $to_uid = (int)$to_input;
        } else {
            $to_user_obj = get_user_by('login', $to_input) ?: get_user_by('slug', $to_input);
            $to_uid = $to_user_obj ? $to_user_obj->ID : 0;
        }
        $title = sanitize_text_field(wp_unslash($_POST['msg_title'] ?? ''));
        $content = wp_kses_post(wp_unslash($_POST['msg_content'] ?? ''));
        if ($to_uid && $title && $content && get_userdata($to_uid)) {
            crrg_send_message($uid, $to_uid, $title, $content);
            $message = '私信已发送。';
        } else {
            $message = '用户不存在，请检查用户名。';
        }
    } elseif ($action === 'delete') {
        crrg_delete_message($_POST['mid'] ?? '', $uid);
        $message = '已删除。';
    }
}

// 标记已读
if (isset($_GET['read'])) {
    crrg_mark_message_read($_GET['read']);
}

$tab = $_GET['tab'] ?? 'inbox';
$inbox = crrg_get_messages($uid);
$sent = crrg_get_sent_messages($uid);
$unread = crrg_unread_count($uid);

// 查看单条消息
$view_msg = null;
if (isset($_GET['view']) && isset($inbox[$_GET['view']])) {
    $view_msg = $inbox[$_GET['view']];
    crrg_mark_message_read($_GET['view']);
} elseif (isset($_GET['view']) && isset($sent[$_GET['view']])) {
    $view_msg = $sent[$_GET['view']];
}

// 回复模式
$reply_to = null;
if (isset($_GET['reply']) && isset($inbox[$_GET['reply']])) {
    $reply_to = $inbox[$_GET['reply']];
}

// 新私信指定收件人
$to_user = null;
if (isset($_GET['to'])) {
    $to_user = get_userdata((int)$_GET['to']);
}

get_header();
?>

<div class="gov-main">
    <div class="gov-content">
        <h1 style="font-size:22px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;">✉️ 私信</h1>
        <div style="color:#999;font-size:12px;margin-bottom:20px;border-bottom:1px solid #eee;padding-bottom:12px;">
            收件箱 <?php if ($unread): ?><span style="color:#C41230;">(<?php echo $unread; ?> 未读)</span><?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div style="background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:10px 16px;border-radius:4px;margin-bottom:16px;"><?php echo esc_html($message); ?></div>
        <?php endif; ?>

        <!-- 查看单条消息 -->
        <?php if ($view_msg): $sender = get_userdata($view_msg['from']); $recipient = get_userdata($view_msg['to']); ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:20px;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #eee;">
                    <div>
                        <strong style="font-size:16px;"><?php echo esc_html($view_msg['title']); ?></strong>
                        <div style="font-size:12px;color:#999;margin-top:2px;">
                            <?php if ((int)$view_msg['from'] === $uid): ?>
                                发送给：<?php echo $recipient ? esc_html($recipient->display_name) : '未知'; ?>
                            <?php else: ?>
                                来自：<?php echo $sender ? esc_html($sender->display_name) : '未知'; ?>
                            <?php endif; ?>
                            · <?php echo $view_msg['time']; ?>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <?php if ((int)$view_msg['from'] !== $uid): ?>
                            <a href="?tab=inbox&reply=<?php echo urlencode($_GET['view'] ?? ''); ?>" class="button button-small" style="background:#1B3A5C;color:#fff;border:none;padding:4px 12px;border-radius:3px;text-decoration:none;font-size:12px;">回复</a>
                        <?php endif; ?>
                        <a href="?tab=<?php echo $tab; ?>" style="color:#999;font-size:12px;text-decoration:none;line-height:28px;">← 返回列表</a>
                    </div>
                </div>
                <div style="font-size:14px;color:#333;line-height:1.8;white-space:pre-wrap;"><?php echo $view_msg['content']; ?></div>
            </div>
        <?php endif; ?>

        <!-- 回复/新私信表单 -->
        <?php if ($reply_to || $to_user || (isset($_GET['compose']) && !$view_msg)): ?>
            <?php 
            if ($reply_to) {
                $reply_user = get_userdata($reply_to['from']);
                $prefill_to = $reply_user ? $reply_user->user_login : '';
                $prefill_title = 'Re: ' . $reply_to['title'];
            } elseif ($to_user) {
                $prefill_to = $to_user->user_login;
                $prefill_title = '';
            } else {
                $prefill_to = '';
                $prefill_title = '';
            }
            ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:20px;margin-bottom:20px;">
                <h3 style="margin-top:0;"><?php echo $reply_to ? '回复私信' : '发送新私信'; ?></h3>
                <form method="post">
                    <?php wp_nonce_field('crrg_msg'); ?>
                    <input type="hidden" name="msg_action" value="send">
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:13px;color:#666;margin-bottom:4px;">收件人</label>
                        <input type="text" name="to_user" value="<?php echo esc_attr($prefill_to); ?>" required
                            style="width:260px;padding:8px 12px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;"
                            placeholder="输入用户名">
                    </div>
                    <div style="margin-bottom:12px;">
                        <input type="text" name="msg_title" value="<?php echo esc_attr($prefill_title); ?>" required
                            style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:3px;font-size:14px;"
                            placeholder="私信标题">
                    </div>
                    <div style="margin-bottom:12px;">
                        <?php wp_editor('', 'msg_content', ['textarea_name'=>'msg_content','textarea_rows'=>6,'media_buttons'=>false,'teeny'=>true]); ?>
                    </div>
                    <button type="submit" style="background:#C41230;color:#fff;border:none;padding:8px 24px;border-radius:4px;cursor:pointer;">发送私信</button>
                    <a href="?tab=inbox" style="color:#999;font-size:13px;text-decoration:none;margin-left:10px;">取消</a>
                </form>
            </div>
        <?php endif; ?>

        <!-- 标签切换 -->
        <?php if (!$view_msg && !$reply_to && !$to_user && !isset($_GET['compose'])): ?>
            <div style="margin-bottom:16px;display:flex;gap:8px;align-items:center;">
                <a href="?tab=inbox" style="padding:6px 16px;border-radius:3px;font-size:13px;text-decoration:none;<?php echo $tab==='inbox'?'background:#1B3A5C;color:#fff;':'background:#f0f0f0;color:#333;'; ?>">收件箱<?php echo $unread?' ('.$unread.')':''; ?></a>
                <a href="?tab=sent" style="padding:6px 16px;border-radius:3px;font-size:13px;text-decoration:none;<?php echo $tab==='sent'?'background:#1B3A5C;color:#fff;':'background:#f0f0f0;color:#333;'; ?>">已发送</a>
                <a href="?compose=1" style="padding:6px 16px;border-radius:3px;font-size:13px;text-decoration:none;background:#C41230;color:#fff;">✚ 写私信</a>
            </div>

            <?php $list = $tab === 'sent' ? $sent : $inbox; ?>
            <?php if ($list): ?>
                <?php foreach ($list as $mid => $msg): $other = $tab==='sent' ? get_userdata($msg['to']) : get_userdata($msg['from']); ?>
                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:12px 16px;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center;<?php echo (!$msg['read'] && $tab==='inbox')?'border-left:3px solid #C41230;':''; ?>">
                        <div style="flex:1;">
                            <a href="?tab=<?php echo $tab; ?>&view=<?php echo urlencode($mid); ?>" style="font-weight:<?php echo (!$msg['read']&&$tab==='inbox')?'bold':'normal'; ?>;color:#1B3A5C;text-decoration:none;font-size:14px;">
                                <?php if (!$msg['read'] && $tab==='inbox'): ?><span style="color:#C41230;">●</span> <?php endif; ?>
                                <?php echo esc_html($msg['title']); ?>
                            </a>
                            <div style="font-size:11px;color:#999;margin-top:2px;">
                                <?php echo $tab==='sent'?'发送给':'来自'; ?>：<?php echo $other ? esc_html($other->display_name) : '用户#'.$msg[$tab==='sent'?'to':'from']; ?>
                                · <?php echo $msg['time']; ?>
                            </div>
                        </div>
                        <form method="post" style="margin:0;" onsubmit="return confirm('确定删除？');">
                            <?php wp_nonce_field('crrg_msg'); ?>
                            <input type="hidden" name="msg_action" value="delete">
                            <input type="hidden" name="mid" value="<?php echo $mid; ?>">
                            <button type="submit" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:12px;">删除</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:#999;text-align:center;padding:40px;"><?php echo $tab==='sent'?'暂无已发送私信。':'收件箱为空。'; ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
