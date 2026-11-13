# engine/ · 任务引擎使用手册（作者视角）

> 2026-10-14 立。**加一个任务＝照 `missions/task-template.php` 抄一份**，声明式写完即可。
> 背后要读的东西：14 号文（沙盒化设计）、15 号文（调查/对话/战斗排查）、11 号文（改动规矩）。
> 门禁：`php tools/verify-task.php`（定义完整性）＋ `php tools/verify-bus.php`（总线）＋ 四件套。

---

## 一、加一个任务：七步

1. 复制 `missions/task-template.php` → `missions/task-<你的任务>.php`
2. 改 `crrg_task_define('m_你的id', [...])` 里的内容（字段见下表）
3. 在 `functions.php` 那一排 `require_once` 里加一行（放在同类 missions 旁边）
4. `php -l` 改过的文件
5. `php tools/verify-task.php`（定义门禁；任何一条红都别交）
6. 四件套：`verify-game` / `lint-names` / `paths-verify` / 快照 diff ＋ `verify-bus`
7. 变更日志记一段（改了什么、为什么、怎么验的、教训）

---

## 二、字段表（只有这些，够用）

| 字段 | 作用 | 备注 |
|---|---|---|
| `name/level/kind/min_lv/scene/brief_room/depart_room/entry_room/deadline_h/prompt/steps/reward/rep/settle_note` | **老系统字段**，原样透传 | 老面板/老结算/老门禁照旧认 ✓ |
| `trigger` | 怎么被拉起来 | `room` / `rooms` / `night` / `flags` / `not_flags` / `fact` / `min_lv`，可组合；**空条件＝永不触发**（防手滑）|
| `stages` | 阶段（单调，只能前进）| 顺序就是数组顺序；倒退会被拒 ✓ |
| `clues` | 线索表 | 每条 `title/point/src/where/link/to`；**自动登记进笔记台账** ✓ |
| `concl` | **结论层**（治"反网状"）| `need` ＋ `of`（2/3 条就成立）✓ 网要有边 |
| `paths` | **多解法**（≥2）| `name/type/need/check/cost/gain/gain_on_fail/text` |
| `newcomers` | **任务期新人物** | 名字/角色/房间/时段/对话树；**善后即失去对话** ✓ |
| `dlg` | **老人物插支线** | `['npc' => ['键' => ['label','stage','cond','node']]]`；不改原树 ✓ |
| `actors` | **NPC 时段移动** | `['zhou' => ['room'=>'b3_water','phases'=>['下午']]]`；善后自动复原 ✓ |
| `beats` | 世界自走节拍 | `when`（flags/clues/concl/fact）→ `do`（narrate/stage/fact）；幂等 ✓ |
| `outcome` | 结算产出 | `personal / facts / actors / world / archive` 五路 |
| `cleanup` | 善后 | `true` ＝ 引擎全包（撤对话/撤新人/NPC 复原/留线索）✓ |

**代价（cost）只许写个人账**：`minutes`（个人在线钟）/ `expose` / `flag` / `san`。
**不许写任何推世界钟的字段** ✗ —— 门禁 ⑦ 会拦（多人共享的钟谁都不许拨）。

---

## 三、四条纪律（门禁会查）

1. **线索必须有归处**：`clues` 里每条都要被至少一个 `concl.of` 引用 → 否则是散点不是链 ✗
2. **解法至少两条**，且失败要推进（写 `gain_on_fail` / `fail_stage`）——"没成可以再来"不算 ✓
3. **新人物/注入/NPC 移动一律写在任务定义里**（`newcomers/dlg/actors`）✗ 别手写注册函数：
   引擎会按声明装配，善后也由引擎统一撤 ✓（手写的迟早忘删 ✓）
4. **文案不写内部键**（`stage_` / `clue_` / `concl_` / `beat_`）✓ 玩家只该看到人话 ✓

---

## 四、委员长那几条要求，落在哪个字段

| 要求 | 落在哪 |
|---|---|
| 新人物的**分阶段**对话 | `newcomers[..].nodes[..].cond`（`flag` = 已得线索）＋ `effect.clue` |
| 老人物**插入**对话 | `dlg[<npc>][<键>]`（`stage` + `cond` 控制何时出现）|
| 任务结束**失去**对话 | `cleanup: true` → `crrg_dlg_release()` ＋ 事实表开关 `world.ovr_off.<任务>`（跨请求/跨玩家 ✓）|
| 结束后对**账号的 NPC** 有结果 | `outcome.actors`（人情/旗标；"他欠你一次"能写进后续任务的条件）|
| 结束后对**世界**有结果 | `outcome.facts`（公共事实，别的任务/对话能订阅 ✓）＋ `outcome.world.note` |
| **NPC 时间段移动** | `actors` ＋ `crrg_actor_where()`（作息来自名册 `sched`，任务期覆盖优先）|
| **线索系统** | `clues` → 台账（`crrg_note_lines`）＋ `crrg_clue_board()`（已得/未得/去哪找）|
| **检定系统** | `paths[..].check` → 复用 CoC 规则层；三档：干净 / 加代价 / 失败推进 ✓ |
| **网状叙事** | `concl`（线索→结论）＋ `beats`（局面自走）＋ `outcome.facts`（给别的任务留钩子）|
| **多解法** | `paths`（硬来/侦查/找人/花钱…各带代价）|
| **沙盒结合** | `trigger`（世界给的机会）＋ `beats`（玩家不在也变）＋ `clocks`（威胁钟，挂世界钟）|

---

## 五、可扩展点（我在引擎里留的口子）

- `crrg_task_pump()`：每次动作跑一遍（触发＋节拍）；要加"新触发类型"只改 `crrg_task_trigger_ok()` 一处 ✓
- `crrg_task_try($s,$pc,$task,$pathId)`：动作入口；按钮/指令两条路都从这儿走 ✓（铁律：两条路都要能到）
- `crrg_tick_on()`：**每 30 游戏分钟一格**的推进器（世界自走）；任务想搭车就 `crrg_tick_on()` ✓
- `crrg_evt_on()`：事件订阅（`player.enter` / `player.check` / `npc.talked` / `mission.beat`…）✓
- 想再加"任务级检定种类"，扩 `resolve.php` 的 `check` 字段即可 ✓

## 六、已知待办（不给作者用，给接手的人）

- 按钮侧还没有"线索板/任务面板"的入口（现在只有 `笔记 <任务>` 文字入口）→ 属 15 号文 P1.5 的面板部分
- 老任务**尚未**逐个搬进模板（搬一条＝按第二节重写一遍定义；`legacy_steps` 保证不搬也能跑）✓
- `crrg_task_clocks`（威胁钟）字段已留、推进器已通，但**还没有任务真的用**（P1 内容迁移时用）✓


---

## 七、与各系统的对接（P1.9，2026-10-14 起）

**作者永远不直接调系统函数** ✓ 一律写声明，由 `engine/links.php` 转交。

- **need**：`flags` `not_flags` `item`(+`n`) `money` `rep` `lv` `who`(+`fav`) `vitals` `hour` `weather` `travel_node` `players_here` `tasks`
- **cost**：`minutes` `expose` `flag` `san` `hp` `energy` `food` `water` `money` `items` `rep` `fav` `sleep`
- **gain**：`clues` `stage` `stage_text` `items` `money` `rep` `fav` `flags` `fact` `vitals` `sleep` `narrate` `concl`
- **path.type**：`check` / `brute` / `npc` / **`travel`**（行程，必然要写 `dest`）/ **`fight`**（必写 `on_win`+`on_lose`）/ `quick`
- **outcome**：`personal` / `facts` / `actors` / `world` / `archive` / **`on_expire`**（期限到点的后果）

**跨请求的两种**（行程、战斗）都是同一套：**入口带 `ctx` → 出口挂"待落账" → `pump` 收进个人档** ✓
（写错键名＝静默失效 ✗ → `verify-task.php` 的白名单会拦 ✓）
