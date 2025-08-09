<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;

class ComponentListCommand extends ConduitCommand
{
    protected $signature = 'component:list {--json : Output as JSON}';

    protected $description = 'List all installed components';

    protected function executeCommand(): int
    {
        $componentsDir = base_path('💩-components');

        if (! is_dir($componentsDir)) {
            $this->warn('No components installed yet.');
            $this->smartInfo('Use `php 💩 install <component>` to install your first component.');

            return self::SUCCESS;
        }

        $components = $this->scanForComponents($componentsDir);

        if (empty($components)) {
            $this->warn('No components found.');

            return self::SUCCESS;
        }

        // JSON output for AI/automation
        if ($this->option('json') || $this->isNonInteractiveMode()) {
            return $this->jsonResponse(['components' => $components]);
        }

        // Human-readable output
        $this->displayComponentsTable($components);

        return self::SUCCESS;
    }

    private function scanForComponents(string $dir): array
    {
        $components = [];
        $directories = File::directories($dir);

        foreach ($directories as $componentDir) {
            $manifestPath = $componentDir.'/💩.json';

            if (! file_exists($manifestPath)) {
                continue;
            }

            try {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                $componentName = basename($componentDir);

                $components[] = [
                    'name' => $componentName,
                    'version' => $manifest['version'] ?? 'unknown',
                    'description' => $manifest['description'] ?? 'No description',
                    'shit_acronym' => $manifest['shit_acronym'] ?? null,
                    'path' => $componentDir,
                    'commands' => $this->getComponentCommands($componentDir),
                ];
            } catch (\Exception $e) {
                // Skip invalid components
                continue;
            }
        }

        return $components;
    }

    private function getComponentCommands(string $componentDir): array
    {
        $commands = [];
        $commandsDir = $componentDir.'/app/Commands';

        if (! is_dir($commandsDir)) {
            return $commands;
        }

        $files = File::files($commandsDir);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $file->getFilenameWithoutExtension();

            // Skip base classes and interfaces
            if (in_array($className, ['BaseCommand', 'ConduitCommand', 'CommandInterface'])) {
                continue;
            }

            $commands[] = strtolower(str_replace('Command', '', $className));
        }

        return $commands;
    }

    private function displayComponentsTable(array $components): void
    {
        $this->smartInfo("💩 Installed Components\n");

        $rows = [];
        foreach ($components as $component) {
            $acronym = $component['shit_acronym']
                ? "\n<fg=gray>{$component['shit_acronym']}</>"
                : '';

            $commands = ! empty($component['commands'])
                ? "\n<fg=cyan>".implode(', ', $component['commands']).'</>'
                : "\n<fg=gray>No commands</>";

            $rows[] = [
                $component['name'],
                $component['version'],
                $component['description'].$acronym,
                count($component['commands']).' commands'.$commands,
            ];
        }

        $this->table(
            ['Component', 'Version', 'Description', 'Commands'],
            $rows
        );

        $this->newLine();
        $this->smartInfo('💡 Tip: Use `php 💩 <component>:<command>` to run component commands');
    }
}
