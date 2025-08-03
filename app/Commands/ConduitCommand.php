<?php

namespace App\Commands;

use Laravel\Prompts\Exceptions\NonInteractiveValidationException;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/**
 * Base command for Human-AI collaboration pattern
 * 
 * This architecture enables seamless interaction between:
 * - Jordan (Human) → Beautiful interactive prompts  
 * - Claude (AI) → JSON output and non-interactive defaults
 * - CI/CD → Automated execution with environment variables
 */
abstract class ConduitCommand extends Command
{
    /**
     * Execute the command - override this instead of handle()
     */
    abstract protected function executeCommand(): int;

    /**
     * Laravel Zero's handle method - we intercept this for smart routing
     */
    final public function handle(): int
    {
        // Detect interaction mode early
        if ($this->isNonInteractiveMode()) {
            return $this->executeNonInteractive();
        }

        // Interactive mode with graceful fallback
        try {
            return $this->executeCommand();
        } catch (NonInteractiveValidationException $e) {
            // Graceful fallback to non-interactive if prompts fail
            $this->warn('Falling back to non-interactive mode due to prompt failure');
            return $this->executeNonInteractive();
        }
    }

    /**
     * Execute in non-interactive mode (for AI agents, CI/CD)
     * Override this for specialized AI behavior
     */
    protected function executeNonInteractive(): int
    {
        // Default: call the normal execute with fallback handling
        return $this->executeCommand();
    }

    /**
     * Detect if we're in non-interactive/agent mode
     */
    protected function isNonInteractiveMode(): bool
    {
        return !$this->input->isInteractive() ||
               in_array('--no-interaction', $GLOBALS['argv'] ?? []) ||
               in_array('-n', $GLOBALS['argv'] ?? []) ||
               $this->getUserAgent() !== 'human';
    }

    /**
     * Get the user agent (human, claude, ai, ci, etc.)
     */
    protected function getUserAgent(): string
    {
        return $_SERVER['CONDUIT_USER_AGENT'] ?? 
               env('CONDUIT_USER_AGENT') ?? 
               'human';
    }

    /**
     * Smart text prompt that works for both humans and AI
     */
    protected function smartText(
        string $label,
        string $placeholder = '',
        mixed $default = '',
        bool $required = false,
        string $hint = '',
        callable $validate = null
    ): string {
        // In non-interactive mode, return default immediately
        if ($this->isNonInteractiveMode()) {
            if (empty($default) && $required) {
                throw new \RuntimeException("Non-interactive mode requires a default value for: {$label}");
            }
            return (string) $default;
        }

        // Interactive mode - use Laravel Prompts
        return text(
            label: $label,
            placeholder: $placeholder,
            default: $default,
            required: $required,
            hint: $hint,
            validate: $validate
        );
    }

    /**
     * Smart confirm that works for both humans and AI
     */
    protected function smartConfirm(
        string $label,
        bool $default = true,
        string $yes = 'Yes',
        string $no = 'No',
        bool $required = true,
        string $hint = ''
    ): bool {
        // In non-interactive mode, return default immediately
        if ($this->isNonInteractiveMode()) {
            return $default;
        }

        // Interactive mode - use Laravel Prompts
        return confirm(
            label: $label,
            default: $default,
            yes: $yes,
            no: $no,
            required: $required,
            hint: $hint
        );
    }

    /**
     * Smart output that adapts to the interaction mode
     */
    protected function smartOutput(array $data, string $humanMessage = ''): void
    {
        if ($this->option('json') || $this->getUserAgent() !== 'human') {
            $this->line(json_encode($data, JSON_PRETTY_PRINT));
        } else {
            if ($humanMessage) {
                $this->info($humanMessage);
            }
            foreach ($data as $key => $value) {
                $this->line("   " . ucwords(str_replace('_', ' ', $key)) . ": {$value}");
            }
        }
    }

    /**
     * Smart info message (only shows in human mode)
     */
    protected function smartInfo(string $message): void
    {
        if (!$this->isNonInteractiveMode()) {
            $this->info($message);
        }
    }

    /**
     * Smart line (only shows in human mode unless forced)
     */
    protected function smartLine(string $message, bool $force = false): void
    {
        if (!$this->isNonInteractiveMode() || $force) {
            $this->line($message);
        }
    }

    /**
     * Smart newline (only in human mode)
     */
    protected function smartNewLine(): void
    {
        if (!$this->isNonInteractiveMode()) {
            $this->newLine();
        }
    }

    /**
     * Force a message in both modes (errors, warnings)
     */
    protected function forceOutput(string $message, string $type = 'line'): void
    {
        match($type) {
            'info' => $this->info($message),
            'error' => $this->error($message),
            'warn' => $this->warn($message),
            default => $this->line($message)
        };
    }

    /**
     * Standard JSON response for AI agents
     */
    protected function jsonResponse(array $data, int $status = self::SUCCESS): int
    {
        if ($this->isNonInteractiveMode() || $this->option('json')) {
            $response = [
                'status' => $status === self::SUCCESS ? 'success' : 'error',
                'data' => $data
            ];
            $this->line(json_encode($response, JSON_PRETTY_PRINT));
        }
        return $status;
    }
}
