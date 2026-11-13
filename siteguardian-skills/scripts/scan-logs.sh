#!/bin/bash
# === 紧急日志扫描 ===
# 用法: bash scan-logs.sh [行数]

LINES="${1:-50}"
ROOT="/www/wwwroot/crrg_moebius_com_114"
ERR_LOG="$ROOT/error.log"
NGINX_ERR="/www/wwwlogs/crrg-moebius.com.error.log"
PHP_ERR=$(ls /www/server/php/*/var/log/php_errors.log 2>/dev/null | head -1)

echo "=== 日志扫描 (最近 $LINES 行) ==="

echo ""; echo "【WordPress error.log】"
[ -f "$ERR_LOG" ] && tail -$LINES "$ERR_LOG" | grep -i "error\|fatal\|warning" | tail -20 || echo "  无"

echo ""; echo "【Nginx 错误日志】"
[ -f "$NGINX_ERR" ] && tail -$LINES "$NGINX_ERR" | tail -15 || echo "  文件不存在"

echo ""; echo "【PHP 错误日志】"
[ -f "$PHP_ERR" ] && tail -$LINES "$PHP_ERR" | grep -i "error\|fatal" | tail -15 || echo "  文件不存在"

echo ""; echo "【系统日志 (OOM/杀进程)】"
dmesg -T 2>/dev/null | tail -20 | grep -i "oom\|kill" || journalctl -k --no-pager -n 20 2>/dev/null | grep -i "oom\|kill" || echo "  无"

echo ""; echo "【最近登录】"
last -5 2>/dev/null
