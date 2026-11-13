<?php
/* Template Name: 河洛 */
if (!is_user_logged_in()) { wp_redirect(wp_login_url()); exit; }

$user_id    = get_current_user_id();
$rank       = crrg_get_rank($user_id);
$can_access = in_array($rank, ['advisor', 'deputy', 'chairman'], true);
$rank_data  = crrg_get_rank_data($rank);
$cur        = wp_get_current_user();

get_header();

if (!$can_access) : ?>
<div class="gov-main"><div class="gov-content" style="text-align:center;padding:80px 20px;">
  <div style="font-size:48px;margin-bottom:16px;">🔒</div>
  <h2 style="color:#C41230;">权限不足，无法访问</h2>
  <p style="color:#999;">河洛仅限首席顾问及以上级别访问。</p>
  <a href="/" style="color:#1B3A5C;">返回首页</a>
</div></div>
<?php get_footer(); exit; endif;

$ajax_url = admin_url('admin-ajax.php');
$nonce    = wp_create_nonce('crrg_heluo');
$avatar   = get_stylesheet_directory_uri() . '/assets/heluo.png';
?>

<style>
:root{
  --h-bg:#000; --h-panel:#0a0000; --h-line:#4d0a0a;
  --h-accent:#ff2b2b; --h-accent-dim:#a01a1a; --h-white:#e9e9e9;
  --h-red:#ff3b57; --h-cyan:#22d3ee;
}
.heluo-wrap{position:relative;max-width:1240px;margin:0 auto;padding:26px 16px;
  font-family:"JetBrains Mono","Courier New",Consolas,monospace;color:var(--h-accent);background:#000;}
.heluo-wrap *{box-sizing:border-box;}
.heluo-wrap::after{content:"";position:absolute;inset:0;pointer-events:none;z-index:5;
  background:repeating-linear-gradient(0deg,rgba(255,43,43,.05) 0 1px,transparent 1px 3px);
  animation:hScan 8s linear infinite;}
@keyframes hScan{from{background-position-y:0}to{background-position-y:120px}}

.h-head{display:flex;align-items:center;gap:14px;flex-wrap:wrap;
  padding:12px 18px;border:1px solid var(--h-line);background:var(--h-panel);
  letter-spacing:.12em;font-size:12px;text-transform:uppercase;}
.h-dot{width:9px;height:9px;border-radius:50%;background:var(--h-accent);
  box-shadow:0 0 10px var(--h-accent);animation:hBlink 1.6s infinite;}
@keyframes hBlink{0%,100%{opacity:1}50%{opacity:.25}}
.h-title{color:var(--h-accent);font-weight:700;position:relative;}
.h-title::before,.h-title::after{content:attr(data-t);position:absolute;left:0;top:0;}
.h-title::before{color:var(--h-red);animation:hShift 3.4s infinite steps(1);}
.h-title::after{color:var(--h-cyan);animation:hShift2 4.1s infinite steps(1);}
@keyframes hShift{0%,92%,100%{transform:none;opacity:0}93%{transform:translate(-2px,1px);opacity:.9}95%{transform:translate(2px,-1px);opacity:.7}}
@keyframes hShift2{0%,88%,100%{transform:none;opacity:0}89%{transform:translate(2px,-1px);opacity:.8}91%{transform:translate(-1px,1px);opacity:.6}}
.h-spacer{flex:1}
.h-tag{border:1px solid var(--h-line);padding:3px 9px;color:var(--h-accent-dim);}
.h-tag b{color:var(--h-accent);}

.h-body{display:grid;grid-template-columns:minmax(280px,420px) 1fr;gap:18px;margin-top:18px;}
@media(max-width:860px){.h-body{grid-template-columns:1fr}}

.h-stage{position:relative;border:1px solid var(--h-line);
  background:radial-gradient(ellipse at 50% 40%,rgba(255,43,43,.14),transparent 65%),#000;
  overflow:hidden;height:640px;max-height:78vh;}
.h-avatar{display:block;width:100%;height:100%;object-fit:contain;object-position:center;
  position:relative;z-index:2;animation:hFloat 7s ease-in-out infinite;
  filter:drop-shadow(0 0 22px rgba(255,43,43,.42));}
@keyframes hFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}
.h-avatar.g{position:absolute;inset:0;z-index:3;opacity:0;pointer-events:none;mix-blend-mode:screen;}
.h-avatar.ga{filter:sepia(1) hue-rotate(-40deg) saturate(9);animation:hGa 5.5s infinite steps(1);}
.h-avatar.gb{filter:sepia(1) hue-rotate(150deg) saturate(7);animation:hGb 7.3s infinite steps(1);}
@keyframes hGa{0%,100%{opacity:0;transform:none;clip-path:inset(0 0 100% 0)}
 4%{opacity:.85;transform:translate(-8px,2px);clip-path:inset(14% 0 60% 0)}
 6%{opacity:.85;transform:translate(6px,-2px);clip-path:inset(52% 0 28% 0)}
 8%{opacity:0} 55%{opacity:0}
 57%{opacity:.7;transform:translate(5px,3px);clip-path:inset(72% 0 10% 0)}
 59%{opacity:0}}
@keyframes hGb{0%,100%{opacity:0;transform:none;clip-path:inset(0 0 100% 0)}
 31%{opacity:.7;transform:translate(7px,-2px);clip-path:inset(30% 0 48% 0)}
 33%{opacity:0} 78%{opacity:0}
 80%{opacity:.6;transform:translate(-6px,3px);clip-path:inset(8% 0 74% 0)}
 82%{opacity:0}}
.h-corner{position:absolute;width:16px;height:16px;border:2px solid var(--h-accent);z-index:4;opacity:.55;}
.h-corner.tl{top:8px;left:8px;border-right:0;border-bottom:0}
.h-corner.tr{top:8px;right:8px;border-left:0;border-bottom:0}
.h-corner.bl{bottom:8px;left:8px;border-right:0;border-top:0}
.h-corner.br{bottom:8px;right:8px;border-left:0;border-top:0}
.h-meta{position:absolute;left:12px;bottom:10px;z-index:4;font-size:11px;letter-spacing:.1em;
  color:var(--h-accent-dim);text-shadow:0 0 6px #000;}
.h-meta b{color:var(--h-accent);}

.h-console{border:1px solid var(--h-line);background:var(--h-panel);display:flex;flex-direction:column;
  height:640px;max-height:78vh;}
.h-log{flex:1;overflow-y:auto;padding:18px;display:flex;flex-direction:column;gap:14px;
  scrollbar-width:thin;scrollbar-color:var(--h-accent-dim) transparent;}
.h-log::-webkit-scrollbar{width:8px}
.h-log::-webkit-scrollbar-track{background:#000}
.h-log::-webkit-scrollbar-thumb{background:var(--h-line)}
.h-log::-webkit-scrollbar-thumb:hover{background:var(--h-accent-dim)}
.h-msg{max-width:92%;line-height:1.75;font-size:14px;white-space:pre-wrap;word-break:break-word;color:var(--h-accent);}
.h-msg.u{align-self:flex-end;text-align:right;color:var(--h-white);
  border-right:2px solid var(--h-white);padding-right:12px;}
.h-msg.a{align-self:flex-start;color:var(--h-accent);
  border-left:2px solid var(--h-accent-dim);padding-left:12px;}
.h-msg.a::before{content:"heluo ▸ ";color:var(--h-accent-dim);font-weight:700;}
.h-msg.s{align-self:flex-start;color:var(--h-accent-dim);font-size:12px;letter-spacing:.1em;}
.h-msg.e{align-self:flex-start;color:var(--h-red);font-size:13px;border-left:2px solid var(--h-red);padding-left:12px;}
.h-cursor{display:inline-block;width:8px;height:15px;background:var(--h-accent);
  vertical-align:-2px;animation:hBlink .9s steps(1) infinite;}
.h-inputrow{border-top:1px solid var(--h-line);display:flex;gap:10px;align-items:flex-start;
  padding:12px;background:#000;}
.h-prompt{color:var(--h-accent);font-weight:700;font-size:15px;line-height:46px;flex-shrink:0;}
#h-in{flex:1;background:#000;border:1px solid var(--h-line);color:var(--h-accent);
  font-family:inherit;font-size:14px;padding:11px 13px;resize:none;min-height:46px;max-height:150px;outline:none;caret-color:var(--h-accent);}
#h-in:focus{border-color:var(--h-accent);box-shadow:0 0 0 1px var(--h-accent),0 0 18px rgba(255,43,43,.24) inset;}
#h-in::placeholder{color:#5a1a1a;}
#h-send{background:#000;color:var(--h-accent);border:1px solid var(--h-accent);
  font-family:inherit;font-size:13px;letter-spacing:.16em;padding:0 22px;cursor:pointer;font-weight:700;height:46px;}
#h-send:hover{background:var(--h-accent);color:#000;box-shadow:0 0 18px rgba(255,43,43,.65)}
#h-send:disabled{opacity:.35;cursor:not-allowed;box-shadow:none;background:#000;color:var(--h-accent)}
.h-foot{margin-top:12px;font-size:11px;letter-spacing:.1em;color:var(--h-accent-dim);display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;}
</style>

<div class="heluo-wrap">
  <div class="h-head">
    <span class="h-dot"></span>
    <span class="h-title" data-t="HELUO">HELUO</span>
    <span style="color:var(--h-accent-dim)">// 推演调度系统</span>
    <span class="h-spacer"></span>
    <span class="h-tag">SECURE CHANNEL <b>ACTIVE</b></span>
    <span class="h-tag"><?php echo esc_html($rank_data['icon'] . ' ' . $rank_data['name']); ?> · <?php echo esc_html($cur->display_name); ?></span>
  </div>

  <div class="h-body">
    <div class="h-stage">
      <img class="h-avatar" src="<?php echo esc_url($avatar); ?>" alt="河洛">
      <img class="h-avatar g ga" src="<?php echo esc_url($avatar); ?>" aria-hidden="true" alt="">
      <img class="h-avatar g gb" src="<?php echo esc_url($avatar); ?>" aria-hidden="true" alt="">
      <span class="h-corner tl"></span><span class="h-corner tr"></span>
      <span class="h-corner bl"></span><span class="h-corner br"></span>
      <div class="h-meta">ID: <b>HELUO-01</b> · 状态: <b>在线</b> · 记忆: <b>共享</b></div>
    </div>

    <div class="h-console">
      <div class="h-log" id="h-log">
        <div class="h-msg s">▌ 通道已建立 · 与 QQ 端共享同一份记忆</div>
        <div class="h-msg a">河洛已就位。有什么要问的？</div>
      </div>
      <div class="h-inputrow">
        <span class="h-prompt">&gt;</span>
        <textarea id="h-in" rows="1" placeholder="输入指令，Enter 发送（Shift+Enter 换行）…"></textarea>
        <button id="h-send">发送</button>
      </div>
    </div>
  </div>

  <div class="h-foot">
    <span>HELUO / CRRG-MOEBIUS.COM</span>
    <span>仅限首席顾问及以上 · 所有对话进入共享记忆</span>
  </div>
</div>

<script>
(function(){
  var AJAX  = <?php echo json_encode($ajax_url); ?>;
  var NONCE = <?php echo json_encode($nonce); ?>;
  var log   = document.getElementById('h-log');
  var input = document.getElementById('h-in');
  var btn   = document.getElementById('h-send');
  var busy  = false;

  // 历史时间戳样式
  var _st = document.createElement('style');
  _st.textContent = '.h-ts{opacity:.35;font-size:11px;margin-left:8px;letter-spacing:.5px}';
  document.head.appendChild(_st);

  function scroll(){ log.scrollTop = log.scrollHeight; }
  function add(cls, text){
    var d = document.createElement('div');
    d.className = 'h-msg ' + cls;
    d.textContent = text;
    log.appendChild(d); scroll();
    return d;
  }
  function typeOut(el, text, done){
    var i = 0, cur = document.createElement('span');
    cur.className = 'h-cursor';
    el.textContent = '';
    var t = document.createElement('span');
    el.appendChild(t); el.appendChild(cur);
    var timer = setInterval(function(){
      t.textContent = text.slice(0, ++i);
      scroll();
      if (i >= text.length){ clearInterval(timer); cur.remove(); if(done) done(); }
    }, 18);
  }

  function send(){
    var text = input.value.trim();
    if (!text || busy) return;
    add('u', text);
    input.value = ''; input.style.height = 'auto';
    busy = true; btn.disabled = true;
    var loading = add('s', '▌ 解析中…');

    var fd = new FormData();
    fd.append('action', 'crrg_heluo_chat');
    fd.append('_wpnonce', NONCE);
    fd.append('message', text);

    fetch(AJAX, { method:'POST', body: fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(j){
        loading.remove();
        if (j && j.success){
          var m = add('a', '');
          typeOut(m, j.data.reply || '(空回复)');
        } else {
          add('e', '▌ ' + ((j && j.data && j.data.msg) || '未知错误'));
        }
      })
      .catch(function(err){ loading.remove(); add('e', '▌ 连接失败：' + err.message); })
      .finally(function(){ busy = false; btn.disabled = false; input.focus(); });
  }

  // 回读本站留档：刷新后恢复本账户自己的历史（不进 AI 上下文）
  function loadHistory(){
    var fd = new FormData();
    fd.append('action', 'crrg_heluo_history');
    fd.append('_wpnonce', NONCE);
    fetch(AJAX, { method:'POST', body: fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j || !j.success || !j.data.items.length) return;
        j.data.items.forEach(function(it){
          var u = add('u', it.q);
          var ts = document.createElement('span');
          ts.className = 'h-ts'; ts.textContent = it.t;
          u.appendChild(ts);
          add('a', it.a);
        });
        add('s', '▌ 本站留档 ' + j.data.items.length + ' 条（上限 ' + j.data.keep + '）');
      })
      .catch(function(){});
  }
  loadHistory();

  btn.addEventListener('click', send);
  input.addEventListener('keydown', function(e){
    if (e.key === 'Enter' && !e.shiftKey){ e.preventDefault(); send(); }
  });
  input.addEventListener('input', function(){
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 150) + 'px';
  });
  input.focus();

  // 整页背景转黑（仅本模板）
  document.body.style.background = '#000';
  ['.site', '.site-content', '.ast-container', '#content', '#main', 'main',
   '.entry-content', '.gov-main', '.gov-content', 'article'].forEach(function(sel){
    document.querySelectorAll(sel).forEach(function(el){ el.style.background = '#000'; });
  });
})();
</script>
<?php get_footer(); ?>
