<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Features;

/*
 * The defaults merged in when the application has not published its own copy.
 *
 * Never pass options here. This file is required on every boot by mergeConfigFrom(), even when
 * the application has published its own config, so any option set here would overwrite the
 * application's. Fortify splits its config the same way, and for the same reason.
 */

return [
    'features' => [
        Features::problemDetails(),
        Features::deprecation(),
        Features::conditionalRequests(),
        Features::pagination(),
    ],
];
