<?php

namespace App\Commands;

use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class ComponentCheckoutCommand extends ConduitCommand
{
    protected $signature = 'component:checkout {component : Component name} {branch? : Branch to checkout} {--list : List available branches}';

    protected $description = 'Switch component branches or create new ones';

    protected function executeCommand(): int
    {
        $component = $this->argument('component');
        $branch = $this->argument('branch');
        $componentPath = base_path("💩-components/{$component}");

        if (! is_dir($componentPath)) {
            $this->error("Component '{$component}' is not installed");

            return self::FAILURE;
        }

        if (! is_dir("{$componentPath}/.git")) {
            $this->error('Component has no git repository');
            $this->info("Run: php 💩 component:fork {$component}");

            return self::FAILURE;
        }

        // List branches if requested
        if ($this->option('list')) {
            return $this->listBranches($componentPath);
        }

        // If no branch specified, show interactive selector
        if (! $branch) {
            $branches = $this->getAllBranches($componentPath);

            if (empty($branches)) {
                $this->error('No branches found');

                return self::FAILURE;
            }

            $branch = select(
                'Select a branch to checkout:',
                $branches,
                $this->getCurrentBranch($componentPath)
            );
        }

        // Check if branch exists locally
        $localBranches = $this->getLocalBranches($componentPath);
        $remoteBranches = $this->getRemoteBranches($componentPath);

        if (in_array($branch, $localBranches)) {
            // Switch to existing local branch
            $result = Process::path($componentPath)->run("git checkout {$branch}");

            if ($result->successful()) {
                $this->info("✅ Switched to branch: {$branch}");

                return self::SUCCESS;
            } else {
                $this->error("Failed to checkout branch: {$result->errorOutput()}");

                return self::FAILURE;
            }
        }

        // Check if it exists on a remote
        $remoteBranch = $this->findRemoteBranch($branch, $remoteBranches);

        if ($remoteBranch) {
            // Create local branch from remote
            $result = Process::path($componentPath)->run("git checkout -b {$branch} {$remoteBranch}");

            if ($result->successful()) {
                $this->info("✅ Created and switched to branch: {$branch}");
                $this->info("   Tracking: {$remoteBranch}");

                return self::SUCCESS;
            } else {
                $this->error("Failed to create branch: {$result->errorOutput()}");

                return self::FAILURE;
            }
        }

        // Branch doesn't exist, offer to create it
        if (confirm("Branch '{$branch}' doesn't exist. Create it?")) {
            $result = Process::path($componentPath)->run("git checkout -b {$branch}");

            if ($result->successful()) {
                $this->info("✅ Created and switched to new branch: {$branch}");
                $this->info("💡 Push to your fork: git push -u origin {$branch}");

                return self::SUCCESS;
            } else {
                $this->error("Failed to create branch: {$result->errorOutput()}");

                return self::FAILURE;
            }
        }

        return self::FAILURE;
    }

    private function getCurrentBranch(string $path): string
    {
        $result = Process::path($path)->run('git branch --show-current');

        return trim($result->output());
    }

    private function getLocalBranches(string $path): array
    {
        $result = Process::path($path)->run("git branch --format='%(refname:short)'");

        return array_filter(array_map('trim', explode("\n", $result->output())));
    }

    private function getRemoteBranches(string $path): array
    {
        $result = Process::path($path)->run("git branch -r --format='%(refname:short)'");

        return array_filter(array_map('trim', explode("\n", $result->output())));
    }

    private function getAllBranches(string $path): array
    {
        $current = $this->getCurrentBranch($path);
        $local = $this->getLocalBranches($path);
        $remote = $this->getRemoteBranches($path);

        $branches = [];

        // Add local branches
        foreach ($local as $branch) {
            $branches[$branch] = $branch.($branch === $current ? ' (current)' : ' (local)');
        }

        // Add remote branches
        foreach ($remote as $branch) {
            $localName = preg_replace('/^[^\/]+\//', '', $branch);
            if (! isset($branches[$localName])) {
                $branches[$branch] = $branch.' (remote)';
            }
        }

        return $branches;
    }

    private function findRemoteBranch(string $branch, array $remoteBranches): ?string
    {
        // First check origin
        if (in_array("origin/{$branch}", $remoteBranches)) {
            return "origin/{$branch}";
        }

        // Then check upstream
        if (in_array("upstream/{$branch}", $remoteBranches)) {
            return "upstream/{$branch}";
        }

        return null;
    }

    private function listBranches(string $path): int
    {
        $current = $this->getCurrentBranch($path);
        $this->info("Current branch: {$current}");
        $this->newLine();

        $local = $this->getLocalBranches($path);
        if (! empty($local)) {
            $this->line('Local branches:');
            foreach ($local as $branch) {
                $this->line('  '.($branch === $current ? "* {$branch}" : "  {$branch}"));
            }
            $this->newLine();
        }

        $remote = $this->getRemoteBranches($path);
        if (! empty($remote)) {
            $this->line('Remote branches:');
            foreach ($remote as $branch) {
                $this->line("  {$branch}");
            }
        }

        return self::SUCCESS;
    }
}
