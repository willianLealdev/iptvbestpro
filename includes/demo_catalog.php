<?php
/**
 * Conteúdo de demonstração (revisão das lojas e testes): somente conteúdo livre/licenciado para teste.
 *  - Big Buck Bunny e Tears of Steel: filmes abertos da Blender Foundation (Creative Commons BY)
 *  - Streams de teste públicos da Apple e da Akamai (feitos para testar players)
 * Nenhum canal, filme ou marca comercial.
 */
define('DEMO_USER', 'demo');
define('DEMO_PASS', 'bestpro');

function demo_streams(){
    return array(
        'akamai_live' => 'https://devstreaming-cdn.apple.com/videos/streaming/examples/bipbop_4x3/bipbop_4x3_variant.m3u8', // Apple (teste livre): o stream da Akamai não tocava na Roku
        'apple_adv'   => 'https://devstreaming-cdn.apple.com/videos/streaming/examples/img_bipbop_adv_example_fmp4/master.m3u8',
        'apple_16x9'  => 'https://devstreaming-cdn.apple.com/videos/streaming/examples/bipbop_16x9/bipbop_16x9_variant.m3u8',
        'bbb'         => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
        'tos'         => 'https://demo.unified-streaming.com/k8s/features/stable/video/tears-of-steel/tears-of-steel.ism/.m3u8',
    );
}

function demo_img($base, $file){ return rtrim($base, '/').'/assets/images/demo/'.$file; }

function demo_catalog($base){
    $s = demo_streams();
    return array(
        'live_cats' => array(array('category_id' => '1', 'category_name' => 'Demo Live', 'parent_id' => 0)),
        'live' => array(
            array('id' => 1, 'name' => 'Demo Channel 1', 'logo' => 'ch1.png', 'src' => $s['akamai_live']),
            array('id' => 2, 'name' => 'Demo Channel 2', 'logo' => 'ch2.png', 'src' => $s['apple_adv']),
            array('id' => 3, 'name' => 'Demo Channel 3', 'logo' => 'ch3.png', 'src' => $s['bbb']),
            array('id' => 4, 'name' => 'Demo Channel 4', 'logo' => 'ch4.png', 'src' => $s['tos']),
        ),
        'vod_cats' => array(array('category_id' => '11', 'category_name' => 'Open Movies', 'parent_id' => 0)),
        'vod' => array(
            array('id' => 101, 'name' => 'Big Buck Bunny', 'img' => 'm_bbb.jpg', 'src' => $s['bbb'], 'year' => '2008', 'genre' => 'Animation / Comedy', 'dir' => 'Sacha Goedegebure', 'mins' => 10,
                  'plot' => 'Open movie by the Blender Foundation (Creative Commons BY). A giant rabbit gets his revenge on three bullying rodents.'),
            array('id' => 102, 'name' => 'Tears of Steel', 'img' => 'm_tos.jpg', 'src' => $s['tos'], 'year' => '2012', 'genre' => 'Science Fiction', 'dir' => 'Ian Hubert', 'mins' => 12,
                  'plot' => 'Open movie by the Blender Foundation (Creative Commons BY). A group of scientists tries to save the world from robots.'),
            array('id' => 103, 'name' => 'Test Pattern', 'img' => 'm_test.jpg', 'src' => $s['apple_16x9'], 'year' => '2024', 'genre' => 'Test', 'dir' => 'Apple sample stream', 'mins' => 30,
                  'plot' => 'Public HLS sample stream used to test video players.'),
        ),
        'series_cats' => array(array('category_id' => '21', 'category_name' => 'Open Series', 'parent_id' => 0)),
        'series' => array(
            array('id' => 201, 'name' => 'Open Movie Collection', 'img' => 's_open.jpg', 'genre' => 'Animation', 'plot' => 'Short open movies released under Creative Commons.',
                  'episodes' => array(array('id' => 301, 'title' => 'Big Buck Bunny', 'src' => $s['bbb']), array('id' => 302, 'title' => 'Tears of Steel', 'src' => $s['tos']))),
            array('id' => 202, 'name' => 'Player Test Streams', 'img' => 's_test.jpg', 'genre' => 'Test', 'plot' => 'Public sample streams for testing playback.',
                  'episodes' => array(array('id' => 303, 'title' => 'Sample stream 16x9', 'src' => $s['apple_16x9']), array('id' => 304, 'title' => 'Advanced sample stream', 'src' => $s['apple_adv']))),
        ),
    );
}

// Endereço real de um item (live / movie / series) pelo id
function demo_source($base, $type, $id){
    $c = demo_catalog($base);
    $id = (int)$id;
    if ($type == 'live') { foreach ($c['live'] as $x) if ($x['id'] == $id) return $x['src']; }
    if ($type == 'movie') { foreach ($c['vod'] as $x) if ($x['id'] == $id) return $x['src']; }
    if ($type == 'series') { foreach ($c['series'] as $se) foreach ($se['episodes'] as $e) if ($e['id'] == $id) return $e['src']; }
    return '';
}
