<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Quill\App;

if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->safeLoad();
}

$app = new App([
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'env'   => $env = getenv('APP_ENV') ?: 'dev',
    'docs'  => filter_var(getenv('APP_DOCS') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'route_cache' => $env === 'prod' ? sys_get_temp_dir() . '/quill_routes.cache' : false,
]);

require __DIR__ . '/../routes.php';

$app->run();
