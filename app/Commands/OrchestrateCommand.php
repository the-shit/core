<?php

namespace App\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class OrchestrateCommand extends ConduitCommand
{
    protected $signature = 'orchestrate 
                            {action? : Action to perform (status|assign|release|conflicts|dashboard|register|check|update)}
                            {--instance= : Claude instance identifier}
                            {--task= : Task description or ID}
                            {--files=* : Files being worked on}
                            {--watch : Watch for changes in real-time}
                            {--resolve : Auto-resolve conflicts}
                            {--tmux= : Tmux session name}
                            {--pid= : Process ID}
                            {--check-file= : Check if file is available for editing}
                            {--action-type= : Type of action (edit|read|create)}
                            {--heartbeat : Send heartbeat update}';

    protected $description = '🎭 Orchestrate multiple Claude Code instances - prevent conflicts, track work';

    private string $lockDir;

    private string $stateFile;

    private string $conflictLog;

    public function __construct()
    {
        parent::__construct();
        $this->lockDir = storage_path('orchestration/locks');
        $this->stateFile = storage_path('orchestration/state.json');
        $this->conflictLog = storage_path('orchestration/conflicts.jsonl');
    }

    protected function executeCommand(): int
    {
        $this->ensureOrchestrationDirectories();

        $action = $this->argument('action') ?? 'dashboard';

        return match ($action) {
            'status' => $this->showStatus(),
            'assign' => $this->assignWork(),
            'release' => $this->releaseWork(),
            'conflicts' => $this->detectConflicts(),
            'dashboard' => $this->showDashboard(),
            'register' => $this->registerInstance(),
            'check' => $this->checkFileAvailability(),
            'update' => $this->updateInstance(),
            default => $this->showHelp()
        };
    }

    private function ensureOrchestrationDirectories(): void
    {
        File::ensureDirectoryExists($this->lockDir);
        File::ensureDirectoryExists(dirname($this->stateFile));

        if (! File::exists($this->stateFile)) {
            File::put($this->stateFile, json_encode([
                'instances' => [],
                'tasks' => [],
                'started_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));
        }
    }

    private function showStatus(): int
    {
        $state = $this->getState();

        if ($this->isNonInteractiveMode()) {
            return $this->jsonResponse($state);
        }

        $this->title('🎭 Orchestration Status');

        // Active instances
        $activeInstances = collect($state['instances'])
            ->filter(fn ($i) => $i['status'] === 'active');

        if ($activeInstances->isEmpty()) {
            $this->warn('No active Claude instances');
        } else {
            $this->info("Active Instances: {$activeInstances->count()}");

            $this->table(
                ['Instance', 'Task', 'Files', 'Started', 'Duration'],
                $activeInstances->map(function ($instance) {
                    $started = Carbon::parse($instance['started_at']);

                    return [
                        $instance['id'],
                        str($instance['task'] ?? 'No task')->limit(40),
                        count($instance['files'] ?? []),
                        $started->format('H:i:s'),
                        $started->diffForHumans(null, true),
                    ];
                })->toArray()
            );
        }

        // File locks
        $this->showFileLocks();

        return self::SUCCESS;
    }

    private function assignWork(): int
    {
        $instanceId = $this->option('instance') ?? $this->generateInstanceId();
        $task = $this->option('task');
        $files = $this->option('files');

        if (! $task) {
            $task = $this->smartText('What task is this instance working on?');
        }

        // Check for conflicts
        $conflicts = $this->checkFileConflicts($files);
        if (! empty($conflicts)) {
            $this->error('⚠️  File conflicts detected!');
            foreach ($conflicts as $file => $owner) {
                $this->line("  • {$file} is locked by {$owner}");
            }

            if (! $this->option('resolve') && ! $this->smartConfirm('Continue anyway?', false)) {
                return self::FAILURE;
            }
        }

        // Lock files
        foreach ($files as $file) {
            $this->lockFile($file, $instanceId);
        }

        // Update state
        $state = $this->getState();
        $state['instances'][$instanceId] = [
            'id' => $instanceId,
            'task' => $task,
            'files' => $files,
            'status' => 'active',
            'started_at' => now()->toIso8601String(),
            'pid' => getmypid(),
        ];

        $state['tasks'][] = [
            'id' => uniqid('task_'),
            'instance' => $instanceId,
            'description' => $task,
            'created_at' => now()->toIso8601String(),
        ];

        $this->saveState($state);

        if ($this->isNonInteractiveMode()) {
            return $this->jsonResponse(['assigned' => $instanceId, 'files' => $files]);
        }

        $this->info("✅ Work assigned to instance: {$instanceId}");
        $this->info("Task: {$task}");
        if (! empty($files)) {
            $this->line('Files locked: '.implode(', ', $files));
        }

        return self::SUCCESS;
    }

    private function releaseWork(): int
    {
        $instanceId = $this->option('instance');

        if (! $instanceId) {
            $state = $this->getState();
            $activeInstances = collect($state['instances'])
                ->filter(fn ($i) => $i['status'] === 'active')
                ->pluck('id')
                ->toArray();

            if (empty($activeInstances)) {
                $this->warn('No active instances to release');

                return self::SUCCESS;
            }

            $instanceId = $this->smartChoice(
                'Which instance to release?',
                $activeInstances,
                $activeInstances[0]
            );
        }

        // Release file locks
        $state = $this->getState();
        $instance = $state['instances'][$instanceId] ?? null;

        if (! $instance) {
            $this->error("Instance not found: {$instanceId}");

            return self::FAILURE;
        }

        foreach ($instance['files'] ?? [] as $file) {
            $this->unlockFile($file, $instanceId);
        }

        // Update state
        $state['instances'][$instanceId]['status'] = 'completed';
        $state['instances'][$instanceId]['completed_at'] = now()->toIso8601String();
        $this->saveState($state);

        if ($this->isNonInteractiveMode()) {
            return $this->jsonResponse(['released' => $instanceId]);
        }

        $this->info("✅ Released instance: {$instanceId}");

        return self::SUCCESS;
    }

    private function detectConflicts(): int
    {
        $state = $this->getState();
        $activeInstances = collect($state['instances'])
            ->filter(fn ($i) => $i['status'] === 'active');

        $fileMap = [];
        $conflicts = [];

        // Build file ownership map
        foreach ($activeInstances as $instance) {
            foreach ($instance['files'] ?? [] as $file) {
                if (isset($fileMap[$file])) {
                    $conflicts[] = [
                        'file' => $file,
                        'instances' => [$fileMap[$file], $instance['id']],
                        'detected_at' => now()->toIso8601String(),
                    ];
                }
                $fileMap[$file] = $instance['id'];
            }
        }

        // Log conflicts
        if (! empty($conflicts)) {
            foreach ($conflicts as $conflict) {
                File::append($this->conflictLog, json_encode($conflict)."\n");
            }
        }

        if ($this->isNonInteractiveMode()) {
            return $this->jsonResponse(['conflicts' => $conflicts]);
        }

        if (empty($conflicts)) {
            $this->info('✅ No conflicts detected');
        } else {
            $this->error('⚠️  Conflicts detected!');
            $this->table(
                ['File', 'Conflicting Instances'],
                collect($conflicts)->map(fn ($c) => [
                    $c['file'],
                    implode(' vs ', $c['instances']),
                ])->toArray()
            );

            if ($this->option('resolve')) {
                $this->resolveConflicts($conflicts);
            }
        }

        return empty($conflicts) ? self::SUCCESS : self::FAILURE;
    }

    private function showDashboard(): int
    {
        if ($this->option('watch')) {
            return $this->watchDashboard();
        }

        $state = $this->getState();

        $this->title('🎭 THE SHIT Orchestration Dashboard');

        // Statistics
        $stats = [
            'Active' => collect($state['instances'])->where('status', 'active')->count(),
            'Completed' => collect($state['instances'])->where('status', 'completed')->count(),
            'Total Tasks' => count($state['tasks'] ?? []),
            'Locked Files' => count(File::files($this->lockDir)),
        ];

        $this->table(['Metric', 'Value'], collect($stats)->map(fn ($v, $k) => [$k, $v])->toArray());

        // Active work
        $this->newLine();
        $this->line('<fg=cyan>═══ Active Work ═══</>');

        $activeInstances = collect($state['instances'])
            ->filter(fn ($i) => $i['status'] === 'active');

        if ($activeInstances->isEmpty()) {
            $this->line('  <fg=gray>No active instances</>');
        } else {
            foreach ($activeInstances as $instance) {
                $this->line("  <fg=green>●</> {$instance['id']}");
                $this->line("    Task: <fg=yellow>{$instance['task']}</>");
                if (! empty($instance['files'])) {
                    $this->line('    Files: '.implode(', ', array_map(fn ($f) => basename($f), $instance['files'])));
                }
                $this->line('    Started: '.Carbon::parse($instance['started_at'])->diffForHumans());
            }
        }

        // Recent conflicts
        if (File::exists($this->conflictLog)) {
            $recentConflicts = collect(explode("\n", File::get($this->conflictLog)))
                ->filter()
                ->map(fn ($line) => json_decode($line, true))
                ->take(-5);

            if ($recentConflicts->isNotEmpty()) {
                $this->newLine();
                $this->line('<fg=red>═══ Recent Conflicts ═══</>');
                foreach ($recentConflicts as $conflict) {
                    $this->line("  ⚠️  {$conflict['file']} ({$conflict['instances'][0]} vs {$conflict['instances'][1]})");
                }
            }
        }

        // Suggestions
        $this->newLine();
        $this->line('<fg=magenta>═══ Commands ═══</>');
        $this->line('  <fg=gray>php 💩 orchestrate assign --task="Building feature X" --files=app/Feature.php</>');
        $this->line('  <fg=gray>php 💩 orchestrate release --instance=claude_abc123</>');
        $this->line('  <fg=gray>php 💩 orchestrate conflicts --resolve</>');
        $this->line('  <fg=gray>php 💩 orchestrate dashboard --watch</>');

        return self::SUCCESS;
    }

    private function watchDashboard(): int
    {
        $this->info('Watching orchestration status... (Ctrl+C to stop)');

        while (true) {
            Process::run('clear');
            $this->showDashboard();
            sleep(2);
        }
    }

    private function lockFile(string $file, string $instanceId): void
    {
        $lockFile = $this->lockDir.'/'.md5($file).'.lock';
        File::put($lockFile, json_encode([
            'file' => $file,
            'instance' => $instanceId,
            'locked_at' => now()->toIso8601String(),
        ]));
    }

    private function unlockFile(string $file, string $instanceId): void
    {
        $lockFile = $this->lockDir.'/'.md5($file).'.lock';

        if (File::exists($lockFile)) {
            $lock = json_decode(File::get($lockFile), true);
            if ($lock['instance'] === $instanceId) {
                File::delete($lockFile);
            }
        }
    }

    private function checkFileConflicts(array $files): array
    {
        $conflicts = [];

        foreach ($files as $file) {
            $lockFile = $this->lockDir.'/'.md5($file).'.lock';
            if (File::exists($lockFile)) {
                $lock = json_decode(File::get($lockFile), true);
                $conflicts[$file] = $lock['instance'];
            }
        }

        return $conflicts;
    }

    private function showFileLocks(): void
    {
        $locks = collect(File::files($this->lockDir))
            ->map(function ($file) {
                $lock = json_decode(File::get($file), true);

                return [
                    'file' => basename($lock['file']),
                    'instance' => $lock['instance'],
                    'locked_at' => Carbon::parse($lock['locked_at'])->diffForHumans(),
                ];
            });

        if ($locks->isNotEmpty()) {
            $this->newLine();
            $this->info('File Locks:');
            $this->table(['File', 'Locked By', 'Since'], $locks->toArray());
        }
    }

    private function resolveConflicts(array $conflicts): void
    {
        $this->info('Attempting to resolve conflicts...');

        foreach ($conflicts as $conflict) {
            // Simple resolution: release the older lock
            $instances = collect($conflict['instances'])
                ->map(fn ($id) => $this->getState()['instances'][$id] ?? null)
                ->filter()
                ->sortBy('started_at');

            $older = $instances->first();
            if ($older) {
                $this->warn("Releasing older lock from {$older['id']}");
                foreach ($older['files'] ?? [] as $file) {
                    if ($file === $conflict['file']) {
                        $this->unlockFile($file, $older['id']);
                    }
                }
            }
        }

        $this->info('✅ Conflicts resolved');
    }

    private function generateInstanceId(): string
    {
        return 'claude_'.substr(uniqid(), -6).'_'.getmypid();
    }

    private function getState(): array
    {
        return json_decode(File::get($this->stateFile), true);
    }

    private function saveState(array $state): void
    {
        File::put($this->stateFile, json_encode($state, JSON_PRETTY_PRINT));
    }

    private function registerInstance(): int
    {
        $instanceId = $this->option('instance') ?? $this->generateClaudeInstanceId();
        $tmuxSession = $this->option('tmux') ?? $this->detectTmuxSession();
        $pid = $this->option('pid') ?? getmypid();

        $state = $this->getState();

        // Check if instance already exists
        if (isset($state['instances'][$instanceId]) && $state['instances'][$instanceId]['status'] === 'active') {
            if ($this->isNonInteractiveMode()) {
                return $this->jsonResponse(['error' => 'Instance already registered', 'instance' => $instanceId]);
            }
            $this->warn("Instance already registered: {$instanceId}");

            return self::FAILURE;
        }

        // Register the instance
        $state['instances'][$instanceId] = [
            'id' => $instanceId,
            'status' => 'active',
            'task' => 'Claude Code Session',
            'files' => [],
            'tmux_session' => $tmuxSession,
            'pid' => $pid,
            'started_at' => now()->toIso8601String(),
            'last_heartbeat' => now()->toIso8601String(),
            'environment' => [
                'user' => $_ENV['USER'] ?? 'unknown',
                'term' => $_ENV['TERM'] ?? 'unknown',
                'claude_version' => $_ENV['CLAUDE_VERSION'] ?? 'unknown',
            ],
        ];

        $this->saveState($state);

        if ($this->isNonInteractiveMode()) {
            return $this->jsonResponse(['registered' => $instanceId, 'tmux' => $tmuxSession]);
        }

        $this->info("✅ Registered Claude instance: {$instanceId}");
        if ($tmuxSession) {
            $this->line("  Tmux session: {$tmuxSession}");
        }
        $this->line("  PID: {$pid}");

        return self::SUCCESS;
    }

    private function checkFileAvailability(): int
    {
        $file = $this->option('check-file');
        $instanceId = $this->option('instance') ?? $this->generateClaudeInstanceId();
        $actionType = $this->option('action-type') ?? 'edit';

        if (! $file) {
            if ($this->isNonInteractiveMode()) {
                return $this->jsonResponse(['error' => 'No file specified']);
            }
            $this->error('No file specified to check');

            return self::FAILURE;
        }

        // For read operations, always allow
        if ($actionType === 'read') {
            if ($this->isNonInteractiveMode()) {
                return $this->jsonResponse(['available' => true, 'action' => 'read']);
            }

            return self::SUCCESS;
        }

        // Check for conflicts
        $lockFile = $this->lockDir.'/'.md5($file).'.lock';

        if (File::exists($lockFile)) {
            $lock = json_decode(File::get($lockFile), true);

            // If it's our own lock, allow
            if ($lock['instance'] === $instanceId) {
                if ($this->isNonInteractiveMode()) {
                    return $this->jsonResponse(['available' => true, 'own_lock' => true]);
                }

                return self::SUCCESS;
            }

            // Check if the locking instance is still alive
            if ($this->isInstanceAlive($lock['instance'])) {
                if ($this->isNonInteractiveMode()) {
                    return $this->jsonResponse([
                        'available' => false,
                        'locked_by' => $lock['instance'],
                        'locked_at' => $lock['locked_at'],
                        'conflict' => true,
                    ]);
                }

                $this->error("⚠️  CONFLICT: {$file} is being edited by {$lock['instance']}");
                $this->line('  Locked at: '.Carbon::parse($lock['locked_at'])->diffForHumans());

                // Log the conflict
                File::append($this->conflictLog, json_encode([
                    'file' => $file,
                    'requested_by' => $instanceId,
                    'locked_by' => $lock['instance'],
                    'action' => $actionType,
                    'timestamp' => now()->toIso8601String(),
                ])."\n");

                return self::FAILURE;
            } else {
                // Instance is dead, clean up the lock
                $this->unlockFile($file, $lock['instance']);
            }
        }

        // File is available, lock it for this instance if it's an edit
        if ($actionType === 'edit' || $actionType === 'create') {
            $this->lockFile($file, $instanceId);

            // Update instance's file list
            $state = $this->getState();
            if (isset($state['instances'][$instanceId])) {
                if (! in_array($file, $state['instances'][$instanceId]['files'] ?? [])) {
                    $state['instances'][$instanceId]['files'][] = $file;
                    $state['instances'][$instanceId]['last_heartbeat'] = now()->toIso8601String();
                    $this->saveState($state);
                }
            }
        }

        if ($this->isNonInteractiveMode()) {
            return $this->jsonResponse(['available' => true, 'locked' => true]);
        }

        return self::SUCCESS;
    }

    private function updateInstance(): int
    {
        $instanceId = $this->option('instance');

        if (! $instanceId) {
            if ($this->isNonInteractiveMode()) {
                return $this->jsonResponse(['error' => 'No instance ID provided']);
            }
            $this->error('No instance ID provided');

            return self::FAILURE;
        }

        $state = $this->getState();

        if (! isset($state['instances'][$instanceId])) {
            if ($this->isNonInteractiveMode()) {
                return $this->jsonResponse(['error' => 'Instance not found']);
            }
            $this->error("Instance not found: {$instanceId}");

            return self::FAILURE;
        }

        // Update heartbeat
        if ($this->option('heartbeat')) {
            $state['instances'][$instanceId]['last_heartbeat'] = now()->toIso8601String();
        }

        // Update task if provided
        if ($task = $this->option('task')) {
            $state['instances'][$instanceId]['task'] = $task;
        }

        // Update files if provided
        if ($files = $this->option('files')) {
            $state['instances'][$instanceId]['files'] = array_unique(
                array_merge($state['instances'][$instanceId]['files'] ?? [], $files)
            );
        }

        $this->saveState($state);

        if ($this->isNonInteractiveMode()) {
            return $this->jsonResponse(['updated' => $instanceId]);
        }

        return self::SUCCESS;
    }

    private function isInstanceAlive(string $instanceId): bool
    {
        $state = $this->getState();
        $instance = $state['instances'][$instanceId] ?? null;

        if (! $instance || $instance['status'] !== 'active') {
            return false;
        }

        // Check heartbeat (consider dead if no heartbeat for 5 minutes)
        $lastHeartbeat = Carbon::parse($instance['last_heartbeat'] ?? $instance['started_at']);
        if ($lastHeartbeat->diffInMinutes(now()) > 5) {
            return false;
        }

        // Check if PID is still running (if local)
        if (isset($instance['pid'])) {
            $pidCheck = Process::run("ps -p {$instance['pid']}");
            if (! $pidCheck->successful()) {
                return false;
            }
        }

        // Check tmux session if present
        if (isset($instance['tmux_session']) && $instance['tmux_session']) {
            $tmuxCheck = Process::run("tmux has-session -t '{$instance['tmux_session']}' 2>/dev/null");
            if (! $tmuxCheck->successful()) {
                return false;
            }
        }

        return true;
    }

    private function detectTmuxSession(): ?string
    {
        // Check if we're in a tmux session
        if (isset($_ENV['TMUX'])) {
            $result = Process::run('tmux display-message -p "#S"');
            if ($result->successful()) {
                return trim($result->output());
            }
        }

        return null;
    }

    private function generateClaudeInstanceId(): string
    {
        $tmux = $this->detectTmuxSession();
        $suffix = $tmux ? str_replace([' ', '-'], '_', $tmux) : substr(uniqid(), -6);

        return 'claude_'.$suffix.'_'.getmypid();
    }

    private function showHelp(): int
    {
        $this->title('🎭 Orchestration Help');

        $this->line('Available actions:');
        $this->line('  <fg=cyan>status</>    - Show current orchestration status');
        $this->line('  <fg=cyan>assign</>    - Assign work to a Claude instance');
        $this->line('  <fg=cyan>release</>   - Release work from an instance');
        $this->line('  <fg=cyan>conflicts</> - Detect and resolve file conflicts');
        $this->line('  <fg=cyan>dashboard</> - Show orchestration dashboard');

        $this->newLine();
        $this->line('Examples:');
        $this->line('  php 💩 orchestrate assign --task="Refactor auth" --files=app/Auth.php');
        $this->line('  php 💩 orchestrate release --instance=claude_abc123');
        $this->line('  php 💩 orchestrate conflicts --resolve');
        $this->line('  php 💩 orchestrate dashboard --watch');

        return self::SUCCESS;
    }
}
