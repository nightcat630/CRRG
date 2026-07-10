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
    ⚠ <?php echo esc_html($alert['title']); ?>：<?php echo esc_html($alert['content']); ?>
</div>
<?php endif; ?>
<div class="gov-main">
    <div class="gov-content" style="flex: 1;">
        <!-- 公告轮播 -->
        <?php $anns = crrg_get_announcements(); if ($anns): ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:4px;padding:8px 14px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
            <span style="color:#C41230;font-weight:bold;font-size:13px;white-space:nowrap;"> 公告</span>
            <div id="ann-carousel" style="flex:1;overflow:hidden;position:relative;height:20px;">
                <?php foreach ($anns as $i => $ann): ?>
                <div class="ann-item" style="position:absolute;top:0;left:0;right:0;opacity:<?php echo $i===0?1:0; ?>;transition:opacity 0.5s;">
                    <a href="/notices/" style="color:#991b1b;font-size:13px;text-decoration:none;font-weight:600;"><?php echo esc_html($ann['title']); ?></a>
                    <span style="color:#666;font-size:11px;margin-left:6px;">— <?php echo esc_html(mb_strlen($ann['content'])>40 ? mb_substr(strip_tags($ann['content']),0,40).'…' : strip_tags($ann['content'])); ?></span>
                    <span style="color:#999;font-size:10px;margin-left:6px;"><?php echo date('m-d', strtotime($ann['time'])); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <script>
        (function(){var c=document.getElementById('ann-carousel');if(!c)return;var items=c.querySelectorAll('.ann-item'),i=0,n=items.length;if(n<2)return;setInterval(function(){items[i].style.opacity=0;i=(i+1)%n;items[i].style.opacity=1;},4000);})();
        </script>
        <?php endif; ?>
        <!-- 今日要闻 -->
        <div style="padding:16px 0;border-bottom:1px solid #eee;margin-bottom:24px;">
            <h2 style="font-size:20px;color:#1B3A5C;margin:0;font-weight:bold;"> 今日要闻</h2>
            <p style="color:#999;margin:4px 0 0;font-size:13px;">超自然现象每日简报</p>
        </div>
        <?php if ($news_items): $first = array_shift($news_items); $thumb = ''; preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/', $first->post_content, $m); if($m) $thumb=$m[1]; ?>
            <div style="display:flex;gap:20px;margin-bottom:20px;background:#fff;border:1px solid #e0e0e0;border-radius:4px;overflow:hidden;">
                <?php if($thumb): ?><img src="<?php echo esc_url($thumb); ?>" style="width:300px;height:200px;object-fit:cover;flex-shrink:0;" alt=""><?php endif; ?>
                <div style="padding:20px 20px 20px <?php echo $thumb?'0':'20px'; ?>;flex:1;">
                    <a href="<?php echo get_permalink($first); ?>" style="font-size:18px;font-weight:bold;color:#1B3A5C;text-decoration:none;"><?php echo esc_html($first->post_title); ?></a>
                    <div style="font-size:12px;color:#999;margin:8px 0;"><?php echo get_the_date('Y-m-d H:i',$first); ?> · <?php $a=get_userdata($first->post_author); echo $a?esc_html($a->display_name):'未知'; ?></div>
                    <div style="font-size:13px;color:#666;line-height:1.8;"><?php echo wp_trim_words(strip_tags($first->post_content),60); ?></div>
                </div>
            </div>
            <?php if ($news_items): ?>
                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
                <?php foreach ($news_items as $n): ?>
                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:14px;">
                        <a href="<?php echo get_permalink($n); ?>" style="font-size:14px;font-weight:bold;color:#1B3A5C;text-decoration:none;"><?php echo esc_html($n->post_title); ?></a>
                        <div style="font-size:11px;color:#999;margin-top:4px;"><?php echo get_the_date('m-d H:i',$n); ?></div>
                        <div style="font-size:12px;color:#666;margin-top:6px;"><?php echo wp_trim_words(strip_tags($n->post_content),25); ?></div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
            <div class="widget-title"><a href="/notices/" style="color:inherit;text-decoration:none;"> 最新通知</a></div>
            <ul style="list-style:none;padding:0;margin:0;font-size:13px;line-height:2;">
                <?php $anns = crrg_get_announcements(); if ($anns): foreach (array_slice($anns,0,5) as $ann): ?>
                    <li>· <a href="/notices/" style="color:#333;text-decoration:none;"><?php echo esc_html($ann['title']); ?></a></li>
                <?php endforeach; else: ?><li>· 暂无通知</li><?php endif; ?>
            </ul>
        </div>
        <div class="widget">
            <div class="widget-title"><a href="/reports/" style="color:inherit;text-decoration:none;"> 报告</a></div>
            <ul style="list-style:none;padding:0;margin:0;font-size:13px;line-height:2;">
                <li><a href="/reports/" style="color:#333;">→ 提交新报告</a></li>
                <li><a href="/reports/" style="color:#333;">→ 我的草稿</a></li>
                <li><a href="/reports/" style="color:#333;">→ 已发布报告</a></li>
            </ul>
        </div>
        <div class="widget">
            <div class="widget-title"> 数据统计</div>
            <div style="font-size:13px;line-height:2.2;">
                <?php global $wpdb; $counts=[]; $types=['镇物','事件','人物','组织','研究发现','祂们','秘术','优秀员工'];
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
        <h2 style="font-size:20px;color:#1B3A5C;margin:0 0 16px;font-weight:bold;"> 走进抵御小组</h2>
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


<!-- 档案馆导航 -->
<div style="max-width:1200px;margin:30px auto 0;padding:0 20px;">
    <div style="padding:24px;background:#fff;border:1px solid #e0e0e0;border-radius:4px;">
        <h2 style="font-size:20px;color:#1B3A5C;margin:0 0 4px;font-weight:bold;"> 档案馆导航</h2>
        <p style="color:#999;margin:0 0 20px;font-size:13px;">按报告类型分类检索 · 点击类型名查看全部</p>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;">
            <?php
            $archive_types = [
                ['name'=>'镇物','slug'=>'artifacts','icon'=>'','desc'=>'遗蜕与玄造','color'=>'#6B3FA0'],
                ['name'=>'事件','slug'=>'events','icon'=>'','desc'=>'异常事件记录','color'=>'#C41230'],
                ['name'=>'人物','slug'=>'personnel','icon'=>'','desc'=>'关键人物档案','color'=>'#1B3A5C'],
                ['name'=>'组织','slug'=>'organizations','icon'=>'','desc'=>'相关组织情报','color'=>'#2E7D32'],
                ['name'=>'研究发现','slug'=>'research','icon'=>'','desc'=>'研究成果汇编','color'=>'#E65100'],
                ['name'=>'祂们','slug'=>'entities','icon'=>'','desc'=>'高维存在名录','color'=>'#880E4F'],
                ['name'=>'秘术','slug'=>'esoterica','icon'=>'','desc'=>'秘术与仪轨','color'=>'#004D40'],
                ['name'=>'优秀员工','slug'=>'outstanding','icon'=>'','desc'=>'优秀员工表彰','color'=>'#F0A500'],
            ];
            foreach ($archive_types as $at):
                $atype_posts = get_posts([
                    'post_type'=>'post','post_status'=>'publish',
                    'meta_key'=>'crrg_report_type_name','meta_value'=>$at['name'],
                    'posts_per_page'=>3,'orderby'=>'date','order'=>'DESC'
                ]);
                $atype_count = count(get_posts([
                    'post_type'=>'post','post_status'=>'publish',
                    'meta_key'=>'crrg_report_type_name','meta_value'=>$at['name'],
                    'posts_per_page'=>-1,'orderby'=>'date','order'=>'DESC',
                    'fields'=>'ids'
                ]));
            ?>
            <div style="border:1px solid #e8e8e8;border-radius:4px;overflow:hidden;transition:box-shadow 0.2s;">
                <div style="background:<?php echo $at['color']; ?>;color:#fff;padding:10px 14px;display:flex;align-items:center;gap:8px;">
                    <span style="font-size:18px;"><?php echo $at['icon']; ?></span>
                    <a href="/<?php echo $at['slug']; ?>/" style="color:#fff;text-decoration:none;font-weight:bold;font-size:14px;flex:1;"><?php echo $at['name']; ?></a>
                    <span style="background:rgba(255,255,255,0.25);padding:2px 8px;border-radius:10px;font-size:11px;"><?php echo $atype_count; ?>篇</span>
                </div>
                <div style="padding:8px 14px 10px;min-height:60px;">
                    <?php if ($atype_posts): foreach ($atype_posts as $ap): ?>
                        <div style="padding:3px 0;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <a href="<?php echo get_permalink($ap); ?>" style="color:#555;text-decoration:none;">· <?php echo esc_html($ap->post_title); ?></a>
                        </div>
                    <?php endforeach; else: ?>
                        <div style="font-size:12px;color:#ccc;padding:8px 0;text-align:center;">暂无报告</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>


<!-- 最新文章 + 组员热议 双列 -->
<div style="max-width:1200px;margin:30px auto;padding:0 20px;display:flex;gap:24px;">
    <div style="flex:1;background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:20px;">
        <h3 style="font-size:16px;color:#1B3A5C;margin:0 0 12px;border-bottom:2px solid #C41230;padding-bottom:8px;"> 最新文章</h3>
        <?php if ($recent): foreach ($recent as $r): $rt = get_post_meta($r->ID,'crrg_report_type_name',true)?:'未分类'; ?>
            <div style="padding:8px 0;border-bottom:1px solid #f5f5f5;">
                <a href="<?php echo get_permalink($r); ?>" style="font-size:13px;color:#333;text-decoration:none;"><?php echo esc_html($r->post_title); ?></a>
                <div style="font-size:10px;color:#999;"><span style="background:#f0f0f0;padding:1px 6px;border-radius:2px;"><?php echo $rt; ?></span> <?php echo get_the_date('m-d',$r); ?></div>
            </div>
        <?php endforeach; else: ?><p style="color:#999;font-size:12px;">暂无文章</p><?php endif; ?>
    </div>
    <div style="flex:1;background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:20px;">
        <h3 style="font-size:16px;color:#1B3A5C;margin:0 0 12px;border-bottom:2px solid #C41230;padding-bottom:8px;"> 组员热议</h3>
        <?php if ($hot): foreach ($hot as $t): $likes = count(get_post_meta($t->ID,'crrg_likes',true)?:[]); ?>
            <div style="padding:8px 0;border-bottom:1px solid #f5f5f5;">
                <a href="<?php echo get_permalink($t); ?>" style="font-size:13px;color:#333;text-decoration:none;"><?php echo esc_html($t->post_title); ?></a>
                <span style="color:#C41230;font-size:11px;">❤ <?php echo $likes; ?></span>
            </div>
        <?php endforeach; else: ?><p style="color:#999;font-size:12px;">暂无热议</p><?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
