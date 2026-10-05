<?php
/**
 * Dispositivos ao vivo: a tela pergunta a cada 10 s só uma "assinatura" da lista (1 consulta leve)
 * e recarrega apenas quando algo mudou (aparelho novo, ativação, pacote, vencimento, nome).
 * last_seen fica de fora: o app consulta o status a cada 5 s e isso não deve recarregar a tela.
 */
function devices_live_signature($mysqli, $scope_sql = ''){
    $where = $scope_sql != '' ? 'WHERE '.$scope_sql : '';
    $q = mysqli_query($mysqli, "SELECT COUNT(*) AS n, COALESCE(MAX(id),0) AS m,
            COALESCE(SUM(CRC32(CONCAT_WS('|', id, owner, reseller_id, package, expires_at, activated_at, customer_name))),0) AS h
            FROM tbl_devices $where");
    $r = $q ? mysqli_fetch_assoc($q) : array('n' => 0, 'm' => 0, 'h' => 0);
    return $r['n'].'-'.$r['m'].'-'.$r['h'];
}

function devices_live_output($mysqli, $scope_sql = ''){
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(array('sig' => devices_live_signature($mysqli, $scope_sql)));
    exit;
}

// Botão "Atualizar" + recarga automática quando a lista muda
function devices_live_script($sig_url){ ?>
<script>
(function () {
    var url = <?php echo json_encode($sig_url); ?>, last = null, busy = false;
    function blocked() {
        if (document.hidden) return true;
        if (document.querySelector('.modal.show')) return true;
        var a = document.activeElement;
        return a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.tagName === 'SELECT');
    }
    function check() {
        if (busy || blocked()) return;
        busy = true;
        fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
            busy = false;
            if (!d || !d.sig) return;
            if (last !== null && d.sig !== last) { location.reload(); return; }
            last = d.sig;
        }).catch(function () { busy = false; });
    }
    check();
    setInterval(check, 10000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
    document.querySelectorAll('.js-devices-refresh').forEach(function (b) {
        b.addEventListener('click', function () { b.classList.add('disabled'); location.reload(); });
    });
})();
</script>
<?php }
