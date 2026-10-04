<?php
/**
 * Formulário de playlist (abas URL / Account) + lista de playlists do dispositivo.
 * Usado em device_playlist.php (admin) e revenda/playlist.php (revendedor).
 */

function playlist_form_styles(){ ?>
<style>
    .pl-box { background: #0d1520; color: #e5e7eb; border: 1px solid rgba(255,255,255,.55); border-radius: 18px; padding: 26px 32px 32px; max-width: 760px; margin: 0 auto; }
    .pl-tabs { display: flex; justify-content: center; gap: 40px; border-bottom: 1px solid rgba(255,255,255,.12); margin-bottom: 26px; }
    .pl-tab { background: none; border: 0; color: #fff; font-weight: 600; font-size: 17px; padding: 10px 14px; border-bottom: 2px solid transparent; margin-bottom: -1px; cursor: pointer; }
    .pl-tab.active { color: #3b8de0; border-bottom-color: #3b8de0; }
    .pl-pane { display: none; }
    .pl-pane.active { display: block; }
    .pl-field { margin-bottom: 26px; }
    .pl-field label { display: block; color: #9ca3af; font-size: 15px; margin-bottom: 6px; }
    .pl-field input { width: 100%; background: transparent; border: 0; border-bottom: 1px solid rgba(255,255,255,.35); color: #fff; font-size: 16px; padding: 6px 0; outline: none; }
    .pl-field input:focus { border-bottom-color: #3b8de0; }
    .pl-pass { position: relative; }
    .pl-pass button { position: absolute; right: 0; bottom: 6px; background: none; border: 0; color: #9ca3af; font-size: 20px; cursor: pointer; }
    .pl-submit { display: block; margin: 8px auto 0; min-width: 216px; border: 0; border-radius: 22px; padding: 10px 26px; color: #fff; font-weight: 600; background: linear-gradient(90deg, #1f5f9e, #1b3a6e); }
    .pl-submit:disabled { opacity: .55; }
    .pl-hint { color: #8b95a5; font-size: 13px; margin-top: -14px; margin-bottom: 22px; }
    .pl-dns { color: #8b95a5; font-size: 13px; text-align: center; margin-top: 18px; }
    .pl-list .pl-item { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-bottom: 1px solid #f1f3f7; }
    .pl-list .pl-item:last-child { border-bottom: 0; }
    .pl-item__icon { width: 40px; height: 40px; border-radius: 10px; background: #eef3ff; color: #3b6cf6; display: flex; align-items: center; justify-content: center; font-size: 20px; flex: 0 0 40px; }
    .pl-item__body { flex: 1 1 auto; min-width: 0; }
    .pl-item__name { font-weight: 600; }
    .pl-item__meta { color: #6b7280; font-size: 13px; word-break: break-all; }
</style>
<?php }

/**
 * $hidden_html: campos extras (ex.: CSRF). $dns_list: servidores ativos (só para informação).
 */
function playlist_form_render($hidden_html, $dns_list){ ?>
<div class="pl-box">
    <div class="pl-tabs" role="tablist">
        <button type="button" class="pl-tab active" data-pl-tab="url">URL</button>
        <button type="button" class="pl-tab" data-pl-tab="account">Account</button>
    </div>

    <form method="post" class="pl-pane active" data-pl-pane="url" autocomplete="off">
        <?php echo $hidden_html; ?>
        <input type="hidden" name="mode" value="url">
        <div class="pl-field">
            <label>Playlist name</label>
            <input type="text" name="playlist_name" maxlength="120" required>
        </div>
        <div class="pl-field">
            <label>Playlist URL</label>
            <input type="url" name="playlist_url" required placeholder="http://servidor:porta/get.php?username=...&password=...">
        </div>
        <div class="pl-hint">A URL é usada só para pegar usuário e senha. O app sempre usa o servidor DNS cadastrado que estiver funcionando.</div>
        <button type="submit" name="playlist_submit" class="pl-submit">Upload Now</button>
    </form>

    <form method="post" class="pl-pane" data-pl-pane="account" autocomplete="off">
        <?php echo $hidden_html; ?>
        <input type="hidden" name="mode" value="account">
        <div class="pl-field">
            <label>Playlist name</label>
            <input type="text" name="playlist_name" maxlength="120" required>
        </div>
        <div class="pl-field">
            <label>Username</label>
            <input type="text" name="username" maxlength="191" required autocomplete="off">
        </div>
        <div class="pl-field pl-pass">
            <label>Password</label>
            <input type="password" name="password" maxlength="191" required autocomplete="new-password">
            <button type="button" data-pl-eye title="Mostrar senha"><i class="ri-eye-line"></i></button>
        </div>
        <div class="pl-hint">O sistema procura sozinho qual servidor DNS aceita este usuário. Se o servidor cair ou você migrar o usuário, o app passa a usar o outro automaticamente.</div>
        <button type="submit" name="playlist_submit" class="pl-submit">Upload Now</button>
    </form>

    <div class="pl-dns">
        <?php if ($dns_list) { ?>
            Servidores ativos: <?php $t = array(); foreach ($dns_list as $d) { $t[] = htmlspecialchars($d['title']); } echo implode(' · ', $t); ?>
        <?php } else { ?>
            Nenhum servidor DNS ativo cadastrado.
        <?php } ?>
    </div>
</div>
<?php }

function playlist_form_script(){ ?>
<script>
(function () {
    document.querySelectorAll('[data-pl-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var key = tab.getAttribute('data-pl-tab');
            document.querySelectorAll('[data-pl-tab]').forEach(function (t) { t.classList.toggle('active', t === tab); });
            document.querySelectorAll('[data-pl-pane]').forEach(function (p) { p.classList.toggle('active', p.getAttribute('data-pl-pane') === key); });
        });
    });
    document.querySelectorAll('[data-pl-eye]').forEach(function (b) {
        b.addEventListener('click', function () {
            var input = b.parentNode.querySelector('input');
            input.type = input.type === 'password' ? 'text' : 'password';
            b.innerHTML = input.type === 'password' ? '<i class="ri-eye-line"></i>' : '<i class="ri-eye-off-line"></i>';
        });
    });
    document.querySelectorAll('.pl-pane').forEach(function (f) {
        f.addEventListener('submit', function () {
            var btn = f.querySelector('.pl-submit');
            btn.disabled = true;
            btn.textContent = 'Verificando servidores...';
        });
    });
})();
</script>
<?php }

/**
 * Lista as playlists do aparelho com botão de excluir.
 */
function playlist_list_render($playlists, $hidden_html){ ?>
    <?php if ($playlists) { ?>
        <div class="pl-list">
            <?php foreach ($playlists as $p) { ?>
                <div class="pl-item">
                    <div class="pl-item__icon"><i class="ri-play-list-2-line"></i></div>
                    <div class="pl-item__body">
                        <div class="pl-item__name"><?php echo htmlspecialchars(isset($p['playlist_name']) && $p['playlist_name'] != '' ? $p['playlist_name'] : $p['user_name']); ?></div>
                        <div class="pl-item__meta">
                            Usuário: <?php echo htmlspecialchars($p['user_name']); ?> · Servidor: <?php echo htmlspecialchars($p['dns_base']); ?>
                            <?php if (!empty($p['registered_on']) && ctype_digit((string)$p['registered_on'])) { ?> · <?php echo date('d/m/Y', (int)$p['registered_on']); ?><?php } ?>
                        </div>
                    </div>
                    <form method="post" onsubmit="return confirm('Remover esta playlist do aparelho?');">
                        <?php echo $hidden_html; ?>
                        <input type="hidden" name="playlist_id" value="<?php echo (int)$p['id']; ?>">
                        <button type="submit" name="playlist_delete" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                    </form>
                </div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <p class="text-muted text-center py-4 mb-0">Nenhuma playlist neste aparelho ainda.</p>
    <?php } ?>
<?php }
