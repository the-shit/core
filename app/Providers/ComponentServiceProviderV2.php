<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

class ComponentServiceProviderV2 extends ServiceProvider
{
    /**
     * V2: Direct component loading with unique namespaces
     */
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

        foreach (glob($componentsPath.'/*/💩.json') as $manifestPath) {
            $componentPath = dirname($manifestPath);
            $componentName = basename($componentPath);

            // Check if component uses new structure (has src/ directory)
            if (is_dir($componentPath.'/src')) {
                $this->registerV2Component($componentPath, $componentName);
            } else {
                // Fall back to V1 proxy method for old components
                $this->registerV1Component($componentPath, $componentName);
            }
        }
    }

    /**
     * Register a V2 component with unique namespace
     */
    private function registerV2Component(string $componentPath, string $componentName): void
    {
        try {
            $manifest = json_decode(file_get_contents($componentPath.'/💩.json'), true);

            if (! isset($manifest['commands'])) {
                return;
            }

            // Generate namespace from component name
            // e.g., "brain" -> "BrainComponent", "spotify" -> "SpotifyComponent"
            $namespace = $this->generateNamespace($componentName);

            // Add component's src/ to autoloader
            $this->registerComponentAutoloader($namespace, $componentPath.'/src');

            // Load component's service provider if it exists
            $providerClass = $namespace.'\\Providers\\ServiceProvider';
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }

            // Register commands directly
            $this->registerV2Commands($namespace, $manifest['commands']);

            // Emit event for component loaded
            event('component.loaded', [
                'name' => $componentName,
                'version' => 'v2',
                'namespace' => $namespace,
                'path' => $componentPath,
            ]);

        } catch (\Exception $e) {
            // Log error but don't crash
            logger()->error("Failed to load V2 component {$componentName}: ".$e->getMessage());
        }
    }

    /**
     * Register a V1 component using proxy method (backward compatibility)
     */
    private function registerV1Component(string $componentPath, string $componentName): void
    {
        try {
            $manifest = json_decode(file_get_contents($componentPath.'/💩.json'), true);

            if (! isset($manifest['commands'])) {
                return;
            }

            // Use the old proxy method for V1 components
            foreach ($manifest['commands'] as $commandName => $description) {
                $this->registerProxyCommand($componentPath, $componentName, $commandName, $description);
            }

        } catch (\Exception $e) {
            // Silently skip malformed components
        }
    }

    /**
     * Add component namespace to autoloader
     */
    private function registerComponentAutoloader(string $namespace, string $path): void
    {
        $loader = require base_path('vendor/autoload.php');
        $loader->addPsr4($namespace.'\\', $path.'/');
        $loader->register();
    }

    /**
     * Register V2 commands directly from namespace
     */
    private function registerV2Commands(string $namespace, array $commands): void
    {
        foreach ($commands as $commandName => $description) {
            // Try to find the command class
            // First, try the command name as-is
            $possibleClasses = [
                $namespace.'\\Commands\\'.$this->commandNameToClass($commandName),
                $namespace.'\\Commands\\'.ucfirst(explode(':', $commandName)[1] ?? $commandName).'Command',
                $namespace.'\\'.$this->commandNameToClass($commandName),
            ];

            foreach ($possibleClasses as $className) {
                if (class_exists($className)) {
                    // Check if it's a valid command
                    if (is_subclass_of($className, \Illuminate\Console\Command::class)) {
                        $this->commands([$className]);
                        break;
                    }
                }
            }
        }
    }

    /**
     * Old proxy method for V1 components (copied from original)
     */
    private function registerProxyCommand(string $componentPath, string $componentName, string $commandName, string $description): void
    {
        Artisan::command($commandName.' {args?*} {--json : Output as JSON}', function () use ($componentPath, $componentName, $commandName) {
            $baseCommand = str_replace($componentName.':', '', $commandName);

            $possibleBinaries = [
                $componentPath.'/'.$componentName,
                $componentPath.'/component',
                $componentPath.'/application',
            ];

            $binary = null;
            foreach ($possibleBinaries as $possible) {
                if (file_exists($possible) && is_executable($possible)) {
                    $binary = $possible;
                    break;
                }
            }

            if (! $binary) {
                $this->error("Component binary not found for {$componentName}");

                return 1;
            }

            // Build the command
            $args = $this->argument('args') ?? [];
            $command = array_merge(['/usr/bin/env', 'php', $binary, $baseCommand], $args);

            // Add options
            if ($this->option('json')) {
                $command[] = '--json';
            }

            // Execute the component command
            $process = new \Symfony\Component\Process\Process($command);
            $process->setTty(\Symfony\Component\Process\Process::isTtySupported());
            $process->setTimeout(null);

            try {
                $process->run(function ($type, $buffer) {
                    echo $buffer;
                });

                return $process->getExitCode();
            } catch (\Exception $e) {
                $this->error('Failed to execute component command: '.$e->getMessage());

                return 1;
            }
        })->describe($description);
    }

    /**
     * Generate namespace from component name
     */
    private function generateNamespace(string $componentName): string
    {
        // Convert kebab-case to PascalCase
        // e.g., "brain" -> "Brain", "spotify-player" -> "SpotifyPlayer"
        $parts = explode('-', $componentName);
        $namespace = implode('', array_map('ucfirst', $parts));

        return $namespace.'Component';
    }

    /**
     * Convert command name to class name
     */
    private function commandNameToClass(string $commandName): string
    {
        // Remove prefix if exists (e.g., "brain:analyze" -> "analyze")
        if (str_contains($commandName, ':')) {
            $commandName = explode(':', $commandName)[1];
        }

        // Convert to PascalCase and add Command suffix
        $parts = explode('-', $commandName);

        return implode('', array_map('ucfirst', $parts)).'Command';
    }

    /**
     * Get component information (for debugging/status)
     */
    public function getComponentInfo(): array
    {
        $info = [
            'v1' => [],
            'v2' => [],
        ];

        $componentsPath = base_path('💩-components');

        foreach (glob($componentsPath.'/*/💩.json') as $manifestPath) {
            $componentPath = dirname($manifestPath);
            $componentName = basename($componentPath);
            $manifest = json_decode(file_get_contents($manifestPath), true);

            if (is_dir($componentPath.'/src')) {
                $info['v2'][] = [
                    'name' => $componentName,
                    'namespace' => $this->generateNamespace($componentName),
                    'commands' => count($manifest['commands'] ?? []),
                ];
            } else {
                $info['v1'][] = [
                    'name' => $componentName,
                    'type' => 'proxy',
                    'commands' => count($manifest['commands'] ?? []),
                ];
            }
        }

        return $info;
    }
}
