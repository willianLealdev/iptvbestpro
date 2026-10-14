<?php $page_title="Gateways de Pagamento";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/payment_helper.php");
    define('AMAZON_IAP_SKU_UI', 'com.iptvbestpro.app.year');

    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] != 3){
        header("Location:dashboard.php"); // chaves de pagamento: somente o Super Admin
        exit;
    }
    payments_migrate($mysqli);

    function gw_flash($ok, $msg){
        $_SESSION['gw_msg'] = $msg;
        $_SESSION['msg'] = 'gw_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }
    // Campo secreto: em branco mantém o valor atual
    function gw_secret($mysqli, $post_key, $cfg_key){
        $v = trim(isset($_POST[$post_key]) ? $_POST[$post_key] : '');
        if (!empty($_POST['clear_'.$post_key])) { pay_set($mysqli, $cfg_key, ''); return; }
        if ($v != '') pay_set($mysqli, $cfg_key, $v);
    }

    if(isset($_POST['save_mp'])){
        pay_set($mysqli, 'mp_enabled', !empty($_POST['mp_enabled']) ? '1' : '0');
        gw_secret($mysqli, 'mp_token', 'mp_token');
        gw_secret($mysqli, 'mp_webhook_secret', 'mp_webhook_secret');
        gw_flash(true, 'Mercado Pago salvo.');
        header("Location:settings_payments.php"); exit;
    }
    if(isset($_POST['save_stripe'])){
        pay_set($mysqli, 'stripe_enabled', !empty($_POST['stripe_enabled']) ? '1' : '0');
        gw_secret($mysqli, 'stripe_secret', 'stripe_secret');
        gw_secret($mysqli, 'stripe_webhook_secret', 'stripe_webhook_secret');
        gw_flash(true, 'Stripe salvo.');
        header("Location:settings_payments.php"); exit;
    }
    if(isset($_POST['save_amazon'])){
        gw_secret($mysqli, 'amazon_secret', 'amazon_secret');
        pay_set($mysqli, 'amazon_sandbox_ok', !empty($_POST['amazon_sandbox_ok']) ? '1' : '0');
        gw_flash(true, 'Amazon salvo.');
        header("Location:settings_payments.php"); exit;
    }
    if(isset($_POST['test_mp'])){
        $r = pay_http('GET', MP_API.'/users/me', array('Authorization: Bearer '.pay_cfg($mysqli, 'mp_token')));
        $ok = $r['code'] == 200;
        gw_flash($ok, $ok ? 'Mercado Pago conectado: conta '.(isset($r['json']['nickname']) ? $r['json']['nickname'] : '').(isset($r['json']['site_id']) ? ' ('.$r['json']['site_id'].')' : '').'.' : 'Falha ao conectar no Mercado Pago (HTTP '.$r['code'].'). Confira o Access Token.');
        header("Location:settings_payments.php"); exit;
    }
    if(isset($_POST['test_stripe'])){
        $r = pay_http('GET', STRIPE_API.'/v1/balance', array('Authorization: Bearer '.pay_cfg($mysqli, 'stripe_secret')));
        $ok = $r['code'] == 200;
        $mode = strpos(pay_cfg($mysqli, 'stripe_secret'), 'sk_test_') === 0 ? 'modo TESTE' : 'modo PRODUÇÃO';
        gw_flash($ok, $ok ? 'Stripe conectado ('.$mode.').' : 'Falha ao conectar no Stripe (HTTP '.$r['code'].'). Confira a Secret key.');
        header("Location:settings_payments.php"); exit;
    }

    if(isset($_SESSION['gw_msg'])){
        $client_lang['gw_custom'] = $_SESSION['gw_msg'];
        unset($_SESSION['gw_msg']);
    }

    $base = pay_base_url();
    $mp_hook = $base.'/pagamento/webhook_mp.php';
    $st_hook = $base.'/pagamento/webhook_stripe.php';
    $recent = mysqli_query($mysqli, "SELECT * FROM tbl_payments ORDER BY id DESC LIMIT 15");
    $mp_token = pay_cfg($mysqli, 'mp_token');
    $st_secret = pay_cfg($mysqli, 'stripe_secret');
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo $page_title; ?></li>
            </ol>
        </nav>

        <style>
            .gw-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15,23,42,.06); }
            .gw-url { font-family: monospace; font-size: 13px; background: #f7f9fe; border-radius: 8px; padding: 8px 10px; word-break: break-all; }
        </style>

        <div class="alert alert-info">
            <b>Como funciona:</b> o cliente ou revendedor clica em "Pagar". O servidor lê o <b>valor do pedido no banco</b> (plano ou créditos) e cria a cobrança.
            Quando o gateway confirma, o painel <b>consulta o pagamento na API do gateway</b>, confere valor e moeda e libera automaticamente
            (código de ativação ou créditos). Nenhum valor é enviado pelo navegador.
        </div>

        <div class="row g-4">
            <div class="col-xl-6">
                <div class="card gw-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="mb-0"><i class="ri-qr-code-line"></i> Mercado Pago — Pix</h5>
                            <span class="badge <?php echo pay_mp_enabled($mysqli) ? 'bg-success' : 'bg-secondary'; ?>"><?php echo pay_mp_enabled($mysqli) ? 'Ativo' : 'Inativo'; ?></span>
                        </div>
                        <p class="small text-muted">Usado para pagamentos em <b>R$</b> (clientes do Brasil e créditos de revendedores).</p>
                        <form method="post" autocomplete="off">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="mp_enabled" id="mp_enabled" value="1" <?php if(pay_cfg($mysqli, 'mp_enabled') == '1'){ echo 'checked'; } ?>>
                                <label class="form-check-label" for="mp_enabled">Aceitar Pix pelo Mercado Pago</label>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Access Token (produção)</label>
                                <input type="password" name="mp_token" class="form-control" autocomplete="new-password" placeholder="<?php echo $mp_token != '' ? 'Salvo: '.htmlspecialchars(pay_mask($mp_token)).' (deixe em branco para manter)' : 'APP_USR-...'; ?>">
                                <small class="text-muted">Mercado Pago → Seu negócio → Configurações → Credenciais (ou "Suas integrações" → sua aplicação → Credenciais de produção).</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Assinatura secreta do webhook <span class="text-muted">(recomendado)</span></label>
                                <input type="password" name="mp_webhook_secret" class="form-control" autocomplete="new-password" placeholder="<?php echo pay_cfg($mysqli, 'mp_webhook_secret') != '' ? 'Salva: '.htmlspecialchars(pay_mask(pay_cfg($mysqli, 'mp_webhook_secret'))) : 'Chave secreta exibida ao configurar o webhook'; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">URL do webhook (cole no Mercado Pago, evento <b>Pagamentos</b>)</label>
                                <div class="gw-url"><?php echo htmlspecialchars($mp_hook); ?></div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="save_mp" class="btn btn-primary">Salvar</button>
                                <button type="submit" name="test_mp" class="btn btn-outline-secondary" <?php if($mp_token == ''){ echo 'disabled'; } ?>>Testar conexão</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card gw-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="mb-0"><i class="ri-bank-card-line"></i> Stripe — Cartão</h5>
                            <span class="badge <?php echo pay_stripe_enabled($mysqli) ? 'bg-success' : 'bg-secondary'; ?>"><?php echo pay_stripe_enabled($mysqli) ? 'Ativo' : 'Inativo'; ?></span>
                        </div>
                        <p class="small text-muted">Cartão de crédito em <b>R$ ou US$</b>. O cliente paga na página segura do Stripe; o painel nunca vê os dados do cartão.</p>
                        <form method="post" autocomplete="off">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="stripe_enabled" id="stripe_enabled" value="1" <?php if(pay_cfg($mysqli, 'stripe_enabled') == '1'){ echo 'checked'; } ?>>
                                <label class="form-check-label" for="stripe_enabled">Aceitar cartão pelo Stripe</label>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Secret key</label>
                                <input type="password" name="stripe_secret" class="form-control" autocomplete="new-password" placeholder="<?php echo $st_secret != '' ? 'Salva: '.htmlspecialchars(pay_mask($st_secret)).' (deixe em branco para manter)' : 'sk_live_... ou sk_test_...'; ?>">
                                <small class="text-muted">Stripe → Developers → API keys. Use <b>sk_test_</b> para testar e <b>sk_live_</b> em produção.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Signing secret do webhook</label>
                                <input type="password" name="stripe_webhook_secret" class="form-control" autocomplete="new-password" placeholder="<?php echo pay_cfg($mysqli, 'stripe_webhook_secret') != '' ? 'Salvo: '.htmlspecialchars(pay_mask(pay_cfg($mysqli, 'stripe_webhook_secret'))) : 'whsec_...'; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">URL do webhook (Stripe → Developers → Webhooks → Add endpoint)</label>
                                <div class="gw-url"><?php echo htmlspecialchars($st_hook); ?></div>
                                <small class="text-muted">Eventos: <b>checkout.session.completed</b>, <b>checkout.session.expired</b>, <b>checkout.session.async_payment_succeeded</b>.</small>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="save_stripe" class="btn btn-primary">Salvar</button>
                                <button type="submit" name="test_stripe" class="btn btn-outline-secondary" <?php if($st_secret == ''){ echo 'disabled'; } ?>>Testar conexão</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card gw-card mt-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="mb-0"><i class="ri-amazon-line"></i> Amazon Appstore — compra dentro do app (Fire TV)</h5>
                    <span class="badge <?php echo pay_cfg($mysqli, 'amazon_secret') != '' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo pay_cfg($mysqli, 'amazon_secret') != '' ? 'Ativo' : 'Inativo'; ?></span>
                </div>
                <p class="small text-muted">No app da Amazon, o cliente assina pelo botão "Assinar pela Amazon" e paga com a conta Amazon dele. O painel confere cada compra direto com a Amazon e ativa o aparelho sozinho. Produto (SKU do período de 1 ano): <b><?php echo htmlspecialchars(AMAZON_IAP_SKU_UI); ?></b></p>
                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">Shared Key (chave compartilhada)</label>
                        <input type="password" name="amazon_secret" class="form-control" autocomplete="new-password" placeholder="<?php echo pay_cfg($mysqli, 'amazon_secret') != '' ? 'Salva: '.htmlspecialchars(pay_mask(pay_cfg($mysqli, 'amazon_secret'))).' (deixe em branco para manter)' : '2:...'; ?>">
                        <small class="text-muted">Amazon Developer Console → seu perfil (canto de cima) → <b>Shared Key</b>.</small>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="amazon_sandbox_ok" id="amazon_sandbox_ok" value="1" <?php if(pay_cfg($mysqli, 'amazon_sandbox_ok') == '1'){ echo 'checked'; } ?>>
                        <label class="form-check-label" for="amazon_sandbox_ok">Aceitar compras de teste do App Tester (deixe desligado em produção)</label>
                    </div>
                    <button type="submit" name="save_amazon" class="btn btn-primary">Salvar</button>
                </form>
            </div>
        </div>

        <div class="card gw-card mt-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Últimos pagamentos</h5>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>#</th><th>Data</th><th>Gateway</th><th>Pedido</th><th class="text-end">Valor</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if(mysqli_num_rows($recent) > 0){ while($p = mysqli_fetch_assoc($recent)){
                            $badge = array('paid' => 'bg-success', 'pending' => 'bg-warning text-dark', 'failed' => 'bg-danger', 'expired' => 'bg-secondary');
                            $label = array('paid' => 'Pago', 'pending' => 'Pendente', 'failed' => 'Recusado', 'expired' => 'Expirado'); ?>
                            <tr>
                                <td><?php echo (int)$p['id']; ?></td>
                                <td><?php echo date('d/m/Y H:i', $p['created_at']); ?></td>
                                <td><?php echo $p['provider'] == 'mercadopago' ? 'Mercado Pago (Pix)' : 'Stripe (cartão)'; ?></td>
                                <td><?php echo $p['order_type'] == 'sub' ? 'Assinatura' : 'Créditos'; ?> #<?php echo (int)$p['order_id']; ?></td>
                                <td class="text-end"><?php echo money_format_currency($p['amount'], $p['currency']); ?></td>
                                <td><span class="badge <?php echo isset($badge[$p['status']]) ? $badge[$p['status']] : 'bg-secondary'; ?>"><?php echo isset($label[$p['status']]) ? $label[$p['status']] : htmlspecialchars($p['status']); ?></span></td>
                            </tr>
                        <?php } } else { ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhum pagamento ainda.</td></tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
