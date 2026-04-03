<?php

declare(strict_types=1);

use Handlers\UserHandler;

/** @var \Quill\App $app */

$app->resource('/users', UserHandler::class);

$app->group('/api', function ($app) {
    $app->group('/v1', function ($app) {
        // ... v1 routes ...
    });
});
