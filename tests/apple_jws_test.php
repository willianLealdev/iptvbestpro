<?php
// Teste automático (GitHub Actions) da conferência das compras da Apple: monta uma corrente de certificados
// de teste (raiz -> intermediário -> folha), assina uma transação como a Apple faz (ES256) e confere.
if (php_sapi_name() !== 'cli') exit;
require __DIR__.'/../includes/apple_iap_helper.php';

function mk($dn, $signKey, $signCert, $curve, $days = 30){
    $key = openssl_pkey_new(array('private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => $curve));
    $csr = openssl_csr_new($dn, $key, array('digest_alg' => 'sha256'));
    $cert = openssl_csr_sign($csr, $signCert, $signKey ?: $key, $days, array('digest_alg' => 'sha256'), random_int(1, 1 << 30));
    openssl_x509_export($cert, $pem);
    return array($key, $pem);
}
function b64u($s){ return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function der_of($pem){ return base64_encode(base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem))); }
// DER (openssl_sign) -> JOSE r||s
function jose($der){
    $o = 2; if (ord($der[1]) & 0x80) $o += ord($der[1]) & 0x7f;
    $lr = ord($der[$o + 1]); $r = substr($der, $o + 2, $lr); $o += 2 + $lr;
    $ls = ord($der[$o + 1]); $s2 = substr($der, $o + 2, $ls);
    return str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT).str_pad(ltrim($s2, "\x00"), 32, "\x00", STR_PAD_LEFT);
}

list($rk, $rp) = mk(array('commonName' => 'Teste Raiz'), null, null, 'secp384r1');
list($ik, $ip) = mk(array('commonName' => 'Teste Intermediario'), $rk, $rp, 'secp384r1');
list($lk, $lp) = mk(array('commonName' => 'Teste Folha'), $ik, $ip, 'prime256v1');

$fail = 0;
for ($n = 0; $n < 40; $n++) { // várias assinaturas: cobre r/s com zero ou bit alto no começo
    $hdr = b64u(json_encode(array('alg' => 'ES256', 'x5c' => array(der_of($lp), der_of($ip), der_of($rp)))));
    $pl = b64u(json_encode(array('bundleId' => APPLE_BUNDLE_ID, 'productId' => APPLE_PRODUCT_YEAR, 'n' => $n)));
    openssl_sign($hdr.'.'.$pl, $der, $lk, OPENSSL_ALGO_SHA256);
    $jws = $hdr.'.'.$pl.'.'.b64u(jose($der));
    $ok = apple_jws_verify($jws, $rp);
    if (!$ok || $ok['n'] !== $n) { echo "falhou a assinatura valida $n\n"; $fail++; }
    // conteúdo alterado tem que ser recusado
    $bad = $hdr.'.'.b64u(json_encode(array('bundleId' => APPLE_BUNDLE_ID, 'productId' => APPLE_PRODUCT_YEAR, 'n' => $n + 1))).'.'.b64u(jose($der));
    if (apple_jws_verify($bad, $rp) !== false) { echo "aceitou conteudo alterado $n\n"; $fail++; }
}
// corrente que não termina na raiz da Apple é recusada
if (apple_jws_verify($jws) !== false) { echo "aceitou raiz que nao e da Apple\n"; $fail++; }
// a raiz guardada é mesmo a Apple Root CA - G3
$fp = strtoupper(openssl_x509_fingerprint(apple_root_g3_pem(), 'sha256'));
if ($fp !== '63343ABFB89A6A03EBB57E9B3F5FA7BE7C4F5C756F3017B3A8C488C3653E9179') { echo "raiz da Apple errada: $fp\n"; $fail++; }
echo $fail ? "FALHOU ($fail)\n" : "apple_jws ok\n";
exit($fail ? 1 : 0);
