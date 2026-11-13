/* CRRG 演习 · 场景示意图（2026-10-22）
 * 设计原则：**完全不碰原页面逻辑** ✓
 *   - 自己找 DOM（找不到就安静退出）
 *   - 用 setInterval 监听「场景示意 · 房间名」这个标题的变化
 *   - 全程 try/catch，出错只在 console 说一声，不影响页面任何功能
 */
(function () {
  'use strict';
  try {
    var BASE = '/wp-content/themes/astra-child/assets/scenes/';

    /* 房间名关键词 → 场景号（精确优先，顺序即优先级） */
    var MAP = [
      ['朱贞吉', 17], ['组长办公室', 17], ['文职办公室', 17],
      /* 2026-10-22：**镇上的房间**（怪胎任务用到的 13 间）——先复用最接近的类 */
      /* 2026-10-22：**镇上的房间用新图**（24–36，不再复用基地图 ✗） */
      ['李家老宅', 24], ['老宅', 24],
      ['河沿', 25],
      ['主街', 26],
      ['旅社', 27],
      ['小学', 28], ['教室', 28],
      ['供销社', 29],
      ['派出所', 30],
      ['卫生院', 31],
      ['废品', 32],
      ['阅览室', 33], ['文化站', 33],
      ['老祠堂', 34], ['祠堂', 34],
      ['书房', 35],
      ['后山', 36], ['岔路', 36],

      ['秘术', 18],
      ['观察室', 19],
      ['货运电梯', 20],
      ['厕所', 21], ['浴室', 21], ['洗漱', 21],
      ['样本存储', 22],
      ['整备室', 23],
      ['值班室', 2], ['指挥大厅', 2], ['情报', 2], ['通讯', 2], ['地图室', 2], ['会议室', 2], ['简报', 2],
      ['技研', 3], ['技术研发', 3], ['实验室', 3], ['分析室', 3], ['标本', 3], ['研究', 3],
      ['档案', 4],
      ['医务', 5], ['医疗', 5],
      ['食堂', 6], ['厨房', 6],
      ['宿舍', 7], ['营房', 7], ['单人间', 7],
      ['仓库', 8], ['物资', 8], ['蓄电池', 8], ['存放区', 8], ['发电', 8], ['变电', 8], ['通风', 8], ['水源', 8],
      ['武器', 9], ['军械', 9],
      ['收容', 10],
      ['车库', 11], ['维修', 11],
      ['训练', 12], ['体能', 12], ['战术模拟', 12],
      ['镇物', 13], ['机柜', 13],
      ['缓冲', 14],
      ['走廊', 15],
      ['楼梯', 1], ['隧道', 1], ['通道', 1], ['电梯', 1], ['出口', 1], ['撤离', 1], ['入口', 1], ['主走廊', 1]
    ];

    var lastTitle = null;

    function theme() {
      var d = document.querySelector('.drl');
      return (d && d.getAttribute('data-theme')) || 'red';
    }

    function sceneNo(title) {
      for (var i = 0; i < MAP.length; i++) {
        if (String(title).indexOf(MAP[i][0]) >= 0) return MAP[i][1];
      }
      return 0;
    }

    function ensureBox() {
      var host = document.getElementById('drl-scene-box');
      if (host) return host;
      var h3 = document.getElementById('drl-art-h');
      if (!h3 || !h3.parentNode) return null;
      host = document.createElement('div');
      host.id = 'drl-scene-box';
      host.style.cssText = 'margin:2px 0 8px;display:none;';
      var img = document.createElement('img');
      img.id = 'drl-scene-img';
      img.alt = '';
      img.style.cssText = 'width:100%;display:block;image-rendering:pixelated;';
      img.onerror = function () { host.style.display = 'none'; };
      host.appendChild(img);
      /* 放在标题下面、原来那个 <pre> 上面 */
      var pre = document.getElementById('drl-art');
      if (pre && pre.parentNode === h3.parentNode) h3.parentNode.insertBefore(host, pre);
      else h3.parentNode.insertBefore(host, h3.nextSibling);
      return host;
    }

    function tick() {
      try {
        var h3 = document.getElementById('drl-art-h');
        if (!h3) return;
        var t = String(h3.textContent || '');
        if (t === lastTitle) return;
        lastTitle = t;
        var name = t.replace(/^.*·\s*/, '').trim();
        var no = sceneNo(name);
        var host = ensureBox();
        if (!host) return;
        if (!no) { host.style.display = 'none'; return; }
        var img = host.querySelector('img');
        img.src = BASE + 'scene' + (no < 10 ? '0' : '') + no + '-' + theme() + '.png';
        host.style.display = 'block';
      } catch (e) { /* 静默 */ }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', tick);
    else tick();
    setInterval(tick, 700);
  } catch (e) {
    if (window.console && console.warn) console.warn('[scene] disabled:', e && e.message);
  }
})();
