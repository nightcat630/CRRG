<?php
function report_form_fields($prefix, $category_val, $access_val, $threat_val, $event_date_val, $event_start_val, $event_end_val, $location_val, $tags_val) {
    $types = ['artifacts'=>'镇物','events'=>'事件','personnel'=>'人物','organizations'=>'组织','research'=>'研究发现','entities'=>'祂们','esoterica'=>'秘术','outstanding'=>'优秀员工','other'=>'其他'];
    $uid = get_current_user_id(); $my_rank = crrg_get_rank($uid);
    $is_events = $category_val === 'events';
    ?>
    <div style="margin-bottom:16px;">
        <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">档案类型</label>
        <select name="<?php echo $prefix; ?>category" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;background:#fff;">
            <?php foreach($types as $k=>$v) echo '<option value="'.$k.'"'.($k===$category_val?' selected':'').'>'.$v.'</option>'; ?>
        </select>
    </div>
    <div style="margin-bottom:16px;">
        <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">报告时间</label>
        <input type="datetime-local" name="<?php echo $prefix; ?>event_date" value="<?php echo $event_date_val ? esc_attr(date('Y-m-d\TH:i', strtotime($event_date_val))) : ''; ?>" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;">
        <span style="font-size:11px;color:#999;">对外显示的发布时间，留空用实际提交时间</span>
    </div>
    <div class="event-range-block" style="<?php echo $is_events?'':'display:none;'; ?>">
        <div style="font-weight:bold;color:#333;font-size:14px;margin:12px 0 6px;">事件时间范围 <span style="font-weight:normal;font-size:11px;color:#999;">（地图过滤用）</span></div>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;">
            <span style="font-size:12px;color:#666;white-space:nowrap;">起始：</span>
            <input type="datetime-local" name="<?php echo $prefix; ?>event_start" value="<?php echo $event_start_val ? esc_attr(date('Y-m-d\TH:i', strtotime($event_start_val))) : ''; ?>" style="flex:1;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;">
            <span style="font-size:12px;color:#666;white-space:nowrap;">结束：</span>
            <input type="datetime-local" name="<?php echo $prefix; ?>event_end" value="<?php echo $event_end_val ? esc_attr(date('Y-m-d\TH:i', strtotime($event_end_val))) : ''; ?>" style="flex:1;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;">
        </div>
        <span style="font-size:11px;color:#999;">起始留空=持续至终点；终点留空=起始后持续至今；均留空=常驻事件</span>
    </div>
    <div style="margin-bottom:16px;">
        <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">访问等级</label>
        <select name="<?php echo $prefix; ?>access_level" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;background:#fff;">
            <?php foreach (crrg_get_accessible_ranks($my_rank) as $r): ?>
                <option value="<?php echo $r['id']; ?>" <?php echo $r['id']===$access_val?'selected':''; ?>><?php echo $r['icon']; ?> <?php echo $r['name']; ?> 及以上可阅</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="margin-bottom:16px;">
        <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">威胁等级</label>
        <select name="<?php echo $prefix; ?>threat_level" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;background:#fff;">
            <option value="">未评级</option>
            <?php $tls = ['ren'=>' 人 — 对人类产生影响','gui'=>' 鬼 — 对神秘生物/古神眷属产生影响','mo'=>' 魔 — 对次级旧日支配者/旧日支配者/古神产生影响','shen'=>' 神 — 对外神产生影响'];
            foreach ($tls as $k=>$v) echo '<option value="'.$k.'"'.($k===$threat_val?' selected':'').'>'.$v.'</option>'; ?>
        </select>
    </div>
    <div style="margin-bottom:16px;">
        <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">事件地点</label>
        <?php $addr = json_decode(file_get_contents(__DIR__ . '/addresses.json'), true); $countries = array_keys($addr); ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <select id="<?php echo $prefix; ?>addr_country" style="flex:1;min-width:100px;padding:8px 10px;border:1px solid #d5d5d5;border-radius:4px;font-size:13px;background:#fff;">
                <option value="">国家</option>
                <?php foreach($countries as $c) echo '<option value="'.$c.'">'.$c.'</option>'; ?>
                <option value="__other__">其他</option>
            </select>
            <select id="<?php echo $prefix; ?>addr_province" style="flex:1;min-width:100px;padding:8px 10px;border:1px solid #d5d5d5;border-radius:4px;font-size:13px;background:#fff;" disabled><option value="">省/自治区</option></select>
            <select id="<?php echo $prefix; ?>addr_city" style="flex:1;min-width:100px;padding:8px 10px;border:1px solid #d5d5d5;border-radius:4px;font-size:13px;background:#fff;" disabled><option value="">市/州</option></select>
            <select id="<?php echo $prefix; ?>addr_county" style="flex:1;min-width:100px;padding:8px 10px;border:1px solid #d5d5d5;border-radius:4px;font-size:13px;background:#fff;" disabled><option value="">区/县</option></select>
        </div>
        <input type="text" name="<?php echo $prefix; ?>location" value="<?php echo esc_attr($location_val); ?>" style="width:100%;margin-top:8px;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;" placeholder="手动输入或上方选择...">
        <input type="hidden" name="<?php echo $prefix; ?>lat"><input type="hidden" name="<?php echo $prefix; ?>lng">
        <button type="button" class="map-pick-btn" style="font-size:12px;padding:4px 12px;background:#1B3A5C;color:#fff;border:none;border-radius:3px;cursor:pointer;">📍 在地图上选点</button>
        <div class="map-pick-div" style="display:none;width:100%;height:300px;margin-top:6px;border:1px solid #d5d5d5;border-radius:4px;"></div>
    </div>
    <div style="margin-bottom:16px;">
    <div style="margin-bottom:16px;">
        <label style="display:block;font-weight:bold;margin-bottom:6px;color:#333;">标签 <span style="font-weight:normal;color:#999;font-size:12px;">（逗号分隔）</span></label>
        <input type="text" name="<?php echo $prefix; ?>tags" value="<?php echo esc_attr($tags_val); ?>" style="width:100%;padding:10px 14px;border:1px solid #d5d5d5;border-radius:4px;font-size:14px;" placeholder="调查报告,始源实体">
    </div>
    <?php
}
