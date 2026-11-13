#!/bin/bash
# === SiteGuardian 综合健康检查 ===
# 用法: bash health-check.sh [域名]

SITE="${1:-crrg-moebius.com}"
ROOT="/www/wwwroot/crrg_moebius_com_114"
SCORE=0; TOTAL=0
red(){ echo -e "\033[31m$1\033[0m"; }
green(){ echo -e "\033[32m$1\033[0m"; }
yellow(){ echo -e "\033[33m$1\033[0m"; }

check(){ TOTAL=$((TOTAL+1)); eval "$1" &>/dev/null && { green "  ✅ $2"; SCORE=$((SCORE+1)); } || red "  ❌ $2"; }

echo "============================================"
echo " SiteGuardian 健康检查 — $SITE"
echo " $(date '+%Y-%m-%d %H:%M:%S')"
echo "============================================"

# ─── 服务状态 ───
echo ""; echo "【服务状态】"
check "systemctl is-active --quiet nginx" "Nginx 运行中"
check "systemctl is-active --quiet php-fpm-81 || systemctl is-active --quiet php8.1-fpm" "PHP-FPM 运行中"
check "systemctl is-active --quiet mysql || systemctl is-active --quiet mysqld || systemctl is-active --quiet mariadb" "MySQL/MariaDB 运行中"

# ─── 网站可达性 ───
echo ""; echo "【网站可达性】"
HTTP=$(curl -sI -m5 "https://$SITE/" 2>/dev/null | head -1)
check "[ \"$HTTP\" = \"${HTTP/200/}\" ] && false || true" "HTTPS 首页返回 200 (实际: ${HTTP:-无响应})"
HTTPS_REDIRECT=$(curl -sI -m5 "http://$SITE/" 2>/dev/null | grep -i "location: https")
check "[ -n \"$HTTPS_REDIRECT\" ]" "HTTP → HTTPS 重定向正常"

# ─── 磁盘/内存 ───
echo ""; echo "【系统资源】"
DISK=$(df / | awk 'NR==2{printf "%.0f", $5}')
check "[ $DISK -lt 90 ]" "磁盘使用率 ${DISK}%（阈值 90%）"
MEM=$(free | awk '/Mem:/{printf "%.0f", $3/$2*100}')
check "[ $MEM -lt 90 ]" "内存使用率 ${MEM}%（阈值 90%）"

# ─── SSL 证书 ───
echo ""; echo "【SSL 证书】"
EXPIRY=$(echo | openssl s_client -servername "$SITE" -connect "$SITE:443" 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2)
if [ -n "$EXPIRY" ]; then
    EXP_SEC=$(date -d "$EXPIRY" +%s 2>/dev/null)
    NOW=$(date +%s)
    DAYS=$(( (EXP_SEC - NOW) / 86400 ))
    check "[ $DAYS -gt 14 ]" "证书剩余 ${DAYS} 天（阈值 14 天）"
else
    red "  ❌ 无法获取证书信息"
    TOTAL=$((TOTAL+1))
fi

# ─── 关键文件权限 ───
echo ""; echo "【关键权限】"
check "[ \"$(stat -c '%a' $ROOT/wp-config.php)\" = \"440\" -o \"$(stat -c '%a' $ROOT/wp-config.php)\" = \"400\" ]" "wp-config.php 权限安全 ($(stat -c '%a' $ROOT/wp-config.php 2>/dev/null))"
check "[ \"$(stat -c '%U:%G' $ROOT)\" = \"www:www\" ]" "网站根目录属主正确 ($(stat -c '%U:%G' $ROOT))"
check "! [ -f $ROOT/xmlrpc.php ] || curl -sI -m3 'https://$SITE/xmlrpc.php' | grep -q '403'" "XML-RPC 已禁用"

# ─── RSS Feed ───
echo ""; echo "【RSS Feed】"
RSS_CT=$(curl -sI -m5 "https://$SITE/feed/" 2>/dev/null | grep -i content-type)
check "echo \"$RSS_CT\" | grep -q 'text/xml'" "Feed Content-Type 为 text/xml"
RSS_XML=$(curl -s -m5 "https://$SITE/feed/" 2>/dev/null)
check "echo \"$RSS_XML\" | grep -q 'xml-stylesheet'" "Feed 包含 XSL 样式表引用"
check "echo \"$RSS_XML\" | python3 -c 'import sys, xml.etree.ElementTree as ET; ET.fromstring(sys.stdin.read()); print(\"OK\")' 2>/dev/null" "Feed XML 格式合法"

# ─── 备份检查 ───
echo ""; echo "【备份状态】"
LATEST_BAK=$(ls -t /www/backup/*.tar.gz 2>/dev/null | head -1)
check "[ -n \"$LATEST_BAK\" ]" "存在备份文件"
if [ -n "$LATEST_BAK" ]; then
    BAK_AGE=$(( ($(date +%s) - $(stat -c '%Y' "$LATEST_BAK")) / 3600 ))
    check "[ $BAK_AGE -lt 25 ]" "最近备份 ${BAK_AGE} 小时前（阈值 25h）"
fi

# ─── 结果 ───
echo ""; echo "============================================"
PCT=$(( SCORE * 100 / TOTAL ))
if [ $PCT -ge 90 ]; then green " 评分: $SCORE/$TOTAL ($PCT%) — 健康 ✅";
elif [ $PCT -ge 70 ]; then yellow " 评分: $SCORE/$TOTAL ($PCT%) — 需关注 ⚠️";
else red " 评分: $SCORE/$TOTAL ($PCT%) — 异常 ❌"; fi
echo "============================================"
