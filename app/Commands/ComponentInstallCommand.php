<?php

namespace App\Commands;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\warning;

class ComponentInstallCommand extends ConduitCommand
{
    protected $signature = 'install {component : Component name (e.g., spotify, github)} {version? : Version constraint (e.g., ^1.0, ~2.1, 1.2.3)} {--json : Output as JSON}';

    protected $description = 'Install a component from THE SHIT ecosystem';

    protected function executeCommand(): int
    {
        $component = $this->argument('component');
        $version = $this->argument('version') ?? '*';
        $componentsDir = base_path('💩-components');
        $componentPath = $componentsDir.'/'.$component;

        // Check if already installed
        if (is_dir($componentPath)) {
            $this->smartInfo("✅ Component '{$component}' is already installed");

            return self::SUCCESS;
        }

        $this->smartInfo("💩 Installing {$component}...");

        // Create components directory if needed
        if (! is_dir($componentsDir)) {
            mkdir($componentsDir, 0755, true);
        }

        // Determine the GitHub repo
        $repo = "S-H-I-T/{$component}";

        try {
            // Get releases from GitHub
            $releases = $this->getGitHubReleases($repo);

            if (empty($releases)) {
                // No releases, try to clone from main branch
                $this->smartInfo('📦 No releases found, installing from main branch...');

                return $this->cloneFromGitHub($repo, $componentPath, 'main');
            }

            // Find matching version
            $release = $this->findMatchingRelease($releases, $version);

            if (! $release) {
                $this->forceOutput("❌ No release matching version constraint '{$version}'", 'error');

                return self::FAILURE;
            }

            $this->smartInfo("📦 Installing {$component} v{$release['tag_name']}...");

            // Clone specific tag
            return $this->cloneFromGitHub($repo, $componentPath, $release['tag_name']);

        } catch (\Exception $e) {
            $this->forceOutput('❌ Failed to install: '.$e->getMessage(), 'error');

            return self::FAILURE;
        }
    }

    private function getGitHubReleases(string $repo): array
    {
        try {
            // Use HTTP directly for unauthenticated requests
            $response = Http::withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'THE-SHIT-CLI',
            ])->get("https://api.github.com/repos/{$repo}/releases");

            if ($response->failed()) {
                if ($response->status() === 404) {
                    // Repo might not exist or have releases
                    return [];
                }
                throw new \Exception('GitHub API error: '.$response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            // Check if it's a 404 (repo doesn't exist or no releases)
            if (str_contains($e->getMessage(), '404')) {
                return [];
            }
            throw new \Exception('GitHub API error: '.$e->getMessage());
        }
    }

    private function findMatchingRelease(array $releases, string $constraint): ?array
    {
        if ($constraint === '*' || $constraint === 'latest') {
            return $releases[0] ?? null; // Most recent release
        }

        // Simple version matching for now
        // TODO: Implement full semver constraint matching
        foreach ($releases as $release) {
            $tag = ltrim($release['tag_name'], 'v');

            // Exact match
            if ($tag === ltrim($constraint, 'v^~')) {
                return $release;
            }

            // Simple caret (^) matching - same major version
            if (str_starts_with($constraint, '^')) {
                $constraintVersion = ltrim($constraint, '^');
                $major = explode('.', $constraintVersion)[0];
                if (str_starts_with($tag, $major.'.')) {
                    return $release;
                }
            }
        }

        return null;
    }

    private function cloneFromGitHub(string $repo, string $path, string $ref): int
    {
        // Clone the repository
        $cloneCommand = [
            'git', 'clone',
            '--depth', '1',
            '--branch', $ref,
            "https://github.com/{$repo}.git",
            $path,
        ];

        $result = Process::timeout(300)
            ->run(implode(' ', array_map('escapeshellarg', $cloneCommand)));

        if (! $result->successful()) {
            $this->forceOutput('❌ Failed to clone repository: '.$result->errorOutput(), 'error');

            return self::FAILURE;
        }

        // Remove .git directory to keep it clean
        $this->exec("rm -rf {$path}/.git");

        // Install dependencies if composer.json exists
        if (file_exists($path.'/composer.json')) {
            $this->smartInfo('📚 Installing dependencies...');
            $result = Process::path($path)
                ->timeout(300)
                ->run('composer install --no-dev');

            if (! $result->successful()) {
                warning('Failed to install dependencies');
            }
        }

        // Make executable if exists
        $manifest = json_decode(file_get_contents($path.'/💩.json'), true);
        if (isset($manifest['executable'])) {
            $executable = $path.'/bin/'.$manifest['executable'];
            if (file_exists($executable)) {
                chmod($executable, 0755);
            }
        }

        $this->smartInfo("✅ Successfully installed {$repo}@{$ref}");
        $this->smartInfo('🚀 Component ready to use!');

        return self::SUCCESS;
    }

    private function exec(string $command): void
    {
        Process::run($command);
    }
}
