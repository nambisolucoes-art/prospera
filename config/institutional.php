<?php

return [
    /*
     * S06: catálogo fechado de fontes da identidade visual. Nenhuma URL de fonte nem CSS é aceito (seção 09.4).
     */
    'fonts' => [
        'noto-sans' => 'Noto Sans',
        'source-sans-pro' => 'Source Sans Pro',
        'open-sans' => 'Open Sans',
        'roboto' => 'Roboto',
        'lato' => 'Lato',
        'montserrat' => 'Montserrat',
        'merriweather' => 'Merriweather',
        'playfair-display' => 'Playfair Display',
    ],

    // Redes sociais aceitas e os domínios permitidos para o link de cada uma.
    'social_networks' => [
        'instagram' => ['label' => 'Instagram', 'hosts' => ['instagram.com']],
        'facebook' => ['label' => 'Facebook', 'hosts' => ['facebook.com', 'fb.com']],
        'linkedin' => ['label' => 'LinkedIn', 'hosts' => ['linkedin.com']],
        'youtube' => ['label' => 'YouTube', 'hosts' => ['youtube.com', 'youtu.be']],
        'tiktok' => ['label' => 'TikTok', 'hosts' => ['tiktok.com']],
        'x' => ['label' => 'X', 'hosts' => ['x.com', 'twitter.com']],
    ],
];
