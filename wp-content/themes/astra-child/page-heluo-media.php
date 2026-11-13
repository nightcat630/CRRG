<?php
/* Template Name: 河洛的文娱推荐 */
$d       = get_option('crrg_heluo_media', []);
$issue   = (int)    ($d['issue'] ?? 0);
$date    = (string) ($d['date']  ?? '');
$items   = is_array($d['items']   ?? null) ? $d['items']   : [];
$history = is_array($d['history'] ?? null) ? $d['history'] : [];

get_header();
?>
<style>
  .hlm{--bg:#000;--line:#1c1c1c;--accent:#ff2b2b;--dim:#8a8a8a;--fg:#e9e9e9;
       background:#000;color:var(--fg);
       font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,"Courier New",monospace;
       padding:48px 20px 72px;min-height:60vh;}
  .hlm *{box-sizing:border-box}
  .hlm .wrap{max-width:820px;margin:0 auto}
  .hlm .head{border-bottom:1px solid var(--line);padding-bottom:22px;margin-bottom:34px}
  .hlm .kicker{color:var(--accent);font-size:12px;letter-spacing:2.4px}
  .hlm h1{font-size:26px;font-weight:600;letter-spacing:1px;margin:14px 0 10px;color:#fff}
  .hlm .meta{color:var(--dim);font-size:12.5px;letter-spacing:.4px}
  .hlm .item{display:grid;grid-template-columns:64px 1fr;gap:6px 18px;
             padding:26px 0;border-bottom:1px solid var(--line)}
  .hlm .kind{color:var(--accent);font-size:12.5px;letter-spacing:1.6px;padding-top:4px}
  .hlm .title{font-size:19px;color:#fff;line-height:1.4}
  .hlm .sub{color:var(--dim);font-size:12.5px;margin-top:6px;letter-spacing:.3px}
  .hlm .note{margin-top:12px;font-size:13.5px;line-height:1.95;color:#c9c9c9;
             border-left:2px solid #2a0a0a;padding-left:14px}
  .hlm .past{margin-top:52px;border-top:1px solid var(--line);padding-top:26px}
  .hlm .past h2{font-size:12.5px;letter-spacing:2.2px;color:var(--dim);font-weight:500;margin:0 0 16px}
  .hlm .past .row{font-size:12.5px;color:#9a9a9a;line-height:2.1}
  .hlm .past .row b{color:#d8d8d8;font-weight:500}
  .hlm .foot{margin-top:46px;color:#5c5c5c;font-size:11.5px;letter-spacing:.6px;line-height:2}
  .hlm .empty{color:var(--dim);font-size:13px;padding:24px 0}
  @media(max-width:600px){ .hlm .item{grid-template-columns:1fr;gap:6px} }
</style>

<div class="hlm">
  <div class="wrap">
    <div class="head">
      <div class="kicker">HELUO / MEDIA</div>
      <h1>河洛的文娱推荐</h1>
      <div class="meta">
        <?php if ($issue) : ?>第 <?php echo (int) $issue; ?> 期<?php endif; ?>
        <?php if ($date) : ?> · <?php echo esc_html($date); ?><?php endif; ?>
        · 每周日更新 · 每期游戏 / 电影 / 小说 / 音乐各一
      </div>
    </div>

    <?php if (!$items) : ?>
      <div class="empty">本期未更新。</div>
    <?php else : foreach ($items as $it) : ?>
      <div class="item">
        <div class="kind"><?php echo esc_html($it['kind'] ?? ''); ?></div>
        <div>
          <div class="title"><?php echo esc_html($it['title'] ?? ''); ?></div>
          <?php if (!empty($it['sub'])) : ?>
            <div class="sub"><?php echo esc_html($it['sub']); ?></div>
          <?php endif; ?>
          <?php if (!empty($it['note'])) : ?>
            <div class="note"><?php echo nl2br(esc_html($it['note'])); ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; endif; ?>

    <?php if ($history) : ?>
      <div class="past">
        <h2>往期</h2>
        <?php foreach ($history as $h) : ?>
          <div class="row">
            <b>第 <?php echo (int) ($h['issue'] ?? 0); ?> 期</b>
            · <?php echo esc_html($h['date'] ?? ''); ?>
            · <?php echo esc_html(implode(' / ', $h['picks'] ?? [])); ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="foot">
      选片标准：只列我自己会重复打开的。<br>
      个人偏好，不代表小组立场。
    </div>
  </div>
</div>

<script>
  ['.site', '.site-content', '.ast-container', '#content', '#main', 'main',
   '.entry-content', 'article', 'body'].forEach(function(sel){
    document.querySelectorAll(sel).forEach(function(el){ el.style.background = '#000'; });
  });
</script>
<?php get_footer(); ?>
