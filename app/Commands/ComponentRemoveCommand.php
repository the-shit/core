<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ComponentRemoveCommand extends ConduitCommand
{
    protected $signature = 'component:remove {component : Component name to remove} {--force : Skip confirmation} {--json : Output as JSON}';

    protected $description = 'Remove an installed component';

    protected function executeCommand(): int
    {
        $component = $this->argument('component');
        $componentsDir = base_path('💩-components');
        $componentPath = $componentsDir.'/'.$component;

        // Check if component exists
        if (! is_dir($componentPath)) {
            $this->forceOutput("❌ Component '{$component}' is not installed", 'error');

            // Suggest similar components
            $installed = $this->getInstalledComponents($componentsDir);
            if (! empty($installed)) {
                $this->smartInfo("\nInstalled components: ".implode(', ', $installed));
            }

            return self::FAILURE;
        }

        // Get component info before removal
        $manifest = $this->getComponentManifest($componentPath);
        $componentInfo = $manifest ? "{$component} v{$manifest['version']}" : $component;

        // Confirm removal
        if (! $this->option('force')) {
            $confirmed = $this->smartConfirm(
                "Are you sure you want to remove {$componentInfo}?",
                false
            );

            if (! $confirmed) {
                $this->smartInfo('Removal cancelled.');

                return self::SUCCESS;
            }
        }

        $this->smartInfo("🗑️  Removing {$componentInfo}...");

        try {
            // Remove component directory
            $this->removeDirectory($componentPath);

            // Update registry if it exists
            $this->updateComponentRegistry($component, 'remove');

            $this->smartInfo("✅ Successfully removed {$componentInfo}");

            // Check if components directory is empty
            if ($this->isDirectoryEmpty($componentsDir)) {
                File::deleteDirectory($componentsDir);
                $this->smartInfo('📦 No components remaining. Components directory removed.');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->forceOutput('❌ Failed to remove component: '.$e->getMessage(), 'error');

            return self::FAILURE;
        }
    }

    private function getInstalledComponents(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $components = [];
        foreach (File::directories($dir) as $componentDir) {
            $components[] = basename($componentDir);
        }

        return $components;
    }

    private function getComponentManifest(string $componentPath): ?array
    {
        $manifestPath = $componentPath.'/💩.json';

        if (! file_exists($manifestPath)) {
            return null;
        }

        try {
            return json_decode(file_get_contents($manifestPath), true);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function removeDirectory(string $path): void
    {
        // Use File facade for consistent removal
        if (! File::deleteDirectory($path)) {
            // Fallback to system command if needed
            $result = Process::run('rm -rf '.escapeshellarg($path));

            if (! $result->successful()) {
                throw new \Exception("Could not remove directory: {$path}");
            }
        }
    }

    private function isDirectoryEmpty(string $dir): bool
    {
        if (! is_dir($dir)) {
            return true;
        }

        $items = scandir($dir);

        return count($items) <= 2; // Only . and ..
    }

    private function updateComponentRegistry(string $component, string $action): void
    {
        $registryPath = base_path('💩-components.json');

        if (! file_exists($registryPath)) {
            return;
        }

        try {
            $registry = json_decode(file_get_contents($registryPath), true) ?? [];

            if ($action === 'remove' && isset($registry['installed'][$component])) {
                unset($registry['installed'][$component]);
                $registry['removed'][] = [
                    'name' => $component,
                    'removed_at' => now()->toIso8601String(),
                ];
            }

            file_put_contents($registryPath, json_encode($registry, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            // Registry update is non-critical, continue
        }
    }
}
