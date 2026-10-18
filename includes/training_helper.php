<?php
/**
 * Treinamento em vídeo do portal da revenda (aparece no portal da revenda e no painel admin).
 * Os vídeos ficam em assets/training/. Para adicionar um vídeo novo, coloque o arquivo lá e inclua na lista abaixo.
 */

// $set: 'reseller' (portal da revenda e admin) ou 'customer' (área do cliente)
function training_videos($set = 'reseller'){
    if ($set == 'customer') return array(
        array('c01-ativando-o-aparelho.mp4', 'Ativando o seu aparelho', 'Do cadastro no site até o aparelho ativo com o código.'),
        array('c02-adicionando-a-lista.mp4', 'Adicionando a sua lista', 'Como colocar a sua lista no aparelho pela área do cliente.'),
    );
    return array(
        array('01-conhecendo-o-portal.mp4', 'Conhecendo o portal', 'Onde fica cada coisa no seu painel.'),
        array('02-comprando-creditos.mp4', 'Comprando créditos', 'Como comprar créditos e pagar o pedido.'),
        array('03-cadastrando-dns.mp4', 'Cadastrando DNS', 'Como cadastrar os servidores das suas listas.'),
        array('04-minha-marca.mp4', 'Minha Marca: logo, fundo e cores', 'Como deixar o app com a sua marca.'),
        array('05-posteres.mp4', 'Pôsteres', 'Como trocar os pôsteres do tema MovieUI.'),
        array('06-ativando-aparelho.mp4', 'Ativando o aparelho do cliente', 'Do código no app até o aparelho ativo.'),
        array('07-publicando-lista.mp4', 'Adicionando e publicando a lista', 'Como mandar a lista para o aparelho do cliente.'),
        array('08-suporte.mp4', 'Suporte e Telegram', 'Como falar com a gente e acompanhar as novidades.'),
    );
}

// Idiomas com vídeo: assets/training/<idioma>/<arquivo>
function training_langs(){
    $out = array();
    foreach (array('pt' => 'Português', 'en' => 'English', 'es' => 'Español') as $k => $n) {
        if (is_dir(__DIR__.'/../assets/training/'.$k)) $out[$k] = $n;
    }
    return $out;
}

// Idioma dos vídeos: o escolhido na página (?vlang=), senão o do portal; sem vídeos nesse idioma, inglês e depois português
function training_lang(){
    $langs = training_langs();
    $want = isset($_GET['vlang']) ? (string)$_GET['vlang'] : (function_exists('i18n_lang') ? i18n_lang() : 'pt');
    if (isset($langs[$want])) return $want;
    if (isset($langs['en']) && $want != 'pt') return 'en';
    return isset($langs['pt']) ? 'pt' : (string)key($langs);
}

// Só os vídeos que já existem no servidor
function training_available($lang, $set = 'reseller'){
    $out = array();
    foreach (training_videos($set) as $v) {
        if (is_file(__DIR__.'/../assets/training/'.$lang.'/'.$v[0])) $out[] = $v;
    }
    return $out;
}

function training_render($prefix = '', $set = 'reseller'){
    $lang = training_lang();
    $list = training_available($lang, $set);
    if (!$list) { echo '<div class="alert alert-info">Os vídeos de treinamento ainda estão sendo preparados.</div>'; return; }
    $base = $prefix.'assets/training/'.$lang.'/';
    $langs = training_langs();
    if (count($langs) > 1) { ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3" translate="no">
            <?php foreach ($langs as $k => $n) { ?>
                <a href="?vlang=<?php echo $k; ?>" class="btn btn-sm <?php echo $k == $lang ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $n; ?></a>
            <?php } ?>
        </div>
    <?php } ?>
    <style>
        .tr-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; align-items: start; }
        .tr-player { background: #050814; border-radius: 14px; overflow: hidden; box-shadow: 0 6px 20px rgba(15,23,42,.12); }
        .tr-player video { width: 100%; display: block; aspect-ratio: 16 / 9; background: #050814; }
        .tr-now { padding: 14px 18px; background: #fff; }
        .tr-now b { display: block; font-size: 17px; }
        .tr-now span { color: #6b7a99; font-size: 14px; }
        .tr-list { display: grid; gap: 8px; }
        .tr-item { display: flex; gap: 12px; align-items: center; padding: 12px 14px; border-radius: 12px; background: #fff; border: 1px solid #e8ecf4; cursor: pointer; text-align: left; width: 100%; transition: .15s; }
        .tr-item:hover { border-color: #3b6cf6; }
        .tr-item.on { border-color: #3b6cf6; background: #eef3ff; }
        .tr-n { flex: none; width: 34px; height: 34px; border-radius: 50%; background: #f1f4fa; color: #3b6cf6; display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .tr-item.on .tr-n { background: #3b6cf6; color: #fff; }
        .tr-item.done .tr-n { background: #16a34a; color: #fff; }
        .tr-item b { display: block; font-size: 14.5px; color: #1f2a44; }
        .tr-item small { color: #6b7a99; }
        @media (max-width: 991.98px) { .tr-wrap { grid-template-columns: 1fr; } }
    </style>
    <div class="tr-wrap">
        <div class="tr-player">
            <video id="trVideo" controls playsinline preload="metadata" src="<?php echo htmlspecialchars($base.$list[0][0]); ?>"></video>
            <div class="tr-now"><b id="trTitle"><?php echo htmlspecialchars('1. '.$list[0][1]); ?></b><span id="trDesc"><?php echo htmlspecialchars($list[0][2]); ?></span></div>
        </div>
        <div class="tr-list">
            <?php foreach ($list as $i => $v) { ?>
                <button type="button" class="tr-item<?php echo $i == 0 ? ' on' : ''; ?>" data-src="<?php echo htmlspecialchars($base.$v[0]); ?>" data-t="<?php echo htmlspecialchars(($i + 1).'. '.$v[1]); ?>" data-d="<?php echo htmlspecialchars($v[2]); ?>" data-k="<?php echo htmlspecialchars($v[0]); ?>">
                    <span class="tr-n"><?php echo $i + 1; ?></span>
                    <span><b><?php echo htmlspecialchars($v[1]); ?></b><small><?php echo htmlspecialchars($v[2]); ?></small></span>
                </button>
            <?php } ?>
        </div>
    </div>
    <script>
    (function () {
        var v = document.getElementById('trVideo'), items = document.querySelectorAll('.tr-item');
        var seen = {}; try { seen = JSON.parse(localStorage.getItem('bp_training_seen') || '{}'); } catch (e) {}
        function mark() { items.forEach(function (b) { b.classList.toggle('done', !!seen[b.getAttribute('data-k')] && !b.classList.contains('on')); }); }
        function pick(b, play) {
            items.forEach(function (x) { x.classList.remove('on'); }); b.classList.add('on');
            v.src = b.getAttribute('data-src');
            document.getElementById('trTitle').textContent = b.getAttribute('data-t');
            document.getElementById('trDesc').textContent = b.getAttribute('data-d');
            mark(); if (play) { var p = v.play(); if (p && p.catch) p.catch(function () {}); }
        }
        items.forEach(function (b) { b.addEventListener('click', function () { pick(b, true); window.scrollTo({ top: 0, behavior: 'smooth' }); }); });
        // terminou um vídeo: marca como assistido e passa para o próximo
        v.addEventListener('ended', function () {
            var cur = document.querySelector('.tr-item.on'); if (!cur) return;
            seen[cur.getAttribute('data-k')] = 1; try { localStorage.setItem('bp_training_seen', JSON.stringify(seen)); } catch (e) {}
            var next = cur.nextElementSibling; if (next) pick(next, true); else mark();
        });
        mark();
    })();
    </script>
    <?php
}
