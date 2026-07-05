<?php
// ops: lijst van [naam, waarde]. Ondersteund: grayscale, sepia, saturate,
// brightness, contrast, hue-rotate (graden). Volgorde is betekenisvol.
// fx (optioneel): special effects bij het bakken — vignette 0..1, grain 0..1.
return [
    ['id' => 'origineel', 'label' => 'Origineel', 'ops' => []],
    ['id' => 'helder',    'label' => 'Helder',    'ops' => [['contrast', 1.15], ['saturate', 1.3]]],
    ['id' => 'warm',      'label' => 'Warm',      'ops' => [['sepia', 0.28], ['saturate', 1.25], ['brightness', 1.03]]],
    ['id' => 'zomer',     'label' => 'Zomer',     'ops' => [['sepia', 0.18], ['saturate', 1.35], ['brightness', 1.06], ['contrast', 1.05]]],
    ['id' => 'koel',      'label' => 'Koel',      'ops' => [['saturate', 0.9], ['hue-rotate', 12], ['brightness', 1.02]]],
    ['id' => 'fade',      'label' => 'Fade',      'ops' => [['contrast', 0.85], ['brightness', 1.08], ['saturate', 0.85]]],
    ['id' => 'retro',     'label' => "Retro '77", 'ops' => [['sepia', 0.28], ['hue-rotate', -8], ['saturate', 1.25], ['brightness', 1.08], ['contrast', 0.92]], 'fx' => ['grain' => 0.3]],
    ['id' => 'film',      'label' => 'Film',      'ops' => [['contrast', 0.92], ['brightness', 1.05], ['saturate', 0.9], ['sepia', 0.12]], 'fx' => ['grain' => 0.35, 'vignette' => 0.3]],
    ['id' => 'sepia',     'label' => 'Sepia',     'ops' => [['sepia', 0.75], ['contrast', 1.02]]],
    ['id' => 'zwartwit',  'label' => 'Zwart-wit', 'ops' => [['grayscale', 1], ['contrast', 1.05]]],
    ['id' => 'noir',      'label' => 'Noir',      'ops' => [['grayscale', 1], ['contrast', 1.22], ['brightness', 0.98]], 'fx' => ['vignette' => 0.45]],
];
