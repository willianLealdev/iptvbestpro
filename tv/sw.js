/* App do Windows (PWA): sempre busca da internet (listas e vídeos ao vivo não podem vir de cache).
   Sem internet, mostra um aviso em vez da página de erro do navegador. */
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function (e) {
  if (e.request.mode !== 'navigate') return;
  e.respondWith(fetch(e.request).catch(function () {
    return new Response('<!DOCTYPE html><meta charset="utf-8"><title>IPTV Best Pro</title>' +
      '<body style="margin:0;height:100vh;display:flex;align-items:center;justify-content:center;background:#070b18;color:#fff;font:20px Segoe UI,Arial,sans-serif;text-align:center">' +
      '<div><h2>Sem conexão com a internet</h2><p>Verifique a sua conexão e abra o app de novo.</p>' +
      '<p><button onclick="location.reload()" style="font-size:18px;padding:10px 24px;border-radius:10px;border:0;background:#f5b82e">Tentar de novo</button></p></div></body>',
      { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }));
});
