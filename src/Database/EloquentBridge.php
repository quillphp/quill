<?php

declare(strict_types=1);

namespace Quill\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Psr\Container\ContainerInterface;

/**
 * Bridge for illuminate/database (Eloquent).
 */
class EloquentBridge
{
    /**
     * Configure and boot Eloquent.
     *
     * @param array<string, mixed> $config Database configuration
     * @param ContainerInterface|null $container Optional PSR-11 container to bind to
     */
    public static function boot(array $config, ?ContainerInterface $container = null): Capsule
    {
        $capsule = new Capsule;

        // 1. Add connections
        if (isset($config['connections'])) {
            foreach ($config['connections'] as $name => $conn) {
                $capsule->addConnection($conn, $name === ($config['default'] ?? 'default') ? 'default' : $name);
            }
        } else {
            $capsule->addConnection($config);
        }

        // 2. Set event dispatcher for model events
        $capsule->setEventDispatcher(new Dispatcher(new Container));

        // 3. Make global
        $capsule->setAsGlobal();

        // 4. Boot ORM
        $capsule->bootEloquent();

        // 5. Register in container if provided
        if ($container && method_exists($container, 'set')) {
            $container->set('db', $capsule->getDatabaseManager());
            $container->set(Capsule::class, $capsule);
        }

        return $capsule;
    }
}
