<?php

namespace App\Commands;

use Illuminate\Support\Facades\Process;
use JordanPartridge\GithubClient\Exceptions\ApiException;
use JordanPartridge\GithubClient\Facades\Github;

use function Laravel\Prompts\warning;

class ComponentInstallCommand extends ConduitCommand
{
    protected $signature = 'install {component : Component name (e.g., spotify, github)} {version? : Version constraint (e.g., ^1.0, ~2.1, 1.2.3)} {--branch= : Install from a specific branch} {--global : Install globally to ~/.shit/components} {--json : Output as JSON}';

    protected $description = 'Install a component from THE SHIT ecosystem';

    protected function executeCommand(): int
    {
        $component = $this->argument('component');
        $version = $this->argument('version') ?? '*';
        $branch = $this->option('branch');
        
        // Determine installation directory
        if ($this->option('global')) {
            $componentsDir = $_SERVER['HOME'] . '/.shit/components';
        } else {
            $componentsDir = base_path('💩-components');
        }
        
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

        // Find the component repository by searching for the conduit-component topic
        $repo = $this->findComponentRepository($component);

        if (! $repo) {
            $this->forceOutput("❌ Component '{$component}' not found in Conduit ecosystem", 'error');
            $this->smartInfo("💡 Try running 'conduit search {$component}' to find available components");

            return self::FAILURE;
        }

        try {
            // Check if --branch option was used
            if ($branch) {
                $this->smartInfo("🌿 Installing from branch: {$branch}...");

                return $this->cloneFromGitHub($repo, $componentPath, $branch);
            }

            // Check if version is a branch reference (starts with branch: or contains /)
            if (str_starts_with($version, 'branch:') || str_contains($version, '/')) {
                $branch = str_starts_with($version, 'branch:') ? substr($version, 7) : $version;
                $this->smartInfo("🌿 Installing from branch: {$branch}...");

                return $this->cloneFromGitHub($repo, $componentPath, $branch);
            }

            // Get releases from GitHub
            $releases = $this->getGitHubReleases($repo);

            if (empty($releases)) {
                // No releases, try to clone from default branch
                $defaultBranch = $this->getDefaultBranch($repo);
                $this->smartInfo("📦 No releases found, installing from {$defaultBranch} branch...");

                return $this->cloneFromGitHub($repo, $componentPath, $defaultBranch);
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
            // Parse owner and repo from full name
            [$owner, $repoName] = explode('/', $repo);

            // Use GitHub client to get releases
            $releases = Github::releases()->all($owner, $repoName);

            // Convert ReleaseData objects to arrays for compatibility
            return array_map(fn ($release) => [
                'tag_name' => $release->tag_name,
                'name' => $release->name,
                'prerelease' => $release->prerelease,
                'draft' => $release->draft,
                'published_at' => $release->published_at,
            ], $releases);
        } catch (ApiException $e) {
            // Check if it's a 404 (repo doesn't exist or no releases)
            if ($e->getCode() === 404) {
                return [];
            }
            throw new \Exception('GitHub API error: '.$e->getMessage());
        } catch (\Exception $e) {
            // Repo might not exist or have other issues
            return [];
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

        // Keep .git but set up as upstream (not origin)
        // This allows users to fork later if they want to contribute
        Process::path($path)->run('git remote rename origin upstream 2>/dev/null');

        // Add helpful message about forking
        $this->smartInfo("💡 To contribute changes, run: php 💩 component:fork {$repo}");

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

        // Make executable if manifest exists
        $manifestPath = $path.'/💩.json';
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (isset($manifest['executable'])) {
                $executable = $path.'/bin/'.$manifest['executable'];
                if (file_exists($executable)) {
                    chmod($executable, 0755);
                }
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

    private function getDefaultBranch(string $repo): string
    {
        try {
            // Use the Repo value object for proper validation
            $repoObj = \JordanPartridge\GithubClient\ValueObjects\Repo::fromFullName($repo);

            // Get the repository info to find default branch
            $repoData = Github::repos()->get($repoObj);

            // If it's not master, make fun of them
            if ($repoData->default_branch !== 'master') {
                $this->smartInfo("😏 Using '{$repoData->default_branch}' branch... how progressive of them");
            }

            return $repoData->default_branch;
        } catch (\Exception $e) {
            // If we can't get the repo info, fallback to master
            // The old-school way is usually the right way
            return 'master';
        }
    }

    /**
     * Find a component repository by searching for component topics
     */
    private function findComponentRepository(string $component): ?string
    {
        try {
            // Search ONLY for shit-component topic
            // If it ain't shit, we ain't installing it!
            $searchQuery = urlencode("topic:shit-component {$component} in:name");
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'THE-SHIT-CLI',
            ])->get("https://api.github.com/search/repositories?q={$searchQuery}&sort=stars&order=desc");

            if ($response->successful()) {
                $data = $response->json();

                if ($data['total_count'] > 0) {
                    // Try to find exact match first
                    foreach ($data['items'] as $item) {
                        if (strtolower($item['name']) === strtolower($component)) {
                            $this->smartInfo("💩 Found THE SHIT component: {$item['full_name']}");

                            return $item['full_name'];
                        }
                    }

                    // If no exact match, take the first result
                    $firstMatch = $data['items'][0];
                    $this->smartInfo("💩 Found THE SHIT component: {$firstMatch['full_name']}");

                    return $firstMatch['full_name'];
                }
            }
        } catch (\Exception $e) {
            // Search failed, fallback to config-based approach
        }

        // Fallback to the configured organization
        $githubOrg = config('conduit.components.github_username', 'the-shit');
        $fallbackRepo = "{$githubOrg}/{$component}";

        // Check if this repo exists
        try {
            $repoObj = \JordanPartridge\GithubClient\ValueObjects\Repo::fromFullName($fallbackRepo);
            Github::repos()->get($repoObj);

            return $fallbackRepo;
        } catch (\Exception $e) {
            // Repo doesn't exist
        }

        return null;
    }
}
