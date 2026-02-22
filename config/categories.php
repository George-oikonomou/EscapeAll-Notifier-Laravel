<?php

return [
    // Master list of category definitions used for seeding.
    // Add new categories by appending to this list (do not change existing codes).
    'items' => [
        // We keep only language-independent data here. Translations live in
        // resources/lang/*/categories.php and are looked up by `slug`.
        ['code' => 0,  'slug' => 'actor',                'icon' => 'fas5 fa5-mask text-orange',         'emoji' => '🎭'],
        ['code' => 1,  'slug' => 'no-actor',             'icon' => 'fas5 fa5-mask',                     'emoji' => '🚫🎭'],
        ['code' => 2,  'slug' => 'horror',               'icon' => 'fas5 fa5-skull text-danger',        'emoji' => '💀'],
        ['code' => 3,  'slug' => 'non-horror',           'icon' => null,                                'emoji' => '😊'],
        ['code' => 4,  'slug' => 'psychological-thriller','icon' => 'fa fa-heartbeat text-danger',      'emoji' => '🧠'],
        ['code' => 5,  'slug' => 'for-children',         'icon' => 'fa fa-child text-orange',           'emoji' => '👶'],
        ['code' => 6,  'slug' => 'kids-friendly',        'icon' => 'fa fa-child',                       'emoji' => '👨‍👩‍👧'],
        ['code' => 7,  'slug' => 'action',               'icon' => 'fa fa-bolt text-gold',              'emoji' => '⚡'],
        ['code' => 8,  'slug' => 'running',              'icon' => 'fas5 fa5-running',                  'emoji' => '🏃'],
        ['code' => 9,  'slug' => 'role-playing',         'icon' => 'fa fa-id-badge',                    'emoji' => '🎲'],
        ['code' => 10, 'slug' => 'adults-only',          'icon' => 'fa fa-ban text-danger',             'emoji' => '🔞'],
        ['code' => 11, 'slug' => 'comedy',               'icon' => 'fa fa-smile-o',                     'emoji' => '😂'],
        ['code' => 12, 'slug' => 'sci-fi',               'icon' => 'fas5 fa5-user-astronaut text-purple','emoji' => '🚀'],
        ['code' => 13, 'slug' => 'virtual-reality',      'icon' => 'fas5 fa5-glasses',                  'emoji' => '🥽'],
        ['code' => 14, 'slug' => 'online',               'icon' => 'fa fa-wifi',                        'emoji' => '🌐'],
        ['code' => 15, 'slug' => 'outdoor',              'icon' => 'fa fa-sun-o text-info',             'emoji' => '☀️'],
        ['code' => 16, 'slug' => 'has-score',            'icon' => 'fa fa-trophy text-orange',          'emoji' => '🏆'],
        ['code' => 17, 'slug' => 'christmas',            'icon' => 'fas5 fa5-tree text-success',        'emoji' => '🎄'],
        ['code' => 18, 'slug' => 'wheelchair-accessible','icon' => 'fa fa-wheelchair',                  'emoji' => '♿'],
        ['code' => 19, 'slug' => 'coming-soon',          'icon' => 'fa fa-hourglass-half',              'emoji' => '🔜'],
        ['code' => 20, 'slug' => 'youth-pass',           'icon' => 'fa fa-hashtag',                     'emoji' => '🎫'],
        ['code' => 21, 'slug' => 'portable-game',        'icon' => 'fa fa-exchange',                    'emoji' => '📱'],
    ],
];
