<?php
/**
 * Arquivo de início do Media Station X: o cliente digita o endereço deste servidor em
 * Configurações > Parâmetro de início e o MSX lê este arquivo (/msx/start.json).
 */
require_once(__DIR__."/msx_lib.php");

msx_out(array(
    'name' => 'IPTV Best Pro',
    'version' => MSX_VERSION,
    'parameter' => 'menu:'.msx_base().'/msx/app.json?r=open',
    'welcome' => 'none',
    'launcher' => array('icon' => 'blank', 'image' => msx_logo(), 'color' => 'msx-black-soft'),
));
