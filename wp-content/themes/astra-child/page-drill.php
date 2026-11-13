<?php
/* Template Name: 虚拟演习 */
if (!is_user_logged_in()) { wp_redirect(wp_login_url()); exit; }

$uid   = get_current_user_id();
$ctx   = crrg_drill_ctx($uid);
$s     = crrg_drill_state($uid);
$pc    = crrg_drill_pc($uid);
$view  = crrg_drill_view($s, $ctx, $uid, $pc);
$ajax  = admin_url('admin-ajax.php');
$nonce = wp_create_nonce('crrg_drill');

/* 本页自成一份 HTML：不带主题页眉、导航、页脚与侧栏（其他页面照旧） */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">

<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html(get_the_title() ?: '虚拟演习'); ?> · <?php bloginfo('name'); ?></title>
<?php wp_head(); ?>
<style>
  html,body{margin:0;padding:0;background:#000;overflow-x:hidden}
  #wpadminbar,.site-header,.site-footer,.ast-mobile-header-wrap,.main-header-menu-toggle,
  #ast-mobile-popup,.ast-above-header,.ast-below-header,.site-primary-footer-wrap,
  .widget-area,aside{display:none !important}

  /* ── 窗口层（2026-10-01：黑底终端风 ＋ **纯白框**；选项也用白框圈出，与正文区分） ──
   要点：① 底色/字体沿用主界面变量（跟着主题色 ✓）
        ② **所有"结构线"一律纯白**（外框 2px ✓、标题栏下沿 ✓、底栏上沿 ✓）
        ③ **窗口里的选项＝白框按钮** ✓（正文只是文字 ✗ → 一眼能分出哪个能点 ✓）
   注：图层挂在 body 下（在 .drl 之外 ✗）→ 变量定义在**图层自己**身上，并全部留 #fff 兜底 ✓ */
  .drl-wins { position: fixed; inset: 0; pointer-events: none; z-index: 60;
    --wline:#ffffff; --wline-soft:rgba(255,255,255,.65); --wline-faint:rgba(255,255,255,.35); }
  .drl-win { position: absolute; pointer-events: auto; min-width: 300px; max-width: 620px;
    background:#000; color: var(--fg);
    font-family: inherit; font-size: 13px; line-height: 1.55;
    border: 2px solid var(--wline, #fff); border-radius: 2px;
    box-shadow: 0 0 10px rgba(255,255,255,.10), 0 14px 36px rgba(0,0,0,.8); overflow: hidden; }
  .drl-win .w-hd { display:flex; align-items:center; gap:8px; cursor: move; user-select:none;
    padding: 6px 10px 6px 8px; background:#000;
    border-bottom: 1px solid var(--wline, #fff); }
  .drl-win .w-hd .w-mk { color: var(--wline, #fff); font-size: 12px; }
  .drl-win .w-hd b { flex: 1; font-weight: 500; font-size: 11.5px; letter-spacing: 1.8px;
    color: var(--wline, #fff); text-transform: uppercase; }
  .drl-win .w-cl { cursor: pointer; color: var(--wline, #fff); font-size: 12px; letter-spacing:1px; }
  .drl-win .w-cl:hover { text-shadow: 0 0 6px rgba(255,255,255,.85); }
  .drl-win .w-bd { padding: 10px 10px 11px; max-height: 48vh; overflow: auto; }
  .drl-win .w-bd::-webkit-scrollbar { width: 8px; }
  .drl-win .w-bd::-webkit-scrollbar-thumb { background: var(--wline-faint, rgba(255,255,255,.35)); }
  .drl-win .w-bd::-webkit-scrollbar-track { background:#000; }
  /* 说明与正文：**不加框**（与下面"选项"形成对比 ✓） */
  /* 2026-10-22：对话窗口里的**立绘**（点阵 mask，随主色变） */
  .drl-win .w-face{width:92px;height:92px;margin:0 auto 10px;display:block;background:var(--accent);
    opacity:.9;-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-position:center;mask-position:center;
    -webkit-mask-size:contain;mask-size:contain;}
  .drl-win .w-face.mini{width:34px;height:34px;margin:0 6px 0 0;display:inline-block;vertical-align:-10px;}
    /* 2026-10-22：**固定立绘位**（委员长："直接预留出立绘的位置"）
     永远存在 ✓ 有图放图、没图放占位 ✗ 不再"有才插" */
  .drl-win .w-portrait{width:140px;height:140px;margin:2px auto 10px;position:relative;
    border:1px solid var(--line2);background:#070707;display:flex;align-items:center;justify-content:center;}
  .drl-win .w-portrait img{width:100%;height:100%;object-fit:contain;display:block;image-rendering:pixelated;}
  .drl-win .w-portrait .w-ph{font-size:11px;color:var(--dim);letter-spacing:2px;}
  .drl-win .w-portrait::after{content:'';position:absolute;inset:0;pointer-events:none;
    background:linear-gradient(transparent 60%,rgba(0,0,0,.55));}
  /* 2026-10-22：**版式模板**（layout 1/2/3） */
  .drl-win .w-l2{display:grid;grid-template-columns:150px 1fr;gap:12px;align-items:start;}
  .drl-win .w-l2 .w-portrait{margin:2px 0 0;}
  .drl-win .w-l2 .w-main{min-width:0;}
  /* 2026-10-22 重写：对话窗口新版式（左立绘栏 + 右内容，名字牌） */
  .drl-win .w-dlg{display:grid;grid-template-columns:132px 1fr;gap:0;}
  .drl-win .w-dlg .w-port{background:#050505;border-right:1px solid var(--line2);
    display:flex;align-items:flex-start;justify-content:center;padding:10px 0;}
  .drl-win .w-dlg .w-port img{width:112px;height:112px;object-fit:contain;image-rendering:pixelated;display:block;}
  .drl-win .w-dlg .w-port .w-ph{font-size:10px;color:var(--dim);letter-spacing:3px;padding-top:46px;}
  .drl-win .w-dlg .w-main2{padding:8px 10px 4px;min-width:0;}
  .drl-win .w-nametag{display:flex;align-items:baseline;gap:8px;margin-bottom:8px;padding-bottom:6px;
    border-bottom:1px solid var(--line);}
  .drl-win .w-nametag b{font-size:14px;letter-spacing:2px;color:var(--accent);font-weight:600;}
  .drl-win .w-nametag i{font-style:normal;font-size:10px;color:var(--dim);letter-spacing:1px;}
  .drl-win .w-btnline{border-left:2px solid var(--line2);padding-left:8px;margin-top:3px;}
  .drl-win .w-btnline:hover{border-left-color:var(--accent);}
  /* 2026-10-22：立绘改用**遮罩＋主色**（跟着 data-theme 变 ✓ 不再直出白图 ✗） */
  .drl-win .w-portrait .w-fig2,
  .drl-win .w-port .w-fig2{width:112px;height:112px;object-fit:contain;display:block;image-rendering:pixelated;}
  .drl-win .w-portrait .w-fig{width:112px;height:112px;display:block;background:var(--accent,#ff2b2b);
    opacity:.94;-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;
    -webkit-mask-position:center;mask-position:center;
    -webkit-mask-size:contain;mask-size:contain;}
  .drl-win .w-note { color: var(--dim); font-size: 12px; margin-bottom: 8px; }
  .drl-win .w-tx { white-space: pre-wrap; margin-bottom: 5px; }
  /* ── 窗口里的选项：白框按钮 ✓ 左侧加粗一道，明确"这儿能点" ✓ ── */
  .drl-win .w-bt { display:block; width:100%; text-align:left; margin: 4px 0;
    background:#000; border:1px solid var(--wline, #fff); border-left-width:3px;
    color:#f2f2f2; font-family: inherit; font-size: 12.5px; padding: 7px 10px; cursor: pointer; border-radius: 0;
    transition: background .08s linear; }
  .drl-win .w-bt:hover { background: rgba(255,255,255,.10); color:#fff; }
  .drl-win .w-bt:active { background: rgba(255,255,255,.18); }
  .drl-win .w-bt[disabled] { border-color: var(--wline-faint, rgba(255,255,255,.35));
    color: rgba(255,255,255,.45); cursor: default; background:#000; }
  /* 分类分隔（k=divider）：小标题，不框 ✓ */
  .drl-win .w-dv { margin: 10px 0 5px; font-size: 11px; letter-spacing: 1.6px; color: var(--wline-soft, rgba(255,255,255,.65));
    border-top: 1px dashed var(--wline-faint, rgba(255,255,255,.35)); padding-top: 6px; }
  /* 底栏：窗口 id ＋ 提示（终端状态行感 ✓） */
  .drl-win .w-ft { border-top: 1px solid var(--wline-soft, rgba(255,255,255,.65)); padding: 4px 10px; font-size: 10.5px;
    letter-spacing: 1.2px; color: var(--wline-soft, rgba(255,255,255,.65)); background:#000; }
</style>
</head>
<body <?php body_class('drl-fullscreen'); ?>>
<?php wp_body_open(); ?>
?>
<style>
  .drl{--bg:#000;--fg:#ff2b2b;--ok:#7dd87d;--warn:#c4562e;
       --accent:#ff2b2b;--bright:#ff8a8a;--mid:#c04a4a;--dim:#7a2a2a;--dim2:#9c4a4a;
       --line:#2a0d0d;--line2:#5a1414;--hover:#170203;--panel2:#0d0303;--panel3:#120404;
       background:#000;color:var(--fg);
       font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,"Courier New",monospace;
       padding:32px 16px 56px;min-height:72vh}
  .drl[data-theme="amber"]{--fg:#ffb000;--accent:#ffb000;--bright:#ffd07a;--mid:#c9a24a;--dim:#8a6a1f;
       --dim2:#7a6120;--line:#241a06;--line2:#5a420f;--hover:#150f02;--panel2:#0a0700;--panel3:#0d0a02}
  .drl[data-theme="green"]{--fg:#7dd87d;--accent:#7dd87d;--bright:#c9ffc9;--mid:#6fae6f;--dim:#3f6a3f;
       --dim2:#4f8a4f;--line:#0d2a0d;--line2:#145a14;--hover:#021703;--panel2:#030d03;--panel3:#051005}
  .drl[data-theme="cyan"]{--fg:#56d6d6;--accent:#56d6d6;--bright:#b9f5f5;--mid:#4aa9a9;--dim:#236060;
       --dim2:#2f7a7a;--line:#0a2424;--line2:#125a5a;--hover:#021414;--panel2:#030a0a;--panel3:#041010}
  .drl[data-theme="violet"]{--fg:#b48cff;--accent:#b48cff;--bright:#ded0ff;--mid:#8b6fc0;--dim:#4a3a6a;
       --dim2:#6a54a0;--line:#180d2a;--line2:#3d2160;--hover:#0d0217;--panel2:#06030d;--panel3:#0a0514}
  .drl *{box-sizing:border-box}
  .drl .wrap{max-width:1560px;margin:0 auto}
  .drl .hd{border-bottom:1px solid var(--line);padding-bottom:14px;margin-bottom:14px;
           display:flex;align-items:baseline;gap:14px;flex-wrap:wrap}
  .drl .hd b{font-size:17px;letter-spacing:1px;color:var(--bright)}
  .drl .hd span{font-size:11.5px;color:var(--dim);letter-spacing:1.6px}
  .drl .palette{margin-left:auto;display:flex;align-items:center;gap:6px}
  .drl .palette i{font-style:normal;font-size:11px;color:var(--dim2);letter-spacing:1px}
  .drl .sw{width:16px;height:16px;border-radius:50%;border:1px solid var(--line2);cursor:pointer;padding:0}
  .drl .sw[data-theme="red"]{background:#ff2b2b}
  .drl .sw[data-theme="amber"]{background:#ffb000}
  .drl .sw[data-theme="green"]{background:#7dd87d}
  .drl .sw[data-theme="cyan"]{background:#56d6d6}
  .drl .sw[data-theme="violet"]{background:#b48cff}
  .drl .sw.on{outline:1px solid var(--accent);outline-offset:2px}
  /* 对话头像：白色点阵 PNG 作遮罩，背景填主色 → 随主题变色 ✓ */
  .drl .dlg-face{display:inline-block;width:1.5em;height:1.5em;margin-right:.5em;
    vertical-align:-.34em;background:var(--accent);opacity:.92;
    -webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;
    -webkit-mask-position:center;mask-position:center;
    -webkit-mask-size:contain;mask-size:contain;}
  .drl .bar{font-size:12px;color:var(--dim);border:1px solid var(--line);
            padding:8px 12px;margin-bottom:14px;letter-spacing:.4px;background:#000}
  .drl .grid{display:grid;grid-template-columns:minmax(230px,300px) minmax(430px,1fr) minmax(240px,320px);
             gap:14px;margin-bottom:14px;align-items:start}
  .drl .col{display:flex;flex-direction:column;gap:14px;min-width:0}
  @media(max-width:1240px){
    .drl .grid{grid-template-columns:minmax(220px,1fr) minmax(380px,1.4fr)}
    .drl .grid .col.side3{grid-column:1 / -1}
  }
  @media(max-width:860px){ .drl .grid{grid-template-columns:1fr} }
  .drl .panel{border:1px solid var(--line);padding:12px 14px;background:#000}
  .drl .panel h3{margin:0 0 10px;font-size:11.5px;font-weight:500;letter-spacing:1.8px;color:var(--dim)}
  .drl .ent{display:block;width:100%;text-align:left;background:none;border:none;
            color:var(--fg);font-family:inherit;font-size:13px;padding:5px 0;cursor:pointer}
  .drl .ent:hover{color:#fff;background:var(--hover)}
  .drl .ent i{font-style:normal;color:var(--dim);margin-right:8px;font-size:11.5px}
  .drl .ent.npc i{color:var(--mid)}
  .drl .empty{color:var(--dim2);font-size:12px;padding:4px 0}
  .drl pre.map{margin:0;font-size:11.5px;line-height:1.5;color:var(--mid);white-space:pre;background:#000}
  .drl pre.art{margin:0;font-size:10px;line-height:1.22;color:var(--dim2);white-space:pre;background:#000;overflow-x:auto}
  .drl .exits{margin-top:10px;border-top:1px solid var(--line);padding-top:8px}
  .drl .ex{display:flex;gap:9px;align-items:baseline;width:100%;text-align:left;background:none;
           border:none;color:var(--fg);font-family:inherit;font-size:12.5px;padding:4px 0;cursor:pointer}
  .drl .ex:hover{color:#fff;background:var(--hover)}
  .drl .ex b{color:var(--mid);font-weight:500;min-width:74px;display:inline-block}
  .drl .ex em{font-style:normal;color:var(--dim);font-size:11px;margin-left:auto}
  .drl .ex.locked{color:#7a4a2a}
  .drl .battle{border:1px solid var(--line);padding:12px 14px;margin-bottom:14px;background:#000;display:none}
  .drl .battle.on{display:block}
  .drl .battle h4{margin:0 0 8px;font-size:11.5px;color:var(--dim);letter-spacing:1.4px;font-weight:500}
  .drl .tacwrap{overflow-x:auto;margin-bottom:12px}
  .drl .tac{display:grid;gap:2px;background:#0d0700;padding:2px;border:1px solid var(--line);width:max-content}
  .drl .tac .ax{font-size:10px;color:var(--dim2);display:flex;align-items:center;justify-content:center;min-width:26px;height:22px}
  .drl .tac .c{width:22px;height:22px;border-radius:3px;background:var(--panel2);border:1px solid var(--line);
               font-size:11px;color:#e9e9e9;display:flex;align-items:center;justify-content:center;cursor:default}
  .drl .tac .c.empty{cursor:pointer}
  .drl .tac .c.empty:hover{border-color:var(--line2);background:var(--hover)}
  .drl .tac .c.me{background:var(--accent);color:#000;font-weight:600}
  .drl .tac .c.ally{background:#2f6f6a;color:#eafaf7}
  .drl .tac .c.npc{background:#8c1f1f;color:#ffecec;cursor:pointer}
  .drl .tac .c.cover{background:#3a3a3a}
  .drl .tac .c.cover2{background:#565656}
  .drl .tac .c.lamp{background:#3c3410}
  .drl .tac .c.breaker{background:#1d2f3c}
  .drl .tac .c.oil{background:#3a2a12;border-color:#6b4a0f}
  .drl .tac .c.hydrant{background:#14303a}
  .drl .tac .c.fire{background:#c4562e;color:#fff}
  .drl .tac .c.on{border-color:#fff}
  .drl .battle .sit{font-size:12.5px;line-height:1.9;color:var(--mid);margin-bottom:10px}
  .drl .battle .blog{font-size:11.5px;color:#7faea0;line-height:1.8;margin-bottom:10px}
  .drl .bgrid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .drl .chip{display:inline-block;padding:4px 10px;margin:0 4px 4px 0;font-size:11.5px;
             border:1px solid var(--line);background:var(--panel2);color:var(--fg);font-family:inherit;cursor:pointer}
  .drl .chip:hover{border-color:var(--line2);color:#fff}
  .drl .chip.on{border-color:var(--mid);color:var(--bright)}
  .drl .chip.off{color:#5a4526;cursor:not-allowed}
  .drl .actzone{border:1px solid var(--line);background:#000;padding:10px 12px;margin-top:12px}
  .drl .actzone h4{margin:0 0 6px;font-size:11.5px;color:var(--dim);letter-spacing:1.4px;font-weight:500}
  .drl .actzone .chip{font-size:12.5px;padding:6px 12px}
  @media(max-width:760px){ .drl .bgrid{grid-template-columns:1fr} }
  .drl .sheet{font-size:12.5px;line-height:1.95;color:var(--mid);white-space:pre-wrap;word-break:break-word}
  .drl .panels{display:grid;grid-template-columns:repeat(auto-fill,minmax(232px,1fr));gap:12px;margin-bottom:14px}
  .drl .pgroup{grid-column:1/-1;border:1px solid var(--line);border-radius:5px;background:#080c0f;overflow:hidden}
  .drl .pgh{display:flex;align-items:center;gap:8px;padding:7px 10px;cursor:pointer;background:#0c1216;
             border-bottom:1px solid var(--line);font-size:12px;color:var(--bright);user-select:none}
  .drl .pgh:hover{background:#101a20}
  .drl .pgh .pgc{margin-left:auto;color:#5f7c88;font-size:11px}
  .drl .pgb{display:grid;grid-template-columns:repeat(auto-fill,minmax(232px,1fr));gap:12px;padding:10px}
  .drl .pgb.off{display:none}
  .drl .pgbar{grid-column:1/-1;display:flex;gap:8px;justify-content:flex-end;font-size:11px;color:#5f7c88}
  .drl .pcard{border:1px solid var(--line);padding:11px 12px;background:#000;
               max-height:330px;display:flex;flex-direction:column}
  .drl .plist{overflow-y:auto;flex:1 1 auto;min-height:0;padding-right:6px}
  .drl .plist::-webkit-scrollbar{width:8px}
  .drl .plist::-webkit-scrollbar-track{background:var(--panel3)}
  .drl .plist::-webkit-scrollbar-thumb{background:var(--line2);border-radius:4px}
  .drl .plist::-webkit-scrollbar-thumb:hover{background:var(--dim2)}
  .drl .pdiv{font-size:11px;color:var(--dim2);letter-spacing:1.2px;margin:7px 0 3px;
             border-bottom:1px dashed var(--line);padding-bottom:3px}
  .drl #drl-ents{max-height:320px;overflow-y:auto}
  .drl #drl-exits{max-height:300px;overflow-y:auto}
  .drl .bt-tools{max-height:250px;overflow-y:auto;margin-top:4px}
  .drl .pcard{padding-bottom:6px}
  .drl .pcard h4{margin:0 0 6px;font-size:11.5px;color:var(--dim);letter-spacing:1.4px;font-weight:500}
  .drl .pcard p{margin:0 0 9px;font-size:11.5px;color:var(--dim2);line-height:1.7}
  .drl .pitem{display:block;width:100%;text-align:left;background:var(--panel2);border:1px solid var(--line);
              color:var(--fg);font-family:inherit;font-size:12px;padding:6px 9px;margin:3px 0;cursor:pointer}
  .drl .pitem:hover{border-color:var(--line2);color:#fff}
  .drl .pitem.dim{color:#6b4a2a}
  .drl .pitem b{font-weight:500}
  .drl .pitem i{font-style:normal;color:var(--dim2);font-size:11px;display:block;margin-top:2px}
  .drl .log{height:36vh;min-height:200px;overflow-y:auto;border:1px solid var(--line);
            padding:12px 14px;font-size:13.5px;line-height:1.95;white-space:pre-wrap;
            word-break:break-word;background:#000}
  .drl .log .sys{color:var(--dim)}
  .drl .log .in{color:var(--mid)}
  .drl .log .feed{color:#7faea0}
  .drl .row{display:flex;gap:10px;margin-top:12px;align-items:center}
  .drl .row i{color:var(--fg);font-style:normal}
  .drl input{flex:1;background:#000;border:1px solid var(--line);color:var(--fg);
             font-family:inherit;font-size:13.5px;padding:9px 12px;outline:none}
  .drl input:focus{border-color:var(--line2)}
  .drl button.btn{background:#000;border:1px solid var(--line);color:var(--dim);
                  font-family:inherit;font-size:12px;padding:8px 12px;cursor:pointer}
  .drl button.btn:hover{color:var(--fg);border-color:var(--line2)}
  .drl .hint{margin-top:12px;font-size:11.5px;color:var(--dim2);line-height:1.9}
  .drl .hint code{color:var(--mid)}
  @media(max-width:760px){ .drl .grid{grid-template-columns:1fr} }
</style>

<!-- CRRG-DRL-BUILD: 2026-10-01 06:30（窗口按钮 k=cmd ✓） -->
<div class="drl" data-theme="<?php echo esc_attr($pc['theme'] ?? 'red'); ?>">
  <div class="wrap">
    <div class="hd">
      <b>虚拟演习终端</b>
      <span>CRRG · QILIANSHAN BASE SIMULATION · CoC 7E</span>
      <div class="palette" id="drl-palette">
        <i>色调</i>
        <button class="sw" data-theme="red"    title="河洛红（默认）"></button>
        <button class="sw" data-theme="amber"  title="琥珀"></button>
        <button class="sw" data-theme="green"  title="终端绿"></button>
        <button class="sw" data-theme="cyan"   title="荧光青"></button>
        <button class="sw" data-theme="violet" title="紫"></button>
      </div>
    </div>
    <div class="bar" id="drl-status"><?php echo esc_html(crrg_drill_status_line($s, $ctx, $pc)); ?></div>

    <div class="panels" id="drl-panels"></div>

    <div class="grid">
      <div class="col side1">
        <div class="panel">
          <h3 id="drl-ent-h">此处可交互</h3>
          <div id="drl-ents"></div>
        </div>
        <div class="panel">
          <h3 id="drl-pc-h">角色卡</h3>
          <div id="drl-pc" class="sheet"></div>
        </div>
      </div>

      <div class="col main2">
        <div class="battle" id="drl-battle"></div>

        <div class="log" id="drl-log"></div>

        <div class="row">
          <i>&gt;</i>
          <input id="drl-in" autocomplete="off" spellcheck="false" placeholder="输入指令，回车执行">
          <button class="btn" id="drl-send">执行</button>
          <button class="btn" id="drl-help">帮助</button>
          <button class="btn" id="drl-reset">重置</button>
        </div>

        <div class="actzone" id="drl-actions"></div>
      </div>

      <div class="col side3">
        <div class="panel" id="drl-worldmap-card" style="display:none">
          <h3 id="drl-worldmap-h">区域图 · 基地周边</h3>
          <div id="drl-worldmap" style="margin-top:6px;cursor:zoom-in"></div>
          <div style="margin-top:6px"><button type="button" id="drl-wm-open" style="font-size:12px;padding:4px 10px;background:#1d3a46;color:#bfe0ea;border:1px solid #2b5768;border-radius:3px;cursor:pointer;">全屏查看（可放大）</button></div>
        </div>
        <div class="panel">
          <h3 id="drl-world-h">时间与天气</h3>
          <div id="drl-world" class="sheet"></div>
        </div>
        <div class="panel">
          <h3 id="drl-map-h">方位</h3>
          <pre class="map" id="drl-map"></pre>
          <div class="exits" id="drl-exits"></div>
        </div>
        <div class="panel">
          <h3 id="drl-art-h">场景示意</h3>
          <pre class="art" id="drl-art"></pre>
        </div>
      </div>
    </div>

    <div class="hint">
      左栏点即交互、右栏点地点即前往。手打亦可：<code>坐下</code>　<code>睡觉</code>　<code>吃</code>　<code>喝</code>　
      <code>说 内容</code>　<code>表情 叹气</code>　<code>动作 记录</code>　<code>掷 侦查</code>　<code>搜索</code>　
      <code>背包</code>　<code>装备 防护服</code>　<code>角色卡</code>
      <br>同地点的人会看到你的动作与发言（左侧日志里的青色行）；每 30 秒自动刷新。
    </div>
  </div>
</div>

<script>
(function(){
  var AJAX  = <?php echo json_encode($ajax); ?>;
  var NONCE = <?php echo json_encode($nonce); ?>;
  var log   = document.getElementById('drl-log');
  var input = document.getElementById('drl-in');
  var st    = document.getElementById('drl-status');
  var entBox= document.getElementById('drl-ents');
  var entH  = document.getElementById('drl-ent-h');
  var mapBox= document.getElementById('drl-map');
  var mapH  = document.getElementById('drl-map-h');
  var exBox = document.getElementById('drl-exits');
  var pcBox = document.getElementById('drl-pc');
  var busy  = false;
  var OCC = ['行动员','情报分析员','研究员','档案文献馆员','特殊勤务司队员','医疗员','文职','后勤'];

  /* 色调切换：本地立即生效，并写回账号 */
  var root = document.querySelector('.drl');
  var THEMES = ['red','amber','green','cyan','violet'];
  function setTheme(t, save){
    if (THEMES.indexOf(t) < 0) t = 'red';
    root.setAttribute('data-theme', t);
    document.querySelectorAll('#drl-palette .sw').forEach(function(b){
      b.classList.toggle('on', b.getAttribute('data-theme') === t);
    });
    try { localStorage.setItem('crrg_drill_theme', t); } catch(e){}
    if (save) act('theme', t);
  }
  document.querySelectorAll('#drl-palette .sw').forEach(function(b){
    b.addEventListener('click', function(){ setTheme(b.getAttribute('data-theme'), true); });
  });
  var saved = null;
  try { saved = localStorage.getItem('crrg_drill_theme'); } catch(e){}
  setTheme(saved || root.getAttribute('data-theme') || 'red', false);

  /* ── 时段滤镜：清晨青灰、正午亮、黄昏暖、夜里暗＋鼠标手电 ── */
  /* ── 世界时钟：本地按 1:48 推进，天气时段到点自动向后端同步 ── */
  var W = null, Wgame0 = 0, Wreal0 = 0, WslotEnd = 0, Wtimer = null, Wrefreshing = false;
  var WTZ = 0;                                   // 站点时区偏移（秒）
  function wfmt(ts){
    var d = new Date((ts + WTZ) * 1000);         // 统一按站点时区显示
    var p = function(n){ return (n < 10 ? '0' : '') + n; };
    return (d.getUTCMonth() + 1) + '月' + d.getUTCDate() + '日  ' + p(d.getUTCHours()) + ':' + p(d.getUTCMinutes());
  }
  function wphase(h){
    if (h < 5) return '深夜'; if (h < 8) return '黎明'; if (h < 11) return '上午';
    if (h < 13) return '正午'; if (h < 17) return '下午'; if (h < 19) return '黄昏';
    if (h < 23) return '夜'; return '深夜';
  }
  function wdraw(){
    if (!W) return;
    var now = Wgame0 + (Date.now() - Wreal0) / 1000 * (W.scale || 48);
    var d = new Date((now + WTZ) * 1000), h = d.getUTCHours();
    var day = W.day + Math.floor((now - W.ts) / 86400);
    var nextHour = Math.max(1, Math.ceil((3600 - (d.getUTCMinutes() * 60 + d.getUTCSeconds())) / (W.scale || 48)));
    document.getElementById('drl-world').textContent =
      wfmt(now) + '\n' +
      '天气：' + W.weather.n + '——' + W.weather.d + '\n' +
      '现实约 ' + nextHour + ' 秒后，游戏内过一小时（1 秒＝游戏 ' + (W.scale || 48) + ' 秒）';
    if (now >= WslotEnd && !Wrefreshing) { Wrefreshing = true; act('world_refresh', ''); }
  }
  function syncWorld(w){
    W = w; WTZ = w.tz_offset || 0; Wgame0 = w.ts; Wreal0 = Date.now(); WslotEnd = w.slot_end || (w.ts + 3600);
    Wrefreshing = false;
    wdraw();
    if (!Wtimer) Wtimer = setInterval(wdraw, 1000);
  }
  document.addEventListener('visibilitychange', function(){ if (!document.hidden && W) act('world_refresh', ''); });
  /* 心跳：页面开着每分钟结算一次身体状态；不在前台就不发（离线=暂停） */
  setInterval(function(){
    if (document.hidden) return;
    fetch(AJAX, { method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'action=crrg_drill&k=heartbeat&nonce=' + encodeURIComponent(NONCE)
    }).catch(function(){});
  }, 60000);

  /* ── 区域图全屏浮层：放大只放大空间、字号不变；缩小隐藏村庄名 ── */
  var WM_ZOOM = 1, WM_SVG = null;
  var WM_LOD_VILLAGE = 1.5;     // 低于此缩放：不显示村庄名
  var WM_LOD_ROAD    = 1.15;    // 低于此缩放：不显示路网编号
  function wmCollect(svgEl){
    WM_SVG = {
      el: svgEl,
      texts: Array.from(svgEl.querySelectorAll('text')).map(function(t){
        return { el: t, cls: t.getAttribute('class') || '', fs: parseFloat(t.getAttribute('data-fs') || t.getAttribute('font-size') || 12) };
      }),
      lines: Array.from(svgEl.querySelectorAll('[data-sw]')).map(function(el){
        return { el: el, sw: parseFloat(el.getAttribute('data-sw')) || 1 };
      })
    };
  }
  function wmApply(){
    var box = document.getElementById('drl-wm-svg');
    if (!WM_SVG) return;
    var z = WM_ZOOM;
    WM_SVG.el.style.width = (z * 100) + '%';
    WM_SVG.el.style.maxWidth = 'none';
    WM_SVG.el.style.height = 'auto';
    /* 字号／线宽按 1/z 反算 → 屏幕上大小恒定 */
    WM_SVG.texts.forEach(function(o){
      var hidden = (o.cls.indexOf('wm-v') >= 0 && z < WM_LOD_VILLAGE)
                || (o.cls.indexOf('wm-r') >= 0 && z < WM_LOD_ROAD);
      o.el.style.display = hidden ? 'none' : '';
      o.el.setAttribute('font-size', (o.fs / z).toFixed(2));
    });
    WM_SVG.lines.forEach(function(o){ o.el.setAttribute('stroke-width', (o.sw / z).toFixed(2)); });
    document.getElementById('drl-wm-zoom').textContent = Math.round(z * 100) + '%'
      + (z < WM_LOD_VILLAGE ? '（缩略：仅显示镇以上）' : '');
  }
  function openWorldMap(svgHtml){
    var ov = document.getElementById('drl-wm-overlay');
    var box = document.getElementById('drl-wm-svg');
    box.innerHTML = svgHtml;
    var svgEl = box.querySelector('svg');
    if (svgEl) wmCollect(svgEl);
    WM_ZOOM = 1; wmApply(); ov.style.display = '';
    document.body.style.overflow = 'hidden';
  }
  function closeWorldMap(){
    document.getElementById('drl-wm-overlay').style.display = 'none';
    document.body.style.overflow = '';
  }
  document.addEventListener('DOMContentLoaded', function(){
    document.getElementById('drl-wm-close').onclick = closeWorldMap;
    document.getElementById('drl-wm-in').onclick  = function(){ WM_ZOOM = Math.min(6, WM_ZOOM * 1.3); wmApply(); };
    document.getElementById('drl-wm-out').onclick = function(){ WM_ZOOM = Math.max(0.4, WM_ZOOM / 1.3); wmApply(); };
    document.getElementById('drl-wm-fit').onclick = function(){ WM_ZOOM = 1; wmApply(); };
    document.getElementById('drl-wm-open').onclick = function(){
      var box = document.getElementById('drl-worldmap'); if (box) openWorldMap(box.innerHTML);
    };
    /* 滚轮缩放（按住 Ctrl 或直接滚轮） */
    document.getElementById('drl-wm-body').addEventListener('wheel', function(e){
      if (!e.ctrlKey && !e.shiftKey) return;
      e.preventDefault();
      WM_ZOOM = Math.min(6, Math.max(0.4, WM_ZOOM * (e.deltaY < 0 ? 1.15 : 1/1.15)));
      wmApply();
    }, { passive: false });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeWorldMap(); });
  });

  function scroll(){ log.scrollTop = log.scrollHeight; }
  /* 2026-10-22：对话消息前缀**点阵头像**（按 NPC 名字识别 ✓ 名字表来自演习名册）
     头像用 mask 上色 → 跟着 data-theme 的主色自动变 ✓ */
  window.FACE_MAP = {
    '康明远':'kang','周德海':'zhou','陈连生':'chen','韩雪':'han','白淑英':'bai','魏长庚':'wei',
    '窗口办事员':'bs_clerk','卖菜的':'bs_vendor','招待所前台':'bs_innkeeper','车站值班员':'bs_stationman','采编员':'bs_reporter',
    '车站民警':'gz_cop','大学生':'gz_student','搓澡师傅':'gz_bathman','装卸工':'gz_porter','检查站值班':'gz_gatekeeper'
  };
  window.FACE_BASE = '/wp-content/themes/astra-child/assets/faces/';
  function faceOf(text){
    var t = (text || '').replace(/^[【\[（(·\s]+/, '');
    for (var k in FACE_MAP){
      if (t.indexOf(k) === 0) return (window.FACE_MAP || {})[k];
    }
    return '';
  }
  function add(text, cls){
    var d = document.createElement('div');
    if (cls) d.className = cls;
    var f = faceOf(text);
    if (f && !/^\s*(▌|〔|【系统)/.test(text)) {
      var sp = document.createElement('span');
      sp.className = 'dlg-face';
      sp.style.webkitMaskImage = 'url(' + (window.FACE_BASE || '') + f + '.png)';
      sp.style.maskImage = 'url(' + (window.FACE_BASE || '') + f + '.png)';
      d.appendChild(sp);
      d.appendChild(document.createTextNode(text));
    } else {
      d.textContent = text;
    }
    log.appendChild(d); scroll();
  }
  function mkBtn(label, cmd, cls){
    var b = document.createElement('button');
    b.className = cls || 'ent';
    b.textContent = label;
    b.addEventListener('click', function(){ run(cmd); });
    return b;
  }
  function feed(lines){
    (lines || []).forEach(function(m){
      /* 注：游戏里**文本本身**已带名字（如「验证器坐了下来。」✓），所以这里**不再拼 who** ✗
         （2026-10-01：一度想在此加名字，会重复 ✓ —— 正解是"谁生成的文本谁带名字" ✓） */
      add('· ' + (m.t || '') + ' ' + (m.text || ''), 'feed');
    });
  }

  /* 左栏：车卡中 → 车卡按钮；已就绪 → 可交互实体 */
  function renderLeft(v){
    if (v.pc && !v.pc.ready) {
      entH.textContent = '车卡 · 首次进入';
      entBox.innerHTML = '';
      entBox.appendChild(mkBtn('[掷骰] 生成属性', '掷骰'));
      entBox.appendChild(mkBtn('[?] 查看可选职业', '选职业'));
      OCC.forEach(function(o){ entBox.appendChild(mkBtn('[职业] ' + o, '选职业 ' + o)); });
      entBox.appendChild(mkBtn('[自动分配] 点完技能点', '自动分配'));
      entBox.appendChild(mkBtn('[完成] 进入演习', '完成'));
      entBox.appendChild(mkBtn('[角色卡] 查看当前', '角色卡'));
      return;
    }
    entH.textContent = '此处可交互 · ' + v.title;
    entBox.innerHTML = '';
    var kinds = {item:'物', npc:'人'};
    (v.ents || []).forEach(function(e){
      var b = document.createElement('button');
      b.className = 'ent' + (e.k === 'npc' ? ' npc' : '');
      var i = document.createElement('i'); i.textContent = '[' + (kinds[e.k] || '物') + ']';
      b.appendChild(i); b.appendChild(document.createTextNode(e.n));
      b.title = '>' + e.c;
      b.addEventListener('click', function(){ run(e.c); });
      entBox.appendChild(b);
    });
    if (v.players && v.players.length) {
      var h = document.createElement('div');
      h.className = 'empty'; h.style.marginTop = '8px'; h.textContent = '此刻同在此处（点一下可互动）：';
      entBox.appendChild(h);
      v.players.forEach(function(p){
        var obj = (typeof p === 'object' && p !== null);          // 2026-10-01：结构化（id/name/role）✓ 兼容旧字符串 ✓
        var b = document.createElement('button');
        b.className = 'ent npc';
        var i = document.createElement('i'); i.textContent = '[人]';
        b.appendChild(i); b.appendChild(document.createTextNode(obj ? (p.label || p.name) : p));
        if (obj && p.id) {
          b.title = '互动：打招呼 / 递东西 / 邀请组队 / 留话';
          b.addEventListener('click', function(){ act('win', 'player:' + p.id); });
        } else { b.style.cursor = 'default'; }
        entBox.appendChild(b);
      });
    }
    if (!entBox.children.length) {
      var e2 = document.createElement('div');
      e2.className = 'empty'; e2.textContent = '此处没有可交互的东西。';
      entBox.appendChild(e2);
    }
  }

  function renderBattle(b){
    var box = document.getElementById('drl-battle');
    var az0 = document.getElementById('drl-actions');
  if (!b) { box.className = 'battle'; box.innerHTML = ''; if (az0) az0.innerHTML = ''; return; }
    box.className = 'battle on';
    box.innerHTML = '';
    var h = document.createElement('h4');
    h.textContent = b.title + (b.sim ? '（非致命模拟）' : '') + (b.focus ? '　目标：' + b.focus : '');
    box.appendChild(h);
    var lg2 = document.createElement('div'); lg2.className = 'blog';
    lg2.textContent = '色块：黄＝你　青＝队友　红＝对手　深灰／浅灰＝半／全身掩体　棕＝油桶　深蓝＝电闸／消防栓　暗黄＝照明开关　橙＝火场。点空地朝该方向移动，点对手选中它。';
    box.appendChild(lg2);
    if (b.grid) {
      var wrap = document.createElement('div'); wrap.className = 'tacwrap';
      var tac = document.createElement('div'); tac.className = 'tac';
      tac.style.gridTemplateColumns = '26px repeat(' + b.grid.w + ', 22px)';
      var byCell = {};
      (b.grid.marks || []).forEach(function(m){ byCell[m.x + ',' + m.y] = m; });
      function ax(t){ var d = document.createElement('div'); d.className='ax'; d.textContent = t || ''; return d; }
      tac.appendChild(ax(''));
      for (var x = 0; x < b.grid.w; x++) tac.appendChild(ax(x % 5 === 0 ? x : ''));
      for (var y = b.grid.h - 1; y >= 0; y--) {
        tac.appendChild(ax(y % 5 === 0 ? y : ''));
        for (var cx = 0; cx < b.grid.w; cx++) {
          var m = byCell[cx + ',' + y];
          var cell = document.createElement('div');
          cell.className = 'c ' + (m ? m.cls : 'empty');
          cell.textContent = m ? (m.lab || '') : '';
          if (m) {
            cell.title = m.t || '';
            if (m.k === 'actor') cell.addEventListener('click', function(k){ return function(){ act('bt_focus', k); }; }(m.id));
          } else {
            cell.title = '向这里移动';
            cell.addEventListener('click', function(px, py){ return function(){
              var dx = px - (b.me ? b.me[0] : 0), dy = py - (b.me ? b.me[1] : 0);
              var dirs = [['东',0],['东北',45],['北',90],['西北',135],['西',180],['西南',225],['南',270],['东南',315]];
              var ang = (Math.atan2(dy, dx) * 180 / Math.PI + 360) % 360, best = '东', bd = 999;
              dirs.forEach(function(d){ var diff = Math.abs(((d[1] - ang + 540) % 360) - 180); if (diff < bd) { bd = diff; best = d[0]; } });
              act('bt_move', best);
            }; }(cx, y));
          }
          tac.appendChild(cell);
        }
      }
      wrap.appendChild(tac); box.appendChild(wrap);
    }
    var g = document.createElement('div'); g.className = 'bgrid';
    var left = document.createElement('div');
    (b.list || []).forEach(function(l){ var d = document.createElement('div'); d.className = 'sit'; d.textContent = l; left.appendChild(d); });
    if (b.log && b.log.length) {
      var lg = document.createElement('div'); lg.className = 'blog'; lg.textContent = b.log.join('\n'); left.appendChild(lg);
    }
    g.appendChild(left); box.appendChild(g);

    var az = document.getElementById('drl-actions');
    az.innerHTML = '';
    var zones = {tools: box};
    function chips(grp, host){
      var t = document.createElement('h4'); t.textContent = grp.title; host.appendChild(t);
      var wrap = document.createElement('div');
      (grp.items || []).forEach(function(it){
        var chip = document.createElement('button');
        chip.className = 'chip' + (it.on ? ' on' : '') + (it.off ? ' off' : '');
        chip.textContent = it.label + (it.note ? '（' + it.note + '）' : '');
        if (!it.off) chip.addEventListener('click', function(){ act(it.k, it.id || ''); });
        wrap.appendChild(chip);
      });
      host.appendChild(wrap);
    }
    var tools = document.createElement('div'); tools.className = 'plist bt-tools';
    (b.groups || []).forEach(function(grp){
      if (grp.zone === 'actions') { chips(grp, az); }
      else { var host = document.createElement('div'); chips(grp, host); tools.appendChild(host); }
    });
    box.appendChild(tools);
    az.style.display = '';
  }

  function pgState(key, dflt){
    try { var v = localStorage.getItem('drl_pg_' + key); if (v !== null) return v === '1'; } catch(e){}
    return !!dflt;
  }
  function pgSet(key, on){ try { localStorage.setItem('drl_pg_' + key, on ? '1' : '0'); } catch(e){} }
  function renderPanels(list, groups){
    var box = document.getElementById('drl-panels');
    box.innerHTML = '';
    groups = groups || {};
    /* 顶部：全部展开／收起 */
    var bar = document.createElement('div'); bar.className = 'pgbar';
    var bAll = document.createElement('button'); bAll.textContent = '全部展开';
    bAll.style.cssText = 'font-size:11px;padding:2px 8px;background:#0f1a20;color:#9fb4bd;border:1px solid #22323a;border-radius:3px;cursor:pointer';
    var bNone = document.createElement('button'); bNone.textContent = '全部收起';
    bNone.style.cssText = bAll.style.cssText;
    bar.appendChild(bAll); bar.appendChild(bNone); box.appendChild(bar);

    var grouped = {};
    (list || []).forEach(function(p){
      var g = p.g || 'scene';
      (grouped[g] = grouped[g] || []).push(p);
    });
    var order = Object.keys(groups).length ? Object.keys(groups) : Object.keys(grouped);
    order.forEach(function(gk){
      if (!grouped[gk] || !grouped[gk].length) return;
      var gdef = groups[gk] || { n: gk, open: 1 };
      var sec = document.createElement('div'); sec.className = 'pgroup';
      var head = document.createElement('div'); head.className = 'pgh';
      var arrow = document.createElement('span'); arrow.className = 'pga';
      var name = document.createElement('span'); name.textContent = gdef.n || gk;
      var cnt = document.createElement('span'); cnt.className = 'pgc'; cnt.textContent = grouped[gk].length + ' 张';
      /* 折叠时在组头显示第一条摘要（例如「精力 97 ／饱食 100 ／口渴 100」） */
      var sum = document.createElement('span'); sum.className = 'pgs';
      sum.style.cssText = 'color:#5f7c88;font-size:11px;margin-left:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:52%';
      var first = grouped[gk][0] || {};
      sum.textContent = (first.note || first.title || '').slice(0, 34);
      head.appendChild(arrow); head.appendChild(name); head.appendChild(cnt); head.appendChild(sum);
      var body = document.createElement('div'); body.className = 'pgb';
      grouped[gk].forEach(function(p){
        var card = document.createElement('div'); card.className = 'pcard';
        var h = document.createElement('h4'); h.textContent = p.title; card.appendChild(h);
        if (p.note) { var n = document.createElement('p'); n.textContent = p.note; card.appendChild(n); }
        var bl = document.createElement('div'); bl.className = 'plist';
        (p.items || []).forEach(function(it){
          if (it.k === 'divider') {
            var dv = document.createElement('div'); dv.className = 'pdiv'; dv.textContent = it.label;
            bl.appendChild(dv); return;
          }
          var b = document.createElement('button');
          var off = (p.locked || it.locked || it.done || it.off || it.afford === false);
          b.className = 'pitem' + (off ? ' dim' : '');
          var t = document.createElement('b'); t.textContent = it.label; b.appendChild(t);
          if (it.note) { var i = document.createElement('i'); i.textContent = it.note; b.appendChild(i); }
          if (!off) b.addEventListener('click', function(){ act(it.k, it.id || ''); });
          bl.appendChild(b);
        });
        card.appendChild(bl); body.appendChild(card);
      });
      function paint(on){
        body.classList.toggle('off', !on);
        arrow.textContent = on ? '▾' : '▸';
      }
      paint(pgState(gk, gdef.open));
      head.addEventListener('click', function(){
        var on = body.classList.contains('off');
        paint(on); pgSet(gk, on);
      });
      bAll.addEventListener('click', function(){ paint(true); pgSet(gk, true); });
      bNone.addEventListener('click', function(){ paint(false); pgSet(gk, false); });
      sec.appendChild(head); sec.appendChild(body); box.appendChild(sec);
    });
  }


  /* ══ 窗口管理器（2026-09-30）：一个图层上开可拖可关的窗口 ══ */
  var WINS = {};                       // id -> {el, z}
  var WZ = 61;
  function winsLayer(){
    var l = document.getElementById('drl-wins');
    if (!l) { l = document.createElement('div'); l.id = 'drl-wins'; l.className = 'drl-wins';
              document.body.appendChild(l); }
    return l;
  }
  function winClose(id){
    if (!id) return;
    Object.keys(WINS).forEach(function(k){
      if (k === id || k.indexOf(id) === 0) { WINS[k].el.remove(); delete WINS[k]; }
    });
  }
  function winOpen(w){
    if (!w || !w.id) return;
    var key = w.id + (w.arg ? ':' + w.arg : '');
    var box = WINS[key];
    if (!box) {
      var el = document.createElement('div');
      el.className = 'drl-win';
      el.style.left = (80 + Object.keys(WINS).length * 26) + 'px';
      el.style.top  = (120 + Object.keys(WINS).length * 22) + 'px';
      var hd = document.createElement('div'); hd.className = 'w-hd';
      var mk = document.createElement('span'); mk.className = 'w-mk'; mk.textContent = '▌';
      var tt = document.createElement('b'); tt.textContent = w.title || w.id;
      var cl = document.createElement('span'); cl.className = 'w-cl'; cl.textContent = '[ × ]';
      cl.title = '关闭（Esc 也行）';
      cl.addEventListener('click', function(){ el.remove(); delete WINS[key]; });
      hd.appendChild(mk); hd.appendChild(tt); hd.appendChild(cl);
      var bd = document.createElement('div'); bd.className = 'w-bd';
      /* 2026-10-22 重写：**固定立绘位**（永远渲染 ✓ 有图放图 / 没图放占位）
         fid 来源：后端 w.face 优先 ✓ 没有才按标题『名字』查表 ✓ */
      var ft = document.createElement('div'); ft.className = 'w-ft';
      el.appendChild(hd); el.appendChild(bd); el.appendChild(ft);
      winsLayer().appendChild(el);
      /* 拖动 */
      (function(){ var dx=0, dy=0, drag=false;
        hd.addEventListener('mousedown', function(e){ drag=true; dx=e.clientX-el.offsetLeft; dy=e.clientY-el.offsetTop; e.preventDefault(); });
        document.addEventListener('mousemove', function(e){ if(!drag) return; el.style.left=(e.clientX-dx)+'px'; el.style.top=(e.clientY-dy)+'px'; });
        document.addEventListener('mouseup', function(){ drag=false; });
      })();
      /* Esc 关掉最上面的窗口 ✓ */
      document.addEventListener('keydown', function(e){
        if (e.key !== 'Escape') return;
        var top = null, z = 0;
        Object.keys(WINS).forEach(function(k){ if (WINS[k].el.style.zIndex > z) { z = +WINS[k].el.style.zIndex; top = k; } });
        if (top) { WINS[top].el.remove(); delete WINS[top]; }
      });
      box = WINS[key] = { el: el, bd: bd, ft: ft };
    }
    box.el.style.zIndex = ++WZ;
    box.ft.textContent = '拖动标题栏移动　·　Esc 关闭';
    var bd = box.bd; bd.innerHTML = '';
    /* fid 三重兜底：① 后端 face ② 窗口 arg（npc_han → han）③ 标题『名字』查表 */
    var fid = String(w.face || '');
    if (!fid) {
      var a2 = String(w.arg || '');
      if (a2.indexOf('npc_') === 0) fid = a2.slice(4);
    }
    if (!fid) {
      var probe = String(w.title || '') + ' ' + ((w.text && w.text[0]) || '');
      var mm = probe.match(/[『「]([^』」]+)[』」]/);
      var who = mm ? mm[1].replace(/^问/, '').trim() : '';
      fid = (window.FACE_MAP || {})[who] || '';
    }
    var pf = document.createElement('div'); pf.className = 'w-portrait';
    if (fid) {
      /* 2026-10-22 定稿：**按主题取彩色图**（<id>-<theme>.png ✓ 不用 mask ✗ 不用 filter ✗） */
      var themeNow = 'red';
      try { var dEl = document.querySelector('.drl'); if (dEl) themeNow = dEl.getAttribute('data-theme') || 'red'; } catch(e){}
      var faceUrl = (window.FACE_BASE || '/wp-content/themes/astra-child/assets/faces/') + fid + '-' + themeNow + '.png';
      var fig = document.createElement('img'); fig.className = 'w-fig2';
      fig.src = faceUrl; fig.alt = '';
      fig.onerror = function(){ this.style.display = 'none'; };
      pf.appendChild(fig);
    } else {
      var ph = document.createElement('span'); ph.className = 'w-ph'; ph.textContent = '立绘';
      pf.appendChild(ph);
    }
    /* 2026-10-22 重写：**对话窗口换版式**（左立绘栏 + 右内容 + 名字牌）
       判定：后端给了 face，或标题叫"对话" → 走新版式 ✓ 其它窗口保持原样（列表页不动 ✓） */
    var isDlg = !!(w.face || String(w.title || '') === '对话' || w.layout);
    var mainBox = bd;
    if (isDlg) {
      var grid = document.createElement('div'); grid.className = 'w-dlg';
      var port = document.createElement('div'); port.className = 'w-port';
      if (fid) {
        var themeNow = 'red';
        try { var dEl = document.querySelector('.drl'); if (dEl) themeNow = dEl.getAttribute('data-theme') || 'red'; } catch(e){}
        var fig = document.createElement('img'); fig.className = 'w-fig2';
        fig.src = (window.FACE_BASE || '/wp-content/themes/astra-child/assets/faces/') + fid + '-' + themeNow + '.png';
        fig.alt = '';
        fig.onerror = function(){ this.style.display = 'none'; };
        port.appendChild(fig);
      } else {
        var ph2 = document.createElement('span'); ph2.className = 'w-ph'; ph2.textContent = '立绘';
        port.appendChild(ph2);
      }
      var main2 = document.createElement('div'); main2.className = 'w-main2';
      /* 名字牌：从『名字』里取（后端 face 时用名册反查也行） */
      var nm = '';
      var tg = String(w.title || '');
      var mm2 = (String((w.text && w.text[0]) || '') + ' ' + tg).match(/[『「]([^』」]+)[』」]/);
      if (mm2) nm = mm2[1].trim();
      if (nm) {
        var nt = document.createElement('div'); nt.className = 'w-nametag';
        var nb = document.createElement('b'); nb.textContent = nm;
        var ni = document.createElement('i'); ni.textContent = 'REC · 对话中';
        nt.appendChild(nb); nt.appendChild(ni); main2.appendChild(nt);
      }
      grid.appendChild(port); grid.appendChild(main2);
      bd.appendChild(grid);
      mainBox = main2;
    } else {
      bd.appendChild(pf);
    }
    if (w.note) { var n = document.createElement('div'); n.className = 'w-note'; n.textContent = w.note; mainBox.appendChild(n); }
    (w.text || []).forEach(function(l){ var d = document.createElement('div'); d.className='w-tx'; d.textContent = l; mainBox.appendChild(d); });
    (w.items || []).forEach(function(it){
      if (it.k === 'divider') { var dv = document.createElement('div'); dv.className='w-dv'; dv.textContent = (it.label || ''); mainBox.appendChild(dv); return; }
      var b = document.createElement('button'); b.className = 'w-bt' + (isDlg ? ' w-btnline' : '');
      b.textContent = it.label || ''; if (it.off) { b.disabled = true; }
      else b.addEventListener('click', function(){
        /* 2026-10-01：k='cmd' 表示"这条按钮本身是一条指令"（如「去 青石镇」「问 常守业」）
           → 直接把它当输入发出去 ✓（「互动」窗口里列的东西因此都能点 ✓） */
        if (it.k === 'cmd') { run(it.id || ''); } else { act(it.k, it.id || ''); }
      });
      bd.appendChild(b);
    });
  }

  function act(k, id){
    if (busy) return;
    busy = true;
    var fd = new FormData();
    fd.append('action', 'crrg_drill_act');
    fd.append('_wpnonce', NONCE);
    fd.append('k', k);
    fd.append('id', id || '');
    fetch(AJAX, { method:'POST', body: fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j || !j.success) { add('▌ ' + ((j && j.data && j.data.msg) || '操作失败'), 'sys'); return; }
        (j.data.lines || []).forEach(function(l){ add(l); });
        if (j.data.status) st.textContent = j.data.status;
        if (j.data.win) winOpen(j.data.win);
        /* 2026-10-01：服务器叫关就关（对话结束 ✓）——按 id 前缀匹配（如 dlg:tw_paperman ✓） */
        if (j.data.close) winClose(j.data.close);
        renderView(j.data.view);
        if (j.data.view) feed(j.data.view.feed);
      })
      .catch(function(e){ add('▌ 连接失败：' + e.message, 'sys'); })
      .finally(function(){ busy = false; });
  }

  function renderView(v){
    if (!v) return;
    renderLeft(v);
    renderPanels(v.panels, v.pgroups);
    renderBattle(v.battle);

    var wmCard = document.getElementById('drl-worldmap-card');
    var wmBox  = document.getElementById('drl-worldmap');
    if (v.worldmap) {
      wmBox.innerHTML = v.worldmap; wmCard.style.display = '';
      var svgEl = wmBox.querySelector('svg'); if (svgEl) { svgEl.style.maxWidth = '100%'; }
      wmBox.onclick = function(){ openWorldMap(v.worldmap); };
    } else { wmBox.innerHTML = ''; wmCard.style.display = 'none'; }
    if (v.world) syncWorld(v.world);
    document.getElementById('drl-art-h').textContent = '场景示意 · ' + v.title;
    document.getElementById('drl-art').textContent = v.art || '（此处没有绘制示意）';
    mapH.textContent = '方位 · ' + v.title + (v.floor && v.floor !== '—' ? '（' + v.floor + '）' : '');
    mapBox.textContent = (v.map || []).join('\n');
    exBox.innerHTML = '';
    (v.exits || []).forEach(function(x){
      var b = document.createElement('button');
      b.className = 'ex' + (x.locked ? ' locked' : '');
      var s1 = document.createElement('b'); s1.textContent = x.no + ' ' + x.sym;
      b.appendChild(s1);
      b.appendChild(document.createTextNode(x.name));
      var em = document.createElement('em');
      em.textContent = (x.dist ? x.dist + ' · ' : '') + x.card + ' 级' + (x.locked ? ' · 无权限' : '');
      b.appendChild(em);
      b.title = '>去 ' + x.label;
      b.addEventListener('click', function(){ run('去 ' + x.label); });
      exBox.appendChild(b);
    });

    var p = v.pc || {};
    document.getElementById('drl-pc-h').textContent = '角色卡 · ' + (p.name || '—') + (p.occ && p.occ !== '—' ? '（' + p.occ + '）' : '');
    pcBox.textContent = (p.sheet || []).join('\n') + (p.ready ? '' : '\n（尚未完成车卡）');

    var bag = (v.bag || []), worn = (v.worn || []);
  }

  function post(action, cmd, silent){
    var fd = new FormData();
    fd.append('action', action);
    fd.append('_wpnonce', NONCE);
    if (cmd !== undefined && cmd !== null) fd.append('cmd', cmd);
    return fetch(AJAX, { method:'POST', body: fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j || !j.success) {
          if (!silent) add('▌ ' + ((j && j.data && j.data.msg) || '未知错误'), 'sys');
          return;
        }
        if (!silent) (j.data.lines || []).forEach(function(l){ add(l); });
        if (j.data.status) st.textContent = j.data.status;
        if (j.data.win) winOpen(j.data.win);
        /* 2026-10-01：服务器叫关就关（对话结束 ✓）——按 id 前缀匹配（如 dlg:tw_paperman ✓） */
        if (j.data.close) winClose(j.data.close);
        renderView(j.data.view);
        if (j.data.view) feed(j.data.view.feed);
      })
      .catch(function(e){ if (!silent) add('▌ 连接失败：' + e.message, 'sys'); });
  }

  function run(text){
    if (!text || busy) return;
    add('> ' + text, 'in');
    busy = true;
    post('crrg_drill_cmd', text).finally(function(){ busy = false; input.focus(); });
  }

  document.getElementById('drl-send').addEventListener('click', function(){ var t = input.value.trim(); input.value=''; run(t); });
  input.addEventListener('keydown', function(e){ if (e.key === 'Enter'){ var t = input.value.trim(); input.value=''; run(t); } });
  /* 2026-10-01：输入框旁的「帮助」改为**开窗**（窗口里列全部指令，窗口名可点开 ✓）*/
  document.getElementById('drl-help').addEventListener('click', function(){ act('win','help'); });
  document.getElementById('drl-reset').addEventListener('click', function(){ if (confirm('重置演习进度？')) run('重置'); });

  var INIT = <?php echo json_encode($view, JSON_UNESCAPED_UNICODE); ?>;
  renderView(INIT);
  add('〔演习终端已就绪〕CoC 7e 规则。首次进入请先车卡：掷骰 → 选职业 → 加点/自动分配 → 完成。', 'sys');
  feed(INIT.feed);
  input.focus();

  setInterval(function(){ post('crrg_drill_state', null, true); }, 30000);

  ['.site', '.site-content', '.ast-container', '#content', '#main', 'main',
   '.entry-content', 'article', 'body',
   '.gov-main', '.gov-content', '.gov-wrap', '.ast-separate-container', '.site-content .ast-container'].forEach(function(sel){
    document.querySelectorAll(sel).forEach(function(el){ el.style.background = '#000'; });
  });
})();
</script>
<div id="drl-wm-overlay" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.9)">
  <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-bottom:1px solid #22323a;background:#0b1216">
    <span style="color:#bfe0ea;font-size:13px">区域图 · 基地周边</span>
    <button type="button" id="drl-wm-out"  style="font-size:12px;padding:4px 10px;background:#12222a;color:#bfe0ea;border:1px solid #2b5768;border-radius:3px;cursor:pointer;">缩小 −</button>
    <button type="button" id="drl-wm-in"   style="font-size:12px;padding:4px 10px;background:#12222a;color:#bfe0ea;border:1px solid #2b5768;border-radius:3px;cursor:pointer;">放大 ＋</button>
    <button type="button" id="drl-wm-fit"  style="font-size:12px;padding:4px 10px;background:#12222a;color:#bfe0ea;border:1px solid #2b5768;border-radius:3px;cursor:pointer;">适应宽度</button>
    <span id="drl-wm-zoom" style="color:#7f9aa6;font-size:12px">100%</span>
    <button type="button" id="drl-wm-close" style="margin-left:auto;font-size:12px;padding:4px 12px;background:#3a1d1d;color:#ffb3b3;border:1px solid #6b2b2b;border-radius:3px;cursor:pointer;">关闭</button>
  </div>
  <div id="drl-wm-body" style="overflow:auto;height:calc(100% - 42px);padding:12px">
    <div id="drl-wm-svg" style="transform-origin:top left"></div>
  </div>
</div>
<?php wp_footer(); ?>
<script src="/wp-content/themes/astra-child/assets/drill-scene.js?v=1"></script>
</body>
</html>
