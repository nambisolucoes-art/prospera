<?php

return [
    /*
     * S06: catálogo fechado de fontes da identidade visual, as mesmas duas que o tema Bold carrega (globals.css). Nenhuma URL de fonte nem CSS é aceito (seção 09.4).
     */
    'fonts' => [
        'noto-sans' => 'Noto Sans',
        'source-sans-pro' => 'Source Sans Pro',
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
