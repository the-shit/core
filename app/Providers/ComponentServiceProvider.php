<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ComponentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->discoverAndRegisterComponents();
    }

    private function discoverAndRegisterComponents(): void
    {
        // Get component paths from Laravel config
        $componentPaths = [
            config('shit.paths.components.local'),
            config('shit.paths.components.user'),
            config('shit.paths.components.system'),
        ];

        foreach ($componentPaths as $componentsPath) {
            if (! is_dir($componentsPath)) {
                continue;
            }

            // Look for components with 💩.json manifest files
            foreach (glob($componentsPath.'/*/💩.json') as $manifestPath) {
                $componentPath = dirname($manifestPath);
                $componentName = basename($componentPath);
                
                // Skip hidden directories (starting with .)
                if (str_starts_with($componentName, '.')) {
                    continue;
                }
                
                // Skip if already registered (higher priority paths win)
                if ($this->isComponentRegistered($componentName)) {
                    continue;
                }
                
                $this->registerComponent($componentPath);
            }
        }
    }
    
    private function isComponentRegistered(string $componentName): bool
    {
        // Check if component commands are already registered
        foreach (Artisan::all() as $command) {
            if (str_starts_with($command->getName(), $componentName . ':')) {
                return true;
            }
        }
        return false;
    }

    private function registerComponent(string $componentPath): void
    {
        try {
            // Read the 💩.json manifest
            $manifestPath = $componentPath.'/💩.json';
            if (! file_exists($manifestPath)) {
                return;
            }

            $manifest = json_decode(file_get_contents($manifestPath), true);

            if (! isset($manifest['commands'])) {
                return;
            }

            $componentName = basename($componentPath);

            // Register proxy commands for each command in the manifest
            foreach ($manifest['commands'] as $commandName => $description) {
                $this->registerProxyCommand($componentPath, $componentName, $commandName, $description);
            }

        } catch (\Exception $e) {
            // Silently skip malformed components for now
        }
    }

    private function registerProxyCommand(string $componentPath, string $componentName, string $commandName, string $description): void
    {
        // The "Nuno way" - use Symfony Console's flexibility
        // Create a command that accepts ANY input without validation
        Artisan::command($commandName, function () use ($componentPath, $componentName, $commandName) {
            // Find the component executable
            $componentBinary = $this->findComponentBinary($componentPath, $componentName);

            if (! $componentBinary) {
                $this->error("❌ Component executable not found in: {$componentPath}");

                return 1;
            }

            // Strip the component prefix from the command name
            $baseCommand = str_replace($componentName.':', '', $commandName);

            // Build the command array
            $commandArray = [
                PHP_BINARY,
                $componentBinary,
                $baseCommand,
            ];

            // Use global argv to bypass Laravel's parsing entirely
            // This is the nuclear option but it works
            global $argv;

            // Find where our command appears in argv
            $foundCommand = false;
            foreach ($argv as $i => $arg) {
                if ($foundCommand) {
                    // Add everything after the command
                    $commandArray[] = $arg;
                } elseif ($arg === $commandName) {
                    $foundCommand = true;
                }
            }

            // Use Process with TTY for interactive support
            $result = Process::tty()->timeout(0)->run($commandArray);

            return $result->exitCode();
        })
            ->describe($description)
            ->ignoreValidationErrors() // This is the key - ignore Laravel's validation
            ->setDefinition([
                // Accept ANY arguments and options dynamically
                new InputArgument('arguments', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Arguments'),
                new InputOption('provider', null, InputOption::VALUE_OPTIONAL, 'Provider'),
                new InputOption('model', null, InputOption::VALUE_OPTIONAL, 'Model'),
                new InputOption('stream', null, InputOption::VALUE_NONE, 'Stream'),
                new InputOption('no-stream', null, InputOption::VALUE_NONE, 'No stream'),
                new InputOption('temperature', null, InputOption::VALUE_OPTIONAL, 'Temperature'),
                new InputOption('max-tokens', null, InputOption::VALUE_OPTIONAL, 'Max tokens'),
                new InputOption('json', null, InputOption::VALUE_NONE, 'JSON output'),
                new InputOption('format', null, InputOption::VALUE_OPTIONAL, 'Format'),
                new InputOption('system', null, InputOption::VALUE_OPTIONAL, 'System prompt'),
                new InputOption('context', null, InputOption::VALUE_OPTIONAL, 'Context'),
            ]);
    }

    /**
     * Find the component executable
     */
    private function findComponentBinary(string $componentPath, string $componentName): ?string
    {
        $possibleBinaries = [
            $componentPath.'/'.$componentName,  // e.g., ai, spotify
            $componentPath.'/component',          // default Laravel Zero
            $componentPath.'/application',        // original Laravel Zero name
        ];

        foreach ($possibleBinaries as $binary) {
            if (file_exists($binary)) {
                return $binary;
            }
        }

        return null;
    }
}
