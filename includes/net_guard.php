<?php
/**
 * Proteção contra SSRF: o painel só busca endereços PÚBLICOS da internet.
 * Bloqueia localhost, redes privadas (10.x, 172.16-31.x, 192.168.x), link-local (169.254.x, inclui metadados de nuvem),
 * CGNAT e IPv6 interno — inclusive quando um servidor IPTV redireciona para um desses endereços.
 */
function net_ip_is_public($ip){
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
        && !preg_match('/^(100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|0\.|127\.|169\.254\.)/', $ip)
        && !preg_match('/^(::1|::ffff:|fc|fd|fe80)/i', $ip);
}

// Devolve o IP público do host (ou '' se não resolve ou se algum IP dele é interno)
function net_public_ip($host){
    $host = trim((string)$host, '[]');
    if ($host === '') return '';
    if (filter_var($host, FILTER_VALIDATE_IP)) return net_ip_is_public($host) ? $host : '';
    $ips = @gethostbynamel($host);
    if (!$ips) return '';
    foreach ($ips as $ip) if (!net_ip_is_public($ip)) return '';
    return $ips[0];
}

// URL http/https de host público? (portas comuns de web; bloqueia portas de serviços internos)
function net_url_is_public($url){
    $p = @parse_url((string)$url);
    if (!$p || empty($p['host']) || !isset($p['scheme']) || !in_array(strtolower($p['scheme']), array('http', 'https'), true)) return false;
    if (!empty($p['user']) || !empty($p['pass'])) return false;
    if (isset($p['port']) && ((int)$p['port'] < 1 || (int)$p['port'] > 65535 || in_array((int)$p['port'], array(22, 25, 3306, 5432, 6379, 11211, 27017, 9200), true))) return false;
    return net_public_ip($p['host']) !== '';
}
