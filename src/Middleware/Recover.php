<?php

declare(strict_types=1);

namespace Quill\Middleware;

use Quill\Contracts\MiddlewareInterface;
use Quill\Contracts\ConfigurableMiddleware;
use Quill\Http\Request;
use Quill\Http\HttpResponse;

class Recover implements MiddlewareInterface
{
    use ConfigurableMiddleware;

    /** @var array<string, mixed> */
    protected array $config;

    protected static function defaults(): array
    {
        return [
            'enable_stacktrace' => false,
            'custom_handler' => null, // closure function (\Throwable $e, Request $req): mixed
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
        try {
            return $next($request);
        } catch (\Throwable $e) {
            if (isset($this->config['custom_handler']) && is_callable($this->config['custom_handler'])) {
                return ($this->config['custom_handler'])($e, $request);
            }

            $error = ['status' => 500, 'error' => 'Internal Server Error'];
            
            if ($this->config['enable_stacktrace']) {
                $error['message'] = $e->getMessage();
                $error['file'] = $e->getFile();
                $error['line'] = $e->getLine();
                $error['trace'] = $e->getTraceAsString();
            }

            return new HttpResponse($error, 500);
        }
    }
}
