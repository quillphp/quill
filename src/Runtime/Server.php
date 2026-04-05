<?php

declare(strict_types=1);

namespace Quill\Runtime;

use Quill\Routing\Router;
use Quill\Http\Request;
use Quill\Routing\RouteMatch;
use Quill\Validation\Validator;

/**
 * Quill Runtime Server
 * Bridges the binary HTTP core with the PHP application via FFI callbacks.
 */
final class Server
{
    private Router $router;
    /** @var mixed Shared reference to the FFI callback to prevent GC */
    private $callback;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Start the Quill binary HTTP server.
     */
    public function start(int $port = 8080): void
    {
        $ffi = Runtime::get();

        /** @phpstan-ignore-next-line */
        if (!method_exists(\FFI::class, 'callback')) {
            throw new \RuntimeException(sprintf(
                "FFI::callback() is not available in this PHP environment. Registered FFI methods: %s. FFI Enabled: %s",
                implode(', ', get_class_methods(\FFI::class) ?: []),
                ini_get('ffi.enable')
            ));
        }

        /** @phpstan-ignore-next-line */
        $this->callback = \FFI::callback(
            'int (*)(uint32_t, char*, char*, char*, uint32_t)',
            function (int $handlerId, string $paramsJson, string $dtoDataJson, $outResponse, int $max) {
                try {
                    $params = Json::decode($paramsJson);
                    $dtoData = Json::decode($dtoDataJson);

                    $request = new Request();
                    /** @var array<string, string> $params */
                    $request->setPathVars($params);

                    /** @var array{int, array<string>|(callable(): mixed), array<string, string>} $info */
                    $info = [1, $this->router->getRoutes()[$handlerId][2], $params];
                    $routeMatch = new RouteMatch(
                        $info,
                        $this->router->getParamCache(),
                        [],
                        $this->router->getContainer(),
                        /** @var array<string, mixed> $dtoData */
                        $dtoData
                    );

                    $result = $routeMatch->execute($request);

                    $response = [
                        'status' => 200,
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'X-Powered-By' => 'Quill-Runtime',
                        ],
                        'body' => $result
                    ];

                    $json = Json::encode($response);
                    $len = strlen($json);
                    if ($len >= $max)
                        $len = $max - 1;

                    /** @phpstan-ignore-next-line */
                    \FFI::memcpy($outResponse, $json, $len);
                    return $len;
                } catch (\Throwable $e) {
                    return -1;
                }
            }
        );

        $handle = $this->router->getHandle();
        $registry = Validator::getRegistry();

        if ($handle === null) {
            throw new \RuntimeException("Quill Router handle not initialized.");
        }

        echo "Quill Runtime listening on http://0.0.0.0:$port\n";

        /** @phpstan-ignore-next-line */
        $res = $ffi->quill_server_start($handle, $registry, $port, $this->callback);

        if ($res !== 0) {
            throw new \RuntimeException("Failed to start Quill Server (Exit code: $res)");
        }
    }
}
