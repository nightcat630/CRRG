<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:atom="http://www.w3.org/2005/Atom">

<xsl:output method="html" encoding="UTF-8" indent="no"
  doctype-public="-//W3C//DTD HTML 4.01 Transitional//EN"/>

<xsl:template match="/rss/channel">
  <html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><xsl:value-of select="title"/> — RSS</title>
    <style>
      body{font-family:"Microsoft YaHei","PingFang SC",sans-serif;max-width:720px;margin:30px auto;padding:0 16px;background:#F0F2F5;color:#1e293b}
      .hdr{background:#C41230;color:#fff;padding:20px 24px;border-radius:6px 6px 0 0}
      .hdr h1{margin:0;font-size:20px}.hdr p{margin:4px 0 0;font-size:12px;opacity:.85}
      .dsc{background:#fff;padding:12px 24px;border:1px solid #e0e0e0;border-top:0;font-size:13px;color:#666}
      .tip{background:#fff;padding:14px 24px;margin-bottom:14px;border:1px solid #e0e0e0;border-radius:6px;font-size:13px;color:#666;line-height:1.7}
      .itm{background:#fff;padding:16px 24px;border:1px solid #e0e0e0;border-top:0}
      .itm h3{margin:0 0 4px;font-size:16px}.itm h3 a{color:#1B3A5C;text-decoration:none}
      .itm h3 a:hover{color:#C41230}.mt{font-size:11px;color:#999;margin-bottom:6px}
      .cat{display:inline-block;margin:0 3px 3px 0;padding:1px 8px;background:#1B3A5C;color:#fff;border-radius:3px;font-size:11px}
      .ftr{text-align:center;padding:16px;font-size:12px;color:#999}.ftr a{color:#1B3A5C;text-decoration:none}
      .itm:last-child{border-radius:0 0 6px 6px}
    </style>
  </head>
  <body>
    <div class="hdr">
      <h1><xsl:value-of select="title"/></h1>
      <p>RSS · <xsl:value-of select="lastBuildDate"/></p>
    </div>
    <div class="dsc"><xsl:value-of select="description"/></div>
    <div class="tip">
      📡 <b>如何订阅？</b> 复制本页地址粘贴到任意 RSS 阅读器（Feedly、Inoreader、Reeder 等）即可。浏览器直接打开仅作预览。
    </div>
    <xsl:for-each select="item">
      <div class="itm">
        <h3>
          <a>
            <xsl:attribute name="href"><xsl:value-of select="link"/></xsl:attribute>
            <xsl:value-of select="title"/>
          </a>
        </h3>
        <div class="mt">
          <xsl:value-of select="dc:creator"/> · <xsl:value-of select="pubDate"/>
        </div>
        <xsl:if test="category">
          <xsl:for-each select="category">
            <span class="cat"><xsl:value-of select="."/></span>
          </xsl:for-each>
        </xsl:if>
        <p><xsl:value-of select="description"/></p>
        <p><a><xsl:attribute name="href"><xsl:value-of select="link"/></xsl:attribute><xsl:text>→ 阅读全文</xsl:text></a></p>
      </div>
    </xsl:for-each>
    <div class="ftr">
      <p><xsl:value-of select="title"/> · <a><xsl:attribute name="href"><xsl:value-of select="link"/></xsl:attribute>访问网站</a></p>
      <p>订阅地址：<code><xsl:value-of select="atom:link/@href"/></code></p>
    </div>
  </body>
  </html>
</xsl:template>

</xsl:stylesheet>
