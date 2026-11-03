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
  // Whale TV (Philips, Sharp, Toshiba, JVC...): app aberto pelo endereço /tv/?p=whale ou navegador da Whale
  var IS_WHALE = /[?&]p=whale\b/i.test(location.search) || /WhaleTV|Whale ?OS|Zeasn/i.test(UA);
  // Titan OS (Philips e JVC desde 2023): app aberto pelo endereço /tv/?p=titan; o navegador traz "TitanOS/" no UA
  var IS_TITAN = /[?&]p=titan\b/i.test(location.search) || /TitanOS\//i.test(UA);
  // Windows (app da Microsoft Store): aberto pelo endereço /tv/?p=windows; usa mouse e teclado
  var IS_WIN = /[?&]p=windows\b/i.test(location.search);
  var PLATFORM = IS_WIN ? 'windows' : (IS_TITAN ? 'titan_os' : (IS_WHALE ? 'whale_tv' : (/Tizen/i.test(UA) ? 'samsung_tv' : 'lg_tv')));
  var PLATFORM_NAME = { windows: 'Windows', titan_os: 'Titan OS', whale_tv: 'Whale TV', samsung_tv: 'Samsung Tizen', lg_tv: 'LG webOS' }[PLATFORM];
  var DID = LS.get('bp_did', '');
  if (!DID) {
    DID = 'tv';
    for (var i = 0; i < 14; i++) DID += '0123456789abcdef'.charAt(Math.floor(Math.random() * 16));
    LS.set('bp_did', DID);
  }

  /* ---------------- idiomas ---------------- */
  var T = {
    pt: {
      buf: 'Buffer', buf_n: 'Normal', buf_h: 'Alto', buf_m: 'Máximo',
      buf_msg: 'Buffer maior trava menos em internet fraca, mas o ao vivo fica alguns segundos mais atrasado.',
      player: 'Player', pl_auto: 'Automático', pl_native: 'Player da TV', pl_hls: 'Player compatível',
      player_msg: 'Se algum canal travar ou não abrir, troque o player.',
      lic_reset: 'Resetar licença', lic_reset_msg: 'Isso desvincula este aparelho da revenda atual e apaga as listas. Depois você digita um novo código. Deseja continuar?', lic_yes: 'Sim, resetar', lic_cancel: 'Cancelar', lic_done: 'Licença resetada. Digite o novo código.',
      act_title: 'Ative este aparelho', act_msg: 'Digite o código da sua revenda (6 letras) ou da sua assinatura (8 números).',
      act_dev: 'ID do aparelho', act_btn: 'Ativar', act_check: 'Checar agora', act_wait: 'Aguarde o revendedor liberar',
      act_wait_msg: 'Seu código foi enviado. Esta tela verifica sozinha a cada 5 segundos.', act_other: 'Usar outro código',
      act_expired: 'Sua ativação venceu', act_expired_msg: 'Fale com a sua revenda para renovar.', act_invalid: 'Código inválido. Use 6 letras ou 8 números.',
      act_help: 'Peça o seu código de ativação ao seu fornecedor ou revenda.',
      buy_title: 'Não tem um código?', buy_msg: 'Aponte a câmera do celular para o QR code e compre a sua licença no site. Você recebe o código na hora.', buy_or: 'ou acesse', trial_title: 'Teste grátis de {0} dias', trial_msg: 'Aponte a câmera do celular para o QR code, crie a sua conta e ganhe {0} dias grátis. Você recebe o código na hora.',
      no_net: 'Sem conexão com a internet. Verifique e tente de novo.', server_err: 'Não foi possível falar com o servidor. Tente de novo.',
      up_title: 'Envie a sua lista', up_msg: 'Aponte a câmera do celular para o QR code e digite o usuário e a senha da sua lista. Esta tela abre sozinha quando a lista chegar.', up_wait: 'Aguardando a lista…',
      no_list: 'Nenhuma lista neste aparelho', no_list_msg: 'Peça para a sua revenda adicionar a sua lista no painel.', no_list_cust: 'Entre na sua área do cliente em {0} e adicione a sua lista em "Playlists deste aparelho". Depois aperte Tentar de novo.', retry: 'Tentar de novo',
      list_err: 'Não foi possível carregar esta lista. Fale com a sua revenda.',
      live: 'TV AO VIVO', movies: 'FILMES', series: 'SÉRIES', playlist: 'PLAYLIST', settings: 'CONFIGURAÇÕES',
      categories: 'Categorias', loading: 'Carregando…', empty: 'Nada por aqui.', now: 'Agora', next: 'A seguir', no_epg: 'Sem guia de programação',
      play: 'Assistir', resume: 'Continuar de', from_start: 'Do início', continue: 'Continuar assistindo', season: 'Temporada',
      reconnect: 'Reconectando…', p_movie: 'FILME', p_series: 'SÉRIE', p_pause: 'Pausar', p_play: 'Assistir', p_aspect: 'Tela', p_asp_contain: 'Original', p_asp_cover: 'Preencher', p_asp_fill: 'Esticar',
      p_audio: 'Áudio e legenda', p_diag: 'Diagnóstico', p_back: 'Voltar', p_h_bar: 'barra do vídeo', p_h_pick: 'escolhe', p_h_ok: 'confirma', p_h_exit: 'sai',
      p_no_tracks: 'Este vídeo não tem outras faixas de áudio ou legenda.', p_audio_h: 'Áudio', p_sub_h: 'Legenda', p_sub_off: 'Sem legenda', p_diag_h: 'Diagnóstico do vídeo',
      d_res: 'Resolução', d_time: 'Tempo', d_buf: 'Buffer à frente', d_state: 'Estado', d_drop: 'Quadros perdidos', d_player: 'Player', d_host: 'Servidor', d_dev: 'Aparelho', d_err: 'Erro',
      l_list: 'Ao vivo', l_fav: 'Favoritar', l_favd: 'Favorito', l_epg: 'Programação', l_prev: 'Canal anterior', l_zoom: 'Zoom', l_reload: 'Recarregar', l_live: 'AO VIVO', l_h_open: 'abre', l_h_zap: 'troca de canal', l_h_close: 'fecha', l_none: 'Ainda não há canal anterior.',
      ch_off: 'Este canal está fora do ar no momento.', play_err: 'Não foi possível reproduzir agora.',
      connected: 'Conectado', language: 'Idioma', device: 'ID do aparelho', support: 'Suporte', refresh: 'Atualizar lista', about: 'Sobre',
      refresh_ok: 'Lista atualizada.', sup_talk: 'Fale com a sua revenda para ter suporte.', sup_none: 'Contato de suporte não cadastrado.',
      sup_scan: 'Aponte a câmera do celular para abrir o WhatsApp', close: 'Fechar',
      exp_title: 'Seu plano está vencendo', exp_days: 'Seu plano vence em {0} dia(s).', exp_today: 'Seu plano vence hoje.',
      exp_reseller: 'Fale com a sua revenda para renovar e continuar assistindo.', exp_support: 'Fale com o suporte para renovar e continuar assistindo.',
      exit_hint: 'Para sair, feche a janela no X do canto de cima.', exit_q: 'Sair do app?', yes: 'Sim', no: 'Não',
      search: 'BUSCA', favorites: 'FAVORITOS', cont_tile: 'CONTINUAR', cw_empty: 'Nada para continuar ainda. Assista um canal, filme ou episódio e ele aparece aqui.',
      search_hint: 'Digite o nome do canal, filme ou série', search_go: 'Buscar', search_none: 'Nada encontrado.', searching: 'Buscando…',
      fav_add: '★ Favoritar', fav_del: '★ Remover dos favoritos', fav_added: 'Adicionado aos favoritos.', fav_removed: 'Removido dos favoritos.',
      fav_empty: 'Nenhum favorito ainda.', fav_live_hint: 'Botão colorido (ou INFO) = favoritar canal', fav_which: 'Favoritos de…',
      ch_title: 'Canais', mv_title: 'Filmes', sr_title: 'Séries'
    },
    en: {
      buf: 'Buffer', buf_n: 'Normal', buf_h: 'High', buf_m: 'Maximum',
      buf_msg: 'A bigger buffer freezes less on weak internet, but live TV runs a few seconds behind.',
      player: 'Player', pl_auto: 'Automatic', pl_native: 'TV player', pl_hls: 'Compatible player',
      player_msg: 'If a channel freezes or does not open, change the player.',
      lic_reset: 'Reset license', lic_reset_msg: 'This unlinks this device from the current reseller and erases the lists. Then you can enter a new code. Continue?', lic_yes: 'Yes, reset', lic_cancel: 'Cancel', lic_done: 'License reset. Enter the new code.',
      act_title: 'Activate this device', act_msg: 'Enter your reseller code (6 letters) or your subscription code (8 numbers).',
      act_dev: 'Device ID', act_btn: 'Activate', act_check: 'Check now', act_wait: 'Waiting for your reseller',
      act_wait_msg: 'Your code was sent. This screen checks automatically every 5 seconds.', act_other: 'Use another code',
      act_expired: 'Your activation has expired', act_expired_msg: 'Contact your reseller to renew.', act_invalid: 'Invalid code. Use 6 letters or 8 numbers.',
      act_help: 'Ask your provider or reseller for your activation code.',
      buy_title: "Don't have a code?", buy_msg: 'Point your phone camera at the QR code and buy your license on the website. You get the code right away.', buy_or: 'or visit', trial_title: '{0}-day free trial', trial_msg: 'Point your phone camera at the QR code, create your account and get {0} days free. You get the code right away.',
      no_net: 'No internet connection. Check it and try again.', server_err: 'Could not reach the server. Try again.',
      up_title: 'Send your list', up_msg: 'Point your phone camera at the QR code and enter your list username and password. This screen opens by itself when the list arrives.', up_wait: 'Waiting for the list…',
      no_list: 'No playlist on this device', no_list_msg: 'Ask your reseller to add your list in the panel.', no_list_cust: 'Sign in to your customer area at {0} and add your list in "Playlists for this device". Then press Try again.', retry: 'Try again',
      list_err: 'Could not load this list. Contact your reseller.',
      live: 'LIVE TV', movies: 'MOVIES', series: 'SERIES', playlist: 'PLAYLIST', settings: 'SETTINGS',
      categories: 'Categories', loading: 'Loading…', empty: 'Nothing here.', now: 'Now', next: 'Next', no_epg: 'No program guide',
      play: 'Play', resume: 'Resume from', from_start: 'From start', continue: 'Continue watching', season: 'Season',
      reconnect: 'Reconnecting…', p_movie: 'MOVIE', p_series: 'SERIES', p_pause: 'Pause', p_play: 'Play', p_aspect: 'Screen', p_asp_contain: 'Original', p_asp_cover: 'Fill', p_asp_fill: 'Stretch',
      p_audio: 'Audio & subtitles', p_diag: 'Diagnostics', p_back: 'Back', p_h_bar: 'video bar', p_h_pick: 'choose', p_h_ok: 'confirm', p_h_exit: 'exit',
      p_no_tracks: 'This video has no other audio or subtitle tracks.', p_audio_h: 'Audio', p_sub_h: 'Subtitles', p_sub_off: 'No subtitles', p_diag_h: 'Video diagnostics',
      d_res: 'Resolution', d_time: 'Time', d_buf: 'Buffer ahead', d_state: 'State', d_drop: 'Dropped frames', d_player: 'Player', d_host: 'Server', d_dev: 'Device', d_err: 'Error',
      l_list: 'Live', l_fav: 'Favorite', l_favd: 'Favorited', l_epg: 'Guide', l_prev: 'Previous channel', l_zoom: 'Zoom', l_reload: 'Reload', l_live: 'LIVE', l_h_open: 'open', l_h_zap: 'change channel', l_h_close: 'close', l_none: 'No previous channel yet.',
      ch_off: 'This channel is offline right now.', play_err: 'Could not play right now.',
      connected: 'Connected', language: 'Language', device: 'Device ID', support: 'Support', refresh: 'Refresh list', about: 'About',
      refresh_ok: 'List refreshed.', sup_talk: 'Contact your reseller for support.', sup_none: 'No support contact registered.',
      sup_scan: 'Point your phone camera at the code to open WhatsApp', close: 'Close',
      exp_title: 'Your plan is about to expire', exp_days: 'Your plan expires in {0} day(s).', exp_today: 'Your plan expires today.',
      exp_reseller: 'Talk to your reseller to renew and keep watching.', exp_support: 'Contact support to renew and keep watching.',
      exit_hint: 'To exit, close the window with the X at the top corner.', exit_q: 'Do you want to exit?', yes: 'Yes', no: 'No',
      search: 'SEARCH', favorites: 'FAVORITES', cont_tile: 'CONTINUE', cw_empty: 'Nothing to continue yet. Watch a channel, movie or episode and it will show up here.',
      search_hint: 'Type the name of a channel, movie or series', search_go: 'Search', search_none: 'Nothing found.', searching: 'Searching…',
      fav_add: '★ Add to favorites', fav_del: '★ Remove from favorites', fav_added: 'Added to favorites.', fav_removed: 'Removed from favorites.',
      fav_empty: 'No favorites yet.', fav_live_hint: 'Color button (or INFO) = favorite channel', fav_which: 'Favorites from…',
      ch_title: 'Channels', mv_title: 'Movies', sr_title: 'Series'
    },
    es: {
      buf: 'Buffer', buf_n: 'Normal', buf_h: 'Alto', buf_m: 'Máximo',
      buf_msg: 'Un buffer mayor se traba menos con internet débil, pero el en vivo queda unos segundos más atrasado.',
      player: 'Reproductor', pl_auto: 'Automático', pl_native: 'Reproductor de la TV', pl_hls: 'Reproductor compatible',
      player_msg: 'Si algún canal se traba o no abre, cambia el reproductor.',
      lic_reset: 'Restablecer licencia', lic_reset_msg: 'Esto desvincula este dispositivo del revendedor actual y borra las listas. Luego escribes un nuevo código. ¿Deseas continuar?', lic_yes: 'Sí, restablecer', lic_cancel: 'Cancelar', lic_done: 'Licencia restablecida. Escribe el nuevo código.',
      act_title: 'Activa este dispositivo', act_msg: 'Escribe el código de tu revendedor (6 letras) o de tu suscripción (8 números).',
      act_dev: 'ID del dispositivo', act_btn: 'Activar', act_check: 'Verificar ahora', act_wait: 'Espera que tu revendedor lo libere',
      act_wait_msg: 'Tu código fue enviado. Esta pantalla verifica sola cada 5 segundos.', act_other: 'Usar otro código',
      act_expired: 'Tu activación venció', act_expired_msg: 'Habla con tu revendedor para renovar.', act_invalid: 'Código inválido. Usa 6 letras u 8 números.',
      act_help: 'Pide tu código de activación a tu proveedor o revendedor.',
      buy_title: '¿No tienes un código?', buy_msg: 'Apunta la cámara del celular al código QR y compra tu licencia en el sitio. Recibes el código al instante.', buy_or: 'o entra a', trial_title: 'Prueba gratis de {0} días', trial_msg: 'Apunta la cámara del celular al código QR, crea tu cuenta y obtén {0} días gratis. Recibes el código al instante.',
      no_net: 'Sin conexión a internet. Revisa e intenta de nuevo.', server_err: 'No fue posible conectar con el servidor. Intenta de nuevo.',
      up_title: 'Envía tu lista', up_msg: 'Apunta la cámara del celular al código QR y escribe el usuario y la contraseña de tu lista. Esta pantalla se abre sola cuando llega la lista.', up_wait: 'Esperando la lista…',
      no_list: 'Ninguna lista en este dispositivo', no_list_msg: 'Pide a tu revendedor que agregue tu lista en el panel.', no_list_cust: 'Entra en tu área de cliente en {0} y agrega tu lista en "Listas de este dispositivo". Luego presiona Intentar de nuevo.', retry: 'Intentar de nuevo',
      list_err: 'No fue posible cargar esta lista. Habla con tu revendedor.',
      live: 'TV EN VIVO', movies: 'PELÍCULAS', series: 'SERIES', playlist: 'PLAYLIST', settings: 'CONFIGURACIÓN',
      categories: 'Categorías', loading: 'Cargando…', empty: 'Nada por aquí.', now: 'Ahora', next: 'A continuación', no_epg: 'Sin guía de programación',
      play: 'Ver', resume: 'Continuar desde', from_start: 'Desde el inicio', continue: 'Seguir viendo', season: 'Temporada',
      reconnect: 'Reconectando…', p_movie: 'PELÍCULA', p_series: 'SERIE', p_pause: 'Pausar', p_play: 'Ver', p_aspect: 'Pantalla', p_asp_contain: 'Original', p_asp_cover: 'Llenar', p_asp_fill: 'Estirar',
      p_audio: 'Audio y subtítulos', p_diag: 'Diagnóstico', p_back: 'Volver', p_h_bar: 'barra del video', p_h_pick: 'elige', p_h_ok: 'confirma', p_h_exit: 'sale',
      p_no_tracks: 'Este video no tiene otras pistas de audio o subtítulos.', p_audio_h: 'Audio', p_sub_h: 'Subtítulos', p_sub_off: 'Sin subtítulos', p_diag_h: 'Diagnóstico del video',
      d_res: 'Resolución', d_time: 'Tiempo', d_buf: 'Buffer adelante', d_state: 'Estado', d_drop: 'Cuadros perdidos', d_player: 'Reproductor', d_host: 'Servidor', d_dev: 'Dispositivo', d_err: 'Error',
      l_list: 'En vivo', l_fav: 'Favorito', l_favd: 'Favorito ★', l_epg: 'Programación', l_prev: 'Canal anterior', l_zoom: 'Zoom', l_reload: 'Recargar', l_live: 'EN VIVO', l_h_open: 'abre', l_h_zap: 'cambia de canal', l_h_close: 'cierra', l_none: 'Aún no hay canal anterior.',
      ch_off: 'Este canal está fuera del aire ahora.', play_err: 'No fue posible reproducir ahora.',
      connected: 'Conectado', language: 'Idioma', device: 'ID del dispositivo', support: 'Soporte', refresh: 'Actualizar lista', about: 'Acerca de',
      refresh_ok: 'Lista actualizada.', sup_talk: 'Habla con tu revendedor para tener soporte.', sup_none: 'Contacto de soporte no registrado.',
      sup_scan: 'Apunta la cámara del celular al código para abrir WhatsApp', close: 'Cerrar',
      exp_title: 'Tu plan está por vencer', exp_days: 'Tu plan vence en {0} día(s).', exp_today: 'Tu plan vence hoy.',
      exp_reseller: 'Habla con tu revendedor para renovar y seguir viendo.', exp_support: 'Habla con soporte para renovar y seguir viendo.',
      exit_hint: 'Para salir, cierra la ventana con la X de la esquina superior.', exit_q: '¿Salir de la app?', yes: 'Sí', no: 'No',
      search: 'BUSCAR', favorites: 'FAVORITOS', cont_tile: 'SEGUIR', cw_empty: 'Nada para seguir todavía. Mira un canal, película o episodio y aparecerá aquí.',
      search_hint: 'Escribe el nombre del canal, película o serie', search_go: 'Buscar', search_none: 'No se encontró nada.', searching: 'Buscando…',
      fav_add: '★ Agregar a favoritos', fav_del: '★ Quitar de favoritos', fav_added: 'Agregado a favoritos.', fav_removed: 'Quitado de favoritos.',
      fav_empty: 'Ningún favorito todavía.', fav_live_hint: 'Botón de color (o INFO) = favorito', fav_which: 'Favoritos de…',
      ch_title: 'Canales', mv_title: 'Películas', sr_title: 'Series'
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
    info: '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>',
    search: '<path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>',
    star: '<path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>',
    play: '<path d="M8 5v14l11-7z"/>',
    pbk: '<path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>',
    cal: '<path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-1V1h-2zm3 18H5V8h14v11z"/>',
    undo: '<path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/>',
    rew: '<path d="M11 18V6l-8.5 6 8.5 6zm.5-6l8.5 6V6l-8.5 6z"/>',
    ff: '<path d="M4 18l8.5-6L4 6v12zm9-12v12l8.5-6L13 6z"/>',
    pause: '<path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>',
    aspect: '<path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>',
    cc: '<path d="M19 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm-8 7H9.5v-.5h-2v3h2V13H11v1c0 .55-.45 1-1 1H7c-.55 0-1-.45-1-1v-4c0-.55.45-1 1-1h3c.55 0 1 .45 1 1v1zm7 0h-1.5v-.5h-2v3h2V13H18v1c0 .55-.45 1-1 1h-3c-.55 0-1-.45-1-1v-4c0-.55.45-1 1-1h3c.55 0 1 .45 1 1v1z"/>',
    gauge: '<path d="M12 4a9 9 0 0 0-7.8 13.5l1.7-1A7 7 0 1 1 19 12h2a9 9 0 0 0-9-8zm4.2 3.4l-5 5.6a1.5 1.5 0 1 0 2.1 2.1l5.6-5-2.7-2.7zM3 19h18v2H3z"/>'
  };

  /* ---------------- estado ---------------- */
  var S = {
    screen: '', keys: null, brand: { name: 'IPTV Best Pro', logo: SRV + '/assets/images/brand/iptvbestpro_logo.png' },
    status: {}, pls: [], pl: null, login: null, base: '', modal: null
  };
  var stage = $('stage');

  function fit() {
    var w = window.innerWidth || 1920, h = window.innerHeight || 1080;
    var s = Math.min(w / 1920, h / 1080);
    // o fundo cobre a janela inteira (TV, computador, celular, tela larga); a tela 16:9 fica centralizada por cima
    stage.style.width = Math.ceil(w / s) + 'px';
    stage.style.height = Math.ceil(h / s) + 'px';
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
      device_model: { windows: 'Windows PC', lg_tv: 'LG webOS TV', whale_tv: 'Whale TV', titan_os: 'Titan OS TV', samsung_tv: 'Samsung Smart TV' }[PLATFORM], os_version: '', app_version: VER, app_name: 'IPTV Best Pro' };
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
    if (c === 403 || c === 404 || c === 405 || c === 406 || c === 457) return 'fav'; // botões coloridos / INFO
    if (c >= 48 && c <= 57) return 'n' + (c - 48);
    if (c >= 96 && c <= 105) return 'n' + (c - 96);
    return '';
  }
  // Multitarefa (Samsung/LG): app escondido (Home, outra fonte) pausa o vídeo; ao voltar, continua de onde estava
  document.addEventListener('visibilitychange', function () {
    [L.video, PL.video].forEach(function (v) {
      if (!v) return;
      try {
        if (document.hidden) { v._bgPaused = !v.paused; if (!v.paused) v.pause(); }
        else if (v._bgPaused) { v._bgPaused = false; var p = v.play(); if (p && p.catch) p.catch(function () {}); }
      } catch (e) {}
    });
  });
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
  // Windows: botão Voltar na tela (mouse), botão "voltar" do mouse e rodinha para subir/descer nas listas
  function goBack() { if (S.modal) closeModal(); else if (S.back) S.back(); }
  if (IS_WIN) {
    document.documentElement.className += ' win';
    var wb = document.createElement('div');
    wb.id = 'winback'; wb.title = 'Voltar'; wb.innerHTML = '&#8592;';
    wb.addEventListener('click', function (e) { e.preventDefault(); goBack(); });
    document.body.appendChild(wb);
    document.addEventListener('mouseup', function (e) { if (e.button === 3) { e.preventDefault(); goBack(); } });
    var wheelAt = 0;
    document.addEventListener('wheel', function (e) {
      if (S.screen === 'play') return;
      var now = Date.now();
      if (now - wheelAt < 120 || !e.deltaY) return;
      wheelAt = now;
      move(e.deltaY > 0 ? 'down' : 'up');
    }, { passive: true });
  }
  stage.addEventListener('click', function (e) {
    var el = e.target; while (el && el !== stage && !(el.classList && el.classList.contains('f'))) el = el.parentNode;
    if (el && el !== stage) { focus(el); activate(el); }
  });
  function activate(el) {
    if (el.tagName === 'INPUT') { el.focus(); return; }
    var a = el.getAttribute('data-a');
    if (a === '_reload') { toast(t('loading')); try { sessionStorage.setItem('bp_fresh', '1'); } catch (e) {} setTimeout(function () { location.reload(); }, 300); return; }
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
      // tela inicial: dia da semana e data, hora grande e o botão Atualizar na ponta direita (recarrega o app inteiro sem sair)
      '<div class="right">' + (title ? '' : '<div class="hdate"><b id="hday"></b><span id="hdt"></span></div>') + '<span id="clock"></span>' +
      (title ? '' : '<div class="reload f" data-a="_reload">' + svg(IC.sync, 44) + '</div>') + '</div></div>';
  }
  var timers = [];
  function every(fn, ms) { timers.push(setInterval(fn, ms)); }
  function stopTimers() { for (var i = 0; i < timers.length; i++) clearInterval(timers[i]); timers = []; }
  var WDAYS = { pt: ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'],
    en: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    es: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'] };
  var MONTHS = { pt: ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'],
    en: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    es: ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'] };
  function clock() {
    var c = $('clock'); if (!c) return;
    var d = new Date(), lg = WDAYS[LANG] ? LANG : 'pt';
    c.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes());
    var wd = $('hday'), dt = $('hdt');
    if (wd) wd.textContent = WDAYS[lg][d.getDay()];
    if (dt) dt.textContent = lg === 'en' ? MONTHS.en[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() : d.getDate() + ' ' + MONTHS[lg][d.getMonth()] + ' ' + d.getFullYear();
  }
  function qrHtml(text) {
    try { var q = qrcode(0, 'M'); q.addData(text); q.make(); return '<div class="qr">' + q.createImgTag(8, 0) + '</div>'; } catch (e) { return ''; }
  }
  /* ---------------- vídeo: player da TV ou, se ela não tocar HLS, o hls.js ---------------- */
  // Chrome/Edge novos têm player HLS nativo: ele toca o vídeo dos servidores IPTV (não precisa de CORS e passa http para https sozinho); o hls.js não toca
  var NATIVE_HLS = !!document.createElement('video').canPlayType('application/vnd.apple.mpegurl');
  function loadHls(cb) {
    if (window.Hls) return cb();
    var sc = document.createElement('script'); sc.src = SRV + '/tv/hls.light.min.js'; sc.onload = cb; sc.onerror = cb; document.body.appendChild(sc);
  }
  // Configurações > Buffer (só vale no player compatível; o player da TV controla o próprio buffer)
  var BUFS = {
    n: { maxBufferLength: 40, maxMaxBufferLength: 90, liveSyncDurationCount: 5, liveMaxLatencyDurationCount: 12, maxBufferSize: 60 * 1000 * 1000, stall: 10 },
    h: { maxBufferLength: 60, maxMaxBufferLength: 180, liveSyncDurationCount: 7, liveMaxLatencyDurationCount: 16, maxBufferSize: 80 * 1000 * 1000, stall: 12 },
    m: { maxBufferLength: 90, maxMaxBufferLength: 300, liveSyncDurationCount: 10, liveMaxLatencyDurationCount: 20, maxBufferSize: 100 * 1000 * 1000, stall: 15 }
  };
  function bufPref() { var b = LS.get('bp_buf', 'n'); return BUFS[b] ? b : 'n'; }
  function playerPref() { var p = LS.get('bp_player', 'auto'); return p === 'native' || p === 'hls' ? p : 'auto'; }
  function unsetSrc(v) {
    if (!v) return;
    if (v._stallT) { clearInterval(v._stallT); v._stallT = null; }
    if (v._hls) { try { v._hls.destroy(); } catch (e) {} v._hls = null; }
  }
  // Retomada: o vídeo já tocava e ficou parado carregando por alguns segundos -> reconecta sozinho
  // (filme e série voltam do mesmo ponto). Mais de 3 vezes em 2 minutos: deixa o aviso de erro da tela agir.
  function watchStall(v, url, onFatal, useHls) {
    var lastT = -1, still = 0, limit = BUFS[bufPref()].stall;
    v._played = false;
    v.addEventListener('playing', function () { v._played = true; });
    v._stallT = setInterval(function () {
      if (!v._played || v.paused || document.hidden) { still = 0; lastT = v.currentTime; return; }
      if (v.currentTime !== lastT) { lastT = v.currentTime; still = 0; return; }
      still += 2;
      if (still < limit) return;
      var now = Date.now();
      v._stalls = (v._stalls || []).filter(function (x) { return now - x < 120000; });
      if (v._stalls.length >= 3) { clearInterval(v._stallT); v._stallT = null; if (onFatal) onFatal(); return; }
      v._stalls.push(now);
      var pos = v.currentTime, vod = isFinite(v.duration) && v.duration > 0;
      setSrc(v, url, onFatal, useHls);
      if (vod && pos > 1) {
        var seek = function () { v.removeEventListener('loadedmetadata', seek); try { v.currentTime = pos; } catch (e) {} };
        v.addEventListener('loadedmetadata', seek);
      }
    }, 2000);
  }
  function setSrc(v, url, onFatal, forceHls) {
    unsetSrc(v);
    v._url = url;
    var pref = playerPref(), isHls = /\.m3u8(\?|$)/i.test(url);
    // Player da TV escolhido: não troca para o compatível; Player compatível: usa sempre o hls.js
    var useHls = isHls && (!NATIVE_HLS || pref === 'hls' || (forceHls && pref !== 'native'));
    watchStall(v, url, onFatal, useHls);
    if (useHls) {
      loadHls(function () {
        if (window.Hls && Hls.isSupported()) {
          var bc = BUFS[bufPref()];
          var h = new Hls({ maxBufferLength: bc.maxBufferLength, maxMaxBufferLength: bc.maxMaxBufferLength, maxBufferSize: bc.maxBufferSize, backBufferLength: 30, liveSyncDurationCount: bc.liveSyncDurationCount, liveMaxLatencyDurationCount: bc.liveMaxLatencyDurationCount, manifestLoadingMaxRetry: 6, levelLoadingMaxRetry: 6, fragLoadingMaxRetry: 6, nudgeMaxRetry: 8 });
          v._hls = h;
          // queda de rede ou falha de vídeo: tenta se recuperar sozinho (sem recarregar o canal) antes de desistir
          var rec = { net: 0, media: 0 };
          h.on(Hls.Events.ERROR, function (ev, d) {
            if (d) v._dbg = d.type + '/' + d.details + (d.response && d.response.code !== undefined ? ' ' + d.response.code : '');
            if (!d || !d.fatal) return;
            if (d.type === Hls.ErrorTypes.NETWORK_ERROR && rec.net < 3) { rec.net++; try { h.startLoad(); } catch (e) {} return; }
            if (d.type === Hls.ErrorTypes.MEDIA_ERROR && rec.media < 2) { rec.media++; try { h.recoverMediaError(); } catch (e) {} return; }
            if (onFatal) onFatal();
          });
          h.on(Hls.Events.FRAG_LOADED, function () { rec.net = 0; });
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
    var o = parseInt(tc.background_overlay, 10); if (isNaN(o)) o = 55;
    var css = '#stage:before{background:rgba(5,8,20,' + Math.max(0, Math.min(95, o)) / 100 + ')}';
    // Cores escolhidas no tema (Personalizar tema): tudo o que muda no painel muda aqui
    var ms = tc.customized === '1' && tc.model_settings ? tc.model_settings : (tc.model_defaults || {}); // tema sem personalizar: cores originais do modelo
    TH.id = String(tc.theme_id || ''); TH.ms = ms || {};
    function pick(keys) { for (var i = 0; i < keys.length; i++) { var v = hexRgb(ms[keys[i]]); if (v) return v; } return ''; }
    var acc = pick(['theme_primary', 'accent', 'accent_start', 'buttons', 'live_color']);
    if (acc) {
      var a = acc.split(','), dark = (0.299 * a[0] + 0.587 * a[1] + 0.114 * a[2]) / 255 > 0.6;
      var A = 'rgb(' + acc + ')';
      css += '.f.on{box-shadow:0 0 0 5px ' + A + ',0 10px 30px rgba(0,0,0,.5)}.spin{border-top-color:' + A + '}.code.on{border-color:' + A + '}' +
        '.btn.gold{background:' + A + ';color:' + (dark ? '#1b1300' : '#fff') + '}.row.sel,.epg .ch,.seasons .btn.sel{color:' + A + '}' +
        '.row.sel:before,.player .track i{background:' + A + '}.row.on{background:rgba(' + acc + ',.16)}.zap,.toast{border-color:' + A + '}';
    }
    var card = pick(['theme_surface', 'card', 'panel_top']) || (ms.tile_auto === '0' ? hexRgb(ms.tile_color) : '');
    if (card) css += '.tile,.set,.card{background:rgba(' + card + ',.9)}.tile.on,.set.on{background:rgba(' + card + ',1)}.poster,.det .cover{background:rgb(' + card + ')}';
    var txt = hexRgb(ms.theme_text);
    if (txt) css += '#stage{color:rgb(' + txt + ')}';
    // cor de cada seção na tela inicial (ícone ou cartão)
    var secs = { live: ['live_color', 'live_top'], movies: ['movies_color', 'movies_top'], series: ['series_color', 'series_top'] };
    for (var k in secs) {
      var ic = hexRgb(ms[secs[k][0]]), tl = hexRgb(ms[secs[k][1]]);
      if (ic) css += '.tile[data-a="' + k + '"] .ic{color:rgb(' + ic + ')}';
      if (tl) css += '.tile[data-a="' + k + '"]{background:rgba(' + tl + ',.92)}';
    }
    // fundo: imagem do tema, pôsteres, cores do tema (degradê) ou o padrão
    var top = pick(['bg_top', 'bg', 'theme_background']), bot = hexRgb(ms.bg_bottom) || top;
    if (tc.background_image || (S.posters && S.posters.length) || !top) {
      setBg(tc.background_image || (S.posters && S.posters.length ? S.posters[0] : DEF_BG));
    } else {
      stage.style.backgroundImage = 'none';
      css += '#stage{background:-webkit-linear-gradient(top,rgb(' + top + '),rgb(' + bot + '));background:linear-gradient(to bottom,rgb(' + top + '),rgb(' + bot + '))}#stage:before{background:transparent}';
    }
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
    var keep = { bp_did: 1, bp_lang: 1, bp_buf: 1, bp_player: 1 }; // preferências do aparelho ficam
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
    // Sem revenda: QR code para comprar a licença no site (aparelho de revenda vencido renova com a revenda)
    var st = S.status || {};
    if (!waiting && st.owner !== 'reseller') {
      var buy = SRV + '/cliente/register?ref=tv&device=' + encodeURIComponent(DID);
      html = html.replace('<div class="card">', '<div class="card withqr">') +
        (+st.trial_days > 0
          ? '<div class="buyqr"><div class="trialtag">' + esc(t('trial_title', st.trial_days)) + '</div>' + qrHtml(buy) + '<p>' + esc(t('trial_msg', st.trial_days)) + '</p>'
          : '<div class="buyqr"><h2>' + esc(t('buy_title')) + '</h2>' + qrHtml(buy) + '<p>' + esc(t('buy_msg')) + '</p>') +
        '<p class="buyurl">' + esc(t('buy_or')) + ' <b>' + esc(SRV.replace(/^https?:\/\//, '')) + '</b></p></div>';
    }
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
      { y: function () {
        try { if (window.webOS && webOS.platformBack) webOS.platformBack(); else if (typeof SmartTvA_API !== 'undefined' && SmartTvA_API.exit) SmartTvA_API.exit(); else if (window.tizen) tizen.application.getCurrentApplication().exit(); else window.close(); } catch (e) { try { window.close(); } catch (e2) {} }
        // Windows / navegador: o sistema não deixa a página fechar a janela sozinha; avisa como sair
        setTimeout(function () { if (!document.hidden) { closeModal(); toast(t('exit_hint'), 5000); } }, 600);
      } });
  }

  /* ---------------- listas do aparelho ---------------- */
  function loadPlaylists(chooser) {
    splash();
    // depois de ativar (ou trocar de dono) busca de novo a marca do dono do aparelho
    if (!chooser && !S.brandFresh) { S.brandFresh = true; return fetchBrand(function () { loadPlaylists(false); }); }
    // botão Atualizar: o painel confere o servidor de cada lista do zero
    var ex = {};
    try { if (sessionStorage.getItem('bp_fresh')) { ex.fresh = '1'; sessionStorage.removeItem('bp_fresh'); } } catch (e) {}
    api('get_device_user', ex, function (err, arr) {
      if (err) { toast(t(err.status === 0 ? 'no_net' : 'server_err')); return noList(); }
      S.pls = (arr || []).filter(function (p) { return p && p.user_name; });
      if (!S.pls.length) { clearLocal(); return noList(); }
      if (chooser) return playlists();
      var saved = LS.get('bp_pl', ''), pick = null;
      for (var i = 0; i < S.pls.length; i++) if (String(S.pls[i].id) === saved) pick = S.pls[i];
      if (!pick && saved) { var keep = S.pls; clearLocal(); S.pls = keep; } // a lista salva não é mais deste aparelho: apaga os dados dela (e mantém as listas que chegaram)
      login(pick || S.pls[0]);
    });
  }
  function noList() {
    // Aparelho de servidor parceiro: QR code da página /lista e confere a cada 5 segundos se a lista chegou
    var up = (S.status || {}).upload_url;
    if (up) {
      show('<div class="card withqr"><img class="logo" src="' + esc(S.brand.logo) + '"><h2>' + esc(t('up_title')) + '</h2><p>' + esc(t('up_msg')) + '</p>' +
        '<div class="devid">' + esc(t('act_dev')) + '<b>' + esc(DID) + '</b></div><div class="spin"></div><p>' + esc(t('up_wait')) + '</p></div>' +
        '<div class="buyqr"><h2>' + esc(t('up_title')) + '</h2>' + qrHtml(up) + '<p class="buyurl"><b>' + esc(up.replace(/^https?:\/\//, '').replace(/\?.*$/, '')) + '</b></p></div>',
        { back: confirmExit });
      S.screen = 'nolist';
      every(function () {
        if (S.screen !== 'nolist') return;
        api('get_device_user', {}, function (err, arr) {
          if (err || S.screen !== 'nolist') return;
          var got = (arr || []).filter(function (p) { return p && p.user_name; });
          if (got.length) loadPlaylists(false);
        });
      }, 5000);
      return;
    }
    show('<div class="card"><img class="logo" src="' + esc(S.brand.logo) + '"><h2>' + esc(t('no_list')) + '</h2><p>' + esc((S.status || {}).owner === 'customer' ? t('no_list_cust', SRV.replace(/^https?:\/\//, '') + '/cliente') : t('no_list_msg')) + '</p>' +
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
      // sempre o DNS cadastrado no painel (o servidor informado no login atende várias IPTVs; quem define o conteúdo é o DNS)
      S.base = String(pl.dns_base || j._dns || '').replace(/\/+$/, '');
      if (!S.base) { var si = j.server_info || {}, proto = si.server_protocol || 'http', port = proto === 'https' ? si.https_port : si.port; S.base = proto + '://' + si.url + (port ? ':' + port : ''); }
      LS.set('bp_pl', String(pl.id));
      // Servidor informado no login: só é usado se o DNS não tocar no navegador (alguns DNS redirecionam o vídeo para um servidor http sem CORS)
      var si2 = j.server_info || {}, pr2 = si2.server_protocol || 'http', po2 = pr2 === 'https' ? si2.https_port : si2.port;
      // página https: o servidor também em https (http é bloqueado pelo navegador)
      if (location.protocol === 'https:' && si2.url) { pr2 = 'https'; po2 = si2.https_port && String(si2.https_port) !== '443' ? si2.https_port : ''; }
      S.alt = si2.url ? pr2 + '://' + si2.url + (po2 && !(pr2 === 'http' && String(po2) === '80') ? ':' + po2 : '') : ''; S.altTried = false;
      // O app roda em https: o navegador bloqueia vídeo http (conteúdo misto, fica a tela preta). Se o mesmo DNS responde em https, usa https
      httpsBase(S.base, function (b) {
        S.base = b;
        home();
        expiryNotice();
      });
    });
  }
  function httpsBase(base, cb) {
    var m = /^http:\/\/([^\/:]+)(?::(\d+))?$/i.exec(base || '');
    if (location.protocol !== 'https:' || !m || !window.fetch) return cb(base);
    var up = 'https://' + m[1] + (m[2] && m[2] !== '80' ? ':' + m[2] : ''), done = false;
    function fin(b) { if (!done) { done = true; cb(b); } }
    var tm = setTimeout(function () { fin(base); }, 4000);
    fetch(up + '/player_api.php', { mode: 'no-cors', cache: 'no-store' }).then(function () { clearTimeout(tm); fin(up); }, function () { clearTimeout(tm); fin(base); });
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
  function clearCatalog() { try { var ks = []; for (var i = 0; i < localStorage.length; i++) ks.push(localStorage.key(i)); for (var j = 0; j < ks.length; j++) if (/^bp_(last|cw|pos|fav|search)/.test(ks[j])) localStorage.removeItem(ks[j]); } catch (e) {} }

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

  /* ---------------- modelos de tela inicial (os mesmos 8 temas do app Android e da Roku) ----------------
   * 1 OneUI · 2 Glossy · 3 Black Panther · 4 MovieUI · 5 VUI · 6 Natal · 7 Halloween · 8 BestPro (padrão)
   * Posições em pixels da tela de 1920x1080 (a tela inteira é escalada para a TV). Cores vêm do painel. */
  var TH = { id: '', ms: {} };
  var ICN = { tv: 'tv', movie: 'movie', series: 'series', star: 'star', playlist: 'list', settings: 'gear', search: 'search', play: 'play', refresh: 'sync', support: 'chat' };
  function thc(k, d) { var v = TH.ms ? TH.ms[k] : ''; v = String(v || ''); if (/^[0-9a-f]{6}$/i.test(v)) v = '#' + v; return /^#[0-9a-f]{6}$/i.test(v) ? v : d; }
  function thOn(k) { return !TH.ms || String(TH.ms[k]) !== '0'; }
  function hexA(h, a) { var r = hexRgb(h); return r ? 'rgba(' + r + ',' + (a === undefined ? 1 : a) + ')' : h; }
  function mixc(h, k) { // k > 0 clareia, k < 0 escurece
    var r = hexRgb(h); if (!r) return h; r = r.split(',');
    for (var i = 0; i < 3; i++) { var v = +r[i]; r[i] = Math.round(k >= 0 ? v + (255 - v) * k : v * (1 + k)); }
    return 'rgb(' + r.join(',') + ')';
  }
  function svgc(path, size, color) { return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="' + (color || '#fff') + '">' + path + '</svg>'; }
  var thCss = '', thN = 0;
  function ht(x, y, w, h, act, c) {
    var id = 'th' + (thN++), st = c.style || 'card';
    var css = 'left:' + x + 'px;top:' + y + 'px;width:' + w + 'px;height:' + h + 'px;';
    if (st === 'grad' || (c.c2 && st !== 'wide')) css += 'background:' + c.c1 + ';background:-webkit-linear-gradient(315deg,' + c.c2 + ',' + c.c1 + ');background:linear-gradient(135deg,' + c.c2 + ',' + c.c1 + ');';
    else css += 'background:' + (c.alpha ? hexA(c.c1, c.alpha) : c.c1) + ';';
    if (st === 'round') css += 'border-radius:50%;';
    if (c.ring) css += 'border:' + (st === 'wide' ? 5 : 2) + 'px solid ' + c.ring + ';';
    if (c.bar) css += 'border-left:12px solid ' + c.bar + ';';
    if (c.focus) thCss += '#' + id + '.on{box-shadow:0 0 0 6px ' + c.focus + ',0 12px 34px rgba(0,0,0,.55)}';
    var s = c.iconSize || 90, ic = c.icon ? svgc(IC[ICN[c.icon]] || IC.info, s, c.iconColor) : '';
    if (c.img) ic = '<img src="' + SRV + '/tv/img/' + c.img + '.png" style="width:' + s + 'px;height:' + s + 'px">';
    if (c.iconCircle) ic = '<div class="tcirc" style="background:' + c.iconCircle + ';width:' + Math.round(s * 1.75) + 'px;height:' + Math.round(s * 1.75) + 'px">' + ic + '</div>';
    // nome cabe no cartão: diminui a letra quando o texto é comprido (ex.: CONFIGURAÇÕES num botão estreito)
    var fs = c.labelSize || 40;
    if (c.label) {
      var room = st === 'btn' ? w - (c.icon || c.img ? s + 24 : 0) - (c.bar ? 92 : 80) : (st === 'wide' ? w - s - 140 : w - 30);
      var need = c.label.length * fs * 0.72;
      if (need > room) fs = Math.max(16, Math.floor(room / (c.label.length * 0.72)));
    }
    var lb = c.label ? '<div class="tlb" style="font-size:' + fs + 'px">' + esc(c.label) + '</div>' : '';
    var inner = st === 'btn' ? '<div class="trow">' + ic + lb + '</div>'
      : (st === 'wide' ? '<div class="trow wide">' + lb + ic + '</div>'
      : (st === 'round' ? ic : '<div class="tcol">' + ic + lb + '</div>'));
    if (c.pattern) inner = '<div class="tpat"></div>' + inner;
    if (c.decor) inner += '<img class="tdecor" src="' + SRV + '/tv/img/' + c.decor + '.png" style="left:' + c.dx + 'px;top:' + c.dy + 'px;width:' + c.dw + 'px;height:' + c.dh + 'px">';
    return '<div id="' + id + '" class="tt' + (st === 'round' ? ' round' : '') + (act ? ' f' : '') + '"' + (act ? ' data-a="' + act + '"' : '') + ' style="' + css + '">' + inner + '</div>';
  }
  function themeHome() {
    var id = TH.id, L = [];
    thCss = ''; thN = 0;
    if (id === '1') {
      // OneUI: TV ao vivo grande à esquerda, Filmes e Séries ao lado, botões embaixo
      var pat = thOn('show_pattern');
      L.push(ht(60, 160, 640, 760, 'live', { style: 'grad', c1: thc('live_bottom', '#3C56DE'), c2: thc('live_top', '#36D29B'), pattern: pat, icon: 'tv', iconSize: 170, label: t('live'), labelSize: 58 }));
      L.push(ht(730, 160, 555, 560, 'movies', { style: 'grad', c1: thc('movies_bottom', '#F69D3F'), c2: thc('movies_top', '#DB2E54'), pattern: pat, icon: 'movie', iconSize: 130, label: t('movies'), labelSize: 48 }));
      L.push(ht(1305, 160, 555, 560, 'series', { style: 'grad', c1: thc('series_bottom', '#5396DC'), c2: thc('series_top', '#9C3CB3'), pattern: pat, icon: 'series', iconSize: 130, label: t('series'), labelSize: 48 }));
      var b = thc('buttons', '#4DB6AC'), bx = [730, 1017, 1304, 1591];
      var acts = [['cont', 'play', t('cont_tile')], ['fav', 'star', t('favorites')], ['playlist', 'playlist', t('playlist')], ['settings', 'settings', t('settings')]];
      for (var i = 0; i < 4; i++) L.push(ht(bx[i], 750, 269, 170, acts[i][0], { style: 'card', c1: b, icon: acts[i][1], iconSize: 50, label: acts[i][2], labelSize: 26 }));
    } else if (id === '2') {
      // Glossy: botões redondos à esquerda, menu no meio, TV/Filmes/Séries com borda colorida à direita
      var a1 = thc('accent_start', '#FD4C3A'), a2 = thc('accent_end', '#FF7439'), p1 = thc('panel_top', '#233245'), p2 = thc('panel_bottom', '#141A26');
      var rb = [['settings', 'settings'], ['playlist', 'playlist'], ['refresh', 'refresh'], ['support', 'support']];
      for (var r = 0; r < 4; r++) L.push(ht(60, 170 + r * 160, 120, 120, rb[r][0], { style: 'round', c1: a1, c2: a2, icon: rb[r][1], iconSize: 56, focus: '#fff' }));
      L.push(ht(230, 160, 560, 760, '', { style: 'card', c1: p2, c2: p1 }));
      var mid = [['fav', 'star', t('favorites')], ['search', 'search', t('search')], ['cont', 'play', t('continue')]];
      for (var q = 0; q < 3; q++) L.push(ht(260, 190 + q * 240, 500, 220, mid[q][0], { style: 'btn', c1: mixc(p2, 0.06), icon: mid[q][1], iconSize: 60, label: mid[q][2], labelSize: 32, focus: a2 }));
      var big = [['live', 'tv', t('live')], ['movies', 'movie', t('movies')], ['series', 'series', t('series')]];
      for (var g = 0; g < 3; g++) L.push(ht(830, 160 + g * 265, 1030, 230, big[g][0], { style: 'wide', c1: p2, ring: a1, focus: a2, icon: big[g][1], iconSize: 110, label: big[g][2], labelSize: 52 }));
    } else if (id === '3') {
      // Black Panther: cartões cinza com ícone no círculo e botões com faixa
      var ac = thc('accent', '#E22F4D'), cd = thc('card', '#404040');
      var big3 = [['live', 'tv', t('live')], ['movies', 'movie', t('movies')], ['series', 'series', t('series')]];
      var small = [['cont', 'play', t('cont_tile')], ['fav', 'star', t('favorites')], ['playlist', 'playlist', t('playlist')], ['settings', 'settings', t('settings')]];
      for (var b3 = 0; b3 < 3; b3++) L.push(ht(100 + b3 * 600, 170, 520, 440, big3[b3][0], { style: 'card', c1: cd, ring: 'rgba(255,255,255,.19)', iconCircle: ac, icon: big3[b3][1], iconSize: 90, label: big3[b3][2], labelSize: 44 }));
      for (var s3 = 0; s3 < 4; s3++) L.push(ht(100 + s3 * 440, 680, 400, 130, small[s3][0], { style: 'btn', c1: cd, bar: ac, icon: small[s3][1], iconSize: 52, label: small[s3][2], labelSize: 30 }));
    } else if (id === '4') {
      // MovieUI: pôster em tela cheia e a fila de cartões embaixo
      var tcol = TH.ms && String(TH.ms.tile_auto) === '0' ? thc('tile_color', '#3A0A10') : '#3A0A10';
      var row = [['live', 'tv', t('live')], ['movies', 'movie', t('movies')], ['series', 'series', t('series')], ['cont', 'play', t('cont_tile')], ['fav', 'star', t('favorites')], ['playlist', 'playlist', t('playlist')], ['settings', 'settings', t('settings')]];
      for (var m4 = 0; m4 < 7; m4++) L.push(ht(70 + m4 * 255, 760, 240, 200, row[m4][0], { style: 'card', c1: tcol, alpha: 0.92, icon: row[m4][1], iconSize: 68, label: row[m4][2], labelSize: 25 }));
    } else if (id === '5' || id === '6' || id === '7') {
      // VUI, Natal e Halloween: 3 cartões altos e botões à direita
      var cols = id === '7' ? [thc('live_color', '#FF811D'), thc('movies_color', '#7761D5'), thc('series_color', '#00C853')]
        : [thc('live_color', '#E62E2F'), thc('movies_color', '#00E676'), thc('series_color', '#FF9100')];
      var top = thc('bg_top', thc('bg', '#070923')), cd5 = mixc(top, 0.07), pat5 = thOn('show_pattern'), decor = thOn('show_decor');
      var big5 = [['live', 'tv', t('live')], ['movies', 'movie', t('movies')], ['series', 'series', t('series')]];
      var imgs = ['hw_pumpkin', 'hw_bat', 'hw_cauldron'];
      var xm = [{ decor: 'xmas_santa', dx: -20, dy: 520, dw: 230, dh: 230 }, { decor: 'xmas_balls', dx: 45, dy: -10, dw: 300, dh: 300 }, { decor: 'xmas_snowman', dx: 190, dy: 540, dw: 210, dh: 210 }];
      for (var v = 0; v < 3; v++) {
        var cfg = { style: 'card', c1: cd5, ring: 'rgba(255,255,255,.08)', pattern: pat5, icon: big5[v][1], iconColor: cols[v], iconSize: 170, label: big5[v][2], labelSize: 54, focus: cols[v] };
        if (id === '7' && decor) { cfg.img = imgs[v]; cfg.iconSize = 270; }
        if (id === '6' && decor) { cfg.decor = xm[v].decor; cfg.dx = xm[v].dx; cfg.dy = xm[v].dy; cfg.dw = xm[v].dw; cfg.dh = xm[v].dh; }
        L.push(ht(60 + v * 420, 160, 390, 760, big5[v][0], cfg));
      }
      var side = [['cont', 'play', t('continue')], ['fav', 'star', t('favorites')], ['playlist', 'playlist', t('playlist')], ['settings', 'settings', t('settings')]];
      var hwi = [null, 'hw_ghost', 'hw_candy', 'hw_candycorn'];
      for (var sd = 0; sd < 4; sd++) {
        var c5 = { style: 'btn', c1: cd5, ring: 'rgba(255,255,255,.08)', icon: side[sd][1], iconSize: 56, label: side[sd][2], labelSize: 32 };
        if (id === '7' && decor && hwi[sd]) c5.img = hwi[sd];
        L.push(ht(1320, 160 + sd * 195, 540, 175, side[sd][0], c5));
      }
      if (id === '7' && decor) L.push('<img class="tdeco" src="' + SRV + '/tv/img/hw_web.png" style="left:1560px;top:860px;width:300px;height:300px;opacity:.18"><img class="tdeco" src="' + SRV + '/tv/img/hw_spider.png" style="left:1225px;top:130px;width:110px;height:110px;opacity:.55">');
      if (id === '6' && thOn('show_snow')) {
        var sn = '';
        for (var f = 0; f < 28; f++) {
          var sz = 6 + Math.floor(Math.random() * 10), dur = 9 + Math.floor(Math.random() * 10), dl = -Math.floor(Math.random() * dur);
          sn += '<i style="left:' + Math.floor(Math.random() * 1900) + 'px;width:' + sz + 'px;height:' + sz + 'px;opacity:' + (0.35 + Math.random() * 0.5).toFixed(2) + ';-webkit-animation-duration:' + dur + 's;animation-duration:' + dur + 's;-webkit-animation-delay:' + dl + 's;animation-delay:' + dl + 's"></i>';
        }
        L.push('<div class="snow">' + sn + '</div>');
      }
    } else return '';
    return '<style>' + thCss + '</style><div class="thome">' + L.join('') + '</div>';
  }

  /* ---------------- tela inicial ---------------- */
  // Licença: confere ao voltar para a tela inicial (no máximo 1 vez por minuto) e a cada 10 minutos.
  // Aparelho excluído/resetado no painel perde o acesso na hora, sem precisar fechar o app.
  var lastLicCheck = 0;
  function licenseCheck(force) {
    if (S.screen === 'act' || (!force && Date.now() - lastLicCheck < 60000)) return;
    lastLicCheck = Date.now();
    api('device_status', {}, function (err, arr) {
      if (err) return;
      var o = arr && arr[0] ? arr[0] : {};
      saveStatus(o);
      if (o.status && o.status !== 'active') {
        stopPreview(); var pl = $('player'); if (pl) pl.parentNode.removeChild(pl);
        activation(o.status, '');
        return;
      }
      serverCheck();
    });
  }
  // Servidor da lista: se o painel trocou (migração, queda, "Usar este", endereço do DNS editado),
  // o app entra de novo na lista pelo servidor novo. Na tela inicial troca na hora; assistindo, troca ao voltar.
  function normDns(u) {
    u = String(u || '').toLowerCase().replace(/\/+$/, '');
    if (u && !/^https?:\/\//.test(u)) u = 'http://' + u;
    return u.replace(/^(http:\/\/[^\/]+):80$/, '$1').replace(/^(https:\/\/[^\/]+):443$/, '$1');
  }
  function serverCheck(now) {
    if (!S.pl || !S.login) return;
    api('get_device_user', {}, function (err, arr) {
      if (err || !arr || !S.pl) return;
      var cur = null;
      for (var i = 0; i < arr.length; i++) if (arr[i] && String(arr[i].id) === String(S.pl.id)) cur = arr[i];
      if (!cur) { S.pls = arr; S.pendingPl = 'gone'; }
      else if (normDns(cur.dns_base) !== normDns(S.pl.dns_base) || cur.user_name !== S.pl.user_name || cur.user_password !== S.pl.user_password) S.pendingPl = cur;
      else return;
      if (S.screen === 'home') applyPendingPl();
      else if (now) { // o vídeo não volta: troca de lista na hora, sem esperar o cliente voltar para a tela inicial
        S.failing = false; stopPreview(); var pl = $('player'); if (pl && pl.parentNode) pl.parentNode.removeChild(pl);
        applyPendingPl();
      }
    });
  }
  // Vídeo (canal, filme ou episódio) não volta: pergunta ao painel a cada ~20 s enquanto continuar falhando.
  // Se outra lista do aparelho conecta, o painel entrega essa lista e o app entra nela sozinho.
  var failIv = null, failAt = 0;
  function playbackFailed() {
    S.failing = true;
    if (!failIv) failIv = setInterval(function () {
      if (!S.failing) { clearInterval(failIv); failIv = null; return; }
      playbackFailed();
    }, 25000);
    if (!S.pl || !S.login || Date.now() - failAt < 20000) return;
    failAt = Date.now();
    serverCheck(true);
  }
  function applyPendingPl() {
    var np = S.pendingPl; S.pendingPl = null;
    if (!np) return false;
    if (np === 'gone') { loadPlaylists(false); return true; } // a lista saiu do aparelho: carrega as listas de novo
    L.cats = null; L.chans = []; L.playList = []; L.playIdx = -1; V.movie.cats = null; V.series.cats = null;
    S.pl = null; splash(); login(np);
    return true;
  }
  setInterval(function () { licenseCheck(true); }, 10 * 60000);
  function home() {
    if (S.pendingPl && applyPendingPl()) return; // servidor da lista mudou no painel: entra de novo pelo novo
    stopPreview();
    setTimeout(function () { licenseCheck(false); }, 1500);
    var tiles = [['live', IC.tv, 'live'], ['movies', IC.movie, 'movies'], ['series', IC.series, 'series']];
    var mini = [['cont', IC.play, 'cont_tile'], ['search', IC.search, 'search'], ['fav', IC.star, 'favorites'], ['playlist', IC.list, 'playlist'], ['settings', IC.gear, 'settings']];
    var h = '', m = '';
    for (var i = 0; i < tiles.length; i++) h += '<div class="tile f" data-a="' + tiles[i][0] + '"><div class="ic">' + svg(tiles[i][1]) + '</div><div class="lb">' + esc(t(tiles[i][2])) + '</div></div>';
    for (var j = 0; j < mini.length; j++) m += '<div class="tile mini f" data-a="' + mini[j][0] + '"><div class="ic">' + svg(mini[j][1], 54) + '</div><div class="lb">' + esc(t(mini[j][2])) + '</div></div>';
    var themed = themeHome();
    var body = themed || ('<div class="hero"><img src="' + esc(S.brand.banner || S.brand.logo) + '" onerror="this.style.display=\'none\'"></div><div class="tiles big">' + h + '</div><div class="tiles minis">' + m + '</div>');
    show(header('') + body, {
      focus: '[data-a=' + (LS.get('bp_home', 'live')) + ']',
      back: confirmExit,
      acts: {
        live: function () { LS.set('bp_home', 'live'); live(); },
        movies: function () { LS.set('bp_home', 'movies'); vodScreen('movie'); },
        series: function () { LS.set('bp_home', 'series'); vodScreen('series'); },
        playlist: function () { LS.set('bp_home', 'playlist'); loadPlaylists(true); },
        cont: function () { LS.set('bp_home', 'cont'); continueLast(); },
        search: function () { LS.set('bp_home', 'search'); searchScreen(); },
        fav: function () { LS.set('bp_home', 'fav'); favoritesMenu(); },
        refresh: function () { toast(t('loading')); try { sessionStorage.setItem('bp_fresh', '1'); } catch (e) {} setTimeout(function () { location.reload(); }, 300); },
        support: function () { support(); },
        settings: function () { LS.set('bp_home', 'settings'); settings(); }
      }
    });
    S.screen = 'home';
    clock(); every(clock, 20000);
  }

  /* ---------------- TV ao vivo ---------------- */
  var L = { cats: null, chans: [], catIdx: 0, catName: '', mode: 'cats', playList: [], playIdx: -1, full: false, video: null, err: 0 };
  function live() {
    L.mode = 'cats'; L.full = false; L.err = 0; L.curCat = '';
    show(header(t('live')) + '<div class="left"><div class="scroll" id="lst"></div></div>' +
      '<div class="right"><div class="pvbox" id="pv"><video id="vid" autoplay></video><div class="pvmsg hide" id="pvmsg"></div></div>' +
      '<div class="epg" id="epg"></div><div class="hint">' + esc(t('fav_live_hint')) + '</div></div>', {
      keys: liveKeys, back: liveBack,
      acts: {
        cat: function (el, i) { openCat(+i); },
        favcat: function () { openChans(favList('live'), undefined, '_fav'); },
        srch: function () { stopPreview(); searchScreen(); },
        ch: function (el, i) { i = +i; if (L.chans[i] && L.playIdx >= 0 && L.playList[L.playIdx] && L.playList[L.playIdx].stream_id == L.chans[i].stream_id) setFull(true); else playChan(i); }
      }
    });
    S.screen = 'live'; clock(); every(clock, 20000);
    L.video = $('vid');
    L.video.addEventListener('error', onLiveError);
    L.video.addEventListener('playing', function () { L.err = 0; S.failing = false; var m = $('pvmsg'); if (m) m.classList.add('hide'); });
    if (L.cats) { renderCats(); resumeLast(); return; }
    $('lst').innerHTML = '<div class="empty">' + esc(t('loading')) + '</div>';
    xt('get_live_categories', {}, function (err, j) {
      if (err || !j || !j.length) { if (L.mode !== 'chans') $('lst').innerHTML = '<div class="empty">' + esc(t(err ? 'list_err' : 'empty')) + '</div>'; return; }
      L.cats = j.filter(function (c) { return !ADULT.test(c.category_name || ''); });
      if (L.mode === 'chans') return; // já abriu favoritos/busca enquanto carregava
      renderCats(); resumeLast();
    });
  }
  function renderCats() {
    L.mode = 'cats';
    var h = '';
    h += '<div class="row f" data-a="srch"><span>' + esc(t('search_go')) + '</span></div>';
    if (favList('live').length) h += '<div class="row f" data-a="favcat"><span>★ ' + esc(t('favorites')) + ' (' + favList('live').length + ')</span></div>';
    for (var i = 0; i < L.cats.length; i++) h += '<div class="row f' + (i === L.catIdx && L.curCat !== '_fav' ? ' sel' : '') + '" data-a="cat" data-i="' + i + '"><span>' + esc(L.cats[i].category_name) + '</span></div>';
    $('lst').innerHTML = h || '<div class="empty">' + esc(t('empty')) + '</div>';
    var el = $('lst').querySelector('[data-i="' + L.catIdx + '"]'); focused = null; focus(el || $('lst').querySelector('.f'));
  }
  function resumeLast() {
    if (L.skipResume) { L.skipResume = false; return; }
    if (L.playIdx >= 0) return;
    var last = LS.getJ('bp_last_live', null);
    if (!last) return;
    if (last.cat === '_fav') { openChans(favList('live'), last.sid, '_fav'); return; }
    for (var i = 0; i < L.cats.length; i++) if (String(L.cats[i].category_id) === String(last.cat)) { L.catIdx = i; openCat(i, last.sid); return; }
  }
  function openCat(i, startSid) {
    L.catIdx = i; L.catName = L.cats[i].category_name;
    $('lst').innerHTML = '<div class="empty">' + esc(t('loading')) + '</div>';
    xt('get_live_streams', { category_id: L.cats[i].category_id }, function (err, j) {
      if (S.screen !== 'live') return;
      if (err || !j || !j.length) { toast(t(err ? 'list_err' : 'empty')); renderCats(); return; }
      openChans(j, startSid, L.cats[i].category_id);
    });
  }
  // Mostra uma lista de canais (de uma categoria, dos favoritos ou da busca) e toca o canal pedido
  function openChans(j, startSid, catId) {
    if (!$('lst')) return;
    if (!j || !j.length) { toast(t('fav_empty')); return; }
    L.chans = j; L.mode = 'chans'; L.curCat = catId || '';
    L.skipResume = false;
    renderChans();
    var start = 0;
    if (startSid !== undefined) for (var q = 0; q < j.length; q++) if (String(j[q].stream_id) === String(startSid)) start = q;
    if (L.playIdx < 0 || startSid !== undefined) playChan(start);
    markPlaying();
    var cur = $('lst').querySelector('.sel') || $('lst').querySelector('.f'); focused = null; focus(cur);
    if (L.wantFull) { L.wantFull = false; setFull(true); }
  }
  function renderChans() {
    var j = L.chans, h = '';
    for (var k = 0; k < j.length; k++) h += '<div class="row f" data-a="ch" data-i="' + k + '"><img src="' + esc(j[k].stream_icon || '') + '" onerror="this.style.visibility=\'hidden\'"><span>' + (isFav('live', j[k]) ? '★ ' : '') + (k + 1) + '  ' + esc(j[k].name) + '</span></div>';
    $('lst').innerHTML = h;
  }
  function markPlaying() {
    var rows = $('lst') ? $('lst').querySelectorAll('[data-a=ch]') : [];
    var pid = L.playIdx >= 0 && L.playList[L.playIdx] ? String(L.playList[L.playIdx].stream_id) : '';
    for (var i = 0; i < rows.length; i++) { var c = L.chans[+rows[i].getAttribute('data-i')]; rows[i].classList.toggle('sel', !!c && String(c.stream_id) === pid); }
  }
  // Navegador (https) não toca o vídeo do DNS: troca uma vez para o servidor informado no login e devolve o novo link (ou '')
  function altUrl(url) {
    if (location.protocol !== 'https:' || !S.alt || S.altTried || !S.base || S.alt === S.base || String(url).indexOf(S.base) !== 0) return '';
    S.altTried = true; var nu = S.alt + String(url).slice(S.base.length); S.base = S.alt; return nu;
  }
  function liveUrl(c) { var u = S.login.user_info; return S.base + '/live/' + encodeURIComponent(u.username) + '/' + encodeURIComponent(u.password) + '/' + c.stream_id + '.m3u8'; }
  function playChan(i) {
    L.playList = L.chans.slice(0); L.playIdx = i; L.err = 0;
    startLive();
  }
  function startLive() {
    var c = L.playList[L.playIdx]; if (!c || !L.video) return;
    if (L.curCh && String(L.curCh.stream_id) !== String(c.stream_id)) L.prevCh = L.curCh; // para o botão "Canal anterior"
    L.curCh = c;
    L.video._triedHls = false;
    setSrc(L.video, liveUrl(c), onLiveError);
    if (IS_WIN) {
      var wv = L.video, wm = $('pvmsg');
      if (wv._wd) clearTimeout(wv._wd);
      wv._wd = setTimeout(function () { if (!wv._played && wm && L.video === wv) { wm.textContent = 'Sem imagem após 15 s' + diagText(wv); wm.classList.remove('hide'); } }, 15000);
    }
    var catId = L.curCat || (L.cats && L.cats[L.catIdx] ? L.cats[L.catIdx].category_id : '');
    LS.setJ('bp_last_live', { cat: catId, sid: c.stream_id });
    setLastPlay({ kind: 'live', cat: catId, sid: c.stream_id, name: c.name, icon: c.stream_icon || '', list: catId === '_search' ? L.playList.slice(0, 80) : null });
    markPlaying(); loadEpg(c);
    if (L.full) { zapBanner((L.playIdx + 1) + '  ·  ' + c.name); lbSync(); }
  }
  // Windows: mostra o motivo da falha na tela (ajuda a achar o problema)
  function diagText(v) {
    if (!IS_WIN || !v) return '';
    var host = String(v._url || '').replace(/^https?:\/\//, '').split('/')[0];
    return ' [v33 ' + (NATIVE_HLS ? 'hls-nativo=sim ' : 'hls-nativo=nao ') + 'pronto=' + v.readyState + ' rede=' + v.networkState + ' ' + (v._hls ? 'hls.js ' + (v._dbg || '') : 'nativo ' + (v.error ? 'erro ' + v.error.code + ' ' + (v.error.message || '') : '')) + ' · ' + host + ']';
  }
  function onLiveError() {
    var m = $('pvmsg'); if (!m) return;
    var au = L.video ? altUrl(L.video._url || '') : '';
    if (au) { L.video._triedHls = false; setSrc(L.video, au, onLiveError); return; }
    if (L.video && !L.video._hls && !L.video._triedHls && playerPref() === 'auto' && /\.m3u8(\?|$)/i.test(L.video._url || '')) {
      L.video._triedHls = true; setSrc(L.video, L.video._url, onLiveError, true); return;
    }
    L.err++;
    if (IS_WIN) { try { console.log('[play] ' + diagText(L.video)); } catch (e) {} }
    if (L.err <= 2) { m.textContent = t('reconnect') + diagText(L.video); m.classList.remove('hide'); setTimeout(function () { if (S.screen === 'live') startLive(); }, 2500); }
    else { m.textContent = t('ch_off') + diagText(L.video); m.classList.remove('hide'); playbackFailed(); }
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
    if (on) { buildLbar(); if (L.playList[L.playIdx]) zapBanner((L.playIdx + 1) + '  ·  ' + L.playList[L.playIdx].name); }
    else removeLbar();
  }
  /* barra de controles da TV ao vivo em tela cheia: lista, favoritar, programação, canal anterior, zoom, recarregar */
  var LB = ['list', 'fav', 'epg', 'prev', 'zoom', 'reload'], lsel = 0, lshow = false, lbT = null, LASP = ['contain', 'cover', 'fill'], lAsp = Math.max(0, LASP.indexOf(LS.get('bp_laspect', 'contain')));
  function lAspTxt() { return lAsp === 0 ? '100%' : t('p_asp_' + LASP[lAsp]); }
  function lbtn(b, ic, lb, sub) { return '<div class="pbt" data-lb="' + b + '"><div class="pbc">' + svg(ic, 44) + '</div><span>' + esc(lb) + '</span><small>' + (sub ? esc(sub) : '&nbsp;') + '</small></div>'; }
  function lbarBtns() {
    var c = L.playList[L.playIdx], fav = !!c && isFav('live', c);
    return lbtn('list', IC.list, t('l_list')) + lbtn('fav', IC.star, fav ? t('l_favd') : t('l_fav')) + lbtn('epg', IC.cal, t('l_epg')) +
      lbtn('prev', IC.undo, t('l_prev'), L.prevCh ? L.prevCh.name : '') + lbtn('zoom', IC.aspect, t('l_zoom'), lAspTxt()) + lbtn('reload', IC.sync, t('l_reload'));
  }
  function lbPaint() {
    var els = document.querySelectorAll('#lbar .pbt');
    for (var i = 0; i < els.length; i++) { if (i === lsel) els[i].classList.add('on'); else els[i].classList.remove('on'); }
  }
  function lbSync() { var b = $('lbar'); if (!b) return; var c = b.querySelector('.pctl'); if (c) c.innerHTML = lbarBtns(); lbPaint(); }
  function buildLbar() {
    removeLbar();
    var pv = $('pv'); if (!pv) return;
    var b = document.createElement('div'); b.id = 'lbar'; b.className = 'lbar hide';
    b.innerHTML = '<div class="pctl">' + lbarBtns() + '</div><div class="phint"><b class="lv">&#9679; ' + esc(t('l_live')) + '</b> <kbd>&#9664;</kbd><kbd>&#9654;</kbd> ' + esc(t('p_h_pick')) + ' &middot; <kbd>OK</kbd> ' + esc(t('l_h_open')) +
      ' &middot; <kbd>&#9650;</kbd><kbd>&#9660;</kbd> ' + esc(t('l_h_zap')) + ' &middot; <kbd>' + esc(t('p_back')) + '</kbd> ' + esc(t('l_h_close')) + '</div>';
    pv.appendChild(b);
    lsel = 0; lshow = false;
    if (L.video) L.video.style.objectFit = LASP[lAsp];
    b.addEventListener('click', function (e) {
      var el = e.target; while (el && el !== b && !(el.getAttribute && el.getAttribute('data-lb'))) el = el.parentNode;
      e.stopPropagation();
      if (el && el !== b) { lsel = LB.indexOf(el.getAttribute('data-lb')); lbRun(LB[lsel]); } else showLbar();
    }, true);
    pv.addEventListener('mousemove', function () { if (L.full) showLbar(); });
  }
  function removeLbar() { clearTimeout(lbT); lshow = false; var b = $('lbar'); if (b && b.parentNode) b.parentNode.removeChild(b); if (L.video) L.video.style.objectFit = ''; }
  function showLbar() { var b = $('lbar'); if (!b) return; b.classList.remove('hide'); lshow = true; lbPaint(); clearTimeout(lbT); lbT = setTimeout(hideLbar, 6000); }
  function hideLbar() { var b = $('lbar'); if (b) b.classList.add('hide'); lshow = false; }
  function epgModal() {
    var c = L.playList[L.playIdx]; if (!c) return;
    modal('<h2>' + esc(c.name) + '</h2><div class="diag"><div><span>' + esc(t('loading')) + '</span></div></div><div class="btn f" data-a="_close">' + esc(t('close')) + '</div>');
    xt('get_short_epg', { stream_id: c.stream_id, limit: 8 }, function (err, j) {
      var box = document.querySelector('.modal .diag'); if (!box) return;
      var l = j && j.epg_listings ? j.epg_listings : [];
      if (!l.length) { box.innerHTML = '<div><span>' + esc(t('no_epg')) + '</span></div>'; return; }
      var h = '';
      for (var i = 0; i < l.length; i++) h += '<div><span>' + hhmm(+l[i].start_timestamp) + ' &ndash; ' + hhmm(+l[i].stop_timestamp) + '</span><b>' + esc(unb64(l[i].title)) + '</b></div>';
      box.innerHTML = h;
    });
  }
  function lbRun(b) {
    var c = L.playList[L.playIdx];
    if (b === 'list') { setFull(false); return; }
    if (b === 'fav') { if (c) favToggle('live', c); lbSync(); showLbar(); return; }
    if (b === 'epg') { hideLbar(); epgModal(); return; }
    if (b === 'prev') {
      if (!L.prevCh) { toast(t('l_none')); return; }
      for (var i = 0; i < L.playList.length; i++) if (String(L.playList[i].stream_id) === String(L.prevCh.stream_id)) { L.playIdx = i; L.err = 0; startLive(); showLbar(); return; }
      toast(t('l_none')); return;
    }
    if (b === 'zoom') { lAsp = (lAsp + 1) % LASP.length; LS.set('bp_laspect', LASP[lAsp]); if (L.video) L.video.style.objectFit = LASP[lAsp]; lbSync(); showLbar(); return; }
    if (b === 'reload') { L.err = 0; startLive(); showLbar(); return; }
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
    if (k === 'fav') {
      // favoritar: o canal com foco na lista ou o que está tocando
      var c = null;
      if (!L.full && focused && focused.getAttribute('data-a') === 'ch') c = L.chans[+focused.getAttribute('data-i')];
      if (!c) c = L.playList[L.playIdx];
      if (c) { favToggle('live', c); if (L.mode === 'chans') { var fi = focused ? focused.getAttribute('data-i') : null; renderChans(); markPlaying(); var el = fi !== null ? $('lst').querySelector('[data-i="' + fi + '"]') : null; if (el) { focused = null; focus(el); } } }
      return true;
    }
    if (L.full) {
      if (k === 'up') { zap(-1); return true; }
      if (k === 'down') { zap(1); return true; }
      if (!lshow) {
        // barra escondida: OK ou ◀ ▶ abrem a barra; Voltar sai da tela cheia
        if (k === 'ok' || k === 'left' || k === 'right') { showLbar(); return true; }
        if (k === 'back') { setFull(false); return true; }
        return true;
      }
      if (k === 'left') { lsel = Math.max(0, lsel - 1); showLbar(); return true; }
      if (k === 'right') { lsel = Math.min(LB.length - 1, lsel + 1); showLbar(); return true; }
      if (k === 'ok') { lbRun(LB[lsel]); return true; }
      if (k === 'back') { hideLbar(); return true; }
      return true;
    }
    return false;
  }
  function liveBack() { if (L.mode === 'chans') { L.curCat = ''; L.mode = 'cats'; if (L.cats) renderCats(); else { stopPreview(); home(); } } else { stopPreview(); home(); } }
  function stopPreview() { if (L.video) { unsetSrc(L.video); try { L.video.pause(); L.video.removeAttribute('src'); L.video.load(); } catch (e) {} L.video = null; } L.playIdx = -1; L.full = false; removeLbar(); }

  /* ---------------- favoritos e continuar assistindo ---------------- */
  // Favoritos ficam no aparelho, por lista (trocar de lista apaga). kind: live | movie | series
  function favKey(kind) { return 'bp_fav_' + kind; }
  function favList(kind) { return LS.getJ(favKey(kind), []); }
  function favId(kind, it) { return String(kind === 'series' ? (it.series_id || it.id) : (it.stream_id || it.id)); }
  function isFav(kind, it) { var id = favId(kind, it), l = favList(kind); for (var i = 0; i < l.length; i++) if (favId(kind, l[i]) === id) return true; return false; }
  function favToggle(kind, it) {
    var id = favId(kind, it), l = favList(kind), out = [], had = false;
    for (var i = 0; i < l.length; i++) { if (favId(kind, l[i]) === id) had = true; else out.push(l[i]); }
    if (!had) {
      var o = kind === 'series' ? { series_id: id, name: it.name, cover: it.cover || it.img || '' }
        : (kind === 'movie' ? { stream_id: id, name: it.name, stream_icon: it.stream_icon || it.img || '', container_extension: it.container_extension || it.ext || 'mp4' }
        : { stream_id: id, name: it.name, stream_icon: it.stream_icon || '' });
      out.unshift(o);
    }
    LS.setJ(favKey(kind), out.slice(0, 300));
    toast(t(had ? 'fav_removed' : 'fav_added'));
    return !had;
  }
  // Último que o cliente assistiu (canal, filme ou episódio): botão "Continuar" da tela inicial abre direto nele
  function setLastPlay(o) { LS.setJ('bp_last_play', o); }
  function continueLast() {
    var o = LS.getJ('bp_last_play', null);
    if (!o) { toast(t('cw_empty'), 5000); return; }
    if (o.kind === 'live') {
      LS.setJ('bp_last_live', { cat: o.cat, sid: o.sid });
      L.wantFull = true; L.playIdx = -1;
      if (o.cat === '_fav' || o.cat === '_search') { L.skipResume = true; live(); openChans(o.cat === '_fav' ? favList('live') : (o.list || [{ stream_id: o.sid, name: o.name, stream_icon: o.icon || '' }]), o.sid, o.cat); return; }
      live();
      return;
    }
    if (o.kind === 'movie') { playVod('movie', o.id, o.name, o.ext || 'mp4', +LS.get('bp_pos_m_' + o.id, '0'), { name: o.name, stream_icon: o.icon || '' }); return; }
    if (o.kind === 'series') { playVod('series', o.id, o.title || o.name, o.ext || 'mp4', +LS.get('bp_pos_e_' + o.id, '0'), null, o.series); return; }
  }

  /* ---------------- busca ---------------- */
  function searchScreen(prev) {
    var last = LS.get('bp_search', '');
    show(header(t('search')) + '<div class="sbox"><input id="sq" class="code f" maxlength="60" autocomplete="off" spellcheck="false" placeholder="' + esc(t('search_hint')) + '" value="' + esc(last) + '">' +
      '<div class="btn gold f" data-a="go">' + esc(t('search_go')) + '</div></div><div class="sres scroll" id="sres"></div>', {
      focus: '#sq', back: home,
      onInputDone: function () { runSearch(); },
      acts: {
        go: function () { runSearch(); },
        r: function (el, ref) {
          var pr = ref.split(':'), it = SR[pr[0]][+pr[1]];
          if (pr[0] === 'live') { L.skipResume = true; L.wantFull = true; live(); openChans(SR.live, it.stream_id, '_search'); }
          else if (pr[0] === 'movie') movieDetails(it, function () { searchScreen(); });
          else seriesDetails(it, function () { searchScreen(); });
        }
      }
    });
    S.screen = 'search'; clock();
    if (prev !== false && last && SR.text === last) renderSearch();
  }
  var SR = { text: '', live: [], movie: [], series: [] };
  function runSearch() {
    var q = ($('sq') ? $('sq').value : '').replace(/^\s+|\s+$/g, '');
    if (q.length < 2) { toast(t('search_hint')); return; }
    LS.set('bp_search', q);
    SR = { text: q, live: [], movie: [], series: [] };
    $('sres').innerHTML = '<div class="empty">' + esc(t('searching')) + '</div>';
    var left = 3;
    ['live', 'movie', 'series'].forEach(function (type) {
      xt('search', { type: type, text: q }, function (err, j) {
        if (SR.text !== q) return;
        SR[type] = j && j.length ? j : [];
        if (--left === 0 && S.screen === 'search') renderSearch();
      });
    });
  }
  function renderSearch() {
    var h = '', groups = [['live', 'ch_title'], ['movie', 'mv_title'], ['series', 'sr_title']];
    for (var g = 0; g < groups.length; g++) {
      var l = SR[groups[g][0]]; if (!l.length) continue;
      h += '<div class="shead">' + esc(t(groups[g][1])) + ' (' + l.length + ')</div>';
      for (var i = 0; i < l.length; i++) {
        var it = l[i], img = it.stream_icon || it.cover || '';
        h += '<div class="row f" data-a="r" data-i="' + groups[g][0] + ':' + i + '"><img src="' + esc(img) + '" onerror="this.style.visibility=\'hidden\'"><span>' + esc(it.name) + '</span></div>';
      }
    }
    var box = $('sres'); if (!box) return;
    box.innerHTML = h || '<div class="empty">' + esc(t('search_none')) + '</div>';
    box.scrollTop = 0;
  }
  function favoritesMenu() {
    modal('<h2>' + esc(t('fav_which')) + '</h2>' +
      '<div class="btn f" data-a="fl">' + esc(t('live')) + ' (' + favList('live').length + ')</div>' +
      '<div class="btn f" data-a="fm">' + esc(t('movies')) + ' (' + favList('movie').length + ')</div>' +
      '<div class="btn f" data-a="fs">' + esc(t('series')) + ' (' + favList('series').length + ')</div>' +
      '<div class="btn f" data-a="_close">' + esc(t('close')) + '</div>', {
      fl: function () { closeModal(true); if (!favList('live').length) return toast(t('fav_empty')); L.skipResume = true; live(); openChans(favList('live'), undefined, '_fav'); },
      fm: function () { closeModal(true); V.movie.want = '_fav'; vodScreen('movie'); },
      fs: function () { closeModal(true); V.series.want = '_fav'; vodScreen('series'); }
    });
  }

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
        cat: function (el, i) { if (st.cats[+i] && st.cats[+i].category_id === '_search') return searchScreen(); loadVodCat(type, +i); },
        item: function (el, i) { var it = st.items[+i]; if (type === 'movie') movieDetails(it); else seriesDetails(it); }
      }
    });
    S.screen = type; clock(); every(clock, 20000);
    var act = type === 'movie' ? 'get_vod_categories' : 'get_series_categories';
    if (st.cats) { if (st.want === '_fav') { st.want = ''; st.catIdx = 2; } return renderVodCats(type); }
    $('lst').innerHTML = '<div class="empty">' + esc(t('loading')) + '</div>';
    xt(act, {}, function (err, j) {
      if (err || !j) { $('lst').innerHTML = '<div class="empty">' + esc(t('list_err')) + '</div>'; return; }
      st.cats = [{ category_id: '_search', category_name: t('search_go') }, { category_id: '_cw', category_name: t('continue') }, { category_id: '_fav', category_name: '★ ' + t('favorites') }].concat(j.filter(function (c) { return !ADULT.test(c.category_name || ''); }));
      st.catIdx = cwList(type).length ? 1 : (st.cats.length > 3 ? 3 : 1);
      if (st.want === '_fav') { st.want = ''; st.catIdx = 2; }
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
    if (c.category_id === '_search') { st.items = []; $('gridin').innerHTML = '<div class="empty">' + esc(t('search_hint')) + '</div>'; return; }
    if (c.category_id === '_cw') { st.items = cwList(type); return renderGrid(type); }
    if (c.category_id === '_fav') { st.items = favList(type); return renderGrid(type); }
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
  function movieDetails(it, backFn) {
    var id = it.stream_id || it.id;
    show(header(t('movies')) + '<div class="det"><img class="cover" src="' + esc(it.stream_icon || it.img || '') + '" onerror="this.style.visibility=\'hidden\'">' +
      '<div class="info"><h1>' + esc(it.name) + '</h1><div class="meta" id="meta"></div><div class="plot" id="plot">' + esc(t('loading')) + '</div><div class="acts" id="acts"></div></div></div>',
      { back: backFn || function () { vodScreen('movie'); }, acts: {
        fav: function (el) { var on = favToggle('movie', it); el.textContent = t(on ? 'fav_del' : 'fav_add'); },
        play: function () { playVod('movie', id, it.name, it.container_extension || (it.ext) || 'mp4', 0, it); },
        resume: function () { playVod('movie', id, it.name, it.container_extension || it.ext || 'mp4', +LS.get('bp_pos_m_' + id, '0'), it); }
      } });
    S.screen = 'mdet';
    var pos = +LS.get('bp_pos_m_' + id, '0');
    var acts = (pos > 30 ? '<div class="btn gold f" data-a="resume">' + esc(t('resume')) + ' ' + fmtTime(pos) + '</div><div class="btn f" data-a="play">' + esc(t('from_start')) + '</div>' : '<div class="btn gold f" data-a="play">' + esc(t('play')) + '</div>') +
      '<div class="btn f" data-a="fav">' + esc(t(isFav('movie', it) ? 'fav_del' : 'fav_add')) + '</div>';
    $('acts').innerHTML = acts; focused = null; focusFirst();
    xt('get_vod_info', { vod_id: id }, function (err, j) {
      if (S.screen !== 'mdet') return;
      var inf = j && j.info ? j.info : {}, md = j && j.movie_data ? j.movie_data : {};
      if (md.container_extension) it.container_extension = md.container_extension;
      $('plot').textContent = inf.plot || inf.description || '';
      $('meta').textContent = [inf.releasedate || inf.release_date || '', inf.genre || '', inf.duration || ''].filter(function (x) { return x; }).join('  ·  ');
    });
  }
  function seriesDetails(it, backFn) {
    var sid = it.series_id || it.id;
    show(header(t('series')) + '<div class="det"><img class="cover" src="' + esc(it.cover || it.img || '') + '" onerror="this.style.visibility=\'hidden\'">' +
      '<div class="info"><h1>' + esc(it.name) + '</h1><div class="meta" id="meta"></div><div class="plot" id="plot" style="max-height:140px">' + esc(t('loading')) + '</div>' +
      '<div class="seasons" id="seas"></div><div class="eps scroll" id="eps"></div></div></div>',
      { back: backFn || function () { vodScreen('series'); }, acts: {
        sfav: function (el) { var on = favToggle('series', it); el.textContent = t(on ? 'fav_del' : 'fav_add'); }
      } });
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
      sh += '<div class="btn f" data-a="sfav">' + esc(t(isFav('series', it) ? 'fav_del' : 'fav_add')) + '</div>';
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
        playVod('series', ep.id, it.name + ' – ' + (ep.title || ''), ep.container_extension || 'mp4', p > 30 ? p : 0, null, { series_id: sid, name: it.name, cover: it.cover || it.img || '' });
      };
      if (seasons.length) { var first = $('seas').querySelector('.btn'); S.acts.season(first, seasons[0]); focused = null; focus(first); }
    });
  }

  /* ---------------- player de filme / episódio ---------------- */
  var PL = { video: null, type: '', id: '', saveT: null };
  function playVod(type, id, title, ext, startAt, item, series) {
    var u = S.login.user_info;
    var url = S.base + '/' + type + '/' + encodeURIComponent(u.username) + '/' + encodeURIComponent(u.password) + '/' + id + '.' + (ext || 'mp4');
    if (type === 'movie' && item) cwAdd('movie', { id: id, stream_id: id, name: item.name, stream_icon: item.stream_icon || item.img || '', container_extension: ext });
    if (type === 'movie') setLastPlay({ kind: 'movie', id: id, name: item ? item.name : title, ext: ext || 'mp4', icon: item ? (item.stream_icon || item.img || '') : '' });
    else setLastPlay({ kind: 'series', id: id, title: title, ext: ext || 'mp4', series: series || null });
    var back = S.back;
    var p = document.createElement('div'); p.className = 'player'; p.id = 'player';
    // barra de controles: voltar/avançar 10 s, pausar, formato da tela, áudio e legenda, diagnóstico
    var ASP = ['contain', 'cover', 'fill'], aspI = Math.max(0, ASP.indexOf(LS.get('bp_aspect', 'contain')));
    var BTNS = ['rew', 'pp', 'ff', 'asp', 'aud', 'diag'], sel = 1, mode = 'btn';
    function aspLabel() { return t('p_aspect') + ': ' + t('p_asp_' + ASP[aspI]); }
    function pbt(b, ic, lb) { return '<div class="pbt" data-b="' + b + '"><div class="pbc">' + svg(ic, 44) + '</div><span>' + esc(lb) + '</span></div>'; }
    p.innerHTML = '<video id="pvid" autoplay playsinline></video><div class="msg hide" id="pmsg"></div>' +
      '<div class="pbar" id="pbar"><div class="ptop"><div class="pbk" id="pbk">' + svg(IC.pbk, 44) + '</div><div><div class="pkind">' + esc(type === 'movie' ? t('p_movie') : t('p_series')) + '</div><div class="ttl">' + esc(title) + '</div></div></div>' +
      '<div class="pbot"><div class="track" id="ptk"><i id="ptr"></i><b id="pth"></b></div><div class="tms"><span id="ptc">0:00</span><span id="ptd">--:--</span></div>' +
      '<div class="pctl">' + pbt('rew', IC.rew, '10 s') + pbt('pp', IC.pause, t('p_pause')) + pbt('ff', IC.ff, '10 s') + pbt('asp', IC.aspect, aspLabel()) + pbt('aud', IC.cc, t('p_audio')) + pbt('diag', IC.info, t('p_diag')) + '</div>' +
      '<div class="phint"><kbd>&#9650;</kbd> ' + esc(t('p_h_bar')) + ' &middot; <kbd>&#9664;</kbd><kbd>&#9654;</kbd> ' + esc(t('p_h_pick')) + ' &middot; <kbd>OK</kbd> ' + esc(t('p_h_ok')) + ' &middot; <kbd>' + esc(t('p_back')) + '</kbd> ' + esc(t('p_h_exit')) + '</div></div></div>';
    stage.appendChild(p);
    PL.video = $('pvid'); PL.type = type; PL.id = id;
    PL.video.style.objectFit = ASP[aspI];
    var key = 'bp_pos_' + (type === 'movie' ? 'm_' : 'e_') + id, seeked = false, errs = 0, barT = null;
    function paintSel() {
      var els = p.querySelectorAll('.pbt');
      for (var i = 0; i < els.length; i++) { if (i === sel && mode === 'btn') els[i].classList.add('on'); else els[i].classList.remove('on'); }
      var tk = $('ptk'); if (tk) { if (mode === 'seek') tk.classList.add('on'); else tk.classList.remove('on'); }
    }
    function paintPlay() {
      var b = p.querySelector('[data-b=pp]'); if (!b || !PL.video) return;
      var paused = PL.video.paused;
      b.querySelector('.pbc').innerHTML = svg(paused ? IC.play : IC.pause, 44);
      b.querySelector('span').textContent = paused ? t('p_play') : t('p_pause');
    }
    function showBar() { var b = $('pbar'); if (!b) return; b.classList.remove('hide'); paintSel(); clearTimeout(barT); barT = setTimeout(function () { var b2 = $('pbar'); if (b2 && !(PL.video && PL.video.paused)) b2.classList.add('hide'); }, 5000); }
    function seekBy(d) { var v = PL.video; if (!v) return; try { v.currentTime = Math.max(0, Math.min(v.duration || 1e9, v.currentTime + d)); } catch (e) {} showBar(); }
    function togglePlay() { var v = PL.video; if (!v) return; if (v.paused) { var pr = v.play(); if (pr && pr.catch) pr.catch(function () {}); } else v.pause(); showBar(); }
    function cycleAspect() {
      aspI = (aspI + 1) % ASP.length; LS.set('bp_aspect', ASP[aspI]);
      if (PL.video) PL.video.style.objectFit = ASP[aspI];
      var l = p.querySelector('[data-b=asp] span'); if (l) l.textContent = aspLabel();
      showBar();
    }
    // áudio e legenda (hls.js, faixas nativas do navegador ou da TV); sem faixas extras, só avisa
    function tracksModal() {
      var v = PL.video, h = v && v._hls, au = [], su = [], i, x;
      if (h && h.audioTracks && h.audioTracks.length > 1) for (i = 0; i < h.audioTracks.length; i++) { x = h.audioTracks[i]; au.push({ l: x.name || x.lang || (t('p_audio_h') + ' ' + (i + 1)), on: h.audioTrack === i, set: (function (n) { return function () { h.audioTrack = n; }; })(i) }); }
      else if (v && v.audioTracks && v.audioTracks.length > 1) for (i = 0; i < v.audioTracks.length; i++) { x = v.audioTracks[i]; au.push({ l: x.label || x.language || (t('p_audio_h') + ' ' + (i + 1)), on: !!x.enabled, set: (function (n) { return function () { for (var j = 0; j < v.audioTracks.length; j++) v.audioTracks[j].enabled = (j === n); }; })(i) }); }
      if (h && h.subtitleTracks && h.subtitleTracks.length) { su.push({ l: t('p_sub_off'), on: h.subtitleTrack < 0, set: function () { h.subtitleTrack = -1; } }); for (i = 0; i < h.subtitleTracks.length; i++) { x = h.subtitleTracks[i]; su.push({ l: x.name || x.lang || (t('p_sub_h') + ' ' + (i + 1)), on: h.subtitleTrack === i, set: (function (n) { return function () { h.subtitleTrack = n; }; })(i) }); } }
      else if (v && v.textTracks && v.textTracks.length) { var tt = []; for (i = 0; i < v.textTracks.length; i++) if (v.textTracks[i].kind === 'subtitles' || v.textTracks[i].kind === 'captions') tt.push(v.textTracks[i]); if (tt.length) { su.push({ l: t('p_sub_off'), on: tt.every(function (z) { return z.mode !== 'showing'; }), set: function () { tt.forEach(function (z) { z.mode = 'disabled'; }); } }); tt.forEach(function (z, n) { su.push({ l: z.label || z.language || (t('p_sub_h') + ' ' + (n + 1)), on: z.mode === 'showing', set: function () { tt.forEach(function (w) { w.mode = (w === z ? 'showing' : 'disabled'); }); } }); }); } }
      if (!au.length && !su.length) { toast(t('p_no_tracks')); return; }
      var all = au.concat(su), html = '', n = 0;
      if (au.length) { html += '<h2>' + esc(t('p_audio_h')) + '</h2>'; au.forEach(function (o) { html += '<div class="btn f' + (o.on ? ' gold' : '') + '" data-a="T" data-i="' + (n++) + '">' + esc(o.l) + '</div>'; }); }
      if (su.length) { html += '<h2>' + esc(t('p_sub_h')) + '</h2>'; su.forEach(function (o) { html += '<div class="btn f' + (o.on ? ' gold' : '') + '" data-a="T" data-i="' + (n++) + '">' + esc(o.l) + '</div>'; }); }
      modal(html, { T: function (el, k) { var o = all[+k]; if (o) o.set(); closeModal(); } });
    }
    function diagModal() {
      var v = PL.video; if (!v) return;
      var buf = 0; try { for (var i = 0; i < v.buffered.length; i++) if (v.buffered.start(i) <= v.currentTime && v.buffered.end(i) >= v.currentTime) buf = v.buffered.end(i) - v.currentTime; } catch (e) {}
      var q = null; try { q = v.getVideoPlaybackQuality ? v.getVideoPlaybackQuality() : null; } catch (e) {}
      var host = String(url).replace(/^[a-z]+:\/\//i, '').split('/')[0];
      var rows = [[t('d_res'), (v.videoWidth || 0) + ' x ' + (v.videoHeight || 0)], [t('d_time'), fmtTime(v.currentTime) + ' / ' + (v.duration ? fmtTime(v.duration) : '--:--')],
        [t('d_buf'), Math.round(buf) + ' s'], [t('d_state'), 'ready ' + v.readyState + ' / net ' + v.networkState], [t('d_drop'), q ? q.droppedVideoFrames + ' / ' + q.totalVideoFrames : '-'],
        [t('d_player'), v._hls ? 'hls.js' : 'native'], [t('d_host'), host + ' (' + (ext || 'mp4') + ')'], [t('d_dev'), PLATFORM_NAME + ' v' + VER]];
      if (v.error) rows.push([t('d_err'), v.error.code + (v.error.message ? ' ' + v.error.message : '')]);
      var h = '<h2>' + esc(t('p_diag_h')) + '</h2><div class="diag">';
      for (var r = 0; r < rows.length; r++) h += '<div><span>' + esc(rows[r][0]) + '</span><b>' + esc(rows[r][1]) + '</b></div>';
      modal(h + '</div><div class="btn f" data-a="_close">' + esc(t('close')) + '</div>');
    }
    function runBtn(b) {
      if (b === 'rew') seekBy(-10); else if (b === 'ff') seekBy(10); else if (b === 'pp') togglePlay();
      else if (b === 'asp') cycleAspect(); else if (b === 'aud') tracksModal(); else if (b === 'diag') diagModal();
    }
    // mouse / toque nos botões, na barra de progresso e no botão voltar
    p.addEventListener('click', function (e) {
      var el = e.target, b = null; while (el && el !== p) { if (el.getAttribute && el.getAttribute('data-b')) { b = el.getAttribute('data-b'); break; } if (el.id === 'pbk' || el.id === 'ptk') break; el = el.parentNode; }
      e.stopPropagation();
      if (b) { sel = BTNS.indexOf(b); mode = 'btn'; runBtn(b); return; }
      if (el && el.id === 'pbk') { if (S.back) S.back(); return; }
      if (el && el.id === 'ptk') { var v = PL.video, r = el.getBoundingClientRect(); if (v && v.duration && r.width) { try { v.currentTime = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)) * v.duration; } catch (er) {} } showBar(); return; }
      showBar();
    }, true);
    p.addEventListener('mousemove', function () { showBar(); });
    function save() { var v = PL.video; if (!v || !v.duration) return; if (v.currentTime > v.duration - 15) LS.del(key); else if (v.currentTime > 5) LS.set(key, String(Math.floor(v.currentTime))); }
    PL.video.addEventListener('loadedmetadata', function () { if (startAt > 0 && !seeked) { seeked = true; try { PL.video.currentTime = startAt; } catch (e) {} } });
    PL.video.addEventListener('timeupdate', function () { var v = PL.video; if (!v || !v.duration || !$('ptr')) return; var pc = (v.currentTime / v.duration * 100) + '%'; $('ptr').style.width = pc; $('pth').style.left = pc; $('ptc').textContent = fmtTime(v.currentTime); $('ptd').textContent = fmtTime(v.duration); });
    PL.video.addEventListener('pause', paintPlay); PL.video.addEventListener('play', paintPlay);
    PL.video.addEventListener('pause', function () { showBar(); });
    PL.video.addEventListener('playing', function () { S.failing = false; $('pmsg').classList.add('hide'); });
    function verr() {
      // página https: o vídeo do filme/episódio termina num endereço http que o navegador bloqueia → o painel entrega o vídeo por https (tv_vod.php)
      if (location.protocol === 'https:' && url.indexOf('/tv_vod.php') < 0 && S.pl) {
        url = SRV + '/tv_vod.php?d=' + encodeURIComponent(DID) + '&a=' + encodeURIComponent(APP_ID) + '&p=' + encodeURIComponent(S.pl.id) + '&t=' + type + '&i=' + encodeURIComponent(id) + '&e=' + encodeURIComponent(ext || 'mp4');
        PL.video._triedHls = false; setSrc(PL.video, url, verr); return;
      }
      var m0 = $('pmsg'); if (!m0) return;
      if (PL.video && !PL.video._hls && !PL.video._triedHls && playerPref() === 'auto' && /\.m3u8(\?|$)/i.test(url)) { PL.video._triedHls = true; setSrc(PL.video, url, verr, true); return; }
      errs++; var m = m0; if (errs <= 1) { m.textContent = t('reconnect'); m.classList.remove('hide'); setTimeout(function () { if (PL.video) setSrc(PL.video, url, verr); }, 2500); } else { m.textContent = t('play_err'); m.classList.remove('hide'); playbackFailed(); } }
    PL.video.addEventListener('error', verr);
    setSrc(PL.video, url, verr);
    showBar();
    PL.saveT = setInterval(save, 60000); // salva onde parou a cada 1 minuto (sobrescreve)
    var prevKeys = S.keys;
    function close() { save(); clearInterval(PL.saveT); unsetSrc(PL.video); try { PL.video.pause(); PL.video.removeAttribute('src'); PL.video.load(); } catch (e) {} var el = $('player'); if (el) el.parentNode.removeChild(el); PL.video = null; S.keys = prevKeys; S.back = back; }
    S.keys = function (k) {
      var v = PL.video; if (!v) return true;
      var bar = $('pbar'), shown = bar && !bar.classList.contains('hide');
      if (k === 'back') { close(); return true; }
      if (k === 'playpause') { togglePlay(); return true; }
      if (!shown) {
        // barra escondida: OK pausa, ◀ ▶ pulam 10 s, o resto só mostra a barra
        if (k === 'ok') { togglePlay(); return true; }
        if (k === 'right') { seekBy(10); return true; }
        if (k === 'left') { seekBy(-10); return true; }
        mode = 'btn'; showBar(); return true;
      }
      if (k === 'up') { mode = 'seek'; showBar(); return true; }
      if (k === 'down') { mode = 'btn'; showBar(); return true; }
      if (mode === 'seek') {
        if (k === 'right') { seekBy(10); return true; }
        if (k === 'left') { seekBy(-10); return true; }
        if (k === 'ok') { mode = 'btn'; showBar(); return true; }
      }
      if (k === 'right') { sel = Math.min(BTNS.length - 1, sel + 1); showBar(); return true; }
      if (k === 'left') { sel = Math.max(0, sel - 1); showBar(); return true; }
      if (k === 'ok') { runBtn(BTNS[sel]); return true; }
      showBar(); return true;
    };
  }

  /* ---------------- configurações ---------------- */
  function settings() {
    var langName = { pt: 'Português', en: 'English', es: 'Español' };
    var items = [['lang', IC.globe, t('language'), langName[LANG]], ['dev', IC.id, t('device'), DID], ['sup', IC.chat, t('support'), ''],
      ['ref', IC.sync, t('refresh'), ''], ['pl', IC.list, t('playlist'), S.pl ? (S.pl.playlist_name || S.pl.user_name) : ''],
      ['buf', IC.gauge, t('buf'), t('buf_' + bufPref())], ['player', IC.play, t('player'), t('pl_' + playerPref())],
      ['about', IC.info, t('about'), 'v' + VER], ['reset', IC.sync, t('lic_reset'), '']];
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
        buf: function () {
          var cur = bufPref();
          modal('<h2>' + esc(t('buf')) + '</h2><p>' + esc(t('buf_msg')) + '</p>' + ['n', 'h', 'm'].map(function (b) { return '<div class="btn f' + (b === cur ? ' gold' : '') + '" data-a="B" data-i="' + b + '">' + esc(t('buf_' + b)) + '</div>'; }).join(''),
            { B: function (el, b) { LS.set('bp_buf', b); closeModal(true); settings(); focus(document.querySelector('[data-a=buf]')); } });
        },
        player: function () {
          var cur = playerPref();
          modal('<h2>' + esc(t('player')) + '</h2><p>' + esc(t('player_msg')) + '</p>' + ['auto', 'native', 'hls'].map(function (p) { return '<div class="btn f' + (p === cur ? ' gold' : '') + '" data-a="P" data-i="' + p + '">' + esc(t('pl_' + p)) + '</div>'; }).join(''),
            { P: function (el, p) { LS.set('bp_player', p); closeModal(true); settings(); focus(document.querySelector('[data-a=player]')); } });
        },
        sup: support,
        ref: function () { L.cats = null; V.movie.cats = null; V.series.cats = null; toast(t('refresh_ok')); },
        pl: function () { loadPlaylists(true); },
        about: function () { modal('<img class="logo" src="' + esc(S.brand.logo) + '"><h1>' + esc(S.brand.name) + '</h1><p>v' + VER + ' · ' + PLATFORM_NAME + '</p><div class="btn f" data-a="_close">' + esc(t('close')) + '</div>'); },
        // Resetar licença: o cliente trocou de revenda e quer digitar um código novo neste aparelho
        reset: function () {
          modal('<h2>' + esc(t('lic_reset')) + '</h2><p>' + esc(t('lic_reset_msg')) + '</p><div class="btn f" data-a="_close">' + esc(t('lic_cancel')) + '</div><div class="btn gold f" data-a="y">' + esc(t('lic_yes')) + '</div>',
            { y: function () {
              closeModal(true); splash();
              api('device_reset', {}, function (err) {
                if (err) { toast(t('no_net')); return settings(); }
                clearLocal(); toast(t('lic_done')); activation('unlinked', '');
              });
            } });
        }
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
  try { if (window.tizen && tizen.tvinputdevice) { ['MediaPlayPause', 'MediaPlay', 'MediaPause', 'ChannelUp', 'ChannelDown', 'ColorF0Red', 'ColorF1Green', 'ColorF2Yellow', 'ColorF3Blue', 'Info', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'].forEach(function (k) { try { tizen.tvinputdevice.registerKey(k); } catch (e) {} }); } } catch (e) {}
  boot();
})();
