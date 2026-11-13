# CRRG 演习系统 · 工具手册与守则

> 原则：**工具只放"可重复运行的回归/检查件"**；一次性手术脚本用完归档到 `/root/backups/quarantine-*/`。
> 改游戏代码前先读本文件，尤其**第二、三节**。

---

## 一、工具清单

### 门禁与回归（每次改完必跑）

| 工具 | 用途 | 怎么跑 |
|---|---|---|
| **`verify-game.php`** | **证据式验证（门禁）**：真跑房间/命令/面板按钮/商店/物品/NPC/对话/掉落物/库存/门禁矩阵/任务链/存档迁移/窗口/前端JS… 共 **21 条**；每条报"查了 N 处（下限 M）"；**有错或有跳过 ⇒ 退出码 1** | `php tools/verify-game.php`（**必须 0**） |
| `lint-names.php` | 文本级补充：重复 case／函数重定义／裸 `%`／内部键外泄… | `php tools/lint-names.php` |
| `paths-verify.php` | 《封条》过法数据完整性（节点 52／过法 256／抽样可跑） | `php tools/paths-verify.php` |
| `snapshot-game.php` ＋ `snapshot-game-baseline.json` | **全游戏行为快照（已钉钟 ✓ 可复现）** | 跑完 `diff` 基线 |
| `snapshot-seal.php` ＋ `snapshot-baseline.json` | 《封条》线 100 条固定用例 | 同上 |
| `playtest/`（16 个） | **玩家视角端到端**（新卡零预置真玩）—— 抓过真 bug，别删 | `php tools/playtest/playtest-mid.php` |

### 体检（随时可跑，不改变任何东西）

| 工具 | 用途 |
|---|---|
| `scan-consts.php` | 找出"**用了但没定义**"的常量（今天靠它发现掉落物/库存两个 100% 坏掉的子系统 ✓） |
| `scan-stale-closures.php` | 找出"调用 `$set/$has/$fav…` 却未在函数内定义"（一问就崩的那类 ✗） |
| `scan-architecture.php` | 文件分类与"加一样东西要动几处" |
| `scan-skeletons.php` | 函数骨架聚类（找同构可并的重复） |

### 结构手术（通用、带守卫）

| 工具 | 用途 | 守卫 |
|---|---|---|
| `move-func.php <源> <函数> <目标> [--apply]` | 搬**整函数** | 源里恰好 1 次；搬完**源 0／目标 1**，否则**不落盘** ✓ |
| `split-file.php <任务名> [--apply]` | 按配置把若干函数搬到目标文件 | 同上 ＋ 原子化（先临时文件校验语法）✓ |
| `dlg-migrate.php [--apply]` | **对话 v2 迁移器**：把"纯话题"的旧 NPC 机械转成节点树（`var_export` 原样保文本 ✓；含 need/closed/skill/dyn 的自动跳过 ✓） | 旧内容不丢（逐条比对 ✓） |

---

## 二、目录结构（游戏侧）

```
includes/
  drill-economy.php    315 行：经济系统（货物·商店·宿舍·队伍·车辆·买·出发）
  （其余为**网站**功能：公告/值班/消息/威胁/排行…与游戏无关）
  game/
    registry.php       注册中枢（类型表：room/npc/item/shop/mission/panel/look/cmd/scene/text/dlg2/window）
    core/       (6)    drill.php（229 行，只加载器）+ drill-part-{core,view,panels,exec}
                       ＋ **registry-legacy.php**（注册表/场景访问/卡级/在线时长，自 drill-hub 搬出 ✓）
    systems/   (20+)   coc / vitals / rep / appraise / items / npc / equip / weapons / trade /
                       combat / travel(+events) / world / worldmap / dialogue(+v2) / fate / art /
                       board / dorm / team / looks / notes
    missions/  (11)    missions-base + 封条五段 + 三个引擎
    modules/   (10)    town(4) / chase(4) / wild(1)
    content/   (10+)   纯数据：rooms / items / taxonomy / entities / shops(-town/-stock) /
                       npcs / town-npcs / dlg/（对话树 + README）
    ui/        (4)     windows.php（窗口层）/ panels.php（面板构建）/ actions.php（动作分发）+ README
```

**内容与逻辑的边界**：`content/` 只有数据（`return [...]` 形态或注册调用）；逻辑读中枢或调系统函数 ✓

---

## 三、守则（今天用血换的，逐条都有出处）

1. **内容改动禁止手抄**：必须"逐字提取 → 程序生成 → 对照台/基线比对" ✓
2. **结构性改动**：先写**自包含脚本**（定位→改→语法校验→**失败自动回滚**）✓
3. **删除前先做丢失预检**：问"**删掉的东西别处有没有等价物**" ✓（今天问出 15 个会被误删的节点 ✓）
4. **先文件、后接线**：只有目标文件**存在且 `php -l` 通过**，才允许把它 require 进 `functions.php` ✓
   （我犯过三次 ✗ —— 每次都是"文件没生成，require 先写了"）
5. **原子化**：凡"生成文件＋改写源文件"的工具，必须**先校验、后落盘**；失败**不留半成品** ✓
   （半成品会导致**静默数据丢失**：名册变空而站点仍 200 ✗）
6. **目录重构后必须同步更新工具**：凡"按路径找文件"的检查，一律改成**按能力（函数是否存在）找** ✓
   （搬迁后 `file_exists('includes/drill-items.php')` 守卫让整段检查**静默跳过** ✗）
7. **加载顺序**：新增系统文件必须 require 在"**会用到它的模块**之前"（模块可能在**文件级**调用）✓
8. **拆分器必须有守卫**：搬走的函数在**源里 0 次、目标里恰好 1 次**，否则不落盘 ✓
   （按"含注释的整块文本"删除 → 注释不同则未命中 → **函数重复声明 → 站点 500** ✗）
9. **迁面板／迁窗口必须"两侧验收"**：**面板侧 −N** ✓ **且** 对应的**窗口侧仍 ≥ N** ✓
   （我只核对面板侧 ✗ → "数字对上了，功能却空了" ✗）
10. **绝不"从同一文件读又写"**：`open(f,'w')` 会**先截断** → 再读就是空的 → 写回空 ✗
    （我这样把 `drill-economy.php` 写成过 **0 字节** ✗；正确做法：先 `file_get_contents` 到内存，或用**精确编辑工具** ✓）
11. **测试要用干净且状态正常的环境**：大面积失败时**先怀疑测试前提** ✗
    （我拿"睡着的角色"测指令 ✗ → 12 条命令全"失败"，其实只是被"睡眠守卫"挡住了 ✓）
12. **内部键不外泄**：玩家可见文本里不得出现 `ask`/`bt_start` 这类内部标识符 ✓

---

## 四、改动游戏代码的推荐顺序

```
0. php tools/verify-game.php          ← 门禁（退出码必须 0；它会告诉你"查了多少、错在哪"）
1. php tools/snapshot-game.php /tmp/now.json && diff tools/snapshot-game-baseline.json /tmp/now.json
2. 改（内容改动逐字搬；结构改动走 move-func/split-file；窗口/面板迁移记得**两侧验收**）
3. 再跑 0 与 1；动了《封条》数据还要跑 paths-verify.php
4. 需要时跑体检：scan-consts / scan-stale-closures / scan-skeletons
5. 有意改变行为时，才更新基线（并在变更日志里写明理由）
```

---

## 五、变更日志

所有改动与**踩过的坑**都记在 `/root/重生抵御小组网站/网站维护注意事项/变更日志.md`
—— 出事时先翻它，多数坑已经记过一遍了 ✓
