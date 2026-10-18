<?php

use craft\helpers\App;
use modules\demo\Module;

return [
    'id' => App::env('CRAFT_APP_ID') ?: 'CraftCMS',
    'modules' => [
        'demo' => Module::class,
    ],
    'bootstrap' => ['demo'],
];
