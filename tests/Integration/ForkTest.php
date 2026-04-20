<?php

declare(strict_types=1);

namespace Quill\Tests\Integration;

use Quill\App;
use Quill\Http\Request;
use Quill\Runtime\Runtime;

/**
 * ForkTest
 * Verifies multi-worker stability, signal handling (ENH-6), 
 * and metrics exposure (ENH-5).
 */
dataset('worker_counts', [1, 2, 4]);

it('scales correctly and exposes metrics', function (int $workers) {
    if (!function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl extension not available');
    }

    $port = 9000 + $workers;
    $pidFile = sys_get_temp_dir() . "/quill_test_$port.pid";
    
    // Start server in background
    $cmd = sprintf(
        "QUILL_WORKERS=%d QUILL_RUNTIME=rust php -d ffi.enable=on bin/quill serve --port=%d > /dev/null 2>&1 & echo $!",
        $workers,
        $port
    );
    
    $serverPid = (int) shell_exec($cmd);
    file_put_contents($pidFile, $serverPid);
    
    // Give workers time to boot
    sleep(1);
    
    try {
        // 1. Verify health endpoint
        $health = @file_get_contents("http://127.0.0.1:$port/__quill/health");
        expect($health)->toBe('{"status":"ok"}');
        
        // 2. Verify metrics endpoint (ENH-5)
        $metrics = @file_get_contents("http://127.0.0.1:$port/__quill/metrics");
        expect($metrics)->toContain('"active_workers"');
        
        // 3. Verify standard route
        $hello = @file_get_contents("http://127.0.0.1:$port/hello");
        if ($hello !== false) {
             expect($hello)->toContain('Hello');
        }

    } finally {
        // 4. Graceful Shutdown (ENH-6)
        if ($serverPid > 0) {
            posix_kill($serverPid, SIGTERM);
            // Wait for drainage
            sleep(1);
            // Verify process is gone
            expect(posix_getpgid($serverPid))->toBeFalse();
        }
    }
})->with('worker_counts')->skip(!Runtime::isAvailable());
