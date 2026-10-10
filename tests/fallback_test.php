<?php
// Teste automático (GitHub Actions) da troca de usuário do aparelho (playlist_fallback_map).
// Willian = lista 1, Tina = lista 2, Maria = lista 3. online/offline = se o painel achou um DNS que conecta.
if (php_sapi_name() !== 'cli') exit;
// só a função de regra (sem banco)
$src = file_get_contents(__DIR__.'/../includes/playlist_helper.php');
preg_match('/function playlist_fallback_map\(.*?\n}\n/s', str_replace("\r\n", "\n", $src), $m);
eval($m[0]);

function L($st) { $r = array(); foreach ($st as $id => $s) $r[] = array('id' => $id, 'dns_status' => $s); return $r; }
$fail = 0;
function check($name, $got, $want) {
    global $fail;
    if (json_encode($got) !== json_encode($want)) { echo "FALHOU: $name -> ".json_encode($got)." (esperado ".json_encode($want).")\n"; $fail++; }
    else echo "ok: $name\n";
}

// 1) tudo conectando: ninguém é trocado
check('todos conectam', playlist_fallback_map(L(array(1 => 'online', 2 => 'online')), array()), array());
// 2) Willian caiu em todos os DNS, Tina conecta: a lista do Willian entrega a Tina
$m = playlist_fallback_map(L(array(1 => 'offline', 2 => 'online')), array());
check('Willian caiu -> Tina', $m, array(1 => 2));
// 3) Willian voltou, Tina continua conectando: fica na Tina (até ela cair)
$m = playlist_fallback_map(L(array(1 => 'online', 2 => 'online')), $m);
check('Willian voltou -> continua na Tina', $m, array(1 => 2));
// 4) Tina caiu, Willian conecta: volta para o Willian
$m = playlist_fallback_map(L(array(1 => 'online', 2 => 'offline')), $m);
check('Tina caiu -> Willian (a lista da Tina entrega o Willian)', $m, array(2 => 1));
// 5) os dois caíram: entrega como está (o monitor testa tudo de novo)
check('ninguém conecta -> sem troca', playlist_fallback_map(L(array(1 => 'offline', 2 => 'offline')), $m), array());
// 6) três usuários: Willian e Tina caídos, Maria conecta -> os dois entregam a Maria
$m = playlist_fallback_map(L(array(1 => 'offline', 2 => 'offline', 3 => 'online')), array());
check('3 usuários -> Maria', $m, array(1 => 3, 2 => 3));
// 7) usando a Maria, Tina volta: continua na Maria (não fica pulando)
$m = playlist_fallback_map(L(array(1 => 'offline', 2 => 'online', 3 => 'online')), $m);
check('Tina volta -> continua na Maria', $m, array(1 => 3, 2 => 3));
// 8) Maria cai, Tina conecta: Willian e Maria passam a entregar a Tina
$m = playlist_fallback_map(L(array(1 => 'offline', 2 => 'online', 3 => 'offline')), $m);
check('Maria caiu -> Tina', $m, array(1 => 2, 3 => 2));
// 9) uma lista só: nunca troca
check('uma lista só', playlist_fallback_map(L(array(1 => 'offline')), array()), array());
// 10) lista apagada no painel some do mapa
check('lista apagada', playlist_fallback_map(L(array(2 => 'online', 3 => 'online')), array(1 => 2)), array());
// 11) lista por URL (status vazio) conta como conectando
check('lista por URL conta como ativa', playlist_fallback_map(L(array(1 => 'offline', 2 => '')), array()), array(1 => 2));

echo $fail ? "FALHOU ($fail)\n" : "fallback ok\n";
exit($fail ? 1 : 0);
