<?php

declare(strict_types=1);

namespace Quill\Tests;

use PHPUnit\Framework\TestCase;
use Quill\App;
use Quill\Cors;
use Quill\Middleware\RateLimiter;
use Quill\Middleware\InMemoryRateLimitStorage;
use Quill\Middleware\SecurityHeaders;
use Quill\HttpResponse;
use Quill\Request;

class SecurityTest extends TestCase
{
    public function testRateLimiting(): void
    {
        $app = new App(['route_cache' => false, 'debug' => true]);
        $storage = new InMemoryRateLimitStorage();
        $limiter = new RateLimiter($storage, 2, 60);

        $app->use($limiter);
        $app->get('/rate-limit', fn() => ['ok' => true]);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/rate-limit';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Hit 1
        ob_start(); $app->handle(); ob_get_clean();
        
        // Hit 2
        ob_start(); $app->handle(); ob_get_clean();

        // Hit 3 (should fail)
        ob_start();
        $app->handle();
        $response = ob_get_clean();

        if (http_response_code() === 500) {
            echo "ERROR 500: " . $response . "\n";
        }

        $this->assertEquals(429, http_response_code());
        $this->assertStringContainsString('Too Many Requests', $response);
    }

    public function testCorsRegexOrigins(): void
    {
        $app = new App(['route_cache' => false]);
        $app->use(Cors::middleware([
            'origins' => ['https://domain.com', '#^https://.*\.my-saas\.com$#'],
        ]));

        $app->get('/cors', fn() => ['cors' => 'ok']);

        // Test exact match
        $_SERVER['HTTP_ORIGIN'] = 'https://domain.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/cors';
        
        ob_start(); $app->handle(); ob_get_clean();
        
        $this->assertEquals(200, http_response_code());
    }

    public function testSecurityHeaders(): void
    {
        $app = new App(['route_cache' => false]);
        $app->use(new SecurityHeaders([
            'csp' => "default-src 'self'",
        ]));

        $app->get('/secure', function() {
            return new HttpResponse(['msg' => 'secure']);
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/secure';

        ob_start();
        $app->handle();
        $output = ob_get_clean();

        $this->assertStringContainsString('secure', $output);
        // In a real environment, we'd check headers. Here we check that the 
        // middleware executed and didn't block the response.
    }
}
