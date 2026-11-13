# SiteGuardian 运维技能包 v1.0

## 安装

```bash
cd /www/wwwroot/crrg_moebius_com_114
tar xzf siteguardian-skills.tar.gz
cd siteguardian-skills
chmod +x scripts/*.sh
```

## 技能列表

| 命令 | 用途 |
|------|------|
| `bash scripts/health-check.sh` | 综合健康检查，输出评分 |
| `bash scripts/fix-permissions.sh` | 修复文件/目录权限 |
| `bash scripts/rss-diagnose.sh` | RSS Feed 完整诊断 |
| `bash scripts/nginx-safe-reload.sh` | Nginx 配置安全重载 |
| `bash scripts/scan-logs.sh 100` | 扫描错误日志 |
| `bash scripts/backup-verify.sh` | 验证备份完整性 |

## 常见问题速查

### 网站打不开
1. `bash scripts/health-check.sh`
2. 若服务挂了：`systemctl restart nginx php8.1-fpm`
3. 若磁盘满：`df -h && du -sh /www/wwwroot/*`

### RSS Feed 异常
1. `bash scripts/rss-diagnose.sh`
2. 常见根因：Content-Type 不对 / XSL 损坏 / XML 非法字符

### 权限错误
1. `bash scripts/fix-permissions.sh --dry-run` （预览）
2. `bash scripts/fix-permissions.sh` （执行）

### 改完 Nginx 配置
1. **绝对不要直接 `nginx -s reload`**
2. `bash scripts/nginx-safe-reload.sh` （自动备份→检查→重载）

## 关键路径

- 网站根目录：`/www/wwwroot/crrg_moebius_com_114`
- 子主题：`wp-content/themes/astra-child/`
- Nginx 配置：`/www/server/panel/vhost/nginx/crrg-moebius.com.conf`
- Nginx 扩展：`/www/server/panel/vhost/nginx/extension/crrg-moebius.com/`
- 备份目录：`/www/backup/`
- 错误日志：`/www/wwwlogs/crrg-moebius.com.error.log`
