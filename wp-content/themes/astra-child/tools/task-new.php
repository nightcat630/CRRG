<?php
/**
 * 任务生成器：php tools/task-new.php m_new_id "任务名"
 * 从 missions/task-template.php 抄一份骨架，填好 id / 名字，存成 missions/task-<slug>.php
 * （只生成骨架，不替你写内容；生成后照 engine/README.md 七步走 ✓）
 */
$THEME = dirname(__DIR__);
if (($argv[1] ?? '') === '' || ($argv[2] ?? '') === '') {
    fwrite(STDERR, "用法：php tools/task-new.php <任务id> \"<任务名>\"\n");
    exit(1);
}
$id = preg_replace('/[^a-z0-9_]/', '', strtolower($argv[1]));
$name = (string) $argv[2];
$slug = preg_replace('/[^a-z0-9_]/', '_', substr($id, 0, 40));
$tpl = $THEME . '/includes/game/missions/task-template.php';
$out = $THEME . '/includes/game/missions/task-' . $slug . '.php';
if (!file_exists($tpl)) { fwrite(STDERR, "模板不在：$tpl\n"); exit(1); }
if (file_exists($out)) { fwrite(STDERR, "已经存在，不覆盖：$out\n"); exit(1); }
$code = file_get_contents($tpl);
$code = str_replace("'m_tpl_example'", "'" . $id . "'", $code);
$code = str_replace("=> '模板样例：任务名'", "=> '" . str_replace("'", "", $name) . "'", $code);
$code = str_replace("clue_m_tpl_example_", "clue_" . $id . "_", $code);
file_put_contents($out, $code);
echo "已生成：", $out, "\n";
echo "下一步：① 写内容 ② 在 functions.php 里 require 它 ③ php tools/verify-task.php ④ 四件套 + verify-bus\n";
