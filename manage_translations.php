<?php $page_title="Traduções";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/reseller_helper.php");

    // Só o Super Admin mexe nas traduções
    if(!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] != 3){
        header("Location:dashboard.php");
        exit;
    }
    resellers_migrate($mysqli);
    i18n_migrate($mysqli);

    function tr_flash($ok, $msg){
        $_SESSION['tr_msg'] = $msg;
        $_SESSION['msg'] = 'tr_custom';
        $_SESSION['class'] = $ok ? 'success' : 'error';
    }
    function tr_clean($v){ return mb_substr(trim((string)$v), 0, 2000); }
    $back = "manage_translations.php".(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '');

    if(isset($_POST['capture'])){
        $until = $_POST['capture'] == 'on' ? time() + 86400 : 0;
        panel_config_set($mysqli, 'i18n_capture_until', (string)$until);
        tr_flash(true, $until ? 'Captura ligada por 24 horas. Navegue pelas páginas nos 3 idiomas para coletar os textos.' : 'Captura desligada.');
        header("Location:".$back);
        exit;
    }
    if(isset($_POST['save_tr'])){
        $src = i18n_norm(isset($_POST['src']) ? $_POST['src'] : '');
        if($src !== ''){
            $pt = tr_clean($_POST['pt']); $en = tr_clean($_POST['en']); $es = tr_clean($_POST['es']);
            $h = sha1($src); $now = time();
            $stmt = mysqli_prepare($mysqli, "INSERT INTO tbl_i18n (src, src_hash, pt, en, es, updated_at) VALUES (?,?,?,?,?,?)
                                             ON DUPLICATE KEY UPDATE pt = VALUES(pt), en = VALUES(en), es = VALUES(es), updated_at = VALUES(updated_at)");
            mysqli_stmt_bind_param($stmt, 'sssssi', $src, $h, $pt, $en, $es, $now);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            mysqli_query($mysqli, "DELETE FROM tbl_i18n_missing WHERE src_hash = '".$h."'");
            tr_flash($ok, $ok ? 'Tradução salva.' : 'Não foi possível salvar a tradução.');
        }
        header("Location:".$back);
        exit;
    }
    if(isset($_POST['ignore_missing'])){
        mysqli_query($mysqli, "UPDATE tbl_i18n_missing SET ignored = 1 WHERE id = ".(int)$_POST['missing_id']);
        tr_flash(true, 'Texto ignorado.');
        header("Location:".$back);
        exit;
    }
    if(isset($_POST['clear_missing'])){
        mysqli_query($mysqli, "DELETE FROM tbl_i18n_missing");
        tr_flash(true, 'Lista de textos sem tradução limpa.');
        header("Location:".$back);
        exit;
    }
    if(isset($_POST['reset_tr'])){
        mysqli_query($mysqli, "DELETE FROM tbl_i18n WHERE src_hash = '".sha1(i18n_norm($_POST['src']))."'");
        tr_flash(true, 'Tradução personalizada removida. Volta a valer a tradução padrão.');
        header("Location:".$back);
        exit;
    }

    if(isset($_SESSION['tr_msg'])){
        $client_lang['tr_custom'] = $_SESSION['tr_msg'];
        unset($_SESSION['tr_msg']);
    }

    $capture_until = (int)panel_config_get($mysqli, 'i18n_capture_until', '0');
    $capture_on = $capture_until > time();

    $missing = array();
    $res = mysqli_query($mysqli, "SELECT * FROM tbl_i18n_missing WHERE ignored = 0 ORDER BY hits DESC, last_seen DESC LIMIT 200");
    while($res && $r = mysqli_fetch_assoc($res)) $missing[] = $r;

    // Busca no dicionário (arquivo + personalizadas)
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $file_dict = @include("includes/lang/dict.php");
    if(!is_array($file_dict)) $file_dict = array();
    $custom = array();
    $res = mysqli_query($mysqli, "SELECT * FROM tbl_i18n ORDER BY updated_at DESC");
    while($res && $r = mysqli_fetch_assoc($res)) $custom[$r['src']] = $r;
    $results = array();
    if($q !== ''){
        $needle = mb_strtolower($q);
        $all = $file_dict;
        foreach($custom as $src => $r){ $all[$src] = array('pt' => $r['pt'], 'en' => $r['en'], 'es' => $r['es']); }
        foreach($all as $src => $tr){
            $hay = mb_strtolower($src.' '.implode(' ', $tr));
            if(mb_strpos($hay, $needle) !== false){
                $base = isset($file_dict[$src]) ? $file_dict[$src] : array('pt' => '', 'en' => '', 'es' => '');
                if(isset($custom[$src])) foreach(array('pt','en','es') as $l){ if($custom[$src][$l] !== null && $custom[$src][$l] !== '') $base[$l] = $custom[$src][$l]; }
                $results[$src] = $base;
                if(count($results) >= 60) break;
            }
        }
    } else {
        foreach($custom as $src => $r){ $results[$src] = array('pt' => $r['pt'], 'en' => $r['en'], 'es' => $r['es']); }
    }
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
            .tr-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(15, 23, 42, .06); }
            .tr-src { font-weight: 600; color: #1f2a44; word-break: break-word; }
            .tr-row { border: 1px solid #eef0f4; border-radius: 12px; padding: 14px; margin-bottom: 12px; }
            .tr-row textarea { font-size: 14px; min-height: 42px; }
            .tr-flag { width: 20px; height: 14px; border-radius: 3px; margin-right: 6px; vertical-align: -2px; }
        </style>

        <div class="row g-4">
            <div class="col-xl-4">
                <div class="card tr-card mb-4">
                    <div class="card-body p-4">
                        <h5 class="mb-2">Idiomas do sistema</h5>
                        <p class="text-muted small mb-3">O sistema funciona em <b>Português</b>, <b>English</b> e <b>Español</b>. Cada pessoa pode trocar o idioma pelo seletor no topo e salvar como padrão. Sem padrão salvo, o idioma segue o país do IP: Brasil = Português, países de língua espanhola = Español, demais = English.</p>
                        <hr>
                        <h6 class="mb-2">Capturar textos sem tradução</h6>
                        <p class="text-muted small">Enquanto ligado, os textos das páginas que ainda não têm tradução aparecem na lista ao lado para você traduzir. Desliga sozinho em 24 horas.</p>
                        <form method="post">
                            <?php if($capture_on){ ?>
                                <div class="alert alert-success small py-2">Ligado até <?php echo date('d/m/Y H:i', $capture_until); ?></div>
                                <button type="submit" name="capture" value="off" class="btn btn-outline-secondary w-100">Desligar captura</button>
                            <?php } else { ?>
                                <button type="submit" name="capture" value="on" class="btn btn-primary w-100"><i class="ri-record-circle-line"></i> Ligar captura por 24 horas</button>
                            <?php } ?>
                        </form>
                    </div>
                </div>
                <div class="card tr-card">
                    <div class="card-body p-4">
                        <h5 class="mb-3">Buscar tradução</h5>
                        <form method="get">
                            <div class="input-group">
                                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($q); ?>" placeholder="Texto em qualquer idioma">
                                <button class="btn btn-primary" type="submit"><i class="ri-search-line"></i></button>
                            </div>
                        </form>
                        <p class="text-muted small mt-2 mb-0">Encontre um texto e corrija a tradução. A sua versão tem prioridade sobre a padrão.</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card tr-card mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h5 class="mb-0">Textos sem tradução <span class="badge bg-warning text-dark"><?php echo count($missing); ?></span></h5>
                            <?php if($missing){ ?>
                                <form method="post" onsubmit="return confirm('Limpar a lista de textos sem tradução?');"><button type="submit" name="clear_missing" class="btn btn-sm btn-outline-danger">Limpar lista</button></form>
                            <?php } ?>
                        </div>
                        <?php if($missing){ foreach($missing as $m){ ?>
                            <form method="post" class="tr-row">
                                <input type="hidden" name="src" value="<?php echo htmlspecialchars($m['src']); ?>">
                                <div class="d-flex justify-content-between gap-2 mb-2">
                                    <div class="tr-src" translate="no"><?php echo htmlspecialchars($m['src']); ?></div>
                                    <small class="text-muted text-nowrap" translate="no"><?php echo htmlspecialchars($m['page']); ?> · <?php echo (int)$m['hits']; ?>×</small>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-4"><label class="small"><img class="tr-flag" src="https://flagcdn.com/w40/br.png" alt="">PT</label><textarea name="pt" class="form-control" rows="1"><?php echo htmlspecialchars($m['src']); ?></textarea></div>
                                    <div class="col-md-4"><label class="small"><img class="tr-flag" src="https://flagcdn.com/w40/us.png" alt="">EN</label><textarea name="en" class="form-control" rows="1"></textarea></div>
                                    <div class="col-md-4"><label class="small"><img class="tr-flag" src="https://flagcdn.com/w40/es.png" alt="">ES</label><textarea name="es" class="form-control" rows="1"></textarea></div>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button type="submit" name="save_tr" class="btn btn-sm btn-success"><i class="ri-check-line"></i> Salvar</button>
                                    <input type="hidden" name="missing_id" value="<?php echo (int)$m['id']; ?>">
                                    <button type="submit" name="ignore_missing" class="btn btn-sm btn-outline-secondary">Ignorar (não é texto do sistema)</button>
                                </div>
                            </form>
                        <?php } } else { ?>
                            <p class="text-muted mb-0">Nenhum texto pendente. <?php echo $capture_on ? 'Navegue pelas páginas para coletar textos.' : 'Ligue a captura para encontrar textos que ainda não têm tradução.'; ?></p>
                        <?php } ?>
                    </div>
                </div>

                <div class="card tr-card">
                    <div class="card-body p-4">
                        <h5 class="mb-3"><?php echo $q !== '' ? 'Resultados da busca' : 'Traduções personalizadas'; ?> <span class="badge bg-light text-dark"><?php echo count($results); ?></span></h5>
                        <?php if($results){ foreach($results as $src => $tr){ ?>
                            <form method="post" class="tr-row">
                                <input type="hidden" name="src" value="<?php echo htmlspecialchars($src); ?>">
                                <div class="tr-src mb-2" translate="no"><?php echo htmlspecialchars($src); ?><?php if(isset($custom[$src])){ ?> <span class="badge bg-success">personalizada</span><?php } ?></div>
                                <div class="row g-2">
                                    <div class="col-md-4"><label class="small"><img class="tr-flag" src="https://flagcdn.com/w40/br.png" alt="">PT</label><textarea name="pt" class="form-control" rows="1"><?php echo htmlspecialchars((string)$tr['pt']); ?></textarea></div>
                                    <div class="col-md-4"><label class="small"><img class="tr-flag" src="https://flagcdn.com/w40/us.png" alt="">EN</label><textarea name="en" class="form-control" rows="1"><?php echo htmlspecialchars((string)$tr['en']); ?></textarea></div>
                                    <div class="col-md-4"><label class="small"><img class="tr-flag" src="https://flagcdn.com/w40/es.png" alt="">ES</label><textarea name="es" class="form-control" rows="1"><?php echo htmlspecialchars((string)$tr['es']); ?></textarea></div>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button type="submit" name="save_tr" class="btn btn-sm btn-success"><i class="ri-check-line"></i> Salvar</button>
                                    <?php if(isset($custom[$src])){ ?><button type="submit" name="reset_tr" class="btn btn-sm btn-outline-secondary">Voltar ao padrão</button><?php } ?>
                                </div>
                            </form>
                        <?php } } else { ?>
                            <p class="text-muted mb-0"><?php echo $q !== '' ? 'Nada encontrado.' : 'Nenhuma tradução personalizada ainda.'; ?></p>
                        <?php } ?>
                        <p class="small text-muted mt-2 mb-0">Partes variáveis aparecem como <code translate="no">{}</code> no texto original e como <code translate="no">{0}</code>, <code translate="no">{1}</code> nas traduções. Mantenha esses marcadores.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
