<?php
// ops: lijst van [naam, waarde]. Ondersteund: grayscale, sepia, saturate,
// brightness, contrast, hue-rotate (graden). Volgorde is betekenisvol.
return [
    ['id' => 'origineel', 'label' => 'Origineel', 'ops' => []],
    ['id' => 'zwartwit',  'label' => 'Zwart-wit', 'ops' => [['grayscale', 1], ['contrast', 1.05]]],
    ['id' => 'sepia',     'label' => 'Sepia',     'ops' => [['sepia', 0.75], ['contrast', 1.02]]],
    ['id' => 'warm',      'label' => 'Warm',      'ops' => [['sepia', 0.28], ['saturate', 1.25], ['brightness', 1.03]]],
    ['id' => 'koel',      'label' => 'Koel',      'ops' => [['saturate', 0.9], ['hue-rotate', 12], ['brightness', 1.02]]],
    ['id' => 'fade',      'label' => 'Fade',      'ops' => [['contrast', 0.85], ['brightness', 1.08], ['saturate', 0.85]]],
];
