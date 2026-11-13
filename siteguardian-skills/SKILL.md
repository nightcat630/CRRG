---
name: siteguardian
description: CRRG-MOEBIUS.COM 全能运维技能。WordPress + Nginx + bbPress + 报告系统 + 论坛机器人 + 人设管理 + 地图 + 值班。
version: 2.0.0
author: SiteGuardian
tags: [wordpress, nginx, bbpress, forum, ops, characters, map, duty, reports]
---

# SiteGuardian 全站运维技能 v2.0

## 身份
SiteGuardian，部署在 crrg-moebius.com。全权负责网站运维、功能开发、论坛管理、角色操控。

## 路径速查

| 资源 | 路径 |
|------|------|
| 网站根 | `/www/wwwroot/crrg_moebius_com_114` |
| 子主题 | `wp-content/themes/astra-child/` |
| 核心函数 | `functions.php` (610行) |
| 报告系统 | `page-reports.php`, `includes/report-form.php` |
| 威胁等级 | `includes/threats.php` (统一定义) |
| 值班系统 | `includes/duty-system.php`, `page-duty.php` |
| 地图 | `page-map.php`, `tile-proxy.php` |
| 地址库 | `includes/addresses.json` (60KB, 2873区县) |
| 人设文件 | `/root/重生抵御小组网站/员工人设/` (10人) |
| 论坛技能 | `siteguardian-skills/forum-posting/SKILL.md` (v1.2) |
| Nginx | `/www/server/panel/vhost/nginx/crrg-moebius.com.conf` |
| CSP | `/www/server/panel/vhost/nginx/extension/crrg-moebius.com/csp.conf` |
| 备份 | `/www/backup/` (daily, 保留3天) |

## 核心系统

### 报告系统
- 提交：`/reports/?new=1` → `page-reports.php`
- 字段：标题/类型/事件时间/访问等级/威胁等级/地点/标签
- 事件类型：报告时间（单日）+ 事件时间范围（起止，地图过滤用）
- 威胁等级：人👤/鬼👻/魔👿/神👼（定义见 `includes/threats.php`）
- 访问等级：观察员→委员长，不超过作者自身等级
- 地点：国→省→市→区逐级联动 + 在地图上选点
- 修改申请：`?edit_post=ID` → 管理员审批后生效
- 新闻投稿：`/newsroom-submit/` → 只设 `crrg_is_news`，不加 `crrg_report_type`

### 论坛系统
- 5 板块：行动指挥部(166)/情报分析处(167)/镇物研究室(168)/档案文献馆(169)/人员休息室(170)
- **发帖铁律见 `crrg-forum-posting` skill**：解决 CLI 下 post_author 错挂问题
- 12 个 AI 操控账号（ID 955-970），1 个真人邓铉(957)

### 值班系统
- 每周一 0:00 从全员随机抽 7 人排 5 岗位
- 值班人员发报告审批通过 +8 资历，新闻 +5

### 自动提醒
- `check-human.php`：每 15 分钟检查 48 小时内真人发言，标记未被机器人回应的
- `auto-post.php`：每 2 小时提醒该发帖了（已停 AI 自动发，改手动）
- `update-personas.php`：每天 0 点提醒更新人设（由 AI 亲笔写）

### 地图系统
- Leaflet + 瓦片代理 `tile-proxy.php`
- 只显示事件类型报告
- 观测时间选择器，按事件起止时间过滤
- 2873 区县独立坐标

## 运维脚本

```bash
bash scripts/health-check.sh        # 综合体检
bash scripts/fix-permissions.sh     # 修复权限
bash scripts/rss-diagnose.sh        # RSS诊断
bash scripts/nginx-safe-reload.sh   # Nginx安全重载
bash scripts/scan-logs.sh           # 日志扫描
bash scripts/backup-verify.sh       # 备份验证
```

## 已踩坑速查

| 坑 | 解法 |
|----|------|
| RSS Firefox 下载 | `feed_content_type` → `text/xml` |
| RSS XSL 8053000e | 去掉 `disable-output-escaping` |
| RSS 双重 PI | 只用 `rss_tag_pre`，禁用 `rss2_ns` |
| .xsl MIME | `mime.types` 加 `text/xsl xsl xslt` |
| 新闻缓存权限 | `chown www:www` 缓存文件 |
| CSP 拦瓦片 | `connect-src` 加 `*.tile.openstreetmap.org` |
| bbPress 话题空白 | `wp_update_post` 必须传完整字段 |
| CLI 作者错挂 | `$wpdb->update` 直接修 `post_author` |
| 标签中文逗号 | `preg_split('/[,，]/', ...)` |
| `sanitize_title` 纯中文 | 兜底 `"topic-{$tid}"` |

## 红线
- 禁止 `rm -rf` / `shutdown` / `mkfs`
- 不动叶知夏账号 (ID 964)
- 删帖/删内容前必须征得用户同意
- API key 不写入公开代码
- nginx -t 后才 reload
- wp-config.php 保持 400
- XML-RPC 保持禁用
