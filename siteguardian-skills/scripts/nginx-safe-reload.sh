#!/bin/bash
# === Nginx 安全检查 & 重载 ===
# 修改配置后的安全流程

CONF_DIR="/www/server/nginx/conf"
VHOST_DIR="/www/server/panel/vhost/nginx"

echo "=== Nginx 安全流程 ==="

# 1. 备份
echo "[1/3] 备份当前配置..."
BACKUP="/www/backup/nginx-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP" && cp -r $CONF_DIR $VHOST_DIR "$BACKUP" 2>/dev/null
echo "  备份至 $BACKUP"

# 2. 语法检查
echo "[2/3] 语法检查..."
if nginx -t 2>&1; then
    echo "  ✅ 语法正确"
else
    echo "  ❌ 语法错误，请修复后重试"
    exit 1
fi

# 3. 平滑重载
echo "[3/3] 重载配置..."
nginx -s reload && echo "  ✅ 重载成功" || echo "  ❌ 重载失败"
