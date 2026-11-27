<?php

/*
 * S08: biblioteca de mídia. Todos os limites, o orçamento de memória e o catálogo de variantes ficam aqui, num só lugar
 * (nenhum Controller nem componente repete esses números). Os valores de memória e de tempo foram fixados a partir da
 * linha de base medida na seção 35 do plano 002.
 */
return [
    // Discos (config/filesystems.php): original privado e derivados processados, ambos fora de `public/`.
    'disks' => [
        'originals' => 'media-originals',
        'variants' => 'media-variants',
    ],

    // Barreiras antes de decodificar, nesta ordem: bytes, tipo pelo conteúdo, dimensões, estimativa de memória.
    'limits' => [
        'max_bytes' => 5 * 1024 * 1024,
        'max_side' => 12000,
        'max_megapixels' => 20,
    ],

    // Estimativa de memória da decodificação no GD: largura x altura x bytes por pixel x fator de cópias, mais a base.
    'memory' => [
        'bytes_per_pixel' => 4,
        'copies_factor' => 1.0,
        // Base medida de uma requisição (framework e conexões, cerca de 31 MB) mais o trabalho das variantes maiores, com folga.
        'overhead_bytes' => 40 * 1024 * 1024,
        // Fração do `memory_limit` real que uma requisição de envio pode usar.
        'budget_fraction' => 0.9,
    ],

    // Orçamento de tempo de parede de um envio, em segundos. Estourar aborta e remove arquivos parciais.
    'time_budget_seconds' => 5,

    // Resíduos de armazenamento só são apagados depois deste tempo, para não colidir com um envio em andamento.
    'orphan_grace_minutes' => 60,

    'quality' => ['jpeg' => 82, 'webp' => 82, 'png_compression' => 6],

    // Catálogo fechado de usos e das larguras nominais de cada um. O cliente nunca envia dimensões. Sem ampliação: uma
    // largura maior que a do original não é gerada, e a menor largura nominal vale no máximo a do original.
    'purposes' => [
        'logo' => ['label' => 'Logotipo', 'widths' => [160, 320, 640]],
        'banner' => ['label' => 'Banner', 'widths' => [640, 1280, 1920]],
        'gallery' => ['label' => 'Imagem de galeria', 'widths' => [320, 960, 1600]],
        'employee_photo' => ['label' => 'Foto profissional', 'widths' => [160, 400, 800]],
        'og_image' => ['label' => 'Imagem de compartilhamento', 'widths' => [1200]],
    ],
];
