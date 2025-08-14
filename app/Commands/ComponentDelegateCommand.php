<?php

namespace App\Commands;

use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

/**
 * Generic component delegation command that bypasses Laravel's strict option parsing
 * This solves the delegation issues identified by GPT-4o and Grok-4
 */
class ComponentDelegateCommand extends Command
{
    /**
     * The signature uses {arguments*} to capture everything without validation
     * This is the key to fixing the delegation issues
     */
    protected $signature = 'delegate {component} {subcommand} {arguments?*}';

    protected $hidden = true;

    public function handle(): int
    {
        $component = $this->argument('component');
        $command = $this->argument('command');
        $arguments = $this->argument('arguments') ?? [];

        $componentPath = base_path("💩-components/{$component}");
        $componentBinary = $this->findComponentBinary($componentPath, $component);

        if (! $componentBinary) {
            $this->error("❌ Component '{$component}' not found or has no executable");

            return 1;
        }

        // Build command array for Process (avoids shell interpretation issues)
        $commandArray = [
            PHP_BINARY,
            $componentBinary,
            $command,
        ];

        // Add all arguments - they're already properly formatted from argv
        foreach ($arguments as $arg) {
            $commandArray[] = $arg;
        }

        // Use Process with array to avoid shell escaping issues
        $result = Process::tty()
            ->timeout(0)
            ->run($commandArray);

        return $result->exitCode();
    }

    private function findComponentBinary(string $componentPath, string $componentName): ?string
    {
        $possibleBinaries = [
            $componentPath.'/'.$componentName,
            $componentPath.'/component',
            $componentPath.'/application',
        ];

        foreach ($possibleBinaries as $binary) {
            if (file_exists($binary)) {
                return $binary;
            }
        }

        return null;
    }
}
