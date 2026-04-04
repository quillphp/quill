<?php

declare(strict_types=1);

namespace Quill\Middleware;

use Quill\Contracts\MiddlewareInterface;
use Quill\Contracts\ConfigurableMiddleware;
use Quill\Request;
use Quill\HttpResponse;

class RequestId implements MiddlewareInterface
{
    use ConfigurableMiddleware;

    /** @var array<string, mixed> */
    protected array $config;

    protected static function defaults(): array
    {
        return [
            'header' => 'X-Request-ID',
            'generator' => null, // Optional closure for ID generation
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(static::defaults(), $config);
    }

    public function handle(Request $request, callable $next): mixed
    {
        /** @var callable|null $generator */
        $generator = $this->config['generator'];
        /** @var string $header */
        $header = $this->config['header'];

        $id = $request->header($header) 
            ?? ($generator ? $generator() : bin2hex(random_bytes(16)));

        // Inject into request context so downstream handlers can access it
        $request->set('request_id', $id);

        $response = $next($request);

        // Add to HttpResponse if native, or via raw header if standard response
        if ($response instanceof HttpResponse) {
            $response->header($header, (string)$id);
        } else {
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                header("{$header}: {$id}");
            }
        }

        return $response;
    }
}
