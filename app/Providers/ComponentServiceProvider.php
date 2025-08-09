<?php

namespace App\Providers;

use App\Commands\ConduitCommand;
use Illuminate\Support\ServiceProvider;

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

        foreach (glob($componentsPath.'/*/💩.json') as $manifestPath) {
            $this->registerComponent(dirname($manifestPath));
        }
    }

    private function registerComponent(string $componentPath): void
    {
        try {
            $config = json_decode(file_get_contents($componentPath.'/💩.json'), true);

            if (! isset($config['commands'])) {
                return;
            }

            // Get the executable path
            $executable = $this->getComponentExecutable($componentPath, $config);

            if (! $executable || ! file_exists($executable)) {
                return;
            }

            // Create and register proxy commands
            foreach ($config['commands'] as $command => $description) {
                $this->createAndRegisterProxyCommand($command, $executable, $description);
            }

        } catch (\Exception $e) {
            // Silently skip malformed components for now
        }
    }

    private function getComponentExecutable(string $componentPath, array $config): string
    {
        if (isset($config['executable'])) {
            return $componentPath.'/bin/'.$config['executable'];
        }

        $componentName = basename($componentPath);

        return $componentPath.'/bin/'.$componentName;
    }

    private function createAndRegisterProxyCommand(string $command, string $executable, string $description): void
    {
        // Create a dynamic command class that extends ConduitCommand
        $className = $this->generateCommandClassName($command);

        eval($this->generateCommandClass($className, $command, $executable, $description));

        // Register the command
        $this->commands([$className]);
    }

    private function generateCommandClassName(string $command): string
    {
        // Create a safe class name from the command
        $className = 'Dynamic'.str_replace([':', '-', '.'], '', ucwords($command, ':-.')).'ProxyCommand';

        return "App\\Commands\\Dynamic\\{$className}";
    }

    private function generateCommandClass(string $className, string $command, string $executable, string $description): string
    {
        $shortClassName = substr(strrchr($className, '\\'), 1);

        return "
        namespace App\Commands\Dynamic;
        
        use App\Commands\ConduitCommand;
        use Symfony\Component\Process\Process;
        
        class {$shortClassName} extends ConduitCommand
        {
            protected \$signature = '{$command} {args?*}';
            protected \$description = '{$description}';
            
            protected function executeCommand(): int
            {
                return \$this->executeComponentCommand();
            }
            
            private function executeComponentCommand(): int
            {
                // Extract method from command (e.g., 'spotify:play' -> 'play')
                \$method = explode(':', '{$command}')[1] ?? '{$command}';
                
                // Build command arguments
                \$args = ['php', '{$executable}', \$method];
                
                // Add any extra arguments (like --reset)
                if (\$this->argument('args')) {
                    foreach (\$this->argument('args') as \$arg) {
                        \$args[] = \$arg;
                    }
                }
                
                // Add command options
                foreach (\$this->getDefinition()->getOptions() as \$option) {
                    \$name = \$option->getName();
                    if (in_array(\$name, ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env', 'silent'])) {
                        continue;
                    }
                    
                    \$value = \$this->option(\$name);
                    if (\$value !== null && \$value !== false && \$value !== '') {
                        if (\$value === true) {
                            \$args[] = '--' . \$name;
                        } else {
                            \$args[] = '--' . \$name . '=' . \$value;
                        }
                    }
                }
                
                // Execute the component
                \$process = new Process(\$args);
                \$process->setTimeout(300);
                \$process->setTty(Process::isTtySupported());
                
                try {
                    \$exitCode = \$process->run();
                    
                    return \$exitCode;
                } catch (\Exception \$e) {
                    \$this->forceOutput('❌ Component error: ' . \$e->getMessage(), 'error');
                    return self::FAILURE;
                }
            }
        }";
    }
}
