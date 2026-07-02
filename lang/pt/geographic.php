<?php

// TODO: translate to Portuguese

return [
    // Full level names (LocatableType::label(), ExploreCommunities::currentLevel())
    'level' => [
        'country'               => 'Country',
        'province'              => 'Province',
        'district_municipality' => 'District Municipality',
        'local_municipality'    => 'Local Municipality',
        'main_place'            => 'Main Place',
        'city'                  => 'City',
        'national'              => 'National',
    ],

    // Short badge on community cards (CommunityCard::levelBadge())
    'badge_card' => [
        'national'   => 'National',
        'provincial' => 'Provincial',
        'dm'         => 'DM',
        'lm'         => 'LM',
        'main_place' => 'Main Place',
        'metro'      => 'Metro',
        'city'       => 'City',
    ],

    // Badge on the location column-browser list items (column-browser badgeFor())
    'badge_list' => [
        'country'            => 'Country',
        'province'           => 'Province',
        'dm'                 => 'DM',
        'local_municipality' => 'Local Municipality',
        'main_place'         => 'Main Place',
        'metro'              => 'Metro',
        'city'               => 'City',
    ],
];
