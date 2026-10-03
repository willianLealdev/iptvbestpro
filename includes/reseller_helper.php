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

    // Dispositivo ativado por qual revendedor (0 = nenhum)
    $col = mysqli_query($mysqli, "SHOW COLUMNS FROM `tbl_devices` LIKE 'reseller_id'");
    if ($col && mysqli_num_rows($col) == 0) {
        mysqli_query($mysqli, "ALTER TABLE `tbl_devices` ADD `reseller_id` INT(11) NOT NULL DEFAULT 0, ADD KEY `idx_reseller` (`reseller_id`)");
    }
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
