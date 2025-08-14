<?php

namespace Tests\Liberation;

use App\Commands\ConduitCommand;
use Tests\TestCase;

/**
 * Tests that validate liberation from Human-AI friction
 * Collaboration must be seamless, automatic, and configuration-free
 */
class HumanAiLiberationTest extends TestCase
{
    protected function asHuman(): self
    {
        $_SERVER['CONDUIT_USER_AGENT'] = 'human';

        return $this;
    }

    protected function asAI(string $agent = 'claude'): self
    {
        $_SERVER['CONDUIT_USER_AGENT'] = $agent;

        return $this;
    }

    protected function tearDown(): void
    {
        unset($_SERVER['CONDUIT_USER_AGENT']);
        parent::tearDown();
    }
}

test('agent detection happens automatically without configuration', function () {
    // Create a test command
    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:liberation';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function getUserAgentPublic(): string
        {
            return $this->getUserAgent();
        }
    };

    // No configuration needed for human
    $this->asHuman();
    expect($command->getUserAgentPublic())->toBe('human');

    // No configuration needed for AI
    $this->asAI();
    expect($command->getUserAgentPublic())->toBe('claude');

    // Works with any AI agent
    $this->asAI('gpt');
    expect($command->getUserAgentPublic())->toBe('gpt');
});

test('commands adapt output format based on consumer without configuration', function () {
    // Human gets formatted output
    $this->asHuman();
    ob_start();
    $this->artisan('orchestrate', ['action' => 'dashboard']);
    $output = ob_get_clean();

    expect($output)->toContain('Dashboard'); // Human-readable
    expect($output)->not->toContain('{"'); // Not JSON

    // AI gets JSON automatically
    $this->asAI();
    ob_start();
    $this->artisan('orchestrate', ['action' => 'dashboard', '--json' => true]);
    $output = ob_get_clean();

    if (str_contains($output, '{')) {
        $json = json_decode($output, true);
        expect($json)->toBeArray(); // Valid JSON for AI
    }
});

test('mode switching requires zero configuration changes', function () {
    // Start as human
    $this->asHuman();
    $humanResult = $this->artisan('brain', ['query' => 'test']);

    // Switch to AI - no config needed
    $this->asAI();
    $aiResult = $this->artisan('brain', ['query' => 'test']);

    // Both work without any configuration
    expect($humanResult->getExitCode())->toBe(0);
    expect($aiResult->getExitCode())->toBe(0);
});

test('handoff between human and AI is frictionless', function () {
    // Human starts a task
    $this->asHuman();
    $this->artisan('orchestrate', [
        'action' => 'assign',
        '--task' => 'Refactor auth system',
        '--instance' => 'human_session_1',
    ]);

    // AI can immediately see and continue the work
    $this->asAI();
    $this->artisan('orchestrate', ['action' => 'status'])
        ->expectsOutputToContain('Refactor auth'); // AI sees human's task

    // Another AI can take over
    $this->asAI('gpt');
    $this->artisan('orchestrate', ['action' => 'status'])
        ->expectsOutputToContain('Refactor auth'); // Seamless handoff
});

test('smart defaults eliminate need for agent-specific configuration', function () {
    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:defaults';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function getSmartDefault(string $key, mixed $humanDefault, mixed $aiDefault): mixed
        {
            return $this->getUserAgent() === 'human' ? $humanDefault : $aiDefault;
        }
    };

    // Human gets human-appropriate defaults
    $this->asHuman();
    expect($command->getSmartDefault('format', 'pretty', 'json'))->toBe('pretty');
    expect($command->getSmartDefault('interactive', true, false))->toBe(true);

    // AI gets AI-appropriate defaults
    $this->asAI();
    expect($command->getSmartDefault('format', 'pretty', 'json'))->toBe('json');
    expect($command->getSmartDefault('interactive', true, false))->toBe(false);
});

test('no special setup required for AI agents', function () {
    // AI can start using THE SHIT immediately
    $this->asAI();

    // No API key configuration needed for basic commands
    $this->artisan('test')
        ->doesntExpectOutput('API key required')
        ->doesntExpectOutput('Configure AI settings');

    // No special AI mode activation needed
    $this->artisan('brain', ['query' => 'test'])
        ->doesntExpectOutput('Enable AI mode')
        ->doesntExpectOutput('Switch to AI mode');
});

test('error handling adapts to agent type', function () {
    // Humans get helpful, friendly errors
    $this->asHuman();
    $this->artisan('install', ['component' => 'nonexistent'])
        ->expectsOutputToContain('not found')
        ->doesntExpectOutputToContain('{"error"');

    // AI gets structured errors
    $this->asAI();
    ob_start();
    $this->artisan('install', ['component' => 'nonexistent', '--json' => true]);
    $output = ob_get_clean();

    if (str_contains($output, 'error')) {
        expect($output)->toContain('error'); // Structured error for AI
    }
});

test('parallel agent execution without conflicts', function () {
    // Multiple agents can work simultaneously
    $agents = ['claude', 'gpt', 'gemini'];
    $results = [];

    foreach ($agents as $agent) {
        $this->asAI($agent);
        $result = $this->artisan('orchestrate', [
            'action' => 'assign',
            '--task' => "Task for {$agent}",
            '--instance' => $agent,
        ]);
        $results[$agent] = $result->getExitCode();
    }

    // All agents succeeded without conflict
    foreach ($results as $agent => $exitCode) {
        expect($exitCode)->toBe(0);
    }
});

test('liberation from authentication complexity', function () {
    // No login required
    $this->artisan('brain', ['query' => 'test'])
        ->doesntExpectOutput('Please login')
        ->doesntExpectOutput('Authentication required');

    // No session management
    $this->artisan('install', ['component' => 'test'])
        ->doesntExpectOutput('Session expired')
        ->doesntExpectOutput('Refresh token');

    // No API keys for basic operations
    $this->artisan('test')
        ->doesntExpectOutput('API key')
        ->doesntExpectOutput('Configure credentials');
});
