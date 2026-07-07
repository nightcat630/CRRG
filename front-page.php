<?php
/* Template Name: 首页模板 */

get_header();
add_filter('astra_content_wrapper_class', '__return_empty_string');

$news_items = get_posts(['post_type'=>'post','post_status'=>'publish','meta_key'=>'crrg_is_news','meta_value'=>'1','posts_per_page'=>6]);
$hot = get_posts(['post_type'=>'topic','posts_per_page'=>5,'meta_key'=>'crrg_likes','orderby'=>'meta_value_num','order'=>'DESC']);
$recent = get_posts(['post_type'=>'post','post_status'=>'publish','meta_key'=>'crrg_report_type_name','meta_compare'=>'EXISTS','posts_per_page'=>5,'orderby'=>'date','order'=>'DESC']);
$alert = crrg_get_alert();
$top = crrg_get_top_member();
?>
<?php if ($alert['active']): ?>
<div style="background:<?php echo esc_attr($alert['color']); ?>;color:#fff;padding:10px 20px;text-align:center;font-size:14px;font-weight:bold;">
    ⚠️ <?php echo esc_html($alert['title']); ?>：<?php echo esc_html($alert['content']); ?>
</div>
<?php endif; ?>
<div class="gov-main">
    <div class="gov-content" style="flex: 1;">
        <!-- 今日要闻 -->
        <div style="padding:16px 0;border-bottom:1px solid #eee;margin-bottom:24px;">
            <h2 style="font-size:20px;color:#1B3A5C;margin:0;font-weight:bold;">📰 今日要闻</h2>
            <p style="color:#999;margin:4px 0 0;font-size:13px;">超自然现象每日简报</p>
        </div>
        <?php if ($news_items): array_unshift($news_items, $first); ?>
            <div id="news-carousel" style="position:relative;margin-bottom:20px;">
                <style>#news-track::-webkit-scrollbar{display:none;}</style>
                <!-- 新闻卡片容器 -->
                <div id="news-track" style="display:flex;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;gap:0;border:1px solid #e0e0e0;border-radius:4px;background:#fff;scrollbar-width:none;-ms-overflow-style:none;">
                    <?php foreach ($news_items as $idx => $n):
                        $thumb = '';
                        preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/', $n->post_content, $m);
                        if($m) $thumb=$m[1];
                        $author = get_userdata($n->post_author);
                    ?>
                    <div class="news-slide" data-index="<?php echo $idx; ?>" style="flex:0 0 100%;scroll-snap-align:start;display:flex;min-height:200px;">
                        <?php if($thumb): ?><img src="<?php echo esc_url($thumb); ?>" style="width:300px;min-height:200px;object-fit:cover;flex-shrink:0;" alt=""><?php endif; ?>
                        <div style="padding:20px 24px;flex:1;display:flex;flex-direction:column;justify-content:center;">
                            <a href="<?php echo get_permalink($n); ?>" style="font-size:18px;font-weight:bold;color:#1B3A5C;text-decoration:none;"><?php echo esc_html($n->post_title); ?></a>
                            <div style="font-size:12px;color:#999;margin:8px 0;"><?php echo get_the_date('Y-m-d H:i',$n); ?> · <?php echo $author?esc_html($author->display_name):'未知'; ?></div>
                            <div style="font-size:13px;color:#666;line-height:1.8;"><?php echo wp_trim_words(strip_tags($n->post_content),60); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- 左箭头 -->
                <button id="news-prev" style="position:absolute;left:0;top:50%;transform:translateY(-50%);background:rgba(0,0,0,0.45);border:none;color:rgba(255,255,255,0.85);font-size:28px;width:44px;height:64px;cursor:pointer;border-radius:0 6px 6px 0;display:flex;align-items:center;justify-content:center;z-index:10;transition:background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.65)'" onmouseout="this.style.background='rgba(0,0,0,0.45)'">◀</button>
                <!-- 右箭头 -->
                <button id="news-next" style="position:absolute;right:0;top:50%;transform:translateY(-50%);background:rgba(0,0,0,0.45);border:none;color:rgba(255,255,255,0.85);font-size:28px;width:44px;height:64px;cursor:pointer;border-radius:6px 0 0 6px;display:flex;align-items:center;justify-content:center;z-index:10;transition:background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.65)'" onmouseout="this.style.background='rgba(0,0,0,0.45)'">▶</button>
                <!-- 指示点 -->
                <div style="display:flex;justify-content:center;gap:8px;padding:10px 0;" id="news-dots">
                    <?php foreach ($news_items as $idx => $n): ?>
                    <span class="news-dot" data-index="<?php echo $idx; ?>" style="width:8px;height:8px;border-radius:50%;background:<?php echo $idx===0?'#C41230':'#d5d5d5'; ?>;cursor:pointer;transition:background 0.2s;"></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <script>
            (function(){
                var track = document.getElementById('news-track');
                var slides = track.querySelectorAll('.news-slide');
                var dots = document.querySelectorAll('.news-dot');
                var total = slides.length;
                var current = 0;
                function goTo(idx) {
                    if (idx < 0) idx = total - 1;
                    if (idx >= total) idx = 0;
                    current = idx;
                    track.scrollTo({left: slides[idx].offsetLeft, behavior: 'smooth'});
                    dots.forEach(function(d,i){ d.style.background = i===idx ? '#C41230' : '#d5d5d5'; });
                }
                document.getElementById('news-prev').addEventListener('click', function(){ goTo(current - 1); });
                document.getElementById('news-next').addEventListener('click', function(){ goTo(current + 1); });
                dots.forEach(function(d){ d.addEventListener('click', function(){ goTo(parseInt(this.dataset.index)); }); });
                // 监听手动滚动同步指示点
                var scrolling = false;
                track.addEventListener('scroll', function(){
                    if (scrolling) return;
                    scrolling = true;
                    setTimeout(function(){
                        var sw = slides[0].offsetWidth;
                        var idx = Math.round(track.scrollLeft / sw);
                        if (idx >= 0 && idx < total && idx !== current) {
                            current = idx;
                            dots.forEach(function(d,i){ d.style.background = i===idx ? '#C41230' : '#d5d5d5'; });
                        }
                        scrolling = false;
                    }, 150);
                });
                // 触屏滑动也同步
                track.addEventListener('touchend', function(){
                    setTimeout(function(){
                        var sw = slides[0].offsetWidth;
                        var idx = Math.round(track.scrollLeft / sw);
                        if (idx >= 0 && idx < total && idx !== current) {
                            current = idx;
                            dots.forEach(function(d,i){ d.style.background = i===idx ? '#C41230' : '#d5d5d5'; });
                        }
                    }, 100);
                });
            })();
            </script>
        <?php else: ?>
            <p style="color:#999;text-align:center;padding:40px;">暂无要闻</p>
        <?php endif; ?>
    </div>

    <div class="gov-sidebar">
        <div class="widget">
            <div class="widget-title">⭐ 本周优秀干员</div>
            <div style="font-size:13px;text-align:center;padding:8px 0;">
                <?php if ($top): $tr = crrg_get_rank_data(crrg_get_rank($top->ID)); ?>
                    <?php echo get_avatar($top->ID, 56, '', '', ['style'=>'border-radius:50%;border:2px solid #C41230;']); ?>
                    <div style="margin-top:6px;font-weight:bold;"><?php echo esc_html($top->display_name); ?></div>
                    <div style="color:#999;font-size:11px;"><?php echo $tr['icon'].' '.$tr['name']; ?></div>
                <?php else: ?><p style="color:#999;">暂无数据</p><?php endif; ?>
            </div>
        </div>
        <div class="widget">
            <div class="widget-title"><a href="/notices/" style="color:inherit;text-decoration:none;">📌 最新通知</a></div>
            <ul style="list-style:none;padding:0;margin:0;font-size:13px;line-height:2;">
                <?php $anns = crrg_get_announcements(); if ($anns): foreach (array_slice($anns,0,5) as $ann): ?>
                    <li>· <a href="/notices/" style="color:#333;text-decoration:none;"><?php echo esc_html($ann['title']); ?></a></li>
                <?php endforeach; else: ?><li>· 暂无通知</li><?php endif; ?>
            </ul>
        </div>
        <div class="widget">
            <div class="widget-title"><a href="/reports/" style="color:inherit;text-decoration:none;">📝 报告</a></div>
            <ul style="list-style:none;padding:0;margin:0;font-size:13px;line-height:2;">
                <li><a href="/reports/" style="color:#333;">→ 提交新报告</a></li>
                <li><a href="/reports/" style="color:#333;">→ 我的草稿</a></li>
                <li><a href="/reports/" style="color:#333;">→ 已发布报告</a></li>
            </ul>
        </div>
        <div class="widget">
            <div class="widget-title">📊 数据统计</div>
            <div style="font-size:13px;line-height:2.2;">
                <?php global $wpdb; $counts=[]; $types=['镇物','事件','人物','组织','研究发现','祂们','秘术'];
                foreach ($types as $t) { $c=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key='crrg_report_type_name' AND pm.meta_value=%s AND p.post_status='publish'",$t)); $counts[$t]=(int)$c; }
                $user_count=count_users(); $total_users=$user_count['total_users']??0; ?>
                <div>镇物：<b><?php echo $counts['镇物']; ?></b> 件</div>
                <div>事件：<b><?php echo $counts['事件']; ?></b> 起</div>
                <div>人物：<b><?php echo $counts['人物']; ?></b> 人</div>
                <div>组织：<b><?php echo $counts['组织']; ?></b> 个</div>
                <div>祂们：<b><?php echo $counts['祂们']>0?$counts['祂们']:'██'; ?></b> 项</div>
                <div>人员：<b><?php echo $total_users; ?></b> 人</div>
            </div>
        </div>
    </div>
</div>

<!-- 走进抵御小组 -->
<div style="max-width:1200px;margin:40px auto 0;padding:0 20px;">
    <div style="padding:28px;background:#fff;border:1px solid #e0e0e0;border-radius:4px;border-left:4px solid #1B3A5C;">
        <h2 style="font-size:20px;color:#1B3A5C;margin:0 0 16px;font-weight:bold;">🏛️ 走进抵御小组</h2>
        <div style="font-size:14px;color:#555;line-height:2;">
            <p><strong>中央重生抵御小组</strong>（以下简称"小组"），是中华人民共和国境内唯一专门从事高维超自然威胁监测、评估与遏制的国家建制力量。其前身可追溯至二十世纪五十年代，由古秘术集大成者朱陆爻联合官方组建，历经多次改组、裁撤与重建，终以现行体制延续至今。</p>
            
            <h3 style="font-size:16px;color:#1B3A5C;margin:20px 0 10px;">历史沿革</h3>
            
            <p><strong>草创与建制（1950年代）</strong><br>1950年代前期，解放军入藏期间于雪域高原多次遭遇无法以常理解释的异常现象。与此同时，朱陆爻沿玄子修行路线抵达西藏，凭借《玄子七章秘经》中记载的宇宙真相，确认"重生"这一高维外神正持续对三维世界施加影响。他通过青帮关系联络顾竹轩，进而与官方建立接触，以秘术实证佐证超自然威胁之存在。经中央决策层认可，一支由干部与古秘术传人混编、军方士兵担任外勤支援的专门力量正式成立，初称"中央重生打击小组"，此即小组之雏形。</p>
            
            <p><strong>委员会时期（1960—1970年代）</strong><br>随着国内形势变动，原有双轨领导架构因理念分歧陷入僵局。反右期间，部分秘术传人受到冲击，组织被迫进行深度改组，将领导层改造为具有政治觉悟的党员与资深传人共治体系，同时启用"中央特别事务调查委员会"名义，对外挂牌"中国科学院超自然现象研究组"作为掩护，形成覆盖七大地理区的层级化架构，职能涵盖行动、情报、技术、联络与后勤保障。</p>
            
            <p><strong>动荡与蛰伏（1960—1990年代）</strong><br>1960年代中后期，朱陆爻通过卦象预判两场重大危机即将降临，遂以"破四旧"为名发动全国性镇物收集行动。镇物分为"遗蜕"与"玄造"两类，按影响层级分为人、鬼、魔、神四等，小组实际掌控前三等级。行动初期有效遏制了邪教势力蔓延，但随后失控扩大化，邪教徒伪装混入扰乱局势，最终因镇物争夺间接诱发唐山大地震，行动以惨烈代价中止。此后小组内部凝聚力严重下降，市场经济兴起后，这一"烧钱且有前科"的组织被大幅裁撤，大部分成员流入民间，卷入所谓"气功热"风潮，仅剩少数朱陆爻亲信及后人勉力维系。</p>
            
            <p><strong>重建与回归（1998年至今）</strong><br>1998年朱陆爻逝世，原委员会实质性解散，剩余核心人员重组为"中央重生抵御小组"，职能专一化，聚焦于"重生"复苏事件。此后十余年间，小组处于半沉寂状态，直至朱陆爻曾孙朱贞吉高中毕业后重掌大局，整合残存人脉与器物，尝试将祖辈秘术与现代技术手段对接，为古老的传统注入新的可能。</p>
            
            <h3 style="font-size:16px;color:#1B3A5C;margin:20px 0 10px;">使命与原则</h3>
            <p>小组的根本任务，是监测并遏制高维外神"重生"及其化身（女娲、黄天、弥勒、老子等）对现实世界的渗透，同时防范"荒""命"等其他外域邪神经由高维长城裂隙入侵。行动遵循"秘密、克制、牺牲"三大原则——不公开暴露超自然存在，不使用超出必要限度的力量，并在极端情况下以核心成员献祭为代价换取封印稳定。</p>
            
            <h3 style="font-size:16px;color:#1B3A5C;margin:20px 0 10px;">对外身份</h3>
            <p>小组现行正式名称为"中央重生抵御小组"，隶属中央保密局第九办公室，对外的公开挂牌单位为"中国科学院超自然现象研究组"。实际执行任务时，以人民保卫部特殊勤务司为一线行动代号。自千禧年改组后，小组脱离原委员会大规模行政体系，以精干、高效、隐秘为运作基调。</p>
            
            <p style="text-align:center;color:#999;font-style:italic;">历史从未结束，危机从未远离。中央重生抵御小组，仍在黑暗中守护人间。</p>
        </div>
    </div>
</div>

<!-- 最新文章 + 组员热议 双列 -->
<div style="max-width:1200px;margin:30px auto;padding:0 20px;display:flex;gap:24px;">
    <div style="flex:1;background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:20px;">
        <h3 style="font-size:16px;color:#1B3A5C;margin:0 0 12px;border-bottom:2px solid #C41230;padding-bottom:8px;">📄 最新文章</h3>
        <?php if ($recent): foreach ($recent as $r): $rt = get_post_meta($r->ID,'crrg_report_type_name',true)?:'未分类'; ?>
            <div style="padding:8px 0;border-bottom:1px solid #f5f5f5;">
                <a href="<?php echo get_permalink($r); ?>" style="font-size:13px;color:#333;text-decoration:none;"><?php echo esc_html($r->post_title); ?></a>
                <div style="font-size:10px;color:#999;"><span style="background:#f0f0f0;padding:1px 6px;border-radius:2px;"><?php echo $rt; ?></span> <?php echo get_the_date('m-d',$r); ?></div>
            </div>
        <?php endforeach; else: ?><p style="color:#999;font-size:12px;">暂无文章</p><?php endif; ?>
    </div>
    <div style="flex:1;background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:20px;">
        <h3 style="font-size:16px;color:#1B3A5C;margin:0 0 12px;border-bottom:2px solid #C41230;padding-bottom:8px;">🔥 组员热议</h3>
        <?php if ($hot): foreach ($hot as $t): $likes = count(get_post_meta($t->ID,'crrg_likes',true)?:[]); ?>
            <div style="padding:8px 0;border-bottom:1px solid #f5f5f5;">
                <a href="<?php echo get_permalink($t); ?>" style="font-size:13px;color:#333;text-decoration:none;"><?php echo esc_html($t->post_title); ?></a>
                <span style="color:#C41230;font-size:11px;">❤️ <?php echo $likes; ?></span>
            </div>
        <?php endforeach; else: ?><p style="color:#999;font-size:12px;">暂无热议</p><?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
