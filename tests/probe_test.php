<?php
// Teste automático (GitHub Actions): resposta do servidor IPTV -> lista ativa, vencida/desativada (migrada) ou recusada
if (php_sapi_name() !== 'cli') exit;
$src = str_replace("\r\n", "\n", file_get_contents(__DIR__.'/../includes/playlist_helper.php'));
function dns_normalize($u){ return rtrim(strtolower(trim($u)), '/'); }
preg_match('/function playlist_probe_result\(.*?\n}\n/s', $src, $m);
eval($m[0]);
$fail = 0;
function check($name, $got, $want) { global $fail; if ($got !== $want) { echo "FALHOU: $name -> ".var_export($got, true)."\n"; $fail++; } else echo "ok: $name\n"; }
$j = function ($status, $exp) { return json_encode(array('user_info' => array('auth' => 1, 'status' => $status, 'exp_date' => $exp), 'server_info' => array())); };
$r = playlist_probe_result($j('Active', time() + 86400 * 30), 200);
check('ativa', array($r['result'], $r['active']), array('ok', true));
$r = playlist_probe_result($j('Expired', time() - 86400), 200);
check('vencida no servidor antigo', array($r['result'], $r['active']), array('ok', false));
$r = playlist_probe_result($j('Disabled', time() + 86400), 200);
check('desativada no servidor antigo', array($r['result'], $r['active']), array('ok', false));
$r = playlist_probe_result($j('Active', null), 200);
check('sem vencimento (ilimitada)', array($r['result'], $r['active']), array('ok', true));
$r = playlist_probe_result(json_encode(array('user_info' => array('auth' => 0))), 200);
check('recusou', $r['result'], 'auth_fail');
$r = playlist_probe_result(false, 0);
check('fora do ar', $r['result'], 'unreachable');
exit($fail ? 1 : 0);
