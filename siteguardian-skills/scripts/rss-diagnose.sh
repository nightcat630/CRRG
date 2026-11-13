#!/bin/bash
# === RSS Feed 诊断 ===
SITE="${1:-crrg-moebius.com}"
echo "=== RSS Feed 诊断 — $SITE ==="

# 1. HTTP 状态
echo -n "HTTP: "; curl -sI -m5 "https://$SITE/feed/" | head -1

# 2. Content-Type
echo -n "Content-Type: "; curl -sI -m5 "https://$SITE/feed/" | grep -i content-type

# 3. XSL 引用
echo -n "XSL 引用: "; curl -s -m5 "https://$SITE/feed/" | grep -o 'xml-stylesheet[^?]*?' | head -1

# 4. XML 合法性
echo -n "XML 合法性: "
curl -s -m5 "https://$SITE/feed/" | python3 -c "
import sys, xml.etree.ElementTree as ET
try:
    raw = sys.stdin.read()
    feed = ET.fromstring(raw)
    ch = feed.find('channel')
    items = ch.findall('item')
    print(f'合法 ✅ — {ch.findtext(\"title\")}, {len(items)} 篇文章')
except Exception as e:
    print(f'非法 ❌ — {e}')
"

# 5. XSL 文件
echo -n "XSL 文件: "; curl -sI -m5 "https://$SITE/wp-content/themes/astra-child/rss-style.xsl" | head -1
echo -n "XSL MIME: "; curl -sI -m5 "https://$SITE/wp-content/themes/astra-child/rss-style.xsl" | grep -i content-type

# 6. 页脚链接
echo -n "页脚 RSS: "; curl -s -m5 "https://$SITE/" | grep -o 'RSS 订阅[^<]*' | head -1
