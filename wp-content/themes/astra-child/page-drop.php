<?php
/**
 * Template Name: 文件投递
 * 手机浏览器打开就能上传/下载（2026-10-31，委员长："搞个文件好麻烦"）
 * ------------------------------------------------------------
 * · 用一个**访问密钥**保护（URL 里带 ?k=…），不依赖 WordPress 登录
 * · 上传落到 /root/data/inbox/（按日期分子目录），单文件上限 200 MB
 * · 列表里可以直接下载、复制链接
 * · **不开放删除**（避免误删），要删跟河洛说
 */
if (!defined('ABSPATH')) exit;

/* 密钥：改这里，或放到 option crrg_drop_key */
$KEY = (string) get_option('crrg_drop_key', '');
if ($KEY === '') $KEY = 'crrg-drop-2026';
define('DROP_DIR', WP_CONTENT_DIR . '/uploads/inbox');   /* 2026-10-31：/root/data 是 700，www 用户写不进 ✗ */
define('DROP_MAXMB', 200);

$k = (string) ($_GET['k'] ?? '');
$ok = hash_equals($KEY, $k);

$msg = '';
$msgType = 'ok';

/* 上传 */
if ($ok && !empty($_FILES['f'])) {
    $f = $_FILES['f'];
    if (($f['error'] ?? 1) === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name'])) {
        if ($f['size'] > DROP_MAXMB * 1048576) {
            $msg = '太大了（上限 ' . DROP_MAXMB . ' MB）';
            $msgType = 'err';
        } else {
            $day = date('Ymd');
            $dir = DROP_DIR . '/' . $day;
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $name = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', basename((string) $f['name']));
            if ($name === '' || $name === '_') $name = 'file_' . date('His');
            $target = $dir . '/' . $name;
            if (file_exists($target)) {
                $p = pathinfo($name);
                $target = $dir . '/' . ($p['filename'] ?? 'f') . '_' . date('His') . '.' . ($p['extension'] ?? 'bin');
            }
            if (move_uploaded_file($f['tmp_name'], $target)) {
                $msg = '已收到：' . basename($target) . '（' . round(filesize($target) / 1048576, 2) . ' MB）';
            } else {
                $msg = '写入失败';
                $msgType = 'err';
            }
        }
    } else {
        $msg = '上传失败（错误码 ' . (int) ($f['error'] ?? -1) . '）';
        $msgType = 'err';
    }
}

/* 列表（最近 40 个） */
$files = [];
if ($ok && is_dir(DROP_DIR)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(DROP_DIR, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $fi) {
        if (!$fi->isFile()) continue;
        $files[] = ['path' => $fi->getPathname(), 'name' => $fi->getFilename(),
                    'size' => $fi->getSize(), 'time' => $fi->getMTime()];
    }
    usort($files, fn($a, $b) => $b['time'] <=> $a['time']);
    $files = array_slice($files, 0, 40);
}

/* 下载：?dl=文件名（只在 inbox 里找，防目录穿越） */
if ($ok && !empty($_GET['dl'])) {
    $want = basename((string) $_GET['dl']);
    if (is_dir(DROP_DIR)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(DROP_DIR, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $fi) {
            if ($fi->isFile() && $fi->getFilename() === $want) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . rawurlencode($want) . '"');
                header('Content-Length: ' . $fi->getSize());
                readfile($fi->getPathname());
                exit;
            }
        }
    }
    $msg = '没找到这个文件'; $msgType = 'err';
}

$base = esc_url_raw(remove_query_arg('k'));
?>
<!doctype html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>文件投递 · CRRG</title>
<style>
  body{margin:0;background:#0a0a0c;color:#e8e6e2;font:15px/1.7 -apple-system,"PingFang SC","Microsoft Yahei",sans-serif;padding:18px}
  h1{font-size:17px;letter-spacing:2px;margin:0 0 4px;color:#c9c6c0}
  .sub{font-size:12px;color:#7a7a80;margin-bottom:18px}
  .box{border:1px solid #26262e;background:#101014;padding:16px;margin-bottom:16px}
  input[type=file]{width:100%;color:#bbb;font-size:14px;padding:10px 0}
  button{width:100%;padding:13px;background:#7d1a1a;color:#fff;border:0;font-size:15px;letter-spacing:2px;margin-top:10px}
  button:active{background:#5c1212}
  .msg{padding:10px 12px;margin-bottom:14px;font-size:13px;border-left:2px solid #4a7d4a;background:#0e1410;color:#b8d8b8}
  .msg.err{border-left-color:#7d1a1a;background:#170c0c;color:#e0a8a8}
  ul{list-style:none;padding:0;margin:0}
  li{padding:9px 0;border-bottom:1px solid #1c1c22;font-size:13px}
  li a{color:#9fd;text-decoration:none;word-break:break-all}
  li .m{color:#666;font-size:11px;margin-left:6px}
  .empty{color:#666;font-size:13px}
</style>
</head>
<body>
<?php if (!$ok): ?>
  <h1>文件投递</h1>
  <div class="sub">需要访问密钥</div>
  <div class="box">
    <form method="get">
      <input type="text" name="k" placeholder="输入密钥" style="width:100%;padding:10px;background:#0a0a0c;border:1px solid #26262e;color:#ddd">
      <button type="submit">进入</button>
    </form>
  </div>
<?php else: ?>
  <h1>文件投递</h1>
  <div class="sub">传上来就进服务器 · 上限 <?php echo DROP_MAXMB; ?> MB · 落盘 /root/data/inbox</div>

  <?php if ($msg): ?><div class="msg <?php echo $msgType === 'err' ? 'err' : ''; ?>"><?php echo esc_html($msg); ?></div><?php endif; ?>

  <div class="box">
    <form method="post" enctype="multipart/form-data">
      <input type="file" name="f" required>
      <button type="submit">上传</button>
    </form>
  </div>

  <h1 style="font-size:14px">最近收到的</h1>
  <?php if (!$files): ?>
    <div class="empty">还没有文件。</div>
  <?php else: ?>
    <ul>
      <?php foreach ($files as $f): ?>
        <li>
          <a href="<?php echo esc_url(add_query_arg(['dl' => urlencode($f['name']), 'k' => $k], '') ?: ''); ?>"><?php echo esc_html($f['name']); ?></a>
          <span class="m"><?php echo round($f['size'] / 1048576, 2); ?> MB · <?php echo date('m-d H:i', $f['time']); ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endif; ?>
</body>
</html>
