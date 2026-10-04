<?php
/**
 * Formulário de playlist (abas URL / Account) + lista de playlists do dispositivo.
 * Usado em device_playlist.php (admin) e revenda/playlist.php (revendedor).
 */

function playlist_form_styles(){ ?>
<style>
    .pl-box { background: #fff; color: #1f2a44; border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15, 23, 42, .06); padding: 22px 28px 28px; height: 100%; }
    .pl-tabs { display: flex; justify-content: center; gap: 36px; border-bottom: 1px solid #eef0f4; margin-bottom: 24px; }
    .pl-tab { background: none; border: 0; color: #6b7a99; font-weight: 600; font-size: 16px; padding: 10px 14px; border-bottom: 2px solid transparent; margin-bottom: -1px; cursor: pointer; }
    .pl-tab:hover { color: #1f2a44; }
    .pl-tab.active { color: #3b6cf6; border-bottom-color: #3b6cf6; }
    .pl-pane { display: none; }
    .pl-pane.active { display: block; }
    .pl-field { margin-bottom: 18px; }
    .pl-field label { display: block; color: #4b5563; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
    .pl-field input { width: 100%; background: #fff; border: 1px solid #dfe3ea; border-radius: 8px; color: #1f2a44; font-size: 15px; padding: 9px 12px; outline: none; transition: border-color .15s, box-shadow .15s; }
    .pl-field input:focus { border-color: #3b6cf6; box-shadow: 0 0 0 3px rgba(59, 108, 246, .12); }
    .pl-field input::placeholder { color: #a3abb9; }
    .pl-pass { position: relative; }
    .pl-pass input { padding-right: 42px; }
    .pl-pass button { position: absolute; right: 8px; bottom: 6px; background: none; border: 0; color: #6b7280; font-size: 19px; cursor: pointer; }
    .pl-submit { display: block; margin: 6px auto 0; min-width: 200px; border: 0; border-radius: 8px; padding: 10px 26px; color: #fff; font-weight: 600; background: #3b6cf6; }
    .pl-submit:hover { background: #2f5be0; }
    .pl-submit:disabled { opacity: .6; }
    .pl-hint { color: #6b7a99; font-size: 13px; margin-top: -6px; margin-bottom: 20px; }
    .pl-dns { color: #6b7a99; font-size: 13px; text-align: center; margin-top: 16px; }
    .pl-dns.pl-dns--warn { color: #b45309; background: #fff7e6; border: 1px solid #ffe7ba; border-radius: 8px; padding: 8px 12px; }
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
function playlist_form_render($hidden_html, $dns_list, $dns_manage_url = ''){ ?>
<div class="pl-box">
    <div class="pl-tabs" role="tablist">
        <button type="button" class="pl-tab active" data-pl-tab="url">URL</button>
        <button type="button" class="pl-tab" data-pl-tab="account">Account</button>
    </div>

    <form method="post" class="pl-pane active" data-pl-pane="url" autocomplete="off">
        <?php echo $hidden_html; ?>
        <input type="hidden" name="mode" value="url">
        <input type="hidden" name="playlist_submit" value="1">
        <div class="pl-field">
            <label>Playlist name</label>
            <input type="text" name="playlist_name" maxlength="120" required>
        </div>
        <div class="pl-field">
            <label>Playlist URL</label>
            <input type="url" name="playlist_url" required placeholder="http://servidor:porta/get.php?username=...&password=...">
        </div>
        <div class="pl-hint">O servidor já vem na URL, então ela é salva direto, sem verificação.</div>
        <button type="submit" class="pl-submit">Upload Now</button>
    </form>

    <form method="post" class="pl-pane" data-pl-pane="account" autocomplete="off">
        <?php echo $hidden_html; ?>
        <input type="hidden" name="mode" value="account">
        <input type="hidden" name="playlist_submit" value="1">
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
        <button type="submit" class="pl-submit" <?php if (!$dns_list) { echo 'disabled'; } ?>>Upload Now</button>

        <?php if ($dns_list) { ?>
            <div class="pl-dns">Servidores ativos: <?php $t = array(); foreach ($dns_list as $d) { $t[] = htmlspecialchars($d['title']); } echo implode(' · ', $t); ?></div>
        <?php } else { ?>
            <div class="pl-dns pl-dns--warn">
                Nenhum servidor DNS ativo. Para usar usuário e senha, cadastre pelo menos um servidor
                <?php if ($dns_manage_url != '') { ?>em <a href="<?php echo htmlspecialchars($dns_manage_url); ?>">Servidores DNS → DNS</a><?php } else { ?>(fale com o administrador)<?php } ?>.
            </div>
        <?php } ?>
    </form>
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
                            <?php if (isset($p['source']) && $p['source'] == 'url') { ?> · <span title="Servidor fixo da URL">URL</span><?php } else { ?> · <span title="Troca de servidor automática">DNS automático</span><?php } ?>
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
