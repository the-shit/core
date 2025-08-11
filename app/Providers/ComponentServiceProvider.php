<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Artisan;

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
        $componentsPath = base_path('💩-components');

        if (! is_dir($componentsPath)) {
            return;
        }

        // Look for components with 💩.json manifest files
        foreach (glob($componentsPath.'/*/💩.json') as $manifestPath) {
            $componentPath = dirname($manifestPath);
            // Skip hidden directories (starting with .)
            if (str_starts_with(basename($componentPath), '.')) {
                continue;
            }
            $this->registerComponent($componentPath);
        }
    }

    private function registerComponent(string $componentPath): void
    {
        try {
            // Read the 💩.json manifest
            $manifestPath = $componentPath . '/💩.json';
            if (!file_exists($manifestPath)) {
                return;
            }
            
            $manifest = json_decode(file_get_contents($manifestPath), true);
            
            if (!isset($manifest['commands'])) {
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
        // Register a closure-based command that acts as a proxy
        // Add common options that we'll pass through
        Artisan::command($commandName.' {args?*} {--json : Output as JSON}', function () use ($componentPath, $componentName, $commandName) {
            // Strip the component prefix from the command name (e.g., "spotify:pause" -> "pause")
            $baseCommand = str_replace($componentName.':', '', $commandName);
            
            // Build command to execute the component's Laravel Zero app
            // Try to find the executable - could be 'component', 'spotify', or the component name
            $possibleBinaries = [
                $componentPath . '/' . $componentName,  // e.g., spotify
                $componentPath . '/component',          // default Laravel Zero
                $componentPath . '/application',        // original Laravel Zero name
            ];
            
            $componentBinary = null;
            foreach ($possibleBinaries as $binary) {
                if (file_exists($binary)) {
                    $componentBinary = $binary;
                    break;
                }
            }
            
            if (!$componentBinary) {
                $this->error("❌ Component executable not found in: {$componentPath}");
                return 1;
            }
            
            // Build the full command with PHP (escape PHP_BINARY since it might have spaces)
            $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($componentBinary) . ' ' . escapeshellarg($baseCommand);
            
            // Add all arguments
            $args = $this->argument('args');
            if (!empty($args)) {
                foreach ($args as $arg) {
                    // Pass options through as-is (they'll have -- prefix)
                    if (str_starts_with($arg, '--')) {
                        $command .= ' ' . $arg;
                    } else {
                        $command .= ' ' . escapeshellarg($arg);
                    }
                }
            }
            
            // Add standard options
            if ($this->option('json')) {
                $command .= ' --json';
            }
            
            // Use Process facade to run the command
            $result = \Illuminate\Support\Facades\Process::run($command);
            
            // Output the result
            if ($result->output()) {
                $this->getOutput()->write($result->output());
            }
            
            // Also output any error output
            if ($result->errorOutput()) {
                $this->getOutput()->write($result->errorOutput());
            }
            
            return $result->exitCode();
        })->describe($description);
    }
}