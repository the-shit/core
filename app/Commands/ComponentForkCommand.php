<?php

namespace App\Commands;

use Illuminate\Support\Facades\Process;
use JordanPartridge\GithubClient\Facades\Github;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

class ComponentForkCommand extends ConduitCommand
{
    protected $signature = 'component:fork {component : Component to fork} {--setup-only : Only setup remotes, don\'t fork}';

    protected $description = 'Fork a component to your GitHub for development';

    protected function executeCommand(): int
    {
        $component = $this->argument('component');
        $componentPath = base_path("💩-components/{$component}");

        if (! is_dir($componentPath)) {
            $this->error("Component '{$component}' is not installed");
            $this->info("Run: php 💩 install {$component}");

            return self::FAILURE;
        }

        // Check if .git exists
        if (! is_dir("{$componentPath}/.git")) {
            $this->warn('Component has no git repository');

            if (confirm('Would you like to reinitialize git for this component?')) {
                return $this->reinitializeGit($component, $componentPath);
            }

            return self::FAILURE;
        }

        // Get current remotes
        $remotes = $this->getRemotes($componentPath);

        if (isset($remotes['origin']) && isset($remotes['upstream'])) {
            $this->info('Component already has fork setup:');
            $this->line("  origin   → {$remotes['origin']} (your fork)");
            $this->line("  upstream → {$remotes['upstream']} (original)");

            if (! confirm('Reconfigure remotes?')) {
                return self::SUCCESS;
            }
        }

        // Get upstream URL
        $upstreamUrl = $remotes['origin'] ?? $this->detectUpstreamUrl($component);

        if (! $upstreamUrl) {
            $this->error('Could not determine upstream repository');

            return self::FAILURE;
        }

        // Parse repo info
        preg_match('/github\.com[\/:](.+?)\/(.+?)(\.git)?$/', $upstreamUrl, $matches);
        $upstreamOwner = $matches[1] ?? null;
        $repoName = $matches[2] ?? null;

        if (! $upstreamOwner || ! $repoName) {
            $this->error('Could not parse repository information');

            return self::FAILURE;
        }

        // Get user's GitHub username
        $username = $this->getGitHubUsername();

        if (! $username) {
            $this->error('Could not determine your GitHub username');
            $this->info("Make sure you're authenticated with GitHub CLI: gh auth login");

            return self::FAILURE;
        }

        // Fork the repository if not setup-only
        if (! $this->option('setup-only')) {
            $this->info("🍴 Forking {$upstreamOwner}/{$repoName} to {$username}/{$repoName}...");

            $forked = spin(
                fn () => $this->forkRepository($upstreamOwner, $repoName),
                'Creating fork on GitHub...'
            );

            if (! $forked) {
                // Fork might already exist
                $this->warn('Fork may already exist, continuing...');
            }
        }

        // Configure remotes
        $this->info('🔧 Configuring git remotes...');

        Process::path($componentPath)->run('git remote remove origin 2>/dev/null');
        Process::path($componentPath)->run('git remote remove upstream 2>/dev/null');

        // Add your fork as origin
        $originUrl = "git@github.com:{$username}/{$repoName}.git";
        Process::path($componentPath)->run("git remote add origin {$originUrl}");

        // Add original as upstream
        Process::path($componentPath)->run("git remote add upstream {$upstreamUrl}");

        // Set up branch tracking
        $defaultBranch = $this->getDefaultBranch($componentPath);
        Process::path($componentPath)->run("git branch --set-upstream-to=origin/{$defaultBranch} {$defaultBranch}");

        $this->info('✅ Component fork configured!');
        $this->newLine();
        $this->line('Git remotes:');
        $this->line("  origin   → {$originUrl} (push/pull)");
        $this->line("  upstream → {$upstreamUrl} (pull only)");
        $this->newLine();
        $this->line('Common workflows:');
        $this->line("  php 💩 component:sync {$component}     # Pull upstream changes");
        $this->line("  php 💩 component:checkout {$component} feature/my-feature");
        $this->line('  git push origin feature/my-feature    # Push to your fork');
        $this->line("  php 💩 component:pr {$component}        # Create PR to upstream");

        return self::SUCCESS;
    }

    private function getRemotes(string $path): array
    {
        $result = Process::path($path)->run('git remote -v');
        $remotes = [];

        foreach (explode("\n", $result->output()) as $line) {
            if (preg_match('/^(\w+)\s+(.+?)\s+\(fetch\)/', $line, $matches)) {
                $remotes[$matches[1]] = $matches[2];
            }
        }

        return $remotes;
    }

    private function detectUpstreamUrl(string $component): ?string
    {
        // Try to find the component in THE SHIT ecosystem
        $searchQuery = urlencode("topic:shit-component {$component} in:name");
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'THE-SHIT-CLI',
        ])->get("https://api.github.com/search/repositories?q={$searchQuery}");

        if ($response->successful() && $response['total_count'] > 0) {
            $repo = $response['items'][0];

            return $repo['clone_url'];
        }

        return null;
    }

    private function getGitHubUsername(): ?string
    {
        // Try gh CLI first
        $result = Process::run('gh api user --jq .login 2>/dev/null');
        if ($result->successful()) {
            return trim($result->output());
        }

        // Try git config
        $result = Process::run('git config --global github.user');
        if ($result->successful()) {
            return trim($result->output());
        }

        return null;
    }

    private function forkRepository(string $owner, string $repo): bool
    {
        try {
            $result = Process::timeout(30)->run("gh repo fork {$owner}/{$repo} --clone=false 2>&1");

            return $result->successful() || str_contains($result->output(), 'already exists');
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getDefaultBranch(string $path): string
    {
        $result = Process::path($path)->run('git symbolic-ref refs/remotes/origin/HEAD 2>/dev/null');
        if ($result->successful()) {
            return basename(trim($result->output()));
        }

        return 'main';
    }

    private function reinitializeGit(string $component, string $path): int
    {
        $upstreamUrl = $this->detectUpstreamUrl($component);

        if (! $upstreamUrl) {
            $this->error("Could not find upstream repository for {$component}");

            return self::FAILURE;
        }

        $this->info('Reinitializing git repository...');

        // Initialize git
        Process::path($path)->run('git init');
        Process::path($path)->run('git add .');
        Process::path($path)->run("git commit -m 'Initial component state'");

        // Add upstream
        Process::path($path)->run("git remote add upstream {$upstreamUrl}");

        $this->info("✅ Git reinitialized with upstream: {$upstreamUrl}");
        $this->info("Now run: php 💩 component:fork {$component}");

        return self::SUCCESS;
    }
}
