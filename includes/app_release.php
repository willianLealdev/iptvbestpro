<?php
/**
 * Versão atual do app para download direto (página /download e link curto /apk).
 * A cada nova versão: troque o arquivo em downloads/ e atualize estes dados.
 */
function app_release(){
    return array(
        'version'      => '1.0.9',
        'version_code' => 10,
        'date'         => '2026-10-04',
        'file'         => 'downloads/iptvbestpro.apk',
        'min_android'  => '6.0',
    );
}
