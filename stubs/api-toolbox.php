<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Each feature maps to one HTTP standard. A feature that is not listed here
    | registers nothing at all. Options are passed inline to the feature.
    |
    */

    'features' => [
        Features::problemDetails([
            // Where problem type slugs resolve to. Point this at your docs.
            'type_base_uri' => '/problems',
            // Paths that always get problem details, whatever the request's Accept header.
            'paths' => ['api/*'],
        ]),
    ],

];
