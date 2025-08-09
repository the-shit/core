<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class ComponentUpdateCommand extends ConduitCommand
{
    protected $signature = 'component:update {component? : Component to update} {--all : Update all components} {--json : Output as JSON}';

    protected $description = 'Update installed components to their latest versions';

    protected function executeCommand(): int
    {
        $component = $this->argument('component');
        $updateAll = $this->option('all');

        if (! $component && ! $updateAll) {
            $this->error('Please specify a component or use --all to update all components');

            return self::FAILURE;
        }

        $componentsDir = base_path('💩-components');

        if (! is_dir($componentsDir)) {
            $this->warn('No components installed.');

            return self::SUCCESS;
        }

        if ($updateAll) {
            return $this->updateAllComponents($componentsDir);
        }

        return $this->updateSingleComponent($component, $componentsDir);
    }

    private function updateAllComponents(string $componentsDir): int
    {
        $components = $this->getInstalledComponents($componentsDir);

        if (empty($components)) {
            $this->warn('No components found to update.');

            return self::SUCCESS;
        }

        $this->smartInfo("💩 Updating all components...\n");

        $updated = 0;
        $failed = 0;

        foreach ($components as $component) {
            $result = $this->updateComponent($component['name'], $component['path']);

            if ($result) {
                $updated++;
            } else {
                $failed++;
            }
        }

        $this->newLine();
        $this->smartInfo("✅ Updated: {$updated} components");

        if ($failed > 0) {
            $this->warn("❌ Failed: {$failed} components");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function updateSingleComponent(string $component, string $componentsDir): int
    {
        $componentPath = $componentsDir.'/'.$component;

        if (! is_dir($componentPath)) {
            $this->error("Component '{$component}' is not installed");

            return self::FAILURE;
        }

        $this->smartInfo("💩 Updating {$component}...");

        if ($this->updateComponent($component, $componentPath)) {
            $this->smartInfo("✅ Successfully updated {$component}");

            return self::SUCCESS;
        }

        $this->error("❌ Failed to update {$component}");

        return self::FAILURE;
    }

    private function updateComponent(string $name, string $path): bool
    {
        try {
            // Get current version
            $currentManifest = $this->getComponentManifest($path);
            $currentVersion = $currentManifest['version'] ?? 'unknown';

            // Check for latest version on GitHub
            $repo = "S-H-I-T/{$name}";
            $latestVersion = $this->getLatestVersion($repo);

            if (! $latestVersion) {
                $this->warn("Could not fetch latest version for {$name}");

                return false;
            }

            // Compare versions
            if (version_compare($currentVersion, $latestVersion, '>=')) {
                $this->info("{$name} is already up to date (v{$currentVersion})");

                return true;
            }

            $this->info("Updating {$name} from v{$currentVersion} to v{$latestVersion}");

            // Backup current installation
            $backupPath = $path.'.backup';
            $this->backupComponent($path, $backupPath);

            try {
                // Remove old version
                File::deleteDirectory($path);

                // Clone new version
                $this->cloneComponent($repo, $path, $latestVersion);

                // Install dependencies
                $this->installDependencies($path);

                // Remove backup
                File::deleteDirectory($backupPath);

                return true;

            } catch (\Exception $e) {
                // Restore from backup on failure
                $this->warn('Update failed, restoring backup...');
                File::deleteDirectory($path);
                File::moveDirectory($backupPath, $path);
                throw $e;
            }

        } catch (\Exception $e) {
            $this->error("Error updating {$name}: ".$e->getMessage());

            return false;
        }
    }

    private function getLatestVersion(string $repo): ?string
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'THE-SHIT-CLI',
            ])->get("https://api.github.com/repos/{$repo}/releases/latest");

            if ($response->successful()) {
                $release = $response->json();

                return ltrim($release['tag_name'] ?? '', 'v');
            }

            // If no releases, get default branch latest commit
            $response = Http::withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'THE-SHIT-CLI',
            ])->get("https://api.github.com/repos/{$repo}");

            if ($response->successful()) {
                // Use main/master branch as version "dev-main"
                return 'dev-main';
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function cloneComponent(string $repo, string $path, string $version): void
    {
        $cloneCommand = [
            'git', 'clone',
            '--depth', '1',
        ];

        // Add branch/tag if not dev version
        if (! str_starts_with($version, 'dev-')) {
            $cloneCommand[] = '--branch';
            $cloneCommand[] = "v{$version}";
        }

        $cloneCommand[] = "https://github.com/{$repo}.git";
        $cloneCommand[] = $path;

        $result = Process::timeout(300)
            ->run(implode(' ', array_map('escapeshellarg', $cloneCommand)));

        if (! $result->successful()) {
            throw new \Exception('Failed to clone repository: '.$result->errorOutput());
        }

        // Remove .git directory
        File::deleteDirectory($path.'/.git');
    }

    private function installDependencies(string $path): void
    {
        if (! file_exists($path.'/composer.json')) {
            return;
        }

        Process::path($path)
            ->timeout(300)
            ->run('composer install --no-dev');

        // Non-critical if dependencies fail
    }

    private function backupComponent(string $source, string $destination): void
    {
        if (! File::copyDirectory($source, $destination)) {
            throw new \Exception('Failed to backup component');
        }
    }

    private function getInstalledComponents(string $dir): array
    {
        $components = [];

        foreach (File::directories($dir) as $componentDir) {
            $manifestPath = $componentDir.'/💩.json';

            if (file_exists($manifestPath)) {
                $components[] = [
                    'name' => basename($componentDir),
                    'path' => $componentDir,
                ];
            }
        }

        return $components;
    }

    private function getComponentManifest(string $path): ?array
    {
        $manifestPath = $path.'/💩.json';

        if (! file_exists($manifestPath)) {
            return null;
        }

        try {
            return json_decode(file_get_contents($manifestPath), true);
        } catch (\Exception $e) {
            return null;
        }
    }
}
