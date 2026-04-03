<?php

declare(strict_types=1);

namespace Quill;

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
        'serve'        => 'Start the development server (default: 8000)',
        'routes'       => 'List all registered application routes',
        'benchmark'    => 'Run the internal performance benchmarking suite',
        'make:handler' => 'Create a new handler class',
        'make:dto'     => 'Create a new DTO class',
        'completion'   => 'Generate shell completion script (zsh/bash)',
        'help'         => 'Show this help message',
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
            'menu'         => $this->showMenu(),
            'serve'        => $this->serve($argv[2] ?? '8000'),
            'routes'       => $this->routes(),
            'benchmark'    => $this->benchmark(),
            'make:handler' => $this->makeHandler($argv[2] ?? null),
            'make:dto'     => $this->makeDTO($argv[2] ?? null),
            'completion'   => $this->completion($argv[2] ?? 'zsh'),
            'help'         => $this->showHelp(),
            default        => null,
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
        echo " " . $this->color("1.", "dim") . " Launch Dev Server    " . $this->color("./quill serve", "cyan") . "\n";
        echo " " . $this->color("2.", "dim") . " List Routes          " . $this->color("./quill routes", "cyan") . "\n";
        echo " " . $this->color("3.", "dim") . " Run Benchmarks       " . $this->color("./quill benchmark", "cyan") . "\n";
        echo " " . $this->color("4.", "dim") . " Create Handler       " . $this->color("./quill make:handler", "cyan") . "\n\n";
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
        if (file_exists(__DIR__ . '/../routes.php')) {
            require __DIR__ . '/../routes.php';
        }
        
        $handlers = $app->getHandlers();
        echo "\n " . $this->color("Quill Registered Routes", "bold") . "\n";
        echo " " . str_repeat("─", 80) . "\n";
        printf(" %-10s │ %-30s │ %-30s\n", "METHOD", "PATH", "HANDLER");
        echo " " . str_repeat("─", 80) . "\n";
        
        foreach ($handlers as [$method, $path, $handler]) {
            $h = is_array($handler) ? implode('::', $handler) : ($handler instanceof \Closure ? 'Closure' : 'Unknown');
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

    private function benchmark(): void
    {
        (new Internal\Benchmark())->run();
    }

    private function makeHandler(?string $name): void
    {
        if (!$name) {
            echo "\n " . $this->color("✖ Error:", "red") . " Handler name required (e.g., UserHandler)\n\n";
            exit(1);
        }
        if (!str_ends_with($name, 'Handler')) $name .= 'Handler';
        $path = __DIR__ . "/../handlers/$name.php";
        
        if (file_exists($path)) {
            echo "\n " . $this->color("✖ Error:", "red") . " Handler $name already exists.\n\n";
            exit(1);
        }

        if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
        
        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace Handlers;\n\nuse Quill\Request;\n\nclass $name\n{\n    public function index(Request \$request): array\n    {\n        return ['message' => 'Hello from $name'];\n    }\n}\n";
        file_put_contents($path, $content);
        echo "\n " . $this->color("✓", "green") . " Created handler: " . $this->color("handlers/$name.php", "bold") . "\n\n";
    }

    private function makeDTO(?string $name): void
    {
        if (!$name) {
            echo "\n " . $this->color("✖ Error:", "red") . " DTO name required (e.g., UserCreate)\n\n";
            exit(1);
        }
        if (!str_ends_with($name, 'DTO')) $name .= 'DTO';
        $path = __DIR__ . "/../dtos/$name.php";

        if (file_exists($path)) {
            echo "\n " . $this->color("✖ Error:", "red") . " DTO $name already exists.\n\n";
            exit(1);
        }

        if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);

        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace Dtos;\n\nuse Quill\DTO;\n\nclass $name extends DTO\n{\n    public string \$email;\n}\n";
        file_put_contents($path, $content);
        echo "\n " . $this->color("✓", "green") . " Created DTO: " . $this->color("dtos/$name.php", "bold") . "\n\n";
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
