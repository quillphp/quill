<?php

declare(strict_types=1);

namespace Quill\Database;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Psr\Container\ContainerInterface;

/**
 * Bridge for Doctrine ORM / DBAL.
 */
class DoctrineBridge
{
    /**
     * Configure and boot Doctrine EntityManager.
     *
     * @param array<string, mixed> $config Database and ORM configuration
     * @param ContainerInterface|null $container Optional PSR-11 container to bind to
     */
    public static function boot(array $config, ?ContainerInterface $container = null): EntityManager
    {
        $setup = ORMSetup::createAttributeMetadataConfiguration(
            paths: $config['paths'] ?? [getcwd() . '/src'],
            isDevMode: $config['dev_mode'] ?? true,
        );

        $connection = DriverManager::getConnection(
            $config['connection'],
            $setup
        );

        $entityManager = new EntityManager($connection, $setup);

        // Register in container if provided
        if ($container && method_exists($container, 'set')) {
            $container->set(EntityManager::class, $entityManager);
            $container->set(\Doctrine\ORM\EntityManagerInterface::class, $entityManager);
        }

        return $entityManager;
    }
}
