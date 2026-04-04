<?php

declare(strict_types=1);

namespace Quill\Contracts;

use Quill\App;

interface PluginInterface
{
    /** Called once during App::boot() — register routes, middleware, services. */
    public function register(App $app): void;

    /** Called during App::boot() after all plugins are registered. */
    public function boot(App $app): void;
}
