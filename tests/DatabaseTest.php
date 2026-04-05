<?php

declare(strict_types=1);

namespace Quill\Tests;

use PHPUnit\Framework\TestCase;
use Quill\Database\EloquentBridge;
use Quill\Database\DoctrineBridge;
use Quill\App;
use PHPUnit\Framework\Attributes\RequiresPhp;

class DatabaseTest extends TestCase
{
    #[RequiresPhp('8.3')]
    public function testEloquentBridgeBoot(): void
    {
        if (!class_exists('Illuminate\Database\Capsule\Manager')) {
            $this->markTestSkipped('Eloquent not installed.');
        }

        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ];

        $app = new App();
        $capsule = EloquentBridge::boot($config, $app);

        $this->assertInstanceOf(\Illuminate\Database\Capsule\Manager::class, $capsule);
        $this->assertTrue($app->has('db'));
    }

    public function testDoctrineBridgeBoot(): void
    {
        if (!class_exists('Doctrine\ORM\EntityManager')) {
            $this->markTestSkipped('Doctrine ORM not installed.');
        }

        $config = [
            'paths' => [__DIR__],
            'dev_mode' => true,
            'connection' => [
                 'driver' => 'pdo_sqlite',
                 'memory' => true,
            ]
        ];

        $app = new App();
        $em = DoctrineBridge::boot($config, $app);

        $this->assertInstanceOf(\Doctrine\ORM\EntityManager::class, $em);
        $this->assertTrue($app->has(\Doctrine\ORM\EntityManager::class));
    }
}
