<?php

namespace App\Commands;

use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class QualityCommand extends ConduitCommand
{
    protected $signature = 'quality {--fix : Auto-fix code style issues} {--no-tests : Skip running tests} {--path= : Custom path to check} {--json : Output as JSON}';

    protected $description = '💩 Run quality checks (Pint, Larastan, Pest)';

    protected $hidden = false; // Always available, checks current directory

    private array $results = [];

    private array $issues = [
        'pint' => [],
        'stan' => [],
        'tests' => [],
    ];

    protected function executeCommand(): int
    {
        $this->title('💩 THE SHIT Quality Checks');

        $path = $this->option('path') ?? getcwd();

        // Check if we're in a valid Laravel/PHP project
        if (! file_exists($path.'/composer.json')) {
            error('No composer.json found in '.$path);
            note('Run this command in a Laravel/PHP project directory');

            return self::FAILURE;
        }

        // Check for vendor directory
        if (! is_dir($path.'/vendor')) {
            warning('No vendor directory found. Running composer install...');
            $this->task('Installing dependencies', function () use ($path) {
                $result = Process::path($path)->run('composer install');

                return $result->successful();
            });
        }

        $failed = false;

        // 1. Code Formatting with Pint
        $pintResult = $this->task('📝 Checking code formatting (Laravel Pint)', function () use ($path) {
            return $this->runPint($path);
        });

        if (! $pintResult) {
            $failed = true;
            $this->displayPintIssues();
            if (! $this->option('fix')) {
                note('💡 Run with --fix to auto-fix code style issues');
            }
        }

        // 2. Static Analysis with Larastan
        $stanResult = $this->task('🔍 Running static analysis (Larastan)', function () use ($path) {
            return $this->runStan($path);
        });

        if (! $stanResult) {
            $failed = true;
            $this->displayStanIssues();
        }

        // 3. Tests with Pest (optional)
        if (! $this->option('no-tests')) {
            $testResult = $this->task('🧪 Running tests (Pest)', function () use ($path) {
                return $this->runTests($path);
            });

            if (! $testResult) {
                $failed = true;
                $this->displayTestFailures();
            }
            $this->results['tests'] = $testResult;
        } else {
            $this->results['tests'] = 'skipped';
            note('Skipping tests (--no-tests flag)');
        }

        $this->newLine();

        if ($failed) {
            error('❌ Quality checks failed!');
            $this->newLine();
            info('Fix the issues above, then run:');
            $this->line('   php 💩 quality --fix');

            return self::FAILURE;
        }

        $this->info('✨ All quality checks passed!');
        $this->info('💩 Your code is THE SHIT (in a good way)!');

        if ($this->isNonInteractiveMode()) {
            return $this->jsonResponse([
                'results' => $this->results,
                'passed' => true,
                'issues' => $this->issues,
            ]);
        }

        return self::SUCCESS;
    }

    private function runPint(string $path): bool
    {
        $autoFix = $this->option('fix');

        // Check if Pint exists
        $pintPath = $path.'/vendor/bin/pint';
        if (! file_exists($pintPath)) {
            $this->results['pint'] = 'not-installed';

            return true; // Don't fail if Pint isn't installed
        }

        $command = $autoFix ? $pintPath : $pintPath.' --test';
        $result = Process::path($path)->timeout(60)->run($command);

        $this->results['pint'] = $result->successful();

        if (! $result->successful() && ! $autoFix) {
            $this->parsePintOutput($result->output());
            $this->results['pint_output'] = $result->output();
        }

        return $result->successful();
    }

    private function runStan(string $path): bool
    {
        // Check if PHPStan/Larastan exists
        $stanPath = $path.'/vendor/bin/phpstan';
        if (! file_exists($stanPath)) {
            $this->results['stan'] = 'not-installed';

            return true; // Don't fail if not installed
        }

        // Check for phpstan.neon config
        $configPath = $path.'/phpstan.neon';
        $command = file_exists($configPath)
            ? $stanPath.' analyse --memory-limit=512M'
            : $stanPath.' analyse --memory-limit=512M --level=5 app';

        $result = Process::path($path)->timeout(120)->run($command);

        $this->results['stan'] = $result->successful();

        if (! $result->successful()) {
            $this->parseStanOutput($result->output());
            $this->results['stan_output'] = $result->output();
        }

        return $result->successful();
    }

    private function runTests(string $path): bool
    {
        // Check if Pest exists
        $pestPath = $path.'/vendor/bin/pest';
        if (! file_exists($pestPath)) {
            // Try PHPUnit as fallback
            $phpunitPath = $path.'/vendor/bin/phpunit';
            if (! file_exists($phpunitPath)) {
                $this->results['tests'] = 'not-installed';

                return true; // Don't fail if no test runner
            }
            $testCommand = $phpunitPath;
        } else {
            $testCommand = $pestPath;
        }

        $result = Process::path($path)->timeout(180)->run($testCommand);

        $this->results['tests'] = $result->successful();

        if (! $result->successful()) {
            $this->parseTestOutput($result->output());
            $this->results['tests_output'] = $result->output();
        }

        return $result->successful();
    }

    private function parsePintOutput(string $output): void
    {
        // Parse Pint output for file issues
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            // Look for lines with file paths and rule violations
            if (preg_match('/^  ⨯ (.+?)\s+(.+)$/', $line, $matches)) {
                $this->issues['pint'][] = [
                    'file' => trim($matches[1]),
                    'rules' => trim($matches[2]),
                ];
            }
        }
    }

    private function parseStanOutput(string $output): void
    {
        // Parse PHPStan/Larastan output - it uses a table format with borders
        $lines = explode("\n", $output);
        $currentFile = null;

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            // Look for the header pattern " Line   filename.php "
            if (preg_match('/^\s*Line\s+(.+\.php)\s*$/', $line, $matches)) {
                $currentFile = trim($matches[1]);
                // Skip the separator line after header
                $i++;

                continue;
            }

            // Parse error lines when we have a current file
            if ($currentFile && preg_match('/^\s*(\d+)\s+(.+)$/', $line, $matches)) {
                $lineNum = $matches[1];
                $message = trim($matches[2]);

                // Add the error
                $errorIndex = count($this->issues['stan']);
                $this->issues['stan'][] = [
                    'file' => $currentFile,
                    'line' => $lineNum,
                    'message' => $message,
                ];

                // Check for continuation lines (indented lines that follow)
                while ($i + 1 < count($lines)) {
                    $nextLine = $lines[$i + 1];
                    // If it's indented and not a line number, it's a continuation
                    if (preg_match('/^\s{2,}(?!\d)(.+)$/', $nextLine, $contMatches)) {
                        $content = trim($contMatches[1]);
                        // Skip emoji/badge lines
                        if (! str_starts_with($content, '🪪') && ! str_starts_with($content, '------')) {
                            $this->issues['stan'][$errorIndex]['message'] .= ' '.$content;
                        }
                        $i++;
                    } else {
                        break;
                    }
                }
            }

            // Reset current file when we hit a separator after errors
            if ($currentFile && preg_match('/^\s*-+\s*$/', $line)) {
                $currentFile = null;
            }
        }
    }

    private function parseTestOutput(string $output): void
    {
        // Parse Pest/PHPUnit output for failures
        $lines = explode("\n", $output);
        $currentTest = null;

        foreach ($lines as $line) {
            // Look for FAILED test lines
            if (preg_match('/FAILED\s+(.+?)\s+›\s+(.+)/', $line, $matches)) {
                $this->issues['tests'][] = [
                    'file' => trim($matches[1]),
                    'test' => trim($matches[2]),
                ];
            }
            // Look for assertion failures
            elseif (preg_match('/Failed asserting that (.+)/', $line, $matches)) {
                if (! empty($this->issues['tests'])) {
                    $lastIndex = count($this->issues['tests']) - 1;
                    $this->issues['tests'][$lastIndex]['assertion'] = trim($matches[1]);
                }
            }
        }
    }

    private function displayPintIssues(): void
    {
        if (empty($this->issues['pint'])) {
            return;
        }

        $this->newLine();
        warning('📝 Code Style Issues (Laravel Pint)');

        $rows = [];
        foreach ($this->issues['pint'] as $issue) {
            $fullPath = getcwd().'/'.$issue['file'];
            $file = str_replace(getcwd().'/', '', $issue['file']);

            // Create clickable link for the file
            $clickableFile = $this->makeFileClickable($fullPath, $file);

            $rules = str_replace(',', ', ', $issue['rules']);
            // Truncate long rule lists
            if (strlen($rules) > 50) {
                $rules = substr($rules, 0, 47).'...';
            }
            $rows[] = [$clickableFile, $rules];
        }

        table(
            headers: ['File', 'Rules'],
            rows: $rows
        );
    }

    private function displayStanIssues(): void
    {
        if (empty($this->issues['stan'])) {
            return;
        }

        $this->newLine();
        warning('🔍 Static Analysis Issues (Larastan)');

        // Group by file
        $byFile = [];
        foreach ($this->issues['stan'] as $issue) {
            $file = str_replace(getcwd().'/', '', $issue['file']);
            if (! isset($byFile[$file])) {
                $byFile[$file] = [];
            }
            $byFile[$file][] = $issue;
        }

        foreach ($byFile as $file => $fileIssues) {
            $fullPath = getcwd().'/'.$file;
            $this->line('');

            // Make file header clickable
            $clickableFile = $this->makeFileClickable($fullPath, "📁 $file");
            info($clickableFile);

            $rows = [];
            foreach ($fileIssues as $issue) {
                // Create clickable line number that opens file at specific line
                $clickableLine = $this->makeFileClickable($fullPath.':'.$issue['line'], "Line {$issue['line']}");

                $message = $issue['message'];
                // Truncate very long messages
                if (strlen($message) > 80) {
                    $message = substr($message, 0, 77).'...';
                }
                $rows[] = [$clickableLine, $message];
            }

            table(
                headers: ['Location', 'Issue'],
                rows: $rows
            );
        }

        $totalErrors = count($this->issues['stan']);
        $this->newLine();
        error("Found $totalErrors static analysis ".($totalErrors === 1 ? 'error' : 'errors'));
    }

    private function displayTestFailures(): void
    {
        if (empty($this->issues['tests'])) {
            return;
        }

        $this->newLine();
        warning('🧪 Test Failures');

        $rows = [];
        foreach ($this->issues['tests'] as $failure) {
            $fullPath = getcwd().'/'.$failure['file'];
            $file = str_replace(getcwd().'/', '', $failure['file']);

            // Create clickable link for test file
            $clickableFile = $this->makeFileClickable($fullPath, $file);

            $test = $failure['test'];
            if (isset($failure['assertion'])) {
                $test .= "\n  → ".$failure['assertion'];
            }
            $rows[] = [$clickableFile, $test];
        }

        table(
            headers: ['Test File', 'Failed Test'],
            rows: $rows
        );

        $totalFailures = count($this->issues['tests']);
        $this->newLine();
        error("$totalFailures test".($totalFailures === 1 ? '' : 's').' failed');
    }

    /**
     * Create a clickable terminal link using OSC 8 hyperlinks
     * Works in modern terminals like iTerm2, VS Code terminal, etc.
     */
    private function makeFileClickable(string $path, string $text): string
    {
        // Check if terminal supports hyperlinks
        if (! $this->terminalSupportsHyperlinks()) {
            return $text;
        }

        // Create file:// URL for the path
        // Many editors register as handlers for file:// URLs with line numbers
        $url = 'file://'.$path;

        // OSC 8 hyperlink format: \e]8;;URL\e\\TEXT\e]8;;\e\\
        return "\e]8;;{$url}\e\\{$text}\e]8;;\e\\";
    }

    /**
     * Check if terminal supports hyperlinks
     */
    private function terminalSupportsHyperlinks(): bool
    {
        // Check common terminal environment variables
        $term = getenv('TERM_PROGRAM');
        $terminalApp = getenv('TERMINAL_EMULATOR');

        // List of terminals known to support OSC 8 hyperlinks
        $supportedTerminals = [
            'iTerm.app',
            'vscode',
            'WezTerm',
            'Hyper',
            'Tabby',
            'Alacritty', // With config
            'kitty',
        ];

        foreach ($supportedTerminals as $supported) {
            if (stripos($term, $supported) !== false || stripos($terminalApp, $supported) !== false) {
                return true;
            }
        }

        // Check if we're in VS Code integrated terminal
        if (getenv('VSCODE_GIT_IPC_HANDLE') || getenv('TERM_PROGRAM_VERSION')) {
            return true;
        }

        // Default to true for modern terminals, user can disable if needed
        return true;
    }
}
