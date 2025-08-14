<?php

namespace App\Commands;

use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;

class ComponentSyncCommand extends ConduitCommand
{
    protected $signature = 'component:sync {component : Component to sync} {--branch= : Specific branch to sync}';

    protected $description = 'Pull latest changes from upstream repository';

    protected function executeCommand(): int
    {
        $component = $this->argument('component');
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

        // Check for uncommitted changes
        $status = Process::path($componentPath)->run('git status --porcelain');
        if (! empty(trim($status->output()))) {
            $this->warn('You have uncommitted changes:');
            $this->line($status->output());

            if (! confirm('Stash changes and continue?')) {
                return self::FAILURE;
            }

            Process::path($componentPath)->run('git stash');
            $stashed = true;
        }

        // Get current branch
        $currentBranch = trim(Process::path($componentPath)->run('git branch --show-current')->output());
        $targetBranch = $this->option('branch') ?? $currentBranch;

        // Check if upstream exists
        $remotes = Process::path($componentPath)->run('git remote')->output();
        if (! str_contains($remotes, 'upstream')) {
            $this->error('No upstream remote configured');
            $this->info("Run: php 💩 component:fork {$component} --setup-only");

            return self::FAILURE;
        }

        // Fetch upstream
        $this->info('📥 Fetching upstream changes...');

        $fetched = spin(
            function () use ($componentPath) {
                $result = Process::path($componentPath)->timeout(60)->run('git fetch upstream');

                return $result->successful();
            },
            'Fetching from upstream...'
        );

        if (! $fetched) {
            $this->error('Failed to fetch upstream');

            return self::FAILURE;
        }

        // Show what's new
        $behind = Process::path($componentPath)
            ->run("git rev-list --count HEAD..upstream/{$targetBranch} 2>/dev/null")
            ->output();

        $behind = (int) trim($behind);

        if ($behind === 0) {
            $this->info("✅ Already up to date with upstream/{$targetBranch}");

            if (isset($stashed)) {
                Process::path($componentPath)->run('git stash pop');
                $this->info('📦 Restored stashed changes');
            }

            return self::SUCCESS;
        }

        $this->info("📊 Your branch is {$behind} commits behind upstream/{$targetBranch}");

        // Show commit preview
        $commits = Process::path($componentPath)
            ->run("git log HEAD..upstream/{$targetBranch} --oneline --max-count=10")
            ->output();

        if (! empty($commits)) {
            $this->line('Recent upstream commits:');
            foreach (explode("\n", trim($commits)) as $commit) {
                $this->line("  {$commit}");
            }

            if ($behind > 10) {
                $this->line('  ... and '.($behind - 10).' more');
            }
        }

        // Merge or rebase
        $this->newLine();
        $strategy = confirm(
            'Merge upstream changes? (No = rebase instead)',
            default: true
        ) ? 'merge' : 'rebase';

        if ($strategy === 'merge') {
            $result = Process::path($componentPath)
                ->run("git merge upstream/{$targetBranch}");
        } else {
            $result = Process::path($componentPath)
                ->run("git rebase upstream/{$targetBranch}");
        }

        if (! $result->successful()) {
            $this->error('Sync failed with conflicts');
            $this->line($result->errorOutput());
            $this->newLine();
            $this->warn('Resolve conflicts manually, then:');

            if ($strategy === 'merge') {
                $this->line('  git add .');
                $this->line('  git commit');
            } else {
                $this->line('  git add .');
                $this->line('  git rebase --continue');
            }

            return self::FAILURE;
        }

        $this->info("✅ Successfully synced with upstream/{$targetBranch}");

        // Push to origin if it exists
        $hasOrigin = str_contains(
            Process::path($componentPath)->run('git remote')->output(),
            'origin'
        );

        if ($hasOrigin && confirm('Push updates to your fork?')) {
            $pushResult = Process::path($componentPath)->run("git push origin {$currentBranch}");

            if ($pushResult->successful()) {
                $this->info("✅ Pushed to origin/{$currentBranch}");
            } else {
                $this->warn("Failed to push: {$pushResult->errorOutput()}");
            }
        }

        // Restore stashed changes
        if (isset($stashed)) {
            $popResult = Process::path($componentPath)->run('git stash pop');

            if ($popResult->successful()) {
                $this->info('📦 Restored stashed changes');
            } else {
                $this->warn('Failed to restore stashed changes');
                $this->line('Run: git stash pop');
            }
        }

        return self::SUCCESS;
    }
}
