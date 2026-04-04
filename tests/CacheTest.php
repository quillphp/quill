<?php

declare(strict_types=1);

namespace Quill\Tests;

use PHPUnit\Framework\TestCase;
use Quill\Cache\FileCache;
use Quill\Cache\ApcuCache;

class CacheTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = __DIR__ . '/../tmp/cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            $files = glob("$this->cacheDir/*.*");
            array_map('unlink', is_array($files) ? $files : []);
            rmdir($this->cacheDir);
        }
    }

    public function testFileCacheBasics(): void
    {
        $cache = new FileCache($this->cacheDir);
        
        $cache->set('name', 'Quill');
        $this->assertEquals('Quill', $cache->get('name'));

        $cache->delete('name');
        $this->assertNull($cache->get('name'));
        
        $cache->set('hits', 10);
        $this->assertEquals(10, $cache->get('hits'));
        
        $cache->clear();
        $this->assertNull($cache->get('hits'));
    }

    /**
     * @requires extension apcu
     */
    public function testApcuCacheBasics(): void
    {
        if (!function_exists('apcu_store') || !ini_get('apc.enabled')) {
             $this->markTestSkipped('APCu extension not available or disabled.');
        }

        if (PHP_SAPI === 'cli' && !ini_get('apc.enable_cli')) {
             $this->markTestSkipped('APCu is disabled for CLI (apc.enable_cli=0).');
        }

        $cache = new ApcuCache('test_');
        $this->assertTrue($cache->set('foo', 'bar'), 'APCu set() should return true');
        $this->assertEquals('bar', $cache->get('foo'));
        
        $cache->delete('foo');
        $this->assertNull($cache->get('foo'));
    }


    public function testCacheTtl(): void
    {
        $cache = new FileCache($this->cacheDir);
        $cache->set('temp', 'value', 1); // 1 second
        $this->assertEquals('value', $cache->get('temp'));
        
        // Wait for it to expire
        sleep(2);
        $this->assertNull($cache->get('temp'));
    }
}
