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

        $this->callback = $ffi->callback(
            'int (*)(uint32_t, char*, char*, char*, uint32_t)',
            function (int $handlerId, string $paramsJson, string $dtoDataJson, $outResponse, int $max) {
                try {
                    $params = Json::decode($paramsJson);
                    $dtoData = Json::decode($dtoDataJson);
                    
                    $request = new Request();
                    $request->setPathVars($params);

                    $routeMatch = new RouteMatch(
                        [1, $this->router->getRoutes()[$handlerId][2], $params],
                        $this->router->getParamCache(),
                        [],
                        $this->router->getContainer(),
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
                    if ($len >= $max) $len = $max - 1;
                    
                    /** @phpstan-ignore-next-line */
                    \FFI::memcpy($outResponse, $json, $len);
                    return $len;
                } catch (\Throwable $e) {
                    return -1;
                }
            }
        );

        $handle    = $this->router->getHandle();
        $registry  = Validator::getRegistry();

        if ($handle === null) {
            throw new \RuntimeException("Quill Router handle not initialized.");
        }

        echo "🚀 Quill Runtime listening on http://0.0.0.0:$port\n";
        
        /** @phpstan-ignore-next-line */
        $res = $ffi->quill_server_start($handle, $registry, $port, $this->callback);
        
        if ($res !== 0) {
            throw new \RuntimeException("Failed to start Quill Server (Exit code: $res)");
        }
    }
}
