<?php
/**
 * Página pública de envio da lista para aparelhos ativados com o código de um servidor parceiro.
 * 1) O cliente informa o ID do aparelho (o QR code do app já traz o ID em ?d=): o sistema confere de qual servidor ele é.
 * 2) Depois informa usuário e senha: o sistema confere só nas DNS desse servidor e adiciona a lista.
 * A lista nunca é adicionada pelo app.
 */
require_once("includes/db_helper.php");
require_once("includes/lb_helper.php");
require_once("includes/brand_helper.php");
require_once("includes/server_helper.php");
require_once("includes/auth_layout.php");
server_migrate($mysqli);

function ls_token(){ if (empty($_SESSION['ls_csrf'])) $_SESSION['ls_csrf'] = bin2hex(random_bytes(16)); return $_SESSION['ls_csrf']; }
function ls_ok(){ return isset($_POST['csrf'], $_SESSION['ls_csrf']) && hash_equals($_SESSION['ls_csrf'], (string)$_POST['csrf']); }

// Confere o aparelho sem gastar tentativa quando ele existe e é de um servidor
function ls_device($mysqli, $device_id){
    $device_id = devices_clean(trim((string)$device_id), 191);
    if ($device_id == '') return array(null, null);
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM tbl_devices WHERE device_id = ? ORDER BY last_seen DESC, id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $device_id);
    mysqli_stmt_execute($stmt);
    $d = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return array($d, $d ? server_of_device($mysqli, $d) : null);
}

$did = trim(isset($_POST['device_id']) ? $_POST['device_id'] : (isset($_GET['d']) ? $_GET['d'] : ''));
$step = 'device'; $error = ''; $done = ''; $server = null; $device = null;

if ($did !== '') {
    list($device, $server) = ls_device($mysqli, $did);
    if (!$device || !$server) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') devices_link_fail($mysqli, devices_client_ip());
        $error = 'Aparelho não encontrado. Confira o ID na tela do app e se ele foi ativado com o código do seu servidor.';
    } else if (devices_activation_status($device) != 'active') {
        $error = 'Este aparelho não está ativo. Digite o código do seu servidor no aplicativo para ativar.';
    } else {
        $step = 'user';
    }
}

if ($step == 'user' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_list'])) {
    if (!ls_ok()) {
        $error = 'Sessão expirada. Tente novamente.';
    } else {
        $r = server_upload_submit($mysqli, $device['device_id'], isset($_POST['username']) ? $_POST['username'] : '', isset($_POST['password']) ? $_POST['password'] : '');
        if ($r['ok']) { $done = $r['msg']; $step = 'done'; } else { $error = $r['msg']; $step = $r['step'] == 'device' ? 'device' : 'user'; }
    }
}

auth_start(array('prefix' => '', 'title' => 'Enviar lista', 'badge' => 'Enviar lista', 'icon' => 'ri-upload-cloud-2-line',
    'subtitle' => $step == 'device' ? 'Digite o ID do aparelho que aparece na tela do aplicativo.' : ($step == 'user' ? 'Agora digite o usuário e a senha da sua lista.' : ''))); ?>
        <?php auth_alert('err', $error); ?>
        <?php if ($step == 'done') { ?>
            <div class="au-done">
                <div class="au-done__ico"><i class="ri-checkbox-circle-line"></i></div>
                <h2>Lista adicionada!</h2>
                <p><?php echo auth_e($done); ?></p>
                <p class="au-small">O aplicativo confere sozinho a cada poucos segundos e abre a lista assim que ela chegar.</p>
            </div>
        <?php } else if ($step == 'user') { ?>
            <div class="au-alert ok"><i class="ri-server-line"></i><div>
                Aparelho <b translate="no"><?php echo auth_e($device['device_id']); ?></b> encontrado.<br>
                Servidor: <b translate="no"><?php echo auth_e($server['name']); ?></b>
            </div></div>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf" value="<?php echo ls_token(); ?>">
                <input type="hidden" name="device_id" value="<?php echo auth_e($device['device_id']); ?>">
                <?php auth_input(array('name' => 'username', 'label' => 'Usuário', 'icon' => 'ri-user-3-line', 'value' => isset($_POST['username']) ? $_POST['username'] : '', 'attrs' => 'autofocus autocomplete="off" autocapitalize="off" spellcheck="false"')); ?>
                <?php auth_input(array('name' => 'password', 'type' => 'password', 'label' => 'Senha', 'icon' => 'ri-lock-2-line', 'attrs' => 'autocomplete="off"')); ?>
                <button type="submit" name="send_list" value="1" class="au-btn"><i class="ri-upload-cloud-2-line"></i> Enviar lista</button>
            </form>
            <p class="au-alt"><a href="lista">Usar outro aparelho</a></p>
        <?php } else { ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf" value="<?php echo ls_token(); ?>">
                <?php auth_input(array('name' => 'device_id', 'label' => 'ID do aparelho', 'icon' => 'ri-tv-2-line', 'value' => $did, 'placeholder' => 'Ex.: tv12ab34cd56ef78', 'attrs' => 'autofocus autocomplete="off" autocapitalize="off" spellcheck="false"')); ?>
                <button type="submit" class="au-btn"><i class="ri-arrow-right-line"></i> Continuar</button>
            </form>
        <?php } ?>
<?php auth_end(); ?>
