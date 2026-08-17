<?php
return [

    'educacao' => [
        'table' => 'educacao_foto',
        'foreign_key' => 'educacao_id',
        'mediapath' => 'educacao',
        'galeria' => true,
        'cortar' => false,
        'big' => true,
        'big_width' => 1920,
        'big_height' => 1440,
        'thumb' => false,
        'thumb_width' => 300,
        'thumb_height' => 300,
        'mini' => false,
        'mini_width' => 60,
        'mini_height' => 60,
    ],

    'noticia' => [
        'table' => 'noticia_foto',
        'foreign_key' => 'noticia_id',
        'mediapath' => 'noticia',
        'galeria' => true,
        'cortar' => false,
        'big' => true,
        'big_width' => 1920,
        'big_height' => 1440,
        'thumb' => false,
        'thumb_width' => 300,
        'thumb_height' => 300,
        'mini' => false,
        'mini_width' => 60,
        'mini_height' => 60,
    ],

    'artigo' => [
        'table' => 'noticia_foto',
        'foreign_key' => 'noticia_id',
        'mediapath' => 'noticia',
        'galeria' => true,
        'cortar' => false,
        'big' => true,
        'big_width' => 1920,
        'big_height' => 1440,
        'thumb' => false,
        'thumb_width' => 300,
        'thumb_height' => 300,
        'mini' => false,
        'mini_width' => 60,
        'mini_height' => 60,
    ],

    'empresa' => [
        'table' => 'empresa_fotos',
        'foreign_key' => null,
        'mediapath' => 'empresa',
        'galeria' => true,
        'cortar' => false,
        'big' => true,
        'big_width' => 1920,
        'big_height' => 1440,
        'thumb' => false,
        'thumb_width' => 300,
        'thumb_height' => 300,
        'mini' => false,
        'mini_width' => 60,
        'mini_height' => 60,

    ]

];
