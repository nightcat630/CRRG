<?php
/**
 * RSS Feed XSL 样式注入模板
 * 放入子主题 functions.php
 * 
 * 配套文件: rss-style.xsl (放在子主题根目录)
 * Nginx 需要: text/xsl MIME 类型
 */

// 1. 注入 XSL 样式表引用（仅 rss_tag_pre，不要用 rss2_ns！）
add_action('rss_tag_pre', function () {
    echo '<?xml-stylesheet type="text/xsl" href="'
        . get_stylesheet_directory_uri()
        . '/rss-style.xsl"?>' . "\n";
});

// 2. 改 Content-Type 为 text/xml（Firefox 兼容）
add_filter('feed_content_type', function ($content_type, $type) {
    return 'text/xml';
}, 10, 2);

// ⚠️ 常见错误：
// - 不要同时用 rss_tag_pre 和 rss2_ns，会导致 XSL PI 出现两次
// - rss2_ns 会把 PI 塞进 <rss> 标签属性区，破坏 XML
// - XSL 中不要用 disable-output-escaping="yes"，Firefox 安全策略会拒绝
