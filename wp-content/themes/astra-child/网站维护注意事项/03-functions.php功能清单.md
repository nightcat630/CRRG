# functions.php 功能清单（2026-09 更新，612 行）

> 文件位置：`/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child/functions.php`

## 功能一览（按钩子/行号）

| 行号 | 钩子 | 功能 |
|------|------|------|
| 13 | `show_admin_bar` | 禁用管理栏 |
| 15 | `wp_enqueue_scripts` | 加载样式/脚本 |
| 35 | `astra_single_post_navigation_enabled` | 禁用单篇导航 |
| 38 | `astra_header` → `crrg_custom_header` | 自定义顶部导航 |
| 113 | `astra_header_disable` | 禁用 Astra 默认头部 |
| 114 | `after_setup_theme` | 主题设置 |
| 119 | `astra_footer` → `crrg_custom_footer` | 自定义底部 |
| 138 | `astra_footer_disable` | 禁用 Astra 默认底部 |
| 141 | `login_enqueue_scripts` | 登录页样式 |
| 160-165 | `login_head` / `login_headerurl` / `login_headertext` | 登录页定制（标题"中央重生抵御小组"） |
| 168 | `get_avatar_url` | 头像处理 |
| 186 | `init` | 初始化（含自定义 post type / 重写规则） |
| 207 | `comment_text` | 评论内容处理 |
| 220-224 | `register_form` / `registration_errors` | 注册表单/校验 |
| 232 | `query_vars` | 自定义查询变量 |
| 238 | `redirect_canonical` | 规范重定向 |
| 246 | `pre_get_posts` | 搜索/列表查询过滤 |
| 280 | `template_redirect` | 搜索拦截 + `/register` 跳转 + CRRG-917 彩蛋 |
| 300 | `the_content` | 内容过滤（正文处理） |
| 324 | `wp_footer` | 签到提示 Toast（+2 资历） |
| 341 | `wp_footer` | 收藏/点赞按钮注入 |
| 354 | `wp_ajax_crrg_like_topic` | **论坛话题点赞 AJAX** |
| 368 | `bbp_theme_after_topic_title` | **bbPress 话题标题后注入（点赞按钮）** |
| 374 | `wp_footer` | 前端脚本 |
| 392 | `media_view_strings` | 媒体库文案 |
| 398 | `wp_footer` | 前端脚本 |
| 409 | `wp_insert_comment` | 评论插入钩子 |

## 依赖的 includes 文件

| 文件 | 功能 |
|------|------|
| `includes/announcements.php` | 公告系统 |
| `includes/emergency-alert.php` | 紧急警报横幅 |
| `includes/favorites.php` | 收藏数据层 |
| `includes/rank-system.php` | 资历/等级系统 |

## 注意事项

1. **修改前先备份**：`cp functions.php functions.php.bak.$(date +%Y%m%d_%H%M%S)`
2. **PHP 版本**：服务器 PHP 8.1（宝塔 `/www/server/php/81/bin/php`），`preg_match` 用了 `/u` Unicode 修饰符
3. **WordPress 钩子**：所有自定义逻辑都通过 `add_action` / `add_filter` 挂载，不要直接在文件顶部写裸代码
4. **与 Astra 父主题关系**：这是子主题，CSS 和模板继承 Astra 父主题，不要改 `/wp-content/themes/astra/`
5. **bbPress 集成**：论坛点赞依赖 `bbp_theme_after_topic_title` 和 `wp_ajax_crrg_like_topic`，删改时小心
