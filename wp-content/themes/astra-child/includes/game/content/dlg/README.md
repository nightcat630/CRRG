# 对话系统 · 怎么写（模板说明）

> 两个东西：**版式**（窗口长什么样）和**内容**（谁说什么话）。
> 版式在 `page-drill.php` 里（改一次全体生效）；内容照 `dlg-template.php` 抄。

---

## 一、内容模板（B）

照 `dlg-template.php` 抄一份 → `dlg-<谁>.php` → 在 `functions.php` 里 require 一行。

| 字段 | 必填 | 说明 |
|---|---|---|
| `npc` | ✓ | NPC id。**必须和 `assets/faces/<id>.png` 同名**，立绘自动对上 ✓ |
| `name` | ✓ | 显示名（窗口标题、话题按钮都用它） |
| `room` | ✓ | 常驻房间；NPC 按作息走动时以 `crrg_actor_where()` 为准 |
| `topics[键]` | ✓ | 每个键是一个话题 |
| `topics[].label` | ✓ | 按钮上的字。**不许写内部键**（`stage_`/`clue_`），玩家只该看到人话 |
| `topics[].text` | ✓ | NPC 说的话（数组，逐行）。写"人话"，不写状态 |
| `topics[].once` | | `true` = 只能问一次 |
| `topics[].need` | | 出现条件：`['flag'=>[...]]` / `['item'=>...]` / `['rep'=>...]` |
| `topics[].stage` | | 只在某任务阶段出现（配合引擎的 `dlg` 注入） |
| `topics[].effect` | | 问完给什么：`clue` / `rep` / `flag` / `fav`（好感） |

**三条纪律**
1. **文案不带内部键**；2. **立绘靠 id 对图**，别在文案里解释"这是我的画像"✗；3. **一次只加一个 NPC 的话题**，别把别人的塞进来。

---

## 二、版式模板（A）

窗口数据里加一个字段就能换版式：

```php
return ['title'=>'对话', 'face'=>“kang”, 'layout'=>2, 'note'=>…, 'text'=>…, 'items'=>…];
```

| `layout` | 版式 | 适用 |
|---|---|---|
| **1**（默认） | 立绘居中在上，正文在下（竖排） | 单人对话、说明 |
| **2** | 立绘在左、文字在右（横排，窗口更宽） | 长对话、要读很多字 |
| **3** | 不显示立绘，纯文字 | 系统提示、列表页 |

对应 class：`w-portrait` 固定在窗口体顶端；`layout=2` 时整块改成两栏（CSS 里 `.w-l2`）。
**立绘位永远预留**——有图放图，没图放占位，不会因为缺图把版式撑塌。

---

## 三、自检

```bash
php -l includes/game/content/dlg/dlg-<谁>.php
php tools/verify-game.php     # 「对话：能问出话」（下限 3）
```
