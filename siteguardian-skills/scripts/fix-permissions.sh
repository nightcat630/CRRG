#!/bin/bash
# === 权限修复脚本 ===
# 修复 WordPress 常见文件权限/属主问题

ROOT="/www/wwwroot/crrg_moebius_com_114"
DRY_RUN=false
[[ "$1" == "--dry-run" ]] && DRY_RUN=true

run(){ if $DRY_RUN; then echo "  [DRY] $*"; else eval "$*" && echo "  ✅ $*" || echo "  ❌ $*"; fi; }

echo "=== 权限修复 $(date '+%H:%M:%S') ==="

# 1. 目录属主
echo "[1/5] 修复属主..."
run "chown -R www:www $ROOT"

# 2. 目录权限
echo "[2/5] 目录权限 755..."
run "find $ROOT -type d -exec chmod 755 {} \;"

# 3. 文件权限
echo "[3/5] 文件权限 644..."
run "find $ROOT -type f -exec chmod 644 {} \;"

# 4. 敏感文件
echo "[4/5] 敏感文件 400..."
run "chmod 400 $ROOT/wp-config.php 2>/dev/null"
run "chmod 400 $ROOT/.htaccess 2>/dev/null"

# 5. 缓存目录可写
echo "[5/5] 缓存目录 775..."
for d in wp-content/cache wp-content/uploads wp-content/upgrade; do
    [ -d "$ROOT/$d" ] && run "chmod -R 775 $ROOT/$d"
done

echo "=== 完成 ==="
# 验证关键项
echo "验证:"
stat -c '%U:%G %a %n' $ROOT/wp-config.php 2>/dev/null
stat -c '%U:%G %a %n' $ROOT 2>/dev/null
