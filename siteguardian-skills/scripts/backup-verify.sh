#!/bin/bash
# === 备份验证 ===
BAK_DIR="/www/backup"
echo "=== 备份验证 ==="

# 检查备份目录
[ ! -d "$BAK_DIR" ] && echo "❌ 备份目录 $BAK_DIR 不存在" && exit 1

# 最新备份
LATEST=$(ls -t "$BAK_DIR"/*.{tar.gz,sql,zip} 2>/dev/null | head -1)
if [ -z "$LATEST" ]; then
    echo "❌ 无备份文件"
    exit 1
fi

echo "📦 最新: $(basename "$LATEST")"
echo "   大小: $(du -sh "$LATEST" | cut -f1)"
echo "   时间: $(stat -c '%y' "$LATEST" | cut -d. -f1)"

# 完整性
SIZE=$(stat -c '%s' "$LATEST")
if [ "$SIZE" -lt 1024 ]; then
    echo "   ❌ 文件过小 (< 1KB)，可能损坏"
else
    echo "   ✅ 大小正常"
fi

# 时效性
AGE=$(( ($(date +%s) - $(stat -c '%Y' "$LATEST")) / 3600 ))
[ $AGE -lt 25 ] && echo "   ✅ 时效正常 (${AGE}h)" || echo "   ⚠️ 备份过期 (${AGE}h)"

# 列出所有备份
echo ""; echo "所有备份:"
ls -lht "$BAK_DIR"/*.{tar.gz,sql,zip} 2>/dev/null | head -10

# 备份脚本
echo ""; echo "Cron 配置:"
crontab -l 2>/dev/null | grep -i backup || echo "  ⚠️ 未找到备份 cron 任务"
