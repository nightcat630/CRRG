/* CRRG 终端 · Service Worker
 * 原则：**内容永远走网络**（与站点同步）；只对静态资源做兜底缓存，断网时给一个离线提示页。
 * 需要 HTTPS 才会生效（http 下浏览器不注册 SW，不影响正常使用）。
 */
const CACHE = 'crrg-shell-v1';
const SHELL = ['/app-shell/offline.html', '/app-shell/icon-192.png'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  /* 文档与动态页面：网络优先，失败才给离线页（保证更新同步） */
  if (req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html')) {
    e.respondWith(fetch(req).catch(() => caches.match('/app-shell/offline.html')));
    return;
  }
  /* 静态资源：网络优先，失败用缓存 */
  e.respondWith(fetch(req).catch(() => caches.match(req)));
});
