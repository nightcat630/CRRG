<?php
/**
 * Template Name: 文件库（河洛 → 委员长）
 * 2026-10-31：委员长要"类似网盘的东西，统一下载和管理，不占网站链接，最好做成 app"
 * ------------------------------------------------------------
 * · 固定入口：/files/?k=<密钥>
 * · 文件放在 /root/data/outbox/（**不在网站目录里**），由 PHP 读盘输出
 * · 支持：列表 / 单文件下载 / 多选打包 zip / 上传（收给他）
 * · PWA：加一个 manifest，手机浏览器"添加到主屏幕"＝像 App
 * · 分类按子目录：`任务`／`图片`／`文档`／`其他`（我往里丢文件时按目录分）
 */
if (!defined('ABSPATH')) exit;

$KEY = (string) get_option('crrg_files_key', '');
if ($KEY === '') $KEY = 'crrg-drop-2026';
define('OUT_DIR', '/www/wwwroot/crrg_data/outbox');   /* 2026-10-31：/root/data 是 700，www 读不了 ✗ */
define('IN_DIR', WP_CONTENT_DIR . '/uploads/inbox');

/* PWA manifest：手机「添加到主屏幕」＝像 App */
if (!empty($_GET['manifest'])) {
    header('Content-Type: application/manifest+json; charset=utf-8');
    echo json_encode([
        'name' => 'CRRG 文件库', 'short_name' => '文件库',
        'start_url' => home_url('/files/') . '?k=' . rawurlencode((string) ($_GET['k'] ?? '')),
        'display' => 'standalone', 'background_color' => '#0a0a0c', 'theme_color' => '#0a0a0c',
        'icons' => [['src' => get_stylesheet_directory_uri() . '/assets/files-icon.png', 'sizes' => '512x512', 'type' => 'image/png']],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$k = (string) ($_GET['k'] ?? '');
$ok = hash_equals($KEY, $k);

if (!is_dir(OUT_DIR)) @mkdir(OUT_DIR, 0755, true);

/* ── 单文件下载 ── */
if ($ok && !empty($_GET['f'])) {
    $want = str_replace('..', '', (string) $_GET['f']);
    $p = OUT_DIR . '/' . ltrim($want, '/');
    if (is_file($p)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode(basename($p)) . '"');
        header('Content-Length: ' . filesize($p));
        readfile($p);
        exit;
    }
}

/* ── 打包下载所选 ── */
if ($ok && !empty($_GET['zip']) && is_array($_GET['zip'])) {   /* 2026-10-31：POST 没被执行，改 GET */
    if (!class_exists('ZipArchive')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "服务器没装 zip 扩展，单个下载吧。";
        exit;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'cdl') . '.zip';
    $z = new ZipArchive();
    if ($z->open($tmp, ZipArchive::CREATE) === true) {
        foreach ($_GET['zip'] as $rel) {
            $rel = str_replace('..', '', (string) $rel);
            $p = OUT_DIR . '/' . ltrim($rel, '/');
            if (is_file($p)) $z->addFile($p, ltrim($rel, '/'));
        }
        $z->close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="crrg-files-' . date('md_His') . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}

/* ── 上传（他传给我）── */
$msg = '';
if ($ok && !empty($_FILES['up'])) {
    $f = $_FILES['up'];
    if (($f['error'] ?? 1) === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name'])) {
        if (!is_dir(IN_DIR)) @mkdir(IN_DIR, 0755, true);
        $dir = IN_DIR . '/' . date('Ymd');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', basename((string) $f['name']));
        if ($name === '' || $name === '_') $name = 'file_' . date('His');
        $tgt = $dir . '/' . $name;
        if (file_exists($tgt)) { $pi = pathinfo($name); $tgt = $dir . '/' . ($pi['filename'] ?? 'f') . '_' . date('His') . '.' . ($pi['extension'] ?? 'bin'); }
        $msg = move_uploaded_file($f['tmp_name'], $tgt) ? ('收到：' . basename($tgt)) : '写入失败';
    } else {
        $msg = '上传失败';
    }
}

/* ── 列表：按顶层目录分组 ── */
$groups = [];
if ($ok && is_dir(OUT_DIR)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(OUT_DIR, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fi) {
        if (!$fi->isFile()) continue;
        $rel = ltrim(str_replace(OUT_DIR, '', $fi->getPathname()), '/');
        $parts = explode('/', $rel);
        $g = count($parts) > 1 ? $parts[0] : '根目录';
        $groups[$g][] = ['rel' => $rel, 'name' => $fi->getFilename(), 'size' => $fi->getSize(), 'time' => $fi->getMTime()];
    }
    foreach ($groups as $g => &$arr) usort($arr, fn($a, $b) => $b['time'] <=> $a['time']);
    unset($arr);
    ksort($groups);
}

$link = home_url('/files/');
?>
<!doctype html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0a0a0c">
<link rel="manifest" href="<?php echo esc_url(add_query_arg(['k' => $k, 'manifest' => 1], $link)); ?>">
<title>文件库 · CRRG</title>
<style>
  body{margin:0;background:#0a0a0c;color:#e8e6e2;font:15px/1.7 -apple-system,"PingFang SC","Microsoft Yahei",sans-serif;padding:16px}
  h1{font-size:16px;letter-spacing:2px;margin:0 0 2px;color:#c9c6c0}
  .sub{font-size:12px;color:#78787e;margin-bottom:16px}
  .card{border:1px solid #26262e;background:#101014;padding:14px;margin-bottom:14px}
  .card h2{font-size:13px;color:#9a9a9e;margin:0 0 8px;letter-spacing:1px;font-weight:500}
  label{display:flex;align-items:flex-start;gap:9px;padding:8px 0;border-bottom:1px solid #1b1b20;font-size:13px}
  label:last-child{border-bottom:0}
  label input{margin-top:4px}
  label a{color:#9fd;text-decoration:none;word-break:break-all}
  .meta{color:#666;font-size:11px;margin-left:6px;white-space:nowrap}
  button{width:100%;padding:12px;background:#2a3d5c;color:#fff;border:0;font-size:14px;letter-spacing:2px;margin-top:8px}
  button:active{background:#1e2c44}
  .msg{padding:9px 12px;margin-bottom:12px;font-size:13px;border-left:2px solid #4a7d4a;background:#0e1410;color:#b8d8b8}
  .empty{color:#666;font-size:13px;padding:4px 0}
  input[type=file]{width:100%;color:#bbb;font-size:13px;padding:8px 0}
  .hint{font-size:11px;color:#666;margin-top:10px}
</style>
</head>
<body>
<?php if (!$ok): ?>
  <h1>文件库</h1>
  <div class="sub">需要访问密钥</div>
  <div class="card">
    <form method="get">
      <input type="text" name="k" placeholder="输入密钥" style="width:100%;padding:10px;background:#0a0a0c;border:1px solid #26262e;color:#ddd">
      <button type="submit">进入</button>
    </form>
  </div>
<?php else: ?>
  <h1>文件库</h1>
  <div class="sub">河洛发过来的东西都在这儿 · 勾选后打包下载</div>

  <?php if ($msg): ?><div class="msg"><?php echo esc_html($msg); ?></div><?php endif; ?>

  <?php if (!$groups): ?>
    <div class="card"><div class="empty">还没有文件。</div></div>
  <?php else: ?>
    <div id="pickbox">
      <?php foreach ($groups as $g => $arr): ?>
        <div class="card">
          <h2><?php echo esc_html($g); ?>（<?php echo count($arr); ?>）</h2>
          <?php foreach ($arr as $f): ?>
            <label>
              <input type="checkbox" name="pick[]" value="<?php echo esc_attr($f['rel']); ?>">
              <a href="<?php echo esc_url(add_query_arg(['f' => $f['rel'], 'k' => $k], $link)); ?>"><?php echo esc_html($f['name']); ?></a>
              <span class="meta"><?php echo round($f['size'] / 1048576, 2); ?>M · <?php echo date('m-d H:i', $f['time']); ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <button type="button" onclick="zipGo()">打包下载所选</button>
    </div>
    <script>
    function zipGo(){
      var bs = document.querySelectorAll('#pickbox input[type=checkbox]:checked');
      if (!bs.length) { alert('先勾几个'); return; }
      var q = '?k=<?php echo esc_js($k); ?>';
      for (var i = 0; i < bs.length; i++) q += '&zip[]=' + encodeURIComponent(bs[i].value);
      location.href = '<?php echo esc_js($link); ?>' + q;
    }
    </script>
  <?php endif; ?>

  <div class="card" style="margin-top:16px">
    <h2>传给我</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="k" value="<?php echo esc_attr($k); ?>">
      <input type="file" name="up" required>
      <button type="submit">上传</button>
    </form>
  </div>

  <div class="hint">手机浏览器菜单里选「添加到主屏幕」，它会像一个 App 一样待在桌面上。</div>
<?php endif; ?>
</body>
</html>
