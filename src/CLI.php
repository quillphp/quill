<?php

declare(strict_types=1);

namespace Quill;

use Quill\Http\Request;
use Quill\Http\HttpResponse;
use Quill\Validation\DTO;

/**
 * Modern CLI Orchestrator for Quill (2026).
 * Zero-dependency, high-performance terminal interface.
 */
class CLI
{
    private const COLORS = [
        'green'  => "\033[32m",
        'red'    => "\033[31m",
        'yellow' => "\033[33m",
        'blue'   => "\033[34m",
        'cyan'   => "\033[36m",
        'bold'   => "\033[1m",
        'dim'    => "\033[2m",
        'reset'  => "\033[0m",
    ];

    /** @var array<string, string> */
    private array $commands = [
        'serve'           => 'Start the development server (default: 8000)',
        'routes'          => 'List all registered application routes',
        'make:controller' => 'Create a new controller/handler class',
        'make:dto'        => 'Create a new DTO class',
        'make:middleware' => 'Create a new middleware class',
        'make:exception'  => 'Create a new custom exception',
        'completion'      => 'Generate shell completion script (zsh/bash)',
        'help'            => 'Show this help message',
    ];

    /** @param list<string> $argv */
    public function run(array $argv): void
    {
        $command = $argv[1] ?? 'menu';

        if (!isset($this->commands[$command]) && $command !== 'menu') {
            $this->suggest($command);
            exit(1);
        }

        match ($command) {
            'menu'            => $this->showMenu(),
            'serve'           => $this->serve($argv[2] ?? '8000'),
            'routes'          => $this->routes(),
            'make:handler'    => $this->makeHandler($argv[2] ?? null),
            'make:controller' => $this->makeHandler($argv[2] ?? null),
            'make:dto'        => $this->makeDTO($argv[2] ?? null),
            'make:middleware' => $this->makeMiddleware($argv[2] ?? null),
            'make:exception'  => $this->makeException($argv[2] ?? null),
            'completion'      => $this->completion($argv[2] ?? 'zsh'),
            'help'            => $this->showHelp(),
            default           => null,
        };
    }

    private function color(string $text, string $color): string
    {
        return (self::COLORS[$color] ?? '') . $text . self::COLORS['reset'];
    }

    private function showMenu(): void
    {
        echo "\n " . $this->color("⚡ Quill 1.0.0", "bold") . " — " . $this->color("The Last API Framework", "dim") . "\n";
        echo " " . str_repeat("─", 50) . "\n\n";
        echo " What would you like to do?\n\n";
        echo " " . $this->color("1.", "dim") . " Launch Dev Server     " . $this->color("./quill serve", "cyan") . "\n";
        echo " " . $this->color("2.", "dim") . " List Routes           " . $this->color("./quill routes", "cyan") . "\n";
        echo " " . $this->color("3.", "dim") . " Create Controller     " . $this->color("./quill make:controller", "cyan") . "\n\n";
        echo " " . $this->color("ℹ Tip:", "bold") . " Type " . $this->color("./quill help", "green") . " for the full list of commands.\n\n";
    }

    private function showHelp(): void
    {
        echo "\n " . $this->color("Quill CLI Tool", "bold") . "\n";
        echo " Usage: " . $this->color("./quill [command] [options]", "green") . "\n\n";
        echo " " . $this->color("Commands:", "yellow") . "\n";
        foreach ($this->commands as $cmd => $desc) {
            printf("  %-15s %s\n", $this->color($cmd, "green"), $this->color($desc, "dim"));
        }
        echo "\n";
    }

    private function suggest(string $input): void
    {
        echo "\n " . $this->color("✖ Error:", "red") . " Command " . $this->color($input, "bold") . " not found.\n";
        
        $best = null;
        $shortest = 3;

        foreach (array_keys($this->commands) as $cmd) {
            $lev = levenshtein($input, $cmd);
            if ($lev < $shortest) {
                $best = $cmd;
                $shortest = $lev;
            }
        }

        if ($best) {
            echo " " . $this->color("ℹ Did you mean:", "cyan") . " " . $this->color($best, "green") . "?\n\n";
        }
    }

    private function serve(string $port): void
    {
        echo "\n " . $this->color("✓", "green") . " Quill development server started on " . $this->color("http://localhost:$port", "bold") . "\n";
        echo " " . $this->color("⚡ Path:", "dim") . " public/index.php\n";
        echo " " . $this->color("ℹ Press Ctrl+C to stop.", "dim") . "\n\n";
        passthru(PHP_BINARY . " -S localhost:$port " . __DIR__ . "/../public/index.php");
    }

    private function routes(): void
    {
        putenv('APP_ENV=dev');
        $app = new App(['route_cache' => false]);
        $app->boot();
        if (file_exists(__DIR__ . '/../routes.php')) {
            require __DIR__ . '/../routes.php';
        }
        
        $handlers = $app->getRouter()->getRoutes();
        echo "\n " . $this->color("Quill Registered Routes", "bold") . "\n";
        echo " " . str_repeat("─", 80) . "\n";
        printf(" %-10s │ %-30s │ %-30s\n", "METHOD", "PATH", "HANDLER");
        echo " " . str_repeat("─", 80) . "\n";
        
        foreach ($handlers as [$method, $path, $handler]) {
            /** @var array<string> $hArr */
            $hArr = is_array($handler) ? $handler : [];
            $h = !empty($hArr) ? implode('::', $hArr) : ($handler instanceof \Closure ? 'Closure' : 'Unknown');
            $verbColor = match($method) {
                'GET'    => 'green',
                'POST'   => 'cyan',
                'DELETE' => 'red',
                default  => 'yellow'
            };
            printf(" %-1s %-8s │ %-30s │ %-30s\n", " ", $this->color($method, $verbColor), $path, $this->color($h, "dim"));
        }
        echo " " . str_repeat("─", 80) . "\n\n";
    }

    private function makeHandler(?string $name): void
    {
        $name = $this->validateName($name, 'Handler');
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace Handlers;

use Quill\Http\Request;
use Quill\Http\HttpResponse;

class $name
{
    /** @return array<string, mixed>|HttpResponse */
    public function index(Request \$request): array|HttpResponse
    {
        return ['message' => 'Hello from $name'];
    }
}
PHP;
        $this->writeTemplate("handlers/$name.php", $content);
    }

    private function makeDTO(?string $name): void
    {
        $name = $this->validateName($name, 'DTO');
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace Dtos;

use Quill\Validation\DTO;

/**
 * Modern DTO for Quill.
 * Tip: Use PHP 8.3 property promotion for maximum brevity.
 */
class $name extends DTO
{
    public string \$example;
}
PHP;
        $this->writeTemplate("dtos/$name.php", $content);
    }

    private function makeMiddleware(?string $name): void
    {
        $name = $this->validateName($name, 'Middleware');
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace Middlewares;

use Quill\Http\Request;

class $name
{
    /**
     * @param Request \$request
     * @param callable \$next
     * @return mixed
     */
    public function handle(Request \$request, callable \$next): mixed
    {
        // Pre-processing...
        \$response = \$next(\$request);
        // Post-processing...
        
        return \$response;
    }
}
PHP;
        $this->writeTemplate("middlewares/$name.php", $content);
    }

    private function makeException(?string $name): void
    {
        $name = $this->validateName($name, 'Exception');
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace Exceptions;

class $name extends \Exception
{
    // Custom exception logic...
}
PHP;
        $this->writeTemplate("exceptions/$name.php", $content);
    }

    private function validateName(?string $name, string $suffix): string
    {
        if (!$name) {
            echo "\n " . $this->color("✖ Error:", "red") . " $suffix name required (e.g., User$suffix)\n\n";
            exit(1);
        }
        if (!str_ends_with($name, $suffix)) $name .= $suffix;
        return $name;
    }

    private function writeTemplate(string $relativePath, string $content): void
    {
        $path = __DIR__ . "/../$relativePath";
        if (file_exists($path)) {
            echo "\n " . $this->color("✖ Error:", "red") . " File " . $this->color($relativePath, "bold") . " already exists.\n\n";
            exit(1);
        }

        if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
        file_put_contents($path, $content);
        echo "\n " . $this->color("✓", "green") . " Created: " . $this->color($relativePath, "bold") . "\n\n";
    }

    private function completion(string $shell): void
    {
        if ($shell === 'zsh') {
            $cmds = implode(' ', array_keys($this->commands));
            echo <<<EOD
# Quill Command Completion for ZSH
# To enable: source <(./quill completion zsh)
_quill_completion() {
    local -a commands
    commands=($cmds)
    _describe 'command' commands
}
compdef _quill_completion quill
EOD;
        } else {
            echo "# Auto-completion currently only supports ZSH (default for Mac).\n";
        }
    }
}
