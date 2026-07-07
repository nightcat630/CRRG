# functions.php 功能清单

> 文件位置：`/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child/functions.php`

## 功能一览

| 行号（约） | 功能 | 说明 |
|-----------|------|------|
| 1-15 | 主题头部注释 | Astra 子主题声明 |
| 30-40 | **搜索表单 HTML** | 顶部导航栏搜索框 |
| 40-55 | 导航菜单 | 顶部导航链接 |
| ~60-195 | 报告提交验证 | 用户提交报告的字段校验 |
| 199-226 | **`template_redirect`** | 搜索拦截 + 注册跳转 |
| 228-260 | **收藏按钮** | 文章底部 ⭐ 收藏/取消 |
| 262-290 | **收藏 AJAX** | 前端异步收藏逻辑 |
| 292-310 | **每日签到提示** | 底部 Toast：+2 资历 |
| 312-340 | **论坛点赞** | 话题 ❤️ 点赞/取消 |

---

## 关键代码位置速查

| 需要改什么 | 去第几行 |
|-----------|---------|
| 搜索框样式/文案 | 34-36 |
| 搜索校验规则（前端） | 35（`pattern` 属性） |
| 搜索校验规则（后端） | 213-226 |
| 彩蛋关键词 | 218（`crrg-917`） |
| 收藏按钮样式 | 228-260 |
| 签到提示文案 | 292-310 |
| 论坛点赞逻辑 | 312-340 |

---

## 依赖的 includes 文件

`functions.php` 本身不直接 require 其他文件，但主题依赖以下 includes：

| 文件 | 功能 |
|------|------|
| `includes/announcements.php` | 公告系统 |
| `includes/emergency-alert.php` | 紧急警报横幅 |
| `includes/favorites.php` | 收藏数据层 |
| `includes/rank-system.php` | 资历/等级系统 |

---

## 注意事项

1. **修改前先备份**：`cp functions.php functions.php.bak.$(date +%Y%m%d_%H%M%S)`
2. **PHP 版本**：服务器运行 PHP 7.4+，`preg_match` 使用了 `/u` Unicode 修饰符
3. **WordPress 钩子**：所有自定义逻辑都通过 `add_action` / `add_filter` 挂载，不要直接在文件顶部写裸代码
4. **与 Astra 父主题关系**：这是子主题，CSS 和模板会继承 Astra 父主题，不要修改 `/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra/`
