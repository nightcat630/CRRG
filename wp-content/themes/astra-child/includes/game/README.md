# CRRG 演习系统 · 代码结构与扩展约定

> 目标：**加一样东西，只动一个地方**。逻辑只读注册表，不硬编码内容。
> 本文件是"以后怎么加东西"的唯一依据；新增内容前先看它。

---

## 一、目标结构（分阶段迁入，见第四节）

```
includes/game/
  registry.php      ← 注册中枢（已建 ✓ Phase 0）
  core/             引擎骨架：状态读写、卡级/门禁、存档、时钟、场景切换
  content/          纯数据：房间表、物品表、商店货品、NPC 表、遭遇表、长文本
  systems/          系统逻辑：CoC 检定、战斗、物品、交易、生命/精力、声望、估价
  modules/          可插拔玩法模块（自带数据+逻辑，唯一入口 module.php）
      town/         青石镇（房间/NPC/商店/服务/生活）
      chase/        追击线（含 dlg/notes/prose）
      wild/         野外
      seal/         《封条》合约线（引擎 + 五段数据）
  ui/               面板区块、房间描述、地图、AJAX 接口
```

**判定标准**：随便挑一样内容（一个房间／一个 NPC／一件物品／一个合约／一个面板区块／一条命令），
**"添加它需要改几个文件"应当 ≤ 2**（它所在的模块 + 它自己的数据文件）。

---

## 二、注册中枢 API（`game/registry.php`）

```php
crrg_game_register($type, $id, $data, $meta = []);   // 注册/覆盖一条
crrg_game_register_many($type, ['id'=>$data, …]);    // 批量
crrg_game_one($type, $id, $default);                 // 取一条
crrg_game_all($type);                                // 取全部
crrg_game_has($type, $id);                           // 有没有

crrg_game_panel_register($id, $builder, $opts);      // 注册面板区块（order / when / note）
crrg_game_panel_collect($s, $ctx, $pc, $uid);        // 按 order 收集并构建
crrg_game_cmd($name, $fn, ['alias'=>[…],'help'=>…]); // 注册命令处理器
crrg_game_cmd_lookup($name);                         // 查命令
crrg_game_load_modules();                            // 加载 modules/*/module.php
```

**类型清单**（新增类型要在 `crrg_game_types()` 登记并在本文件补一行）：
`room` ｜ `npc` ｜ `item` ｜ `shop` ｜ `mission` ｜ `panel` ｜ `look` ｜ `cmd` ｜ `scene` ｜ `text`

> 注册**不落库**：每次请求由各模块重新注册（与 WP `init` 同节拍）。
> 因此模块入口统一挂在 `init`（**优先级 ≥ 20**，保证中枢已加载）。

---

## 三、扩展配方（照抄即可）

### 1) 加一个房间
```php
// modules/<模块>/room-<名>.php
add_action('init', function () {
    if (!function_exists('crrg_game_register')) return;
    crrg_game_register('room', 'tw_x', [
        't' => '房间名', 'f' => '区域', 'c' => 1, 'd' => '描述',
        'x' => ['出口名' => '目标房间键'],
    ], ['module' => 'town', 'file' => __FILE__]);
}, 21);
```
（**别再改** `drill-part-core.php` 里的房间大表 ✗ —— Phase 1 会把那张表搬进 `content/rooms.php`，届时旧位置只作兼容读取。）

### 2) 加一个 NPC（含话题）
```php
crrg_game_register('npc', 'tw_smith', [
    'name' => '铁匠', 'alias' => ['打铁'], 'room' => 'tw_hardware',
    'look' => '肩上搭着皮围裙，袖口烧了几个洞。',
    'topics' => ['打铁' => ['文本…', 'flag_xxx']],
], ['module' => 'town']);
```
> NPC 出现在房间里**自动生效**（`crrg_npc_here_line()` 会读注册表）。

### 3) 加一件物品
```php
crrg_game_register('item', 'tool_x', ['n' => '名称', 'p' => 30, 'fx' => ['energy' => 5], 'd' => '说明']);
```

### 4) 加一个合约／任务
```php
crrg_game_register('mission', 'm_x', [
    'name' => '合约名', 'level' => 'D', 'kind' => 'daily', 'min_lv' => 1,
    'scene' => 'base', 'brief_room' => 'b2b_hall', 'deadline_h' => 72,
    'steps' => [ ['flag' => 'x_1', 'text' => '第一步'], … ],
], ['module' => 'town']);
```

### 5) 加一个面板区块（**当前最大缺口**）
```php
crrg_game_panel_register('town_shop', function (&$s, &$ctx, &$pc, $uid) {
    if (($s['room'] ?? '') !== 'tw_coop') return null;             // 不在就不显示
    return ['title' => '供销社', 'note' => '按需买', 'items' => [/* ['label'=>…,'k'=>…,'id'=>…] */]];
}, ['order' => 30, 'when' => fn($s, $ctx, $pc) => ($s['room'] ?? '') === 'tw_coop']);
```

### 6) 加一条命令
```php
crrg_game_cmd('吃面', function (&$s, &$pc, $ctx, $uid, $arg) { return ['你吃了面。']; }, ['alias' => ['noodle']]);
```
（**别再往** `crrg_drill_exec` 的巨型 switch 里加 case ✗ —— Phase 4 会让 exec 先查命令注册表。）

---

## 四、迁移阶段（每阶段都可独立验收，不搞大爆炸）

| 阶段 | 内容 | 验收标准 |
|---|---|---|
| **0 ✓ 已完成** | 注册中枢 + 本约定文档 + `modules/` 入口约定 | 中枢 API 冒烟通过；现有行为零变化（快照 100 条一致） |
| 1 | **纯数据**搬进 `content/`：房间表、物品表、商店货品、NPC 表（旧位置保留兼容读取） | `paths-verify`＋`snapshot` 一致；`crrg_game_all('room'/'item')` 能取到全部 |
| 2 | **系统**归入 `systems/`：coc／combat／items／trade／vitals／rep／appraise | 同上＋`lint-names` 0 错 |
| 3 | **模块化**：town／chase／wild／seal 各自 `module.php`，内容与逻辑归位 | 用 `crrg_game_load_modules()` 加载；删掉 `functions.php` 里对应 require 仍正常 |
| 4 | `crrg_drill_exec`（1375 行 ✗）改为**先查命令注册表**，逐步拆出域处理器 | 每条命令迁移前后输出一致（对照＋快照） |

**每阶段结束都跑**：`lint-names.php` → `snapshot-*` 对比基线 → `paths-verify.php`（若动《封条》数据）→ playtest 冒烟。

---

## 五、纪律（今天用血换来的）

1. **内容改动禁止手抄**：必须"逐字提取 → 程序生成 → 对照台比对"。
2. **结构性改动**：先写**自包含脚本**（定位→改→语法校验→失败自动回滚），且**删除前先做丢失预检**（"删掉的东西别处有没有等价物"）。
3. **大文件拆分**：整函数原样搬；每搬一块跑一次基线；`php -l` 全程。
4. **改完必跑**：`lint-names.php` ＋ 与 `tools/snapshot-baseline.json` 对比（除有意微调外应完全一致）。
5. **内部键不外泄**：玩家可见文本里不得出现 `ask`/`bt_start` 这类内部标识符。
