<?php
/**
 * Revendedores (resellers): tabelas, créditos e vínculo com dispositivos.
 * Tabelas criadas/atualizadas automaticamente.
 */

require_once(__DIR__."/device_tracker.php");

function resellers_migrate($mysqli){
    static $done = false;
    if ($done) return;
    $done = true;

    devices_migrate($mysqli);

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_resellers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(120) NOT NULL,
        `email` VARCHAR(191) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `credits` INT(11) NOT NULL DEFAULT 0,
        `status` INT(1) NOT NULL DEFAULT 1,
        `remark` TEXT NULL,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        `updated_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_reseller_email` (`email`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Histórico de créditos (positivo = adicionado, negativo = removido/consumido)
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbl_reseller_credits` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `reseller_id` INT(11) NOT NULL,
        `amount` INT(11) NOT NULL,
        `balance_after` INT(11) NOT NULL DEFAULT 0,
        `type` VARCHAR(20) NOT NULL DEFAULT 'admin',
        `note` VARCHAR(255) NOT NULL DEFAULT '',
        `admin_id` INT(11) NOT NULL DEFAULT 0,
        `created_at` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_reseller` (`reseller_id`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Campos de ativação no dispositivo
    $columns = array(
        'reseller_id'   => "INT(11) NOT NULL DEFAULT 0",          // revendedor que ativou (0 = admin / nenhum)
        'activated_by'  => "VARCHAR(191) NOT NULL DEFAULT ''",    // e-mail do revendedor ou 'admin: usuario'
        'package'       => "VARCHAR(20) NOT NULL DEFAULT ''",     // '' = não ativado
        'used_credits'  => "INT(11) NOT NULL DEFAULT 0",          // total de créditos gastos neste aparelho
        'activated_at'  => "INT(11) NOT NULL DEFAULT 0",
        'expires_at'    => "INT(11) NOT NULL DEFAULT 0",          // 0 com pacote = vitalício
    );
    foreach ($columns as $name => $definition) {
        $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_devices` LIKE '$name'");
        if ($col && mysqli_num_rows($col) == 0) {
            mysqli_query($mysqli, "ALTER TABLE `tbl_devices` ADD `$name` $definition");
        }
    }
    $idx = mysqli_query($mysqli, "SHOW INDEX FROM `tbl_devices` WHERE Key_name = 'idx_reseller'");
    if ($idx && mysqli_num_rows($idx) == 0) {
        mysqli_query($mysqli, "ALTER TABLE `tbl_devices` ADD KEY `idx_reseller` (`reseller_id`), ADD KEY `idx_expires` (`expires_at`)");
    }
}

/**
 * Pacotes de ativação. Ajuste aqui preço (créditos) e duração (dias, 0 = vitalício).
 */
function devices_packages(){
    return array(
        'trial'    => array('label' => 'Teste',     'days' => 7,   'credits' => 0, 'class' => 'pk-trial'),
        'year'     => array('label' => 'Anual',     'days' => 365, 'credits' => 1, 'class' => 'pk-year'),
        'lifetime' => array('label' => 'Vitalício', 'days' => 0,   'credits' => 2, 'class' => 'pk-lifetime'),
    );
}

// 'active' | 'expired' | 'inactive'
function devices_activation_status($row){
    if ($row['package'] == '') return 'inactive';
    if ((int)$row['expires_at'] == 0) return 'active';
    return ((int)$row['expires_at'] >= time()) ? 'active' : 'expired';
}

/**
 * Ativa ou renova um dispositivo.
 * $reseller_id > 0 desconta os créditos do pacote desse revendedor; 0 = ativação pelo admin (sem custo).
 * Renovação de aparelho ainda válido soma os dias a partir do vencimento atual.
 * Retorna array('ok' => bool, 'msg' => string).
 */
function devices_activate($mysqli, $device_pk, $package_key, $reseller_id, $admin_label = '', $admin_id = 0){
    $packages = devices_packages();
    if (!isset($packages[$package_key])) return array('ok' => false, 'msg' => 'Pacote inválido.');
    $pkg = $packages[$package_key];

    $device_pk = (int)$device_pk;
    $res = mysqli_query($mysqli, "SELECT * FROM tbl_devices WHERE id = $device_pk");
    $device = $res ? mysqli_fetch_assoc($res) : null;
    if (!$device) return array('ok' => false, 'msg' => 'Dispositivo não encontrado.');

    $reseller_id = (int)$reseller_id;
    $credits = 0;
    $activated_by = $admin_label;
    if ($reseller_id > 0) {
        $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id, email, status FROM tbl_resellers WHERE id = $reseller_id"));
        if (!$r) return array('ok' => false, 'msg' => 'Revendedor não encontrado.');
        if ((int)$r['status'] != 1) return array('ok' => false, 'msg' => 'Revendedor bloqueado.');
        $credits = (int)$pkg['credits'];
        $activated_by = $r['email'];
        if ($credits > 0) {
            $note = $pkg['label'].' · '.$device['device_id'];
            if (resellers_add_credits($mysqli, $reseller_id, -$credits, $note, 'activation', $admin_id) === false) {
                return array('ok' => false, 'msg' => 'Créditos insuficientes (precisa de '.$credits.').');
            }
        }
    }

    $now = time();
    $was_valid = devices_activation_status($device) == 'active';
    if ($pkg['days'] == 0) {
        $expires = 0;
    } else {
        $base = ($was_valid && (int)$device['expires_at'] > $now) ? (int)$device['expires_at'] : $now;
        $expires = $base + ($pkg['days'] * 86400);
    }
    $activated_at = $was_valid && (int)$device['activated_at'] > 0 ? (int)$device['activated_at'] : $now;

    $stmt = mysqli_prepare($mysqli, "UPDATE tbl_devices SET package = ?, reseller_id = ?, activated_by = ?, used_credits = used_credits + ?, activated_at = ?, expires_at = ? WHERE id = ?");
    $activated_by = mb_substr($activated_by, 0, 191);
    mysqli_stmt_bind_param($stmt, 'sisiiii', $package_key, $reseller_id, $activated_by, $credits, $activated_at, $expires, $device_pk);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok && $reseller_id > 0 && $credits > 0) {
        resellers_add_credits($mysqli, $reseller_id, $credits, 'Estorno: falha na ativação', 'refund', $admin_id);
    }
    return $ok ? array('ok' => true, 'msg' => 'Dispositivo ativado: '.$pkg['label'].'.') : array('ok' => false, 'msg' => 'Erro ao salvar a ativação.');
}

function devices_deactivate($mysqli, $device_pk){
    $device_pk = (int)$device_pk;
    return mysqli_query($mysqli, "UPDATE tbl_devices SET package = '', expires_at = 0 WHERE id = $device_pk");
}

/**
 * Soma (ou subtrai, se negativo) créditos de forma atômica e registra no histórico.
 * Retorna o novo saldo, ou false se o saldo ficaria negativo / revendedor não existe.
 */
function resellers_add_credits($mysqli, $reseller_id, $amount, $note = '', $type = 'admin', $admin_id = 0){
    $reseller_id = (int)$reseller_id;
    $amount = (int)$amount;
    if ($reseller_id <= 0 || $amount == 0) return false;

    mysqli_begin_transaction($mysqli);
    $res = mysqli_query($mysqli, "SELECT credits FROM tbl_resellers WHERE id = $reseller_id FOR UPDATE");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row || ((int)$row['credits'] + $amount) < 0) {
        mysqli_rollback($mysqli);
        return false;
    }
    $balance = (int)$row['credits'] + $amount;
    $now = time();
    mysqli_query($mysqli, "UPDATE tbl_resellers SET credits = $balance, updated_at = $now WHERE id = $reseller_id");

    $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_reseller_credits (reseller_id, amount, balance_after, type, note, admin_id, created_at) VALUES (?,?,?,?,?,?,?)");
    $note = mb_substr(trim(strip_tags((string)$note)), 0, 255);
    $type = mb_substr((string)$type, 0, 20);
    $admin_id = (int)$admin_id;
    mysqli_stmt_bind_param($stmt, 'iiissii', $reseller_id, $amount, $balance, $type, $note, $admin_id, $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($mysqli);
    return $balance;
}

function resellers_total_credit_share($mysqli){
    $res = mysqli_query($mysqli, "SELECT COALESCE(SUM(amount),0) AS num FROM tbl_reseller_credits WHERE amount > 0");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ? (int)$row['num'] : 0;
}
