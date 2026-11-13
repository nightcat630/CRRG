<?php
// 公告轮播 HTML
function crrg_announcement_carousel() {
    $anns = crrg_get_announcements();
    if (empty($anns)) return;
    $count = count($anns);
    ob_start();
    ?>
    <div class="ann-carousel-widget">
        <div class="widget-title"> 公告</div>
        <div class="ann-carousel" id="ann-carousel" data-count="<?php echo $count; ?>">
            <?php foreach ($anns as $i => $ann): ?>
                <div class="ann-slide" style="<?php echo $i>0?'display:none;':''; ?>">
                    <a href="/notices/" style="color:#1B3A5C;text-decoration:none;font-size:12px;line-height:1.5;">
                        <?php echo esc_html(mb_strlen($ann['title'])>20 ? mb_substr($ann['title'],0,20).'…' : $ann['title']); ?>
                    </a>
                    <div style="font-size:10px;color:#999;"><?php echo date('m-d H:i', strtotime($ann['time'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <style>
    .ann-carousel-widget { background:#fff; border:1px solid #e0e0e0; border-radius:4px; padding:10px 14px; margin-bottom:12px; }
    .ann-carousel-widget .widget-title { font-size:14px; font-weight:bold; color:#C41230; margin-bottom:6px; }
    .ann-carousel { position:relative; min-height:36px; }
    </style>
    <script>
    (function(){
        var c=document.getElementById('ann-carousel'); if(!c)return;
        var slides=c.querySelectorAll('.ann-slide'), idx=0, n=slides.length; if(n<2)return;
        setInterval(function(){
            slides[idx].style.display='none';
            idx=(idx+1)%n;
            slides[idx].style.display='';
        },4000);
    })();
    </script>
    <?php
    return ob_get_clean();
}
