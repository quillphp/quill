<?php

declare(strict_types=1);

namespace Quill\Concerns;

use Quill\Request;
use Quill\Response;
use Quill\HtmlResponse;
use Quill\HttpResponse;
use Quill\ValidationException;

/**
 * Swoole-specific execution logic for the App class.
 */
trait HandlesSwoole
{
    /**
     * Swoole HTTP server run loop.
     */
    protected function runWithSwoole(): void
    {
        $workers    = (int)(getenv('SWOOLE_WORKERS') ?: (function_exists('swoole_cpu_num') ? swoole_cpu_num() : 4));
        $port       = (int)(getenv('SWOOLE_PORT') ?: 8080);
        $gcInterval = (int)(getenv('QUILL_GC_INTERVAL') ?: 500);
        
        $swooleProcess = defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 0;
        $swooleBase    = defined('SWOOLE_BASE')    ? SWOOLE_BASE    : 1;
        $mode = (getenv('SWOOLE_MODE') === 'base') ? $swooleBase : $swooleProcess;

        $server = new \Swoole\Http\Server('0.0.0.0', $port, $mode);
        $server->set([
            'worker_num'               => $workers,
            'max_request'              => (int)(getenv('SWOOLE_MAX_REQUEST') ?: 0),
            'http_compression'         => false,
            'open_http2_protocol'      => false,
            'backlog'                  => 8192,
            'max_conn'                 => 100000,
            'heartbeat_check_interval' => -1,
            'buffer_output_size'       => 4 * 1024 * 1024,
            'socket_buffer_size'       => 8 * 1024 * 1024,
            'enable_coroutine'         => false,
        ]);

        $server->on('start', static function () use ($workers, $port, $mode, $swooleBase): void {
            $modeName = ($mode === $swooleBase) ? 'BASE' : 'PROCESS';
            echo "Quill Swoole server ({$modeName}): {$workers} workers on 0.0.0.0:{$port}\n";
        });

        $app    = $this;
        $gcTick = 0;

        $server->on('request', static function (
            \Swoole\Http\Request  $swReq,
            \Swoole\Http\Response $swRes
        ) use ($app, $gcInterval, &$gcTick): void {
            $app->handleSwooleRequest($swReq, $swRes);

            if ($gcInterval > 0 && ++$gcTick >= $gcInterval) {
                gc_collect_cycles();
                $gcTick = 0;
            }
        });

        $server->start();
    }

    /**
     * Zero-copy Swoole request handler.
     */
    protected function handleSwooleRequest(
        \Swoole\Http\Request  $swReq,
        \Swoole\Http\Response $swRes
    ): void {
        $serverInfo = $swReq->server ?? [];
        $_SERVER['REQUEST_METHOD']  = strtoupper($serverInfo['request_method'] ?? 'GET');
        $_SERVER['REQUEST_URI']     = $serverInfo['request_uri'] ?? '/';
        $_SERVER['REMOTE_ADDR']     = $serverInfo['remote_addr'] ?? '127.0.0.1';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_GET = $swReq->get ?? [];

        $method         = $_SERVER['REQUEST_METHOD'];
        $dispatchMethod = ($method === 'HEAD') ? 'GET' : $method;

        $phpReq = new Request();
        if (!empty($swReq->header)) {
            $phpReq = $phpReq->withSwooleHeaders($swReq->header);
        }
        if ($raw = $swReq->rawContent()) {
            $phpReq = $phpReq->withInput($raw);
        }

        try {
            if (empty($this->middlewares)) {
                $route = $this->router->dispatch($dispatchMethod, $phpReq->path());

                if (!$route->isFound()) {
                    if ($route->isMethodNotAllowed() && $method === 'OPTIONS') {
                        $allowed = array_merge($route->getAllowedMethods(), ['OPTIONS']);
                        $swRes->status(204);
                        $swRes->header('Allow', implode(', ', $allowed));
                        $swRes->end('');
                        return;
                    }
                    $swRes->status($route->isMethodNotAllowed() ? 405 : 404);
                    $swRes->header('Content-Type', 'application/json');
                    $swRes->end($route->isMethodNotAllowed()
                        ? '{"status":405,"error":"Method Not Allowed"}'
                        : '{"status":404,"error":"Not Found"}');
                    return;
                }

                $result = $route->execute($phpReq);

                if ($method === 'HEAD') {
                    $swRes->status(200);
                    $swRes->header('Content-Type', 'application/json');
                    $swRes->end('');
                    return;
                }

                $this->sendSwooleResponse($result, $swRes);

            } else {
                $dispatch = function (Request $req) use ($method): mixed {
                    $dispatchMethod = ($method === 'HEAD') ? 'GET' : $method;
                    $route = $this->router->dispatch($dispatchMethod, $req->path());

                    if ($route->isFound()) {
                        return $route->execute($req);
                    }
                    if ($route->isMethodNotAllowed()) {
                        return new HttpResponse(['status' => 405, 'error' => 'Method Not Allowed'], 405);
                    }
                    return new HttpResponse(['status' => 404, 'error' => 'Not Found'], 404);
                };

                $result = $this->pipeline->send($this->middlewares)->then($phpReq, $dispatch);

                if ($method === 'HEAD') {
                    $swRes->status(200);
                    $swRes->header('Content-Type', 'application/json');
                    foreach (headers_list() as $line) {
                        [$n, $v] = explode(':', $line, 2);
                        $swRes->header(trim($n), trim($v));
                    }
                    header_remove();
                    $swRes->end('');
                    return;
                }

                $phpHeaders = headers_list();
                if ($phpHeaders) {
                    foreach ($phpHeaders as $line) {
                        [$n, $v] = explode(':', $line, 2);
                        $swRes->header(trim($n), trim($v));
                    }
                    header_remove();
                }

                $this->sendSwooleResponse($result, $swRes);
            }
        } catch (ValidationException $e) {
            $swRes->status(422);
            $swRes->header('Content-Type', 'application/json');
            $swRes->end(json_encode([
                'status' => 422,
                'error'  => 'Validation Failed',
                'errors' => $e->getErrors(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            $status = ($e instanceof \InvalidArgumentException) ? 400 : 500;
            $swRes->status($status);
            $swRes->header('Content-Type', 'application/json');
            if ($this->config['debug'] || $this->config['env'] === 'dev') {
                $swRes->end(json_encode([
                    'status' => $status,
                    'error'  => $e->getMessage(),
                    'trace'  => explode("\n", $e->getTraceAsString()),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } else {
                $swRes->end($status === 400
                    ? json_encode(['status' => 400, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : '{"status":500,"error":"Internal Server Error"}');
            }
        }
    }

    /**
     * Write a route handler result directly to a Swoole response object.
     */
    protected function sendSwooleResponse(mixed $result, \Swoole\Http\Response $swRes): void
    {
        if ($result instanceof HtmlResponse) {
            $swRes->status($result->status);
            $swRes->header('Content-Type', 'text/html');
            foreach ($result->headers as $k => $v) {
                $swRes->header($k, $v);
            }
            $swRes->end($result->html);
            return;
        }

        if ($result instanceof HttpResponse) {
            $swRes->status($result->status);
            $swRes->header('Content-Type', 'application/json');
            foreach ($result->headers as $k => $v) {
                $swRes->header($k, $v);
            }
            if ($result->status !== 204) {
                $swRes->end(json_encode(
                    $result->data,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ));
            } else {
                $swRes->end('');
            }
            return;
        }

        $swRes->status(200);
        $swRes->header('Content-Type', 'application/json');
        $swRes->end(json_encode(
            $result,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }
}
