/*
 * IPTV Best Pro — app de TV (LG webOS e Samsung Tizen)
 * Mesmo painel do app Android: ativação por código, listas do aparelho, TV ao vivo, filmes, séries.
 * Escrito em JavaScript antigo (ES5) para funcionar também em TVs mais antigas.
 */
(function () {
  'use strict';

  var SRV = 'https://iptvbestpro.com';
  var APP_ID = 'com.iptvbestpro.app';
  var VER = '1.0.0';
  var ADULT = /adult|adulto|xxx|\+18|18\+|porn|hot\b/i;

  /* ---------------- utilidades ---------------- */
  var LS = {
    get: function (k, d) { try { var v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
    del: function (k) { try { localStorage.removeItem(k); } catch (e) {} },
    getJ: function (k, d) { try { var v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; } },
    setJ: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
  };
  function $(id) { return document.getElementById(id); }
  function esc(s) { return String(s === undefined || s === null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function b64(s) { return window.btoa(unescape(encodeURIComponent(s))); }
  function unb64(s) { try { return decodeURIComponent(escape(window.atob(s))); } catch (e) { return s || ''; } }
  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function fmtTime(sec) { sec = Math.max(0, Math.floor(sec || 0)); var h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60; return (h ? h + ':' + pad(m) : m) + ':' + pad(s); }
  function hhmm(ts) { var d = new Date(ts * 1000); return pad(d.getHours()) + ':' + pad(d.getMinutes()); }

  var UA = navigator.userAgent || '';
  var PLATFORM = /Tizen/i.test(UA) ? 'samsung_tv' : 'lg_tv';
  var DID = LS.get('bp_did', '');
  if (!DID) {
    DID = 'tv';
    for (var i = 0; i < 14; i++) DID += '0123456789abcdef'.charAt(Math.floor(Math.random() * 16));
    LS.set('bp_did', DID);
  }

  /* ---------------- idiomas ---------------- */
  var T = {
    pt: {
      act_title: 'Ative este aparelho', act_msg: 'Digite o código da sua revenda (6 letras) ou da sua assinatura (8 números).',
      act_dev: 'ID do aparelho', act_btn: 'Ativar', act_check: 'Checar agora', act_wait: 'Aguarde o revendedor liberar',
      act_wait_msg: 'Seu código foi enviado. Esta tela verifica sozinha a cada 5 segundos.', act_other: 'Usar outro código',
      act_expired: 'Sua ativação venceu', act_expired_msg: 'Fale com a sua revenda para renovar.', act_invalid: 'Código inválido. Use 6 letras ou 8 números.',
      act_help: 'Peça o seu código de ativação ao seu fornecedor ou revenda.',
      no_net: 'Sem conexão com a internet. Verifique e tente de novo.', server_err: 'Não foi possível falar com o servidor. Tente de novo.',
      no_list: 'Nenhuma lista neste aparelho', no_list_msg: 'Peça para a sua revenda adicionar a sua lista no painel.', retry: 'Tentar de novo',
      list_err: 'Não foi possível carregar esta lista. Fale com a sua revenda.',
      live: 'TV AO VIVO', movies: 'FILMES', series: 'SÉRIES', playlist: 'PLAYLIST', settings: 'CONFIGURAÇÕES',
      categories: 'Categorias', loading: 'Carregando…', empty: 'Nada por aqui.', now: 'Agora', next: 'A seguir', no_epg: 'Sem guia de programação',
      play: 'Assistir', resume: 'Continuar de', from_start: 'Do início', continue: 'Continuar assistindo', season: 'Temporada',
      reconnect: 'Reconectando…', ch_off: 'Este canal está fora do ar no momento.', play_err: 'Não foi possível reproduzir agora.',
      connected: 'Conectado', language: 'Idioma', device: 'ID do aparelho', support: 'Suporte', refresh: 'Atualizar lista', about: 'Sobre',
      refresh_ok: 'Lista atualizada.', sup_talk: 'Fale com a sua revenda para ter suporte.', sup_none: 'Contato de suporte não cadastrado.',
      sup_scan: 'Aponte a câmera do celular para abrir o WhatsApp', close: 'Fechar',
      exp_title: 'Seu plano está vencendo', exp_days: 'Seu plano vence em {0} dia(s).', exp_today: 'Seu plano vence hoje.',
      exp_reseller: 'Fale com a sua revenda para renovar e continuar assistindo.', exp_support: 'Fale com o suporte para renovar e continuar assistindo.',
      exit_q: 'Sair do app?', yes: 'Sim', no: 'Não'
    },
    en: {
      act_title: 'Activate this device', act_msg: 'Enter your reseller code (6 letters) or your subscription code (8 numbers).',
      act_dev: 'Device ID', act_btn: 'Activate', act_check: 'Check now', act_wait: 'Waiting for your reseller',
      act_wait_msg: 'Your code was sent. This screen checks automatically every 5 seconds.', act_other: 'Use another code',
      act_expired: 'Your activation has expired', act_expired_msg: 'Contact your reseller to renew.', act_invalid: 'Invalid code. Use 6 letters or 8 numbers.',
      act_help: 'Ask your provider or reseller for your activation code.',
      no_net: 'No internet connection. Check it and try again.', server_err: 'Could not reach the server. Try again.',
      no_list: 'No playlist on this device', no_list_msg: 'Ask your reseller to add your list in the panel.', retry: 'Try again',
      list_err: 'Could not load this list. Contact your reseller.',
      live: 'LIVE TV', movies: 'MOVIES', series: 'SERIES', playlist: 'PLAYLIST', settings: 'SETTINGS',
      categories: 'Categories', loading: 'Loading…', empty: 'Nothing here.', now: 'Now', next: 'Next', no_epg: 'No program guide',
      play: 'Play', resume: 'Resume from', from_start: 'From start', continue: 'Continue watching', season: 'Season',
      reconnect: 'Reconnecting…', ch_off: 'This channel is offline right now.', play_err: 'Could not play right now.',
      connected: 'Connected', language: 'Language', device: 'Device ID', support: 'Support', refresh: 'Refresh list', about: 'About',
      refresh_ok: 'List refreshed.', sup_talk: 'Contact your reseller for support.', sup_none: 'No support contact registered.',
      sup_scan: 'Point your phone camera at the code to open WhatsApp', close: 'Close',
      exp_title: 'Your plan is about to expire', exp_days: 'Your plan expires in {0} day(s).', exp_today: 'Your plan expires today.',
      exp_reseller: 'Talk to your reseller to renew and keep watching.', exp_support: 'Contact support to renew and keep watching.',
      exit_q: 'Exit the app?', yes: 'Yes', no: 'No'
    },
    es: {
      act_title: 'Activa este dispositivo', act_msg: 'Escribe el código de tu revendedor (6 letras) o de tu suscripción (8 números).',
      act_dev: 'ID del dispositivo', act_btn: 'Activar', act_check: 'Verificar ahora', act_wait: 'Espera que tu revendedor lo libere',
      act_wait_msg: 'Tu código fue enviado. Esta pantalla verifica sola cada 5 segundos.', act_other: 'Usar otro código',
      act_expired: 'Tu activación venció', act_expired_msg: 'Habla con tu revendedor para renovar.', act_invalid: 'Código inválido. Usa 6 letras u 8 números.',
      act_help: 'Pide tu código de activación a tu proveedor o revendedor.',
      no_net: 'Sin conexión a internet. Revisa e intenta de nuevo.', server_err: 'No fue posible conectar con el servidor. Intenta de nuevo.',
      no_list: 'Ninguna lista en este dispositivo', no_list_msg: 'Pide a tu revendedor que agregue tu lista en el panel.', retry: 'Intentar de nuevo',
      list_err: 'No fue posible cargar esta lista. Habla con tu revendedor.',
      live: 'TV EN VIVO', movies: 'PELÍCULAS', series: 'SERIES', playlist: 'PLAYLIST', settings: 'CONFIGURACIÓN',
      categories: 'Categorías', loading: 'Cargando…', empty: 'Nada por aquí.', now: 'Ahora', next: 'A continuación', no_epg: 'Sin guía de programación',
      play: 'Ver', resume: 'Continuar desde', from_start: 'Desde el inicio', continue: 'Seguir viendo', season: 'Temporada',
      reconnect: 'Reconectando…', ch_off: 'Este canal está fuera del aire ahora.', play_err: 'No fue posible reproducir ahora.',
      connected: 'Conectado', language: 'Idioma', device: 'ID del dispositivo', support: 'Soporte', refresh: 'Actualizar lista', about: 'Acerca de',
      refresh_ok: 'Lista actualizada.', sup_talk: 'Habla con tu revendedor para tener soporte.', sup_none: 'Contacto de soporte no registrado.',
      sup_scan: 'Apunta la cámara del celular al código para abrir WhatsApp', close: 'Cerrar',
      exp_title: 'Tu plan está por vencer', exp_days: 'Tu plan vence en {0} día(s).', exp_today: 'Tu plan vence hoy.',
      exp_reseller: 'Habla con tu revendedor para renovar y seguir viendo.', exp_support: 'Habla con soporte para renovar y seguir viendo.',
      exit_q: '¿Salir de la app?', yes: 'Sí', no: 'No'
    }
  };
  var LANG = LS.get('bp_lang', '');
  if (!LANG) { var nl = (navigator.language || 'en').toLowerCase(); LANG = nl.indexOf('pt') === 0 ? 'pt' : (nl.indexOf('es') === 0 ? 'es' : 'en'); }
  function t(k, a) { var s = (T[LANG] && T[LANG][k]) || T.en[k] || k; if (a !== undefined) s = s.replace('{0}', a); return s; }

  /* ---------------- ícones (SVG simples, funcionam em qualquer TV) ---------------- */
  function svg(path, size) { return '<svg width="' + (size || 96) + '" height="' + (size || 96) + '" viewBox="0 0 24 24" fill="#fff">' + path + '</svg>'; }
  var IC = {
    tv: '<path d="M21 3H3a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5v2h8v-2h5a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm0 14H3V5h18v12z"/>',
    movie: '<path d="M18 4l2 4h-3l-2-4h-2l2 4h-3l-2-4H8l2 4H7L5 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V4h-4z"/>',
    series: '<path d="M4 6H2v14a2 2 0 0 0 2 2h14v-2H4V6zm16-4H8a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm-8 12.5v-9l6 4.5-6 4.5z"/>',
    list: '<path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>',
    gear: '<path d="M19.14 12.94a7.07 7.07 0 0 0 0-1.88l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.61-.22l-2.39.96a7 7 0 0 0-1.62-.94l-.36-2.54A.5.5 0 0 0 13.9 2h-3.84a.5.5 0 0 0-.49.42l-.36 2.54a7 7 0 0 0-1.62.94l-2.39-.96a.5.5 0 0 0-.61.22L2.67 8.48a.5.5 0 0 0 .12.64l2.03 1.58a7.07 7.07 0 0 0 0 1.88l-2.03 1.58a.5.5 0 0 0-.12.64l1.92 3.32c.13.22.39.3.61.22l2.39-.96c.5.38 1.04.7 1.62.94l.36 2.54c.05.24.25.42.49.42h3.84c.24 0 .44-.18.49-.42l.36-2.54a7 7 0 0 0 1.62-.94l2.39.96c.22.08.48 0 .61-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58zM12 15.6a3.6 3.6 0 1 1 0-7.2 3.6 3.6 0 0 1 0 7.2z"/>',
    globe: '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm6.93 6h-2.95a15.65 15.65 0 0 0-1.38-3.56A8.03 8.03 0 0 1 18.93 8zM12 4.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96zM4.26 14a8.2 8.2 0 0 1 0-4h3.38a16.5 16.5 0 0 0 0 4H4.26zm.82 2h2.95c.32 1.25.78 2.45 1.38 3.56A7.99 7.99 0 0 1 5.08 16zM8.03 8H5.08a7.99 7.99 0 0 1 4.33-3.56A15.65 15.65 0 0 0 8.03 8zM12 19.96c-.83-1.2-1.48-2.53-1.91-3.96h3.82c-.43 1.43-1.08 2.76-1.91 3.96zM14.34 14H9.66a14.7 14.7 0 0 1 0-4h4.68a14.7 14.7 0 0 1 0 4zm.25 5.56c.6-1.11 1.06-2.31 1.38-3.56h2.95a8.03 8.03 0 0 1-4.33 3.56zM16.36 14a16.5 16.5 0 0 0 0-4h3.38a8.2 8.2 0 0 1 0 4h-3.38z"/>',
    id: '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
    chat: '<path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm-7 12h-2v-2h2v2zm0-4h-2V6h2v4z"/>',
    sync: '<path d="M12 4V1L8 5l4 4V6a6 6 0 0 1 6 6 6 6 0 0 1-.7 2.8l1.46 1.46A8 8 0 0 0 20 12a8 8 0 0 0-8-8zm0 14a6 6 0 0 1-6-6c0-1.01.25-1.97.7-2.8L5.24 7.74A8 8 0 0 0 4 12a8 8 0 0 0 8 8v3l4-4-4-4v3z"/>',
    info: '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>'
  };

  /* ---------------- estado ---------------- */
  var S = {
    screen: '', keys: null, brand: { name: 'IPTV Best Pro', logo: SRV + '/assets/images/brand/iptvbestpro_logo.png' },
    status: {}, pls: [], pl: null, login: null, base: '', modal: null
  };
  var stage = $('stage');

  function fit() {
    var s = Math.min(window.innerWidth / 1920, window.innerHeight / 1080);
    stage.style.webkitTransform = 'scale(' + s + ')';
    stage.style.transform = 'scale(' + s + ')';
  }
  window.addEventListener('resize', fit);
  fit();

  /* ---------------- comunicação com o painel ---------------- */
  function post(url, fd, cb) {
    var x = new XMLHttpRequest();
    x.open('POST', url, true);
    x.timeout = 60000;
    x.onload = function () {
      var j = null;
      try { j = JSON.parse(x.responseText); } catch (e) {}
      if (x.status >= 200 && x.status < 300 && j !== null) cb(null, j); else cb({ status: x.status, body: j }, null);
    };
    x.onerror = x.ontimeout = function () { cb({ status: 0 }, null); };
    x.send(fd);
  }
  function api(helper, extra, cb) {
    var o = { helper_name: helper, application_id: APP_ID, device_id: DID, platform: PLATFORM,
      device_model: PLATFORM === 'lg_tv' ? 'LG webOS TV' : 'Samsung Smart TV', os_version: '', app_version: VER, app_name: 'IPTV Best Pro' };
    for (var k in extra) if (extra.hasOwnProperty(k)) o[k] = extra[k];
    var fd = new FormData();
    fd.append('data', b64(JSON.stringify(o)));
    post(SRV + '/api.php', fd, function (err, j) {
      if (err) return cb(err, null);
      cb(null, j && j.NEMOSOFTS_APP !== undefined ? j.NEMOSOFTS_APP : j);
    });
  }
  function xt(action, params, cb) {
    var fd = new FormData();
    fd.append('q', JSON.stringify({ device_id: DID, application_id: APP_ID, playlist_id: S.pl ? S.pl.id : 0, action: action, params: params || {} }));
    post(SRV + '/tv_xtream.php', fd, cb);
  }

  /* ---------------- navegação pelo controle ---------------- */
  var focused = null;
  function scope() { return S.modal || $('scr'); }
  function visible(el) { return el.offsetParent !== null && !el.classList.contains('hide'); }
  function focusables() {
    var sc = scope(); if (!sc) return [];
    var all = sc.querySelectorAll('.f'), out = [];
    for (var i = 0; i < all.length; i++) if (visible(all[i])) out.push(all[i]);
    return out;
  }
  function ensureVisible(el) {
    var p = el.parentNode;
    while (p && p !== stage) {
      if (p.classList && p.classList.contains('scroll')) {
        var top = 0, n = el;
        while (n && n !== p) { top += n.offsetTop; n = n.offsetParent; }
        if (top < p.scrollTop) p.scrollTop = Math.max(0, top - 12);
        else if (top + el.offsetHeight > p.scrollTop + p.clientHeight) p.scrollTop = top + el.offsetHeight - p.clientHeight + 12;
        break;
      }
      p = p.parentNode;
    }
  }
  function focus(el) {
    if (!el) return;
    if (focused && focused !== el) { focused.classList.remove('on'); if (focused.tagName === 'INPUT') focused.blur(); }
    focused = el;
    el.classList.add('on');
    ensureVisible(el);
    if (S.onFocus) S.onFocus(el);
  }
  function focusFirst(sel) {
    var sc = scope(); if (!sc) return;
    var el = sel ? sc.querySelector(sel) : null;
    if (!el || !visible(el)) { var l = focusables(); el = l[0]; }
    focus(el);
  }
  function move(dir) {
    var list = focusables();
    if (!list.length) return;
    if (!focused || list.indexOf(focused) < 0) { focus(list[0]); return; }
    var r = focused.getBoundingClientRect(), cx = r.left + r.width / 2, cy = r.top + r.height / 2, best = null, bd = 1e9;
    for (var i = 0; i < list.length; i++) {
      var e = list[i]; if (e === focused) continue;
      var q = e.getBoundingClientRect(), ex = q.left + q.width / 2, ey = q.top + q.height / 2, dx = ex - cx, dy = ey - cy, d;
      if (dir === 'right' && dx > 8) d = dx + Math.abs(dy) * 3;
      else if (dir === 'left' && dx < -8) d = -dx + Math.abs(dy) * 3;
      else if (dir === 'down' && dy > 8) d = dy + Math.abs(dx) * 3;
      else if (dir === 'up' && dy < -8) d = -dy + Math.abs(dx) * 3;
      else continue;
      if (d < bd) { bd = d; best = e; }
    }
    if (best) focus(best);
  }
  function keyName(e) {
    var c = e.keyCode;
    if (c === 37) return 'left'; if (c === 38) return 'up'; if (c === 39) return 'right'; if (c === 40) return 'down';
    if (c === 13) return 'ok';
    if (c === 461 || c === 10009 || c === 27 || (c === 8 && !(document.activeElement && document.activeElement.tagName === 'INPUT'))) return 'back';
    if (c === 33 || c === 427) return 'chup'; if (c === 34 || c === 428) return 'chdown';
    if (c === 415 || c === 19 || c === 10252 || c === 179) return 'playpause';
    if (c >= 48 && c <= 57) return 'n' + (c - 48);
    if (c >= 96 && c <= 105) return 'n' + (c - 96);
    return '';
  }
  document.addEventListener('keydown', function (e) {
    var k = keyName(e);
    if (!k) return;
    var inInput = document.activeElement && document.activeElement.tagName === 'INPUT';
    if (inInput && (k === 'left' || k === 'right' || k.charAt(0) === 'n')) return; // digitação no campo
    if (inInput && (k === 'ok' || k === 'up' || k === 'down')) { document.activeElement.blur(); if (k === 'ok' && S.onInputDone) { e.preventDefault(); S.onInputDone(); return; } }
    e.preventDefault();
    if (S.modal) {
      if (k === 'back') { closeModal(); return; }
      if (k === 'ok') { if (focused) activate(focused); return; }
      if (k === 'up' || k === 'down' || k === 'left' || k === 'right') move(k);
      return;
    }
    if (S.keys && S.keys(k) === true) return;
    if (k === 'up' || k === 'down' || k === 'left' || k === 'right') move(k);
    else if (k === 'ok') { if (focused) activate(focused); }
    else if (k === 'back') { if (S.back) S.back(); }
  }, true);
  stage.addEventListener('click', function (e) {
    var el = e.target; while (el && el !== stage && !(el.classList && el.classList.contains('f'))) el = el.parentNode;
    if (el && el !== stage) { focus(el); activate(el); }
  });
  function activate(el) {
    if (el.tagName === 'INPUT') { el.focus(); return; }
    var a = el.getAttribute('data-a');
    if (a && S.acts && S.acts[a]) S.acts[a](el, el.getAttribute('data-i'));
  }

  /* ---------------- telas, janelas e avisos ---------------- */
  function show(html, opts) {
    stopTimers();
    closeModal(true);
    opts = opts || {};
    stage.innerHTML = '<div id="scr" class="screen' + (opts.cls ? ' ' + opts.cls : '') + '">' + html + '</div>';
    S.keys = opts.keys || null; S.back = opts.back || null; S.acts = opts.acts || {}; S.onFocus = opts.onFocus || null; S.onInputDone = opts.onInputDone || null;
    focused = null;
    focusFirst(opts.focus);
  }
  var toastTimer = null;
  function toast(msg, ms) {
    var el = $('toast');
    if (!el) { el = document.createElement('div'); el.id = 'toast'; el.className = 'toast'; stage.appendChild(el); }
    el.textContent = msg; el.classList.remove('hide');
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { el.classList.add('hide'); }, ms || 3500);
  }
  var prevFocus = null, prevActs = null;
  function modal(html, acts) {
    closeModal(true);
    prevFocus = focused; prevActs = S.acts;
    var m = document.createElement('div'); m.className = 'modal';
    m.innerHTML = '<div class="card">' + html + '</div>';
    stage.appendChild(m);
    S.modal = m; S.acts = acts || {};
    S.acts._close = function () { closeModal(); };
    focused = null; focusFirst();
  }
  function closeModal(silent) {
    if (!S.modal) return;
    S.modal.parentNode.removeChild(S.modal); S.modal = null;
    if (!silent) { S.acts = prevActs || S.acts; focused = null; if (prevFocus && document.body.contains(prevFocus)) focus(prevFocus); else focusFirst(); }
  }
  function header(title) {
    return '<div class="top"><img class="logo" src="' + esc(S.brand.logo) + '" onerror="this.style.visibility=\'hidden\'">' +
      (title ? '<div class="title">' + esc(title) + '</div>' : (S.brand.showName === false ? '' : '<div class="brand">' + esc(S.brand.name) + '</div>')) +
      '<div class="right" id="clock"></div></div>';
  }
  var timers = [];
  function every(fn, ms) { timers.push(setInterval(fn, ms)); }
  function stopTimers() { for (var i = 0; i < timers.length; i++) clearInterval(timers[i]); timers = []; }
  function clock() { var c = $('clock'); if (c) { var d = new Date(); c.textContent = d.toLocaleDateString(LANG === 'en' ? 'en-US' : (LANG === 'es' ? 'es-ES' : 'pt-BR')) + '  ' + pad(d.getHours()) + ':' + pad(d.getMinutes()); } }
  function qrHtml(text) {
    try { var q = qrcode(0, 'M'); q.addData(text); q.make(); return '<div class="qr">' + q.createImgTag(8, 0) + '</div>'; } catch (e) { return ''; }
  }
  /* ---------------- vídeo: player da TV ou, se ela não tocar HLS, o hls.js ---------------- */
  var NATIVE_HLS = !!document.createElement('video').canPlayType('application/vnd.apple.mpegurl');
  function loadHls(cb) {
    if (window.Hls) return cb();
    var sc = document.createElement('script'); sc.src = SRV + '/tv/hls.light.min.js'; sc.onload = cb; sc.onerror = cb; document.body.appendChild(sc);
  }
  function unsetSrc(v) { if (v && v._hls) { try { v._hls.destroy(); } catch (e) {} v._hls = null; } }
  function setSrc(v, url, onFatal, forceHls) {
    unsetSrc(v);
    v._url = url;
    if (/\.m3u8(\?|$)/i.test(url) && (!NATIVE_HLS || forceHls)) {
      loadHls(function () {
        if (window.Hls && Hls.isSupported()) {
          var h = new Hls({ maxBufferLength: 30, backBufferLength: 30 });
          v._hls = h;
          h.on(Hls.Events.ERROR, function (ev, d) { if (d && d.fatal && onFatal) onFatal(); });
          h.on(Hls.Events.MANIFEST_PARSED, function () { try { v.play(); } catch (e) {} });
          h.loadSource(url); h.attachMedia(v);
        } else { v.src = url; try { v.play(); } catch (e) {} }
      });
      return;
    }
    v.src = url;
    try { v.play(); } catch (e) {}
  }
  function waLink(phone) { var d = String(phone || '').replace(/\D+/g, ''); return d.length >= 10 ? 'https://wa.me/' + d : ''; }

  /* ---------------- abertura e ativação ---------------- */
  function splash() {
    show('<div class="screen splash"><img src="' + esc(S.brand.logo) + '"><div class="name">' + esc(S.brand.name) + '</div><div class="spin"></div></div>');
  }

  function saveStatus(o) {
    S.status = o || {};
    LS.setJ('bp_status', S.status);
  }
  /* Marca do dono do aparelho (admin ou revenda): logo, nome, fundo, transparência e banner — igual ao app Android */
  var DEF_LOGO = SRV + '/assets/images/brand/iptvbestpro_logo.png', DEF_BG = SRV + '/tv/bg.jpg';
  function applyBrand(tc) {
    tc = tc || {};
    S.brand.name = tc.brand_name || 'IPTV Best Pro';
    S.brand.logo = tc.logo || DEF_LOGO;
    S.brand.showName = tc.show_brand_name !== '0';
    S.brand.banner = tc.banner || '';
    setBg(tc.background_image || (S.posters && S.posters.length ? S.posters[0] : DEF_BG));
    var o = parseInt(tc.background_overlay, 10); if (isNaN(o)) o = 55;
    var css = '#stage:before{background:rgba(5,8,20,' + Math.max(0, Math.min(95, o)) / 100 + ')}';
    // cor dos blocos escolhida no tema (Personalizar tema)
    var ms = tc.model_settings || {}, tcol = hexRgb(ms.tile_color || ms.card_color || '');
    if (tcol) css += '.tile,.set{background:rgba(' + tcol + ',.9)}.tile.on,.set.on{background:rgba(' + tcol + ',1)}';
    var st = $('bpov'); if (!st) { st = document.createElement('style'); st.id = 'bpov'; document.head.appendChild(st); }
    st.textContent = css;
    LS.setJ('bp_brand', tc);
  }
  function hexRgb(h) {
    var m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(String(h || ''));
    return m ? parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16) : '';
  }
  function setBg(url) { stage.style.backgroundImage = 'url("' + url + '")'; }
  // Tema MovieUI: o fundo são os pôsteres do dono do aparelho (os seus ou os da revenda), trocando a cada 20 s
  var posterT = null;
  function loadPosters(tc) {
    clearInterval(posterT);
    if (tc.background_image || String(tc.theme_id) !== '4') return;
    api('get_poster', {}, function (err, arr) {
      var l = [];
      for (var i = 0; arr && i < arr.length; i++) if (arr[i].poster_image) l.push(arr[i].poster_image);
      if (!l.length) return;
      S.posters = l; var k = 0; setBg(l[0]);
      if (l.length > 1) posterT = setInterval(function () { k = (k + 1) % l.length; setBg(l[k]); }, 20000);
    });
  }
  function fetchBrand(cb) {
    api('app_details', {}, function (err, j) {
      try {
        // o tema vem em theme_config, no mesmo nível de details
        var tc = j && j.theme_config ? j.theme_config : (j && j.details && j.details[0] && j.details[0].theme_config) || null;
        if (tc) { applyBrand(tc); loadPosters(tc); }
      } catch (e) {}
      if (cb) cb();
    });
  }
  function boot() {
    applyBrand(LS.getJ('bp_brand', null)); // mostra já a última marca conhecida
    splash();
    fetchBrand(checkStatus);
  }
  function checkStatus() {
    api('device_status', {}, function (err, arr) {
      if (err) {
        // sem servidor: usa a última situação conhecida
        var last = LS.getJ('bp_status', null);
        if (last && last.status === 'active') { S.status = last; return loadPlaylists(); }
        return activation('unlinked', t(err.status === 0 ? 'no_net' : 'server_err'));
      }
      var o = arr && arr[0] ? arr[0] : {};
      saveStatus(o);
      if (o.status === 'active') loadPlaylists();
      else activation(o.status || 'unlinked', '');
    });
  }

  function clearLocal() {
    var keep = { bp_did: 1, bp_lang: 1 };
    try {
      var ks = []; for (var i = 0; i < localStorage.length; i++) ks.push(localStorage.key(i));
      for (var j = 0; j < ks.length; j++) if (!keep[ks[j]] && ks[j].indexOf('bp_') === 0) localStorage.removeItem(ks[j]);
    } catch (e) {}
    S.pl = null; S.login = null; S.pls = []; S.brandFresh = false;
  }

  function activation(status, msg) {
    if (status === 'unlinked') clearLocal();
    var waiting = status === 'pending', expired = status === 'expired';
    var html = '<div class="card"><img class="logo" src="' + esc(S.brand.logo) + '"><h1>' + esc(S.brand.name) + '</h1>' +
      '<h2>' + esc(waiting ? t('act_wait') : (expired ? t('act_expired') : t('act_title'))) + '</h2>' +
      '<p>' + esc(waiting ? t('act_wait_msg') : (expired ? t('act_expired_msg') : t('act_msg'))) + '</p>' +
      '<div class="devid">' + esc(t('act_dev')) + '<b>' + esc(DID) + '</b></div>' +
      (waiting ? '<div class="spin"></div>' : '<input id="code" class="code f" maxlength="8" autocomplete="off" spellcheck="false">') +
      '<p id="aerr" class="err' + (msg ? '' : ' hide') + '">' + esc(msg) + '</p>' +
      (waiting ? '' : '<div class="btn gold f" data-a="link">' + esc(t('act_btn')) + '</div>') +
      '<div class="btn f" data-a="check">' + esc(t('act_check')) + '</div>' +
      (waiting ? '<div class="btn f" data-a="other">' + esc(t('act_other')) + '</div>' : '<p>' + esc(t('act_help')) + '</p>') + '</div>';
    show(html, {
      focus: waiting ? '[data-a=check]' : '#code',
      back: confirmExit,
      onInputDone: function () { doLink(); },
      acts: { link: doLink, check: function () { checkStatus(); }, other: function () { activation('unlinked', ''); } }
    });
    if (waiting) every(function () {
      api('device_status', {}, function (err, arr) {
        if (err || S.screen !== 'act') return;
        var o = arr && arr[0] ? arr[0] : {};
        if (o.status === 'active') { saveStatus(o); loadPlaylists(); }
        else if (o.status && o.status !== 'pending') { saveStatus(o); activation(o.status, ''); }
      });
    }, 5000);
    S.screen = 'act';
  }
  function doLink() {
    var inp = $('code'); if (!inp) return;
    var code = inp.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    inp.value = code;
    var e = $('aerr');
    if (!/^[A-Z]{6}$/.test(code) && !/^[0-9]{8}$/.test(code)) { e.textContent = t('act_invalid'); e.classList.remove('hide'); return; }
    e.classList.add('hide');
    api('device_link', { code: code }, function (err, arr) {
      if (err) { e.textContent = t(err.status === 0 ? 'no_net' : 'server_err'); e.classList.remove('hide'); return; }
      var o = arr && arr[0] ? arr[0] : {};
      if (o.success === '1') clearLocal();
      saveStatus(o);
      if (o.status === 'active') loadPlaylists();
      else if (o.success !== '1') { e.textContent = o.MSG || t('act_invalid'); e.classList.remove('hide'); }
      else activation(o.status || 'pending', '');
    });
  }
  function confirmExit() {
    modal('<h2>' + esc(t('exit_q')) + '</h2><div class="btn gold f" data-a="y">' + esc(t('yes')) + '</div><div class="btn f" data-a="_close">' + esc(t('no')) + '</div>',
      { y: function () { try { if (window.webOS && webOS.platformBack) webOS.platformBack(); else if (window.tizen) tizen.application.getCurrentApplication().exit(); else window.close(); } catch (e) { window.close(); } } });
  }

  /* ---------------- listas do aparelho ---------------- */
  function loadPlaylists(chooser) {
    splash();
    // depois de ativar (ou trocar de dono) busca de novo a marca do dono do aparelho
    if (!chooser && !S.brandFresh) { S.brandFresh = true; return fetchBrand(function () { loadPlaylists(false); }); }
    api('get_device_user', {}, function (err, arr) {
      if (err) { toast(t(err.status === 0 ? 'no_net' : 'server_err')); return noList(); }
      S.pls = (arr || []).filter(function (p) { return p && p.user_name; });
      if (!S.pls.length) { clearLocal(); return noList(); }
      if (chooser) return playlists();
      var saved = LS.get('bp_pl', ''), pick = null;
      for (var i = 0; i < S.pls.length; i++) if (String(S.pls[i].id) === saved) pick = S.pls[i];
      if (!pick && saved) clearLocal(); // a lista salva não é mais deste aparelho: apaga tudo
      login(pick || S.pls[0]);
    });
  }
  function noList() {
    show('<div class="card"><img class="logo" src="' + esc(S.brand.logo) + '"><h2>' + esc(t('no_list')) + '</h2><p>' + esc(t('no_list_msg')) + '</p>' +
      '<div class="devid">' + esc(t('act_dev')) + '<b>' + esc(DID) + '</b></div><div class="btn gold f" data-a="r">' + esc(t('retry')) + '</div></div>',
      { back: confirmExit, acts: { r: function () { checkStatus(); } } });
    S.screen = 'nolist';
  }
  function login(pl) {
    // outra lista: nada da anterior pode aparecer
    if (!S.pl || String(S.pl.id) !== String(pl.id)) { L.cats = null; L.chans = []; L.playList = []; L.playIdx = -1; V.movie.cats = null; V.series.cats = null; }
    S.pl = pl;
    xt('', {}, function (err, j) {
      if (err || !j || !j.user_info || String(j.user_info.auth) !== '1') { toast(t('list_err')); return playlists(); }
      S.login = j;
      var si = j.server_info || {}, proto = si.server_protocol || 'http', port = proto === 'https' ? si.https_port : si.port;
      S.base = si.url ? proto + '://' + si.url + (port ? ':' + port : '') : String(j._dns || pl.dns_base).replace(/\/+$/, '');
      LS.set('bp_pl', String(pl.id));
      home();
      expiryNotice();
    });
  }
  function playlists() {
    var rows = '';
    for (var i = 0; i < S.pls.length; i++) {
      var p = S.pls[i], on = S.pl && String(S.pl.id) === String(p.id) && S.login;
      rows += '<div class="row f" data-a="pick" data-i="' + i + '"><span>' + esc(p.playlist_name || p.user_name) + '</span>' + (on ? '<span class="badge">● ' + esc(t('connected')) + '</span>' : '') + '</div>';
    }
    show(header(t('playlist')) + '<div class="left" style="width:1200px"><div class="scroll">' + rows + '</div></div>',
      { back: function () { if (S.login) home(); else confirmExit(); }, acts: { pick: function (el, i) { var p = S.pls[+i]; if (S.pl && String(S.pl.id) !== String(p.id)) clearCatalog(); splash(); login(p); } } });
    S.screen = 'pl';
    clock();
  }
  function clearCatalog() { try { var ks = []; for (var i = 0; i < localStorage.length; i++) ks.push(localStorage.key(i)); for (var j = 0; j < ks.length; j++) if (/^bp_(last|cw|pos)/.test(ks[j])) localStorage.removeItem(ks[j]); } catch (e) {} }

  /* ---------------- aviso de vencimento ---------------- */
  function expiryNotice() {
    var d = parseInt(S.status.days_left, 10);
    if (isNaN(d) || d > 5) return;
    var today = new Date().toDateString();
    if (LS.get('bp_exp_shown', '') === today) return;
    LS.set('bp_exp_shown', today);
    var reseller = S.status.owner === 'reseller', wa = waLink(S.status.support_whatsapp);
    modal('<h2>' + esc(t('exp_title')) + '</h2><p>' + esc(d <= 0 ? t('exp_today') : t('exp_days', d)) + '</p><p>' + esc(t(reseller ? 'exp_reseller' : 'exp_support')) + '</p>' +
      (wa ? qrHtml(wa) : '') + '<div class="btn f" data-a="_close">' + esc(t('close')) + '</div>');
  }

  /* ---------------- tela inicial ---------------- */
  function home() {
    stopPreview();
    var tiles = [['live', IC.tv, 'live'], ['movies', IC.movie, 'movies'], ['series', IC.series, 'series'], ['playlist', IC.list, 'playlist'], ['settings', IC.gear, 'settings']];
    var h = '';
    for (var i = 0; i < tiles.length; i++) h += '<div class="tile f" data-a="' + tiles[i][0] + '"><div class="ic">' + svg(tiles[i][1]) + '</div><div class="lb">' + esc(t(tiles[i][2])) + '</div></div>';
    show(header('') + '<div class="hero"><img src="' + esc(S.brand.banner || S.brand.logo) + '" onerror="this.style.display=\'none\'"></div><div class="tiles">' + h + '</div>', {
      focus: '[data-a=' + (LS.get('bp_home', 'live')) + ']',
      back: confirmExit,
      acts: {
        live: function () { LS.set('bp_home', 'live'); live(); },
        movies: function () { LS.set('bp_home', 'movies'); vodScreen('movie'); },
        series: function () { LS.set('bp_home', 'series'); vodScreen('series'); },
        playlist: function () { LS.set('bp_home', 'playlist'); loadPlaylists(true); },
        settings: function () { LS.set('bp_home', 'settings'); settings(); }
      }
    });
    S.screen = 'home';
    clock(); every(clock, 20000);
  }

  /* ---------------- TV ao vivo ---------------- */
  var L = { cats: null, chans: [], catIdx: 0, catName: '', mode: 'cats', playList: [], playIdx: -1, full: false, video: null, err: 0 };
  function live() {
    L.mode = 'cats'; L.full = false; L.err = 0;
    show(header(t('live')) + '<div class="left"><div class="scroll" id="lst"></div></div>' +
      '<div class="right"><div class="pvbox" id="pv"><video id="vid" autoplay></video><div class="pvmsg hide" id="pvmsg"></div></div>' +
      '<div class="epg" id="epg"></div></div>', {
      keys: liveKeys, back: liveBack,
      acts: {
        cat: function (el, i) { openCat(+i); },
        ch: function (el, i) { i = +i; if (L.chans[i] && L.playIdx >= 0 && L.playList[L.playIdx] && L.playList[L.playIdx].stream_id == L.chans[i].stream_id) setFull(true); else playChan(i); }
      }
    });
    S.screen = 'live'; clock(); every(clock, 20000);
    L.video = $('vid');
    L.video.addEventListener('error', onLiveError);
    L.video.addEventListener('playing', function () { L.err = 0; var m = $('pvmsg'); if (m) m.classList.add('hide'); });
    if (L.cats) { renderCats(); resumeLast(); return; }
    $('lst').innerHTML = '<div class="empty">' + esc(t('loading')) + '</div>';
    xt('get_live_categories', {}, function (err, j) {
      if (err || !j || !j.length) { $('lst').innerHTML = '<div class="empty">' + esc(t(err ? 'list_err' : 'empty')) + '</div>'; return; }
      L.cats = j.filter(function (c) { return !ADULT.test(c.category_name || ''); });
      renderCats(); resumeLast();
    });
  }
  function renderCats() {
    L.mode = 'cats';
    var h = '';
    for (var i = 0; i < L.cats.length; i++) h += '<div class="row f' + (i === L.catIdx ? ' sel' : '') + '" data-a="cat" data-i="' + i + '"><span>' + esc(L.cats[i].category_name) + '</span></div>';
    $('lst').innerHTML = h || '<div class="empty">' + esc(t('empty')) + '</div>';
    var el = $('lst').querySelector('[data-i="' + L.catIdx + '"]'); focused = null; focus(el || $('lst').querySelector('.f'));
  }
  function resumeLast() {
    if (L.playIdx >= 0) return;
    var last = LS.getJ('bp_last_live', null);
    if (!last) return;
    for (var i = 0; i < L.cats.length; i++) if (String(L.cats[i].category_id) === String(last.cat)) { L.catIdx = i; openCat(i, last.sid); return; }
  }
  function openCat(i, startSid) {
    L.catIdx = i; L.catName = L.cats[i].category_name;
    $('lst').innerHTML = '<div class="empty">' + esc(t('loading')) + '</div>';
    xt('get_live_streams', { category_id: L.cats[i].category_id }, function (err, j) {
      if (S.screen !== 'live') return;
      if (err || !j || !j.length) { toast(t(err ? 'list_err' : 'empty')); renderCats(); return; }
      L.chans = j; L.mode = 'chans';
      var h = '';
      for (var k = 0; k < j.length; k++) h += '<div class="row f" data-a="ch" data-i="' + k + '"><img src="' + esc(j[k].stream_icon || '') + '" onerror="this.style.visibility=\'hidden\'"><span>' + (k + 1) + '  ' + esc(j[k].name) + '</span></div>';
      $('lst').innerHTML = h;
      var start = 0;
      if (startSid !== undefined) for (var q = 0; q < j.length; q++) if (String(j[q].stream_id) === String(startSid)) start = q;
      if (L.playIdx < 0 || startSid !== undefined) playChan(start);
      markPlaying();
      var cur = $('lst').querySelector('.sel') || $('lst').querySelector('.f'); focused = null; focus(cur);
    });
  }
  function markPlaying() {
    var rows = $('lst') ? $('lst').querySelectorAll('[data-a=ch]') : [];
    var pid = L.playIdx >= 0 && L.playList[L.playIdx] ? String(L.playList[L.playIdx].stream_id) : '';
    for (var i = 0; i < rows.length; i++) { var c = L.chans[+rows[i].getAttribute('data-i')]; rows[i].classList.toggle('sel', !!c && String(c.stream_id) === pid); }
  }
  function liveUrl(c) { var u = S.login.user_info; return S.base + '/live/' + encodeURIComponent(u.username) + '/' + encodeURIComponent(u.password) + '/' + c.stream_id + '.m3u8'; }
  function playChan(i) {
    L.playList = L.chans.slice(0); L.playIdx = i; L.err = 0;
    startLive();
  }
  function startLive() {
    var c = L.playList[L.playIdx]; if (!c || !L.video) return;
    L.video._triedHls = false;
    setSrc(L.video, liveUrl(c), onLiveError);
    LS.setJ('bp_last_live', { cat: L.cats[L.catIdx] ? L.cats[L.catIdx].category_id : '', sid: c.stream_id });
    markPlaying(); loadEpg(c);
    if (L.full) zapBanner((L.playIdx + 1) + '  ·  ' + c.name);
  }
  function onLiveError() {
    var m = $('pvmsg'); if (!m) return;
    if (L.video && !L.video._hls && !L.video._triedHls && /\.m3u8(\?|$)/i.test(L.video._url || '')) {
      L.video._triedHls = true; setSrc(L.video, L.video._url, onLiveError, true); return;
    }
    L.err++;
    if (L.err <= 2) { m.textContent = t('reconnect'); m.classList.remove('hide'); setTimeout(function () { if (S.screen === 'live') startLive(); }, 2500); }
    else { m.textContent = t('ch_off'); m.classList.remove('hide'); }
  }
  function loadEpg(c) {
    var box = $('epg'); if (!box) return;
    box.innerHTML = '<div class="ch">' + esc(c.name) + '</div>';
    xt('get_short_epg', { stream_id: c.stream_id, limit: 2 }, function (err, j) {
      if (!$('epg')) return;
      var l = j && j.epg_listings ? j.epg_listings : [];
      var h = '<div class="ch">' + esc(c.name) + '</div>';
      if (!l.length) h += '<div class="next">' + esc(t('no_epg')) + '</div>';
      else {
        h += '<div class="now">' + esc(t('now')) + ': ' + esc(unb64(l[0].title)) + '  <span class="next">' + hhmm(+l[0].start_timestamp) + ' – ' + hhmm(+l[0].stop_timestamp) + '</span></div>';
        if (l[1]) h += '<div class="next">' + esc(t('next')) + ': ' + esc(unb64(l[1].title)) + '  ' + hhmm(+l[1].start_timestamp) + '</div>';
      }
      $('epg').innerHTML = h;
    });
  }
  function setFull(on) {
    L.full = on;
    var pv = $('pv'); if (!pv) return;
    pv.classList.toggle('full', on);
    if (on && L.playList[L.playIdx]) zapBanner((L.playIdx + 1) + '  ·  ' + L.playList[L.playIdx].name);
  }
  var zapT = null, digits = '', digitT = null;
  function zapBanner(txt) {
    var z = $('zap');
    if (!z) { z = document.createElement('div'); z.id = 'zap'; z.className = 'zap'; $('scr').appendChild(z); }
    z.textContent = txt; z.classList.remove('hide');
    clearTimeout(zapT); zapT = setTimeout(function () { var y = $('zap'); if (y) y.classList.add('hide'); }, 3500);
  }
  function zap(step) {
    if (!L.playList.length) return;
    L.playIdx = (L.playIdx + step + L.playList.length) % L.playList.length; L.err = 0;
    startLive();
  }
  function liveKeys(k) {
    if (k.charAt(0) === 'n' && L.playList.length) {
      digits += k.charAt(1); zapBanner(digits + '_');
      clearTimeout(digitT); digitT = setTimeout(function () { var n = parseInt(digits, 10); digits = ''; if (n >= 1 && n <= L.playList.length) { L.playIdx = n - 1; startLive(); } }, 1500);
      return true;
    }
    if (k === 'chup') { zap(1); return true; }
    if (k === 'chdown') { zap(-1); return true; }
    if (L.full) {
      if (k === 'up') { zap(-1); return true; }
      if (k === 'down') { zap(1); return true; }
      if (k === 'ok') { zapBanner((L.playIdx + 1) + '  ·  ' + L.playList[L.playIdx].name); return true; }
      if (k === 'back') { setFull(false); return true; }
      return true;
    }
    return false;
  }
  function liveBack() { if (L.mode === 'chans') renderCats(); else { stopPreview(); home(); } }
  function stopPreview() { if (L.video) { unsetSrc(L.video); try { L.video.pause(); L.video.removeAttribute('src'); L.video.load(); } catch (e) {} L.video = null; } L.playIdx = -1; L.full = false; }

  /* ---------------- filmes e séries ---------------- */
  var V = { movie: { cats: null, catIdx: 0, items: [] }, series: { cats: null, catIdx: 0, items: [] } };
  function cwList(type) { return LS.getJ('bp_cw_' + type, []); }
  function cwAdd(type, item) {
    var l = cwList(type).filter(function (x) { return String(x.id) !== String(item.id); });
    l.unshift(item); LS.setJ('bp_cw_' + type, l.slice(0, 30));
  }
  function vodScreen(type) {
    var st = V[type];
    show(header(t(type === 'movie' ? 'movies' : 'series')) + '<div class="left" style="width:520px"><div class="scroll" id="lst"></div></div>' +
      '<div class="right" style="left:630px"><div class="scroll" id="grid"><div class="grid" id="gridin"></div></div></div>', {
      back: function () { if (focused && focused.classList.contains('poster')) { var c = $('lst').querySelector('.sel'); focus(c || $('lst').querySelector('.f')); } else home(); },
      acts: {
        cat: function (el, i) { loadVodCat(type, +i); },
        item: function (el, i) { var it = st.items[+i]; if (type === 'movie') movieDetails(it); else seriesDetails(it); }
      }
    });
    S.screen = type; clock(); every(clock, 20000);
    var act = type === 'movie' ? 'get_vod_categories' : 'get_series_categories';
    if (st.cats) return renderVodCats(type);
    $('lst').innerHTML = '<div class="empty">' + esc(t('loading')) + '</div>';
    xt(act, {}, function (err, j) {
      if (err || !j) { $('lst').innerHTML = '<div class="empty">' + esc(t('list_err')) + '</div>'; return; }
      st.cats = [{ category_id: '_cw', category_name: t('continue') }].concat(j.filter(function (c) { return !ADULT.test(c.category_name || ''); }));
      st.catIdx = cwList(type).length ? 0 : (st.cats.length > 1 ? 1 : 0);
      renderVodCats(type);
    });
  }
  function renderVodCats(type) {
    var st = V[type], h = '';
    for (var i = 0; i < st.cats.length; i++) h += '<div class="row f' + (i === st.catIdx ? ' sel' : '') + '" data-a="cat" data-i="' + i + '"><span>' + esc(st.cats[i].category_name) + '</span></div>';
    $('lst').innerHTML = h;
    focused = null; focus($('lst').querySelector('.sel') || $('lst').querySelector('.f'));
    loadVodCat(type, st.catIdx);
  }
  function loadVodCat(type, i) {
    var st = V[type]; st.catIdx = i;
    var rows = $('lst').querySelectorAll('.row'); for (var r = 0; r < rows.length; r++) rows[r].classList.toggle('sel', r === i);
    var c = st.cats[i];
    $('gridin').innerHTML = '<div class="empty">' + esc(t('loading')) + '</div>';
    if (c.category_id === '_cw') { st.items = cwList(type); return renderGrid(type); }
    xt(type === 'movie' ? 'get_vod_streams' : 'get_series', { category_id: c.category_id }, function (err, j) {
      if (S.screen !== type || st.catIdx !== i) return;
      st.items = (j || []).slice(0, 300);
      renderGrid(type);
    });
  }
  function renderGrid(type) {
    var st = V[type], h = '';
    for (var i = 0; i < st.items.length; i++) {
      var it = st.items[i], img = it.stream_icon || it.cover || it.img || '';
      h += '<div class="poster f" data-a="item" data-i="' + i + '"><img src="' + esc(img) + '" onerror="this.style.visibility=\'hidden\'"><div class="pt">' + esc(it.name) + '</div></div>';
    }
    $('gridin').innerHTML = h || '<div class="empty">' + esc(t('empty')) + '</div>';
    $('grid').scrollTop = 0;
  }
  function movieDetails(it) {
    var id = it.stream_id || it.id;
    show(header(t('movies')) + '<div class="det"><img class="cover" src="' + esc(it.stream_icon || it.img || '') + '" onerror="this.style.visibility=\'hidden\'">' +
      '<div class="info"><h1>' + esc(it.name) + '</h1><div class="meta" id="meta"></div><div class="plot" id="plot">' + esc(t('loading')) + '</div><div class="acts" id="acts"></div></div></div>',
      { back: function () { vodScreen('movie'); }, acts: {
        play: function () { playVod('movie', id, it.name, it.container_extension || (it.ext) || 'mp4', 0, it); },
        resume: function () { playVod('movie', id, it.name, it.container_extension || it.ext || 'mp4', +LS.get('bp_pos_m_' + id, '0'), it); }
      } });
    S.screen = 'mdet';
    var pos = +LS.get('bp_pos_m_' + id, '0');
    var acts = (pos > 30 ? '<div class="btn gold f" data-a="resume">' + esc(t('resume')) + ' ' + fmtTime(pos) + '</div><div class="btn f" data-a="play">' + esc(t('from_start')) + '</div>' : '<div class="btn gold f" data-a="play">' + esc(t('play')) + '</div>');
    $('acts').innerHTML = acts; focused = null; focusFirst();
    xt('get_vod_info', { vod_id: id }, function (err, j) {
      if (S.screen !== 'mdet') return;
      var inf = j && j.info ? j.info : {}, md = j && j.movie_data ? j.movie_data : {};
      if (md.container_extension) it.container_extension = md.container_extension;
      $('plot').textContent = inf.plot || inf.description || '';
      $('meta').textContent = [inf.releasedate || inf.release_date || '', inf.genre || '', inf.duration || ''].filter(function (x) { return x; }).join('  ·  ');
    });
  }
  function seriesDetails(it) {
    var sid = it.series_id || it.id;
    show(header(t('series')) + '<div class="det"><img class="cover" src="' + esc(it.cover || it.img || '') + '" onerror="this.style.visibility=\'hidden\'">' +
      '<div class="info"><h1>' + esc(it.name) + '</h1><div class="meta" id="meta"></div><div class="plot" id="plot" style="max-height:140px">' + esc(t('loading')) + '</div>' +
      '<div class="seasons" id="seas"></div><div class="eps scroll" id="eps"></div></div></div>',
      { back: function () { vodScreen('series'); }, acts: {} });
    S.screen = 'sdet';
    xt('get_series_info', { series_id: sid }, function (err, j) {
      if (S.screen !== 'sdet') return;
      var inf = j && j.info ? j.info : {}, eps = j && j.episodes ? j.episodes : {};
      $('plot').textContent = inf.plot || '';
      $('meta').textContent = [inf.releaseDate || '', inf.genre || ''].filter(function (x) { return x; }).join('  ·  ');
      var seasons = []; for (var k in eps) if (eps.hasOwnProperty(k)) seasons.push(k);
      seasons.sort(function (a, b) { return +a - +b; });
      var sh = '';
      for (var s = 0; s < seasons.length; s++) sh += '<div class="btn f" data-a="season" data-i="' + esc(seasons[s]) + '">' + esc(t('season')) + ' ' + esc(seasons[s]) + '</div>';
      $('seas').innerHTML = sh;
      S.acts.season = function (el, n) {
        var b = $('seas').querySelectorAll('.btn'); for (var q = 0; q < b.length; q++) b[q].classList.toggle('sel', b[q] === el);
        var list = eps[n] || [], h = '';
        for (var e = 0; e < list.length; e++) {
          var p = +LS.get('bp_pos_e_' + list[e].id, '0');
          h += '<div class="row f" data-a="ep" data-i="' + n + ':' + e + '"><span>' + esc((list[e].episode_num || e + 1) + '. ' + (list[e].title || '')) + '</span>' + (p > 30 ? '<span class="badge">' + fmtTime(p) + '</span>' : '') + '</div>';
        }
        $('eps').innerHTML = h;
      };
      S.acts.ep = function (el, ref) {
        var pr = ref.split(':'), ep = eps[pr[0]][+pr[1]], p = +LS.get('bp_pos_e_' + ep.id, '0');
        cwAdd('series', { id: sid, series_id: sid, name: it.name, cover: it.cover || it.img || '' });
        playVod('series', ep.id, it.name + ' – ' + (ep.title || ''), ep.container_extension || 'mp4', p > 30 ? p : 0, null);
      };
      if (seasons.length) { var first = $('seas').querySelector('.btn'); S.acts.season(first, seasons[0]); focused = null; focus(first); }
    });
  }

  /* ---------------- player de filme / episódio ---------------- */
  var PL = { video: null, type: '', id: '', saveT: null };
  function playVod(type, id, title, ext, startAt, item) {
    var u = S.login.user_info;
    var url = S.base + '/' + type + '/' + encodeURIComponent(u.username) + '/' + encodeURIComponent(u.password) + '/' + id + '.' + (ext || 'mp4');
    if (type === 'movie' && item) cwAdd('movie', { id: id, stream_id: id, name: item.name, stream_icon: item.stream_icon || item.img || '', container_extension: ext });
    var back = S.back;
    var p = document.createElement('div'); p.className = 'player'; p.id = 'player';
    p.innerHTML = '<video id="pvid" autoplay></video><div class="msg hide" id="pmsg"></div><div class="bar" id="pbar"><div class="ttl">' + esc(title) + '</div><div class="track"><i id="ptr"></i></div><div class="tm" id="ptm"></div></div>';
    stage.appendChild(p);
    PL.video = $('pvid'); PL.type = type; PL.id = id;
    var key = 'bp_pos_' + (type === 'movie' ? 'm_' : 'e_') + id, seeked = false, errs = 0, barT = null;
    function showBar() { $('pbar').classList.remove('hide'); clearTimeout(barT); barT = setTimeout(function () { var b = $('pbar'); if (b) b.classList.add('hide'); }, 4000); }
    function save() { var v = PL.video; if (!v || !v.duration) return; if (v.currentTime > v.duration - 15) LS.del(key); else if (v.currentTime > 5) LS.set(key, String(Math.floor(v.currentTime))); }
    PL.video.addEventListener('loadedmetadata', function () { if (startAt > 0 && !seeked) { seeked = true; try { PL.video.currentTime = startAt; } catch (e) {} } });
    PL.video.addEventListener('timeupdate', function () { var v = PL.video; if (!v || !v.duration) return; $('ptr').style.width = (v.currentTime / v.duration * 100) + '%'; $('ptm').textContent = fmtTime(v.currentTime) + ' / ' + fmtTime(v.duration); });
    PL.video.addEventListener('playing', function () { $('pmsg').classList.add('hide'); });
    function verr() {
      var m0 = $('pmsg'); if (!m0) return;
      if (PL.video && !PL.video._hls && !PL.video._triedHls && /\.m3u8(\?|$)/i.test(url)) { PL.video._triedHls = true; setSrc(PL.video, url, verr, true); return; }
      errs++; var m = m0; if (errs <= 1) { m.textContent = t('reconnect'); m.classList.remove('hide'); setTimeout(function () { if (PL.video) setSrc(PL.video, url, verr); }, 2500); } else { m.textContent = t('play_err'); m.classList.remove('hide'); } }
    PL.video.addEventListener('error', verr);
    setSrc(PL.video, url, verr);
    showBar();
    PL.saveT = setInterval(save, 60000); // salva onde parou a cada 1 minuto (sobrescreve)
    var prevKeys = S.keys;
    function close() { save(); clearInterval(PL.saveT); unsetSrc(PL.video); try { PL.video.pause(); PL.video.removeAttribute('src'); PL.video.load(); } catch (e) {} var el = $('player'); if (el) el.parentNode.removeChild(el); PL.video = null; S.keys = prevKeys; S.back = back; }
    S.keys = function (k) {
      var v = PL.video; if (!v) return true;
      if (k === 'back') { close(); return true; }
      if (k === 'ok' || k === 'playpause') { if (v.paused) v.play(); else v.pause(); showBar(); return true; }
      if (k === 'right') { try { v.currentTime = Math.min(v.duration || 1e9, v.currentTime + 10); } catch (e) {} showBar(); return true; }
      if (k === 'left') { try { v.currentTime = Math.max(0, v.currentTime - 10); } catch (e) {} showBar(); return true; }
      showBar(); return true;
    };
  }

  /* ---------------- configurações ---------------- */
  function settings() {
    var langName = { pt: 'Português', en: 'English', es: 'Español' };
    var items = [['lang', IC.globe, t('language'), langName[LANG]], ['dev', IC.id, t('device'), DID], ['sup', IC.chat, t('support'), ''],
      ['ref', IC.sync, t('refresh'), ''], ['pl', IC.list, t('playlist'), S.pl ? (S.pl.playlist_name || S.pl.user_name) : ''], ['about', IC.info, t('about'), 'v' + VER]];
    var h = '';
    for (var i = 0; i < items.length; i++) h += '<div class="set f" data-a="' + items[i][0] + '"><div class="ic">' + svg(items[i][1], 64) + '</div><div class="lb">' + esc(items[i][2]) + '</div><div class="sub">' + esc(items[i][3]) + '</div></div>';
    show(header(t('settings')) + '<div class="sets">' + h + '</div>', {
      back: home,
      acts: {
        lang: function () {
          modal('<h2>' + esc(t('language')) + '</h2>' + ['pt', 'en', 'es'].map(function (l) { return '<div class="btn f' + (l === LANG ? ' gold' : '') + '" data-a="L" data-i="' + l + '">' + langName[l] + '</div>'; }).join(''),
            { L: function (el, l) { LANG = l; LS.set('bp_lang', l); closeModal(true); settings(); } });
        },
        dev: function () { modal('<h2>' + esc(t('device')) + '</h2><div class="devid"><b>' + esc(DID) + '</b></div><div class="btn f" data-a="_close">' + esc(t('close')) + '</div>'); },
        sup: support,
        ref: function () { L.cats = null; V.movie.cats = null; V.series.cats = null; toast(t('refresh_ok')); },
        pl: function () { loadPlaylists(true); },
        about: function () { modal('<img class="logo" src="' + esc(S.brand.logo) + '"><h1>' + esc(S.brand.name) + '</h1><p>v' + VER + ' · ' + (PLATFORM === 'lg_tv' ? 'LG webOS' : 'Samsung Tizen') + '</p><div class="btn f" data-a="_close">' + esc(t('close')) + '</div>'); }
      }
    });
    S.screen = 'set'; clock();
  }
  function support() {
    var st = S.status || {}, wa = waLink(st.support_whatsapp), name = st.support_name || '';
    var h = '<h2>' + esc(t('support')) + '</h2>' + (name ? '<h1 style="font-size:36px">' + esc(name) + '</h1>' : '');
    if (wa) h += '<p>WhatsApp: +' + esc(String(st.support_whatsapp).replace(/\D+/g, '')) + '</p>' + (st.support_note ? '<p>' + esc(st.support_note) + '</p>' : '') + qrHtml(wa) + '<p>' + esc(t('sup_scan')) + '</p>';
    else h += '<p>' + esc(st.owner === 'reseller' ? t('sup_talk') : t('sup_none')) + '</p>';
    h += '<div class="devid">' + esc(t('act_dev')) + '<b>' + esc(DID) + '</b></div><div class="btn f" data-a="_close">' + esc(t('close')) + '</div>';
    modal(h);
  }

  /* ---------------- início ---------------- */
  // Samsung: registra as teclas de mídia e números do controle
  try { if (window.tizen && tizen.tvinputdevice) { ['MediaPlayPause', 'MediaPlay', 'MediaPause', 'ChannelUp', 'ChannelDown', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'].forEach(function (k) { try { tizen.tvinputdevice.registerKey(k); } catch (e) {} }); } } catch (e) {}
  // a cada 30 minutos confere se o aparelho continua ativo
  setInterval(function () {
    if (S.screen === 'act') return;
    api('device_status', {}, function (err, arr) {
      if (err) return;
      var o = arr && arr[0] ? arr[0] : {};
      saveStatus(o);
      if (o.status && o.status !== 'active') { stopPreview(); var pl = $('player'); if (pl) pl.parentNode.removeChild(pl); activation(o.status, ''); }
    });
  }, 30 * 60000);
  boot();
})();
