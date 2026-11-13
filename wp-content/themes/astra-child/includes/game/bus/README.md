# bus/ · 事件总线与事实表（怎么用，别踩坑）

> 2026-10-14 立。**Phase 0 地基**。规矩是硬的，门禁 `tools/verify-bus.php` 会查。

## 一句话

模块之间**不许互相调函数**。要联动就：**发事件**（`crrg_evt_emit`）或**订阅事件**（`crrg_evt_on`）。
公共发生的事写进**事实表**（`crrg_fact_propose`），个人档内的事照旧用个人 flag。

## 事件名规范

`域.动作`，小写点分。**新增事件必须先登记进 `crrg_evt_known()`**，否则门禁报"不在名单里"。

| 域 | 现有事件 | 什么时候发 |
|---|---|---|
| system | `hour` `day` `tick` | 世界钟过整点／换日／推进器跑了一格 |
| world | `fact` | 有公共事实写入 |
| player | `enter` `use` `check` | 进房／用道具／做检定（成败都在 payload 里） |
| npc | `talked` `plan` | 说话／NPC 自己按计划做事（世界自走） |
| mission | `accept` `beat` `settle` | 接单／节拍推进／结算 |

## 三条纪律（门禁会查）

1. **每个事件名至少有一个订阅者**——发出去没人听＝空转；`crrg_evt_orphans()` 会当场记下来。
2. **订阅者必须幂等**——同一事件重复到达不得出错（tick 可能补算，同一格会被重放）。
3. **订阅每次请求重新注册**（与 `registry.php` 同节拍），不落库 ✓

## 事实表（bus/facts.php）

- **只追加**：不 UPDATE、不 DELETE。要撤回就追加一条相反事实 ✓（多人并发因此无锁 ✓）
- 键名 `域.事`：`b4_water.noticed`、`npc.kang.plan`、`base.first_visit.b4_c2`
- 授权（`crrg_fact_propose($k,$v,$opt,$by)`，三选一）：
  - `mode=once`（默认）独有型：一次写入不可逆——**"选择会回来找你"就靠它**
  - `mode=threshold, need=2` 阈值型：够 N 个不同的人参与才成立（默认 2）
  - `mode=roles, roles=['chairman'], who=>['role'=>'…','lv'=>3]` 职务型：值班/处级以上可单人写入
- 读：`crrg_fact($k,$scope)` 最新 ｜ `crrg_fact_count($k,$scope,true)` 几个人 ｜ `crrg_fact_recent()`

## 世界推进器（bus/tick.php）

- **惰性**：玩家动作时补算"上次到现在"；低频 cron 只是兜底。**不做每分钟定时任务** ✗
- **幂等 + 可重放**：一格 = 一个确定性函数；给同一时刻重跑，落的事实一样 ✓
- **不碰个人档**：个人血/体力/期限一律另一套（按在线时长惰性算）✗
- 订阅：`crrg_tick_on(function ($slotAt, $ctx) { … })`；一格 = 30 游戏分钟；离线过久会"跳格"并记 `system.tick_skipped` ✓

## 加一条联动的正确姿势（例）

```php
// 任务侧：只订阅，不调对话函数
crrg_evt_on('npc.talked', function ($p) {
    if (($p['npc'] ?? '') !== '康明远') return;           // 幂等：只认自己要的
    if (crrg_fact('base.康明远_调离')) return;             // 已经发生过就不重复
    crrg_fact_propose('base.康明远_调离', true, ['mode' => 'once', 'scope' => 'base'], $p['uid'] ?? 0);
}, ['module' => 'mission.example']);
```
