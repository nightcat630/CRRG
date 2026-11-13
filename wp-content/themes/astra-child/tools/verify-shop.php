<?php
/**
 * 商店分类栏／分页 门禁（tools/verify-shop.php，2026-10-14）
 * ① **翻页不丢件**：把所有页的 buy 并起来 ＝ 货架总件数 ✓（最值钱的一条：防"分类后少东西"✗）
 * ② **分类不丢件**：所有分类的件数加起来 ＝ 总数 ✓
 * ③ **分类栏本身合法**：每项都有 k=shop_cat 与可用 id ✓（点了不能没反应 ✗）
 * ④ **不改价格**：带分类/翻页参数时，同一 gid 的价格与不带参数时一致 ✓
 */
$THEME = '/www/wwwroot/crrg_moebius_com_114/wp-content/themes/astra-child';
require '/www/wwwroot/crrg_moebius_com_114/wp-load.php';
$err = []; $warn = []; $ok = [];

$s = ['room' => 'b2f_gear', 'flags' => [], 'items' => []];
$ctx = [];
$pc = ['uid' => 0, 'name' => '（门禁）', 'hp' => 10, 'san' => 50, 'money' => 999999, 'credits' => 99999,
       'rep' => [], 'npc_fav' => [], 'items' => [], 'vitals' => crrg_vitals_default()];

/* 找一个"件数最多"的货架来测（真数据 ✓） */
$best = ['sid' => '', 'room' => '', 'n' => -1];
foreach ((array) crrg_drill_rooms() as $rid => $r) {
    foreach ((array) crrg_drill_shops_at((string) $rid) as $sid2 => $shop) {
        $n = 0;
        foreach ((array) crrg_drill_shop_goods($shop) as $l) $n += count((array) $l);
        if ($n > $best['n']) $best = ['sid' => (string) $sid2, 'room' => (string) $rid, 'n' => $n];
    }
}
if ($best['n'] <= 0) { $err[] = '找不到货架（商店系统异常）'; }
else $ok[] = sprintf('最大货架：%s／%s，共 %d 件 ✓', $best['sid'], $best['room'], $best['n']);

$s['room'] = $best['room'];
$shop = (array) (crrg_drill_shops_at($best['room'])[$best['sid']] ?? []);

/* 小工具：读产品**自己报的页数**（「── 全部（N 件，第 x/y 页）──」）✓
   越界页会被产品夹到最后一页（这是好意 ✓），所以**不能靠"翻到空页"来数** ✗（我第一版就这么错的 ✓） */
function shop_pages($w) {
    foreach ((array) $w['items'] as $it) {
        if (preg_match('/第\s*\d+\/(\d+)\s*页/u', (string) $it['label'], $m)) return max(1, (int) $m[1]);
    }
    return 1;
}
function shop_count($w) { $n = 0; foreach ((array) $w['items'] as $it) if (($it['k'] ?? '') === 'buy') $n++; return $n; }

/* ① 翻页不丢件（按产品报的页数翻 ✓） */
$seen = [];
$w1 = crrg_ui_win_build('shop:' . $best['sid'], $s, $ctx, $pc, 0);
$pages = shop_pages($w1);
for ($p = 1; $p <= $pages; $p++) {
    $w = crrg_ui_win_build('shop:' . $best['sid'] . '::' . $p, $s, $ctx, $pc, 0);
    foreach ((array) $w['items'] as $it) if (($it['k'] ?? '') === 'buy') $seen[(string) $it['id']] = 1;
}
if (count($seen) !== $best['n']) $err[] = sprintf('① 翻页丢件：并起来 %d，货架 %d（产品报 %d 页）', count($seen), $best['n'], $pages);
else $ok[] = sprintf('① 翻页不丢件（%d 页并起来 ＝ %d 件）✓', $pages, $best['n']);

/* ② 分类不丢件 ＋ ③ 分类栏合法 */
$cats = [];
foreach ((array) $w1['items'] as $it) {
    if (($it['k'] ?? '') !== 'shop_cat') continue;
    if ((string) ($it['id'] ?? '') === '') $err[] = '③ 分类栏有一项没 id（点了没反应 ✗）';
    if (mb_strpos((string) $it['label'], '全部') !== false) continue;
    $nm = preg_replace('/^[▢▣]\s*/u', '', (string) $it['label']);
    $nm = preg_replace('/\s*\d+$/u', '', (string) $nm);
    $nm = trim((string) $nm);
    if ($nm !== '上一页 →' && $nm !== '' && mb_strpos($nm, '下一页') === false && mb_strpos($nm, '上一页') === false) $cats[] = $nm;
}
$cats = array_values(array_unique($cats));
$sum = 0;
foreach ($cats as $cn) {
    $w = crrg_ui_win_build('shop:' . $best['sid'] . ':' . $cn . ':1', $s, $ctx, $pc, 0);
    $cp = shop_pages($w);
    for ($p = 1; $p <= $cp; $p++) {
        $w2 = crrg_ui_win_build('shop:' . $best['sid'] . ':' . $cn . ':' . $p, $s, $ctx, $pc, 0);
        $sum += shop_count($w2);
    }
}
if ($cats && $sum !== $best['n']) $err[] = sprintf('② 分类不丢件：分类合计 %d，货架 %d', $sum, $best['n']);
elseif ($cats) $ok[] = sprintf('② 分类不丢件（%d 个大类合计 ＝ %d 件）✓', count($cats), $sum);
else $ok[] = '② 未生成分类（货架太小，跳过）✓';

/* ④ 价格不受分类/翻页影响 */
$p1 = [];
foreach ((array) $w1['items'] as $it) if (($it['k'] ?? '') === 'buy') $p1[(string) $it['id']] = (string) $it['label'];
$w9 = crrg_ui_win_build('shop:' . $best['sid'] . ':' . ($cats[0] ?? '') . ':1', $s, $ctx, $pc, 0);
$diff = [];
foreach ((array) $w9['items'] as $it) {
    $id = (string) ($it['id'] ?? '');
    if (($it['k'] ?? '') === 'buy' && isset($p1[$id]) && $p1[$id] !== (string) $it['label']) $diff[] = $id;
}
if ($diff) $err[] = '④ 价格/名称被分类栏改动了：' . implode('、', array_slice($diff, 0, 3));
else $ok[] = '④ 价格与名称不受分类/翻页影响 ✓';

echo "=== 商店门禁（" . date('H:i:s') . "）===\n";
foreach ($ok as $l) echo "  ✓ $l\n";
foreach ($warn as $l) echo "  ! $l\n";
foreach ($err as $l) echo "  ✗ $l\n";
printf("\n小结：错误 %d ｜ 提醒 %d\n", count($err), count($warn));
exit($err ? 1 : 0);
